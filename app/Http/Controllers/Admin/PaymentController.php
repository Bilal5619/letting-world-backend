<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Certificate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Renewal payments taken through Stripe (charges and refunds). */
class PaymentController extends Controller
{
    private const STATUSES = ['paid', 'refunded', 'failed', 'pending', 'not_charged'];

    public function index(Request $request): View
    {
        $query = $this->query($request);

        $totals = Booking::query()->selectRaw("
            SUM(CASE WHEN payment_status = 'paid' THEN price ELSE 0 END) AS collected,
            SUM(CASE WHEN payment_status = 'refunded' THEN price ELSE 0 END) AS refunded,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN payment_status = 'refunded' THEN 1 ELSE 0 END) AS refunded_count,
            SUM(CASE WHEN payment_status = 'paid' AND created_at >= ? THEN price ELSE 0 END) AS this_month
        ", [now()->startOfMonth()])->first();

        return view('admin.payments.index', [
            'payments' => $query->with('property', 'landlord')->latest()->paginate(25)->withQueryString(),
            'totals' => $totals,
            'status' => $request->query('status'),
        ]);
    }

    /** CSV of the filtered list, for accounting. */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->with('property', 'landlord')->latest()->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Booking', 'Landlord', 'Email', 'Property', 'Service', 'Amount (GBP)', 'Payment status', 'Card', 'Stripe payment', 'Fulfilled by', '11Done order']);
            foreach ($rows as $b) {
                fputcsv($out, [
                    $b->created_at->format('Y-m-d H:i'), $b->reference, $b->landlord?->fullName(), $b->landlord?->email,
                    $b->property?->fullAddress(), Certificate::typeLabel($b->service_type), number_format((float) $b->price, 2, '.', ''),
                    $b->payment_status, $b->card_last4 ? strtoupper((string) $b->card_brand).' '.$b->card_last4 : '',
                    $b->payment_intent_id, $b->provider === '11done' ? '11Done' : 'Letting World', $b->provider_reference,
                ]);
            }
            fclose($out);
        }, 'letting-world-payments-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function query(Request $request): Builder
    {
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : null;

        return Booking::query()
            ->whereNotNull('price')
            ->when($status, fn ($q) => $q->where('payment_status', $status))
            ->when(trim((string) $request->query('q')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $like)->orWhere('payment_intent_id', 'like', $like)
                    ->orWhereHas('landlord', fn ($l) => $l->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])->orWhere('email', 'like', $like)));
            });
    }
}
