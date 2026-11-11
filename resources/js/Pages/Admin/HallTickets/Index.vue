<template>
  <AdminLayout title="Hall Tickets" subtitle="Verify student details and documents, approve hall tickets and print them">
    <div v-if="flash.success" class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">{{ flash.error }}</div>

    <!-- stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <button v-for="tile in tiles" :key="tile.key" type="button" @click="go({ status: tile.status })"
              class="text-left bg-white rounded-2xl border shadow-sm p-4 transition-colors"
              :class="filters.status === tile.status ? 'border-primary' : 'border-gray-100 hover:border-primary/30'">
        <div class="flex items-center justify-between mb-1.5">
          <p class="text-text-muted text-[11px] font-semibold uppercase tracking-wider">{{ tile.label }}</p>
          <span class="w-7 h-7 rounded-lg grid place-items-center" :class="tile.bg">
            <svg class="w-4 h-4" :class="tile.cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="tile.icon"/></svg>
          </span>
        </div>
        <p class="font-number font-bold text-2xl" :class="tile.cls">{{ (stats[tile.key] ?? 0).toLocaleString() }}</p>
      </button>
    </div>

    <!-- filters -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 mb-4 space-y-3">
      <div class="flex flex-wrap gap-1" role="tablist" aria-label="Status">
        <button v-for="t in statusTabs" :key="t.key" type="button" role="tab" :aria-selected="filters.status === t.key" @click="go({ status: t.key })"
                class="px-3 py-2 rounded-xl text-sm font-semibold transition-colors"
                :class="filters.status === t.key ? 'bg-primary text-white' : 'text-text-main hover:bg-gray-100'">
          {{ t.label }} <span class="font-number text-xs opacity-70 ml-0.5">{{ t.count }}</span>
        </button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
        <select v-model="classFilter" class="field" aria-label="Class" @change="go({ class_level_id: classFilter })">
          <option value="">All classes</option>
          <option v-for="cl in classLevels" :key="cl.id" :value="String(cl.id)">{{ cl.label }}</option>
        </select>
        <input v-model="searchText" type="search" class="field md:col-span-3" placeholder="Search name, email, phone, school or roll number"
               @keydown.enter="go({ search: searchText })" @search="go({ search: searchText })" />
      </div>
    </div>

    <!-- list -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div v-if="!tickets.data.length" class="py-16 text-center px-4">
        <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-bg grid place-items-center text-accent">
          <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
        </div>
        <p class="font-heading font-bold text-text-main">No hall ticket requests{{ filters.status ? ` (${statusLabel(filters.status)})` : '' }}</p>
        <p class="text-text-muted text-sm mt-1">Students appear here once they complete at least 90% of their profile and submit it for verification.</p>
      </div>

      <template v-else>
        <!-- desktop table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-sm min-w-[860px]">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="th">Student</th>
                <th class="th">School</th>
                <th class="th">Submitted</th>
                <th class="th">Status</th>
                <th class="th">Roll no.</th>
                <th class="th text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-gray-50/50">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3 min-w-0">
                    <img :src="t.student.photo_url" alt="" class="w-10 h-12 rounded-lg object-cover bg-gray-100 border border-gray-200 shrink-0" loading="lazy" @error="(e) => (e.target.style.visibility = 'hidden')" />
                    <div class="min-w-0">
                      <Link :href="route('admin.hall-tickets.show', t.id)" class="font-semibold text-text-main hover:text-primary truncate block max-w-[220px]">{{ t.student.name }}</Link>
                      <p class="text-[11px] text-text-muted">{{ t.student.class || '—' }}</p>
                      <p class="text-[11px] text-text-muted font-number truncate max-w-[220px]">{{ t.student.phone || t.student.email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 max-w-[240px]">
                  <p class="text-text-main truncate">{{ t.student.school || '—' }}</p>
                  <p class="text-[11px] text-text-muted truncate">{{ t.student.city }}</p>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <p class="text-text-main">{{ t.submitted_at }}</p>
                  <p v-if="t.submission_count > 1" class="text-[11px] text-royal">Resubmitted ×{{ t.submission_count - 1 }}</p>
                </td>
                <td class="px-4 py-3">
                  <span class="pill" :class="pillCls(t.status)">{{ t.status_label }}</span>
                  <p v-if="t.reviewer" class="text-[11px] text-text-muted mt-1">by {{ t.reviewer }} · {{ t.reviewed_at }}</p>
                </td>
                <td class="px-4 py-3 font-number font-semibold text-text-main whitespace-nowrap">{{ t.roll_number || '—' }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                  <a v-if="t.status === 'approved'" :href="route('admin.hall-tickets.print', t.id)" class="act text-text-main mr-1">Print</a>
                  <Link :href="route('admin.hall-tickets.show', t.id)" class="act-primary">{{ t.status === 'pending' ? 'Review' : 'View' }}</Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- mobile cards -->
        <div class="md:hidden divide-y divide-gray-100">
          <Link v-for="t in tickets.data" :key="t.id" :href="route('admin.hall-tickets.show', t.id)" class="flex gap-3 p-4 hover:bg-gray-50">
            <img :src="t.student.photo_url" alt="" class="w-11 h-14 rounded-lg object-cover bg-gray-100 border border-gray-200 shrink-0" loading="lazy" @error="(e) => (e.target.style.visibility = 'hidden')" />
            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-2">
                <p class="font-semibold text-text-main truncate">{{ t.student.name }}</p>
                <span class="pill shrink-0" :class="pillCls(t.status)">{{ t.status_label }}</span>
              </div>
              <p class="text-xs text-text-muted truncate">{{ t.student.class }} · {{ t.student.school || 'No school' }}</p>
              <p class="text-[11px] text-text-muted mt-0.5">Submitted {{ t.submitted_at }}<template v-if="t.roll_number"> · <span class="font-number">{{ t.roll_number }}</span></template></p>
            </div>
          </Link>
        </div>
      </template>

      <!-- pagination -->
      <div v-if="tickets.last_page > 1" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-t border-gray-100 text-xs text-text-muted">
        <span>Showing {{ tickets.from }}–{{ tickets.to }} of {{ tickets.total }}</span>
        <div class="flex flex-wrap gap-1">
          <template v-for="(l, i) in tickets.links" :key="i">
            <Link v-if="l.url" :href="l.url" preserve-scroll class="px-2.5 py-1.5 rounded-lg font-semibold" :class="l.active ? 'bg-primary text-white' : 'hover:bg-gray-100 text-text-main'" v-html="l.label" />
            <span v-else class="px-2.5 py-1.5 text-gray-300" v-html="l.label" />
          </template>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
  tickets: { type: Object, required: true },
  stats: { type: Object, required: true },
  filters: { type: Object, required: true },
  classLevels: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const classFilter = ref(props.filters.class_level_id ?? '');
const searchText = ref(props.filters.search ?? '');

const LABELS = { pending: 'Awaiting verification', approved: 'Approved', rejected: 'Rejected' };
const statusLabel = (s) => LABELS[s] ?? s;
const pillCls = (s) => ({ pending: 'bg-amber-100 text-amber-800', approved: 'bg-green-100 text-success', rejected: 'bg-red-100 text-danger' }[s] ?? 'bg-gray-100 text-text-muted');

const tiles = [
  { key: 'pending', status: 'pending', label: 'Awaiting review', cls: 'text-amber-700', bg: 'bg-amber-50', icon: 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z' },
  { key: 'approved', status: 'approved', label: 'Approved', cls: 'text-success', bg: 'bg-green-50', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
  { key: 'rejected', status: 'rejected', label: 'Rejected', cls: 'text-danger', bg: 'bg-red-50', icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
  { key: 'total', status: '', label: 'All requests', cls: 'text-text-main', bg: 'bg-gray-100', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
];

const statusTabs = computed(() => [
  { key: '', label: 'All', count: props.stats.total },
  { key: 'pending', label: 'Awaiting review', count: props.stats.pending },
  { key: 'approved', label: 'Approved', count: props.stats.approved },
  { key: 'rejected', label: 'Rejected', count: props.stats.rejected },
]);

const go = (changes) => {
  const query = { ...props.filters, ...changes };
  Object.keys(query).forEach((k) => { if (query[k] === '' || query[k] === null) delete query[k]; });
  router.get(route('admin.hall-tickets.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<style scoped>
.th { @apply text-left px-4 py-3 text-text-muted text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main; }
.act { @apply text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-gray-100 transition-colors; }
.act-primary { @apply text-xs font-semibold px-3 py-1.5 rounded-lg bg-primary text-white hover:bg-primary-light transition-colors; }
.pill { @apply inline-block text-[11px] font-bold px-2 py-1 rounded-md whitespace-nowrap; }
</style>
