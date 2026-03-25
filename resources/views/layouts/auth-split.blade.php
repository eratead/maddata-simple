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
    <div class="relative z-10">
      <img src="{{ asset('images/madata_logo_white_orange.png') }}" alt="MadData" class="h-8">
    </div>

    {{-- Hero copy + features --}}
    <div class="relative z-10">
      <p class="text-[10px] font-semibold uppercase tracking-widest text-[#F97316] mb-3">Predictive Precision Mobile Platform</p>
      <h1 class="text-2xl font-black text-white leading-tight mb-4">
        Precision Mobile DSP for Targeted Audience Engagement
      </h1>
      <p class="text-sm text-slate-400 leading-relaxed">
        A specialized mobile advertising solution focused on Long Tail and Upper-Mid Funnel activities. By leveraging high-impact full-screen mobile interstitials across premium Israeli and global media (News, Finance, Sports, and Gaming), the platform delivers high-quality awareness and precise audience reach. The system utilizes advanced Device ID and GPS targeting to ensure maximum accuracy, providing cost-effective traffic with high engagement rates compared to traditional channels.
      </p>

      {{-- Feature bullets --}}
      <div class="mt-8 flex flex-col gap-3.5">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
          </div>
          <div>
            <p class="text-white text-sm font-bold leading-none">Hyper-Precise Targeting</p>
            <p class="text-slate-500 text-[11px] mt-0.5">Precision-driven reach using Device ID and real-time GPS data to pinpoint users across hundreds of specific audience segments.</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-[#F97316]/10 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-[#F97316]" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
          </div>
          <div>
            <p class="text-white text-sm font-bold leading-none">Premium Mobile Impact</p>
            <p class="text-slate-500 text-[11px] mt-0.5">High-visibility placements through full-screen interstitials on top-tier news, finance, and leisure platforms.</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
          </div>
          <div>
            <p class="text-white text-sm font-bold leading-none">Optimized Performance</p>
            <p class="text-slate-500 text-[11px] mt-0.5">Cost-effective engagement featuring high CTR and remarketing integration.</p>
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
