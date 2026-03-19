{{--
    Secondary section header — use for sub-section titles WITHIN a page.

    For the main page title and primary CTA, push into the top header bar instead:

      @push('page-title')
          <h1 class="text-sm font-semibold text-gray-800 truncate">Page Title</h1>
      @endpush

      @push('page-actions')
          <x-primary-button>New Item</x-primary-button>
      @endpush

    Props:
      title       — required, section heading
      description — optional, subtitle text
      $slot       — optional, action buttons rendered on the right
--}}
@props(['title', 'description' => null])

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div>
        <h1 class="text-xl font-black text-gray-900 leading-tight">{{ $title }}</h1>
        @if ($description)
            <p class="text-sm text-gray-400 mt-0.5">{{ $description }}</p>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="flex items-center gap-2 shrink-0">
            {{ $slot }}
        </div>
    @endif
</div>
