<template>
  <AdminLayout title="Support Tickets" subtitle="Student help requests and conversations">

    <!-- Flash -->
    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.error }}
    </div>

    <!-- Stats bar -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Open</p>
        <p class="font-number text-2xl font-bold text-accent">{{ stats.open.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Awaiting Reply</p>
        <p class="font-number text-2xl font-bold text-gold">{{ stats.awaiting_reply.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Resolved (mo)</p>
        <p class="font-number text-2xl font-bold text-success">{{ stats.resolved_month.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Total</p>
        <p class="font-number text-2xl font-bold text-primary">{{ stats.total.toLocaleString() }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="lg:col-span-2 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            v-model="filterForm.search" type="text"
            placeholder="Search subject, student name or email…"
            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 bg-gray-50"
            @keydown.enter="applyFilters"
          />
        </div>
        <select v-model="filterForm.status" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Status</option>
          <option value="open">Open</option>
          <option value="pending">Awaiting reply</option>
          <option value="answered">Answered</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
        <select v-model="filterForm.category" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Categories</option>
          <option value="payment">Payment</option>
          <option value="exam">Exam</option>
          <option value="technical">Technical</option>
          <option value="account">Account</option>
          <option value="other">Other</option>
        </select>
        <select v-model="filterForm.priority" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Priorities</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
      </div>
      <div class="flex gap-2 mt-3">
        <button @click="applyFilters" class="bg-primary text-white px-4 py-2 rounded-lg text-xs font-semibold hover:bg-primary-light transition-colors">Apply Filters</button>
        <button v-if="hasFilters" @click="clearFilters" class="bg-gray-100 text-text-muted px-4 py-2 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors">Clear</button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div v-if="tickets.data.length === 0" class="py-20 text-center">
        <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12a8 8 0 01-11.6 7.15L3 21l1.85-6.4A8 8 0 1121 12z"/>
          </svg>
        </div>
        <p class="font-heading font-bold text-text-main text-base mb-1">
          {{ hasFilters ? 'No tickets match your filters' : 'No support tickets yet' }}
        </p>
        <p class="text-text-muted text-sm">
          {{ hasFilters ? 'Try adjusting your filters.' : 'Student tickets will appear here.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50">
            <tr class="text-text-muted text-xs font-semibold uppercase tracking-wider text-left">
              <th class="px-5 py-3">Subject</th>
              <th class="px-5 py-3">Student</th>
              <th class="px-5 py-3">Category</th>
              <th class="px-5 py-3">Priority</th>
              <th class="px-5 py-3">Status</th>
              <th class="px-5 py-3">Updated</th>
              <th class="px-5 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-gray-50/50">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-2">
                  <span v-if="t.admin_unread > 0" class="w-2 h-2 rounded-full bg-accent shrink-0" title="New student message"></span>
                  <span class="font-semibold text-text-main">{{ t.subject }}</span>
                </div>
                <span class="text-text-muted text-xs">{{ t.messages_count }} message{{ t.messages_count === 1 ? '' : 's' }}</span>
              </td>
              <td class="px-5 py-3.5">
                <p class="text-text-main">{{ t.student?.name || '—' }}</p>
                <p class="text-text-muted text-xs">{{ t.student?.email }}</p>
              </td>
              <td class="px-5 py-3.5 capitalize text-text-muted">{{ t.category }}</td>
              <td class="px-5 py-3.5">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold capitalize" :class="priorityBadge(t.priority)">
                  {{ t.priority }}
                </span>
              </td>
              <td class="px-5 py-3.5">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold" :class="statusBadge(t.status)">
                  <span class="w-1.5 h-1.5 rounded-full" :class="statusDot(t.status)"></span>
                  {{ statusLabel(t.status) }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-text-muted text-xs">{{ formatDate(t.last_reply_at || t.created_at) }}</td>
              <td class="px-5 py-3.5 text-right">
                <Link :href="route('admin.support.show', t.id)" class="text-primary font-semibold hover:text-accent transition-colors">Open</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="tickets.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-text-muted text-xs">Showing {{ tickets.from }}–{{ tickets.to }} of {{ tickets.total }} tickets</p>
        <div class="flex gap-1">
          <Link
            v-for="link in tickets.links"
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
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  tickets: Object,
  filters: Object,
  stats:   Object,
});

const filterForm = ref({
  search:   props.filters.search   || '',
  status:   props.filters.status   || '',
  category: props.filters.category || '',
  priority: props.filters.priority || '',
});

const hasFilters = computed(() =>
  filterForm.value.search || filterForm.value.status ||
  filterForm.value.category || filterForm.value.priority
);

const applyFilters = () => {
  router.get(route('admin.support.index'), filterForm.value, { preserveState: true, replace: true });
};
const clearFilters = () => {
  filterForm.value = { search: '', status: '', category: '', priority: '' };
  applyFilters();
};

const formatDate = (d) =>
  d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const statusLabel = (s) => ({ open: 'Open', pending: 'Awaiting reply', answered: 'Answered', resolved: 'Resolved', closed: 'Closed' }[s] || s);
const statusBadge = (s) => ({
  open:     'bg-accent/10 text-accent',
  pending:  'bg-gold/10 text-gold-dark',
  answered: 'bg-royal/10 text-royal',
  resolved: 'bg-success/10 text-success',
  closed:   'bg-gray-100 text-text-muted',
}[s] || 'bg-gray-100 text-text-muted');
const statusDot = (s) => ({
  open:     'bg-accent',
  pending:  'bg-gold',
  answered: 'bg-royal',
  resolved: 'bg-success',
  closed:   'bg-gray-400',
}[s] || 'bg-gray-400');
const priorityBadge = (p) => ({
  high:   'bg-danger/10 text-danger',
  medium: 'bg-royal/10 text-royal',
  low:    'bg-gray-100 text-text-muted',
}[p] || 'bg-gray-100 text-text-muted');
</script>
