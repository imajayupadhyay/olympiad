<template>
  <AdminLayout title="Users" subtitle="Create admin users and assign operational roles">
    <div v-if="$page.props.flash?.success" class="mb-5 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="mb-5 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.error }}
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-6">
      <div v-for="stat in statCards" :key="stat.label" class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4 min-w-0">
        <p class="text-[11px] font-semibold uppercase text-text-muted mb-2">{{ stat.label }}</p>
        <p class="font-number text-2xl font-bold" :class="stat.color">{{ stat.value }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" :class="canWrite ? 'xl:col-span-2' : 'xl:col-span-3'">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col lg:flex-row lg:items-end justify-between gap-4">
          <div>
            <h2 class="font-heading font-bold text-text-main text-base">Admin Users</h2>
            <p class="text-text-muted text-xs mt-0.5">{{ staffUsers.total.toLocaleString() }} records</p>
          </div>
          <form @submit.prevent="applyFilters" class="flex flex-wrap gap-2">
            <input v-model="filterForm.search" type="search" class="filter-control min-w-56" placeholder="Search name or email" />
            <select v-model="filterForm.role_id" class="filter-control min-w-40">
              <option value="">All roles</option>
              <option value="super">Super admin</option>
              <option v-for="role in roles" :key="role.id" :value="String(role.id)">{{ role.name }}</option>
            </select>
            <select v-model="filterForm.status" class="filter-control min-w-32">
              <option value="">Any status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-primary-light">Filter</button>
          </form>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[760px] text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="table-head">User</th>
                <th class="table-head">Role</th>
                <th class="table-head">Status</th>
                <th class="table-head">Updated</th>
                <th class="table-head text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="user in staffUsers.data" :key="user.id" class="hover:bg-gray-50/70">
                <td class="table-cell">
                  <p class="font-semibold text-text-main">{{ user.name }}</p>
                  <p class="text-xs text-text-muted">{{ user.email }}</p>
                </td>
                <td class="table-cell">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold" :class="user.is_super_admin ? 'bg-primary/10 text-primary' : 'bg-accent/10 text-accent'">
                    {{ user.is_super_admin ? 'Super Admin' : user.admin_role?.name }}
                  </span>
                </td>
                <td class="table-cell">
                  <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full" :class="user.is_active ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'">
                    <span class="w-1.5 h-1.5 rounded-full" :class="user.is_active ? 'bg-success' : 'bg-danger'"></span>
                    {{ user.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="table-cell text-xs text-text-muted font-number">{{ formatDate(user.updated_at) }}</td>
                <td class="table-cell">
                  <div v-if="!user.is_super_admin && (canWrite || canDelete)" class="flex justify-end gap-1">
                    <button v-if="canWrite" type="button" @click="startEdit(user)" class="action-link text-primary hover:bg-primary/5">Edit</button>
                    <button v-if="canWrite" type="button" @click="toggleUser(user)" class="action-link" :class="user.is_active ? 'text-amber-600 hover:bg-amber-50' : 'text-success hover:bg-success/5'">
                      {{ user.is_active ? 'Disable' : 'Enable' }}
                    </button>
                    <button v-if="canDelete" type="button" @click="deleteUser(user)" class="action-link text-danger hover:bg-danger/5">Delete</button>
                  </div>
                  <span v-else class="block text-right text-xs text-text-muted">Protected</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="staffUsers.links?.length > 3" class="px-5 py-4 border-t border-gray-100 flex flex-wrap gap-2">
          <Link
            v-for="link in staffUsers.links"
            :key="link.label"
            :href="link.url || '#'"
            preserve-scroll
            class="px-3 py-1.5 rounded-lg text-xs font-semibold border"
            :class="link.active ? 'bg-primary text-white border-primary' : 'bg-white text-text-muted border-gray-200 hover:bg-gray-50'"
            v-html="link.label"
          />
        </div>
      </section>

      <aside v-if="canWrite" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 h-fit xl:sticky xl:top-20">
        <h2 class="font-heading font-bold text-text-main text-base mb-1">{{ editTarget ? 'Edit User' : 'Create User' }}</h2>
        <p class="text-text-muted text-xs mb-5">Admin users must be assigned an active role.</p>

        <form @submit.prevent="submit" class="space-y-4">
          <label class="block">
            <span class="form-label">Name</span>
            <input v-model="form.name" type="text" class="form-control" />
            <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
          </label>
          <label class="block">
            <span class="form-label">Email</span>
            <input v-model="form.email" type="email" class="form-control" />
            <span v-if="form.errors.email" class="form-error">{{ form.errors.email }}</span>
          </label>
          <label class="block">
            <span class="form-label">Role</span>
            <select v-model="form.admin_role_id" class="form-control">
              <option value="">Select role</option>
              <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
            </select>
            <span v-if="form.errors.admin_role_id" class="form-error">{{ form.errors.admin_role_id }}</span>
          </label>
          <label class="block">
            <span class="form-label">{{ editTarget ? 'New Password' : 'Password' }}</span>
            <input v-model="form.password" type="password" class="form-control" :placeholder="editTarget ? 'Leave blank to keep current' : ''" />
            <span v-if="form.errors.password" class="form-error">{{ form.errors.password }}</span>
          </label>
          <label class="block">
            <span class="form-label">Confirm Password</span>
            <input v-model="form.password_confirmation" type="password" class="form-control" />
          </label>
          <div class="flex items-center justify-between rounded-xl bg-gray-50 border border-gray-100 px-3 py-3">
            <span class="text-sm font-medium text-text-main">Active account</span>
            <button type="button" class="relative w-11 h-6 rounded-full transition-colors" :class="form.is_active ? 'bg-success' : 'bg-gray-300'" @click="form.is_active = !form.is_active" :aria-pressed="form.is_active">
              <span class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="form.is_active ? 'translate-x-5' : ''"></span>
            </button>
          </div>
          <div class="flex gap-2">
            <button type="submit" :disabled="form.processing" class="flex-1 bg-primary text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60">
              {{ form.processing ? 'Saving...' : (editTarget ? 'Save Changes' : 'Create User') }}
            </button>
            <button v-if="editTarget" type="button" @click="resetForm" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-text-muted bg-gray-100 hover:bg-gray-200">
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
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  staffUsers: { type: Object, required: true },
  roles: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  summary: { type: Object, default: () => ({}) },
});

const editTarget = ref(null);
const page = usePage();
const canWrite = computed(() => !!page.props.admin_permissions?.staff_users?.write);
const canDelete = computed(() => !!page.props.admin_permissions?.staff_users?.delete);
const filterForm = ref({
  search: props.filters.search || '',
  role_id: props.filters.role_id || '',
  status: props.filters.status || '',
});

const form = useForm({
  name: '',
  email: '',
  admin_role_id: '',
  password: '',
  password_confirmation: '',
  is_active: true,
});

const statCards = computed(() => [
  { label: 'Total', value: (props.summary.total || 0).toLocaleString(), color: 'text-primary' },
  { label: 'Active', value: (props.summary.active || 0).toLocaleString(), color: 'text-success' },
  { label: 'Inactive', value: (props.summary.inactive || 0).toLocaleString(), color: 'text-danger' },
  { label: 'Super Admins', value: (props.summary.super || 0).toLocaleString(), color: 'text-accent' },
]);

const startEdit = (user) => {
  editTarget.value = user;
  form.clearErrors();
  form.name = user.name;
  form.email = user.email;
  form.admin_role_id = user.admin_role_id || '';
  form.password = '';
  form.password_confirmation = '';
  form.is_active = !!user.is_active;
};

const resetForm = () => {
  editTarget.value = null;
  form.clearErrors();
  form.reset();
  form.is_active = true;
};

const submit = () => {
  if (!canWrite.value) return;

  const options = {
    preserveScroll: true,
    onSuccess: () => resetForm(),
  };

  if (editTarget.value) {
    form.put(route('admin.staff-users.update', editTarget.value.id), options);
    return;
  }

  form.post(route('admin.staff-users.store'), options);
};

const toggleUser = (user) => {
  if (!canWrite.value) return;

  router.patch(route('admin.staff-users.toggle', user.id), {
    is_active: !user.is_active,
  }, { preserveScroll: true });
};

const deleteUser = (user) => {
  if (!canDelete.value) return;
  if (!window.confirm(`Delete admin user "${user.name}"?`)) return;
  router.delete(route('admin.staff-users.destroy', user.id), { preserveScroll: true });
};

const applyFilters = () => {
  router.get(route('admin.staff-users.index'), filterForm.value, {
    preserveState: true,
    replace: true,
  });
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

.filter-control,
.form-control {
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  background: #f9fafb;
  padding: 0.625rem 0.75rem;
  color: #0A1024;
  font-size: 0.8125rem;
}

.form-control {
  width: 100%;
  min-height: 2.625rem;
}

.filter-control:focus,
.form-control:focus {
  border-color: #131C3D;
  outline: none;
  box-shadow: 0 0 0 2px rgba(19, 28, 61, 0.1);
}

.form-label {
  display: block;
  margin-bottom: 0.375rem;
  color: #5B6373;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
}

.form-error {
  display: block;
  margin-top: 0.25rem;
  color: #DC2626;
  font-size: 0.75rem;
}
</style>
