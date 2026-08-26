<template>
  <AdminLayout title="Receipts" subtitle="Issue GST-ready receipts and sales reports for completed payments">
    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.success }}
    </div>

    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 mb-6">
      <div>
        <p class="text-sm text-text-muted">
          <span class="font-number font-bold text-primary">{{ summary.paid_count.toLocaleString() }}</span>
          completed {{ summary.paid_count === 1 ? 'payment' : 'payments' }}
        </p>
        <p class="text-xs text-text-muted mt-1">Next receipt: <span class="font-number font-semibold text-text-main">{{ sequence.next_receipt_number }}</span></p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          :disabled="!canBulk"
          @click="downloadSelected"
          class="action-btn"
          :class="canBulk ? 'border-primary/30 text-primary bg-white hover:bg-primary/5' : 'border-gray-200 text-gray-400 bg-gray-100 cursor-not-allowed'"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/>
          </svg>
          Download Selected
        </button>
        <a
          :href="salesReportUrl"
          :aria-disabled="!canSalesReport"
          class="action-btn"
          :class="canSalesReport ? 'border-danger/30 text-danger bg-white hover:bg-danger/5' : 'border-gray-200 text-gray-400 bg-gray-100 pointer-events-none'"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v14H7a2 2 0 01-2-2V5a2 2 0 012-2zm7 0v5h5M9 13h6m-6 4h4"/>
          </svg>
          Sales Report PDF
        </a>
        <Link :href="route('admin.settings.receipts')" class="action-btn border-gray-200 text-text-main bg-white hover:bg-gray-50">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
          Settings
        </Link>
      </div>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-5 gap-3 mb-6">
      <div v-for="stat in statCards" :key="stat.label" class="bg-white border border-gray-100 shadow-sm rounded-lg p-4 min-w-0">
        <p class="text-[11px] font-semibold uppercase text-text-muted mb-1">{{ stat.label }}</p>
        <p class="font-number text-2xl font-bold truncate" :class="stat.color">{{ stat.value }}</p>
      </div>
    </div>

    <section class="bg-white border border-gray-100 shadow-sm rounded-lg mb-5">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
        <h2 class="font-heading font-bold text-text-main text-sm">Receipt filters</h2>
        <button v-if="hasFilters" type="button" @click="clearFilters" class="text-xs font-semibold text-danger hover:text-red-700">Clear all</button>
      </div>
      <form class="p-5" @submit.prevent="applyFilters">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
          <label class="block xl:col-span-2">
            <span class="filter-label">Search</span>
            <input v-model="form.search" type="text" class="filter-control" placeholder="Receipt, student, order or payment ID" />
          </label>

          <label class="block">
            <span class="filter-label">Receipt status</span>
            <select v-model="form.receipt_status" class="filter-control">
              <option value="">All paid payments</option>
              <option value="issued">Issued receipts</option>
              <option value="unissued">Not issued yet</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Payment source</span>
            <select v-model="form.source" class="filter-control">
              <option value="">All sources</option>
              <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Olympiad</span>
            <select v-model="form.exam_id" class="filter-control">
              <option value="">All olympiads</option>
              <option v-for="exam in exams" :key="exam.id" :value="String(exam.id)">
                {{ exam.name }}{{ exam.exam_code ? ` (${exam.exam_code})` : '' }}
              </option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Method</span>
            <select v-model="form.method" class="filter-control">
              <option value="">All methods</option>
              <option v-for="method in methods" :key="method" :value="method">{{ labelMethod(method) }}</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Paid from</span>
            <input v-model="form.date_from" type="date" class="filter-control" />
          </label>

          <label class="block">
            <span class="filter-label">Paid to</span>
            <input v-model="form.date_to" type="date" :min="form.date_from || undefined" class="filter-control" />
          </label>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mt-5 pt-4 border-t border-gray-100">
          <label class="block">
            <span class="filter-label">Rows</span>
            <select v-model="form.per_page" class="filter-control min-w-24">
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </label>
          <button type="submit" class="inline-flex items-center justify-center gap-2 bg-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-primary-light transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10m-7 7h4"/>
            </svg>
            Apply filters
          </button>
        </div>
      </form>
    </section>

    <div class="bg-white border border-gray-100 shadow-sm rounded-lg overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-heading font-bold text-text-main text-sm">Completed payments</h2>
        <span class="text-xs text-text-muted">{{ payments.total.toLocaleString() }} records</span>
      </div>

      <div v-if="payments.data.length === 0" class="py-20 px-5 text-center">
        <div class="w-12 h-12 bg-primary/10 text-primary rounded-lg flex items-center justify-center mx-auto mb-4">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14h6m-6 4h6M7 3h7l5 5v13H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
          </svg>
        </div>
        <p class="font-heading font-bold text-text-main">No completed payments match this view</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[1160px] text-sm">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="table-head w-12">
                <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" :checked="allVisibleSelected" @change="toggleAllVisible" />
              </th>
              <th class="table-head">Receipt</th>
              <th class="table-head">Student</th>
              <th class="table-head">Olympiad</th>
              <th class="table-head text-right">Amount</th>
              <th class="table-head">Paid Date</th>
              <th class="table-head">Method</th>
              <th class="table-head text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="payment in payments.data" :key="payment.id" class="hover:bg-gray-50/70 transition-colors align-top">
              <td class="table-cell">
                <input v-model="selectedIds" :value="payment.id" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
              </td>
              <td class="table-cell">
                <p class="font-number text-xs font-bold text-text-main">{{ payment.receipt?.receipt_number || 'Not issued' }}</p>
                <p class="text-[11px] text-text-muted font-number">Payment #{{ payment.id }}</p>
              </td>
              <td class="table-cell">
                <p class="font-semibold text-text-main max-w-52 truncate">{{ payment.student?.name || '-' }}</p>
                <p class="text-xs text-text-muted max-w-52 truncate">{{ payment.student?.email || '-' }}</p>
                <p v-if="payment.class" class="text-[11px] text-text-muted mt-1">{{ payment.class }}</p>
              </td>
              <td class="table-cell max-w-72">
                <div v-if="payment.exams.length" class="flex flex-wrap gap-1.5">
                  <span v-for="exam in payment.exams.slice(0, 2)" :key="exam" class="inline-flex max-w-52 px-2 py-1 rounded bg-gray-100 text-text-main text-[11px] font-semibold truncate">{{ exam }}</span>
                  <span v-if="payment.exams.length > 2" class="inline-flex px-2 py-1 rounded bg-gray-100 text-text-muted text-[11px] font-semibold">+{{ payment.exams.length - 2 }}</span>
                </div>
                <span v-else class="text-xs text-text-muted">Online olympiad registration</span>
              </td>
              <td class="table-cell text-right">
                <p class="font-number text-sm font-bold text-text-main">{{ inr(payment.amount) }}</p>
                <p v-if="payment.discount_amount > 0" class="text-[11px] text-success">Discount {{ inr(payment.discount_amount) }}</p>
              </td>
              <td class="table-cell">
                <p class="font-number text-xs text-text-main">{{ formatDate(payment.paid_at || payment.created_at) }}</p>
                <span class="inline-flex mt-1 px-2 py-0.5 rounded text-[11px] font-semibold" :class="sourceClass(payment.source)">{{ payment.source_label }}</span>
              </td>
              <td class="table-cell">
                <p class="capitalize text-xs text-text-main">{{ labelMethod(payment.method) }}</p>
                <p v-if="payment.payment_id || payment.manual_reference" class="text-[11px] text-text-muted font-number max-w-40 truncate">{{ payment.payment_id || payment.manual_reference }}</p>
              </td>
              <td class="table-cell text-right">
                <a :href="route('admin.receipts.download', payment.id)" class="inline-flex items-center gap-1 text-primary hover:text-primary-light text-xs font-semibold px-2 py-1 rounded-lg hover:bg-primary/5 transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/>
                  </svg>
                  Download
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="payments.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-text-muted text-xs">Showing {{ payments.from }}-{{ payments.to }} of {{ payments.total }} receipts</p>
        <div class="flex gap-1">
          <Link
            v-for="link in payments.links"
            :key="link.label"
            :href="link.url || '#'"
            :class="[
              'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
              link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100',
              !link.url ? 'opacity-40 pointer-events-none' : '',
            ]"
            v-html="link.label"
            preserve-scroll
          />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  payments: Object,
  filters: Object,
  sources: Object,
  methods: Array,
  exams: Array,
  sequence: Object,
  summary: Object,
  exportLimits: Object,
});

const form = ref({
  search: props.filters.search || '',
  receipt_status: props.filters.receipt_status || '',
  source: props.filters.source || '',
  method: props.filters.method || '',
  exam_id: props.filters.exam_id ? String(props.filters.exam_id) : '',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  per_page: String(props.filters.per_page || 25),
});

const selectedIds = ref([]);

watch(() => props.payments.data, () => { selectedIds.value = []; });

const hasFilters = computed(() => Object.entries(form.value).some(([key, value]) => key !== 'per_page' && value));
const visibleIds = computed(() => props.payments.data.map((payment) => payment.id));
const allVisibleSelected = computed(() => visibleIds.value.length > 0 && visibleIds.value.every((id) => selectedIds.value.includes(id)));
const canBulk = computed(() => selectedIds.value.length > 0 && selectedIds.value.length <= props.exportLimits.bulkPdf);
const canSalesReport = computed(() => Boolean(form.value.date_from && form.value.date_to) && props.summary.paid_count <= props.exportLimits.salesPdf);

const statCards = computed(() => [
  { label: 'Collected', value: inr(props.summary.total_collected), color: 'text-success' },
  { label: 'Issued', value: props.summary.issued_count.toLocaleString(), color: 'text-primary' },
  { label: 'Not Issued', value: props.summary.unissued_count.toLocaleString(), color: 'text-gold' },
  { label: 'Taxable', value: inr(props.summary.taxable_amount), color: 'text-text-main' },
  { label: 'GST', value: inr(props.summary.gst_amount), color: 'text-accent' },
]);

const cleanParams = (includePagination = true) => {
  const params = { ...form.value };
  if (!includePagination) delete params.per_page;

  return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined));
};

const salesReportUrl = computed(() => (
  canSalesReport.value ? route('admin.receipts.sales-report', cleanParams(false)) : '#'
));

const applyFilters = () => {
  router.get(route('admin.receipts.index'), cleanParams(), { preserveState: true, replace: true });
};

const clearFilters = () => {
  form.value = { search: '', receipt_status: '', source: '', method: '', exam_id: '', date_from: '', date_to: '', per_page: '25' };
  applyFilters();
};

const toggleAllVisible = () => {
  selectedIds.value = allVisibleSelected.value ? [] : [...visibleIds.value];
};

const downloadSelected = () => {
  if (!canBulk.value) return;
  window.location.href = route('admin.receipts.bulk', { ids: selectedIds.value.join(',') });
};

const sourceClass = (key) => ({
  marketing: 'bg-accent/10 text-accent-dark',
  admin: 'bg-royal/10 text-royal',
  onboarding: 'bg-gold/10 text-gold-dark',
}[key] || 'bg-gray-100 text-text-muted');

const labelMethod = (method) => String(method || '-').replaceAll('_', ' ');
const inr = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
</script>

<style scoped>
.action-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-width:1px; border-radius:8px; font-size:13px; font-weight:700; transition:background-color .15s ease, color .15s ease; }
.filter-label { display:block; margin-bottom:6px; color:#5B6373; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.filter-control { width:100%; border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px; color:#0A1024; font-size:14px; background:#fff; outline:none; }
.filter-control:focus { border-color:#131C3D; box-shadow:0 0 0 2px rgba(19,28,61,.10); }
.table-head { padding:12px 16px; text-align:left; color:#5B6373; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.table-cell { padding:14px 16px; vertical-align:top; }
</style>
