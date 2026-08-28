<template>
  <AdminLayout title="Receipt Settings" subtitle="Configure invoice identity, numbering, GST and visible receipt fields">
    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.success }}
    </div>

    <form class="grid grid-cols-1 xl:grid-cols-3 gap-6" @submit.prevent="submit">
      <div class="xl:col-span-2 space-y-6">
        <section class="panel">
          <div class="panel-head">
            <h2>Company Details</h2>
            <p>These values are used when receipt PDFs and reports are rendered.</p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-5">
            <label class="block md:col-span-2">
              <span class="field-label">Company / receipt title *</span>
              <input v-model="form.company_name" type="text" class="field-control" />
              <p v-if="form.errors.company_name" class="field-error">{{ form.errors.company_name }}</p>
            </label>

            <label class="block">
              <span class="field-label">GSTIN</span>
              <input v-model="form.gstin" type="text" maxlength="15" class="field-control uppercase" placeholder="15-character GSTIN" />
              <p v-if="form.errors.gstin" class="field-error">{{ form.errors.gstin }}</p>
            </label>

            <label class="block">
              <span class="field-label">HSN / SAC</span>
              <input v-model="form.hsn_sac" type="text" class="field-control" />
              <p v-if="form.errors.hsn_sac" class="field-error">{{ form.errors.hsn_sac }}</p>
            </label>

            <label class="block md:col-span-2">
              <span class="field-label">Address</span>
              <textarea v-model="form.address" rows="3" class="field-control resize-y"></textarea>
              <p v-if="form.errors.address" class="field-error">{{ form.errors.address }}</p>
            </label>

            <label class="block">
              <span class="field-label">State</span>
              <input
                v-model="form.state"
                type="text"
                list="gst-state-options"
                class="field-control"
                placeholder="Delhi / 07"
                @change="syncStateFromName"
                @blur="syncStateFromName"
              />
              <datalist id="gst-state-options">
                <option v-for="option in stateOptions" :key="option.code" :value="option.label"></option>
              </datalist>
              <p v-if="form.errors.state" class="field-error">{{ form.errors.state }}</p>
              <p class="field-hint">Type the name alone or with its GST code, e.g. <strong>Delhi / 07</strong>.</p>
            </label>

            <label class="block">
              <span class="field-label">State code</span>
              <input v-model="form.state_code" type="text" inputmode="numeric" maxlength="2" class="field-control" placeholder="07" />
              <p v-if="form.errors.state_code" class="field-error">{{ form.errors.state_code }}</p>
              <p class="field-hint">Prints as: <strong>{{ statePreview || '—' }}</strong></p>
            </label>

            <label class="block">
              <span class="field-label">Email</span>
              <input v-model="form.email" type="email" class="field-control" />
              <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
            </label>

            <label class="block">
              <span class="field-label">Phone</span>
              <input v-model="form.phone" type="text" class="field-control" />
              <p v-if="form.errors.phone" class="field-error">{{ form.errors.phone }}</p>
            </label>

            <label class="block md:col-span-2">
              <span class="field-label">Website</span>
              <input v-model="form.website" type="text" class="field-control" />
              <p v-if="form.errors.website" class="field-error">{{ form.errors.website }}</p>
            </label>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head">
            <h2>Receipt Numbering</h2>
            <p>The saved prefix and padding control the invoice number shown on receipts and reports.</p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-5">
            <label class="block md:col-span-2">
              <span class="field-label">Prefix *</span>
              <input v-model="form.receipt_prefix" type="text" class="field-control font-number" placeholder="NEO/{FY}/" />
              <p v-if="form.errors.receipt_prefix" class="field-error">{{ form.errors.receipt_prefix }}</p>
              <p class="field-hint">Tokens: {FY}, {YYYY}, {YY}, {MM}</p>
            </label>

            <label class="block">
              <span class="field-label">Padding *</span>
              <input v-model="form.receipt_padding" type="number" min="1" max="10" class="field-control" />
              <p v-if="form.errors.receipt_padding" class="field-error">{{ form.errors.receipt_padding }}</p>
            </label>

            <label class="block">
              <span class="field-label">Next number *</span>
              <input v-model="form.next_sequence_number" type="number" min="1" class="field-control" />
              <p v-if="form.errors.next_sequence_number" class="field-error">{{ form.errors.next_sequence_number }}</p>
            </label>

            <label class="block">
              <span class="field-label">FY start month *</span>
              <select v-model="form.financial_year_start_month" class="field-control">
                <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
              </select>
            </label>

            <div class="md:col-span-3 bg-gray-50 border border-gray-200 rounded-lg p-4">
              <span class="field-label">Current preview</span>
              <p class="font-number text-xl font-bold text-text-main mt-1">{{ previewReceiptNumber }}</p>
              <p class="text-xs text-text-muted mt-1">Financial year {{ sequence.financial_year }} · minimum next number {{ sequence.minimum_next_number.toLocaleString() }}</p>
            </div>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head">
            <h2>Tax & Line Items</h2>
            <p>GST is calculated from the paid amount when receipts are issued.</p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-5">
            <label class="block md:col-span-2">
              <span class="field-label">Default service description *</span>
              <input v-model="form.service_description" type="text" class="field-control" />
              <p v-if="form.errors.service_description" class="field-error">{{ form.errors.service_description }}</p>
            </label>

            <label class="block">
              <span class="field-label">GST rate *</span>
              <input v-model="form.gst_rate" type="number" min="0" max="28" step="0.01" class="field-control" />
              <p v-if="form.errors.gst_rate" class="field-error">{{ form.errors.gst_rate }}</p>
            </label>

            <label class="md:col-span-3 flex items-center justify-between gap-4 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
              <span>
                <span class="block text-sm font-semibold text-text-main">Exam fees include GST</span>
                <span class="block text-xs text-text-muted mt-0.5">Keeps receipt total equal to the amount already collected.</span>
              </span>
              <input v-model="form.prices_include_gst" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
            </label>
          </div>
        </section>
      </div>

      <aside class="space-y-6">
        <section class="panel">
          <div class="panel-head">
            <h2>Logo</h2>
            <p>Uses the current site logo by default.</p>
          </div>
          <div class="p-5 space-y-4">
            <div class="border border-gray-200 rounded-lg p-4 bg-white">
              <img :src="settings.logo_url" alt="Receipt logo" class="max-h-14 max-w-full object-contain" />
            </div>
            <input ref="logoInput" type="file" accept=".jpg,.jpeg,.png,.svg" class="block w-full text-xs text-text-muted" @change="chooseLogo" />
            <label class="flex items-center gap-2 text-sm text-text-muted">
              <input v-model="form.remove_logo" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
              Use default logo
            </label>
            <p v-if="form.errors.logo" class="field-error">{{ form.errors.logo }}</p>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head">
            <h2>Visible Fields</h2>
            <p>Checked fields appear on receipt PDFs and reports.</p>
          </div>
          <div class="p-5 grid grid-cols-1 gap-2">
            <label v-for="(label, key) in visibleFields" :key="key" class="flex items-center gap-3 text-sm text-text-main bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
              <input
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                :checked="form.visible_fields.includes(key)"
                @change="toggleField(key)"
              />
              <span>{{ label }}</span>
            </label>
            <p v-if="form.errors.visible_fields" class="field-error">{{ form.errors.visible_fields }}</p>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head">
            <h2>Footer</h2>
          </div>
          <div class="p-5">
            <textarea v-model="form.footer_note" rows="4" class="field-control resize-y"></textarea>
            <p v-if="form.errors.footer_note" class="field-error">{{ form.errors.footer_note }}</p>
          </div>
        </section>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full bg-primary text-white py-3 rounded-lg text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60"
        >
          {{ form.processing ? 'Saving...' : 'Save Receipt Settings' }}
        </button>
      </aside>
    </form>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  settings: Object,
  visibleFields: Object,
  sequence: Object,
  stateOptions: { type: Array, default: () => [] },
});

const logoInput = ref(null);

const form = useForm({
  company_name: props.settings.company_name || '',
  gstin: props.settings.gstin || '',
  address: props.settings.address || '',
  state: props.settings.state_display || props.settings.state || '',
  state_code: props.settings.state_code || '',
  email: props.settings.email || '',
  phone: props.settings.phone || '',
  website: props.settings.website || '',
  logo: null,
  remove_logo: false,
  hsn_sac: props.settings.hsn_sac || '',
  service_description: props.settings.service_description || 'Online Olympiad Exam Registration',
  gst_rate: props.settings.gst_rate ?? 18,
  prices_include_gst: Boolean(props.settings.prices_include_gst),
  receipt_prefix: props.settings.receipt_prefix || 'NEO/{FY}/',
  receipt_padding: props.settings.receipt_padding || 4,
  financial_year_start_month: props.settings.financial_year_start_month || 4,
  next_sequence_number: props.sequence.next_number || 1,
  visible_fields: props.settings.visible_fields || Object.keys(props.visibleFields),
  footer_note: props.settings.footer_note || '',
});

const padStateCode = (value) => {
  const digits = String(value ?? '').replace(/\D+/g, '');
  return digits ? digits.slice(0, 2).padStart(2, '0') : '';
};

// Mirrors App\Support\GstStateCodes::split() so the box can hold "Delhi / 07".
const splitState = (raw) => {
  const value = String(raw ?? '').trim();
  if (!value) return { name: '', code: '' };

  const bracketed = value.match(/^(.*?)\s*\(\s*(\d{1,2})\s*\)$/);
  if (bracketed) return { name: bracketed[1].trim(), code: padStateCode(bracketed[2]) };

  const separated = value.match(/^(.*?)\s*[/|\u2013\u2014-]\s*(.*)$/);
  if (separated) {
    const [, left, right] = separated;
    if (/^\d{1,2}$/.test(right.trim())) return { name: left.trim(), code: padStateCode(right) };
    if (/^\d{1,2}$/.test(left.trim())) return { name: right.trim(), code: padStateCode(left) };
  }

  if (/^\d{1,2}$/.test(value)) {
    const match = props.stateOptions.find((option) => option.code === padStateCode(value));
    return { name: match?.name || '', code: padStateCode(value) };
  }

  return { name: value, code: '' };
};

const codeForStateName = (name) => {
  const key = String(name ?? '').toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, '');
  if (!key) return '';
  const match = props.stateOptions.find(
    (option) => option.name.toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, '') === key,
  );
  return match?.code || '';
};

// Keep the two boxes in step: a code typed inside the State box wins, a known
// state name fills a blank code box.
const syncStateFromName = () => {
  const { name, code } = splitState(form.state);
  form.state = name;
  const resolved = code || codeForStateName(name) || form.state_code;
  form.state_code = padStateCode(resolved);
};

const statePreview = computed(() => {
  const { name, code } = splitState(form.state);
  const resolvedCode = padStateCode(code || form.state_code || codeForStateName(name));
  if (!name) return resolvedCode;
  return resolvedCode ? `${name} / ${resolvedCode}` : name;
});

const months = [
  ['January', 1], ['February', 2], ['March', 3], ['April', 4], ['May', 5], ['June', 6],
  ['July', 7], ['August', 8], ['September', 9], ['October', 10], ['November', 11], ['December', 12],
].map(([label, value]) => ({ label, value }));

const previewReceiptNumber = computed(() => {
  const padded = String(form.next_sequence_number || 1).padStart(Number(form.receipt_padding || 1), '0');
  const now = new Date();
  const yyyy = String(now.getFullYear());
  const yy = yyyy.slice(-2);
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const startMonth = Number(form.financial_year_start_month || 4);
  const startYear = (now.getMonth() + 1) >= startMonth ? now.getFullYear() : now.getFullYear() - 1;
  const fy = `${startYear}-${String((startYear + 1) % 100).padStart(2, '0')}`;

  return String(form.receipt_prefix || '')
    .replaceAll('{FY}', fy)
    .replaceAll('{YYYY}', yyyy)
    .replaceAll('{YY}', yy)
    .replaceAll('{MM}', mm) + padded;
});

const chooseLogo = (event) => {
  form.logo = event.target.files?.[0] || null;
  if (form.logo) form.remove_logo = false;
};

const toggleField = (field) => {
  form.visible_fields = form.visible_fields.includes(field)
    ? form.visible_fields.filter((item) => item !== field)
    : [...form.visible_fields, field];
};

const submit = () => {
  form.post(route('admin.settings.receipts.update'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.logo = null;
      form.remove_logo = false;
      if (logoInput.value) logoInput.value.value = '';
    },
  });
};
</script>

<style scoped>
.panel { background:#fff; border:1px solid #f3f4f6; border-radius:8px; box-shadow:0 1px 2px rgba(10,16,36,.05); overflow:hidden; }
.panel-head { padding:16px 20px; border-bottom:1px solid #f3f4f6; }
.panel-head h2 { margin:0; font-family:Poppins, sans-serif; font-weight:700; color:#0A1024; font-size:14px; }
.panel-head p { margin:2px 0 0; color:#5B6373; font-size:12px; }
.field-label { display:block; margin-bottom:6px; color:#5B6373; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.field-control { width:100%; border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px; color:#0A1024; font-size:14px; background:#fff; outline:none; }
.field-control:focus { border-color:#131C3D; box-shadow:0 0 0 2px rgba(19,28,61,.10); }
.field-error { margin-top:5px; color:#DC2626; font-size:12px; }
.field-hint { margin-top:5px; color:#5B6373; font-size:11px; }
</style>
