<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Landlord-only: saved cards (Account → Payment Methods). */
class PaymentMethodController extends Controller
{
    public function __construct(private Payments $payments) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'configured' => $this->payments->configured(),
            'methods' => $this->payments->cards($request->user()),
        ]);
    }

    public function setup(Request $request): JsonResponse
    {
        return response()->json($this->payments->setupSheet($request->user()));
    }

    public function makeDefault(Request $request, string $id): JsonResponse
    {
        $this->payments->setDefault($request->user(), $id);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->payments->remove($request->user(), $id);

        return response()->json(['ok' => true]);
    }
}
