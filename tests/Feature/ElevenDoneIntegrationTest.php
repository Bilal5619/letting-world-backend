<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Notifications\BookingUpdated;
use App\Support\Payments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakePayments;
use Tests\TestCase;

/** Letting World ↔ 11Done: quote, hand-over after payment, webhooks, retries, cancellation. */
class ElevenDoneIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private FakePayments $payments;

    private User $landlord;

    private Property $property;

    private User $tenant;

    /** What 11Done's API currently says about the booking (mutated by tests). */
    private array $remote = [];

    /** @var list<array> bodies Letting World posted to 11Done's create-booking endpoint */
    private array $created = [];

    private bool $elevenDoneDown = false;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        config([
            'services.elevendone.url' => 'https://11done.test',
            'services.elevendone.api_key' => 'partner-key',
            'services.elevendone.webhook_secret' => self::SECRET,
        ]);

        $this->payments = new FakePayments;
        $this->app->instance(Payments::class, $this->payments);

        $this->landlord = User::factory()->create([
            'role' => 'landlord', 'first_name' => 'Bilal', 'last_name' => 'Hasnain', 'email' => 'bilal@example.com',
            'phone' => '07700900123', 'profile_completed_at' => now(),
        ]);
        $this->property = $this->landlord->properties()->create([
            'address_line1' => '12 High Street', 'city' => 'Glasgow', 'postcode' => 'G1 1AA', 'property_type' => 'flat',
            'bedrooms' => 2, 'bathrooms' => 1, 'furnishing' => 'furnished', 'has_gas' => true, 'status' => 'let',
        ]);
        $this->tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $this->landlord->id, 'first_name' => 'John', 'phone' => '07700900456']);
        $this->property->tenancies()->create(['tenant_id' => $this->tenant->id]);
        $this->property->certificates()->create(['type' => 'gas_safety', 'issue_date' => today()->subMonths(11)->toDateString(), 'expiry_date' => today()->addMonth()->toDateString()]);

        Http::fake(function (HttpRequest $request) {
            $url = $request->url();
            // Outage hits the hand-over only, so the landlord can still see a price and pay.
            if ($this->elevenDoneDown && ! str_ends_with($url, '/quote')) {
                throw new ConnectionException('down');
            }
            if (str_ends_with($url, '/quote')) {
                return Http::response(['success' => true, 'data' => [
                    'service_id' => 60, 'service_name' => 'Landlord Gas safety inspection', 'price' => 55.0, 'bookable' => true,
                    'reason' => null, 'min_date' => today()->addDays(3)->toDateString(),
                    'time_slots' => ['08:00 AM - 10:00 AM', '10:00 AM - 12:00 PM', '02:00 PM - 04:00 PM', '04:00 PM - 06:00 PM'],
                ]]);
            }
            if (str_ends_with($url, '/bookings') && $request->method() === 'POST') {
                $this->created[] = $request->data();
                $this->remote = ['external_reference' => $request['external_reference'], 'order_number' => '11D-5001', 'order_status' => 'pending'];

                return Http::response(['success' => true, 'data' => $this->remote], 201);
            }
            if (str_ends_with($url, '/cancel')) {
                $this->remote['order_status'] = 'rejected';

                return Http::response(['success' => true, 'data' => $this->remote]);
            }
            if (str_contains($url, '/bookings/')) {
                return Http::response(['success' => true, 'data' => $this->remote]);
            }
            if (str_contains($url, '/certificates/')) {
                return Http::response('%PDF-1.4 new gas safety certificate', 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response('not found', 404);
        });
    }

    private function book(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->landlord)->postJson('/api/bookings', $overrides + [
            'propertyId' => $this->property->id, 'serviceType' => 'gas_safety',
            'preferredDate' => today()->addDays(5)->toDateString(), 'timeSlot' => '10:00 AM - 12:00 PM',
            'access' => 'landlord', 'notifyTenant' => true,
        ]);
    }

    private function webhook(array $data, ?string $secret = self::SECRET)
    {
        $body = json_encode(['event' => 'booking.updated', 'data' => $data]);

        return $this->call('POST', '/api/integrations/11done/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, (string) $secret),
        ], $body);
    }

    public function test_quote_uses_11done_price_dates_and_slots(): void
    {
        $this->actingAs($this->landlord)
            ->getJson("/api/bookings/quote?propertyId={$this->property->id}&serviceType=gas_safety")
            ->assertOk()
            ->assertJsonPath('provider', '11done')
            ->assertJsonPath('price', 55)
            ->assertJsonPath('bookable', true)
            ->assertJsonPath('minDate', today()->addDays(3)->toDateString())
            ->assertJsonPath('timeSlots.1', '10:00 AM - 12:00 PM')
            ->assertJsonPath('tenantCanSchedule', true);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/quote')
            && $r->hasHeader('X-Partner-Key', 'partner-key')
            && $r['service_id'] === 60 && $r['postcode'] === 'G1 1AA' && $r['property']['bedrooms'] === 2);
    }

    public function test_after_payment_the_booking_is_created_in_11done(): void
    {
        $booking = $this->book()->assertCreated()
            ->assertJsonPath('paymentStatus', 'paid')
            ->assertJsonPath('syncStatus', 'sent')
            ->assertJsonPath('providerReference', '11D-5001')
            ->json();

        $this->assertSame(5500, $this->payments->charges[0]['amountPence']); // 11Done's price, not a placeholder
        $sent = $this->created[0];
        $this->assertSame($booking['reference'], $sent['external_reference']);
        $this->assertSame(60, $sent['service_id']);
        $this->assertEquals(55.0, $sent['amount_paid']);
        $this->assertSame('customer', $sent['scheduling']);
        $this->assertSame('10:00 AM - 12:00 PM', $sent['time_slot']);
        $this->assertSame('bilal@example.com', $sent['landlord']['email']);
        $this->assertSame('G1 1AA', $sent['property']['postcode']);
        $this->assertSame(2, $sent['property']['bedrooms']);
    }

    public function test_dates_must_follow_11done_rules(): void
    {
        $this->book(['preferredDate' => today()->addDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors(['preferredDate']);
        $this->book(['timeSlot' => 'morning'])->assertStatus(422)->assertJsonValidationErrors(['timeSlot']);
        $this->assertSame(0, Booking::count());
    }

    public function test_tenant_can_choose_the_time_through_11done(): void
    {
        $this->book(['scheduling' => 'tenant', 'access' => 'tenant', 'preferredDate' => null, 'timeSlot' => null])->assertCreated();

        $this->assertSame('tenant', $this->created[0]['scheduling']);
        $this->assertArrayNotHasKey('booking_date', $this->created[0]);
        $this->assertSame($this->tenant->phone, $this->created[0]['tenant']['phone']);
    }

    public function test_if_11done_is_down_the_booking_is_kept_and_sent_later(): void
    {
        $this->elevenDoneDown = true;
        $booking = $this->book()->assertCreated()->assertJsonPath('syncStatus', 'failed')->json();
        $this->assertSame(1, Booking::count()); // paid booking is not lost

        $this->elevenDoneDown = false;
        $this->artisan('elevendone:sync')->assertSuccessful();
        $this->assertSame('sent', Booking::find($booking['id'])->sync_status);
        $this->assertCount(1, $this->created);
    }

    public function test_updates_from_11done_flow_back_and_the_new_certificate_is_added(): void
    {
        $booking = $this->book()->assertCreated()->json();
        $ref = $booking['reference'];

        $this->webhook(['external_reference' => $ref, 'order_status' => 'accepted'], 'wrong-secret')->assertStatus(401);

        // Engineer assigned.
        $this->webhook([
            'external_reference' => $ref, 'order_number' => '11D-5001', 'order_status' => 'accepted',
            'engineer' => ['id' => 13, 'name' => 'Northern Gas Ltd'], 'booking_date' => today()->addDays(5)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '12:00:00',
        ])->assertOk();
        $b = Booking::find($booking['id']);
        $this->assertSame('confirmed', $b->status);
        $this->assertSame('Northern Gas Ltd', $b->engineer_name);
        $this->assertSame('10:00 AM - 12:00 PM', $b->scheduled_slot);
        Notification::assertSentTo($this->landlord, BookingUpdated::class, fn ($n) => $n->change === 'confirmed');
        Notification::assertSentTo($this->tenant, BookingUpdated::class, fn ($n, $channels, $tenant) => $n->change === 'confirmed'
            && str_contains($n->toArray($tenant)['body'], 'Northern Gas Ltd'));

        // Job done: certificate comes back and renews the property's Gas Safety.
        $newExpiry = today()->addYear()->toDateString();
        $this->webhook([
            'external_reference' => $ref, 'order_number' => '11D-5001', 'order_status' => 'completed',
            'engineer' => ['id' => 13, 'name' => 'Northern Gas Ltd'], 'completed_at' => now()->toIso8601String(),
            'certificate' => ['url' => 'https://11done.test/assets/file/certificates/cp12.pdf', 'file_name' => 'cp12.pdf', 'expiry_date' => $newExpiry],
        ])->assertOk();

        $b->refresh();
        $this->assertSame('completed', $b->status);
        $this->assertNotNull($b->result_certificate_id);
        $this->actingAs($this->landlord)->getJson("/api/certificates?propertyId={$this->property->id}")
            ->assertJsonFragment(['expiryDate' => $newExpiry, 'status' => 'valid', 'providerName' => 'Northern Gas Ltd']);
        Notification::assertSentTo($this->landlord, BookingUpdated::class, fn ($n) => $n->change === 'completed');

        // Repeated webhook doesn't add a second certificate.
        $this->webhook(['external_reference' => $ref, 'order_status' => 'completed', 'certificate' => ['url' => 'https://11done.test/assets/file/certificates/cp12.pdf']])->assertOk();
        $this->assertSame(2, $this->property->certificates()->count());
    }

    public function test_rejected_in_11done_cancels_and_refunds(): void
    {
        $booking = $this->book()->assertCreated()->json();
        $this->webhook(['external_reference' => $booking['reference'], 'order_status' => 'rejected'])->assertOk();

        $b = Booking::find($booking['id']);
        $this->assertSame('cancelled', $b->status);
        $this->assertSame('refunded', $b->payment_status);
        $this->assertSame([$b->payment_intent_id], $this->payments->refunds);
    }

    public function test_cancelling_in_letting_world_cancels_in_11done(): void
    {
        $booking = $this->book()->assertCreated()->json();
        $this->postJson("/api/bookings/{$booking['id']}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), "/bookings/{$booking['reference']}/cancel"));
        $this->assertCount(1, $this->payments->refunds);
    }
}
