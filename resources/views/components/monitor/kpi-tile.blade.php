{{--
    One KPI tile. `expr` is the name of the Alpine variable holding the check,
    so the caller stays in charge of the loop and this file has no hidden
    coupling to a particular variable name.
--}}
@props(['expr'])

<div class="rounded-lg border bg-white px-3 py-2 min-w-0"
     :class="tone({{ $expr }}.status).border">
    <div class="flex items-center gap-1.5">
        <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="tone({{ $expr }}.status).dot"></span>
        <span class="text-[10px] uppercase tracking-wider font-semibold text-gray-500 truncate"
              x-text="{{ $expr }}.label"></span>
    </div>
    <p class="text-sm font-black text-gray-900 mt-0.5 truncate"
       x-text="{{ $expr }}.value ?? '—'"></p>
</div>
