<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 hover:border-gray-300 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer']) }}>
    {{ $slot }}
</button>
