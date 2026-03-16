<div @click="toggleSize(size)"
    :class="{
        'bg-primary text-white border-primary shadow-[0_2px_4px_rgba(79,70,229,0.2)]': selectedSizes.includes(size),
        'bg-white text-gray-500 border-gray-200': !selectedSizes.includes(size),
        'hover:border-primary hover:text-primary hover:bg-indigo-50 cursor-pointer': isAdmin && !selectedSizes.includes(size),
        'cursor-not-allowed pointer-events-none opacity-60': !isAdmin
    }"
    class="px-3.5 py-1.5 text-xs font-medium rounded-full transition-colors select-none border"
    x-text="size">
</div>
