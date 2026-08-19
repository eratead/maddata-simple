@props(['status'])

@php
    /** Complete class literals — Tailwind's JIT cannot see interpolated names. */
    $tone = match ($status) {
        \App\Enums\HealthStatus::OK => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'ring' => 'border-emerald-200 bg-emerald-50'],
        \App\Enums\HealthStatus::WARN => ['dot' => 'bg-amber-500', 'text' => 'text-amber-700', 'ring' => 'border-amber-200 bg-amber-50'],
        \App\Enums\HealthStatus::CRIT => ['dot' => 'bg-red-500', 'text' => 'text-red-700', 'ring' => 'border-red-200 bg-red-50'],
        default => ['dot' => 'bg-gray-400', 'text' => 'text-gray-600', 'ring' => 'border-gray-200 bg-gray-50'],
    };
@endphp

<a href="{{ route('admin.monitor.index') }}"
   data-testid="health-pill"
   title="System health — {{ $status->label() }}"
   class="flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px] font-semibold shrink-0
          transition-colors hover:opacity-80 {{ $tone['ring'] }} {{ $tone['text'] }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $tone['dot'] }}"></span>
    <span class="hidden sm:inline">{{ $status->label() }}</span>
</a>
