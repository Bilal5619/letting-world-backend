@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false])
@php($id = 'f-'.str_replace(['[', ']', '.'], '-', $name).'-'.\Illuminate\Support\Str::random(4))
<div>
    <label for="{{ $id }}" class="lw-label">{{ $label }}@if ($required)<span class="text-bad"> *</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="3" @required($required) aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" {{ $attributes->class('lw-input') }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' || $type === 'file' ? '' : old($name, $value) }}" @required($required) aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" {{ $attributes->class(['lw-input', 'py-2 file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-2.5 file:py-1 file:text-xs file:font-medium file:text-accent' => $type === 'file']) }}>
    @endif
    @error($name)
        <p class="lw-error">{{ $message }}</p>
    @else
        @if ($hint)<p class="lw-hint">{{ $hint }}</p>@endif
    @enderror
</div>
