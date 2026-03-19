<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-1.5 px-4 py-2 bg-[#F97316] hover:bg-[#EA580C] text-white text-sm font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#F97316]/50 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer']) }}>
    {{ $slot }}
</button>
