<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Notifications\CertificateExpiryAlert;
use App\Notifications\TenancyEndingAlert;
use App\Notifications\TenantAppointmentNotice;
use App\Support\Payments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakePayments;
use Tests\TestCase;

class RenewalAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    private FakePayments $payments;

    private User $landlord;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::fake(['api.postcodes.io/*' => Http::response(['result' => ['latitude' => 55.86, 'longitude' => -4.25]])]);
        Notification::fake();

        $this->payments = new FakePayments;
        $this->app->instance(Payments::class, $this->payments);

        $this->landlord = User::factory()->create([
            'role' => 'landlord', 'first_name' => 'Bilal', 'phone' => '07700900123', 'profile_completed_at' => now(),
        ]);
        $this->property = $this->landlord->properties()->create([
            'address_line1' => '12 High Street', 'city' => 'Glasgow', 'postcode' => 'G1 1AA', 'property_type' => 'flat',
            'bedrooms' => 2, 'bathrooms' => 1, 'furnishing' => 'furnished', 'has_gas' => true, 'status' => 'let',
        ]);
    }

    private function certificate(string $type, int $daysUntilExpiry): Certificate
    {
        return $this->property->certificates()->create([
            'type' => $type,
            'issue_date' => today()->addDays($daysUntilExpiry)->subYear()->toDateString(),
            'expiry_date' => today()->addDays($daysUntilExpiry)->toDateString(),
        ]);
    }

    private function tenant(string $email): User
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $this->landlord->id, 'email' => $email]);
        $this->property->tenancies()->create(['tenant_id' => $tenant->id]);

        return $tenant;
    }

    public function test_one_click_renewal_charges_the_saved_card_and_marks_renewal_booked(): void
    {
        $certificate = $this->certificate('gas_safety', 12);
        $tenant = $this->tenant('john@gmail.com');
        $this->actingAs($this->landlord);

        // The confirmation screen is pre-filled: nothing to type except the date.
        $this->getJson("/api/bookings/quote?propertyId={$this->property->id}&serviceType=gas_safety&certificateId={$certificate->id}")
            ->assertOk()
            ->assertJsonPath('property.shortAddress', '12 High Street, Glasgow')
            ->assertJsonPath('certificate.expiryDate', $certificate->expiry_date->toDateString())
            ->assertJsonPath('price', 69)
            ->assertJsonPath('card.last4', '4242')
            ->assertJsonPath('tenants.0.name', "{$tenant->first_name} {$tenant->last_name}");

        $booking = $this->postJson('/api/bookings', [
            'propertyId' => $this->property->id, 'serviceType' => 'gas_safety', 'certificateId' => $certificate->id,
            'preferredDate' => today()->addWeek()->toDateString(), 'timeSlot' => '08:00 AM - 10:00 AM',
            'access' => 'tenant', 'notifyTenant' => true,
        ])->assertCreated()
            ->assertJsonPath('paymentStatus', 'paid')
            ->assertJsonPath('card.last4', '4242')
            ->json();

        $this->assertSame(6900, $this->payments->charges[0]['amountPence']);
        $this->assertSame($booking['reference'], $this->payments->charges[0]['metadata']['booking_reference']);

        // Certificate now shows "Renewal booked" and no longer needs attention.
        $this->getJson("/api/certificates?propertyId={$this->property->id}")->assertJsonPath('0.status', 'renewal_booked');
        $dashboard = $this->getJson('/api/dashboard')->assertOk();
        $this->assertSame(1, $dashboard->json('compliance.renewalBooked'));
        $this->assertNotContains('gas_safety', collect($dashboard->json('actions'))->pluck('certificateType')->all());

        Notification::assertSentTo($this->landlord, BookingConfirmed::class);
        Notification::assertSentTo($tenant, TenantAppointmentNotice::class);

        // Can't double-book; cancelling refunds the card.
        $this->postJson('/api/bookings', [
            'propertyId' => $this->property->id, 'serviceType' => 'gas_safety', 'preferredDate' => today()->addWeek()->toDateString(),
            'timeSlot' => '08:00 AM - 10:00 AM', 'access' => 'landlord', 'notifyTenant' => false,
        ])->assertStatus(422);
        $this->postJson("/api/bookings/{$booking['id']}/cancel")->assertOk()->assertJsonPath('paymentStatus', 'refunded');
        $this->assertSame(['pi_test_1'], $this->payments->refunds);
    }

    public function test_booking_without_a_saved_card_is_refused_and_nothing_is_created(): void
    {
        $this->payments->hasCard = false;
        $this->actingAs($this->landlord)->postJson('/api/bookings', [
            'propertyId' => $this->property->id, 'serviceType' => 'eicr', 'preferredDate' => today()->addWeek()->toDateString(),
            'timeSlot' => '08:00 AM - 10:00 AM', 'access' => 'other', 'accessNotes' => 'Key safe 1234', 'notifyTenant' => false,
        ])->assertStatus(402);

        $this->assertSame(0, $this->landlord->bookings()->count());
    }

    public function test_daily_alerts_go_out_once_per_threshold_and_skip_booked_renewals(): void
    {
        $gas = $this->certificate('gas_safety', 25);   // inside the 30-day window
        $eicr = $this->certificate('eicr', -3);        // just expired
        $this->certificate('epc', 400);               // valid — no alert
        $tenant = $this->tenant('sarah@gmail.com');
        Tenancy::where('tenant_id', $tenant->id)->update(['end_date' => today()->addDays(20)->toDateString()]);

        $this->artisan('alerts:send-expiry')->assertSuccessful();
        Notification::assertSentToTimes($this->landlord, CertificateExpiryAlert::class, 2);
        Notification::assertSentToTimes($this->landlord, TenancyEndingAlert::class, 1);
        Notification::assertSentTo($this->landlord, CertificateExpiryAlert::class, fn ($n) => $n->certificate->is($gas)
            && str_contains($n->toArray($this->landlord)['body'], '12 High Street, Glasgow'));

        // Running again the same day sends nothing new.
        $this->artisan('alerts:send-expiry')->assertSuccessful();
        Notification::assertSentToTimes($this->landlord, CertificateExpiryAlert::class, 2);

        // A week later: gas still has 18 days (same 30-day window, nothing new); EICR gets its weekly reminder.
        $this->travel(7)->days();
        $this->artisan('alerts:send-expiry')->assertSuccessful();
        Notification::assertSentToTimes($this->landlord, CertificateExpiryAlert::class, 3);

        // Once a renewal is booked, that certificate gets no more alerts (EICR keeps its weekly reminders).
        $this->landlord->bookings()->create([
            'property_id' => $this->property->id, 'service_type' => 'gas_safety', 'preferred_date' => today()->addDay(), 'status' => 'requested',
        ]);
        $this->travel(5)->days(); // gas would now be inside the 14-day window
        $this->artisan('alerts:send-expiry')->assertSuccessful();

        $sentFor = fn (Certificate $c) => Notification::sent($this->landlord, CertificateExpiryAlert::class)
            ->filter(fn ($n) => $n->certificate->is($c))->count();
        $this->assertSame(1, $sentFor($gas));
        $this->assertSame(3, $sentFor($eicr)); // expired, expired:1, expired:2
    }

    public function test_add_property_with_photo_then_assign_several_tenants_at_once(): void
    {
        $this->actingAs($this->landlord);
        $john = User::factory()->create(['role' => 'tenant', 'landlord_id' => $this->landlord->id]);
        $sarah = User::factory()->create(['role' => 'tenant', 'landlord_id' => $this->landlord->id]);
        $someoneElses = User::factory()->create(['role' => 'tenant', 'landlord_id' => User::factory()->create()->id]);

        $id = $this->postJson('/api/properties', [
            'reference' => 'High St flat', 'addressLine1' => '12 High Street', 'city' => 'Glasgow', 'postcode' => 'g11aa',
            'propertyType' => 'flat', 'bedrooms' => 2, 'bathrooms' => 1, 'furnishing' => 'furnished', 'hasGas' => true,
            'notes' => 'Bins collected Tuesday', 'status' => 'vacant',
        ])->assertCreated()->assertJsonPath('reference', 'High St flat')->assertJsonPath('notes', 'Bins collected Tuesday')->json('id');

        $this->post("/api/properties/{$id}/photo", ['photo' => UploadedFile::fake()->image('front.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('photoUrl', fn ($url) => str_contains($url, '/api/files/property/'));

        // Another landlord's tenant can't be assigned.
        $this->postJson("/api/properties/{$id}/tenants", ['tenantIds' => [$john->id, $someoneElses->id]])->assertStatus(422);

        $this->postJson("/api/properties/{$id}/tenants", ['tenantIds' => [$john->id, $sarah->id]])->assertCreated()->assertJsonCount(2);
        $this->postJson("/api/properties/{$id}/tenants", ['tenantIds' => [$john->id]])->assertCreated()->assertJsonCount(2); // no duplicates
        $this->getJson("/api/properties/{$id}")->assertJsonPath('status', 'let')->assertJsonPath('tenantCount', 2);
    }

    public function test_notifications_can_be_listed_and_marked_read(): void
    {
        Notification::swap(new ChannelManager($this->app)); // real database channel
        config(['mail.default' => 'array']);
        $this->certificate('gas_safety', 10);
        $this->artisan('alerts:send-expiry');

        $this->actingAs($this->landlord);
        $list = $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread', 1)
            ->assertJsonPath('items.0.kind', 'certificate_expiry')
            ->assertJsonPath('items.0.title', 'Gas Safety Certificate (CP12) expiring');
        $this->postJson('/api/notifications/'.$list->json('items.0.id').'/read')->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('unread', 0);
    }
}
