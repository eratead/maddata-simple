<div x-data="{ open: @entangle($attributes->wire('model')) }" x-show="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" style="display: none;">
        <div @click.away="open = false" class="bg-white rounded shadow-lg w-full max-w-md p-6">
                <div class="text-lg font-semibold mb-4">
                        {{ $title }}
                </div>
                <div class="mb-4">
                        {{ $slot }}
                </div>
                <div class="text-right">
                        {{ $actions ?? '' }}
                </div>
        </div>
</div>
