<template>
  <AdminLayout
    title="Data Entry"
    subtitle="Spreadsheet-style school completion queue"
  >
    <div class="space-y-5">
      <section class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3">
        <button
          v-for="card in summaryCards"
          :key="card.key"
          type="button"
          class="bg-white border border-gray-100 rounded-xl p-3 text-left shadow-sm hover:border-accent/40 transition-colors"
          :class="filters.queue === card.queue ? 'ring-2 ring-accent/30 border-accent/50' : ''"
          @click="selectQueue(card.queue)"
        >
          <p class="text-[11px] text-text-muted font-semibold uppercase">{{ card.label }}</p>
          <p class="mt-1 font-number text-xl font-bold text-text-main">{{ formatNumber(card.value) }}</p>
        </button>
      </section>

      <section class="bg-white border border-gray-100 rounded-xl shadow-sm">
        <div class="p-4 border-b border-gray-100 flex flex-col xl:flex-row xl:items-end gap-3">
          <div class="flex-1 min-w-[220px]">
            <label class="block text-xs font-semibold text-text-muted mb-1">Find school</label>
            <div class="flex gap-2">
              <input
                v-model="filters.search"
                type="search"
                class="field h-10"
                placeholder="School code, code:72282, sid:22709, name, city, PIN"
                @keyup.enter="reloadRows(1)"
              >
              <button type="button" class="btn-primary h-10" @click="reloadRows(1)">
                Search
              </button>
            </div>
          </div>

          <div class="w-full sm:w-56">
            <label class="block text-xs font-semibold text-text-muted mb-1">Work queue</label>
            <select v-model="filters.queue" class="field h-10" @change="reloadRows(1)">
              <option v-for="queue in queues" :key="queue.value" :value="queue.value">{{ queue.label }}</option>
            </select>
          </div>

          <div class="w-full sm:w-56">
            <label class="block text-xs font-semibold text-text-muted mb-1">State</label>
            <select v-model="filters.state" class="field h-10" @change="reloadRows(1)">
              <option value="">All states</option>
              <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
            </select>
          </div>

          <div class="w-full sm:w-40">
            <label class="block text-xs font-semibold text-text-muted mb-1">Rows</label>
            <select v-model.number="filters.per_page" class="field h-10" @change="reloadRows(1)">
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="200">200</option>
            </select>
          </div>

          <button type="button" class="btn-secondary h-10" @click="clearFilters">
            Reset
          </button>
        </div>

        <div class="px-4 py-3 bg-card/40 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center justify-between gap-2">
          <p class="text-xs text-text-muted">
            Showing <span class="font-semibold text-text-main">{{ rowsMeta.from || 0 }}-{{ rowsMeta.to || 0 }}</span>
            of <span class="font-semibold text-text-main">{{ formatNumber(rowsMeta.total || 0) }}</span>.
            Use Tab to move across cells. Paste copied spreadsheet rows directly into editable cells.
          </p>
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="inline-flex items-center gap-1 text-text-muted"><span class="w-2 h-2 rounded-full bg-accent"></span> Unsaved</span>
            <span class="inline-flex items-center gap-1 text-text-muted"><span class="w-2 h-2 rounded-full bg-success"></span> Saved</span>
            <span class="inline-flex items-center gap-1 text-text-muted"><span class="w-2 h-2 rounded-full bg-danger"></span> Error</span>
          </div>
        </div>

        <div v-if="message" class="mx-4 mt-4 rounded-lg border px-3 py-2 text-sm" :class="messageType === 'error' ? 'border-danger/30 bg-red-50 text-danger' : 'border-success/30 bg-green-50 text-success'">
          {{ message }}
        </div>

        <div class="relative">
          <div v-if="loading" class="absolute inset-0 z-20 bg-white/70 backdrop-blur-sm flex items-center justify-center">
            <div class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold shadow-lg">Loading rows...</div>
          </div>

          <div class="overflow-auto max-h-[68vh]">
            <table class="sheet-table">
              <thead>
                <tr>
                  <th class="sticky-col col-index">#</th>
                  <th class="sticky-col col-code">Code</th>
                  <th>SchId</th>
                  <th>School Name</th>
                  <th>Address</th>
                  <th>State</th>
                  <th>District</th>
                  <th>City</th>
                  <th>PIN</th>
                  <th>Email</th>
                  <th>Mobile</th>
                  <th>Head Phone</th>
                  <th>Status</th>
                  <th>Coord 1 Name</th>
                  <th>Coord 1 Role</th>
                  <th>Coord 1 Phone</th>
                  <th>Coord 1 Email</th>
                  <th>Coord 2 Name</th>
                  <th>Coord 2 Role</th>
                  <th>Coord 2 Phone</th>
                  <th>Coord 2 Email</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="rows.length === 0">
                  <td colspan="21" class="empty-cell">No schools match this queue.</td>
                </tr>
                <tr v-for="(row, rowIndex) in rows" :key="row.id" :class="{ 'row-saved': savedRows.has(row.id) }">
                  <td class="sticky-col col-index text-xs text-text-muted font-number">{{ (rowsMeta.from || 1) + rowIndex }}</td>
                  <td class="sticky-col col-code">
                    <button type="button" class="code-pill" @click="copyCode(row.school_code)">{{ row.school_code }}</button>
                  </td>
                  <td class="readonly-cell">{{ row.external_school_id || '-' }}</td>
                  <SheetInput :row-index="rowIndex" field="name" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetInput :row-index="rowIndex" field="address" :row="row" :errors="errors" wide @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetInput :row-index="rowIndex" field="state" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetInput :row-index="rowIndex" field="district" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetInput :row-index="rowIndex" field="city" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetInput :row-index="rowIndex" field="pin_code" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetInput :row-index="rowIndex" field="email" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetInput :row-index="rowIndex" field="mobile" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetInput :row-index="rowIndex" field="head_phone" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <td>
                    <select v-model="row.is_active" class="cell-input w-28" :class="{ dirty: isDirty(row.id, 'is_active') }" :disabled="!canWrite" @change="markDirty(row.id, 'is_active')">
                      <option :value="true">Active</option>
                      <option :value="false">Blocked</option>
                    </select>
                  </td>
                  <SheetInput :row-index="rowIndex" field="coordinators.0.name" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetSelect :row-index="rowIndex" field="coordinators.0.designation" :row="row" :errors="errors" :options="designationOptionsFor(getValue(row, 'coordinators.0.designation'))" @dirty="markDirty" />
                  <SheetInput :row-index="rowIndex" field="coordinators.0.phone" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetInput :row-index="rowIndex" field="coordinators.0.email" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetInput :row-index="rowIndex" field="coordinators.1.name" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetSelect :row-index="rowIndex" field="coordinators.1.designation" :row="row" :errors="errors" :options="designationOptionsFor(getValue(row, 'coordinators.1.designation'))" @dirty="markDirty" />
                  <SheetInput :row-index="rowIndex" field="coordinators.1.phone" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                  <SheetInput :row-index="rowIndex" field="coordinators.1.email" :row="row" :errors="errors" @dirty="markDirty" @paste-grid="handlePaste" />
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="p-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <button type="button" class="btn-secondary" :disabled="rowsMeta.current_page <= 1 || loading" @click="reloadRows(rowsMeta.current_page - 1)">
              Previous
            </button>
            <button type="button" class="btn-secondary" :disabled="rowsMeta.current_page >= rowsMeta.last_page || loading" @click="reloadRows(rowsMeta.current_page + 1)">
              Next
            </button>
          </div>
          <p class="text-xs text-text-muted">
            Page {{ rowsMeta.current_page || 1 }} of {{ rowsMeta.last_page || 1 }}
          </p>
        </div>
      </section>
    </div>

    <div v-if="canWrite && dirtyRowCount > 0" class="fixed bottom-5 right-5 z-30 bg-primary text-white rounded-xl shadow-2xl border border-white/10 px-4 py-3 flex items-center gap-3">
      <div>
        <p class="text-sm font-semibold">{{ dirtyRowCount }} changed {{ dirtyRowCount === 1 ? 'row' : 'rows' }}</p>
        <p class="text-xs text-white/65">Save before changing page or queue.</p>
      </div>
      <button type="button" class="bg-accent hover:bg-accent-dark text-white rounded-lg px-4 py-2 text-sm font-bold disabled:opacity-60" :disabled="saving" @click="saveRows">
        {{ saving ? 'Saving...' : 'Save changes' }}
      </button>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, defineComponent, h, reactive, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  initialRows: { type: Object, required: true },
  filters: { type: Object, required: true },
  summary: { type: Object, required: true },
  states: { type: Array, default: () => [] },
  queues: { type: Array, default: () => [] },
  schoolDesignations: { type: Array, default: () => [] },
});

const editableFields = [
  'name',
  'address',
  'state',
  'district',
  'city',
  'pin_code',
  'email',
  'mobile',
  'head_phone',
  'coordinators.0.name',
  'coordinators.0.designation',
  'coordinators.0.phone',
  'coordinators.0.email',
  'coordinators.1.name',
  'coordinators.1.designation',
  'coordinators.1.phone',
  'coordinators.1.email',
];

const rows = ref(normalizeRows(props.initialRows.data));
const rowsMeta = ref(props.initialRows.meta);
const summary = ref(props.summary);
const filters = reactive({
  search: props.filters.search || '',
  state: props.filters.state || '',
  queue: props.filters.queue || 'incomplete',
  per_page: Number(props.filters.per_page || 100),
});
const dirtyCells = reactive({});
const savedRows = ref(new Set());
const errors = ref({});
const loading = ref(false);
const saving = ref(false);
const message = ref('');
const messageType = ref('success');
const page = usePage();
const canWrite = computed(() => !!page.props.admin_permissions?.data_entry?.write);

const summaryCards = computed(() => [
  { key: 'total', label: 'Total', value: summary.value.total || 0, queue: 'all' },
  { key: 'incomplete', label: 'Incomplete', value: summary.value.incomplete || 0, queue: 'incomplete' },
  { key: 'district', label: 'District', value: summary.value.missing_district || 0, queue: 'missing_district' },
  { key: 'email', label: 'Email', value: summary.value.missing_email || 0, queue: 'missing_email' },
  { key: 'phone', label: 'Phone', value: summary.value.missing_phone || 0, queue: 'missing_phone' },
  { key: 'coord', label: 'Coordinator', value: summary.value.missing_coordinator || 0, queue: 'missing_coordinator' },
  { key: 'pin', label: 'PIN', value: summary.value.missing_pin || 0, queue: 'missing_pin' },
  { key: 'blocked', label: 'Blocked', value: summary.value.blocked || 0, queue: 'blocked' },
]);

const dirtyRowCount = computed(() => Object.keys(dirtyCells).length);
const activeDesignationNames = computed(() => props.schoolDesignations.map((designation) => designation.name));

function designationOptionsFor(currentValue) {
  const names = [...activeDesignationNames.value];
  if (currentValue && !names.includes(currentValue)) names.push(currentValue);

  return names;
}

function normalizeRows(sourceRows) {
  return sourceRows.map((row) => {
    const normalized = {
      ...row,
      coordinators: [...(row.coordinators || [])],
    };

    while (normalized.coordinators.length < 2) {
      normalized.coordinators.push({ name: '', designation: '', phone: '', email: '' });
    }

    return normalized;
  });
}

function markDirty(rowId, field) {
  if (!canWrite.value) return;

  if (!dirtyCells[rowId]) dirtyCells[rowId] = {};
  dirtyCells[rowId][field] = true;
  savedRows.value.delete(rowId);
}

function isDirty(rowId, field) {
  return Boolean(dirtyCells[rowId]?.[field]);
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString('en-IN');
}

function selectQueue(queue) {
  filters.queue = queue;
  reloadRows(1);
}

function queryParams(page = 1) {
  const params = {
    queue: filters.queue,
    per_page: filters.per_page,
    page,
  };

  if (filters.search) params.search = filters.search;
  if (filters.state) params.state = filters.state;

  return params;
}

async function reloadRows(page = 1) {
  if (!confirmIfDirty()) return;

  loading.value = true;
  message.value = '';
  errors.value = {};

  try {
    const { data } = await window.axios.get(route('admin.data-entry.rows'), { params: queryParams(page) });
    rows.value = normalizeRows(data.rows.data);
    rowsMeta.value = data.rows.meta;
    summary.value = data.summary;
    clearDirty();
  } catch {
    showMessage('Could not load the selected school rows.', 'error');
  } finally {
    loading.value = false;
  }
}

function clearFilters() {
  filters.search = '';
  filters.state = '';
  filters.queue = 'incomplete';
  filters.per_page = 100;
  reloadRows(1);
}

function confirmIfDirty() {
  if (dirtyRowCount.value === 0) return true;

  return window.confirm('You have unsaved school rows. Discard these changes and continue?');
}

function clearDirty() {
  Object.keys(dirtyCells).forEach((key) => delete dirtyCells[key]);
}

function toPayload(row) {
  return {
    id: row.id,
    name: row.name,
    address: row.address,
    state: row.state,
    district: row.district,
    city: row.city,
    pin_code: row.pin_code,
    email: row.email,
    mobile: row.mobile,
    head_phone: row.head_phone,
    is_active: row.is_active,
    coordinators: row.coordinators.slice(0, 2).map((coordinator) => ({
      name: coordinator.name,
      designation: coordinator.designation,
      phone: coordinator.phone,
      email: coordinator.email,
    })),
  };
}

async function saveRows() {
  if (!canWrite.value || dirtyRowCount.value === 0 || saving.value) return;

  saving.value = true;
  message.value = '';
  errors.value = {};

  const dirtyIds = Object.keys(dirtyCells).map((id) => Number(id));
  const payload = rows.value
    .filter((row) => dirtyIds.includes(row.id))
    .map((row) => toPayload(row));

  try {
    const { data } = await window.axios.patch(route('admin.data-entry.rows.update'), { rows: payload });
    const updated = normalizeRows(data.rows);
    const byId = new Map(updated.map((row) => [row.id, row]));

    rows.value = rows.value.map((row) => byId.get(row.id) || row);
    summary.value = data.summary;
    clearDirty();
    savedRows.value = new Set(updated.map((row) => row.id));
    showMessage(data.message || 'Rows saved.', 'success');
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
      showMessage('Some cells need correction before saving.', 'error');
    } else {
      showMessage('Could not save rows. Try again.', 'error');
    }
  } finally {
    saving.value = false;
  }
}

function showMessage(text, type = 'success') {
  message.value = text;
  messageType.value = type;
}

function getValue(row, field) {
  return field.split('.').reduce((value, part) => value?.[part], row) ?? '';
}

function setValue(row, field, value) {
  const parts = field.split('.');
  let target = row;
  while (parts.length > 1) {
    const part = parts.shift();
    if (!target[part]) target[part] = {};
    target = target[part];
  }
  target[parts[0]] = value;
}

function handlePaste({ rowIndex, field, event }) {
  if (!canWrite.value) return;

  const text = event.clipboardData?.getData('text/plain') || '';
  if (!text.includes('\t') && !text.includes('\n')) return;

  event.preventDefault();

  const startFieldIndex = editableFields.indexOf(field);
  if (startFieldIndex < 0) return;

  text.replace(/\r/g, '').split('\n').filter((line) => line.length > 0).forEach((line, lineIndex) => {
    const row = rows.value[rowIndex + lineIndex];
    if (!row) return;

    line.split('\t').forEach((cell, cellIndex) => {
      const nextField = editableFields[startFieldIndex + cellIndex];
      if (!nextField) return;
      setValue(row, nextField, cell.trim());
      markDirty(row.id, nextField);
    });
  });
}

async function copyCode(code) {
  try {
    await navigator.clipboard?.writeText(code);
    showMessage(`Copied ${code}.`, 'success');
  } catch {
    showMessage(code, 'success');
  }
}

const SheetInput = defineComponent({
  props: {
    rowIndex: { type: Number, required: true },
    field: { type: String, required: true },
    row: { type: Object, required: true },
    errors: { type: Object, required: true },
    wide: { type: Boolean, default: false },
  },
  emits: ['dirty', 'paste-grid'],
  setup(componentProps, { emit }) {
    const model = computed({
      get: () => getValue(componentProps.row, componentProps.field),
      set: (value) => {
        setValue(componentProps.row, componentProps.field, value);
        emit('dirty', componentProps.row.id, componentProps.field);
      },
    });

    const errorKey = computed(() => `rows.${componentProps.rowIndex}.${componentProps.field}`);
    const hasError = computed(() => Boolean(componentProps.errors[errorKey.value]));

    return () => h('td', { class: hasError.value ? 'cell-error' : '' }, [
      h('input', {
        value: model.value,
        class: [
          'cell-input',
          componentProps.wide ? 'w-96' : 'w-44',
          isDirty(componentProps.row.id, componentProps.field) ? 'dirty' : '',
          hasError.value ? 'invalid' : '',
        ],
        onInput: (event) => { model.value = event.target.value; },
        onPaste: (event) => emit('paste-grid', { rowIndex: componentProps.rowIndex, field: componentProps.field, event }),
        disabled: !canWrite.value,
      }),
      hasError.value ? h('p', { class: 'cell-error-text' }, componentProps.errors[errorKey.value][0]) : null,
    ]);
  },
});

const SheetSelect = defineComponent({
  props: {
    rowIndex: { type: Number, required: true },
    field: { type: String, required: true },
    row: { type: Object, required: true },
    errors: { type: Object, required: true },
    options: { type: Array, default: () => [] },
  },
  emits: ['dirty'],
  setup(componentProps, { emit }) {
    const model = computed({
      get: () => getValue(componentProps.row, componentProps.field),
      set: (value) => {
        setValue(componentProps.row, componentProps.field, value);
        emit('dirty', componentProps.row.id, componentProps.field);
      },
    });

    const errorKey = computed(() => `rows.${componentProps.rowIndex}.${componentProps.field}`);
    const hasError = computed(() => Boolean(componentProps.errors[errorKey.value]));

    return () => h('td', { class: hasError.value ? 'cell-error' : '' }, [
      h('select', {
        value: model.value,
        class: [
          'cell-input',
          'w-44',
          'bg-white',
          isDirty(componentProps.row.id, componentProps.field) ? 'dirty' : '',
          hasError.value ? 'invalid' : '',
        ],
        onChange: (event) => { model.value = event.target.value; },
        disabled: !canWrite.value,
      }, [
        h('option', { value: '' }, 'Select role'),
        ...componentProps.options.map((option) => h('option', { value: option }, option)),
      ]),
      hasError.value ? h('p', { class: 'cell-error-text' }, componentProps.errors[errorKey.value][0]) : null,
    ]);
  },
});
</script>

<style scoped>
.field {
  width: 100%;
  border: 1px solid #d9dee8;
  border-radius: 10px;
  padding: 0 12px;
  font-size: 14px;
  color: #0A1024;
  background: #fff;
}

.field:focus,
.cell-input:focus {
  outline: none;
  border-color: #EE6A2C;
  box-shadow: 0 0 0 3px rgba(238, 106, 44, .16);
}

.btn-primary,
.btn-secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  padding: 0 14px;
  font-size: 13px;
  font-weight: 700;
  transition: background .16s ease, border-color .16s ease, color .16s ease;
}

.btn-primary {
  background: #131C3D;
  color: #fff;
}

.btn-primary:hover {
  background: #0A1024;
}

.btn-secondary {
  min-height: 40px;
  background: #fff;
  color: #131C3D;
  border: 1px solid #d9dee8;
}

.btn-secondary:hover:not(:disabled) {
  border-color: #EE6A2C;
  color: #C9501A;
}

.btn-secondary:disabled {
  opacity: .45;
  cursor: not-allowed;
}

.sheet-table {
  border-collapse: separate;
  border-spacing: 0;
  min-width: 3160px;
  width: 100%;
  font-size: 12px;
}

.sheet-table th {
  position: sticky;
  top: 0;
  z-index: 11;
  background: #131C3D;
  color: #fff;
  text-align: left;
  padding: 10px 8px;
  border-right: 1px solid rgba(255,255,255,.12);
  white-space: nowrap;
  font-weight: 700;
}

.sheet-table td {
  height: 42px;
  padding: 4px;
  border-right: 1px solid #e8edf4;
  border-bottom: 1px solid #e8edf4;
  background: #fff;
  vertical-align: top;
}

.sheet-table tbody tr:nth-child(even) td {
  background: #fcfbf8;
}

.sticky-col {
  position: sticky;
  z-index: 9;
}

th.sticky-col {
  z-index: 12;
}

.col-index {
  left: 0;
  width: 64px;
  min-width: 64px;
}

.col-code {
  left: 64px;
  width: 118px;
  min-width: 118px;
}

td.sticky-col {
  background: #fffaf0;
}

tr:nth-child(even) td.sticky-col {
  background: #f8f1e5;
}

.cell-input {
  height: 32px;
  border: 1px solid transparent;
  border-radius: 7px;
  padding: 0 8px;
  background: transparent;
  color: #0A1024;
}

.cell-input:hover {
  border-color: #cfd6e2;
  background: #fff;
}

.cell-input.dirty {
  border-color: rgba(238, 106, 44, .45);
  background: rgba(238, 106, 44, .08);
}

.cell-input.invalid {
  border-color: rgba(220, 38, 38, .7);
  background: rgba(220, 38, 38, .08);
}

.readonly-cell {
  min-width: 110px;
  color: #5B6373;
  font-family: "Rajdhani", sans-serif;
  font-weight: 700;
  padding-top: 12px !important;
}

.code-pill {
  min-width: 92px;
  border-radius: 999px;
  background: #F3E9D6;
  color: #131C3D;
  border: 1px solid #E7D9BE;
  padding: 5px 8px;
  font-family: "Rajdhani", sans-serif;
  font-weight: 700;
}

.cell-error-text {
  max-width: 176px;
  color: #DC2626;
  font-size: 10px;
  line-height: 1.2;
  margin-top: 2px;
}

.empty-cell {
  text-align: center;
  color: #5B6373;
  padding: 56px 12px !important;
}

.row-saved td {
  animation: savedPulse 1.2s ease;
}

@keyframes savedPulse {
  0% { background-color: rgba(22, 138, 102, .16); }
  100% { background-color: inherit; }
}
</style>
