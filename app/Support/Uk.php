<?php

namespace App\Support;

/** UK-specific formats: postcodes and phone numbers. */
class Uk
{
    /** Covers all valid UK postcode formats, e.g. "SW1A 1AA", "M1 1AE", "B33 8TH". */
    public const POSTCODE_REGEX = '/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i';

    /** 07xxx / 01xxx / 02xxx or +44 equivalents, after normalisePhone(). */
    public const PHONE_REGEX = '/^(?:\+44|0)\d{9,10}$/';

    public static function normalisePhone(?string $phone): ?string
    {
        $phone = preg_replace('/[\s()\-]/', '', (string) $phone);

        return $phone === '' ? null : $phone;
    }

    public static function formatPostcode(?string $postcode): ?string
    {
        $compact = strtoupper(preg_replace('/\s+/', '', (string) $postcode));
        if ($compact === '') {
            return null;
        }

        return strlen($compact) > 3 ? substr($compact, 0, -3).' '.substr($compact, -3) : $compact;
    }
}
