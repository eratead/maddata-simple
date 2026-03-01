@props(['tableId'])

<div id="{{ $tableId }}-wrapper" class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden mt-4">
    <!-- Toolbar -->
    <div class="px-4 md:px-6 py-4 border-b border-gray-100 flex flex-row items-center justify-between gap-3 bg-gray-50/50">
        <!-- Search -->
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
            </svg>
            <input id="{{ $tableId }}-search" type="search" placeholder="Search..." autocomplete="off" class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
        </div>
        
        <!-- Entries per page -->
        <div class="flex items-center gap-2 shrink-0">
            <label for="{{ $tableId }}-per-page" class="hidden sm:inline-block text-xs text-gray-500 font-medium uppercase tracking-wide">Show</label>
            <select id="{{ $tableId }}-per-page" class="border border-gray-200 rounded-lg pl-3 pr-8 py-1.5 text-sm font-medium text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary cursor-pointer transition-colors duration-200 shadow-sm appearance-none" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%208l5%205%205-5%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%221.5%22%20fill%3D%22none%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E'); background-position: right 0.25rem center; background-repeat: no-repeat; background-size: 20px;">
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <span class="hidden sm:inline-block text-xs text-gray-500 font-medium uppercase tracking-wide">entries</span>
        </div>
    </div>

    <!-- Responsive table wrapper -->
    <div class="overflow-x-auto w-full">
        {{ $slot }}
    </div>

    <!-- No Results -->
    <div id="{{ $tableId }}-no-results" class="hidden flex-col items-center justify-center py-16 px-4 text-center bg-white">
        <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mb-3 border border-gray-100">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <p class="text-base font-semibold text-gray-900">No results found</p>
        <p class="text-sm text-gray-500 mt-1 max-w-sm">Try adjusting your search query to find what you're looking for.</p>
        <button id="{{ $tableId }}-clear-filters" class="mt-4 px-4 py-2 text-sm font-medium text-primary border border-primary/20 rounded-lg hover:bg-primary/5 transition-colors duration-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/30">
            Clear search
        </button>
    </div>

    <!-- Footer -->
    <div class="px-4 md:px-6 py-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4 bg-gray-50/30">
        <span class="text-sm font-medium text-gray-500 text-center sm:text-left" id="{{ $tableId }}-entries-info"></span>
        
        <!-- Pagination -->
        <div class="flex items-center gap-1.5" id="{{ $tableId }}-pagination" role="navigation" aria-label="Table pagination">
            <button class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-primary transition-all disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-primary/20" id="{{ $tableId }}-btn-prev" aria-label="Previous page">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div id="{{ $tableId }}-page-numbers" class="flex items-center gap-1"></div>
            <button class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-primary transition-all disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-primary/20" id="{{ $tableId }}-btn-next" aria-label="Next page">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</div>

<style>
/* Inject dynamic CSS logic for pagination states and sort headers without polluting global CSS heavily */
.page-num-btn {
    width: 32px;
    height: 32px;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    background-color: #ffffff;
    color: #4b5563;
}
.page-num-btn:hover:not(:disabled) {
    border-color: var(--color-primary-hover, #2563EB);
    color: var(--color-primary-hover, #2563EB);
    background-color: #eff6ff;
}
.page-num-btn.active {
    background-color: var(--color-primary, #2563EB);
    color: #ffffff;
    border-color: var(--color-primary, #2563EB);
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

th.sortable {
    cursor: pointer;
    user-select: none;
    transition: background-color 0.15s ease;
}
th.sortable:hover {
    background-color: #f8fafc;
}
th.sort-asc .sort-icon-asc {
    color: var(--color-primary, #2563EB);
}
th.sort-desc .sort-icon-desc {
    color: var(--color-primary, #2563EB);
}
th.sort-asc .sort-icon-desc,
th.sort-desc .sort-icon-asc {
    color: #cbd5e1;
}
.sort-icon-asc,
.sort-icon-desc {
    color: #cbd5e1;
    transition: color 0.15s ease;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    class MadDataTable {
        constructor(tableId) {
            this.tableId = tableId;
            this.table = document.getElementById(tableId);
            if (!this.table) return;

            // Optional: configure row styling if standard classes are missing
            this.table.classList.add('min-w-full', 'w-full', 'divide-y', 'divide-gray-100');

            this.wrapper = document.getElementById(`${tableId}-wrapper`);
            this.searchInput = document.getElementById(`${tableId}-search`);
            this.perPageSelect = document.getElementById(`${tableId}-per-page`);
            this.noResults = document.getElementById(`${tableId}-no-results`);
            this.entriesInfo = document.getElementById(`${tableId}-entries-info`);
            this.pageNumbers = document.getElementById(`${tableId}-page-numbers`);
            this.btnPrev = document.getElementById(`${tableId}-btn-prev`);
            this.btnNext = document.getElementById(`${tableId}-btn-next`);
            this.btnClear = document.getElementById(`${tableId}-clear-filters`);

            this.tbody = this.table.querySelector('tbody');
            if(!this.tbody) return;
            
            this.theads = this.table.querySelectorAll('th.sortable');

            this.state = {
                query: '',
                sortColIdx: -1,
                sortDir: 'asc',
                page: 1,
                perPage: parseInt(this.perPageSelect.value, 10),
            };

            // Read original rows
            const trs = Array.from(this.tbody.querySelectorAll('tr'));
            this.originalRows = trs.map((tr, index) => {
                const cells = Array.from(tr.querySelectorAll('td'));
                return {
                    originalIndex: index,
                    tr: tr,
                    // textData is used for searching
                    textData: cells.map(td => td.innerText.trim().toLowerCase()),
                    // rawData is used for sorting
                    rawData: cells.map(td => td.innerText.trim())
                };
            });

            this.filteredRows = [...this.originalRows];

            this.bindEvents();
            this.render();
        }

        bindEvents() {
            let searchTimer;
            this.searchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    this.state.query = this.searchInput.value.trim().toLowerCase();
                    this.state.page = 1;
                    this.updateData();
                }, 200);
            });

            this.perPageSelect.addEventListener('change', () => {
                this.state.perPage = parseInt(this.perPageSelect.value, 10);
                this.state.page = 1;
                this.render();
            });

            this.btnClear.addEventListener('click', () => {
                this.searchInput.value = '';
                this.state.query = '';
                this.state.page = 1;
                this.updateData();
            });

            this.theads.forEach(th => {
                // Determine column index based on DOM position
                th.dataset.colIdx = Array.from(th.parentNode.children).indexOf(th);
                th.addEventListener('click', () => {
                    const colIdx = parseInt(th.dataset.colIdx, 10);
                    if (this.state.sortColIdx === colIdx) {
                        this.state.sortDir = this.state.sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.state.sortColIdx = colIdx;
                        this.state.sortDir = 'asc';
                    }
                    this.state.page = 1;
                    
                    // Update header classes for CSS UI toggling
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
                const totalPages = Math.ceil(this.filteredRows.length / this.state.perPage);
                this.state.page = Math.min(totalPages, this.state.page + 1);
                this.render();
            });
        }

        updateData() {
            // Apply text filter
            if (this.state.query) {
                this.filteredRows = this.originalRows.filter(row => {
                    return row.textData.some(cellText => cellText.includes(this.state.query));
                });
            } else {
                this.filteredRows = [...this.originalRows];
            }

            // Apply sort directions
            if (this.state.sortColIdx !== -1) {
                const idx = this.state.sortColIdx;
                const dir = this.state.sortDir === 'asc' ? 1 : -1;
                this.filteredRows.sort((a, b) => {
                    let va = a.rawData[idx];
                    let vb = b.rawData[idx];
                    
                    // Simple logic to parse currencies/numbers if present
                    let na = parseFloat(va.replace(/[^0-9.-]+/g,""));
                    let nb = parseFloat(vb.replace(/[^0-9.-]+/g,""));
                    if (!isNaN(na) && !isNaN(nb) && String(na).length > 0) {
                        va = na; vb = nb;
                    } else {
                        va = va.toLowerCase(); vb = vb.toLowerCase();
                    }

                    if (va < vb) return -1 * dir;
                    if (va > vb) return 1 * dir;
                    return 0;
                });
            } else {
                // If query changed and custom sort is off, maintain original order
                if(!this.state.query) {
                    this.filteredRows.sort((a,b) => a.originalIndex - b.originalIndex);
                }
            }

            this.render();
        }

        render() {
            const total = this.filteredRows.length;
            const totalPages = Math.ceil(total / this.state.perPage);
            
            // Re-validate bounds
            if (this.state.page > totalPages && totalPages > 0) this.state.page = totalPages;
            
            const start = total === 0 ? 0 : (this.state.page - 1) * this.state.perPage + 1;
            const end = Math.min(this.state.page * this.state.perPage, total);

            if (total === 0) {
                this.noResults.classList.replace('hidden', 'flex');
                this.table.style.display = 'none';
                this.entriesInfo.textContent = 'No entries to show';
                this.pageNumbers.innerHTML = '';
                this.btnPrev.disabled = true;
                this.btnNext.disabled = true;
                return;
            }

            this.noResults.classList.replace('flex', 'hidden');
            this.table.style.display = 'table';
            this.entriesInfo.innerHTML = `Showing <span class="font-semibold text-gray-900">${start}</span> to <span class="font-semibold text-gray-900">${end}</span> of <span class="font-semibold text-gray-900">${total}</span> entries`;

            // Flush empty dom and repaint paged rows
            this.tbody.innerHTML = '';
            const pagedRows = this.filteredRows.slice(start - 1, end);
            pagedRows.forEach(row => {
                this.tbody.appendChild(row.tr);
            });

            this.renderPagination(totalPages);
            this.btnPrev.disabled = this.state.page <= 1;
            this.btnNext.disabled = this.state.page >= totalPages;
        }

        renderPagination(totalPages) {
            if (totalPages <= 1) { this.pageNumbers.innerHTML = ''; return; }

            const pages = [];
            const cur = this.state.page;

            if (totalPages <= 7) {
                for (let i = 1; i <= totalPages; i++) pages.push(i);
            } else {
                pages.push(1);
                if (cur > 3) pages.push('…');
                for (let i = Math.max(2, cur - 1); i <= Math.min(totalPages - 1, cur + 1); i++) pages.push(i);
                if (cur < totalPages - 2) pages.push('…');
                pages.push(totalPages);
            }

            this.pageNumbers.innerHTML = pages.map(p => {
                if (p === '…') {
                    return `<span class="w-8 text-center text-gray-400 text-sm select-none tracking-widest">…</span>`;
                }
                const isActive = p === cur;
                const activeClass = isActive ? 'active' : '';
                return `<button class="page-num-btn ${activeClass}" data-page="${p}">${p}</button>`;
            }).join('');

            // Attach dynamic page navigation clicks
            this.pageNumbers.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const p = parseInt(e.currentTarget.dataset.page, 10);
                    if (!isNaN(p)) {
                        this.state.page = p;
                        this.render();
                    }
                });
            });
        }
    }

    // Initialize globally to avoid double loading on frameworks (if wire/ajax invoked)
    if (!window.MadDataTables) {
        window.MadDataTables = {};
    }
    
    // Register the class
    window.MadDataTableClass = MadDataTable;
    
    // Auto-init this specific table
    window.MadDataTables['{{ $tableId }}'] = new MadDataTable('{{ $tableId }}');
});
</script>
