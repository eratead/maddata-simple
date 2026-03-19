{{--
    Global confirmation dialog — included once in app.blade.php.

    Trigger from any element with Alpine:
      @click="$dispatch('confirm-action', {
          title:        'Delete Client',
          message:      'This cannot be undone.',
          confirmLabel: 'Delete',
          form:         $el.closest('form')
      })"

    The form is submitted on confirm. Works with any hidden DELETE/PATCH form.
--}}
<div x-data="{
        open: false,
        title: '',
        message: '',
        confirmLabel: 'Delete',
        formEl: null,
        show(detail) {
            this.title        = detail.title        ?? 'Are you sure?';
            this.message      = detail.message      ?? '';
            this.confirmLabel = detail.confirmLabel ?? 'Delete';
            this.formEl       = detail.form         ?? null;
            this.open         = true;
        },
        confirm() {
            if (this.formEl) this.formEl.submit();
            this.open = false;
        }
     }"
     @confirm-action.window="show($event.detail)"
     x-cloak>

    {{-- Backdrop --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false"
         class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
         style="display:none">

        {{-- Dialog panel --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-1"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-1"
             @click.stop
             class="bg-white rounded-xl shadow-2xl border border-gray-200 w-full max-w-sm p-6"
             style="display:none">

            {{-- Warning icon --}}
            <div class="w-11 h-11 rounded-full bg-red-50 border border-red-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                </svg>
            </div>

            <h3 class="text-base font-black text-gray-900 text-center leading-tight" x-text="title"></h3>
            <p class="text-sm text-gray-400 text-center mt-1 mb-6" x-text="message"></p>

            <div class="flex gap-3">
                <button @click="open = false"
                        class="flex-1 px-4 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-colors cursor-pointer focus:outline-none">
                    Cancel
                </button>
                <button @click="confirm()"
                        class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors cursor-pointer focus:outline-none">
                    <span x-text="confirmLabel"></span>
                </button>
            </div>

        </div>
    </div>

</div>
