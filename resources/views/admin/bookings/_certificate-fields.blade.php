<x-admin.field name="certificate" label="Certificate file" type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" hint="PDF or photo, up to {{ (int) (config('lettingworld.upload_max_kb') / 1024) }} MB" />
<div class="grid gap-4 sm:grid-cols-2">
    <x-admin.field name="issue_date" label="Issue date" type="date" :value="today()->toDateString()" required max="{{ today()->toDateString() }}" />
    <x-admin.field name="expiry_date" label="Expiry date" type="date" hint="Leave empty to use the standard validity" />
</div>
<x-admin.field name="provider_reg" label="Engineer registration no." placeholder="e.g. Gas Safe 123456" />
