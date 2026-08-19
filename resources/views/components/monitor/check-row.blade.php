{{-- One check: status dot, label, current value, the threshold it is measured
     against, and an optional runbook link. --}}
@props(['expr'])

<div class="flex items-start gap-2.5 px-3 py-2 border-t border-gray-100 first:border-t-0">
    <span class="w-2 h-2 rounded-full shrink-0 mt-1.5" :class="tone({{ $expr }}.status).dot"></span>

    <div class="min-w-0 flex-1">
        <div class="flex items-baseline gap-2 flex-wrap">
            <span class="text-[10px] font-mono text-gray-400" x-text="{{ $expr }}.key"></span>
            <span class="text-xs font-semibold text-gray-800" x-text="{{ $expr }}.label"></span>
        </div>
        <p class="text-[11px] text-gray-400 mt-0.5" x-show="{{ $expr }}.threshold" x-cloak
           x-text="{{ $expr }}.threshold"></p>
    </div>

    <div class="text-right shrink-0">
        <span class="text-xs font-semibold" :class="tone({{ $expr }}.status).text"
              x-text="{{ $expr }}.value ?? statusLabel({{ $expr }}.status)"></span>
        <template x-if="{{ $expr }}.link">
            <a :href="{{ $expr }}.link" target="_blank" rel="noopener"
               class="block text-[10px] text-orange-500 hover:underline">runbook</a>
        </template>
    </div>
</div>
