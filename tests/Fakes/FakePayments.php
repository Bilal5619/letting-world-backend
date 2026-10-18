<?php

namespace Tests\Fakes;

use App\Models\User;
use App\Support\Payments;

/** Stands in for Stripe: one saved Visa ending 4242. */
class FakePayments extends Payments
{
    public array $charges = [];

    public array $refunds = [];

    public bool $hasCard = true;

    public function configured(): bool
    {
        return true;
    }

    public function cards(User $user): array
    {
        return $this->hasCard
            ? [['id' => 'pm_test', 'brand' => 'visa', 'last4' => '4242', 'expMonth' => 5, 'expYear' => 2029, 'isDefault' => true]]
            : [];
    }

    public function charge(User $user, int $amountPence, string $description, array $metadata): array
    {
        abort_unless($this->hasCard, 402, 'Add a payment method before booking.');
        $this->charges[] = compact('amountPence', 'description', 'metadata');

        return ['id' => 'pi_test_'.count($this->charges), 'brand' => 'visa', 'last4' => '4242'];
    }

    public function refund(string $paymentIntentId): void
    {
        $this->refunds[] = $paymentIntentId;
    }
}
