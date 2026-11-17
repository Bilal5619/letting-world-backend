<?php

namespace App\Support;

use App\Models\User;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\PermissionException;
use Stripe\StripeClient;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * All Stripe calls. Card details never reach this server: cards are saved by Stripe's
 * payment sheet in the app, and we only keep Stripe's customer / payment-method IDs.
 * Bound in the container so tests can swap in a fake.
 */
class Payments
{
    /** Must match the API version the Stripe React Native SDK expects for ephemeral keys. */
    private const EPHEMERAL_KEY_VERSION = '2024-06-20';

    public function configured(): bool
    {
        return filled(config('services.stripe.secret')) && filled(config('services.stripe.key'));
    }

    /** @return list<array{id: string, brand: string, last4: string, expMonth: int, expYear: int, isDefault: bool}> */
    public function cards(User $user): array
    {
        if (! $this->configured() || ! $user->stripe_customer_id) {
            return [];
        }

        return $this->call(function (StripeClient $s) use ($user) {
            try {
                $methods = $s->customers->allPaymentMethods($user->stripe_customer_id, ['type' => 'card', 'limit' => 20])->data;
            } catch (InvalidRequestException $e) {
                // Customer from other Stripe keys: no cards here; adding one makes a new customer.
                if ($e->getStripeCode() === 'resource_missing') {
                    return [];
                }
                throw $e;
            }
            if (! $methods) {
                return [];
            }

            $default = $s->customers->retrieve($user->stripe_customer_id)->invoice_settings->default_payment_method ?? null;
            if (! $default || ! collect($methods)->contains('id', $default)) {
                // First saved card becomes the default automatically.
                $default = $methods[0]->id;
                $s->customers->update($user->stripe_customer_id, ['invoice_settings' => ['default_payment_method' => $default]]);
            }

            return collect($methods)->map(fn ($pm) => [
                'id' => $pm->id,
                'brand' => $pm->card->brand,
                'last4' => $pm->card->last4,
                'expMonth' => $pm->card->exp_month,
                'expYear' => $pm->card->exp_year,
                'isDefault' => $pm->id === $default,
            ])->sortByDesc('isDefault')->values()->all();
        });
    }

    public function defaultCard(User $user): ?array
    {
        return collect($this->cards($user))->firstWhere('isDefault', true);
    }

    /** Everything the app's PaymentSheet needs to save a new card. */
    public function setupSheet(User $user): array
    {
        return $this->call(function (StripeClient $s) use ($user) {
            $customerId = $this->customerId($user, $s);
            $ephemeralKey = $s->ephemeralKeys->create(['customer' => $customerId], ['stripe_version' => self::EPHEMERAL_KEY_VERSION]);
            $setupIntent = $s->setupIntents->create([
                'customer' => $customerId,
                'automatic_payment_methods' => [
    'enabled' => true,
    'allow_redirects' => 'never',
],
                'usage' => 'off_session', // allows one-click renewals to charge it later
            ]);

            return [
                'customerId' => $customerId,
                'ephemeralKey' => $ephemeralKey->secret,
                'setupIntentClientSecret' => $setupIntent->client_secret,
                'publishableKey' => config('services.stripe.key'),
            ];
        });
    }

    public function setDefault(User $user, string $paymentMethodId): void
    {
        $this->call(function (StripeClient $s) use ($user, $paymentMethodId) {
            $this->assertOwned($s, $user, $paymentMethodId);
            $s->customers->update($user->stripe_customer_id, ['invoice_settings' => ['default_payment_method' => $paymentMethodId]]);
        });
    }

    public function remove(User $user, string $paymentMethodId): void
    {
        $this->call(function (StripeClient $s) use ($user, $paymentMethodId) {
            $this->assertOwned($s, $user, $paymentMethodId);
            $s->paymentMethods->detach($paymentMethodId);
        });
    }

    /**
     * Charges the landlord's default card without them being present (one-click renewal).
     *
     * @return array{id: string, brand: string, last4: string}
     *
     * @throws HttpException 402 when the card is declined or needs authentication
     */
    public function charge(User $user, int $amountPence, string $description, array $metadata): array
    {
        $card = $this->defaultCard($user);
        abort_unless($card, 402, 'Add a payment method before booking.');

        try {
            $intent = (new StripeClient(config('services.stripe.secret')))->paymentIntents->create([
                'amount' => $amountPence,
                'currency' => 'gbp',
                'customer' => $user->stripe_customer_id,
                'payment_method' => $card['id'],
                'off_session' => true,
                'confirm' => true,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        } catch (CardException $e) {
            abort(402, 'Your card was declined: '.$e->getMessage().' Please update your payment method and try again.');
        } catch (ApiErrorException $e) {
            report($e);
            abort(502, 'The payment could not be taken. Please try again.');
        }

        abort_unless($intent->status === 'succeeded', 402, 'The payment needs extra verification. Please update your payment method.');

        return ['id' => $intent->id, 'brand' => $card['brand'], 'last4' => $card['last4']];
    }

    public function refund(string $paymentIntentId): void
    {
        $this->call(fn (StripeClient $s) => $s->refunds->create(['payment_intent' => $paymentIntentId]));
    }

    private function customerId(User $user, StripeClient $stripe): string
    {
        if ($user->stripe_customer_id) {
            try {
                if (! ($stripe->customers->retrieve($user->stripe_customer_id)->deleted ?? false)) {
                    return $user->stripe_customer_id;
                }
            } catch (InvalidRequestException $e) {
                if ($e->getStripeCode() !== 'resource_missing') {
                    throw $e;
                }
            }
            // Saved under other Stripe keys (e.g. test keys before going live) or deleted in
            // Stripe: start a fresh customer rather than failing every time.
        }

        $customer = $stripe->customers->create([
            'email' => $user->email,
            'name' => trim("{$user->first_name} {$user->last_name}"),
            'metadata' => ['user_id' => $user->id],
        ]);
        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /**
     * Checks the Stripe keys with a harmless read (admin panel "Test Stripe").
     *
     * @return array{ok: bool, message: string}
     */
    public function check(): array
    {
        $secret = (string) config('services.stripe.secret');
        $key = (string) config('services.stripe.key');

        if ($secret === '' || $key === '') {
            return ['ok' => false, 'message' => 'Stripe keys are not set. Add STRIPE_KEY (pk_...) and STRIPE_SECRET (sk_...) to .env, then run php artisan config:clear.'];
        }
        if (! str_starts_with($key, 'pk_')) {
            return ['ok' => false, 'message' => 'STRIPE_KEY should be the publishable key and start with pk_.'];
        }
        if (! str_starts_with($secret, 'sk_') && ! str_starts_with($secret, 'rk_')) {
            return ['ok' => false, 'message' => 'STRIPE_SECRET should be the secret key and start with sk_.'];
        }
        if (str_contains($key, '_test_') !== str_contains($secret, '_test_')) {
            return ['ok' => false, 'message' => 'One Stripe key is a test key and the other is live. Both must be test keys or both live keys.'];
        }

        try {
            (new StripeClient($secret))->balance->retrieve();
        } catch (ApiErrorException $e) {
            return ['ok' => false, 'message' => $this->explain($e).' Stripe said: '.$e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Connected to Stripe ('.(str_contains($secret, '_test_') ? 'test mode' : 'live mode').'). Cards can be added.'];
    }

    /** What went wrong, in words a landlord (or the admin) can act on. */
    private function explain(ApiErrorException $e): string
    {
        return match (true) {
            $e instanceof AuthenticationException => 'Card payments are not set up correctly: the Stripe secret key was rejected.',
            $e instanceof PermissionException => 'Card payments are not set up correctly: the Stripe key does not have permission for this.',
            $e instanceof ApiConnectionException => 'The payment provider could not be reached. Please try again.',
            default => 'The payment provider returned an error. Please try again.',
        };
    }

    /** Never let one landlord touch another customer's card. */
    private function assertOwned(StripeClient $s, User $user, string $paymentMethodId): void
    {
        abort_unless($user->stripe_customer_id, 404);
        abort_unless($s->paymentMethods->retrieve($paymentMethodId)->customer === $user->stripe_customer_id, 404);
    }

    private function call(callable $fn): mixed
    {
        abort_unless($this->configured(), 503, 'Card payments are not set up yet. Please try again later.');

        try {
            return $fn(new StripeClient(config('services.stripe.secret')));
        } catch (ApiErrorException $e) {
            report($e);
            abort(502, $this->explain($e));
        }
    }
}
