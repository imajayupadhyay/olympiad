<template>
  <Teleport to="body">
    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0"
                leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0">
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-primary-dark/55 backdrop-blur-[3px]" @click.self="close">
        <form role="dialog" aria-modal="true" aria-labelledby="exam-import-title"
              class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-[#E7D9BE] p-6"
              @submit.prevent="upload" @keydown.esc="close">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-[11px] font-bold uppercase tracking-wider text-accent">Import from Excel</p>
              <h2 id="exam-import-title" class="font-heading font-bold text-lg text-text-main">Build the paper from a workbook</h2>
            </div>
            <button type="button" class="p-1.5 rounded-lg text-text-muted hover:bg-gray-100" aria-label="Close" @click="close">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <ol class="mt-5 space-y-4 text-sm">
            <li class="flex gap-3">
              <span class="step">1</span>
              <div class="flex-1">
                <p class="font-semibold text-text-main">Download this exam's template</p>
                <p class="text-xs text-text-muted mt-0.5">One row per question, in paper order. The Section column groups rows into sections; Question Bank ID reuses an existing question.</p>
                <a :href="route('admin.exams.import.template', examId)" class="inline-flex mt-2 px-3 py-2 rounded-lg border border-gray-200 text-xs font-bold text-primary hover:border-primary">
                  ↓ Download template (.xlsx)
                </a>
              </div>
            </li>
            <li class="flex gap-3">
              <span class="step">2</span>
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-text-main">Upload the filled workbook</p>
                <label class="mt-2 flex items-center gap-3 rounded-xl border-2 border-dashed px-3 py-3 cursor-pointer transition-colors"
                       :class="form.errors.file ? 'border-danger/50 bg-danger/5' : 'border-gray-200 hover:border-primary'">
                  <input type="file" accept=".xlsx" class="sr-only" @change="pick" />
                  <span class="px-3 py-1.5 rounded-lg bg-gray-100 text-xs font-bold text-text-main shrink-0">Choose file</span>
                  <span class="text-xs truncate" :class="form.file ? 'text-text-main' : 'text-text-muted'">{{ form.file?.name || 'No file chosen' }}</span>
                </label>
                <p v-if="form.errors.file" class="text-danger text-xs mt-1.5">{{ form.errors.file }}</p>
              </div>
            </li>
            <li class="flex gap-3">
              <span class="step">3</span>
              <p class="flex-1 text-text-muted text-xs pt-1">Review and correct every row, choose whether to add to or replace the current paper, then confirm. Nothing is saved before that.</p>
            </li>
          </ol>

          <p v-if="dirty" class="mt-5 rounded-xl border border-gold/30 bg-gold/10 px-3 py-2.5 text-xs font-semibold text-gold-dark">
            Save your changes to this exam before importing — the review page works from the saved paper.
          </p>

          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-text-main bg-gray-100 hover:bg-gray-200" @click="close">Cancel</button>
            <button type="submit" :disabled="!form.file || form.processing || dirty"
                    class="px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-light disabled:opacity-50 disabled:cursor-not-allowed">
              {{ form.processing ? 'Reading workbook…' : 'Upload & review' }}
            </button>
          </div>
        </form>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  open: Boolean,
  examId: { type: Number, required: true },
  dirty: Boolean,
});

const emit = defineEmits(['close']);

const form = useForm({ file: null });

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    form.reset();
    form.clearErrors();
  }
});

const pick = (event) => {
  form.file = event.target.files?.[0] ?? null;
  form.clearErrors('file');
};

const upload = () => {
  if (!form.file || props.dirty) return;
  form.post(route('admin.exams.import.upload', props.examId), { forceFormData: true, preserveScroll: true });
};

const close = () => {
  if (!form.processing) emit('close');
};
</script>

<style scoped>
.step { @apply w-6 h-6 shrink-0 rounded-lg bg-primary/10 text-primary font-number text-xs font-bold grid place-items-center; }
</style>
