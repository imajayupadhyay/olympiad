<template>
  <AdminLayout title="Referrals" subtitle="Track referrals and reward unlocks">

    <!-- Tab nav -->
    <div class="flex items-center gap-1 mb-6 bg-gray-100 p-1 rounded-xl w-fit">
      <Link :href="route('admin.referrals')" class="px-4 py-2 rounded-lg text-sm font-semibold bg-white text-primary shadow-sm">Overview</Link>
      <Link :href="route('admin.referrals.settings')" class="px-4 py-2 rounded-lg text-sm font-semibold text-text-muted hover:text-text-main transition-colors">Settings</Link>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Total Referrals</p>
        <p class="font-number text-2xl font-bold text-primary">{{ totals.all.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Qualified</p>
        <p class="font-number text-2xl font-bold text-success">{{ totals.qualified.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Rewarded</p>
        <p class="font-number text-2xl font-bold text-gold">{{ totals.rewarded.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Reward Discount</p>
        <p class="font-number text-2xl font-bold text-accent">{{ inr(totals.discount) }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="lg:col-span-2 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input v-model="filterForm.search" type="text" placeholder="Search referrer / referee / code…" @keydown.enter="applyFilters"
            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 bg-gray-50" />
        </div>
        <select v-model="filterForm.status" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="qualified">Qualified</option>
          <option value="rewarded">Rewarded</option>
        </select>
        <div class="flex gap-2">
          <button @click="applyFilters" class="bg-primary text-white px-4 py-2 rounded-lg text-xs font-semibold hover:bg-primary-light transition-colors">Apply</button>
          <button v-if="hasFilters" @click="clearFilters" class="bg-gray-100 text-text-muted px-4 py-2 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors">Clear</button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div v-if="referrals.data.length === 0" class="py-20 text-center">
        <p class="font-heading font-bold text-text-main text-base mb-1">{{ hasFilters ? 'No referrals match your filters' : 'No referrals yet' }}</p>
        <p class="text-text-muted text-sm">{{ hasFilters ? 'Try adjusting your filters.' : 'Referrals appear here once students share their links.' }}</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Referrer</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Referee</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Code</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Status</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Reward</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Date</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="r in referrals.data" :key="r.id" class="hover:bg-gray-50/50 transition-colors">
            <td class="px-5 py-3.5">
              <p class="font-semibold text-text-main">{{ r.referrer?.name ?? '—' }}</p>
              <p class="text-text-muted text-xs">{{ r.referrer?.email }}</p>
            </td>
            <td class="px-4 py-3.5">
              <p class="font-semibold text-text-main">{{ r.referee?.name ?? '—' }}</p>
              <p class="text-text-muted text-xs">{{ r.referee?.email }}</p>
            </td>
            <td class="px-4 py-3.5">
              <span class="font-number font-bold text-text-main bg-gray-100 px-2 py-1 rounded-lg tracking-wide">{{ r.code }}</span>
            </td>
            <td class="px-4 py-3.5">
              <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full" :class="badge(r.status).cls">
                <span class="w-1.5 h-1.5 rounded-full" :class="badge(r.status).dot"></span>{{ badge(r.status).label }}
              </span>
            </td>
            <td class="px-4 py-3.5 text-xs">
              <span v-if="r.reward_code" class="font-number font-semibold text-gold">{{ r.reward_code }}</span>
              <span v-else class="text-text-muted">—</span>
            </td>
            <td class="px-4 py-3.5 text-xs text-text-muted whitespace-nowrap">{{ r.created_at }}</td>
          </tr>
        </tbody>
      </table>

      <div v-if="referrals.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-text-muted text-xs">Showing {{ referrals.from }}–{{ referrals.to }} of {{ referrals.total }}</p>
        <div class="flex gap-1">
          <Link v-for="link in referrals.links" :key="link.label" :href="link.url || '#'"
            :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors', link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100', !link.url ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" preserve-scroll />
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  referrals: Object,
  filters:   Object,
  totals:    Object,
});

const filterForm = ref({
  search: props.filters.search || '',
  status: props.filters.status || '',
});
const hasFilters = computed(() => filterForm.value.search || filterForm.value.status);
const applyFilters = () => router.get(route('admin.referrals'), filterForm.value, { preserveState: true, replace: true });
const clearFilters = () => { filterForm.value = { search: '', status: '' }; applyFilters(); };

const inr = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });

const badge = (status) => ({
  pending:   { label: 'Pending',   cls: 'bg-gray-100 text-text-muted', dot: 'bg-gray-400' },
  qualified: { label: 'Qualified', cls: 'bg-success/10 text-success',  dot: 'bg-success' },
  rewarded:  { label: 'Rewarded',  cls: 'bg-gold/10 text-gold',        dot: 'bg-gold' },
}[status] || { label: status, cls: 'bg-gray-100 text-text-muted', dot: 'bg-gray-400' });
</script>
