{{-- One node column: its worst-of status (computed server-side — the UI never
     recomputes a rollup) and every check that belongs to it. --}}
@props(['expr'])

<div class="rounded-lg border bg-white overflow-hidden"
     :class="[tone({{ $expr }}.status).border, {{ $expr }}.status === 'stale' ? 'stale-stripes' : '']">

    <button type="button"
            class="w-full flex items-center gap-2 px-3 py-2 text-left cursor-pointer"
            :class="tone({{ $expr }}.status).bg"
            @click="toggle({{ $expr }}.key)">
        <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="tone({{ $expr }}.status).dot"></span>
        <span class="text-xs font-black uppercase tracking-wider truncate"
              :class="tone({{ $expr }}.status).text" x-text="{{ $expr }}.label"></span>
        <span class="ml-auto text-[10px] text-gray-400 shrink-0"
              x-text="failingIn({{ $expr }}.key).length || ''"></span>
    </button>

    <div x-show="isOpen({{ $expr }}.key)" x-cloak>
        {{-- Stacked: these cards are a quarter of the grid, far too narrow to
             put a sentence-length value beside a label. --}}
        <template x-for="check in checksIn({{ $expr }}.key)" :key="check.key">
            <x-monitor.check-row expr="check" stacked />
        </template>
    </div>
</div>
