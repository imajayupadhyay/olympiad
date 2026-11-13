<template>
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
    <div class="xl:col-span-7 space-y-5 min-w-0">
      <slot name="audience" />

      <section class="card" aria-labelledby="wa-h">
        <header class="card-h">
          <div class="flex items-center gap-3 min-w-0">
            <span class="step">2</span>
            <div class="min-w-0">
              <h2 id="wa-h" class="font-heading font-bold text-text-main text-sm">Choose an approved template</h2>
              <p class="text-[11px] text-text-muted">WhatsApp only allows Meta-approved templates. They are loaded live from Bird{{ options.whatsapp.sender ? ` · sender ${options.whatsapp.sender}` : '' }}.</p>
            </div>
          </div>
          <button v-if="options.whatsapp.configured" type="button" class="btn-ghost" :disabled="listLoading" @click="loadTemplates(true)">
            {{ listLoading ? 'Loading…' : '↻ Refresh from Bird' }}
          </button>
        </header>

        <div v-if="!options.whatsapp.configured" class="p-5 text-sm text-danger">
          WhatsApp isn’t configured on this server. Add <code>BIRD_API_KEY</code>, <code>BIRD_BASE_URL</code> and <code>BIRD_WHATSAPP_FROM</code> to the server <code>.env</code>, then run <code>php artisan config:clear</code>.
        </div>

        <div v-else class="p-4 sm:p-5 space-y-4">
          <p v-if="listError" class="rounded-xl bg-danger/5 border border-danger/20 text-danger text-xs p-3" role="alert">{{ listError }}</p>

          <!-- library -->
          <div v-if="!selected || browsing">
            <input v-model="search" type="search" class="field mb-3" placeholder="Search templates by name or category" aria-label="Search templates" />
            <p v-if="listLoading && !templates.length" class="text-xs text-text-muted py-6 text-center">Loading templates from Bird…</p>
            <p v-else-if="!filtered.length" class="text-xs text-text-muted py-6 text-center">No templates match.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
              <button v-for="t in filtered" :key="t.id" type="button"
                      class="text-left rounded-xl border p-3 transition-colors"
                      :class="[t.usable ? 'border-gray-200 hover:border-success bg-white' : 'border-dashed border-gray-200 bg-gray-50 opacity-70 cursor-not-allowed',
                               draft.template_id === t.id ? 'ring-2 ring-success/40 border-success' : '']"
                      :disabled="!t.usable" :title="t.unusable_reason || ''" @click="choose(t)">
                <span class="flex items-start justify-between gap-2">
                  <span class="font-semibold text-sm text-text-main break-words">{{ t.name }}</span>
                  <span class="shrink-0 text-[10px] font-bold uppercase px-1.5 py-0.5 rounded" :class="t.usable ? 'bg-success/10 text-success' : 'bg-gray-200 text-text-muted'">{{ t.usable ? 'Approved' : 'Not usable' }}</span>
                </span>
                <span class="block text-[11px] text-text-muted mt-1 font-mono truncate">{{ t.slug }}</span>
                <span class="flex flex-wrap gap-1 mt-2 text-[10px] font-semibold">
                  <span class="px-1.5 py-0.5 rounded bg-gray-100 text-text-muted capitalize">{{ t.category }}</span>
                  <span class="px-1.5 py-0.5 rounded bg-gray-100 text-text-muted uppercase">{{ t.language }}</span>
                </span>
                <span v-if="!t.usable" class="block text-[11px] text-text-muted mt-1.5">{{ t.unusable_reason }}</span>
              </button>
            </div>
            <p v-if="fetchedAt" class="text-[11px] text-text-muted mt-3">{{ templates.length }} template(s) in Bird · fetched {{ formatLocal(fetchedAt, { hour: '2-digit', minute: '2-digit' }) }}. New templates approved in Bird appear here after a refresh.</p>
          </div>

          <!-- selected template -->
          <div v-if="selected && !browsing" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-success/5 border border-success/20 px-3 py-2.5">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-text-main truncate">{{ selected.name }}</p>
                <p class="text-[11px] text-text-muted font-mono truncate">{{ selected.slug }} · {{ selected.language }} · {{ selected.category }}<span v-if="selected.version_number"> · v{{ selected.version_number }}</span></p>
              </div>
              <button type="button" class="btn-ghost" @click="browsing = true">Change template</button>
            </div>

            <p v-if="detailLoading" class="text-xs text-text-muted">Loading template details…</p>
            <p v-else-if="!selected.usable" class="rounded-xl bg-danger/5 border border-danger/20 text-danger text-xs p-3">{{ selected.unusable_reason }}</p>

            <ExamContextSelect v-model="examId" :exams="options.exams" />

            <div v-if="selected.variables?.length" class="space-y-3">
              <p class="lbl mb-0">Fill the template variables</p>
              <div v-for="v in selected.variables" :key="v.key" class="rounded-xl border border-gray-100 p-3 space-y-2.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <code class="text-xs font-semibold text-primary">{{ '{' }}{{ '{' }}{{ v.key }}}}</code>
                  <span v-if="v.example" class="text-[11px] text-text-muted truncate max-w-[60%]">Example in Bird: “{{ v.example }}”</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <label class="block">
                    <span class="sub">Fill with</span>
                    <select class="field" :value="sourceValue(v.key)" @change="setSource(v.key, $event.target.value)">
                      <option value="__text">Custom text</option>
                      <optgroup v-for="g in fieldGroups" :key="g.key" :label="g.label">
                        <option v-for="p in g.items" :key="p.key" :value="p.key" :disabled="p.requires_exam && !examId">{{ p.label }}{{ p.requires_exam && !examId ? ' (needs exam)' : '' }}</option>
                      </optgroup>
                    </select>
                  </label>
                  <label v-if="map(v.key).source === 'text'" class="block">
                    <span class="sub flex items-center justify-between">Text <PlaceholderMenu :placeholders="options.placeholders" :has-exam="!!examId" label="Field" align="right" @insert="(t) => map(v.key).value = (map(v.key).value || '') + t" /></span>
                    <input v-model="map(v.key).value" type="text" maxlength="500" class="field" :class="{ 'border-danger': varErr(v.key) }" :placeholder="v.example || 'Value'" />
                  </label>
                  <label v-else class="block">
                    <span class="sub">If a student has no value, use</span>
                    <input v-model="map(v.key).fallback" type="text" maxlength="200" class="field" placeholder="e.g. your school (optional)" />
                  </label>
                </div>
                <p v-if="varErr(v.key)" class="text-danger text-xs">{{ varErr(v.key) }}</p>
              </div>
              <p class="text-[11px] text-text-muted">Line breaks are turned into spaces — WhatsApp doesn’t allow them inside variables.</p>
            </div>
            <p v-else-if="!detailLoading" class="text-xs text-text-muted">This template has no variables. Every student gets the same text.</p>
          </div>
        </div>
      </section>
    </div>

    <aside class="xl:col-span-5 min-w-0">
      <section class="card" aria-labelledby="wa-prev-h">
        <header class="card-h">
          <div class="flex items-center gap-3 min-w-0">
            <span class="step">3</span>
            <div class="min-w-0">
              <h2 id="wa-prev-h" class="font-heading font-bold text-text-main text-sm">Preview &amp; send</h2>
              <p class="text-[11px] text-text-muted truncate">
                {{ b.preview.value?.sample ? `As ${b.preview.value.sample.name} will receive it` : 'With sample details' }}
                <span v-if="b.previewLoading.value" class="text-accent"> · updating…</span>
              </p>
            </div>
          </div>
        </header>
        <div class="p-4 sm:p-5 space-y-4">
          <!-- phone -->
          <div class="rounded-2xl p-4 wa-bg">
            <div v-if="selected" class="max-w-[320px] bg-white rounded-xl rounded-tl-none shadow-sm overflow-hidden">
              <div class="p-3 space-y-1.5">
                <p v-if="selected.header" class="font-bold text-[13px] text-[#111b21]">{{ selected.header }}</p>
                <p class="text-[13px] text-[#111b21] whitespace-pre-line break-words leading-snug">{{ waBody }}</p>
                <p v-if="selected.footer" class="text-[11px] text-[#667781]">{{ selected.footer }}</p>
                <p class="text-[10px] text-[#667781] text-right">{{ nowLabel }}</p>
              </div>
              <div v-for="btn in selected.buttons" :key="btn.text" class="border-t border-gray-100 py-2 text-center text-[13px] font-medium text-[#00a5f4]">
                {{ btn.type === 'url' ? '↗ ' : '' }}{{ btn.text }}
              </div>
            </div>
            <p v-else class="text-center text-xs text-[#54656f] py-10">Pick a template to preview it.</p>
          </div>
          <p v-if="b.preview.value?.preview?.missing?.length" class="text-xs text-danger">
            The preview student has no value for {{ b.preview.value.preview.missing.join(', ') }}. Students like this are <b>skipped</b> unless you add a fallback.
          </p>

          <SendPanel :preview="b.preview.value" :recipients="summary.recipients || 0" :sending="b.sending.value" :ready="ready"
                     not-ready-text="Pick an approved template and fill every variable."
                     channel-label="WhatsApp" :errors="b.errors.value"
                     blank-hint="add a fallback, or they’ll be skipped."
                     test-label="Send a test WhatsApp" test-type="tel" test-placeholder="+91 98765 43210"
                     :testing="b.testing.value" :test-result="b.testResult.value"
                     @test="b.sendTest" @send="b.send(onSent)" />
          <p class="text-[11px] text-text-muted">Each message is billed by WhatsApp/Bird. Students without a verified mobile number are left out automatically.</p>
        </div>
      </section>
    </aside>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, toRef, watch } from 'vue';
import { formatLocal } from '@/Services/localTime.service';
import ExamContextSelect from './ExamContextSelect.vue';
import PlaceholderMenu from './PlaceholderMenu.vue';
import SendPanel from './SendPanel.vue';
import { errorText } from './notifications';
import { useBroadcast } from './useBroadcast';

const props = defineProps({
  draft: { type: Object, required: true },
  audience: { type: Object, required: true },
  summary: { type: Object, required: true },
  options: { type: Object, required: true },
});
const examId = defineModel('examId', { type: Number, default: null });
const emit = defineEmits(['sent']);

const templates = ref([]);
const listLoading = ref(false);
const listError = ref('');
const fetchedAt = ref(null);
const search = ref('');
const selected = ref(null);
const detailLoading = ref(false);
const browsing = ref(false);
const nowLabel = formatLocal(new Date(), { hour: '2-digit', minute: '2-digit' });

const fieldGroups = computed(() => [
  { key: 'student', label: 'Student' },
  { key: 'exam', label: 'Exam' },
  { key: 'portal', label: 'Portal' },
].map((g) => ({ ...g, items: props.options.placeholders.filter((p) => p.group === g.key) })));

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  return templates.value.filter((t) => !q || `${t.name} ${t.slug} ${t.category}`.toLowerCase().includes(q));
});

const loadTemplates = async (refresh = false) => {
  listLoading.value = true;
  listError.value = '';
  try {
    const { data } = await window.axios.get(route('admin.notifications.whatsapp.templates'), { params: refresh ? { refresh: 1 } : {} });
    templates.value = data.templates ?? [];
    fetchedAt.value = data.fetched_at ?? null;
    if (data.message) listError.value = data.message;
    if (props.draft.template_id && !selected.value) {
      const t = templates.value.find((x) => x.id === props.draft.template_id);
      if (t) loadDetail(t.id, false);
    }
  } catch (e) {
    listError.value = errorText(e, 'Could not load templates from Bird.');
  } finally {
    listLoading.value = false;
  }
};

const guess = (v) => {
  const k = `${v.key}`.toLowerCase();
  if (k.includes('name')) return { source: 'placeholder', value: 'student_name' };
  if (k.includes('date')) return { source: 'placeholder', value: 'exam_date' };
  return { source: 'text', value: '' };
};

const loadDetail = async (id, resetMapping = true) => {
  detailLoading.value = true;
  try {
    const { data } = await window.axios.get(route('admin.notifications.whatsapp.template', id));
    selected.value = data.template;
    const current = resetMapping ? {} : { ...props.draft.variables };
    props.draft.variables = Object.fromEntries((data.template.variables ?? []).map((v) => [v.key, current[v.key] ?? { ...guess(v), fallback: '' }]));
  } catch (e) {
    listError.value = errorText(e, 'Could not load the template from Bird.');
    selected.value = null;
  } finally {
    detailLoading.value = false;
  }
};

const choose = (t) => {
  if (!t.usable) return;
  browsing.value = false;
  if (props.draft.template_id === t.id && selected.value) return;
  props.draft.template_id = t.id;
  selected.value = { ...t, variables: [] };
  loadDetail(t.id);
};

const map = (key) => props.draft.variables[key] ?? (props.draft.variables[key] = { source: 'text', value: '', fallback: '' });
const sourceValue = (key) => (map(key).source === 'placeholder' ? map(key).value : '__text');
const setSource = (key, value) => {
  props.draft.variables[key] = value === '__text'
    ? { source: 'text', value: '', fallback: map(key).fallback || '' }
    : { source: 'placeholder', value, fallback: map(key).fallback || '' };
};

const ready = computed(() => !!selected.value?.usable && !detailLoading.value
  && (selected.value.variables ?? []).every((v) => !v.required || (map(v.key).value || '').trim() !== ''));

const b = useBroadcast({
  channel: 'whatsapp',
  content: () => (ready.value ? { template_id: props.draft.template_id, variables: props.draft.variables } : null),
  audience: toRef(props, 'audience'),
  examId,
  summary: toRef(props, 'summary'),
});

const waBody = computed(() => {
  if (b.preview.value?.preview?.body) return b.preview.value.preview.body;
  const body = selected.value?.body ?? '';
  return body.replace(/{{\s*([a-zA-Z0-9_]+)\s*}}/g, (m, k) => {
    const v = props.draft.variables[k];
    if (!v) return m;
    return v.source === 'placeholder' ? `[${props.options.placeholders.find((p) => p.key === v.value)?.label ?? v.value}]` : (v.value || m);
  });
});

const varErr = (key) => b.errors.value?.[`whatsapp.variables.${key}.value`];
const onSent = () => emit('sent');

// a reset after sending clears the draft
watch(() => props.draft.template_id, (id) => { if (!id) { selected.value = null; browsing.value = false; } });
onMounted(() => { if (props.options.whatsapp.configured) loadTemplates(); });
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm; }
.card-h { @apply flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100; }
.step { @apply w-7 h-7 shrink-0 rounded-full bg-primary text-white font-number font-bold text-sm flex items-center justify-center; }
.lbl { @apply block text-[11px] font-semibold text-text-muted uppercase tracking-wider mb-1.5; }
.sub { @apply block text-[11px] font-semibold text-text-muted mb-1; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main; }
.btn-ghost { @apply px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-text-main bg-white hover:border-primary hover:text-primary transition-colors disabled:opacity-40 disabled:pointer-events-none; }
.wa-bg { background-color: #efeae2; background-image: radial-gradient(rgba(0,0,0,.035) 1px, transparent 1px); background-size: 14px 14px; }
</style>
