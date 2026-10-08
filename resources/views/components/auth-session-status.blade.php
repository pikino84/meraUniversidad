@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-xl bg-mera-accent/15 px-4 py-3 font-medium text-sm text-mera-green']) }} role="status">
        {{ $status }}
    </div>
@endif
