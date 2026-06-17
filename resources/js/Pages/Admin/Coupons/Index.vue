<template>
  <AdminLayout title="Coupons" subtitle="Create discount codes and track redemptions">

    <!-- Top bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="bg-primary/10 text-primary text-sm font-semibold px-3 py-1.5 rounded-lg font-number">
          {{ totals.all.toLocaleString() }} Coupons
        </span>
        <span v-if="hasFilters" class="bg-accent/10 text-accent text-xs font-semibold px-2.5 py-1.5 rounded-lg">Filtered</span>
      </div>
      <button @click="openCreate"
        class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Create Coupon
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Total</p>
        <p class="font-number text-2xl font-bold text-primary">{{ totals.all.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Active</p>
        <p class="font-number text-2xl font-bold text-success">{{ totals.active.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Redemptions</p>
        <p class="font-number text-2xl font-bold text-text-main">{{ totals.redemptions.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Discount Given</p>
        <p class="font-number text-2xl font-bold text-gold">{{ inr(totals.discount) }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="lg:col-span-2 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input v-model="filterForm.search" type="text" placeholder="Search code…" @keydown.enter="applyFilters"
            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 bg-gray-50 uppercase placeholder:normal-case" />
        </div>
        <select v-model="filterForm.type" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Types</option>
          <option value="percentage">Percentage</option>
          <option value="fixed">Fixed</option>
        </select>
        <select v-model="filterForm.status" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="expired">Expired</option>
          <option value="disabled">Disabled</option>
        </select>
      </div>
      <div class="flex gap-2 mt-3">
        <button @click="applyFilters" class="bg-primary text-white px-4 py-2 rounded-lg text-xs font-semibold hover:bg-primary-light transition-colors">Apply Filters</button>
        <button v-if="hasFilters" @click="clearFilters" class="bg-gray-100 text-text-muted px-4 py-2 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors">Clear</button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div v-if="coupons.data.length === 0" class="py-20 text-center">
        <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        </div>
        <p class="font-heading font-bold text-text-main text-base mb-1">{{ hasFilters ? 'No coupons match your filters' : 'No coupons yet' }}</p>
        <p class="text-text-muted text-sm mb-5">{{ hasFilters ? 'Try adjusting your filters.' : 'Create your first discount code.' }}</p>
        <button v-if="!hasFilters" @click="openCreate" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          Create Coupon
        </button>
      </div>

      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Code</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Discount</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Conditions</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Usage</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Validity</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Status</th>
            <th class="text-right px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="c in coupons.data" :key="c.id" class="hover:bg-gray-50/50 transition-colors">
            <td class="px-5 py-3.5">
              <button @click="copyCode(c.code)" class="group inline-flex items-center gap-1.5">
                <span class="font-number font-bold text-text-main tracking-wide bg-gray-100 group-hover:bg-primary/10 px-2 py-1 rounded-lg transition-colors">{{ c.code }}</span>
                <svg class="w-3.5 h-3.5 text-text-muted opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
              </button>
              <p v-if="c.description" class="text-text-muted text-xs mt-1 max-w-[160px] truncate">{{ c.description }}</p>
            </td>

            <td class="px-4 py-3.5">
              <span class="font-number font-bold text-accent">{{ c.type === 'percentage' ? c.value + '%' : inr(c.value) }}</span>
              <span class="block text-text-muted text-xs">{{ c.type === 'percentage' ? 'percentage off' : 'flat off' }}</span>
              <span v-if="c.type === 'percentage' && c.max_discount" class="block text-text-muted text-[11px]">up to {{ inr(c.max_discount) }}</span>
            </td>

            <td class="px-4 py-3.5 text-xs text-text-muted">
              <span v-if="c.min_order_amount > 0">Min {{ inr(c.min_order_amount) }}</span>
              <span v-else>—</span>
            </td>

            <td class="px-4 py-3.5">
              <p class="font-number text-sm text-text-main">{{ c.used_count }}<span class="text-text-muted">/{{ c.usage_limit ?? '∞' }}</span></p>
              <p class="text-text-muted text-[11px]">{{ c.usage_limit_per_user ? c.usage_limit_per_user + ' / user' : 'unlimited / user' }}</p>
            </td>

            <td class="px-4 py-3.5 text-xs text-text-muted whitespace-nowrap">
              <span v-if="c.expires_at">till {{ c.expires_at }}</span>
              <span v-else>no expiry</span>
            </td>

            <td class="px-4 py-3.5">
              <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full" :class="badge(c.status).cls">
                <span class="w-1.5 h-1.5 rounded-full" :class="badge(c.status).dot"></span>{{ badge(c.status).label }}
              </span>
            </td>

            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1.5">
                <button @click="openEdit(c)" class="text-text-muted hover:text-text-main text-xs font-semibold px-2 py-1 rounded-lg hover:bg-gray-100 transition-colors">Edit</button>
                <button @click="confirmToggle(c)" class="text-xs font-semibold px-2 py-1 rounded-lg transition-colors" :class="c.is_active ? 'text-amber-600 hover:bg-amber-50' : 'text-success hover:bg-success/5'">{{ c.is_active ? 'Disable' : 'Enable' }}</button>
                <button @click="deleteTarget = c" class="text-danger hover:text-red-700 text-xs font-semibold px-2 py-1 rounded-lg hover:bg-danger/5 transition-colors">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="coupons.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-text-muted text-xs">Showing {{ coupons.from }}–{{ coupons.to }} of {{ coupons.total }}</p>
        <div class="flex gap-1">
          <Link v-for="link in coupons.links" :key="link.label" :href="link.url || '#'"
            :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors', link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100', !link.url ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" preserve-scroll />
        </div>
      </div>
    </div>

    <!-- Create / Edit modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-auto" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full my-8">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-heading font-bold text-text-main text-lg">{{ form.id ? 'Edit Coupon' : 'Create Coupon' }}</h3>
          <button @click="showForm = false" class="text-text-muted hover:text-text-main"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>

        <form @submit.prevent="submit" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-text-muted mb-1.5">Coupon Code</label>
            <div class="flex gap-2">
              <input v-model="form.code" type="text" placeholder="WELCOME20"
                class="flex-1 px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number uppercase focus:outline-none focus:border-primary bg-gray-50 tracking-wide" />
              <button type="button" @click="generateCode" class="px-3 py-2.5 bg-gray-100 text-text-muted rounded-xl text-xs font-semibold hover:bg-gray-200 transition-colors whitespace-nowrap">Generate</button>
            </div>
            <p v-if="form.errors.code" class="text-danger text-xs mt-1">{{ form.errors.code }}</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-text-muted mb-1.5">Description <span class="font-normal">(optional)</span></label>
            <input v-model="form.description" type="text" placeholder="New-year launch offer"
              class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-text-muted mb-1.5">Type</label>
              <select v-model="form.type" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed (₹)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-text-muted mb-1.5">{{ form.type === 'percentage' ? 'Discount %' : 'Discount ₹' }}</label>
              <input v-model="form.value" type="number" step="0.01" min="0.01" :max="form.type === 'percentage' ? 100 : null"
                class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
              <p v-if="form.errors.value" class="text-danger text-xs mt-1">{{ form.errors.value }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div v-if="form.type === 'percentage'">
              <label class="block text-xs font-semibold text-text-muted mb-1.5">Max discount cap ₹ <span class="font-normal">(optional)</span></label>
              <input v-model="form.max_discount" type="number" step="0.01" min="1" placeholder="e.g. 200"
                class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
            </div>
            <div :class="form.type === 'percentage' ? '' : 'col-span-2'">
              <label class="block text-xs font-semibold text-text-muted mb-1.5">Min order ₹ <span class="font-normal">(optional)</span></label>
              <input v-model="form.min_order_amount" type="number" step="0.01" min="0" placeholder="0"
                class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-text-muted mb-1.5">Total uses <span class="font-normal">(blank = ∞)</span></label>
              <input v-model="form.usage_limit" type="number" min="1" placeholder="unlimited"
                class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-text-muted mb-1.5">Uses per student</label>
              <input v-model="form.usage_limit_per_user" type="number" min="1" placeholder="1"
                class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-text-muted mb-1.5">Starts <span class="font-normal">(optional)</span></label>
              <input v-model="form.starts_at" type="date" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-text-muted mb-1.5">Expires <span class="font-normal">(optional)</span></label>
              <input v-model="form.expires_at" type="date" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main" />
              <p v-if="form.errors.expires_at" class="text-danger text-xs mt-1">{{ form.errors.expires_at }}</p>
            </div>
          </div>

          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-accent focus:ring-accent" />
            <span class="text-sm text-text-main font-medium">Active</span>
          </label>

          <div class="flex gap-3 pt-2">
            <button type="button" @click="showForm = false" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
            <button type="submit" :disabled="form.processing" class="flex-1 bg-primary text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60">
              {{ form.processing ? 'Saving…' : (form.id ? 'Save Changes' : 'Create Coupon') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete modal -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-danger/10 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Delete Coupon?</h3>
        <p class="text-text-muted text-sm mb-5"><span class="font-number font-semibold text-text-main">{{ deleteTarget.code }}</span> will be removed. Past redemptions are kept for records.</p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
          <button @click="doDelete" class="flex-1 bg-danger text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-red-700 transition-colors">Delete</button>
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
  coupons: Object,
  filters: Object,
  totals:  Object,
});

const filterForm = ref({
  search: props.filters.search || '',
  type:   props.filters.type   || '',
  status: props.filters.status || '',
});
const hasFilters = computed(() => filterForm.value.search || filterForm.value.type || filterForm.value.status);
const applyFilters = () => router.get(route('admin.coupons'), filterForm.value, { preserveState: true, replace: true });
const clearFilters = () => { filterForm.value = { search: '', type: '', status: '' }; applyFilters(); };

const inr = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });

const badge = (status) => ({
  active:    { label: 'Active',    cls: 'bg-success/10 text-success',  dot: 'bg-success' },
  scheduled: { label: 'Scheduled', cls: 'bg-royal/10 text-royal',      dot: 'bg-royal' },
  expired:   { label: 'Expired',   cls: 'bg-danger/10 text-danger',    dot: 'bg-danger' },
  exhausted: { label: 'Used up',   cls: 'bg-gold/10 text-gold',        dot: 'bg-gold' },
  disabled:  { label: 'Disabled',  cls: 'bg-gray-100 text-text-muted', dot: 'bg-gray-400' },
}[status] || { label: status, cls: 'bg-gray-100 text-text-muted', dot: 'bg-gray-400' });

const copyCode = (code) => navigator.clipboard?.writeText(code);

// ── Create / Edit ──
const showForm = ref(false);
const form = useForm({
  id: null, code: '', description: '', type: 'percentage', value: '',
  max_discount: '', min_order_amount: '', usage_limit: '', usage_limit_per_user: 1,
  starts_at: '', expires_at: '', is_active: true,
});

const blankForm = () => {
  form.id = null; form.code = ''; form.description = ''; form.type = 'percentage'; form.value = '';
  form.max_discount = ''; form.min_order_amount = ''; form.usage_limit = ''; form.usage_limit_per_user = 1;
  form.starts_at = ''; form.expires_at = ''; form.is_active = true; form.clearErrors();
};

const openCreate = () => { blankForm(); showForm.value = true; };
const openEdit = (c) => {
  form.id = c.id; form.code = c.code; form.description = c.description || ''; form.type = c.type;
  form.value = c.value; form.max_discount = c.max_discount ?? ''; form.min_order_amount = c.min_order_amount || '';
  form.usage_limit = c.usage_limit ?? ''; form.usage_limit_per_user = c.usage_limit_per_user ?? '';
  form.starts_at = c.starts_at || ''; form.expires_at = c.expires_at || ''; form.is_active = c.is_active;
  form.clearErrors(); showForm.value = true;
};

const generateCode = () => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  form.code = Array.from({ length: 8 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
};

const submit = () => {
  const opts = { preserveScroll: true, onSuccess: () => { showForm.value = false; } };
  if (form.id) form.put(route('admin.coupons.update', form.id), opts);
  else form.post(route('admin.coupons.store'), opts);
};

// ── Toggle / Delete ──
const confirmToggle = (c) => router.patch(route('admin.coupons.toggle', c.id), { is_active: !c.is_active }, { preserveScroll: true });

const deleteTarget = ref(null);
const doDelete = () => router.delete(route('admin.coupons.destroy', deleteTarget.value.id), {
  preserveScroll: true, onFinish: () => { deleteTarget.value = null; },
});
</script>
