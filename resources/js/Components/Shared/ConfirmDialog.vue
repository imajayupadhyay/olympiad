<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div v-if="state.open" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-primary-dark/55 backdrop-blur-[3px]"
           @click.self="settleConfirm(false)">
        <div role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-title" :aria-describedby="state.message ? 'confirm-dialog-message' : undefined"
             class="confirm-card w-full max-w-md rounded-2xl bg-white shadow-[0_40px_80px_-28px_rgba(10,16,36,.55)] border border-[#E7D9BE] p-6"
             @keydown.esc.prevent="settleConfirm(false)"
             @keydown.tab="trapFocus">
          <div class="flex items-start gap-4">
            <span class="w-11 h-11 shrink-0 rounded-xl grid place-items-center" :class="tone.icon">
              <svg v-if="state.tone === 'danger'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              <svg v-else-if="state.tone === 'warning'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
              <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <div class="min-w-0 pt-0.5">
              <h2 id="confirm-dialog-title" class="font-heading font-bold text-lg text-text-main leading-snug">{{ state.title }}</h2>
              <p v-if="state.message" id="confirm-dialog-message" class="mt-1.5 text-sm text-text-muted leading-relaxed">{{ state.message }}</p>
            </div>
          </div>

          <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <button ref="cancelButton" type="button" @click="settleConfirm(false)"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold text-text-main bg-gray-100 hover:bg-gray-200 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
              {{ state.cancelText }}
            </button>
            <button ref="confirmButton" type="button" @click="settleConfirm(true)"
                    class="px-5 py-2.5 rounded-xl text-sm font-bold text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                    :class="tone.button">
              {{ state.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { confirmState as state, settleConfirm } from '@/composables/useConfirm';

const cancelButton = ref(null);
const confirmButton = ref(null);
let returnFocusTo = null;

const tone = computed(() => ({
  danger: { icon: 'bg-danger/10 text-danger', button: 'bg-danger hover:bg-red-700 focus-visible:ring-danger/40' },
  warning: { icon: 'bg-gold/15 text-gold-dark', button: 'bg-accent hover:bg-accent-dark focus-visible:ring-accent/40' },
  primary: { icon: 'bg-primary/10 text-primary', button: 'bg-primary hover:bg-primary-light focus-visible:ring-primary/40' },
}[state.tone] ?? { icon: 'bg-primary/10 text-primary', button: 'bg-primary hover:bg-primary-light' }));

watch(() => state.open, async (open) => {
  if (open) {
    returnFocusTo = document.activeElement;
    document.body.style.overflow = 'hidden';
    await nextTick();
    // Destructive and leave-page prompts default to the safe choice.
    (state.tone === 'primary' ? confirmButton : cancelButton).value?.focus();
  } else {
    document.body.style.overflow = '';
    returnFocusTo?.focus?.();
    returnFocusTo = null;
  }
});

// A page change must never leave a stale dialog (and a pending promise) behind.
router.on('navigate', () => {
  if (state.open) settleConfirm(false);
});

function trapFocus(event) {
  const first = cancelButton.value;
  const last = confirmButton.value;
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last?.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first?.focus();
  }
}
</script>

<style scoped>
.confirm-card { animation: confirm-pop .2s ease-out; }
@keyframes confirm-pop { from { transform: translateY(8px) scale(.98); } to { transform: none; } }
@media (prefers-reduced-motion: reduce) { .confirm-card { animation: none; } }
</style>
