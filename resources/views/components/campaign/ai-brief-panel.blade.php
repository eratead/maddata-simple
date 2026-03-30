{{-- AI Campaign Assistant Slide-in Panel --}}
{{-- Triggered by: @click="isOpen = true" from parent x-data="campaignAssistant()" --}}

{{-- Backdrop --}}
<div x-show="isOpen" x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="isOpen = false"
    class="fixed inset-0 bg-black/20"
    style="z-index:9000"></div>

<div x-show="isOpen" x-cloak
    x-transition:enter="transition ease-out duration-250 transform"
    x-transition:enter-start="translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-200 transform"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="translate-x-full"
    class="fixed top-0 right-0 h-full w-full max-w-[440px] bg-white shadow-2xl flex flex-col border-l border-gray-200"
    style="z-index:9001"
    @keydown.window.escape="isOpen = false">

    {{-- Panel Header --}}
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-violet-50 to-white flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-violet-600" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900">AI Campaign Assistant</p>
                <p class="text-xs text-gray-400">Paste a brief or give instructions</p>
            </div>
        </div>
        <button type="button" @click="isOpen = false"
            class="w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Messages Area --}}
    <div class="flex-1 overflow-y-auto px-4 py-5 space-y-3" x-ref="messagesArea">

        {{-- Empty State --}}
        <div x-show="messages.length === 0"
            class="flex flex-col items-center justify-center h-full text-center py-10">
            <div class="w-14 h-14 rounded-2xl bg-violet-100 flex items-center justify-center mb-4">
                <svg class="w-7 h-7 text-violet-500" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-gray-700 mb-1">Ready to assist</p>
            <p class="text-xs text-gray-400 max-w-[240px] leading-relaxed">
                Paste an email brief or describe changes and I'll update the form fields automatically.
            </p>
            <div class="mt-5 grid grid-cols-1 gap-2 w-full max-w-[280px]">
                <button type="button"
                    @click="inputText = 'Campaign for women 25-45 in Tel Aviv, budget 50,000 NIS, mobile only, March 2025'; $nextTick(() => $refs.chatInput.focus())"
                    class="text-left text-xs px-3 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-gray-600 hover:bg-violet-50 hover:border-violet-200 hover:text-violet-700 transition-colors">
                    💡 "Women 25-45, Tel Aviv, 50K budget..."
                </button>
                <button type="button"
                    @click="inputText = 'Change budget to 30,000 and remove Jerusalem region'; $nextTick(() => $refs.chatInput.focus())"
                    class="text-left text-xs px-3 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-gray-600 hover:bg-violet-50 hover:border-violet-200 hover:text-violet-700 transition-colors">
                    ✏️ "Change budget to 30,000..."
                </button>
            </div>
        </div>

        {{-- Chat Messages --}}
        <template x-for="(msg, i) in messages" :key="i">
            <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex items-start gap-2'">
                <div x-show="msg.role === 'ai'"
                    class="w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-3.5 h-3.5 text-violet-600" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                    </svg>
                </div>
                <div :class="msg.role === 'user'
                    ? 'bg-[#F97316] text-white rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[85%] text-sm leading-relaxed'
                    : 'bg-gray-100 text-gray-800 rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[85%] text-sm leading-relaxed'"
                    x-text="msg.content">
                </div>
            </div>
        </template>

        {{-- Typing Indicator --}}
        <div x-show="isTyping" class="flex items-start gap-2">
            <div class="w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-violet-600" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                </svg>
            </div>
            <div class="bg-gray-100 rounded-2xl rounded-tl-sm px-4 py-3">
                <div class="flex gap-1 items-center">
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Input Area --}}
    <div class="border-t border-gray-100 px-4 py-3 bg-white flex-shrink-0">
        <div class="flex gap-2 items-end">
            <textarea x-model="inputText" x-ref="chatInput"
                @keydown.enter.prevent="!$event.shiftKey ? sendMessage() : inputText += '\n'"
                :disabled="isTyping"
                rows="2"
                placeholder="Paste a brief or describe changes... (Enter to send)"
                class="flex-1 text-sm px-3 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-violet-300 focus:ring-1 focus:ring-violet-200 focus:bg-white resize-none transition-all disabled:opacity-50"></textarea>
            <button type="button" @click="sendMessage()"
                :disabled="isTyping || inputText.trim() === ''"
                class="flex-shrink-0 w-9 h-9 rounded-xl bg-violet-600 text-white flex items-center justify-center hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all hover:-translate-y-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </button>
        </div>
        <p class="text-[10px] text-gray-400 mt-1.5">Shift+Enter for new line · Enter to send</p>
    </div>
</div>
