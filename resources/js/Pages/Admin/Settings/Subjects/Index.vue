<template>
  <AdminLayout title="Subjects" subtitle="Manage exam subjects — add, edit, reorder or disable">

    <!-- Flash -->
    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.error }}
    </div>

    <div class="max-w-4xl grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Subject list -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-heading font-bold text-text-main text-base">All Subjects</h2>
            <span class="text-text-muted text-xs font-number">{{ subjects.length }} total</span>
          </div>

          <div class="divide-y divide-gray-50">
            <div
              v-for="subject in subjects"
              :key="subject.id"
              class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50/50 transition-colors"
            >
              <!-- Icon + color -->
              <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl shrink-0 border border-gray-100"
                   :style="{ background: subject.color + '18' }">
                {{ subject.icon }}
              </div>

              <!-- Info -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <p class="font-semibold text-text-main text-sm">{{ subject.name }}</p>
                  <span class="text-[10px] font-mono text-text-muted bg-gray-100 px-1.5 py-0.5 rounded">{{ subject.slug }}</span>
                  <span v-if="!subject.is_active" class="text-[10px] font-semibold text-danger bg-red-50 border border-red-100 px-1.5 py-0.5 rounded">Inactive</span>
                </div>
                <p v-if="subject.description" class="text-text-muted text-xs mt-0.5 truncate">{{ subject.description }}</p>
                <p v-else class="text-text-muted text-xs mt-0.5 italic">No description</p>
              </div>

              <!-- Color dot -->
              <div class="w-4 h-4 rounded-full shrink-0 border border-gray-200" :style="{ background: subject.color }"></div>

              <!-- Actions -->
              <div class="flex items-center gap-1 shrink-0">
                <button @click="startEdit(subject)"
                        class="text-primary text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-primary/5 transition-colors">
                  Edit
                </button>
                <button @click="toggleActive(subject)"
                        class="text-xs font-semibold px-2.5 py-1.5 rounded-lg transition-colors"
                        :class="subject.is_active ? 'text-text-muted hover:bg-gray-100' : 'text-success hover:bg-green-50'">
                  {{ subject.is_active ? 'Disable' : 'Enable' }}
                </button>
                <button @click="deleteTarget = subject"
                        class="text-danger text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-danger/5 transition-colors">
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Add / Edit form -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sticky top-20">
          <h2 class="font-heading font-bold text-text-main text-base mb-4">
            {{ editTarget ? 'Edit Subject' : 'Add New Subject' }}
          </h2>

          <form @submit.prevent="editTarget ? submitUpdate() : submitCreate()" class="space-y-3.5">
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Subject Name *</label>
              <input v-model="form.name" type="text" placeholder="e.g. Physics"
                     class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary" />
              <p v-if="form.errors.name" class="text-danger text-xs mt-1">{{ form.errors.name }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Icon (emoji)</label>
                <input v-model="form.icon" type="text" placeholder="🔭" maxlength="5"
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-xl text-center focus:outline-none focus:border-primary" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Badge Color</label>
                <div class="flex items-center gap-2">
                  <input v-model="form.color" type="color"
                         class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer p-0.5 shrink-0" />
                  <input v-model="form.color" type="text" maxlength="7"
                         class="flex-1 px-2 py-2.5 border border-gray-200 rounded-xl text-xs font-mono focus:outline-none focus:border-primary" />
                </div>
              </div>
            </div>

            <!-- Active toggle (edit only) -->
            <div v-if="editTarget" class="flex items-center justify-between py-1">
              <span class="text-sm text-text-muted">Active</span>
              <div class="relative cursor-pointer" @click="form.is_active = !form.is_active">
                <div class="w-10 h-5 rounded-full transition-colors" :class="form.is_active ? 'bg-success' : 'bg-gray-300'"></div>
                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="form.is_active ? 'translate-x-5' : ''"></div>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Description <span class="font-normal normal-case">(optional)</span></label>
              <textarea v-model="form.description" rows="2" placeholder="Brief description of this subject…"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary resize-none"></textarea>
            </div>

            <!-- Preview -->
            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg" :style="{ background: form.color + '22' }">{{ form.icon || '📚' }}</div>
              <div>
                <p class="text-sm font-semibold text-text-main">{{ form.name || 'Subject Name' }}</p>
                <p class="text-xs text-text-muted">Preview</p>
              </div>
              <div class="ml-auto w-4 h-4 rounded-full" :style="{ background: form.color }"></div>
            </div>

            <div class="flex gap-2 pt-1">
              <button type="submit" :disabled="form.processing"
                      class="flex-1 bg-primary text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60">
                {{ editTarget ? 'Update' : 'Add Subject' }}
              </button>
              <button v-if="editTarget" type="button" @click="cancelEdit"
                      class="px-4 py-2.5 rounded-xl text-sm font-semibold text-text-muted bg-gray-100 hover:bg-gray-200 transition-colors">
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Delete confirm -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5);">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Delete "{{ deleteTarget.name }}"?</h3>
        <p class="text-text-muted text-sm mb-5">If this subject has questions linked, deletion will be blocked.</p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold">Cancel</button>
          <button @click="doDelete" class="flex-1 bg-danger text-white py-2.5 rounded-xl text-sm font-semibold">Delete</button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ subjects: Array });

const editTarget  = ref(null);
const deleteTarget = ref(null);

const form = useForm({ name: '', icon: '📚', color: '#2C49A6', description: '', is_active: true });

const startEdit = (s) => {
  editTarget.value = s;
  form.name        = s.name;
  form.icon        = s.icon;
  form.color       = s.color;
  form.description = s.description || '';
  form.is_active   = s.is_active;
};

const cancelEdit = () => {
  editTarget.value = null;
  form.reset();
  form.icon  = '📚';
  form.color = '#2C49A6';
};

const submitCreate = () => {
  form.post(route('admin.settings.subjects.store'), { onSuccess: () => form.reset() });
};

const submitUpdate = () => {
  form.put(route('admin.settings.subjects.update', editTarget.value.id), {
    onSuccess: () => { editTarget.value = null; form.reset(); },
  });
};

const toggleActive = (s) => {
  router.put(route('admin.settings.subjects.update', s.id), { ...s, is_active: !s.is_active }, { preserveScroll: true });
};

const doDelete = () => {
  router.delete(route('admin.settings.subjects.destroy', deleteTarget.value.id), {
    onFinish: () => deleteTarget.value = null,
    preserveScroll: true,
  });
};
</script>
