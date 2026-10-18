<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'reference' => $this->reference,
            'propertyId' => (string) $this->property_id,
            'propertyAddress' => $this->whenLoaded('property', fn () => $this->property->shortAddress()),
            'certificateId' => $this->certificate_id ? (string) $this->certificate_id : null,
            'serviceType' => $this->service_type,
            'serviceLabel' => Certificate::typeLabel($this->service_type),
            'preferredDate' => $this->preferred_date?->toDateString(),
            'timeSlot' => $this->time_slot,
            'access' => $this->access,
            'accessNotes' => $this->access_notes,
            'notifyTenant' => $this->notify_tenant,
            'status' => $this->status,
            'price' => $this->price,
            'paymentStatus' => $this->payment_status,
            'card' => $this->card_last4 ? ['brand' => $this->card_brand, 'last4' => $this->card_last4] : null,
            'provider' => $this->provider,
            'scheduling' => $this->scheduling,
            // 11Done link: order number, sync state, engineer and confirmed appointment.
            'providerReference' => $this->provider_reference,
            'providerStatus' => $this->provider_status,
            'syncStatus' => $this->sync_status,
            'engineerName' => $this->engineer_name,
            'scheduledDate' => $this->scheduled_date?->toDateString(),
            'scheduledSlot' => $this->scheduled_slot,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'resultCertificateId' => $this->result_certificate_id ? (string) $this->result_certificate_id : null,
            'cancellable' => $this->isCancellable(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
