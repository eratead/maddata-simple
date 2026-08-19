<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800">System Health</h1>
@endpush

@push('styles')
<style>
    /* STALE is "I cannot see", which must not look like "fine". */
    .stale-stripes {
        background-image: repeating-linear-gradient(45deg,
            rgba(148,163,184,0.10) 0, rgba(148,163,184,0.10) 6px,
            transparent 6px, transparent 12px);
    }
</style>
@endpush

{{--
    Everything below renders from `state`, never from Blade. Blade seeds it once
    via @js() and the poller replaces it wholesale — two rendering paths for one
    shape is how the "initial" and "refreshed" views drift apart.
--}}
<div x-data="healthMonitor(@js($snapshot), @js([
        'pollSeconds' => $pollSeconds,
        'staleSeconds' => $staleSeconds,
        'kpiKeys' => $kpiKeys,
        'dataUrl' => route('admin.monitor.data'),
        'refreshUrl' => route('admin.monitor.refresh'),
     ]))" x-init="start()" class="space-y-4">

    {{-- ── Overall banner ─────────────────────────────────────────────── --}}
    <div class="rounded-lg border px-4 py-3 flex items-center gap-3 flex-wrap"
         :class="[tone(state.overall).border, tone(state.overall).bg]">

        <span class="w-3 h-3 rounded-full shrink-0" :class="tone(state.overall).dot"></span>
        <span class="text-sm font-black uppercase tracking-wide" :class="tone(state.overall).text"
              x-text="statusLabel(state.overall)"></span>
        <span class="text-xs text-gray-500" x-show="failing().length" x-cloak
              x-text="`(${failing().length} failing)`"></span>

        <span class="ml-auto text-xs text-gray-400" x-text="`refreshed ${agoLabel()}`"></span>

        <span x-show="throttled" x-cloak class="text-xs text-amber-700">refreshing too fast — try again in a moment</span>

        <button type="button" @click="refreshNow()" :disabled="refreshing"
                class="text-xs font-semibold px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700
                       hover:bg-gray-50 disabled:opacity-50 disabled:cursor-wait cursor-pointer shrink-0">
            <span x-show="!refreshing">Refresh now</span>
            <span x-show="refreshing" x-cloak>Refreshing…</span>
        </button>
    </div>

    {{--
        The stale badge is load-bearing. snapshot_ttl is 300s and the
        last-known-good key has no TTL at all, so a dead scheduler would
        otherwise render a confidently green page forever.
    --}}
    <div x-show="isStale()" x-cloak
         class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 flex items-center gap-2">
        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
        </svg>
        <p class="text-sm font-semibold text-amber-800">
            This snapshot is <span x-text="ageLabel()"></span> old — the scheduler may be down.
            What you see below is history, not the current state.
        </p>
    </div>

    {{-- Poll failure: keep the last good state, say so, never blank. --}}
    <div x-show="error" x-cloak
         class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5">
        <p class="text-sm font-semibold text-amber-800">
            Live updates interrupted — showing data from <span x-text="generatedAtLabel()"></span>.
        </p>
    </div>

    {{-- ── KPI strip ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
        <template x-for="kpi in kpis()" :key="kpi.key">
            <x-monitor.kpi-tile expr="kpi" />
        </template>
    </div>

    {{-- ── Failing checks, first and already open ─────────────────────── --}}
    <x-page-box x-show="failing().length" x-cloak class="overflow-hidden">
        <div class="px-4 py-2.5 border-b border-gray-200 bg-gray-50">
            <h2 class="text-xs font-black uppercase tracking-wider text-gray-700">Needs attention</h2>
        </div>
        <template x-for="check in failing()" :key="'f-' + check.key">
            <x-monitor.check-row expr="check" />
        </template>
    </x-page-box>

    {{-- ── The map ────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 items-start">
        <template x-for="node in state.nodes" :key="node.key">
            <x-monitor.node-card expr="node" />
        </template>
    </div>

    <p class="text-[11px] text-gray-400">
        Checks and thresholds: <code class="text-gray-500">config/health.php</code> ·
        Runbook: <a href="{{ config('health.links.monitor') }}" class="text-orange-500 hover:underline">docs/runbooks/health-monitor.md</a> ·
        Same data from the CLI: <code class="text-gray-500">php artisan health:check</code> ·
        <a href="{{ route('admin.system-status.index') }}" class="text-orange-500 hover:underline">System Status controls</a>
    </p>
</div>

@push('scripts')
<script>
    function healthMonitor(initial, opts) {
        // Complete Tailwind class literals, never `bg-${token}-500`: the JIT
        // compiler cannot see interpolated names and would ship this page with
        // no colours at all.
        const TONES = {
            ok:    { dot: 'bg-emerald-500', text: 'text-emerald-700', bg: 'bg-emerald-50', border: 'border-emerald-200' },
            warn:  { dot: 'bg-amber-500',   text: 'text-amber-700',   bg: 'bg-amber-50',   border: 'border-amber-200'   },
            crit:  { dot: 'bg-red-500',     text: 'text-red-700',     bg: 'bg-red-50',     border: 'border-red-200'     },
            stale: { dot: 'bg-gray-400',    text: 'text-gray-600',    bg: 'bg-gray-50',    border: 'border-gray-200'    },
        };
        const LABELS = { ok: 'All systems go', warn: 'Degraded', crit: 'Outage', stale: 'Unknown' };

        return {
            state: initial,
            opts: opts,
            error: false,
            refreshing: false,
            throttled: false,
            now: Math.floor(Date.now() / 1000),
            fetchedAt: Math.floor(Date.now() / 1000),
            inflight: false,
            closed: {},
            timer: null,
            ticker: null,

            start() {
                this.ticker = setInterval(() => {
                    // Gated too: polling already stops in a hidden tab, but an
                    // ungated ticker keeps waking Alpine effects every second
                    // and stops the browser idling the tab at all.
                    if (! document.hidden) this.now = Math.floor(Date.now() / 1000);
                }, 1000);
                this.schedule();
                document.addEventListener('visibilitychange', () => {
                    // Poll immediately on return: a backgrounded tab must not
                    // hold the marker store awake, and a foregrounded one must
                    // not show minutes-old data for another full interval.
                    if (!document.hidden) this.poll();
                    this.schedule();
                });
            },

            schedule() {
                clearInterval(this.timer);
                this.timer = setInterval(() => {
                    if (!document.hidden) this.poll();
                }, this.opts.pollSeconds * 1000);
            },

            async poll() {
                // Rapid alt-tabbing fires visibilitychange repeatedly; without
                // this the polls stack up and burn the 60/min budget.
                if (this.inflight) return;
                this.inflight = true;

                try {
                    const res = await fetch(this.opts.dataUrl, { headers: { 'Accept': 'application/json' } });
                    // A throttle is not a monitoring outage and must not look
                    // like one — keep the last good state, say nothing.
                    if (res.status === 429) return;
                    if (!res.ok) throw new Error(res.status);
                    this.state = await res.json();
                    this.fetchedAt = Math.floor(Date.now() / 1000);
                    this.error = false;
                } catch (e) {
                    // Keep the last good state. A monitor that blanks out when
                    // it cannot reach itself is worse than one that says so.
                    this.error = true;
                } finally {
                    this.inflight = false;
                }
            },

            async refreshNow() {
                this.refreshing = true;
                try {
                    const res = await fetch(this.opts.refreshUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    if (res.status === 429) { this.throttled = true; setTimeout(() => this.throttled = false, 4000); return; }
                    if (!res.ok) throw new Error(res.status);
                    this.state = await res.json();
                    this.fetchedAt = Math.floor(Date.now() / 1000);
                    this.error = false;
                } catch (e) {
                    this.error = true;
                } finally {
                    // Re-enabled on failure too, or one bad click disables the
                    // button for as long as the page stays open.
                    this.refreshing = false;
                }
            },

            tone(status) { return TONES[status] ?? TONES.stale; },
            statusLabel(status) { return LABELS[status] ?? LABELS.stale; },

            checksIn(nodeKey) { return this.state.checks.filter(c => c.node === nodeKey); },
            failingIn(nodeKey) { return this.checksIn(nodeKey).filter(c => c.status !== 'ok'); },
            failing() { return this.state.checks.filter(c => c.status !== 'ok'); },

            kpis() {
                return this.opts.kpiKeys
                    .map(key => this.state.checks.find(c => c.key === key))
                    .filter(Boolean);
            },

            // Nodes are open by default; a click closes one.
            isOpen(key) { return !this.closed[key]; },
            toggle(key) { this.closed[key] = !this.closed[key]; },

            // Server-computed age plus local elapsed time since we fetched it.
            // Never browser-clock-vs-server-clock: a slow laptop clock used to
            // hide the stale banner entirely.
            ageSeconds() {
                return (this.state.age_seconds ?? 0) + Math.max(0, this.now - this.fetchedAt);
            },
            isStale() { return this.ageSeconds() > this.opts.staleSeconds; },
            ageLabel() {
                const s = this.ageSeconds();
                if (s < 60) return `${s}s`;
                if (s < 3600) return `${Math.floor(s / 60)}m`;
                return `${Math.floor(s / 3600)}h`;
            },
            agoLabel() { return `${this.ageLabel()} ago`; },
            generatedAtLabel() {
                return new Date(this.state.generated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            },
        };
    }
</script>
@endpush

</x-app-layout>
