<?php

namespace App\Support;

use App\Models\Certificate;
use App\Models\Property;
use Illuminate\Support\Collection;

/**
 * Works out a property's compliance from its certificates. Only the latest certificate of
 * each type counts — an expired certificate that has been renewed is not flagged.
 */
class Compliance
{
    /**
     * Latest certificate per type. Expects `certificates` to be loaded.
     *
     * @return Collection<string, Certificate>
     */
    public static function current(Property $property): Collection
    {
        // Lets each certificate reuse the property's eager-loaded openBookings.
        $property->certificates->each->setRelation('property', $property);

        return $property->certificates
            ->groupBy('type')
            ->map(fn (Collection $certs) => $certs->sortByDesc(
                fn (Certificate $c) => [$c->expiry_date?->timestamp ?? PHP_INT_MAX, $c->id]
            )->first());
    }

    /** @return list<string> Required certificate types the property has no record of. */
    public static function missing(Property $property): array
    {
        $have = self::current($property)->keys();

        return collect(config('lettingworld.certificate_types'))
            ->filter(fn (array $t) => $t['required'] === true || ($t['required'] === 'gas' && $property->has_gas))
            ->keys()
            ->reject(fn (string $type) => $have->contains($type))
            ->values()
            ->all();
    }

    /** @return array{valid: int, expiring: int, expired: int, missing: int, renewalBooked: int} */
    public static function summary(Property $property): array
    {
        $statuses = self::current($property)->map(fn (Certificate $c) => $c->status());
        $count = fn (string ...$wanted) => $statuses->filter(fn ($s) => in_array($s, $wanted, true))->count();

        return [
            'valid' => $count(Certificate::STATUS_VALID, Certificate::STATUS_NO_EXPIRY),
            'expiring' => $count(Certificate::STATUS_EXPIRING),
            'expired' => $count(Certificate::STATUS_EXPIRED),
            'missing' => count(self::missing($property)),
            'renewalBooked' => $count(Certificate::STATUS_RENEWAL_BOOKED),
        ];
    }
}
