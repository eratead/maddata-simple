@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1.5']) }}>
    {{ $value ?? $slot }}
</label>
