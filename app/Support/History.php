<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Certificate;
use App\Models\Document;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\PropertyEvent;
use App\Models\Tenancy;
use App\Models\TenancyAgreement;
use Throwable;

/**
 * Property history timeline. Entries are written automatically from model events
 * (registered once in AppServiceProvider), so every screen, job and seeder is covered.
 */
class History
{
    public static function record(int $propertyId, string $type, string $title, ?string $description = null, array $meta = []): void
    {
        try {
            PropertyEvent::create([
                'property_id' => $propertyId,
                'user_id' => request()?->user()?->id,
                'type' => $type,
                'title' => mb_substr($title, 0, 255),
                'description' => $description ? mb_substr($description, 0, 255) : null,
                'meta' => $meta ?: null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e); // history must never break the action itself
        }
    }

    public static function register(): void
    {
        Property::created(fn (Property $p) => self::record($p->id, 'property_added', 'Property added', $p->fullAddress()));
        Property::updated(function (Property $p) {
            if ($p->wasChanged('archived_at')) {
                self::record($p->id, $p->archived_at ? 'property_archived' : 'property_restored', $p->archived_at ? 'Property archived' : 'Property restored');
            } elseif ($p->wasChanged('photo_path')) {
                self::record($p->id, 'property_photo', $p->photo_path ? 'Property photo updated' : 'Property photo removed');
            } elseif ($p->wasChanged(['address_line1', 'address_line2', 'city', 'postcode', 'property_type', 'bedrooms', 'bathrooms', 'furnishing', 'has_gas', 'monthly_rent', 'reference', 'notes'])) {
                self::record($p->id, 'property_updated', 'Property details updated');
            } elseif ($p->wasChanged('status')) {
                self::record($p->id, 'property_status', 'Status changed to '.str_replace('_', ' ', $p->status));
            }
        });

        Certificate::created(fn (Certificate $c) => self::record(
            $c->property_id, 'certificate_added', Certificate::typeLabel($c->type).' added',
            $c->expiry_date ? 'Expires '.$c->expiry_date->format('j M Y') : null,
            ['certificateId' => $c->id],
        ));
        Certificate::deleted(fn (Certificate $c) => self::record($c->property_id, 'certificate_deleted', Certificate::typeLabel($c->type).' deleted'));

        Document::created(fn (Document $d) => self::record(
            $d->property_id, 'document_added', "Document uploaded: {$d->title}",
            config("lettingworld.document_categories.{$d->category}"), ['documentId' => $d->id],
        ));
        Document::updated(function (Document $d) {
            if ($d->wasChanged('archived_at')) {
                self::record($d->property_id, $d->archived_at ? 'document_archived' : 'document_restored', ($d->archived_at ? 'Document archived: ' : 'Document restored: ').$d->title);
            } elseif ($d->wasChanged('file_path')) {
                self::record($d->property_id, 'document_replaced', "Document replaced: {$d->title}", 'New file: '.$d->file_name);
            }
        });
        Document::deleted(fn (Document $d) => self::record($d->property_id, 'document_deleted', "Document deleted: {$d->title}"));

        Tenancy::created(fn (Tenancy $t) => self::record($t->property_id, 'tenant_assigned', self::tenantName($t).' assigned to the property', null, ['tenantId' => $t->tenant_id]));
        Tenancy::deleted(fn (Tenancy $t) => self::record($t->property_id, 'tenant_removed', self::tenantName($t).' removed from the property'));

        TenancyAgreement::created(fn (TenancyAgreement $a) => self::record(
            $a->property_id, 'agreement_added', 'Tenancy agreement added',
            'From '.$a->start_date->format('j M Y').($a->end_date ? ' to '.$a->end_date->format('j M Y') : ''),
            ['agreementId' => $a->id],
        ));
        TenancyAgreement::updated(fn (TenancyAgreement $a) => self::record($a->property_id, 'agreement_updated', 'Tenancy agreement updated', null, ['agreementId' => $a->id]));
        TenancyAgreement::deleted(fn (TenancyAgreement $a) => self::record($a->property_id, 'agreement_deleted', 'Tenancy agreement deleted'));

        Booking::created(fn (Booking $b) => self::record(
            $b->property_id, 'booking_created', Certificate::typeLabel($b->service_type).' renewal booked',
            ($b->preferred_date ? 'For '.$b->preferred_date->format('j M Y') : 'Tenant will choose the time')." · Ref {$b->reference}", ['bookingId' => $b->id],
        ));
        Booking::updated(function (Booking $b) {
            if ($b->wasChanged('status')) {
                self::record($b->property_id, "booking_{$b->status}", Certificate::typeLabel($b->service_type)." booking {$b->status}", "Ref {$b->reference}", ['bookingId' => $b->id]);
            }
        });

        MaintenanceRequest::created(fn (MaintenanceRequest $m) => self::record(
            $m->property_id, 'maintenance_reported', "Repair reported: {$m->title}",
            MaintenanceRequest::categoryLabel($m->category).' · '.ucfirst($m->priority).' priority', ['maintenanceId' => $m->id],
        ));
        MaintenanceRequest::updated(function (MaintenanceRequest $m) {
            if ($m->wasChanged('status')) {
                self::record($m->property_id, "maintenance_{$m->status}", 'Repair '.str_replace('_', ' ', $m->status).": {$m->title}", null, ['maintenanceId' => $m->id]);
            }
        });
    }

    private static function tenantName(Tenancy $t): string
    {
        $tenant = $t->relationLoaded('tenant') ? $t->tenant : $t->tenant()->first();

        return $tenant ? trim("{$tenant->first_name} {$tenant->last_name}") : 'A tenant';
    }
}
