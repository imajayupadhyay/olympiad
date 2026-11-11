<template>
  <AdminLayout title="Hall Ticket Layout" subtitle="Choose what every hall ticket shows: student details, schedule columns, instructions and layout">
    <div v-if="flash.success" class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">{{ flash.error }}</div>
    <div v-if="saveError" class="mb-4 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">{{ saveError }}</div>

    <!-- action bar -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 mb-5 flex flex-wrap items-center gap-3">
      <div class="min-w-0 flex-1">
        <p v-if="dirty" class="flex items-center gap-2 text-sm font-semibold text-amber-700">
          <span class="w-2 h-2 rounded-full bg-amber-500"></span> Unsaved changes. The preview shows them; students don't see them until you save.
        </p>
        <p v-else class="text-sm text-text-muted">
          <span class="inline-block w-2 h-2 rounded-full bg-success mr-1.5"></span>
          Live on every hall ticket<template v-if="savedAt"> · saved {{ savedAt }}<template v-if="savedBy"> by {{ savedBy }}</template></template>
        </p>
        <p v-if="!canWrite" class="text-xs text-text-muted">View only. Ask a super admin for "Hall Ticket Layout" write access.</p>
      </div>
      <template v-if="canWrite">
        <button type="button" class="btn-ghost" :disabled="saving" @click="resetDefaults">Reset to default</button>
        <button type="button" class="btn-ghost" :disabled="!dirty || saving" @click="discard">Discard</button>
        <button type="button" class="btn-primary" :disabled="!dirty || saving" @click="save">{{ saving ? 'Saving…' : 'Save layout' }}</button>
      </template>
    </div>

    <!-- On wide screens both panes scroll on their own so the preview never leaves the screen
         (AdminLayout's <main> has overflow-auto, which disables position: sticky). -->
    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] gap-5 items-start xl:items-stretch xl:h-[calc(100vh-215px)] xl:min-h-[560px]">
      <!-- editor -->
      <div class="min-w-0 bg-white rounded-2xl border border-gray-100 shadow-sm xl:overflow-y-auto xl:min-h-0">
        <div class="flex gap-1 overflow-x-auto p-2 border-b border-gray-100 xl:sticky xl:top-0 xl:z-10 bg-white rounded-t-2xl" role="tablist" aria-label="Settings sections">
          <button v-for="t in tabs" :key="t.key" type="button" role="tab" :aria-selected="tab === t.key" @click="tab = t.key"
                  class="shrink-0 px-3 py-2 rounded-xl text-sm font-semibold transition-colors"
                  :class="tab === t.key ? 'bg-primary text-white' : 'text-text-main hover:bg-gray-100'">
            {{ t.label }} <span v-if="t.count !== undefined" class="font-number text-xs opacity-70 ml-0.5">{{ t.count }}</span>
          </button>
        </div>

        <div class="p-4 sm:p-5">
          <!-- ticket fields -->
          <section v-show="tab === 'fields'">
            <h3 class="sec-h">Student details on the ticket</h3>
            <p class="sec-p">Switch a field on to print it. Drag to set the order, rename the printed label, and make long values use a full row. The grid re-flows automatically.</p>
            <ItemListEditor v-model="draft.fields" :catalogue="fieldCatalogue" with-wide :disabled="!canWrite" noun="field" />

            <h3 class="sec-h mt-7">Text rows</h3>
            <p class="sec-p">Fixed text printed on every ticket, e.g. "Reporting centre: Online". New rows are switched on and added to the end of the list above.</p>
            <TextRowsEditor v-model="draft.text_rows" :max="catalogue.limits.text_rows" :disabled="!canWrite" @added="onTextAdded" @removed="onTextRemoved" />
          </section>

          <!-- schedule -->
          <section v-show="tab === 'schedule'">
            <h3 class="sec-h">Examination schedule columns</h3>
            <p class="sec-p">One row per enrolled olympiad. Choose the columns, their order and headings. Mode, centre and the log-in buffer are set under Layout.</p>
            <ItemListEditor v-model="draft.exam_columns" :catalogue="columnCatalogue" :disabled="!canWrite" noun="column" />
          </section>

          <!-- instructions -->
          <section v-show="tab === 'instructions'">
            <h3 class="sec-h">Examination instructions</h3>
            <p class="sec-p">Printed as a numbered list. Drag or use the arrows to reorder.</p>
            <InstructionsEditor v-model="draft.instructions" :defaults="defaultInstructions" :max="catalogue.limits.instructions"
                                :max-length="catalogue.limits.instruction_length" :disabled="!canWrite" />
          </section>

          <!-- custom student fields -->
          <section v-show="tab === 'students'">
            <h3 class="sec-h">Custom student fields</h3>
            <p class="sec-p">Collect extra information from students (Father's name, School ID…) without a developer. These save immediately.</p>
            <CustomFieldsManager :fields="customFields" :types="fieldTypes" :can-write="canWrite" :can-delete="canDelete" />
          </section>

          <!-- layout -->
          <section v-show="tab === 'layout'">
            <h3 class="sec-h">Layout &amp; text</h3>
            <p class="sec-p">Header, photo position, columns, sections and signatures. Everything prints on A4.</p>
            <LayoutEditor v-model="draft.layout" :disabled="!canWrite" />
          </section>
        </div>
      </div>

      <!-- live preview -->
      <div class="min-w-0 xl:min-h-0 xl:flex xl:flex-col">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 sm:p-4 xl:flex xl:flex-col xl:min-h-0 xl:flex-1">
          <div class="flex flex-wrap items-center gap-2 mb-3">
            <h3 class="font-heading font-bold text-text-main mr-auto flex items-center gap-2">
              Live preview
              <span v-if="previewLoading" class="w-3.5 h-3.5 rounded-full border-2 border-gray-200 border-t-accent animate-spin" aria-label="Updating"></span>
            </h3>
            <label class="text-xs text-text-muted" for="preview-with">Preview with</label>
            <select id="preview-with" v-model="previewWith" class="field !w-auto !py-1.5 !text-xs max-w-[220px]">
              <option value="sample:approved">Sample student · approved</option>
              <option value="sample:pending">Sample student · awaiting approval</option>
              <optgroup v-if="previewTickets.length" label="Real hall tickets">
                <option v-for="t in previewTickets" :key="t.id" :value="String(t.id)">{{ t.label }}</option>
              </optgroup>
            </select>
          </div>
          <p v-if="previewError" class="mb-3 text-xs text-danger bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ previewError }}</p>
          <div class="rounded-xl bg-bg p-2 sm:p-3 xl:flex-1 xl:min-h-0 xl:overflow-y-auto" :class="{ 'opacity-60': previewLoading && preview }">
            <ScaledHallTicket v-if="preview" :ticket="preview" />
            <div v-else class="py-24 text-center text-sm text-text-muted">Rendering preview…</div>
          </div>
          <p class="text-[11px] text-text-muted mt-2">Rendered by the same code as the student's printout, so this is exactly what prints.</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ScaledHallTicket from '@/Components/Shared/ScaledHallTicket.vue';
import ItemListEditor from './Components/ItemListEditor.vue';
import TextRowsEditor from './Components/TextRowsEditor.vue';
import InstructionsEditor from './Components/InstructionsEditor.vue';
import LayoutEditor from './Components/LayoutEditor.vue';
import CustomFieldsManager from './Components/CustomFieldsManager.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({
  config: { type: Object, required: true },
  catalogue: { type: Object, required: true },
  customFields: { type: Array, default: () => [] },
  fieldTypes: { type: Object, default: () => ({}) },
  previewTickets: { type: Array, default: () => [] },
  savedAt: { type: String, default: null },
  savedBy: { type: String, default: null },
  defaultInstructions: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const perms = computed(() => page.props.admin_permissions?.settings_hall_ticket ?? {});
const canWrite = computed(() => page.props.admin_is_super || perms.value.write);
const canDelete = computed(() => page.props.admin_is_super || perms.value.delete);

const clone = (o) => JSON.parse(JSON.stringify(o));
const draft = reactive(clone(props.config));
const savedJson = ref(JSON.stringify(props.config));
const dirty = computed(() => JSON.stringify(draft) !== savedJson.value);

const tab = ref('fields');
const tabs = computed(() => [
  { key: 'fields', label: 'Ticket fields', count: draft.fields.filter((f) => f.enabled).length },
  { key: 'schedule', label: 'Exam schedule', count: draft.exam_columns.filter((c) => c.enabled).length },
  { key: 'instructions', label: 'Instructions', count: draft.instructions.length },
  { key: 'students', label: 'Student fields', count: props.customFields.length },
  { key: 'layout', label: 'Layout & text' },
]);

/* ── catalogues for the list editors ── */
const fieldCatalogue = computed(() => {
  const map = Object.fromEntries(props.catalogue.fields.map((f) => [f.key, f]));
  for (const row of draft.text_rows) {
    map[`text:${row.id}`] = { label: row.label || 'Untitled text row', hint: row.value || 'No value yet', group: 'Text row' };
  }
  return map;
});
const columnCatalogue = computed(() => Object.fromEntries(props.catalogue.columns.map((c) => [c.key, c])));

/* Custom fields come and go (saved immediately); keep the draft's field list in step. */
const syncFieldKeys = () => {
  const known = new Set([...props.catalogue.fields.map((f) => f.key), ...draft.text_rows.map((r) => `text:${r.id}`)]);
  for (let i = draft.fields.length - 1; i >= 0; i -= 1) if (!known.has(draft.fields[i].key)) draft.fields.splice(i, 1);
  const have = new Set(draft.fields.map((f) => f.key));
  for (const key of known) if (!have.has(key)) draft.fields.push({ key, enabled: false, label: null, wide: false });
};
watch(() => props.catalogue, () => {
  syncFieldKeys();
  // The saved copy changes the same way server-side, so don't flag it as an unsaved edit.
  const saved = JSON.parse(savedJson.value);
  const known = new Set(props.catalogue.fields.map((f) => f.key));
  saved.fields = saved.fields.filter((f) => known.has(f.key) || f.key.startsWith('text:'));
  for (const f of props.catalogue.fields) if (!saved.fields.some((s) => s.key === f.key)) saved.fields.push({ key: f.key, enabled: false, label: null, wide: false });
  savedJson.value = JSON.stringify(saved);
});

const onTextAdded = (row) => draft.fields.push({ key: `text:${row.id}`, enabled: true, label: null, wide: false });
const onTextRemoved = (row) => {
  const i = draft.fields.findIndex((f) => f.key === `text:${row.id}`);
  if (i >= 0) draft.fields.splice(i, 1);
};

/* What gets sent: unlabeled text rows and blank instructions are dropped (server does the same). */
const payload = () => {
  const data = clone(draft);
  const dropped = new Set(data.text_rows.filter((r) => !r.label?.trim()).map((r) => `text:${r.id}`));
  data.text_rows = data.text_rows.filter((r) => r.label?.trim());
  data.fields = data.fields.filter((f) => !dropped.has(f.key));
  data.instructions = data.instructions.map((s) => s.trim()).filter(Boolean);
  return data;
};

/* ── live preview (server-rendered, debounced) ── */
const previewWith = ref('sample:approved');
const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref('');
let timer = null;
let seq = 0;

const loadPreview = async () => {
  const mine = ++seq;
  previewLoading.value = true;
  const target = previewWith.value.startsWith('sample:')
    ? { sample_status: previewWith.value.split(':')[1] }
    : { ticket_id: Number(previewWith.value) };
  try {
    const res = await window.axios.post(route('admin.settings.hall-ticket.preview'), { ...payload(), ...target });
    if (mine !== seq) return;
    preview.value = res.data.ticket;
    previewError.value = '';
  } catch (e) {
    if (mine !== seq) return;
    previewError.value = e.response?.status === 422
      ? Object.values(e.response.data.errors ?? {})[0]?.[0] ?? 'Some values are not valid.'
      : 'The preview could not be refreshed. Your changes are still here.';
  } finally {
    if (mine === seq) previewLoading.value = false;
  }
};
watch([() => JSON.stringify(draft), previewWith], () => {
  clearTimeout(timer);
  timer = setTimeout(loadPreview, 350);
}, { immediate: true });

/* ── save / discard / reset ── */
const saving = ref(false);
const saveError = ref('');
const afterServerUpdate = () => {
  Object.assign(draft, clone(props.config));
  savedJson.value = JSON.stringify(props.config);
};
const save = () => {
  saveError.value = '';
  router.put(route('admin.settings.hall-ticket.update'), payload(), {
    preserveScroll: true,
    onStart: () => (saving.value = true),
    onFinish: () => (saving.value = false),
    onSuccess: afterServerUpdate,
    onError: (errors) => { saveError.value = Object.values(errors)[0] ?? 'Could not save.'; },
  });
};
const discard = async () => {
  if (!(await confirmDialog({ title: 'Discard your changes?', message: 'The layout goes back to the last saved version.', confirmText: 'Discard', tone: 'warning' }))) return;
  Object.assign(draft, JSON.parse(savedJson.value));
};
const resetDefaults = async () => {
  if (!(await confirmDialog({
    title: 'Reset the hall ticket to the default layout?',
    message: 'Fields, columns, instructions, text rows and layout options return to the defaults and every hall ticket changes immediately. Custom student fields and their data are kept.',
    confirmText: 'Reset layout',
    tone: 'danger',
  }))) return;
  router.post(route('admin.settings.hall-ticket.reset'), {}, { preserveScroll: true, onSuccess: afterServerUpdate });
};

/* ── unsaved-changes guard (§5.2) ── */
let removeGuard = null;
let leaveConfirmed = false;
const onBeforeUnload = (e) => { if (dirty.value) { e.preventDefault(); e.returnValue = ''; } };
onMounted(() => {
  window.addEventListener('beforeunload', onBeforeUnload);
  removeGuard = router.on('before', (event) => {
    const visit = event.detail.visit;
    if (saving.value || leaveConfirmed || !dirty.value || visit.method !== 'get') return;
    event.preventDefault();
    confirmDialog({ title: 'Leave without saving?', message: 'Your hall ticket layout changes will be lost.', confirmText: 'Leave', cancelText: 'Stay', tone: 'warning' })
      .then((leave) => {
        if (!leave) return;
        leaveConfirmed = true;
        router.visit(visit.url.href, { onFinish: () => { leaveConfirmed = false; } });
      });
  });
});
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', onBeforeUnload);
  removeGuard?.();
  clearTimeout(timer);
});
</script>

<style scoped>
.sec-h { @apply font-heading font-bold text-text-main text-base; }
.sec-p { @apply text-sm text-text-muted mt-1 mb-4; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main; }
.btn-primary { @apply text-sm font-semibold px-4 py-2.5 rounded-xl bg-primary text-white hover:bg-primary-light disabled:opacity-40 disabled:cursor-not-allowed; }
.btn-ghost { @apply text-sm font-semibold px-3.5 py-2.5 rounded-xl text-text-main hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed; }
</style>
