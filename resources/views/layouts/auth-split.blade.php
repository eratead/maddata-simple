<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ config('app.name', 'MadData') }} — {{ $title }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>body { font-family: 'Rubik', sans-serif; }</style>
</head>
<body class="antialiased bg-white">

<div class="min-h-screen flex">

  {{-- ── Left branding panel (hidden on mobile) ── --}}
  <div class="hidden md:flex md:w-5/12 lg:w-2/5 flex-col justify-between bg-[#111827] p-10 relative overflow-hidden shrink-0">

    {{-- Decorative ambient circles --}}
    <div class="absolute -top-24 -left-24 w-80 h-80 rounded-full bg-[#F97316]/5 pointer-events-none"></div>
    <div class="absolute bottom-16 -right-20 w-64 h-64 rounded-full bg-[#F97316]/5 pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 rounded-full bg-white/[0.01] pointer-events-none border border-white/5"></div>

    {{-- Logo --}}
    <div class="flex items-center gap-3 relative z-10">
      <div class="w-9 h-9 bg-[#F97316] rounded-lg flex items-center justify-center shadow-lg">
        <span class="text-white font-black text-lg leading-none">M</span>
      </div>
      <span class="text-white font-bold text-lg tracking-tight">Mad<span class="text-[#F97316]">Data</span></span>
    </div>

    {{-- Hero copy + stats --}}
    <div class="relative z-10">
      <p class="text-[10px] font-semibold uppercase tracking-widest text-[#F97316] mb-3">Predictive AdTech Platform</p>
      <h1 class="text-3xl font-black text-white leading-tight mb-4">
        Beyond reporting.<br>True campaign<br>intelligence.
      </h1>
      <p class="text-sm text-slate-400 leading-relaxed max-w-xs">
        Leverage AI-driven insights to uncover hidden audience patterns, optimize creative delivery, and scale your media buying automatically.
      </p>

      {{-- Feature bullets --}}
      <div class="mt-8 flex flex-col gap-3.5">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 6c0 1.657-3.134 3-7 3S5 7.657 5 6m14 0c0-1.657-3.134-3-7-3S5 4.343 5 6m14 0v6M5 6v6m0 0c0 1.657 3.134 3 7 3s7-1.343 7-3M5 12v6c0 1.657 3.134 3 7 3s7-1.343 7-3v-6"/>
            </svg>
          </div>
          <div>
            <p class="text-white text-sm font-bold leading-none">First-Party Data</p>
            <p class="text-slate-500 text-[11px] mt-0.5">Build and activate your private audience DMP.</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-[#F97316]/10 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-[#F97316]" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18.5A2.493 2.493 0 0 1 7.51 20H7.5a2.468 2.468 0 0 1-2.4-3.154 2.98 2.98 0 0 1-.85-5.274 2.468 2.468 0 0 1 .92-3.182 2.477 2.477 0 0 1 1.876-3.344 2.5 2.5 0 0 1 3.41-1.856A2.5 2.5 0 0 1 12 5.5m0 13v-13m0 13a2.493 2.493 0 0 0 4.49 1.5h.01a2.468 2.468 0 0 0 2.403-3.154 2.98 2.98 0 0 0 .847-5.274 2.468 2.468 0 0 0-.921-3.182 2.477 2.477 0 0 0-1.875-3.344A2.5 2.5 0 0 0 14.5 3 2.5 2.5 0 0 0 12 5.5m-8 5a2.5 2.5 0 0 1 3.48-2.3m-.28 8.551a3 3 0 0 1-2.953-5.185M20 10.5a2.5 2.5 0 0 0-3.481-2.3m.28 8.551a3 3 0 0 0 2.954-5.185"/>
            </svg>
          </div>
          <div>
            <p class="text-white text-sm font-bold leading-none">Algorithmic Trading</p>
            <p class="text-slate-500 text-[11px] mt-0.5">Automated anomaly detection & budget pruning.</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.5 11.5 11 13l4-3.5M12 20a16.405 16.405 0 0 1-5.092-5.804A16.694 16.694 0 0 1 5 6.666L12 4l7 2.667a16.695 16.695 0 0 1-1.908 7.529A16.406 16.406 0 0 1 12 20Z"/>
            </svg>
          </div>
          <div>
            <p class="text-white text-sm font-bold leading-none">Deep Transparency</p>
            <p class="text-slate-500 text-[11px] mt-0.5">Hourly performance data down to the line-item level.</p>
          </div>
        </div>
      </div>
    </div>

    <p class="text-[11px] text-slate-600 relative z-10">© {{ date('Y') }} MadData Media. All rights reserved.</p>
  </div>

  {{-- ── Right form panel ── --}}
  <div class="flex-1 flex items-center justify-center bg-white px-6 py-12">
    <div class="w-full max-w-sm">

      {{-- Mobile-only logo --}}
      <div class="flex items-center gap-2 mb-8 md:hidden">
        <div class="w-8 h-8 bg-[#F97316] rounded-lg flex items-center justify-center">
          <span class="text-white font-black text-base leading-none">M</span>
        </div>
        <span class="text-gray-900 font-bold text-base tracking-tight">Mad<span class="text-[#F97316]">Data</span></span>
      </div>

      {{ $slot }}

    </div>
  </div>

</div>
</body>
</html>
