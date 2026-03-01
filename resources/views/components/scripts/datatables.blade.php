@props(['tableId', 'columnDefs' => '[]', 'order' => '[]'])

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
        .dataTables_wrapper .dataTables_length select {
                @apply mx-2 px-3 py-1 bg-white border border-gray-200 rounded-lg text-sm transition-all focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white outline-none cursor-pointer;
                width: 4rem;
        }

        .dataTables_wrapper .dataTables_filter input {
                @apply ml-2 px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm transition-all focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white outline-none;
                width: 200px;
        }

        .dataTables_wrapper .dataTables_info {
                @apply text-xs text-gray-400 font-medium py-4;
        }

        .dataTables_wrapper .dataTables_paginate {
                @apply flex items-center gap-1 py-4;
        }

        .dataTables_wrapper .paginate_button {
                @apply px-3 py-1 text-xs font-semibold rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-primary transition-all cursor-pointer !important;
        }

        .dataTables_wrapper .paginate_button.current {
                @apply !bg-primary !text-white !border-primary shadow-sm !important;
        }

        .dataTables_wrapper tbody tr:hover {
                background-color: #f8fafc !important;
        }

        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter {
                @apply text-sm font-medium text-gray-500;
        }

        table.dataTable.no-footer {
                border-bottom: none !important;
        }
        table.dataTable thead th, table.dataTable thead td {
                border-bottom: none !important;
        }
</style>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
        window.addEventListener('load', function() {
                const table = document.getElementById(@js($tableId));
                if (table) {
                        $(table).DataTable({
                                dom: '<"flex flex-col sm:flex-row justify-between items-center mb-4"<"search"f><"length"l>><"w-full overflow-x-auto pb-2"t><"flex flex-col sm:flex-row justify-between items-center mt-4 border-t border-gray-100 pt-4"ip>',
                                columnDefs: @json($columnDefs),
                                order: @json($order),
                                stateSave: true,
                                pageLength: 25,
                        });
                }
        });
</script>
