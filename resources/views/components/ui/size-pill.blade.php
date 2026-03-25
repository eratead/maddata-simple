<div @click="toggleSize(size)"
    :class="{
        'bg-[#F97316] text-white border-[#F97316]': selectedSizes.includes(size),
        'bg-white text-gray-500 border-gray-200 hover:border-[#F97316] hover:text-[#F97316] hover:bg-orange-50 cursor-pointer': canEdit && !selectedSizes.includes(size),
        'bg-white text-gray-400 border-gray-200 cursor-not-allowed pointer-events-none opacity-60': !canEdit && !selectedSizes.includes(size)
    }"
    class="px-3 py-1.5 text-xs font-medium rounded-full transition-colors select-none border"
    x-text="size">
</div>
