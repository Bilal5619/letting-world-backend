<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\Certificate;
use App\Models\Property;
use App\Models\User;
use App\Notifications\BookingUpdated;
use App\Support\Payments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakePayments;
use Tests\TestCase;

/** /admin: sign-in, every page, and every action an admin can take. */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private FakePayments $payments;

    private User $admin;

    private User $landlord;

    private User $tenant;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Notification::fake();
        config([
            'services.elevendone.url' => 'https://11done.test',
            'services.elevendone.api_key' => 'partner-key',
            'services.elevendone.webhook_secret' => 'secret',
        ]);
        Http::fake(function (HttpRequest $request) {
            $url = $request->url();
            if (str_ends_with($url, '/services')) {
                return Http::response(['success' => true, 'data' => ['services' => [['id' => 60, 'name' => 'Landlord Gas safety inspection']]]]);
            }
            if (str_ends_with($url, '/quote')) {
                return Http::response(['success' => true, 'data' => ['price' => 55, 'bookable' => true, 'min_date' => today()->addDays(3)->toDateString(),
                    'time_slots' => Booking::TIME_SLOTS]]);
            }
            if (str_ends_with($url, '/bookings') && $request->method() === 'POST') {
                return Http::response(['success' => true, 'data' => ['external_reference' => $request['external_reference'], 'order_number' => '11D-9001', 'order_status' => 'pending']], 201);
            }
            if (str_ends_with($url, '/cancel')) {
                return Http::response(['success' => true, 'data' => ['order_status' => 'rejected']]);
            }

            return Http::response(['success' => true, 'data' => ['order_status' => 'accepted', 'order_number' => '11D-9001', 'engineer' => ['name' => 'Tom Reid']]]);
        });

        $this->payments = new FakePayments;
        $this->app->instance(Payments::class, $this->payments);

        $this->admin = User::factory()->create(['role' => 'admin', 'first_name' => 'Sarah', 'last_name' => 'Mitchell', 'email' => 'sarah@lettingworld.test', 'password' => 'Password123']);
        $this->landlord = User::factory()->create(['role' => 'landlord', 'first_name' => 'Bilal', 'last_name' => 'Hasnain', 'password' => 'Password123', 'profile_completed_at' => now()]);
        $this->property = $this->landlord->properties()->create([
            'address_line1' => '12 High Street', 'city' => 'Glasgow', 'postcode' => 'G1 1AA', 'property_type' => 'flat',
            'bedrooms' => 2, 'bathrooms' => 1, 'furnishing' => 'furnished', 'has_gas' => true, 'status' => 'let',
        ]);
        $this->tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $this->landlord->id, 'first_name' => 'John', 'password' => 'Password123']);
        $this->property->tenancies()->create(['tenant_id' => $this->tenant->id]);
        $this->property->certificates()->create(['type' => 'gas_safety', 'issue_date' => today()->subYear()->subDays(5)->toDateString(), 'expiry_date' => today()->subDays(5)->toDateString()]);
        $this->property->maintenanceRequests()->create(['title' => 'Boiler not working', 'category' => 'heating', 'priority' => 'emergency', 'status' => 'reported', 'tenant_id' => $this->tenant->id, 'reported_by' => $this->tenant->id]);
    }

    private function book(string $service, array $extra = []): Booking
    {
        $response = $this->actingAs($this->landlord, 'sanctum')->postJson('/api/bookings', $extra + [
            'propertyId' => $this->property->id, 'serviceType' => $service, 'preferredDate' => today()->addWeek()->toDateString(),
            'timeSlot' => '10:00 AM - 12:00 PM', 'access' => 'tenant', 'notifyTenant' => true,
        ])->assertCreated();
        $this->app['auth']->forgetGuards();

        return Booking::findOrFail($response->json('id'));
    }

    // ---------- Sign-in ----------

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/bookings')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Welcome back');
    }

    public function test_only_admins_can_sign_in(): void
    {
        $this->post('/admin/login', ['email' => $this->landlord->email, 'password' => 'Password123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => 'sarah@lettingworld.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => 'SARAH@lettingworld.test', 'password' => 'Password123'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($this->admin);
        $this->assertDatabaseHas('admin_actions', ['admin_id' => $this->admin->id, 'action' => 'signed_in']);

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_repeated_failed_sign_ins_are_blocked(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/admin/login', ['email' => 'sarah@lettingworld.test', 'password' => 'wrong'.$i]);
        }
        $this->post('/admin/login', ['email' => 'sarah@lettingworld.test', 'password' => 'Password123'])
            ->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 60 seconds.']);
        $this->assertGuest();
    }

    public function test_a_landlord_session_cannot_open_the_admin_and_admins_cannot_use_the_app(): void
    {
        $this->actingAs($this->landlord)->get('/admin')->assertRedirect('/admin/login');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/login', ['email' => 'sarah@lettingworld.test', 'password' => 'Password123'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'Admin accounts sign in at the admin panel.');
    }

    // ---------- Pages ----------

    public function test_every_page_loads(): void
    {
        $booking = $this->book('gas_safety');

        $this->actingAs($this->admin);
        foreach ([
            '/admin', '/admin/bookings', '/admin/bookings?tab=attention', "/admin/bookings/{$booking->id}",
            '/admin/landlords', "/admin/landlords/{$this->landlord->id}", '/admin/tenants', "/admin/tenants/{$this->tenant->id}",
            '/admin/properties', '/admin/properties?view=archived', "/admin/properties/{$this->property->id}",
            '/admin/certificates', '/admin/certificates?tab=missing', '/admin/certificates?tab=all&type=gas_safety',
            '/admin/maintenance', '/admin/maintenance?view=all', '/admin/maintenance/'.$this->property->maintenanceRequests()->first()->id,
            '/admin/payments', '/admin/payments?status=paid', '/admin/settings',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get("/admin/landlords/{$this->tenant->id}")->assertNotFound(); // a tenant isn't a landlord
    }

    public function test_dashboard_shows_real_numbers_and_what_needs_attention(): void
    {
        $this->actingAs($this->admin)->get('/admin')
            ->assertOk()
            ->assertSee('Sarah')
            ->assertSee('Emergency repair: Boiler not working')
            ->assertSee('Gas Safety Certificate (CP12) expired');
    }

    public function test_search_finds_people_properties_and_bookings(): void
    {
        $booking = $this->book('gas_safety');
        $this->actingAs($this->admin);

        $this->getJson('/admin/search?q=Hasnain')->assertOk()->assertJsonFragment(['group' => 'Landlords', 'title' => 'Bilal Hasnain']);
        $this->getJson('/admin/search?q=G1 1AA')->assertJsonFragment(['group' => 'Properties']);
        $this->getJson('/admin/search?q='.$booking->reference)->assertJsonFragment(['group' => 'Bookings']);
        $this->getJson('/admin/search?q=x')->assertExactJson([]);
    }

    // ---------- Team-arranged jobs (services 11Done doesn't offer) ----------

    public function test_team_arranged_job_from_payment_to_new_certificate(): void
    {
        $booking = $this->book('legionella');
        $this->assertSame('letting_world', $booking->provider);
        $this->assertSame('not_required', $booking->sync_status);
        $this->assertSame(8900, $this->payments->charges[0]['amountPence']);

        $this->actingAs($this->admin);
        $this->get('/admin/bookings?tab=attention')->assertSee($booking->reference);

        // Can't complete before a contractor is assigned.
        $this->post("/admin/bookings/{$booking->id}/complete", [])->assertStatus(422);

        $this->post("/admin/bookings/{$booking->id}/schedule", [
            'engineer_name' => 'AquaSafe Ltd', 'scheduled_date' => today()->addDays(4)->toDateString(), 'scheduled_slot' => '08:00 AM - 10:00 AM',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('AquaSafe Ltd', $booking->engineer_name);
        Notification::assertSentTo($this->landlord, BookingUpdated::class, fn ($n) => $n->change === 'confirmed');
        Notification::assertSentTo($this->tenant, BookingUpdated::class, fn ($n) => $n->change === 'confirmed');

        $this->post("/admin/bookings/{$booking->id}/complete", [
            'certificate' => UploadedFile::fake()->create('legionella.pdf', 120, 'application/pdf'),
            'issue_date' => today()->toDateString(),
            'provider_reg' => 'LRA-778',
        ])->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('completed', $booking->status);
        $certificate = Certificate::findOrFail($booking->result_certificate_id);
        $this->assertSame('legionella', $certificate->type);
        $this->assertSame(today()->addMonths(24)->toDateString(), $certificate->expiry_date->toDateString()); // standard validity
        $this->assertSame('AquaSafe Ltd', $certificate->provider_name);
        Storage::disk('local')->assertExists($certificate->file_path);
        Notification::assertSentTo($this->landlord, BookingUpdated::class, fn ($n) => $n->change === 'completed');
        $this->assertSame(2, AdminAction::where('subject_type', 'booking')->where('subject_id', $booking->id)->count()); // scheduled + completed
    }

    public function test_schedule_is_validated(): void
    {
        $booking = $this->book('fire_risk');
        $this->actingAs($this->admin)->post("/admin/bookings/{$booking->id}/schedule", [
            '_modal' => 'schedule', 'engineer_name' => '', 'scheduled_date' => today()->subDay()->toDateString(), 'scheduled_slot' => 'morning',
        ])->assertSessionHasErrors(['engineer_name', 'scheduled_date', 'scheduled_slot']);
        $this->assertSame('requested', $booking->fresh()->status);
    }

    // ---------- 11Done jobs ----------

    public function test_admin_cancel_cancels_in_11done_refunds_and_tells_everyone(): void
    {
        $booking = $this->book('gas_safety');
        $this->assertSame('sent', $booking->sync_status);

        $this->actingAs($this->admin)->post("/admin/bookings/{$booking->id}/cancel", ['reason' => 'Landlord sold the property'])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame('refunded', $booking->payment_status);
        $this->assertSame([$booking->payment_intent_id], $this->payments->refunds);
        $this->assertStringContainsString('Landlord sold the property', $booking->admin_notes);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), "/bookings/{$booking->reference}/cancel"));
        Notification::assertSentTo($this->landlord, BookingUpdated::class, fn ($n) => $n->change === 'cancelled');

        // A second cancel is refused, not refunded twice.
        $this->post("/admin/bookings/{$booking->id}/cancel")->assertSessionHasErrors('booking');
        $this->assertCount(1, $this->payments->refunds);
    }

    public function test_retry_and_refresh_with_11done(): void
    {
        $booking = $this->book('gas_safety');
        $booking->forceFill(['sync_status' => 'failed', 'sync_error' => '11Done could not be reached.'])->save();

        $this->actingAs($this->admin)->post("/admin/bookings/{$booking->id}/retry")->assertSessionHas('status', 'Sent to 11Done.');
        $this->assertSame('sent', $booking->fresh()->sync_status);

        $this->post("/admin/bookings/{$booking->id}/refresh")->assertSessionHas('status', 'Updated from 11Done.');
        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('Tom Reid', $booking->engineer_name);
    }

    public function test_uploading_a_missing_certificate_for_a_completed_job(): void
    {
        $booking = $this->book('gas_safety');
        $booking->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

        $this->actingAs($this->admin)->post("/admin/bookings/{$booking->id}/certificate", [
            'certificate' => UploadedFile::fake()->create('cp12.pdf', 80, 'application/pdf'),
            'issue_date' => today()->toDateString(), 'expiry_date' => today()->addYear()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($booking->fresh()->result_certificate_id);
        $this->actingAs($this->admin)->get('/admin/certificates?tab=valid')->assertSee('12 High Street');
        $this->post("/admin/bookings/{$booking->id}/certificate", [])->assertStatus(422); // only once
    }

    public function test_internal_notes(): void
    {
        $booking = $this->book('gas_safety');
        $this->actingAs($this->admin)->put("/admin/bookings/{$booking->id}/notes", ['admin_notes' => 'Called landlord, confirmed key safe.'])
            ->assertSessionHas('status', 'Notes saved.');
        $this->assertSame('Called landlord, confirmed key safe.', $booking->fresh()->admin_notes);

        // Never shown to the landlord.
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->landlord, 'sanctum')->getJson('/api/bookings')->assertJsonMissing(['adminNotes' => 'Called landlord, confirmed key safe.']);
        $this->assertStringNotContainsString('Called landlord', $this->getJson('/api/bookings')->getContent());
    }

    // ---------- Accounts ----------

    public function test_suspending_a_landlord_signs_them_out_and_blocks_sign_in(): void
    {
        $token = $this->landlord->createToken('mobile')->plainTextToken;

        $this->actingAs($this->admin)->post("/admin/landlords/{$this->landlord->id}/suspend", ['reason' => 'Chargeback on booking'])->assertSessionHasNoErrors();
        $this->assertTrue($this->landlord->fresh()->isSuspended());
        $this->assertSame(0, $this->landlord->tokens()->count());
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/dashboard')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => $this->landlord->email, 'password' => 'Password123'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'This account has been suspended. Please contact Letting World.');

        $this->actingAs($this->admin)->post("/admin/landlords/{$this->landlord->id}/reactivate")->assertSessionHas('status');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => $this->landlord->email, 'password' => 'Password123'])->assertOk();
    }

    public function test_suspending_a_tenant_needs_a_reason(): void
    {
        $this->actingAs($this->admin)->post("/admin/tenants/{$this->tenant->id}/suspend", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/admin/tenants/{$this->tenant->id}/suspend", ['reason' => 'Asked to close account'])->assertSessionHasNoErrors();
        $this->assertTrue($this->tenant->fresh()->isSuspended());
        $this->post("/admin/landlords/{$this->tenant->id}/suspend", ['reason' => 'x'])->assertNotFound();
    }

    // ---------- Settings ----------

    public function test_admin_team_password_and_connection_test(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/settings/admins', [
            'first_name' => 'Omar', 'last_name' => 'Shah', 'email' => 'Omar@LettingWorld.test',
            'password' => 'Secure12345', 'password_confirmation' => 'Secure12345',
        ])->assertSessionHasNoErrors();
        $omar = User::where('email', 'omar@lettingworld.test')->firstOrFail();
        $this->assertTrue($omar->isAdmin());

        $this->post("/admin/settings/admins/{$omar->id}/toggle")->assertSessionHas('status');
        $this->assertTrue($omar->fresh()->isSuspended());
        $this->post("/admin/settings/admins/{$this->admin->id}/toggle")->assertStatus(422); // not yourself

        $this->put('/admin/settings/password', ['current_password' => 'nope', 'new_password' => 'Brandnew12345', 'new_password_confirmation' => 'Brandnew12345'])
            ->assertSessionHasErrors('current_password');
        $this->put('/admin/settings/password', ['current_password' => 'Password123', 'new_password' => 'Brandnew12345', 'new_password_confirmation' => 'Brandnew12345'])
            ->assertSessionHasNoErrors();

        $this->post('/admin/settings/elevendone/test')->assertSessionHas('elevenDoneTest', fn ($r) => $r['ok'] && $r['names'][60] === 'Landlord Gas safety inspection');
        $this->get('/admin/settings')->assertSee('Omar Shah')->assertSee('Added admin Omar Shah');
    }

    public function test_payments_export(): void
    {
        $booking = $this->book('gas_safety');
        $response = $this->actingAs($this->admin)->get('/admin/payments/export?status=paid')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Stripe payment', $csv);
        $this->assertStringContainsString($booking->reference, $csv);
        $this->assertStringContainsString('55.00', $csv);
    }
}
