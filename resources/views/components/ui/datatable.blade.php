@props(['tableId'])

<div id="{{ $tableId }}-wrapper" class="overflow-hidden">

    {{-- ── Toolbar ──────────────────────────────────────────────────── --}}
    <div class="px-4 md:px-6 py-3 border-b border-gray-200 flex items-center justify-between gap-3 bg-gray-50/50">

        {{-- Search + optional filters --}}
        <div class="flex items-center gap-3 flex-1">
            <div class="relative flex-1 max-w-sm">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
                <input id="{{ $tableId }}-search" type="search" placeholder="Search…" autocomplete="off"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg bg-white text-gray-800 placeholder-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors">
            </div>
            {{ $filters ?? '' }}
        </div>

        {{-- Entries per page --}}
        <div class="flex items-center gap-2 shrink-0">
            <label for="{{ $tableId }}-per-page" class="hidden sm:inline text-[10px] font-semibold uppercase tracking-wider text-gray-400">Show</label>
            <select id="{{ $tableId }}-per-page"
                    class="border border-gray-300 rounded-lg pl-3 pr-7 py-2 text-sm font-medium text-gray-700 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] cursor-pointer transition-colors appearance-none"
                    style="background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%208l5%205%205-5%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%221.5%22%20fill%3D%22none%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E');background-position:right 0.25rem center;background-repeat:no-repeat;background-size:20px;">
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <span class="hidden sm:inline text-[10px] font-semibold uppercase tracking-wider text-gray-400">entries</span>
        </div>
    </div>

    {{-- ── Table slot ───────────────────────────────────────────────── --}}
    <div class="overflow-x-auto w-full">
        {{ $slot }}
    </div>

    {{-- ── Empty state ──────────────────────────────────────────────── --}}
    <div id="{{ $tableId }}-no-results" class="hidden flex-col items-center justify-center py-16 px-4 text-center">
        <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mb-3 border border-gray-200">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-sm font-semibold text-gray-800">No results found</p>
        <p class="text-xs text-gray-400 mt-0.5 max-w-xs">Try adjusting your search query.</p>
        <button id="{{ $tableId }}-clear-filters"
                class="mt-3 px-3 py-1.5 text-xs font-semibold text-[#F97316] border border-[#F97316]/20 rounded-lg hover:bg-[#F97316]/5 transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#F97316]/30">
            Clear search
        </button>
    </div>

    {{-- ── Footer / Pagination ──────────────────────────────────────── --}}
    <div class="px-4 md:px-6 py-3 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gray-50/30">
        <span class="text-xs font-medium text-gray-400" id="{{ $tableId }}-entries-info"></span>

        <div class="flex items-center gap-1.5" id="{{ $tableId }}-pagination" role="navigation" aria-label="Table pagination">
            <button id="{{ $tableId }}-btn-prev"
                    class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-[#F97316] transition-colors disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none"
                    aria-label="Previous page">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div id="{{ $tableId }}-page-numbers" class="flex items-center gap-1"></div>
            <button id="{{ $tableId }}-btn-next"
                    class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-[#F97316] transition-colors disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none"
                    aria-label="Next page">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</div>

<style>
.page-num-btn {
    width: 32px; height: 32px;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 500;
    cursor: pointer; transition: all 0.15s ease;
    background-color: #fff; color: #4b5563;
}
.page-num-btn:hover:not(:disabled) { border-color: #F97316; color: #F97316; background-color: #fff7ed; }
.page-num-btn.active { background-color: #F97316; color: #fff; border-color: #F97316; }

th.sortable { cursor: pointer; user-select: none; transition: background-color 0.15s ease; }
th.sortable:hover { background-color: #f8fafc; }
th.sort-asc .sort-icon-asc  { color: #F97316; }
th.sort-desc .sort-icon-desc { color: #F97316; }
th.sort-asc .sort-icon-desc,
th.sort-desc .sort-icon-asc  { color: #cbd5e1; }
.sort-icon-asc, .sort-icon-desc { color: #cbd5e1; transition: color 0.15s ease; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    class MadDataTable {
        constructor(tableId) {
            this.tableId = tableId;
            this.table = document.getElementById(tableId);
            if (!this.table) return;

            this.table.classList.add('min-w-full', 'w-full', 'divide-y', 'divide-gray-100');

            this.wrapper      = document.getElementById(`${tableId}-wrapper`);
            this.searchInput  = document.getElementById(`${tableId}-search`);
            this.perPageSelect= document.getElementById(`${tableId}-per-page`);
            this.noResults    = document.getElementById(`${tableId}-no-results`);
            this.entriesInfo  = document.getElementById(`${tableId}-entries-info`);
            this.pageNumbers  = document.getElementById(`${tableId}-page-numbers`);
            this.btnPrev      = document.getElementById(`${tableId}-btn-prev`);
            this.btnNext      = document.getElementById(`${tableId}-btn-next`);
            this.btnClear     = document.getElementById(`${tableId}-clear-filters`);

            this.tbody  = this.table.querySelector('tbody');
            if (!this.tbody) return;
            this.theads = this.table.querySelectorAll('th.sortable');

            this.state = {
                query: '', sortColIdx: -1, sortDir: 'asc',
                page: 1, perPage: parseInt(this.perPageSelect.value, 10),
            };

            const trs = Array.from(this.tbody.querySelectorAll('tr'));
            this.originalRows = trs.map((tr, index) => {
                const cells = Array.from(tr.querySelectorAll('td'));
                return {
                    originalIndex: index,
                    tr,
                    textData: cells.map(td => td.innerText.trim().toLowerCase()),
                    rawData:  cells.map(td => td.dataset.order ?? td.innerText.trim()),
                };
            });
            this.filteredRows = [...this.originalRows];

            this.bindEvents();
            this.render();
        }

        bindEvents() {
            let timer;
            this.searchInput.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    this.state.query = this.searchInput.value.trim().toLowerCase();
                    this.state.page  = 1;
                    this.updateData();
                }, 200);
            });

            this.perPageSelect.addEventListener('change', () => {
                this.state.perPage = parseInt(this.perPageSelect.value, 10);
                this.state.page    = 1;
                this.render();
            });

            this.btnClear.addEventListener('click', () => {
                this.searchInput.value = '';
                this.state.query = '';
                this.state.page  = 1;
                this.updateData();
            });

            this.theads.forEach(th => {
                th.dataset.colIdx = Array.from(th.parentNode.children).indexOf(th);
                th.addEventListener('click', () => {
                    const colIdx = parseInt(th.dataset.colIdx, 10);
                    if (this.state.sortColIdx === colIdx) {
                        this.state.sortDir = this.state.sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.state.sortColIdx = colIdx;
                        this.state.sortDir    = 'asc';
                    }
                    this.state.page = 1;
                    this.theads.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
                    th.classList.add(this.state.sortDir === 'asc' ? 'sort-asc' : 'sort-desc');
                    this.updateData();
                });
            });

            this.btnPrev.addEventListener('click', () => {
                this.state.page = Math.max(1, this.state.page - 1);
                this.render();
            });
            this.btnNext.addEventListener('click', () => {
                const total = Math.ceil(this.filteredRows.length / this.state.perPage);
                this.state.page = Math.min(total, this.state.page + 1);
                this.render();
            });
        }

        updateData() {
            this.filteredRows = this.state.query
                ? this.originalRows.filter(row => row.textData.some(t => t.includes(this.state.query)))
                : [...this.originalRows];

            if (this.state.sortColIdx !== -1) {
                const idx = this.state.sortColIdx;
                const dir = this.state.sortDir === 'asc' ? 1 : -1;
                this.filteredRows.sort((a, b) => {
                    let va = a.rawData[idx], vb = b.rawData[idx];
                    const na = parseFloat(va.replace(/[^0-9.-]+/g, ''));
                    const nb = parseFloat(vb.replace(/[^0-9.-]+/g, ''));
                    if (!isNaN(na) && !isNaN(nb)) { va = na; vb = nb; }
                    else { va = va.toLowerCase(); vb = vb.toLowerCase(); }
                    return va < vb ? -dir : va > vb ? dir : 0;
                });
            } else if (!this.state.query) {
                this.filteredRows.sort((a, b) => a.originalIndex - b.originalIndex);
            }

            this.render();
        }

        render() {
            const total      = this.filteredRows.length;
            const totalPages = Math.ceil(total / this.state.perPage);
            if (this.state.page > totalPages && totalPages > 0) this.state.page = totalPages;

            const start = total === 0 ? 0 : (this.state.page - 1) * this.state.perPage + 1;
            const end   = Math.min(this.state.page * this.state.perPage, total);

            if (total === 0) {
                this.noResults.classList.replace('hidden', 'flex');
                this.table.style.display = 'none';
                this.entriesInfo.textContent = 'No entries to show';
                this.pageNumbers.innerHTML   = '';
                this.btnPrev.disabled = this.btnNext.disabled = true;
                return;
            }

            this.noResults.classList.replace('flex', 'hidden');
            this.table.style.display = 'table';
            this.entriesInfo.innerHTML = `Showing <strong class="text-gray-700">${start}</strong> – <strong class="text-gray-700">${end}</strong> of <strong class="text-gray-700">${total}</strong>`;

            this.tbody.innerHTML = '';
            this.filteredRows.slice(start - 1, end).forEach(row => this.tbody.appendChild(row.tr));

            this.renderPagination(totalPages);
            this.btnPrev.disabled = this.state.page <= 1;
            this.btnNext.disabled = this.state.page >= totalPages;
        }

        renderPagination(totalPages) {
            if (totalPages <= 1) { this.pageNumbers.innerHTML = ''; return; }
            const cur   = this.state.page;
            const pages = [];
            if (totalPages <= 7) {
                for (let i = 1; i <= totalPages; i++) pages.push(i);
            } else {
                pages.push(1);
                if (cur > 3) pages.push('…');
                for (let i = Math.max(2, cur - 1); i <= Math.min(totalPages - 1, cur + 1); i++) pages.push(i);
                if (cur < totalPages - 2) pages.push('…');
                pages.push(totalPages);
            }
            this.pageNumbers.innerHTML = pages.map(p =>
                p === '…'
                    ? `<span class="w-8 text-center text-gray-400 text-xs select-none">…</span>`
                    : `<button class="page-num-btn ${p === cur ? 'active' : ''}" data-page="${p}">${p}</button>`
            ).join('');
            this.pageNumbers.querySelectorAll('button').forEach(btn =>
                btn.addEventListener('click', e => {
                    const p = parseInt(e.currentTarget.dataset.page, 10);
                    if (!isNaN(p)) { this.state.page = p; this.render(); }
                })
            );
        }
    }

    if (!window.MadDataTables) window.MadDataTables = {};
    window.MadDataTableClass = MadDataTable;
    window.MadDataTables['{{ $tableId }}'] = new MadDataTable('{{ $tableId }}');
});
</script>
