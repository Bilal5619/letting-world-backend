<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\SentAlert;
use App\Models\Tenancy;
use App\Models\User;
use App\Notifications\MaintenanceReported;
use App\Support\PrivateFiles;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo data for testing the app:  php artisan db:seed --class=DemoSeeder
 *
 * Safe to re-run: it first removes the demo accounts (and their files) it created before.
 *
 * Only touches *@lettingworld.test accounts — real accounts are never changed.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Password123';

    public function run(): void
    {
        $this->cleanUp();

        DB::transaction(function () {
            $landlord = $this->landlord('landlord@lettingworld.test', 'Bilal', 'Hasnain', '07700 900101', [
                'address_line1' => '1 Kings Road', 'city' => 'London', 'postcode' => 'SW3 4RP',
                'landlord_type' => 'individual', 'properties_owned' => 4, 'experience' => 'portfolio',
            ]);

            $flat = $this->property($landlord, [
                'reference' => 'Riverside flat', 'notes' => "Concierge holds a spare key. Bins collected Tuesday.\nGas meter in the hallway cupboard.",
                'address_line1' => 'Flat 12, Riverside Court', 'address_line2' => '45 Albert Embankment', 'city' => 'London',
                'postcode' => 'SE1 7TP', 'latitude' => 51.4935, 'longitude' => -0.1215, 'property_type' => 'flat',
                'bedrooms' => 2, 'bathrooms' => 1, 'furnishing' => 'furnished', 'has_gas' => true, 'monthly_rent' => 2150,
            ]);
            $house = $this->property($landlord, [
                'reference' => 'Maple Ave family home', 'notes' => 'Boiler: Worcester Bosch 30i (2019). Stopcock under the kitchen sink.',
                'address_line1' => '28 Maple Avenue', 'city' => 'Manchester', 'postcode' => 'M20 2RN',
                'latitude' => 53.4194, 'longitude' => -2.2297, 'property_type' => 'house',
                'bedrooms' => 3, 'bathrooms' => 2, 'furnishing' => 'unfurnished', 'has_gas' => true, 'monthly_rent' => 1350,
            ]);
            $studio = $this->property($landlord, [
                'reference' => 'Canal St studio',
                'address_line1' => '7 Canal Street', 'city' => 'Birmingham', 'postcode' => 'B1 2JP',
                'latitude' => 52.4781, 'longitude' => -1.9087, 'property_type' => 'studio',
                'bedrooms' => 1, 'bathrooms' => 1, 'furnishing' => 'furnished', 'has_gas' => false, 'monthly_rent' => 875,
            ]);
            $vacant = $this->property($landlord, [
                'address_line1' => '3 Station Road', 'city' => 'Leeds', 'postcode' => 'LS1 4DY',
                'latitude' => 53.7960, 'longitude' => -1.5480, 'property_type' => 'house',
                'bedrooms' => 4, 'bathrooms' => 2, 'furnishing' => 'part_furnished', 'has_gas' => true, 'monthly_rent' => 1600,
                'status' => 'vacant',
            ]);
            $sold = $this->property($landlord, [
                'address_line1' => '19 Old Mill Lane', 'city' => 'Bristol', 'postcode' => 'BS1 4ST',
                'latitude' => 51.4510, 'longitude' => -2.5970, 'property_type' => 'house',
                'bedrooms' => 2, 'bathrooms' => 1, 'furnishing' => 'unfurnished', 'has_gas' => true, 'monthly_rent' => 1100,
                'status' => 'vacant', 'archived_at' => now()->subMonths(2),
            ]);

            // Certificates: a realistic mix of valid / expiring soon / expired / missing.
            $this->certificate($flat, 'gas_safety', now()->subMonths(11)->subDays(10), 'British Gas', 'Gas Safe 123456');  // expiring
            $this->certificate($flat, 'eicr', now()->subYears(2), 'Sparks Electrical Ltd', 'NICEIC 998877');                // valid
            $this->certificate($flat, 'epc', now()->subYears(3), 'EPC Direct', null);                                       // valid
            $this->certificate($house, 'gas_safety', now()->subMonths(13), 'Northern Heating', 'Gas Safe 445566');          // expired
            $this->certificate($house, 'epc', now()->subYears(6), 'EPC Direct', null);                                      // valid
            $this->certificate($house, 'boiler_service', now()->subMonths(4), 'Northern Heating', 'Gas Safe 445566');       // valid
            // House has no EICR -> "missing".
            $this->certificate($studio, 'eicr', now()->subYears(4)->subMonths(11), 'Midlands Electrical', 'NAPIT 22113');  // expiring
            $this->certificate($studio, 'epc', now()->subYears(1), 'EPC Direct', null);                                     // valid
            // Vacant house: nothing uploaded -> gas, EICR and EPC all missing.

            // Tenants (landlord sets the password; these are ready to log in).
            $sarah = $this->tenant($landlord, 'sarah.tenant@lettingworld.test', 'Sarah', 'Ahmed', '07700 900201');
            $james = $this->tenant($landlord, 'james.tenant@lettingworld.test', 'James', 'Walker', '07700 900202');
            $new = $this->tenant($landlord, 'new.tenant@lettingworld.test', 'Amina', 'Khan', '07700 900203', mustChangePassword: true);
            $this->tenant($landlord, 'unassigned.tenant@lettingworld.test', 'Tom', 'Price', '07700 900204');

            $sarahFlat = $this->tenancy($sarah, $flat, now()->subMonths(8), 2150, 1, 2480, 'DPS');
            $jamesHouse = $this->tenancy($james, $house, now()->subYear()->addDays(45), 1350, 5, 1550, 'TDS'); // ends in ~45 days
            $this->tenancy($james, $studio, now()->subMonths(3), 875, 15, 1000, 'myDeposits'); // James rents two properties
            $this->tenancy($new, $flat, now()->subWeeks(2), 0, 1, null, null);                  // joint tenant on the flat

            // Documents.
            $this->document($flat, 'tenancy_agreement', 'Assured Shorthold Tenancy – Sarah Ahmed', $sarahFlat, true);
            $this->document($flat, 'id_document', 'Passport – Sarah Ahmed', $sarahFlat, false);
            $this->document($flat, 'inventory', 'Check-in inventory', null, true);
            $this->document($house, 'tenancy_agreement', 'Assured Shorthold Tenancy – James Walker', $jamesHouse, true);
            $this->document($house, 'invoice', 'Boiler service invoice', null, false);
            $this->document($flat, 'inventory', 'Old inventory (2024)', null, true, archived: true);

            // Tenancy agreement: one contract, two tenants (Sarah + Amina), with the signed copy.
            $agreement = $flat->agreements()->create([
                'start_date' => now()->subMonths(8)->toDateString(),
                'end_date' => now()->subMonths(8)->addYear()->subDay()->toDateString(),
                'monthly_rent' => 2150, 'rent_due_day' => 1, 'deposit_amount' => 2480,
                'deposit_scheme' => 'DPS', 'deposit_reference' => 'DPS-4471-2209',
            ]);
            $agreement->tenants()->sync([$sarah->id, $new->id]);
            $flat->documents()->create([
                'tenancy_agreement_id' => $agreement->id, 'category' => 'tenancy_agreement',
                'title' => 'Tenancy agreement – Sarah Ahmed & Amina Khan', 'shared_with_tenant' => false,
                ...$this->pdf("documents/{$flat->id}", 'signed-tenancy-agreement.pdf', [
                    'Assured Shorthold Tenancy Agreement', $flat->fullAddress(), 'Tenants: Sarah Ahmed, Amina Khan', 'DEMO DOCUMENT',
                ]),
            ]);

            // Repairs.
            $boiler = $this->repair($flat, $sarah, 'No hot water from the boiler', 'heating', 'urgent', 'in_progress',
                'The boiler shows error E119 and there has been no hot water since this morning.', photo: 'Boiler error E119');
            $boiler->updates()->create(['user_id' => $landlord->id, 'status' => 'in_progress', 'note' => 'Thanks Sarah — a heating engineer is booked for tomorrow between 9am and 12pm.']);
            $tap = $this->repair($house, $james, 'Kitchen tap dripping', 'plumbing', 'normal', 'resolved', 'Drips constantly even when fully closed.');
            $tap->updates()->create(['user_id' => $landlord->id, 'status' => 'scheduled', 'note' => 'Plumber coming on Monday.']);
            $tap->updates()->create(['user_id' => $james->id, 'note' => 'Great, I will be in all day.']);
            $tap->updates()->create(['user_id' => $landlord->id, 'status' => 'resolved', 'note' => 'Washer replaced — all sorted.']);
            $tap->update(['resolved_at' => now()->subDays(2)]);
            $this->repair($studio, $landlord, 'Replace smoke alarm batteries', 'security', 'low', 'reported', 'Noticed at the last inspection.');
            $damp = $this->repair($studio, $james, 'Damp patch on bathroom ceiling', 'damp', 'normal', 'reported',
                'A dark patch has appeared above the shower over the last week.', photo: 'Damp patch');
            $landlord->notify(new MaintenanceReported($damp->load('property', 'tenant')));

            // Bookings (demo only: not sent to 11Done, so sync_status stays not_required).
            $landlord->bookings()->create([
                'property_id' => $house->id, 'service_type' => 'gas_safety', 'preferred_date' => now()->addDays(6)->toDateString(),
                'time_slot' => '08:00 AM - 10:00 AM', 'access' => 'other', 'access_notes' => 'Key safe by front door, code 1234', 'notify_tenant' => true,
                'status' => 'confirmed', 'price' => 69, 'payment_status' => 'paid', 'card_brand' => 'visa', 'card_last4' => '4242',
            ]);
            $landlord->bookings()->create([
                'property_id' => $flat->id, 'service_type' => 'gas_safety', 'preferred_date' => now()->addDays(14)->toDateString(),
                'time_slot' => '10:00 AM - 12:00 PM', 'access' => 'tenant', 'notify_tenant' => true, 'status' => 'requested',
                'price' => 69, 'payment_status' => 'paid', 'card_brand' => 'visa', 'card_last4' => '4242',
            ]);
            $landlord->bookings()->create([
                'property_id' => $house->id, 'service_type' => 'boiler_service', 'preferred_date' => now()->subMonths(4)->toDateString(),
                'time_slot' => '02:00 PM - 04:00 PM', 'access' => 'landlord', 'notify_tenant' => true, 'status' => 'completed',
                'price' => 85, 'payment_status' => 'paid', 'card_brand' => 'visa', 'card_last4' => '4242',
            ]);
            $landlord->bookings()->create([
                'property_id' => $studio->id, 'service_type' => 'pat', 'preferred_date' => now()->subMonth()->toDateString(),
                'time_slot' => '04:00 PM - 06:00 PM', 'access' => 'landlord', 'notify_tenant' => false, 'status' => 'cancelled',
                'cancelled_at' => now()->subMonths(2), 'price' => 79, 'payment_status' => 'refunded', 'card_brand' => 'visa', 'card_last4' => '4242',
            ]);

            // A second landlord, to check that accounts can't see each other's data.
            $other = $this->landlord('other.landlord@lettingworld.test', 'Olivia', 'Grant', '07700 900301', [
                'address_line1' => '9 Queen Street', 'city' => 'Edinburgh', 'postcode' => 'EH2 1JE',
                'landlord_type' => 'company', 'company_name' => 'Grant Lettings Ltd', 'company_number' => 'SC123456',
                'properties_owned' => 1, 'experience' => 'experienced',
            ]);
            $otherFlat = $this->property($other, [
                'address_line1' => '22 Leith Walk', 'city' => 'Edinburgh', 'postcode' => 'EH6 5AA',
                'latitude' => 55.9620, 'longitude' => -3.1770, 'property_type' => 'flat',
                'bedrooms' => 1, 'bathrooms' => 1, 'furnishing' => 'furnished', 'has_gas' => true, 'monthly_rent' => 950,
            ]);
            $this->certificate($otherFlat, 'gas_safety', now()->subMonths(2), 'Scottish Gas', 'Gas Safe 778899');
            $otherTenant = $this->tenant($other, 'other.tenant@lettingworld.test', 'Liam', 'Scott', '07700 900302');
            $this->tenancy($otherTenant, $otherFlat, now()->subMonths(5), 950, 1, 1100, 'SafeDeposits Scotland');
        });

        // Admin panel account (/admin).
        User::updateOrCreate(['email' => 'admin@lettingworld.test'], [
            'role' => User::ROLE_ADMIN, 'first_name' => 'Sarah', 'last_name' => 'Mitchell',
            'password' => self::PASSWORD, 'email_verified_at' => now(), 'suspended_at' => null,
        ]);

        // Run the daily alert automation once so the demo landlord has real notifications.
        Artisan::call('alerts:send-expiry');

        $this->command?->info('Demo data ready. All demo accounts use the password: '.self::PASSWORD);
    }

    /** Removes previous demo accounts and their uploaded files. */
    private function cleanUp(): void
    {
        $users = User::where('email', 'like', '%@lettingworld.test')->get();

        DatabaseNotification::where('notifiable_type', (new User)->getMorphClass())->whereIn('notifiable_id', $users->pluck('id'))->delete();
        $propertyIds = Property::whereIn('landlord_id', $users->pluck('id'))->pluck('id');
        SentAlert::query()
            ->where(fn ($q) => $q->where('subject_type', (new Certificate)->getMorphClass())
                ->whereIn('subject_id', Certificate::whereIn('property_id', $propertyIds)->pluck('id')))
            ->orWhere(fn ($q) => $q->where('subject_type', (new Tenancy)->getMorphClass())
                ->whereIn('subject_id', Tenancy::whereIn('property_id', $propertyIds)->pluck('id')))
            ->delete();

        foreach ($users->where('role', User::ROLE_TENANT) as $tenant) {
            $tenant->tenancies->each->delete(); // deletes tenancy documents + files
            $tenant->tokens()->delete();
            $tenant->delete();
        }
        foreach ($users->where('role', User::ROLE_LANDLORD) as $landlord) {
            foreach ($landlord->properties as $property) {
                PrivateFiles::delete($property->photo_path);
                $property->maintenanceRequests()->with('photos')->get()
                    ->each(fn ($m) => $m->photos->each(fn ($p) => PrivateFiles::delete($p->file_path)));
                $property->certificates->each(fn ($c) => PrivateFiles::delete($c->file_path));
                $property->documents->each(fn ($d) => PrivateFiles::delete($d->file_path));
            }
            $landlord->tokens()->delete();
            $landlord->delete(); // cascades properties, certificates, documents, bookings
        }
    }

    private function landlord(string $email, string $first, string $last, string $phone, array $profile): User
    {
        $user = User::create([
            'role' => User::ROLE_LANDLORD,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => str_replace(' ', '', $phone),
            'password' => self::PASSWORD,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'last_login_at' => now(),
        ]);

        $user->landlordProfile()->create($profile + [
            'management' => 'self',
            'preferred_contact' => 'email',
            'whatsapp' => str_replace(' ', '', $phone),
            'hear_about_us' => 'Demo data',
            'terms_accepted_at' => now(),
            'marketing_consent' => false,
        ]);

        return $user;
    }

    private function property(User $landlord, array $attributes): Property
    {
        return $landlord->properties()->create($attributes + ['status' => 'let']);
    }

    private function tenant(User $landlord, string $email, string $first, string $last, string $phone, bool $mustChangePassword = false): User
    {
        return User::create([
            'role' => User::ROLE_TENANT,
            'landlord_id' => $landlord->id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => str_replace(' ', '', $phone),
            'password' => self::PASSWORD,
            'must_change_password' => $mustChangePassword,
            'last_login_at' => $mustChangePassword ? null : now()->subDays(3),
        ]);
    }

    private function tenancy(User $tenant, Property $property, Carbon $start, float $rent, int $dueDay, ?float $deposit, ?string $scheme): Tenancy
    {
        return $tenant->tenancies()->create([
            'property_id' => $property->id,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'monthly_rent' => $rent ?: $property->monthly_rent,
            'rent_due_day' => $dueDay,
            'deposit_amount' => $deposit,
            'deposit_scheme' => $scheme,
        ]);
    }

    private function certificate(Property $property, string $type, Carbon $issued, ?string $provider, ?string $reg): void
    {
        $months = config("lettingworld.certificate_types.$type.months");
        $label = config("lettingworld.certificate_types.$type.label");

        $property->certificates()->create([
            'type' => $type,
            'issue_date' => $issued->toDateString(),
            'expiry_date' => $months ? $issued->copy()->addMonthsNoOverflow($months)->toDateString() : null,
            'provider_name' => $provider,
            'provider_reg' => $reg,
            ...$this->pdf("certificates/{$property->id}", Str::slug($label).'.pdf', [
                $label,
                $property->fullAddress(),
                'Issued: '.$issued->format('d/m/Y'),
                'Engineer: '.($provider ?? '-').($reg ? " ($reg)" : ''),
                'DEMO DOCUMENT - not a real certificate',
            ]),
        ]);
    }

    private function repair(Property $property, User $reporter, string $title, string $category, string $priority, string $status, string $description, ?string $photo = null): MaintenanceRequest
    {
        $repair = $property->maintenanceRequests()->create([
            'tenant_id' => $reporter->isTenant() ? $reporter->id : null,
            'reported_by' => $reporter->id,
            'title' => $title, 'description' => $description,
            'category' => $category, 'priority' => $priority, 'status' => $status,
        ]);
        if ($photo) {
            $repair->photos()->create($this->image("maintenance/{$property->id}", $photo));
        }

        return $repair;
    }

    /** A small placeholder JPEG labelled "DEMO PHOTO". */
    private function image(string $folder, string $caption): array
    {
        $img = imagecreatetruecolor(640, 480);
        imagefilledrectangle($img, 0, 0, 640, 480, imagecolorallocate($img, 233, 239, 249));
        imagefilledrectangle($img, 0, 400, 640, 480, imagecolorallocate($img, 11, 42, 91));
        $navy = imagecolorallocate($img, 11, 42, 91);
        imagestring($img, 5, 40, 200, $caption, $navy);
        imagestring($img, 5, 40, 430, 'DEMO PHOTO - Letting World', imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagejpeg($img, null, 80);
        $jpeg = ob_get_clean();

        $path = "$folder/".Str::uuid().'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        return ['file_path' => $path, 'file_mime' => 'image/jpeg', 'file_size' => strlen($jpeg)];
    }

    private function document(Property $property, string $category, string $title, ?Tenancy $tenancy, bool $shared, bool $archived = false): void
    {
        $property->documents()->create([
            'category' => $category,
            'title' => $title,
            'tenancy_id' => $tenancy?->id,
            'shared_with_tenant' => $shared,
            'archived_at' => $archived ? now()->subMonths(3) : null,
            ...$this->pdf("documents/{$property->id}", Str::slug($title).'.pdf', [
                $title,
                $property->fullAddress(),
                'DEMO DOCUMENT',
            ]),
        ]);
    }

    /** Writes a small one-page PDF to private storage. */
    private function pdf(string $folder, string $name, array $lines): array
    {
        $text = '';
        foreach ($lines as $i => $line) {
            $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'ASCII//TRANSLIT', $line));
            $size = $i === 0 ? 20 : 12;
            $y = 780 - ($i === 0 ? 0 : 30 + $i * 20);
            $text .= "BT /F1 $size Tf 60 $y Td ($safe) Tj ET\n";
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($text)." >>\nstream\n{$text}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        $path = "$folder/".Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $pdf);

        return ['file_path' => $path, 'file_name' => $name, 'file_mime' => 'application/pdf', 'file_size' => strlen($pdf)];
    }
}
