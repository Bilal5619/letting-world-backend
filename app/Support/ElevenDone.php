<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client for 11Done's Letting World partner API. Bound in the container so tests can
 * fake it. "Enabled" only when both the URL and key are configured.
 */
class ElevenDone
{
    public function enabled(): bool
    {
        return filled(config('services.elevendone.url')) && filled(config('services.elevendone.api_key'));
    }

    /** 11Done service ID for a certificate type, or null if 11Done doesn't offer it. */
    public function serviceFor(string $certificateType): ?int
    {
        $id = config("lettingworld.elevendone_services.$certificateType");

        return $id ? (int) $id : null;
    }

    /** @return array{price: float, bookable: bool, reason: ?string, min_date: string, time_slots: list<string>, service_name: string} */
    /** Services Letting World can book (also used by the admin panel's connection test). */
    public function services(): array
    {
        return $this->data($this->http()->get('services'));
    }

    public function quote(int $serviceId, string $postcode, array $property): array
    {
        return $this->data($this->http()->post('quote', [
            'service_id' => $serviceId,
            'postcode' => $postcode,
            'property' => $property,
        ]));
    }

    /** Creates (or, for a repeated reference, returns) the paid booking in 11Done. */
    public function createBooking(array $payload): array
    {
        return $this->data($this->http()->post('bookings', $payload));
    }

    public function booking(string $reference): array
    {
        return $this->data($this->http()->get('bookings/'.rawurlencode($reference)));
    }

    public function cancel(string $reference): array
    {
        return $this->data($this->http()->post('bookings/'.rawurlencode($reference).'/cancel'));
    }

    /** True when an inbound webhook really came from 11Done. */
    public function validSignature(string $body, ?string $signature): bool
    {
        $secret = (string) config('services.elevendone.webhook_secret');

        return $secret !== '' && $signature !== null && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    private function http(): PendingRequest
    {
        if (! $this->enabled()) {
            throw new RuntimeException('11Done is not connected.');
        }

        return Http::baseUrl(config('services.elevendone.url').'/api/partner/letting-world/')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.elevendone.timeout', 10))
            ->withHeaders(['X-Partner-Key' => config('services.elevendone.api_key')]);
    }

    private function data(Response $response): array
    {
        if ($response->failed()) {
            $message = $response->json('message') ?: "11Done returned HTTP {$response->status()}";
            throw new RuntimeException($message, $response->status());
        }

        return $response->json('data') ?? [];
    }

    /** Wraps network failures in a friendly message. */
    public static function attempt(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (ConnectionException) {
            throw new RuntimeException('11Done could not be reached. Please try again in a moment.');
        }
    }
}
