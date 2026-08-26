<template>
  <AdminLayout title="Payments" subtitle="Track every transaction and print receipts">

    <!-- Top bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="bg-primary/10 text-primary text-sm font-semibold px-3 py-1.5 rounded-lg font-number">
          {{ payments.total.toLocaleString() }} Transactions
        </span>
        <span v-if="hasFilters" class="bg-accent/10 text-accent text-xs font-semibold px-2.5 py-1.5 rounded-lg">
          Filtered
        </span>
      </div>
    </div>

    <!-- Stats bar -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Total Collected</p>
        <p class="font-number text-2xl font-bold text-success">{{ inr(totals.collected) }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">This Month</p>
        <p class="font-number text-2xl font-bold text-primary">{{ inr(totals.month) }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Paid</p>
        <p class="font-number text-2xl font-bold text-text-main">{{ totals.paid.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Pending / Failed</p>
        <p class="font-number text-2xl font-bold text-gold">{{ (totals.pending + totals.failed).toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">From Marketing</p>
        <p class="font-number text-2xl font-bold text-accent">{{ inr(totals.marketing ?? 0) }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <div class="lg:col-span-2 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            v-model="filterForm.search" type="text"
            placeholder="Search student, order or payment id…"
            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 bg-gray-50"
            @keydown.enter="applyFilters"
          />
        </div>
        <select v-model="filterForm.status" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Status</option>
          <option value="paid">Paid</option>
          <option value="created">Pending</option>
          <option value="failed">Failed</option>
          <option value="refunded">Refunded</option>
        </select>
        <select v-model="filterForm.source" title="Where the payment came from"
                class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Sources</option>
          <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
        </select>
        <input v-model="filterForm.date_from" type="date" title="From date"
               class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main" />
        <input v-model="filterForm.date_to" type="date" title="To date"
               class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main" />
      </div>
      <div class="flex gap-2 mt-3">
        <button @click="applyFilters" class="bg-primary text-white px-4 py-2 rounded-lg text-xs font-semibold hover:bg-primary-light transition-colors">Apply Filters</button>
        <button v-if="hasFilters" @click="clearFilters" class="bg-gray-100 text-text-muted px-4 py-2 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors">Clear</button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

      <div v-if="payments.data.length === 0" class="py-20 text-center">
        <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
          </svg>
        </div>
        <p class="font-heading font-bold text-text-main text-base mb-1">
          {{ hasFilters ? 'No payments match your filters' : 'No payments yet' }}
        </p>
        <p class="text-text-muted text-sm">
          {{ hasFilters ? 'Try adjusting your filters.' : 'Transactions will appear here once students start enrolling.' }}
        </p>
      </div>

      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Receipt</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Student</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Exam &amp; Class</th>
            <th class="text-right px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Amount</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Date</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Status</th>
            <th class="text-right px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="p in payments.data" :key="p.id" class="hover:bg-gray-50/50 transition-colors">
            <td class="px-5 py-3.5">
              <p class="font-number text-xs font-semibold text-text-main">{{ p.receipt_number || `Payment #${p.id}` }}</p>
              <p v-if="p.payment_id" class="text-text-muted text-[11px] font-number truncate max-w-[130px]">{{ p.payment_id }}</p>
              <span v-if="p.is_manual" class="inline-flex mt-1 bg-accent/10 text-accent text-[10px] font-bold px-2 py-0.5 rounded-full">
                Manual
              </span>
            </td>

            <td class="px-4 py-3.5">
              <p class="font-semibold text-text-main text-sm truncate max-w-[180px]">{{ p.student?.name || '—' }}</p>
              <p class="text-text-muted text-xs truncate max-w-[180px]">{{ p.student?.email }}</p>
            </td>

            <td class="px-4 py-3.5">
              <div v-if="p.exams.length" class="flex flex-wrap gap-1 max-w-[240px]">
                <span v-for="ex in p.exams" :key="ex"
                      class="bg-primary/5 text-primary text-[11px] font-medium px-2 py-0.5 rounded-md">{{ ex }}</span>
              </div>
              <span v-else class="text-text-muted text-xs">—</span>
              <p v-if="p.class" class="mt-1 inline-flex items-center gap-1 text-[11px] text-text-muted">
                <span class="w-1 h-1 rounded-full bg-gold"></span>{{ p.class }}
              </p>
            </td>

            <td class="px-4 py-3.5 text-right">
              <span class="font-number text-sm font-semibold text-text-main">{{ inr(p.amount) }}</span>
              <p class="text-text-muted text-[11px] capitalize">{{ p.method || p.gateway }}</p>
            </td>

            <td class="px-4 py-3.5 text-text-muted text-xs font-number whitespace-nowrap">
              {{ formatDate(p.created_at) }}
              <span class="block mt-1 font-body font-semibold px-1.5 py-0.5 rounded w-fit"
                    :class="sourceClass(p.source)">{{ p.source_label }}</span>
            </td>

            <td class="px-4 py-3.5">
              <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full" :class="badge(p.status).cls">
                <span class="w-1.5 h-1.5 rounded-full" :class="badge(p.status).dot"></span>
                {{ badge(p.status).label }}
              </span>
            </td>

            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1.5">
                <a v-if="p.status === 'paid'"
                   :href="route('admin.payments.receipt', p.id)" target="_blank"
                   class="text-primary hover:text-primary-light text-xs font-semibold px-2 py-1 rounded-lg hover:bg-primary/5 transition-colors inline-flex items-center gap-1">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                  </svg>
                  Receipt
                </a>
                <button
                  v-if="p.status === 'created'"
                  type="button"
                  @click="openReconcile(p)"
                  class="text-success hover:text-green-700 text-xs font-semibold px-2 py-1 rounded-lg hover:bg-success/5 transition-colors"
                >
                  Verify
                </button>
                <button
                  v-if="['created', 'paid'].includes(p.status)"
                  type="button"
                  @click="openDowngrade(p)"
                  class="text-danger hover:text-red-700 text-xs font-semibold px-2 py-1 rounded-lg hover:bg-danger/5 transition-colors"
                >
                  Downgrade
                </button>
                <span v-if="!['created', 'paid'].includes(p.status)" class="text-text-muted/50 text-xs px-2 py-1">—</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="payments.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-text-muted text-xs">Showing {{ payments.from }}–{{ payments.to }} of {{ payments.total }} transactions</p>
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

    <div v-if="reconcileTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Verify Pending Payment</h3>
        <p class="text-text-muted text-sm mb-5">
          Mark NEO-{{ String(reconcileTarget.id).padStart(6, '0') }} as paid and grant access to the attached olympiad(s).
        </p>

        <div class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Reference / UTR / Note ID</label>
            <input
              v-model="reconcileForm.manual_reference"
              type="text"
              class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary"
              :class="reconcileForm.errors.manual_reference ? 'border-danger bg-danger/5' : 'border-gray-200'"
              placeholder="Optional"
            />
            <p v-if="reconcileForm.errors.manual_reference" class="text-danger text-xs mt-1">{{ reconcileForm.errors.manual_reference }}</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Admin Note</label>
            <textarea
              v-model="reconcileForm.manual_note"
              rows="3"
              class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary resize-y"
              :class="reconcileForm.errors.manual_note ? 'border-danger bg-danger/5' : 'border-gray-200'"
              placeholder="Example: Payment verified in Razorpay dashboard."
            ></textarea>
            <p v-if="reconcileForm.errors.manual_note" class="text-danger text-xs mt-1">{{ reconcileForm.errors.manual_note }}</p>
          </div>
        </div>

        <div class="flex gap-3 mt-6">
          <button @click="closeReconcile" type="button" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">
            Cancel
          </button>
          <button
            @click="submitReconcile"
            type="button"
            :disabled="reconcileForm.processing"
            class="flex-1 bg-success text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-green-700 transition-colors disabled:opacity-60"
          >
            {{ reconcileForm.processing ? 'Verifying…' : 'Verify Payment' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="downgradeTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Downgrade Payment</h3>
        <p class="text-text-muted text-sm mb-5">
          Downgrade NEO-{{ String(downgradeTarget.id).padStart(6, '0') }}. Paid payments move to refunded and linked olympiad access is cancelled. Pending payments move out of the pending queue.
        </p>

        <div>
          <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Admin Note</label>
          <textarea
            v-model="downgradeForm.manual_note"
            rows="3"
            class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary resize-y"
            :class="downgradeForm.errors.manual_note || downgradeForm.errors.payment ? 'border-danger bg-danger/5' : 'border-gray-200'"
            placeholder="Example: Refunded, testing user, or access withdrawn."
          ></textarea>
          <p v-if="downgradeForm.errors.manual_note" class="text-danger text-xs mt-1">{{ downgradeForm.errors.manual_note }}</p>
          <p v-if="downgradeForm.errors.payment" class="text-danger text-xs mt-1">{{ downgradeForm.errors.payment }}</p>
        </div>

        <div class="flex gap-3 mt-6">
          <button @click="closeDowngrade" type="button" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">
            Cancel
          </button>
          <button
            @click="submitDowngrade"
            type="button"
            :disabled="downgradeForm.processing"
            class="flex-1 bg-danger text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-red-700 transition-colors disabled:opacity-60"
          >
            {{ downgradeForm.processing ? 'Downgrading…' : 'Downgrade' }}
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  payments: Object,
  sources:  { type: Object, default: () => ({}) },
  filters:  Object,
  totals:   Object,
});

const filterForm = ref({
  search:    props.filters.search    || '',
  status:    props.filters.status    || '',
  source:    props.filters.source    || '',
  date_from: props.filters.date_from || '',
  date_to:   props.filters.date_to   || '',
});

// Which surface produced the payment. Rows predating source tracking read as portal.
const sourceClass = (key) => ({
  marketing:  'bg-accent/10 text-accent-dark',
  admin:      'bg-royal/10 text-royal',
  onboarding: 'bg-gold/10 text-gold-dark',
}[key] || 'bg-gray-100 text-text-muted');
const reconcileTarget = ref(null);
const reconcileForm = useForm({
  manual_reference: '',
  manual_note: '',
});
const downgradeTarget = ref(null);
const downgradeForm = useForm({
  manual_note: '',
});

const hasFilters = computed(() =>
  filterForm.value.search || filterForm.value.status || filterForm.value.source ||
  filterForm.value.date_from || filterForm.value.date_to
);

const applyFilters = () => {
  router.get(route('admin.payments'), filterForm.value, { preserveState: true, replace: true });
};

const clearFilters = () => {
  filterForm.value = { search: '', status: '', source: '', date_from: '', date_to: '' };
  applyFilters();
};

const openReconcile = (payment) => {
  reconcileTarget.value = payment;
  reconcileForm.reset();
  reconcileForm.clearErrors();
};

const closeReconcile = () => {
  reconcileTarget.value = null;
  reconcileForm.reset();
  reconcileForm.clearErrors();
};

const submitReconcile = () => {
  if (!reconcileTarget.value) return;

  reconcileForm.patch(route('admin.payments.reconcile', reconcileTarget.value.id), {
    preserveScroll: true,
    onSuccess: closeReconcile,
  });
};

const openDowngrade = (payment) => {
  downgradeTarget.value = payment;
  downgradeForm.reset();
  downgradeForm.clearErrors();
};

const closeDowngrade = () => {
  downgradeTarget.value = null;
  downgradeForm.reset();
  downgradeForm.clearErrors();
};

const submitDowngrade = () => {
  if (!downgradeTarget.value) return;

  downgradeForm.post(route('admin.payments.refund', downgradeTarget.value.id), {
    preserveScroll: true,
    onSuccess: closeDowngrade,
  });
};

const inr = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (d) =>
  new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

const badge = (status) => ({
  paid:     { label: 'Paid',     cls: 'bg-success/10 text-success', dot: 'bg-success' },
  created:  { label: 'Pending',  cls: 'bg-gold/10 text-gold',       dot: 'bg-gold' },
  failed:   { label: 'Failed',   cls: 'bg-danger/10 text-danger',   dot: 'bg-danger' },
  refunded: { label: 'Refunded', cls: 'bg-gray-100 text-text-muted',dot: 'bg-gray-400' },
}[status] || { label: status, cls: 'bg-gray-100 text-text-muted', dot: 'bg-gray-400' });
</script>
