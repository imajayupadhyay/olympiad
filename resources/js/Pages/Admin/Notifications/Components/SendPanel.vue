<template>
  <div class="space-y-3">
    <!-- warnings from the server preview -->
    <div v-if="preview?.problems?.length" class="rounded-xl border border-danger/20 bg-danger/5 p-3 text-xs text-danger space-y-1" role="alert">
      <p v-for="p in preview.problems" :key="p">⚠ {{ p }}</p>
    </div>
    <div v-if="preview?.blank_counts?.length" class="rounded-xl border border-gold/30 bg-gold/10 p-3 text-xs text-gold-dark space-y-1">
      <p v-for="b in preview.blank_counts" :key="b.key">
        <b class="font-number">{{ b.count.toLocaleString('en-IN') }}</b> recipient(s) have no <b>{{ b.label }}</b> — {{ blankHint }}
      </p>
    </div>

    <!-- test -->
    <div v-if="testLabel" class="rounded-xl border border-gray-100 bg-gray-50/70 p-3">
      <p class="lbl">{{ testLabel }}</p>
      <form class="flex gap-2" @submit.prevent="$emit('test', to)">
        <input v-model="to" :type="testType" class="field" :placeholder="testPlaceholder" :aria-label="testLabel" required />
        <button type="submit" class="btn-ghost shrink-0" :disabled="testing || !canTest">{{ testing ? 'Sending…' : 'Send test' }}</button>
      </form>
      <p v-if="testResult" class="text-[11px] mt-1.5" :class="testResult.ok ? 'text-success' : 'text-danger'" role="status">{{ testResult.message }}</p>
      <p v-else class="text-[11px] text-text-muted mt-1.5">Uses {{ preview?.sample ? preview.sample.name + '’s' : 'sample' }} details. Not counted in history.</p>
    </div>

    <!-- server validation errors from Send -->
    <div v-if="errorList.length" class="rounded-xl border border-danger/20 bg-danger/5 p-3 text-xs text-danger space-y-1" role="alert">
      <p v-for="e in errorList" :key="e">{{ e }}</p>
    </div>

    <button type="button" class="send" :disabled="disabled" @click="$emit('send')">
      <svg v-if="!sending" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
      {{ sending ? 'Sending…' : buttonText }}
    </button>
    <p v-if="disabledReason" class="text-[11px] text-text-muted text-center">{{ disabledReason }}</p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  preview: { type: Object, default: null },
  recipients: { type: Number, default: 0 },
  sending: { type: Boolean, default: false },
  ready: { type: Boolean, default: false },
  notReadyText: { type: String, default: 'Finish the message to send it.' },
  channelLabel: { type: String, required: true },
  errors: { type: Object, default: () => ({}) },
  blankHint: { type: String, default: 'that field will be empty for them.' },
  testLabel: { type: String, default: '' },
  testType: { type: String, default: 'text' },
  testPlaceholder: { type: String, default: '' },
  testDefault: { type: String, default: '' },
  testing: { type: Boolean, default: false },
  testResult: { type: Object, default: null },
});
defineEmits(['send', 'test']);

const to = ref(props.testDefault);
const canTest = computed(() => props.ready && !(props.preview?.problems?.length));
const hasProblems = computed(() => !!props.preview?.problems?.length);
const disabled = computed(() => props.sending || !props.ready || !props.recipients || hasProblems.value);
const disabledReason = computed(() => {
  if (!props.ready) return props.notReadyText;
  if (!props.recipients) return 'No recipients match. Adjust the audience above.';
  if (hasProblems.value) return 'Fix the problems above to send.';
  return '';
});
const buttonText = computed(() => `Send ${props.channelLabel} to ${props.recipients.toLocaleString('en-IN')} student${props.recipients === 1 ? '' : 's'}`);
const errorList = computed(() => Object.entries(props.errors || {})
  .filter(([k]) => k !== 'duplicate')
  .map(([, v]) => (Array.isArray(v) ? v[0] : v)));
</script>

<style scoped>
.lbl { @apply block text-[11px] font-semibold text-text-muted uppercase tracking-wider mb-1.5; }
.field { @apply w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main; }
.btn-ghost { @apply px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-text-main bg-white hover:border-primary hover:text-primary transition-colors disabled:opacity-40 disabled:pointer-events-none; }
.send { @apply w-full flex items-center justify-center gap-2 bg-accent text-white py-3 rounded-xl text-sm font-semibold hover:bg-accent-dark transition-colors shadow-sm disabled:opacity-50 disabled:pointer-events-none; }
</style>
