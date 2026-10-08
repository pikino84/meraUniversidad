<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-mera-green border border-transparent rounded-full font-semibold text-sm text-white hover:bg-mera-secondary focus:outline-none focus-visible:ring-4 focus-visible:ring-mera-accent/50 active:bg-mera-green disabled:opacity-60 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
