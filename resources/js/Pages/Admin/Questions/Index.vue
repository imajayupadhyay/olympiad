<template>
  <AdminLayout title="Question Bank" subtitle="Manage MCQ questions for all subjects and classes">

    <!-- Top bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div class="flex items-center gap-2">
        <span class="bg-primary/10 text-primary text-sm font-semibold px-3 py-1.5 rounded-lg font-number">
          {{ total.toLocaleString() }} Questions
        </span>
        <span v-if="filters.subject_id || filters.class_level_id || filters.question_category_id || filters.difficulty || filters.search"
              class="bg-accent/10 text-accent text-xs font-semibold px-2.5 py-1.5 rounded-lg">
          Filtered
        </span>
      </div>
      <Link
        :href="route('admin.questions.create')"
        class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Add Question
      </Link>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <!-- Search -->
        <div class="lg:col-span-2 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            v-model="form.search"
            type="text"
            placeholder="Search question text…"
            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 bg-gray-50"
            @keydown.enter="applyFilters"
          />
        </div>

        <!-- Subject (from DB) -->
        <select v-model="form.subject_id" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Subjects</option>
          <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.icon }} {{ s.name }}</option>
        </select>

        <select v-model="form.question_category_id" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Categories</option>
          <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
            {{ optionPrefix(category.depth) }} {{ category.path }}
          </option>
        </select>

        <!-- Class (from DB) -->
        <select v-model="form.class_level_id" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Classes</option>
          <option v-for="cl in classLevels" :key="cl.id" :value="cl.id">{{ cl.label }}</option>
        </select>

        <!-- Difficulty -->
        <select v-model="form.difficulty" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Difficulties</option>
          <option v-for="(label, key) in difficulties" :key="key" :value="key">{{ label }}</option>
        </select>

        <!-- Type -->
        <select v-model="form.question_type" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Types</option>
          <option value="single">Single Correct</option>
          <option value="multiple">Multi Correct</option>
        </select>
      </div>

      <div class="flex gap-2 mt-3">
        <button
          @click="applyFilters"
          class="bg-primary text-white px-4 py-2 rounded-lg text-xs font-semibold hover:bg-primary-light transition-colors"
        >
          Apply Filters
        </button>
        <button
          v-if="hasFilters"
          @click="clearFilters"
          class="bg-gray-100 text-text-muted px-4 py-2 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors"
        >
          Clear
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

      <!-- Empty state -->
      <div v-if="questions.data.length === 0" class="py-20 text-center">
        <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <p class="font-heading font-bold text-text-main text-base mb-1">
          {{ hasFilters ? 'No questions match your filters' : 'No questions yet' }}
        </p>
        <p class="text-text-muted text-sm mb-5">
          {{ hasFilters ? 'Try adjusting or clearing your filters.' : 'Start building the question bank by adding your first MCQ.' }}
        </p>
        <Link v-if="!hasFilters" :href="route('admin.questions.create')"
              class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
          Add First Question
        </Link>
      </div>

      <!-- Table rows -->
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider w-10">#</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Question</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider whitespace-nowrap">Subject</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Class</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Level</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Marks</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Status</th>
            <th class="text-right px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr
            v-for="(q, i) in questions.data"
            :key="q.id"
            class="hover:bg-gray-50/50 transition-colors"
          >
            <td class="px-5 py-3.5 text-text-muted font-number text-xs">
              {{ questions.from + i }}
            </td>
            <td class="px-4 py-3.5 max-w-xs">
              <div class="flex items-start gap-2">
                <img v-if="q.question_image_url" :src="q.question_image_url" alt=""
                     class="w-10 h-10 rounded-lg object-cover border border-gray-100 shrink-0" />
                <div class="min-w-0">
                  <p class="text-text-main font-medium line-clamp-2 leading-snug text-xs">{{ stripHtml(q.question_text) }}</p>
                  <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                    <span v-if="q.topic" class="text-text-muted text-[10px]">{{ q.topic }}</span>
                    <span v-if="q.question_category" class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-accent/10 text-accent">
                      {{ q.question_category.name }}
                    </span>
                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded"
                          :class="q.question_type === 'multiple' ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600'">
                      {{ q.question_type === 'multiple' ? 'Multi' : 'Single' }}
                    </span>
                  </div>
                </div>
              </div>
            </td>
            <td class="px-4 py-3.5 whitespace-nowrap">
              <span v-if="q.subject" class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-md"
                    :style="{ background: q.subject.color + '20', color: q.subject.color }">
                {{ q.subject.icon }} {{ q.subject.name }}
              </span>
            </td>
            <td class="px-4 py-3.5">
              <div class="flex flex-wrap gap-1">
                <span v-for="cl in q.class_levels" :key="cl.id"
                      class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-text-main">
                  {{ cl.label }}
                </span>
              </div>
            </td>
            <td class="px-4 py-3.5">
              <span class="text-xs font-semibold px-2 py-1 rounded-md" :class="difficultyClass(q.difficulty)">
                {{ q.difficulty }}
              </span>
            </td>
            <td class="px-4 py-3.5 font-number text-text-main text-sm">
              {{ q.marks }}
              <span v-if="q.negative_marks > 0" class="text-danger text-xs ml-1">-{{ q.negative_marks }}</span>
            </td>
            <td class="px-4 py-3.5">
              <span class="w-2 h-2 rounded-full inline-block mr-1.5" :class="q.is_active ? 'bg-success' : 'bg-gray-300'"></span>
              <span class="text-xs" :class="q.is_active ? 'text-success' : 'text-text-muted'">
                {{ q.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-2">
                <Link
                  :href="route('admin.questions.edit', q.id)"
                  class="text-primary hover:text-primary-light text-xs font-semibold px-2.5 py-1 rounded-lg hover:bg-primary/5 transition-colors"
                >
                  Edit
                </Link>
                <button
                  @click="confirmDelete(q)"
                  class="text-danger hover:text-red-700 text-xs font-semibold px-2.5 py-1 rounded-lg hover:bg-danger/5 transition-colors"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="questions.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-text-muted text-xs">
          Showing {{ questions.from }}–{{ questions.to }} of {{ questions.total }} questions
        </p>
        <div class="flex gap-1">
          <Link
            v-for="link in questions.links"
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

    <!-- Delete confirm modal -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5);">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-danger/10 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Delete Question?</h3>
        <p class="text-text-muted text-sm mb-5 line-clamp-3">{{ stripHtml(deleteTarget.question_text) }}</p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">
            Cancel
          </button>
          <button @click="doDelete" class="flex-1 bg-danger text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-red-700 transition-colors">
            Delete
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  questions:    Object,
  subjects:     Array,
  categories:   Array,
  classLevels:  Array,
  difficulties: Object,
  filters:      Object,
  total:        Number,
});

const form = ref({
  search:         props.filters.search          || '',
  subject_id:     props.filters.subject_id      || '',
  question_category_id: props.filters.question_category_id || '',
  class_level_id: props.filters.class_level_id  || '',
  difficulty:     props.filters.difficulty      || '',
  question_type:  props.filters.question_type   || '',
});

const categoryOptions = computed(() => (props.categories || []).filter((category) => {
  if (!category.is_active) return false;

  return !form.value.subject_id || Number(category.subject_id) === Number(form.value.subject_id);
}));

const hasFilters = computed(() =>
  form.value.search || form.value.subject_id || form.value.question_category_id || form.value.class_level_id ||
  form.value.difficulty || form.value.question_type
);

watch(() => form.value.subject_id, () => {
  if (form.value.question_category_id && !categoryOptions.value.some((category) => Number(category.id) === Number(form.value.question_category_id))) {
    form.value.question_category_id = '';
  }
});

const applyFilters = () => {
  router.get(route('admin.questions.index'), form.value, { preserveState: true, replace: true });
};

const clearFilters = () => {
  form.value = { search: '', subject_id: '', question_category_id: '', class_level_id: '', difficulty: '', question_type: '' };
  applyFilters();
};

const optionPrefix = (depth) => ''.padStart(depth * 2, '-');

const difficultyClass = (d) => ({
  easy:   'bg-green-100 text-green-700',
  medium: 'bg-amber-100 text-amber-700',
  hard:   'bg-red-100 text-red-700',
}[d] || 'bg-gray-100 text-gray-600');

const stripHtml = (html) => {
  const tmp = document.createElement('div');
  tmp.innerHTML = html || '';
  return tmp.textContent || tmp.innerText || '';
};

const deleteTarget = ref(null);
const confirmDelete = (q) => deleteTarget.value = q;
const doDelete = () => {
  router.delete(route('admin.questions.destroy', deleteTarget.value.id), {
    onFinish: () => deleteTarget.value = null,
  });
};
</script>
