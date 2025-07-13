@props(['tableId', 'columnDefs' => '[]', 'order' => '[]'])

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
        .dataTables_wrapper .dataTables_length select {
                width: 3.5rem;
        }

        .dataTables_wrapper tbody tr:hover {
                background-color: #f3f4f6;
                /* Tailwind's gray-100 */
        }
</style>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
        console.info(@json($order));
        window.addEventListener('load', function() {
                const table = document.getElementById(@js($tableId));
                if (table) {
                        $(table).DataTable({
                                dom: '<"flex justify-between items-center mb-4"<"search"f><"length"l>>tip',
                                columnDefs: @json($columnDefs),
                                order: @json($order),
                                stateSave: true,
                        });
                }
        });
</script>
