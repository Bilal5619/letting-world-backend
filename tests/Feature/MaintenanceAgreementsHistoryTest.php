<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PushToken;
use App\Models\User;
use App\Notifications\MaintenanceReported;
use App\Notifications\MaintenanceUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceAgreementsHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::fake(['api.postcodes.io/*' => Http::response(['result' => ['latitude' => 55.86, 'longitude' => -4.25]])]);

        $this->landlord = User::factory()->create(['role' => 'landlord', 'profile_completed_at' => now()]);
        $this->property = $this->landlord->properties()->create([
            'address_line1' => '12 High Street', 'city' => 'Glasgow', 'postcode' => 'G1 1AA', 'property_type' => 'flat',
            'bedrooms' => 2, 'bathrooms' => 1, 'furnishing' => 'furnished', 'has_gas' => true, 'status' => 'let',
        ]);
    }

    private function tenantOn(Property $property, string $first = 'John'): User
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $property->landlord_id, 'first_name' => $first]);
        $property->tenancies()->create(['tenant_id' => $tenant->id]);

        return $tenant;
    }

    /** Guards cache the resolved user between test requests. */
    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    public function test_tenant_reports_a_repair_and_landlord_updates_it(): void
    {
        Notification::fake();
        $john = $this->tenantOn($this->property, 'John');
        $sarah = $this->tenantOn($this->property, 'Sarah');

        $repair = $this->as($john)->post("/api/tenant/properties/{$this->property->id}/maintenance", [
            'title' => 'Boiler not working', 'description' => 'No hot water since this morning',
            'category' => 'heating', 'priority' => 'urgent',
            'photos' => [UploadedFile::fake()->image('boiler.jpg'), UploadedFile::fake()->image('dial.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('status', 'reported')->assertJsonCount(2, 'photos')->json();

        Notification::assertSentTo($this->landlord, MaintenanceReported::class);

        // The landlord sees it (and it's in "Requires attention").
        $this->as($this->landlord)->getJson('/api/maintenance?status=open')->assertJsonCount(1)->assertJsonPath('0.title', 'Boiler not working');
        $this->assertContains('maintenance', collect($this->getJson('/api/dashboard')->json('actions'))->pluck('kind')->all());

        $this->postJson("/api/maintenance/{$repair['id']}/updates", ['status' => 'scheduled', 'note' => 'Engineer booked for Thursday'])
            ->assertOk()->assertJsonPath('status', 'scheduled')->assertJsonPath('updates.0.note', 'Engineer booked for Thursday');
        Notification::assertSentTo($john, MaintenanceUpdated::class);

        // John follows progress and can reply; the photo links work.
        $this->as($john)->getJson("/api/tenant/maintenance/{$repair['id']}")->assertOk()->assertJsonPath('status', 'scheduled');
        $this->postJson("/api/tenant/maintenance/{$repair['id']}/updates", ['note' => 'Thanks!'])->assertOk()->assertJsonCount(2, 'updates');
        $this->get($repair['photos'][0]['url'])->assertOk();

        // Sarah (same property) can't see John's report; she can't change statuses either.
        $this->as($sarah)->getJson("/api/tenant/properties/{$this->property->id}/maintenance")->assertJsonCount(0);
        $this->getJson("/api/tenant/maintenance/{$repair['id']}")->assertNotFound();

        // A repair the landlord logs is visible to both tenants.
        $this->as($this->landlord)->postJson("/api/properties/{$this->property->id}/maintenance", [
            'title' => 'Gutter clearing', 'category' => 'structural', 'priority' => 'low',
        ])->assertCreated();
        $this->as($sarah)->getJson("/api/tenant/properties/{$this->property->id}/maintenance")->assertJsonCount(1);
    }

    public function test_tenants_cannot_report_or_read_repairs_for_other_properties(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord', 'profile_completed_at' => now()]);
        $otherProperty = $otherLandlord->properties()->create([
            'address_line1' => '1 Elsewhere', 'city' => 'Leeds', 'postcode' => 'LS1 1AA', 'property_type' => 'house',
            'bedrooms' => 3, 'bathrooms' => 1, 'furnishing' => 'unfurnished', 'has_gas' => true, 'status' => 'let',
        ]);
        $outsider = $this->tenantOn($otherProperty);

        $this->as($outsider)->postJson("/api/tenant/properties/{$this->property->id}/maintenance", [
            'title' => 'x', 'category' => 'other', 'priority' => 'low',
        ])->assertNotFound();
        $this->getJson("/api/tenant/properties/{$this->property->id}/maintenance")->assertNotFound();
        $this->as($otherLandlord)->getJson('/api/maintenance')->assertJsonCount(0);
    }

    public function test_tenancy_agreement_with_several_tenants_and_signed_contract(): void
    {
        $john = $this->tenantOn($this->property, 'John');
        $sarah = $this->tenantOn($this->property, 'Sarah');
        $lodger = $this->tenantOn($this->property, 'Mike'); // on the property but not on this agreement

        $agreement = $this->as($this->landlord)->post("/api/properties/{$this->property->id}/agreements", [
            'tenantIds' => [$john->id, $sarah->id],
            'startDate' => '2027-01-01', 'endDate' => '2027-12-31',
            'monthlyRent' => 1250, 'rentDueDay' => 1, 'depositAmount' => 1400, 'depositScheme' => 'SafeDeposits Scotland',
            'contract' => UploadedFile::fake()->create('signed-ast.pdf', 300, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonCount(2, 'tenants')->assertJsonPath('contract.name', 'signed-ast.pdf')->assertJsonPath('status', 'upcoming')
            ->json();

        // Dates are copied onto each named tenant's tenancy.
        $this->assertSame('2027-12-31', $john->tenancies()->first()->end_date->toDateString());
        $this->assertNull($lodger->tenancies()->first()->end_date);

        // John sees the agreement and contract; Mike doesn't; the contract isn't in the general documents list.
        $detail = $this->as($john)->getJson("/api/tenant/properties/{$this->property->id}")->assertOk();
        $this->assertCount(1, $detail->json('agreements'));
        $this->assertEqualsCanonicalizing([$john->first_name.' '.$john->last_name, $sarah->first_name.' '.$sarah->last_name], collect($detail->json('agreements.0.tenants'))->pluck('name')->all());
        $this->assertCount(0, $detail->json('documents'));
        $this->get($detail->json('agreements.0.contract.url'))->assertOk();
        $this->as($lodger)->getJson("/api/tenant/properties/{$this->property->id}")->assertJsonCount(0, 'agreements');

        // Replacing the contract archives the old copy.
        $this->as($this->landlord)->post("/api/agreements/{$agreement['id']}/contract", [
            'contract' => UploadedFile::fake()->create('re-signed.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('contract.name', 're-signed.pdf');
        $this->getJson("/api/properties/{$this->property->id}/documents?archived=1")->assertJsonCount(1);

        // Only tenants of this property can be named.
        $stranger = User::factory()->create(['role' => 'tenant', 'landlord_id' => $this->landlord->id]);
        $this->putJson("/api/agreements/{$agreement['id']}", ['tenantIds' => [$stranger->id], 'startDate' => '2027-01-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['tenantIds.0']);
    }

    public function test_documents_can_be_replaced_and_archived_and_history_is_recorded(): void
    {
        $john = $this->tenantOn($this->property);
        $doc = $this->as($this->landlord)->post("/api/properties/{$this->property->id}/documents", [
            'category' => 'inventory', 'title' => 'Check-in inventory', 'sharedWithTenant' => '1',
            'file' => UploadedFile::fake()->create('inventory-v1.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json();

        $this->post("/api/documents/{$doc['id']}/replace", ['file' => UploadedFile::fake()->create('inventory-v2.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('file.name', 'inventory-v2.pdf');
        $this->as($john)->getJson("/api/tenant/properties/{$this->property->id}")->assertJsonCount(1, 'documents');

        $this->as($this->landlord)->postJson("/api/documents/{$doc['id']}/archive")->assertOk()->assertJsonPath('archived', true);
        $this->getJson("/api/properties/{$this->property->id}/documents")->assertJsonCount(0);
        $this->as($john)->getJson("/api/tenant/properties/{$this->property->id}")->assertJsonCount(0, 'documents');

        $types = collect($this->as($this->landlord)->getJson("/api/properties/{$this->property->id}/history")->assertOk()->json())->pluck('type');
        foreach (['property_added', 'tenant_assigned', 'document_added', 'document_replaced', 'document_archived'] as $type) {
            $this->assertContains($type, $types->all(), "history should contain $type");
        }
        $this->as($john)->getJson("/api/properties/{$this->property->id}/history")->assertForbidden();
    }

    public function test_push_notifications_go_to_registered_phones_and_dead_tokens_are_removed(): void
    {
        Notification::swap(new ChannelManager($this->app));
        config(['mail.default' => 'array']);
        Http::fake([
            'api.postcodes.io/*' => Http::response(['result' => null]),
            'exp.host/*' => Http::response(['data' => [['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]]]),
        ]);
        $john = $this->tenantOn($this->property);

        $this->as($this->landlord)->postJson('/api/devices', ['token' => 'ExponentPushToken[abc123]', 'platform' => 'ios'])->assertOk();
        $this->postJson('/api/devices', ['token' => 'not-a-token'])->assertStatus(422);

        $this->as($john)->postJson("/api/tenant/properties/{$this->property->id}/maintenance", [
            'title' => 'Leaking tap', 'category' => 'plumbing', 'priority' => 'normal',
        ])->assertCreated();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'exp.host')
            && $r[0]['to'] === 'ExponentPushToken[abc123]'
            && $r[0]['title'] === 'New repair reported'
            && $r[0]['data']['action'] === 'view_maintenance');
        $this->assertSame(0, PushToken::count()); // DeviceNotRegistered → forgotten
    }
}
