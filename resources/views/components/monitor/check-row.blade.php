{{--
    One check: status dot, label, current value, the threshold it is measured
    against, and an optional runbook link.

    Two layouts, because the same row has to live in a full-width panel and in a
    quarter-width node card. `stacked` puts the value under the label instead of
    beside it — without it, a long value (d2's "upstream branch EOL since … plan
    a migration" is ~140 characters) squeezes the label column to nothing and
    the card wraps one word per line.
--}}
@props(['expr', 'stacked' => false])

<div class="px-3 py-2 border-t border-gray-100 first:border-t-0">
    <div class="{{ $stacked ? 'flex flex-col gap-1' : 'flex items-start gap-2.5' }}">

        <div class="flex items-start gap-2 min-w-0 flex-1">
            <span class="w-2 h-2 rounded-full shrink-0 mt-1.5" :class="tone({{ $expr }}.status).dot"></span>

            <div class="min-w-0">
                <div class="flex items-baseline gap-2 flex-wrap">
                    <span class="text-[10px] font-mono text-gray-400" x-text="{{ $expr }}.key"></span>
                    <span class="text-xs font-semibold text-gray-800" x-text="{{ $expr }}.label"></span>
                </div>
                <p class="text-[11px] text-gray-400 mt-0.5" x-show="{{ $expr }}.threshold" x-cloak
                   x-text="{{ $expr }}.threshold"></p>
            </div>
        </div>

        {{-- min-w-0 and the wrap rule are what stop a long unbroken value (a
             version string like 8.0.46-0ubuntu0.24.04.3) from forcing the row
             wider than its card. --}}
        <div class="min-w-0 {{ $stacked ? 'pl-4' : 'text-right shrink max-w-[55%]' }}">
            <span class="text-xs font-semibold [overflow-wrap:anywhere]"
                  :class="tone({{ $expr }}.status).text"
                  x-text="{{ $expr }}.value ?? statusLabel({{ $expr }}.status)"></span>
            <template x-if="{{ $expr }}.link">
                <a :href="{{ $expr }}.link" target="_blank" rel="noopener"
                   class="block text-[10px] text-orange-500 hover:underline">runbook</a>
            </template>
        </div>
    </div>
</div>
