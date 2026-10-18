<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** UK postcode → latitude/longitude via postcodes.io (free, no API key). */
class Geocoder
{
    /** @return array{0: float, 1: float}|null */
    public static function postcode(string $postcode): ?array
    {
        try {
            $response = Http::timeout(5)->acceptJson()
                ->get('https://api.postcodes.io/postcodes/'.rawurlencode($postcode));

            $result = $response->json('result');
            if ($response->successful() && isset($result['latitude'], $result['longitude'])) {
                return [(float) $result['latitude'], (float) $result['longitude']];
            }
        } catch (Throwable $e) {
            Log::info('Postcode lookup failed', ['postcode' => $postcode, 'error' => $e->getMessage()]);
        }

        // A failed lookup never blocks saving the property — it just has no map pin.
        return null;
    }
}
