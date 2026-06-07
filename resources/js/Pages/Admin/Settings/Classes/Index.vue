<template>
  <AdminLayout title="Class Levels" subtitle="Manage which class levels are available across the platform">

    <!-- Flash -->
    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.error }}
    </div>

    <div class="max-w-3xl grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Class levels grid -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-heading font-bold text-text-main text-base">All Class Levels</h2>
            <span class="text-text-muted text-xs">
              <span class="text-success font-semibold font-number">{{ classes.filter(c => c.is_active).length }}</span> active of
              <span class="font-number">{{ classes.length }}</span>
            </span>
          </div>

          <div class="p-4 grid grid-cols-3 sm:grid-cols-4 gap-3">
            <div
              v-for="cls in classes"
              :key="cls.id"
              class="relative rounded-xl border-2 p-3 text-center transition-all"
              :class="cls.is_active
                ? 'border-primary/20 bg-primary/5'
                : 'border-gray-100 bg-gray-50 opacity-60'"
            >
              <!-- Active indicator -->
              <div class="absolute top-2 right-2 w-2 h-2 rounded-full"
                   :class="cls.is_active ? 'bg-success' : 'bg-gray-300'"></div>

              <p class="font-number font-bold text-2xl" :class="cls.is_active ? 'text-primary' : 'text-gray-400'">
                {{ cls.level }}
              </p>
              <p class="text-xs font-medium mt-0.5" :class="cls.is_active ? 'text-text-muted' : 'text-gray-400'">
                {{ cls.label }}
              </p>

              <div class="flex gap-1 mt-2 justify-center">
                <button
                  @click="startEdit(cls)"
                  class="text-[10px] font-semibold px-2 py-1 rounded-md bg-white border border-gray-200 text-primary hover:border-primary transition-colors"
                >
                  Edit
                </button>
                <button
                  @click="toggleActive(cls)"
                  class="text-[10px] font-semibold px-2 py-1 rounded-md border transition-colors"
                  :class="cls.is_active
                    ? 'bg-white border-gray-200 text-text-muted hover:text-danger hover:border-danger'
                    : 'bg-success/10 border-success/20 text-success hover:bg-success/20'"
                >
                  {{ cls.is_active ? 'Off' : 'On' }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Info note -->
        <div class="mt-4 flex items-start gap-2 bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
          <svg class="w-4 h-4 text-primary shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p class="text-primary text-xs leading-relaxed">
            Disabled class levels will not appear in question creation, exam setup, or student registration dropdowns. Existing questions with that level are preserved.
          </p>
        </div>
      </div>

      <!-- Add / Edit -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sticky top-20">
          <h2 class="font-heading font-bold text-text-main text-base mb-4">
            {{ editTarget ? `Edit ${editTarget.label}` : 'Add Class Level' }}
          </h2>

          <form @submit.prevent="editTarget ? submitUpdate() : submitCreate()" class="space-y-3.5">

            <div v-if="!editTarget">
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Level Number *</label>
              <input v-model.number="addForm.level" type="number" min="1" max="20" placeholder="e.g. 13"
                     class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary font-number" />
              <p v-if="addForm.errors.level" class="text-danger text-xs mt-1">{{ addForm.errors.level }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Label *</label>
              <input v-if="editTarget" v-model="editForm.label" type="text"
                     :placeholder="editTarget.label"
                     class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary" />
              <input v-else v-model="addForm.label" type="text"
                     placeholder="e.g. Class 13 / Pre-Nursery"
                     class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary" />
            </div>

            <div v-if="editTarget" class="flex items-center justify-between py-1">
              <span class="text-sm text-text-muted">Active</span>
              <div class="relative cursor-pointer" @click="editForm.is_active = !editForm.is_active">
                <div class="w-10 h-5 rounded-full transition-colors" :class="editForm.is_active ? 'bg-success' : 'bg-gray-300'"></div>
                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="editForm.is_active ? 'translate-x-5' : ''"></div>
              </div>
            </div>

            <div class="flex gap-2 pt-1">
              <button type="submit" :disabled="(editTarget ? editForm : addForm).processing"
                      class="flex-1 bg-primary text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60">
                {{ editTarget ? 'Update' : 'Add Level' }}
              </button>
              <button v-if="editTarget" type="button" @click="cancelEdit"
                      class="px-4 py-2.5 rounded-xl text-sm font-semibold text-text-muted bg-gray-100 hover:bg-gray-200">
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ classes: Array });

const editTarget = ref(null);

const addForm  = useForm({ level: '', label: '' });
const editForm = useForm({ label: '', is_active: true });

const startEdit = (cls) => {
  editTarget.value  = cls;
  editForm.label    = cls.label;
  editForm.is_active = cls.is_active;
};

const cancelEdit = () => {
  editTarget.value = null;
  editForm.reset();
};

const submitCreate = () => {
  addForm.post(route('admin.settings.classes.store'), { onSuccess: () => addForm.reset() });
};

const submitUpdate = () => {
  editForm.put(route('admin.settings.classes.update', editTarget.value.id), {
    onSuccess: () => { editTarget.value = null; editForm.reset(); },
  });
};

const toggleActive = (cls) => {
  router.put(route('admin.settings.classes.update', cls.id), { label: cls.label, is_active: !cls.is_active }, { preserveScroll: true });
};
</script>
