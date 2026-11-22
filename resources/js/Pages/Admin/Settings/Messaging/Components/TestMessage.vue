<template>
  <section class="card" aria-labelledby="test-h">
    <header class="card-h">
      <div class="min-w-0">
        <h2 id="test-h" class="font-heading font-bold text-text-main text-sm">Send a test message</h2>
        <p class="text-[11px] text-text-muted">One real WhatsApp through {{ provider.label }}, even if it isn’t the active provider. It is billed and isn’t recorded as a broadcast.</p>
      </div>
    </header>

    <div v-if="!provider.can_send" class="p-4 sm:p-5 text-xs text-gold-dark leading-relaxed">{{ provider.unavailable_reason }}</div>
    <p v-else-if="!canWrite" class="p-4 sm:p-5 text-xs text-text-muted">You need write access to Messaging to send a test.</p>

    <form v-else class="p-4 sm:p-5 space-y-3" @submit.prevent="send">
      <label class="block">
        <span class="lbl">Template</span>
        <select v-model="templateId" class="field" :disabled="loading" @change="pick">
          <option :value="null" disabled>{{ loading ? 'Loading templates…' : 'Choose an approved template' }}</option>
          <option v-for="t in usable" :key="t.id" :value="t.id">{{ t.name }} · {{ t.language }}</option>
        </select>
        <span v-if="!loading && !usable.length" class="block text-[11px] text-text-muted mt-1">No usable templates for {{ provider.label }} yet.</span>
      </label>

      <div v-if="detail?.variables?.length" class="space-y-2">
        <p class="lbl mb-0">Variable values</p>
        <label v-for="v in detail.variables" :key="v.key" class="flex items-center gap-2">
          <code class="text-xs font-semibold text-primary shrink-0 min-w-[3.5rem]">{{ '{' }}{{ '{' }}{{ v.key }}}}</code>
          <input v-model="values[v.key]" type="text" maxlength="500" class="field" :placeholder="v.example || 'Value'" />
        </label>
        <p v-for="(msg, key) in variableErrors" :key="key" class="text-xs text-danger">{{ msg }}</p>
      </div>

      <label class="block">
        <span class="lbl">Send to</span>
        <input v-model="to" type="tel" class="field" placeholder="+91 98765 43210" autocomplete="off" />
      </label>

      <button type="submit" class="btn-primary w-full" :disabled="!templateId || !to.trim() || sending">{{ sending ? 'Sending…' : `Send test via ${provider.label}` }}</button>
      <p v-if="result" class="rounded-xl border p-3 text-xs" role="status" :class="result.ok ? 'border-success/20 bg-success/5 text-success' : 'border-danger/20 bg-danger/5 text-danger'">{{ result.message }}</p>
    </form>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { confirmDialog } from '@/composables/useConfirm';
import { errorText } from '@/Pages/Admin/Notifications/Components/notifications';

const props = defineProps({
  provider: { type: Object, required: true },
  canWrite: { type: Boolean, default: false },
});

const templates = ref([]);
const loading = ref(false);
const templateId = ref(null);
const detail = ref(null);
const values = reactive({});
const to = ref('');
const sending = ref(false);
const result = ref(null);
const variableErrors = ref({});

const usable = computed(() => templates.value.filter((t) => t.usable));

const load = async () => {
  loading.value = true;
  try {
    const { data } = await window.axios.get(route('admin.settings.messaging.providers.templates', props.provider.key));
    templates.value = data.templates ?? [];
  } catch {
    templates.value = [];
  } finally {
    loading.value = false;
  }
};

const pick = async () => {
  detail.value = null;
  result.value = null;
  variableErrors.value = {};
  try {
    const { data } = await window.axios.get(route('admin.settings.messaging.providers.template', [props.provider.key, templateId.value]));
    detail.value = data.template;
    for (const v of data.template.variables ?? []) values[v.key] = values[v.key] ?? v.example ?? '';
  } catch (e) {
    result.value = { ok: false, message: errorText(e, 'Could not load the template.') };
  }
};

const send = async () => {
  const ok = await confirmDialog({
    title: 'Send a real WhatsApp message?',
    message: `${props.provider.label} will deliver “${detail.value?.name ?? 'this template'}” to ${to.value.trim()}. The message is billed.`,
    confirmText: 'Send test',
  });
  if (!ok) return;

  sending.value = true;
  result.value = null;
  variableErrors.value = {};
  try {
    const { data } = await window.axios.post(route('admin.settings.messaging.providers.test', props.provider.key), {
      template_id: templateId.value,
      to: to.value.trim(),
      variables: Object.fromEntries((detail.value?.variables ?? []).map((v) => [v.key, values[v.key] ?? ''])),
    });
    result.value = { ok: true, message: data.message };
  } catch (e) {
    variableErrors.value = Object.fromEntries(Object.entries(e?.response?.data?.errors ?? {}).filter(([k]) => k.startsWith('variables.')).map(([k, v]) => [k, v[0]]));
    result.value = { ok: false, message: errorText(e, 'The test message could not be sent.') };
  } finally {
    sending.value = false;
  }
};

onMounted(() => { if (props.provider.can_send && props.canWrite) load(); });
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm; }
.card-h { @apply flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100; }
.lbl { @apply block text-[11px] font-semibold text-text-muted uppercase tracking-wider mb-1.5; }
.field { @apply w-full min-w-0 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main; }
.btn-primary { @apply px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-light transition-colors disabled:opacity-50; }
</style>
