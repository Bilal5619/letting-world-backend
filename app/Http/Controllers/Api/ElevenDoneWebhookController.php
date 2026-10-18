<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Support\ElevenDone;
use App\Support\ElevenDoneSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives booking updates from 11Done (signed with a shared secret). Unknown references are
 * acknowledged and ignored; the scheduled sync also polls, so nothing depends on this alone.
 */
class ElevenDoneWebhookController extends Controller
{
    public function __invoke(Request $request, ElevenDone $elevenDone, ElevenDoneSync $sync): JsonResponse
    {
        if (! $elevenDone->validSignature($request->getContent(), $request->header('X-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $data = $request->json('data') ?? [];
        $booking = Booking::where('reference', $data['external_reference'] ?? '')->first();
        if ($booking) {
            $sync->apply($booking, $data);
        }

        return response()->json(['ok' => true]);
    }
}
