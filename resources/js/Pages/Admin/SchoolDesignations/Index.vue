<template>
  <AdminLayout title="School Designations" subtitle="Manage coordinator designation options for school records">
    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.error }}
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
      <div v-for="stat in statCards" :key="stat.label" class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4 min-w-0">
        <div class="flex items-center gap-2 mb-2">
          <span class="w-2 h-2 rounded-full shrink-0" :class="stat.dot"></span>
          <p class="text-[11px] font-semibold uppercase text-text-muted truncate">{{ stat.label }}</p>
        </div>
        <p class="font-number text-2xl font-bold truncate" :class="stat.color">{{ stat.value }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-6xl">
      <section class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
          <h2 class="font-heading font-bold text-text-main text-base">Available Designations</h2>
          <span class="text-xs text-text-muted font-number">{{ designations.length }} records</span>
        </div>

        <div v-if="designations.length === 0" class="py-16 px-5 text-center">
          <div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h10M7 12h10M7 17h6M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
            </svg>
          </div>
          <p class="font-heading font-bold text-text-main text-base mb-1">No designations yet</p>
          <p class="text-text-muted text-sm">Add the first designation to populate the school coordinator dropdown.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[720px] text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="table-head w-16">Order</th>
                <th class="table-head">Designation</th>
                <th class="table-head">Status</th>
                <th class="table-head">Updated</th>
                <th class="table-head text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="designation in designations" :key="designation.id" class="hover:bg-gray-50/70 transition-colors">
                <td class="table-cell font-number text-xs text-text-muted">{{ designation.sort_order }}</td>
                <td class="table-cell">
                  <p class="font-semibold text-text-main">{{ designation.name }}</p>
                </td>
                <td class="table-cell">
                  <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full" :class="designation.is_active ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'">
                    <span class="w-1.5 h-1.5 rounded-full" :class="designation.is_active ? 'bg-success' : 'bg-danger'"></span>
                    {{ designation.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="table-cell text-xs text-text-muted font-number">{{ formatDate(designation.updated_at) }}</td>
                <td class="table-cell">
                  <div class="flex items-center justify-end gap-1">
                    <button type="button" @click="startEdit(designation)" class="action-link text-primary hover:text-accent hover:bg-primary/5">Edit</button>
                    <button type="button" @click="toggleActive(designation)" class="action-link" :class="designation.is_active ? 'text-amber-600 hover:bg-amber-50' : 'text-success hover:bg-success/5'">
                      {{ designation.is_active ? 'Disable' : 'Enable' }}
                    </button>
                    <button type="button" @click="removeDesignation(designation)" class="action-link text-danger hover:text-red-700 hover:bg-danger/5">Remove</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <aside class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 h-fit lg:sticky lg:top-20">
        <h2 class="font-heading font-bold text-text-main text-base mb-1">
          {{ editTarget ? 'Edit Designation' : 'Add Designation' }}
        </h2>
        <p class="text-text-muted text-xs mb-5">
          Active designations appear in the School Management coordinator dropdown.
        </p>

        <form @submit.prevent="editTarget ? submitUpdate() : submitCreate()" class="space-y-4">
          <label class="block">
            <span class="form-label">Designation Name</span>
            <input v-model="currentForm.name" type="text" class="form-control" placeholder="e.g. Senior Coordinator" />
            <span v-if="currentForm.errors.name" class="form-error">{{ currentForm.errors.name }}</span>
          </label>

          <label v-if="editTarget" class="block">
            <span class="form-label">Display Order</span>
            <input v-model.number="editForm.sort_order" type="number" min="0" max="999" class="form-control font-number" />
            <span v-if="editForm.errors.sort_order" class="form-error">{{ editForm.errors.sort_order }}</span>
          </label>

          <div v-if="editTarget" class="flex items-center justify-between rounded-xl bg-gray-50 border border-gray-100 px-3 py-3">
            <span class="text-sm font-medium text-text-main">Available in dropdown</span>
            <button type="button" class="relative w-11 h-6 rounded-full transition-colors" :class="editForm.is_active ? 'bg-success' : 'bg-gray-300'" @click="editForm.is_active = !editForm.is_active" :aria-pressed="editForm.is_active">
              <span class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="editForm.is_active ? 'translate-x-5' : ''"></span>
            </button>
          </div>

          <div class="flex gap-2 pt-1">
            <button type="submit" :disabled="currentForm.processing" class="flex-1 bg-primary text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60">
              {{ currentForm.processing ? 'Saving...' : (editTarget ? 'Save Changes' : 'Add Designation') }}
            </button>
            <button v-if="editTarget" type="button" @click="cancelEdit" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-text-muted bg-gray-100 hover:bg-gray-200">
              Cancel
            </button>
          </div>
        </form>
      </aside>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  designations: Array,
  summary: Object,
});

const editTarget = ref(null);

const addForm = useForm({ name: '' });
const editForm = useForm({ name: '', is_active: true, sort_order: 0 });
const currentForm = computed(() => editTarget.value ? editForm : addForm);

const statCards = computed(() => [
  { label: 'Total', value: props.summary.total.toLocaleString(), color: 'text-primary', dot: 'bg-primary' },
  { label: 'Active', value: props.summary.active.toLocaleString(), color: 'text-success', dot: 'bg-success' },
  { label: 'Inactive', value: props.summary.inactive.toLocaleString(), color: 'text-danger', dot: 'bg-danger' },
]);

const startEdit = (designation) => {
  editTarget.value = designation;
  editForm.clearErrors();
  editForm.name = designation.name;
  editForm.is_active = !!designation.is_active;
  editForm.sort_order = designation.sort_order ?? 0;
};

const cancelEdit = () => {
  editTarget.value = null;
  editForm.reset();
  editForm.clearErrors();
};

const submitCreate = () => {
  addForm.post(route('admin.school-designations.store'), {
    preserveScroll: true,
    onSuccess: () => addForm.reset(),
  });
};

const submitUpdate = () => {
  editForm.put(route('admin.school-designations.update', editTarget.value.id), {
    preserveScroll: true,
    onSuccess: cancelEdit,
  });
};

const toggleActive = (designation) => {
  router.put(route('admin.school-designations.update', designation.id), {
    name: designation.name,
    is_active: !designation.is_active,
    sort_order: designation.sort_order ?? 0,
  }, { preserveScroll: true });
};

const removeDesignation = (designation) => {
  if (!window.confirm(`Remove "${designation.name}" from available school designations?`)) return;

  router.delete(route('admin.school-designations.destroy', designation.id), { preserveScroll: true });
};

const formatDate = value => value
  ? new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
  : '-';
</script>

<style scoped>
.table-head {
  padding: 0.75rem 1rem;
  color: #5B6373;
  font-size: 0.6875rem;
  font-weight: 600;
  text-align: left;
  text-transform: uppercase;
  white-space: nowrap;
}

.table-cell {
  padding: 1rem;
}

.action-link {
  padding: 0.25rem 0.5rem;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  transition: color 150ms ease, background-color 150ms ease;
}

.form-label {
  display: block;
  margin-bottom: 0.375rem;
  color: #5B6373;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
}

.form-control {
  width: 100%;
  min-height: 2.625rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  background: #f9fafb;
  padding: 0.625rem 0.75rem;
  color: #0A1024;
  font-size: 0.8125rem;
}

.form-control:focus {
  border-color: #131C3D;
  outline: none;
  box-shadow: 0 0 0 2px rgba(19, 28, 61, 0.1);
}

.form-error {
  display: block;
  margin-top: 0.25rem;
  color: #DC2626;
  font-size: 0.75rem;
}
</style>
