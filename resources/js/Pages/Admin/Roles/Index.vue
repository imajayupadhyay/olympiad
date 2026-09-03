<template>
  <AdminLayout title="Permission Management" subtitle="Create admin roles and control module access">
    <div v-if="$page.props.flash?.success" class="mb-5 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="mb-5 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.error }}
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
      <section class="xl:col-span-4 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="font-heading font-bold text-text-main text-base">Roles</h2>
          <span class="text-xs text-text-muted font-number">{{ roles.length }} records</span>
        </div>

        <div v-if="roles.length === 0" class="p-8 text-center">
          <p class="font-heading font-bold text-text-main text-base mb-1">No roles yet</p>
          <p class="text-text-muted text-sm">Create a role to start assigning permissions.</p>
        </div>

        <div v-else class="divide-y divide-gray-100">
          <button
            v-for="role in roles"
            :key="role.id"
            type="button"
            @click="selectRole(role)"
            class="w-full text-left px-5 py-4 hover:bg-gray-50 transition-colors"
            :class="editTarget?.id === role.id ? 'bg-primary/5' : ''"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold text-text-main truncate">{{ role.name }}</p>
                <p class="text-xs text-text-muted truncate">{{ role.description || 'No description' }}</p>
              </div>
              <span class="shrink-0 text-[11px] font-semibold px-2 py-1 rounded-full" :class="role.is_active ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'">
                {{ role.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
            <div class="flex items-center gap-3 mt-3 text-[11px] text-text-muted">
              <span class="font-number">{{ role.users_count }} users</span>
              <span class="font-number">{{ allowedCount(role.permissions) }} modules</span>
              <span>{{ role.slug }}</span>
            </div>
          </button>
        </div>
      </section>

      <section class="xl:col-span-8 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h2 class="font-heading font-bold text-text-main text-base">{{ editTarget ? 'Edit Role' : 'Create Role' }}</h2>
            <p class="text-text-muted text-xs mt-0.5">Write or delete automatically keeps read enabled for that module.</p>
          </div>
          <button v-if="editTarget && canWrite" type="button" @click="resetForm" class="text-sm font-semibold text-primary hover:text-accent">
            New Role
          </button>
        </div>

        <form @submit.prevent="submit" class="p-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
            <label class="block">
              <span class="form-label">Role Name</span>
              <input v-model="form.name" type="text" class="form-control" placeholder="Data Entry Operator" :disabled="!canWrite" />
              <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
            </label>
            <label class="block">
              <span class="form-label">Status</span>
              <select v-model="form.is_active" class="form-control" :disabled="!canWrite">
                <option :value="true">Active</option>
                <option :value="false">Inactive</option>
              </select>
            </label>
            <label class="block md:col-span-2">
              <span class="form-label">Description</span>
              <input v-model="form.description" type="text" class="form-control" placeholder="Short internal note for this role" :disabled="!canWrite" />
              <span v-if="form.errors.description" class="form-error">{{ form.errors.description }}</span>
            </label>
          </div>

          <div class="overflow-x-auto border border-gray-100 rounded-2xl">
            <table class="w-full min-w-[760px] text-sm">
              <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                  <th class="table-head">Module</th>
                  <th v-for="action in actions" :key="action" class="table-head text-center w-28">{{ action }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <template v-for="section in sections" :key="section">
                  <tr class="bg-card/70">
                    <td colspan="4" class="px-4 py-2 text-[11px] font-bold uppercase text-text-muted tracking-wider">{{ section }}</td>
                  </tr>
                  <tr v-for="module in modulesBySection(section)" :key="module.key" class="hover:bg-gray-50/70">
                    <td class="table-cell">
                      <p class="font-semibold text-text-main">{{ module.label }}</p>
                      <p class="text-[11px] text-text-muted">{{ module.key }}</p>
                    </td>
                    <td v-for="action in actions" :key="`${module.key}-${action}`" class="table-cell text-center">
                      <input
                        v-model="form.permissions[module.key][action]"
                        type="checkbox"
                        class="permission-check"
                        @change="normalizeAction(module.key, action)"
                        :disabled="!canWrite"
                        :aria-label="`${module.label} ${action}`"
                      />
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-5">
            <button
              v-if="editTarget && !editTarget.is_system && canDelete"
              type="button"
              @click="deleteRole"
              class="px-4 py-2.5 rounded-xl text-sm font-semibold text-danger bg-danger/10 hover:bg-danger/15 disabled:opacity-50"
              :disabled="editTarget.users_count > 0"
            >
              Delete Role
            </button>
            <span v-else></span>
            <button v-if="canWrite" type="submit" :disabled="form.processing" class="bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60">
              {{ form.processing ? 'Saving...' : (editTarget ? 'Save Role' : 'Create Role') }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  roles: { type: Array, default: () => [] },
  modules: { type: Array, default: () => [] },
  actions: { type: Array, default: () => ['read', 'write', 'delete'] },
  emptyPermissions: { type: Object, default: () => ({}) },
});

const clonePermissions = (permissions) => JSON.parse(JSON.stringify(permissions || {}));
const editTarget = ref(null);
const page = usePage();
const canWrite = computed(() => !!page.props.admin_permissions?.roles?.write);
const canDelete = computed(() => !!page.props.admin_permissions?.roles?.delete);

const form = useForm({
  name: '',
  description: '',
  is_active: true,
  permissions: clonePermissions(props.emptyPermissions),
});

const sections = computed(() => [...new Set(props.modules.map(module => module.section))]);
const modulesBySection = section => props.modules.filter(module => module.section === section);
const allowedCount = permissions => Object.values(permissions || {}).filter(row => row.read).length;

const selectRole = (role) => {
  editTarget.value = role;
  form.clearErrors();
  form.name = role.name;
  form.description = role.description || '';
  form.is_active = !!role.is_active;
  form.permissions = clonePermissions(role.permissions);
};

const resetForm = () => {
  editTarget.value = null;
  form.clearErrors();
  form.reset();
  form.permissions = clonePermissions(props.emptyPermissions);
};

const normalizeAction = (module, action) => {
  const permissions = form.permissions[module];

  if ((action === 'write' || action === 'delete') && permissions[action]) {
    permissions.read = true;
  }

  if (action === 'read' && !permissions.read) {
    permissions.write = false;
    permissions.delete = false;
  }
};

const submit = () => {
  if (!canWrite.value) return;

  const options = {
    preserveScroll: true,
    onSuccess: () => {
      if (!editTarget.value) resetForm();
    },
  };

  if (editTarget.value) {
    form.put(route('admin.roles.update', editTarget.value.id), options);
    return;
  }

  form.post(route('admin.roles.store'), options);
};

const deleteRole = () => {
  if (!canDelete.value) return;
  if (!editTarget.value || editTarget.value.users_count > 0) return;
  if (!window.confirm(`Delete the "${editTarget.value.name}" role?`)) return;

  router.delete(route('admin.roles.destroy', editTarget.value.id), {
    preserveScroll: true,
    onSuccess: resetForm,
  });
};
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
  padding: 0.875rem 1rem;
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

.permission-check {
  width: 1.1rem;
  height: 1.1rem;
  border-radius: 0.35rem;
  border-color: #cbd5e1;
  color: #131C3D;
}
</style>
