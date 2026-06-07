<template>
  <AdminLayout title="Question Categories" subtitle="Organize question bank topics under each subject">
    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.error }}
    </div>

    <div class="max-w-6xl grid grid-cols-1 xl:grid-cols-3 gap-6">
      <div class="xl:col-span-2 space-y-5">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2 relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <input v-model="search" type="text" placeholder="Search categories"
                     class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50" />
            </div>
            <select v-model="listSubjectId" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50">
              <option value="">All Subjects</option>
              <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.icon }} {{ subject.name }}</option>
            </select>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
              <h2 class="font-heading font-bold text-text-main text-base">Category Tree</h2>
              <p class="text-text-muted text-xs mt-0.5">{{ filteredCategories.length }} shown from {{ categories.length }} total</p>
            </div>
          </div>

          <div v-if="filteredCategories.length === 0" class="py-16 text-center">
            <div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-3">
              <svg class="w-7 h-7 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
              </svg>
            </div>
            <p class="font-heading font-bold text-text-main text-base mb-1">No categories yet</p>
            <p class="text-text-muted text-sm">Create parent categories and child topics for each subject.</p>
          </div>

          <div v-else class="divide-y divide-gray-50">
            <div v-for="category in filteredCategories" :key="category.id"
                 class="px-5 py-3.5 hover:bg-gray-50/50 transition-colors flex items-start gap-4">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border border-gray-100"
                   :style="{ background: category.subject?.color + '18', color: category.subject?.color }">
                {{ category.subject?.icon || '#' }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="text-text-muted font-number text-xs w-6">{{ category.depth ? ''.padStart(category.depth * 2, '-') : '' }}</span>
                  <p class="font-semibold text-text-main text-sm">{{ category.name }}</p>
                  <span v-if="!category.is_active" class="text-[10px] font-semibold text-danger bg-red-50 border border-red-100 px-1.5 py-0.5 rounded">Inactive</span>
                  <span class="text-[10px] font-mono text-text-muted bg-gray-100 px-1.5 py-0.5 rounded">{{ category.slug }}</span>
                </div>
                <p class="text-text-muted text-xs mt-1 truncate">{{ category.path }}</p>
                <div class="flex items-center gap-2 mt-2 text-[10px] text-text-muted">
                  <span>{{ category.subject?.name }}</span>
                  <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                  <span>{{ category.children_count }} child</span>
                  <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                  <span>{{ category.questions_count }} questions</span>
                </div>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <button @click="startEdit(category)"
                        class="text-primary text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-primary/5 transition-colors">
                  Edit
                </button>
                <button @click="toggleActive(category)"
                        class="text-xs font-semibold px-2.5 py-1.5 rounded-lg transition-colors"
                        :class="category.is_active ? 'text-text-muted hover:bg-gray-100' : 'text-success hover:bg-green-50'">
                  {{ category.is_active ? 'Disable' : 'Enable' }}
                </button>
                <button @click="deleteTarget = category"
                        class="text-danger text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-danger/5 transition-colors">
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="xl:col-span-1">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sticky top-20">
          <h2 class="font-heading font-bold text-text-main text-base mb-4">
            {{ editTarget ? 'Edit Category' : 'Add Category' }}
          </h2>

          <form @submit.prevent="editTarget ? submitUpdate() : submitCreate()" class="space-y-3.5">
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Subject *</label>
              <select v-model="form.subject_id"
                      class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white"
                      :class="form.errors.subject_id ? 'border-danger' : 'border-gray-200'">
                <option value="">Choose subject</option>
                <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.icon }} {{ subject.name }}</option>
              </select>
              <p v-if="form.errors.subject_id" class="text-danger text-xs mt-1">{{ form.errors.subject_id }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Parent Category</label>
              <select v-model="form.parent_id"
                      :disabled="!form.subject_id"
                      class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white disabled:bg-gray-50 disabled:text-text-muted"
                      :class="form.errors.parent_id ? 'border-danger' : 'border-gray-200'">
                <option value="">No parent - top level</option>
                <option v-for="category in parentOptions" :key="category.id" :value="category.id">
                  {{ optionPrefix(category.depth) }} {{ category.path }}
                </option>
              </select>
              <p v-if="form.errors.parent_id" class="text-danger text-xs mt-1">{{ form.errors.parent_id }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Category Name *</label>
              <input v-model="form.name" type="text" placeholder="e.g. Algebra, Motion, Grammar"
                     class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary"
                     :class="form.errors.name ? 'border-danger' : 'border-gray-200'" />
              <p v-if="form.errors.name" class="text-danger text-xs mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Description</label>
              <textarea v-model="form.description" rows="3" placeholder="Optional short note"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary resize-none"></textarea>
            </div>

            <div v-if="editTarget" class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Sort Order</label>
                <input v-model.number="form.sort_order" type="number" min="0"
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary font-number" />
              </div>
              <div class="flex items-end">
                <div class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl border border-gray-200">
                  <span class="text-sm text-text-muted">Active</span>
                  <div class="relative cursor-pointer" @click="form.is_active = !form.is_active">
                    <div class="w-10 h-5 rounded-full transition-colors" :class="form.is_active ? 'bg-success' : 'bg-gray-300'"></div>
                    <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="form.is_active ? 'translate-x-5' : ''"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
              <p class="text-xs text-text-muted mb-1">Preview</p>
              <p class="text-sm font-semibold text-text-main">{{ previewPath }}</p>
            </div>

            <div class="flex gap-2 pt-1">
              <button type="submit" :disabled="form.processing"
                      class="flex-1 bg-primary text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60">
                {{ editTarget ? 'Update' : 'Add Category' }}
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

    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5);">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Delete "{{ deleteTarget.name }}"?</h3>
        <p class="text-text-muted text-sm mb-5">Deletion is blocked while child categories or questions are linked.</p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold">Cancel</button>
          <button @click="doDelete" class="flex-1 bg-danger text-white py-2.5 rounded-xl text-sm font-semibold">Delete</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  subjects: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
});

const search = ref('');
const listSubjectId = ref('');
const editTarget = ref(null);
const deleteTarget = ref(null);

const form = useForm({
  subject_id: '',
  parent_id: '',
  name: '',
  description: '',
  is_active: true,
  sort_order: 0,
});

const filteredCategories = computed(() => props.categories.filter((category) => {
  const matchesSubject = !listSubjectId.value || Number(category.subject_id) === Number(listSubjectId.value);
  const haystack = `${category.name} ${category.path} ${category.subject?.name || ''}`.toLowerCase();
  const matchesSearch = !search.value || haystack.includes(search.value.toLowerCase());

  return matchesSubject && matchesSearch;
}));

const selectedSubjectCategories = computed(() => props.categories.filter((category) =>
  Number(category.subject_id) === Number(form.subject_id),
));

const parentOptions = computed(() => {
  const blockedIds = editTarget.value ? [editTarget.value.id, ...descendantIds(editTarget.value.id)] : [];

  return selectedSubjectCategories.value.filter((category) => !blockedIds.includes(category.id));
});

const previewPath = computed(() => {
  const name = form.name || 'Category name';
  const parent = props.categories.find((category) => Number(category.id) === Number(form.parent_id));

  return parent ? `${parent.path} / ${name}` : name;
});

watch(() => form.subject_id, () => {
  if (form.parent_id && !parentOptions.value.some((category) => Number(category.id) === Number(form.parent_id))) {
    form.parent_id = '';
  }
});

const descendantIds = (categoryId) => {
  const ids = [];
  const queue = [Number(categoryId)];

  while (queue.length) {
    const parentId = queue.shift();
    props.categories
      .filter((category) => Number(category.parent_id) === parentId)
      .forEach((category) => {
        ids.push(category.id);
        queue.push(category.id);
      });
  }

  return ids;
};

const optionPrefix = (depth) => ''.padStart(depth * 2, '-');

const startEdit = (category) => {
  editTarget.value = category;
  form.subject_id = category.subject_id;
  form.parent_id = category.parent_id || '';
  form.name = category.name;
  form.description = category.description || '';
  form.is_active = category.is_active;
  form.sort_order = category.sort_order || 0;
  form.clearErrors();
};

const cancelEdit = () => {
  editTarget.value = null;
  form.reset();
  form.is_active = true;
  form.sort_order = 0;
};

const submitCreate = () => {
  form.post(route('admin.settings.categories.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      form.is_active = true;
      form.sort_order = 0;
    },
  });
};

const submitUpdate = () => {
  form.put(route('admin.settings.categories.update', editTarget.value.id), {
    preserveScroll: true,
    onSuccess: cancelEdit,
  });
};

const toggleActive = (category) => {
  router.put(route('admin.settings.categories.update', category.id), {
    subject_id: category.subject_id,
    parent_id: category.parent_id,
    name: category.name,
    description: category.description || '',
    is_active: !category.is_active,
    sort_order: category.sort_order || 0,
  }, { preserveScroll: true });
};

const doDelete = () => {
  router.delete(route('admin.settings.categories.destroy', deleteTarget.value.id), {
    preserveScroll: true,
    onFinish: () => deleteTarget.value = null,
  });
};
</script>
