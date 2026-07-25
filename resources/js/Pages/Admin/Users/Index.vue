<template>
  <AdminLayout title="Students" subtitle="Manage all registered student accounts">

    <!-- Top bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="bg-primary/10 text-primary text-sm font-semibold px-3 py-1.5 rounded-lg font-number">
          {{ totals.all.toLocaleString() }} Students
        </span>
        <span v-if="hasFilters" class="bg-accent/10 text-accent text-xs font-semibold px-2.5 py-1.5 rounded-lg">
          Filtered
        </span>
      </div>
      <Link
        :href="route('admin.users.create')"
        class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Add Student
      </Link>
    </div>

    <!-- Stats bar -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Total</p>
        <p class="font-number text-2xl font-bold text-primary">{{ totals.all.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Active</p>
        <p class="font-number text-2xl font-bold text-success">{{ totals.active.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Disabled</p>
        <p class="font-number text-2xl font-bold text-danger">{{ totals.inactive.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Joined Today</p>
        <p class="font-number text-2xl font-bold text-gold">{{ totals.today.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">From Marketing</p>
        <p class="font-number text-2xl font-bold text-accent">{{ (totals.marketing ?? 0).toLocaleString() }}</p>
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
            placeholder="Search name, email, school, city…"
            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 bg-gray-50"
            @keydown.enter="applyFilters"
          />
        </div>
        <select v-model="filterForm.class_level_id" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Classes</option>
          <option v-for="cl in classLevels" :key="cl.id" :value="cl.id">{{ cl.label }}</option>
        </select>
        <select v-model="filterForm.state" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All States</option>
          <option v-for="s in states" :key="s" :value="s">{{ s }}</option>
        </select>
        <select v-model="filterForm.status" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="inactive">Disabled</option>
        </select>
        <select v-model="filterForm.source" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Sources</option>
          <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>
      <div class="flex gap-2 mt-3">
        <button @click="applyFilters" class="bg-primary text-white px-4 py-2 rounded-lg text-xs font-semibold hover:bg-primary-light transition-colors">Apply Filters</button>
        <button v-if="hasFilters" @click="clearFilters" class="bg-gray-100 text-text-muted px-4 py-2 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors">Clear</button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

      <div v-if="students.data.length === 0" class="py-20 text-center">
        <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
          </svg>
        </div>
        <p class="font-heading font-bold text-text-main text-base mb-1">
          {{ hasFilters ? 'No students match your filters' : 'No students yet' }}
        </p>
        <p class="text-text-muted text-sm mb-5">
          {{ hasFilters ? 'Try adjusting your filters.' : 'Add your first student or wait for registrations.' }}
        </p>
        <Link v-if="!hasFilters" :href="route('admin.users.create')"
              class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
          Add Student
        </Link>
      </div>

      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider w-10">#</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Student</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Class</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">School / City</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">State</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Joined</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Status</th>
            <th class="text-right px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="(s, i) in students.data" :key="s.id" class="hover:bg-gray-50/50 transition-colors">
            <td class="px-5 py-3.5 text-text-muted font-number text-xs">{{ students.from + i }}</td>

            <td class="px-4 py-3.5">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center shrink-0 text-primary font-heading font-bold text-sm">
                  {{ initials(s.name) }}
                </div>
                <div class="min-w-0">
                  <p class="font-semibold text-text-main text-sm truncate">{{ s.name }}</p>
                  <p class="text-text-muted text-xs truncate">{{ s.email }}</p>
                </div>
              </div>
            </td>

            <td class="px-4 py-3.5">
              <span v-if="s.class_level" class="font-number text-sm text-text-main">{{ s.class_level.label }}</span>
              <span v-else class="text-text-muted text-xs">—</span>
            </td>

            <td class="px-4 py-3.5">
              <p class="text-text-main text-xs font-medium truncate max-w-[140px]">{{ s.school || '—' }}</p>
              <p class="text-text-muted text-xs">{{ s.city || '' }}</p>
            </td>

            <td class="px-4 py-3.5 text-text-main text-xs">{{ s.state || '—' }}</td>

            <td class="px-4 py-3.5 text-text-muted text-xs font-number whitespace-nowrap">
              {{ formatDate(s.created_at) }}
              <span class="block mt-1 font-body font-semibold px-1.5 py-0.5 rounded w-fit"
                    :class="sourceClass(s.registration_source)">{{ sourceLabel(s.registration_source) }}</span>
            </td>

            <td class="px-4 py-3.5">
              <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full"
                    :class="s.is_active ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'">
                <span class="w-1.5 h-1.5 rounded-full" :class="s.is_active ? 'bg-success' : 'bg-danger'"></span>
                {{ s.is_active ? 'Active' : 'Disabled' }}
              </span>
            </td>

            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1.5">
                <Link :href="route('admin.users.show', s.id)"
                      class="text-primary hover:text-primary-light text-xs font-semibold px-2 py-1 rounded-lg hover:bg-primary/5 transition-colors">
                  View
                </Link>
                <Link :href="route('admin.users.edit', s.id)"
                      class="text-text-muted hover:text-text-main text-xs font-semibold px-2 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                  Edit
                </Link>
                <button @click="openToggle(s)"
                        class="text-xs font-semibold px-2 py-1 rounded-lg transition-colors"
                        :class="s.is_active ? 'text-amber-600 hover:bg-amber-50' : 'text-success hover:bg-success/5'">
                  {{ s.is_active ? 'Disable' : 'Enable' }}
                </button>
                <button @click="openDelete(s)"
                        class="text-danger hover:text-red-700 text-xs font-semibold px-2 py-1 rounded-lg hover:bg-danger/5 transition-colors">
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="students.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-text-muted text-xs">Showing {{ students.from }}–{{ students.to }} of {{ students.total }} students</p>
        <div class="flex gap-1">
          <Link
            v-for="link in students.links"
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

    <!-- Toggle status modal -->
    <div v-if="toggleTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4"
             :class="toggleTarget.is_active ? 'bg-amber-50' : 'bg-success/10'">
          <svg class="w-6 h-6" :class="toggleTarget.is_active ? 'text-amber-500' : 'text-success'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path v-if="toggleTarget.is_active" stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">
          {{ toggleTarget.is_active ? 'Disable Account?' : 'Enable Account?' }}
        </h3>
        <p class="text-text-muted text-sm mb-5">
          {{ toggleTarget.is_active
            ? `${toggleTarget.name} won't be able to log in until re-enabled.`
            : `${toggleTarget.name} will be able to log in again.` }}
        </p>
        <div class="flex gap-3">
          <button @click="toggleTarget = null" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
          <button @click="confirmToggle"
                  class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white transition-colors"
                  :class="toggleTarget.is_active ? 'bg-amber-500 hover:bg-amber-600' : 'bg-success hover:bg-green-700'">
            {{ toggleTarget.is_active ? 'Disable' : 'Enable' }}
          </button>
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
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Delete Student?</h3>
        <p class="text-text-muted text-sm mb-1">
          <span class="font-semibold text-text-main">{{ deleteTarget.name }}</span>
        </p>
        <p class="text-text-muted text-xs mb-5">This will permanently delete their account and all associated data. This cannot be undone.</p>
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
  students:    Object,
  classLevels: Array,
  states:      Array,
  sources:     { type: Object, default: () => ({}) },
  filters:     Object,
  totals:      Object,
});

const filterForm = ref({
  search:         props.filters.search         || '',
  class_level_id: props.filters.class_level_id || '',
  state:          props.filters.state          || '',
  status:         props.filters.status         || '',
  source:         props.filters.source         || '',
});

const hasFilters = computed(() =>
  filterForm.value.search || filterForm.value.class_level_id ||
  filterForm.value.state  || filterForm.value.status || filterForm.value.source
);

const applyFilters = () => {
  router.get(route('admin.users.index'), filterForm.value, { preserveState: true, replace: true });
};

const clearFilters = () => {
  filterForm.value = { search: '', class_level_id: '', state: '', status: '', source: '' };
  applyFilters();
};

// Where the account came from. Accounts predating source tracking read as Website.
const sourceLabel = (key) => props.sources[key] || props.sources.website || 'Website';
const sourceClass = (key) => ({
  marketing: 'bg-accent/10 text-accent-dark',
  admin:     'bg-royal/10 text-royal',
}[key] || 'bg-gray-100 text-text-muted');

const initials = (name) =>
  name?.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() || '?';

const formatDate = (d) =>
  new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

// Toggle
const toggleTarget = ref(null);
const openToggle   = (s) => { toggleTarget.value = s; };
const confirmToggle = () => {
  router.patch(route('admin.users.toggle', toggleTarget.value.id), {
    is_active: !toggleTarget.value.is_active,
  }, { onFinish: () => { toggleTarget.value = null; } });
};

// Delete
const deleteTarget  = ref(null);
const openDelete    = (s) => { deleteTarget.value = s; };
const confirmDelete = () => {
  router.delete(route('admin.users.destroy', deleteTarget.value.id), {
    onFinish: () => { deleteTarget.value = null; },
  });
};
</script>
