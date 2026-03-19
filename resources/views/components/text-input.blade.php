@props(['disabled' => false])

<input @disabled($disabled)
    {{ $attributes->merge(['class' => 'w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-800 placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors disabled:opacity-50 disabled:cursor-not-allowed']) }}>
