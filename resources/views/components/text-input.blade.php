@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-full border-gray-300 px-4 py-2.5 shadow-sm focus:border-mera-sky focus:ring focus:ring-mera-sky/30']) }}>
