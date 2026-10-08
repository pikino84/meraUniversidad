@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm text-mera-green']) }}>
    {{ $value ?? $slot }}
</label>
