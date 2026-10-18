<?php

namespace Tests\Feature;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandlordTenantFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::fake(['api.postcodes.io/*' => Http::response(['result' => ['latitude' => 51.5237, 'longitude' => -0.1585]])]);
    }

    /** Act as the given token's user. Guards cache the resolved user between test requests. */
    private function as(?string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $token ? $this->withToken($token) : $this->withoutToken();
    }

    private function registerLandlord(string $email = 'landlord@example.com'): string
    {
        return $this->as(null)->postJson('/api/auth/register', [
            'firstName' => 'Bilal',
            'lastName' => 'Hasnain',
            'email' => $email,
            'password' => 'secret123',
        ])->assertCreated()->json('token');
    }

    private function completeProfile(string $token): void
    {
        $this->as($token)->putJson('/api/profile', [
            'firstName' => 'Bilal',
            'lastName' => 'Hasnain',
            'phone' => '07700 900123',
            'addressLine1' => '1 High Street',
            'city' => 'London',
            'postcode' => 'sw1a1aa',
            'landlordType' => 'individual',
            'propertiesOwned' => 2,
            'experience' => 'experienced',
            'management' => 'self',
            'preferredContact' => 'email',
            'consentTerms' => true,
            'consentMarketing' => false,
        ])->assertOk()->assertJsonPath('profileComplete', true);
    }

    private function landlord(string $email = 'landlord@example.com'): string
    {
        $token = $this->registerLandlord($email);
        $this->completeProfile($token);

        return $token;
    }

    private function createProperty(string $token, string $address = '10 Baker Street'): string
    {
        return $this->as($token)->postJson('/api/properties', [
            'addressLine1' => $address,
            'city' => 'London',
            'postcode' => 'NW1 6XE',
            'propertyType' => 'flat',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'furnishing' => 'furnished',
            'hasGas' => true,
            'monthlyRent' => 1500,
            'status' => 'vacant',
        ])->assertCreated()->json('id');
    }

    private function createTenant(string $landlord, string $email, ?string $propertyId = null): string
    {
        return $this->as($landlord)->postJson('/api/tenants', array_filter([
            'firstName' => 'Tina',
            'lastName' => 'Tenant',
            'email' => $email,
            'phone' => '07700 900456',
            'temporaryPassword' => 'TemporaryPassword123',
            'propertyId' => $propertyId,
            'startDate' => $propertyId ? '2026-10-01' : null,
            'monthlyRent' => $propertyId ? 1500 : null,
            'rentDueDay' => $propertyId ? 1 : null,
        ]))->assertCreated()->json('id');
    }

    /** Logs a tenant in with the landlord-set password and replaces it, returning a usable token. */
    private function tenantLogin(string $email): string
    {
        $token = $this->as(null)->postJson('/api/auth/login', ['email' => $email, 'password' => 'TemporaryPassword123'])
            ->assertOk()->assertJsonPath('user.role', 'tenant')->assertJsonPath('user.mustChangePassword', true)
            ->json('token');

        $this->as($token)->getJson('/api/tenant/properties')->assertForbidden();
        $this->as($token)->postJson('/api/auth/change-password', [
            'currentPassword' => 'TemporaryPassword123', 'newPassword' => 'newpass123',
        ])->assertOk();

        return $token;
    }

    public function test_register_rejects_duplicate_email_and_weak_password(): void
    {
        $this->registerLandlord();

        $this->as(null)->postJson('/api/auth/register', [
            'firstName' => 'A', 'lastName' => 'B', 'email' => 'LANDLORD@example.com', 'password' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_landlord_cannot_use_app_until_mandatory_profile_is_complete(): void
    {
        $token = $this->registerLandlord();
        $this->as($token)->getJson('/api/dashboard')->assertForbidden();

        $this->as($token)->putJson('/api/profile', [
            'firstName' => 'Bilal', 'lastName' => 'Hasnain', 'landlordType' => 'company',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'phone', 'addressLine1', 'city', 'postcode', 'companyName', 'companyNumber',
            'propertiesOwned', 'experience', 'management', 'preferredContact', 'consentTerms',
        ]);

        $this->completeProfile($token);
        $this->as($token)->getJson('/api/dashboard')->assertOk()->assertJsonPath('stats.properties', 0);
    }

    public function test_property_edit_archive_restore_and_delete(): void
    {
        $landlord = $this->landlord();
        $id = $this->createProperty($landlord);

        $this->as($landlord)->getJson("/api/properties/{$id}")
            ->assertJsonPath('latitude', 51.5237)
            ->assertJsonPath('missingCertificates', ['gas_safety', 'eicr', 'epc']);

        $this->as($landlord)->putJson("/api/properties/{$id}", [
            'addressLine1' => '11 Baker Street', 'city' => 'London', 'postcode' => 'nw16xe', 'propertyType' => 'house',
            'bedrooms' => 3, 'bathrooms' => 2, 'furnishing' => 'unfurnished', 'hasGas' => false, 'status' => 'vacant',
        ])->assertOk()->assertJsonPath('addressLine1', '11 Baker Street')->assertJsonPath('missingCertificates', ['eicr', 'epc']);

        $this->as($landlord)->postJson("/api/properties/{$id}/archive")->assertOk()->assertJsonPath('archived', true);
        $this->as($landlord)->getJson('/api/properties')->assertJsonCount(0);
        $this->as($landlord)->getJson('/api/properties?archived=1')->assertJsonCount(1);
        $this->as($landlord)->postJson("/api/properties/{$id}/restore")->assertOk()->assertJsonPath('archived', false);

        $this->as($landlord)->deleteJson("/api/properties/{$id}")->assertOk();
        $this->as($landlord)->getJson("/api/properties/{$id}")->assertNotFound();
    }

    public function test_tenant_with_two_properties_sees_only_their_own(): void
    {
        $landlord = $this->landlord();
        $propertyA = $this->createProperty($landlord, '10 Baker Street');
        $propertyB = $this->createProperty($landlord, '22 Other Road');
        $propertyC = $this->createProperty($landlord, '33 Not Yours Lane');

        $tenantId = $this->createTenant($landlord, 'tina@gmail.com', $propertyA);
        $this->as($landlord)->postJson("/api/tenants/{$tenantId}/tenancies", [
            'propertyId' => $propertyB, 'startDate' => '2026-11-01', 'monthlyRent' => 900, 'rentDueDay' => 5,
        ])->assertCreated();
        // Can't assign the same property twice.
        $this->as($landlord)->postJson("/api/tenants/{$tenantId}/tenancies", [
            'propertyId' => $propertyB, 'startDate' => '2026-11-01', 'monthlyRent' => 900, 'rentDueDay' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors(['propertyId']);

        $this->as($landlord)->getJson("/api/properties/{$propertyA}")->assertJsonPath('status', 'let');

        $tenant = $this->tenantLogin('tina@gmail.com');
        $ids = collect($this->as($tenant)->getJson('/api/tenant/properties')->assertOk()->json())->pluck('property.id');
        $this->assertEqualsCanonicalizing([$propertyA, $propertyB], $ids->all());

        Property::whereKey($propertyA)->update(['notes' => 'Private: spare key with concierge']);
        $this->as($tenant)->getJson("/api/tenant/properties/{$propertyA}")->assertOk()
            ->assertJsonPath('landlord.email', 'landlord@example.com')
            ->assertJsonMissingPath('property.notes'); // landlord's private notes stay private
        // Changing the ID never reaches a property they are not assigned to.
        $this->as($tenant)->getJson("/api/tenant/properties/{$propertyC}")->assertNotFound();
        // No landlord endpoints.
        foreach (['/api/dashboard', '/api/properties', "/api/properties/{$propertyA}", '/api/tenants', '/api/certificates', '/api/bookings'] as $url) {
            $this->as($tenant)->getJson($url)->assertForbidden();
        }
    }

    public function test_removing_a_tenant_from_a_property_keeps_their_account(): void
    {
        $landlord = $this->landlord();
        $property = $this->createProperty($landlord);
        $tenantId = $this->createTenant($landlord, 'tina@gmail.com', $property);

        $tenancyId = $this->as($landlord)->getJson("/api/tenants/{$tenantId}")->json('tenancies.0.id');
        $this->as($landlord)->deleteJson("/api/tenancies/{$tenancyId}")->assertOk();

        $this->as($landlord)->getJson("/api/tenants/{$tenantId}")->assertOk()->assertJsonCount(0, 'tenancies');
        $this->as($landlord)->getJson("/api/properties/{$property}")->assertJsonPath('status', 'vacant');

        $tenant = $this->tenantLogin('tina@gmail.com');
        $this->as($tenant)->getJson("/api/tenant/properties/{$property}")->assertNotFound();
    }

    public function test_certificates_documents_and_tenant_visibility(): void
    {
        $landlord = $this->landlord();
        $property = $this->createProperty($landlord);
        $this->createTenant($landlord, 'tina@gmail.com', $property);
        $otherTenantId = $this->createTenant($landlord, 'other@gmail.com', $property);

        // Expiry auto-calculated from issue date (Gas Safety = 12 months).
        $cert = $this->as($landlord)->post("/api/properties/{$property}/certificates", [
            'type' => 'gas_safety', 'issueDate' => now()->subMonths(11)->toDateString(),
            'file' => UploadedFile::fake()->create('cp12.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('expiryDate', now()->subMonths(11)->addMonthsNoOverflow(12)->toDateString())
            ->assertJsonPath('status', 'expiring');

        $this->as($landlord)->getJson('/api/dashboard')
            ->assertJsonPath('compliance.expiring', 1)
            ->assertJsonPath('compliance.missing', 2);

        // Private document for the other tenant must never reach Tina.
        $otherTenancy = $this->as($landlord)->getJson("/api/tenants/{$otherTenantId}")->json('tenancies.0.id');
        $this->as($landlord)->post("/api/properties/{$property}/documents", [
            'category' => 'id_document', 'title' => 'Other tenant passport', 'tenancyId' => $otherTenancy,
            'sharedWithTenant' => '1', 'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->as($landlord)->post("/api/properties/{$property}/documents", [
            'category' => 'other', 'title' => 'House rules', 'sharedWithTenant' => '1',
            'file' => UploadedFile::fake()->create('rules.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $tenant = $this->tenantLogin('tina@gmail.com');
        $detail = $this->as($tenant)->getJson("/api/tenant/properties/{$property}")->assertOk();
        $this->assertSame(['House rules'], collect($detail->json('documents'))->pluck('title')->all());
        $this->assertCount(1, $detail->json('certificates'));

        // Signed file link works without a token; a tampered link does not.
        $url = $cert->json('file.url');
        $this->as(null)->get($url)->assertOk();
        $this->as(null)->get(str_replace('/certificate/', '/document/', $url))->assertForbidden();
    }

    public function test_landlords_are_isolated_from_each_other(): void
    {
        $a = $this->landlord('a@example.com');
        $propertyA = $this->createProperty($a);
        $tenantA = $this->createTenant($a, 'tenant-a@gmail.com', $propertyA);
        $certA = $this->as($a)->post("/api/properties/{$propertyA}/certificates", ['type' => 'epc', 'issueDate' => '2025-01-01'], ['Accept' => 'application/json'])->json('id');

        $b = $this->landlord('b@example.com');
        $propertyB = $this->createProperty($b);
        $tenantB = $this->createTenant($b, 'tenant-b@gmail.com');

        $this->as($b)->getJson("/api/properties/{$propertyA}")->assertNotFound();
        $this->as($b)->putJson("/api/properties/{$propertyA}", [])->assertNotFound();
        $this->as($b)->getJson("/api/tenants/{$tenantA}")->assertNotFound();
        $this->as($b)->deleteJson("/api/certificates/{$certA}")->assertNotFound();
        $this->as($b)->getJson("/api/properties/{$propertyA}/documents")->assertNotFound();
        $this->as($b)->post("/api/properties/{$propertyA}/certificates", ['type' => 'epc'], ['Accept' => 'application/json'])->assertNotFound();
        $this->as($b)->getJson('/api/certificates')->assertJsonCount(0);
        // B can't assign their tenant to A's property, or A's tenant to their own.
        $this->as($b)->postJson("/api/tenants/{$tenantB}/tenancies", [
            'propertyId' => $propertyA, 'startDate' => '2026-10-01', 'monthlyRent' => 1, 'rentDueDay' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['propertyId']);
        $this->as($b)->postJson("/api/tenants/{$tenantA}/tenancies", [
            'propertyId' => $propertyB, 'startDate' => '2026-10-01', 'monthlyRent' => 1, 'rentDueDay' => 1,
        ])->assertNotFound();
        $this->as($b)->postJson('/api/bookings', [
            'propertyId' => $propertyA, 'serviceType' => 'epc', 'preferredDate' => now()->addWeek()->toDateString(),
            'timeSlot' => '08:00 AM - 10:00 AM', 'access' => 'landlord', 'notifyTenant' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['propertyId']);
    }

    public function test_booking_a_renewal_and_cancelling(): void
    {
        $landlord = $this->landlord();
        $property = $this->createProperty($landlord);
        $payload = [
            'propertyId' => $property, 'serviceType' => 'gas_safety', 'preferredDate' => now()->addWeek()->toDateString(),
            'timeSlot' => '08:00 AM - 10:00 AM', 'access' => 'landlord', 'notifyTenant' => true,
        ];

        $booking = $this->as($landlord)->postJson('/api/bookings', $payload)->assertCreated()
            ->assertJsonPath('status', 'requested')->json();
        $this->assertStringStartsWith('LW-', $booking['reference']);
        $this->as($landlord)->postJson('/api/bookings', $payload)->assertStatus(422)->assertJsonValidationErrors(['serviceType']);

        $this->as($landlord)->postJson("/api/bookings/{$booking['id']}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->as($landlord)->getJson('/api/bookings')->assertJsonCount(1);
    }

    public function test_payment_methods_report_when_stripe_is_not_configured(): void
    {
        config(['services.stripe.key' => null, 'services.stripe.secret' => null]);
        $landlord = $this->landlord();

        $this->as($landlord)->getJson('/api/payment-methods')->assertOk()->assertJsonPath('configured', false);
        $this->as($landlord)->postJson('/api/payment-methods/setup')->assertStatus(503);
    }
}
