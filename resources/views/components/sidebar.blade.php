<aside class="fixed inset-y-0 left-0 w-56 bg-white shadow z-40 flex flex-col pt-20">
    <nav class="flex flex-col gap-2 px-4 text-gray-700">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100">
            <span>📊</span>
            <span class="font-medium">Dashboard</span>
        </a>
        <a href="{{ route('clients.index') }}" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100">
            <span>🧾</span>
            <span class="font-medium">Clients</span>
        </a>
    </nav>
</aside>
