<template>
  <AdminLayout title="Forms" subtitle="Contact form submissions from the website">

    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.success }}
    </div>

    <!-- Stats bar -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Total</p>
        <p class="font-number text-2xl font-bold text-primary">{{ stats.total.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">This Month</p>
        <p class="font-number text-2xl font-bold text-accent">{{ stats.month.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Today</p>
        <p class="font-number text-2xl font-bold text-gold">{{ stats.today.toLocaleString() }}</p>
      </div>
    </div>

    <!-- Filter -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5">
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            v-model="filterForm.search" type="text"
            placeholder="Search name, email, phone or message…"
            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 bg-gray-50"
            @keydown.enter="applyFilters"
          />
        </div>
        <div class="flex gap-2">
          <button @click="applyFilters" class="bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">Search</button>
          <button v-if="hasFilters" @click="clearFilters" class="bg-gray-100 text-text-muted px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Clear</button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div v-if="leads.data.length === 0" class="py-20 text-center">
        <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <p class="font-heading font-bold text-text-main text-base mb-1">
          {{ hasFilters ? 'No submissions match your search' : 'No submissions yet' }}
        </p>
        <p class="text-text-muted text-sm">
          {{ hasFilters ? 'Try a different search term.' : 'Contact form submissions from the website will appear here.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50">
            <tr class="text-text-muted text-xs font-semibold uppercase tracking-wider text-left">
              <th class="px-5 py-3">Name</th>
              <th class="px-5 py-3">Email</th>
              <th class="px-5 py-3">Phone</th>
              <th class="px-5 py-3">Message</th>
              <th class="px-5 py-3">Received</th>
              <th class="px-5 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="l in leads.data" :key="l.id" class="hover:bg-gray-50/50">
              <td class="px-5 py-3.5 font-semibold text-text-main">{{ l.name }}</td>
              <td class="px-5 py-3.5 text-text-muted">{{ l.email }}</td>
              <td class="px-5 py-3.5 text-text-muted">{{ l.phone || '—' }}</td>
              <td class="px-5 py-3.5 text-text-muted max-w-xs truncate">{{ l.message }}</td>
              <td class="px-5 py-3.5 text-text-muted text-xs whitespace-nowrap">{{ formatDate(l.created_at) }}</td>
              <td class="px-5 py-3.5 text-right whitespace-nowrap">
                <button @click="viewTarget = l" class="text-primary font-semibold hover:text-accent transition-colors">View</button>
                <button @click="deleteTarget = l" class="ml-3 text-danger font-semibold hover:text-red-700 transition-colors">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="leads.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-text-muted text-xs">Showing {{ leads.from }}–{{ leads.to }} of {{ leads.total }} submissions</p>
        <div class="flex gap-1">
          <Link
            v-for="link in leads.links"
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

    <!-- View modal -->
    <div v-if="viewTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)" @click.self="viewTarget = null">
      <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6">
        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="font-heading font-bold text-text-main text-lg">{{ viewTarget.name }}</h3>
            <p class="text-text-muted text-xs mt-0.5">Received {{ formatDate(viewTarget.created_at) }}</p>
          </div>
          <button @click="viewTarget = null" class="text-text-muted hover:text-text-main text-2xl leading-none">&times;</button>
        </div>
        <div class="space-y-3 text-sm">
          <div class="flex gap-2">
            <span class="text-text-muted w-16 shrink-0">Email</span>
            <a :href="`mailto:${viewTarget.email}`" class="text-primary font-medium hover:text-accent break-all">{{ viewTarget.email }}</a>
          </div>
          <div class="flex gap-2">
            <span class="text-text-muted w-16 shrink-0">Phone</span>
            <a v-if="viewTarget.phone" :href="`tel:${viewTarget.phone}`" class="text-text-main font-medium">{{ viewTarget.phone }}</a>
            <span v-else class="text-text-muted">—</span>
          </div>
          <div>
            <span class="text-text-muted block mb-1">Message</span>
            <p class="text-text-main leading-relaxed whitespace-pre-wrap bg-gray-50 rounded-xl p-3 border border-gray-100">{{ viewTarget.message }}</p>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="viewTarget = null" class="bg-gray-100 text-text-main px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Close</button>
          <a :href="`mailto:${viewTarget.email}`" class="bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">Reply by email</a>
        </div>
      </div>
    </div>

    <!-- Delete modal -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-danger/10 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Delete submission?</h3>
        <p class="text-text-muted text-sm mb-5">
          This will permanently delete the submission from <span class="font-semibold text-text-main">{{ deleteTarget.name }}</span>. This cannot be undone.
        </p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
          <button @click="confirmDelete" class="flex-1 bg-danger text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-red-700 transition-colors">Delete</button>
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
  leads:   Object,
  filters: Object,
  stats:   Object,
});

const filterForm = ref({ search: props.filters.search || '' });
const hasFilters = computed(() => !!filterForm.value.search);

const applyFilters = () => {
  router.get(route('admin.forms.index'), filterForm.value, { preserveState: true, replace: true });
};
const clearFilters = () => {
  filterForm.value = { search: '' };
  applyFilters();
};

const formatDate = (d) =>
  d ? new Date(d).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

const viewTarget = ref(null);
const deleteTarget = ref(null);
const confirmDelete = () => {
  router.delete(route('admin.forms.destroy', deleteTarget.value.id), {
    preserveScroll: true,
    onFinish: () => { deleteTarget.value = null; },
  });
};
</script>
