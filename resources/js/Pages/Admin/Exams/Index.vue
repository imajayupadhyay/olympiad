<template>
  <AdminLayout title="Exam Management" subtitle="Create, schedule, publish, and archive olympiad exams">
    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.error }}
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-2">Total Exams</p>
        <p class="font-number font-bold text-3xl text-primary">{{ stats.total.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-2">Published</p>
        <p class="font-number font-bold text-3xl text-success">{{ stats.published.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-2">Drafts</p>
        <p class="font-number font-bold text-3xl text-text-main">{{ stats.draft.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-2">Archived</p>
        <p class="font-number font-bold text-3xl text-gold">{{ stats.archived.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-2">No Questions</p>
        <p class="font-number font-bold text-3xl text-danger">{{ stats.unassigned.toLocaleString() }}</p>
      </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
      <div class="flex items-center gap-2">
        <span v-if="hasFilters" class="bg-accent/10 text-accent text-xs font-semibold px-2.5 py-1.5 rounded-lg">Filtered</span>
      </div>
      <Link
        :href="route('admin.exams.create')"
        class="inline-flex items-center justify-center gap-2 bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Create Exam
      </Link>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5">
      <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
        <div class="md:col-span-2 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input v-model="filterForm.search" type="text" placeholder="Search exam name or code"
                 class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 bg-gray-50"
                 @keydown.enter="applyFilters" />
        </div>

        <select v-model="filterForm.subject_id" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Subjects</option>
          <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.icon }} {{ subject.name }}</option>
        </select>

        <select v-model="filterForm.question_category_id" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Categories</option>
          <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
            {{ optionPrefix(category.depth) }} {{ category.path }}
          </option>
        </select>

        <select v-model="filterForm.class_level_id" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Classes</option>
          <option v-for="classLevel in classLevels" :key="classLevel.id" :value="classLevel.id">{{ classLevel.label }}</option>
        </select>

        <select v-model="filterForm.status" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
          <option value="">All Statuses</option>
          <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>

      <div class="flex gap-2 mt-3">
        <button @click="applyFilters" class="bg-primary text-white px-4 py-2 rounded-lg text-xs font-semibold hover:bg-primary-light transition-colors">
          Apply Filters
        </button>
        <button v-if="hasFilters" @click="clearFilters" class="bg-gray-100 text-text-muted px-4 py-2 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors">
          Clear
        </button>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div v-if="exams.data.length === 0" class="py-20 text-center">
        <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
        </div>
        <p class="font-heading font-bold text-text-main text-base mb-1">
          {{ hasFilters ? 'No exams match your filters' : 'No exams yet' }}
        </p>
        <p class="text-text-muted text-sm mb-5">
          {{ hasFilters ? 'Try adjusting or clearing your filters.' : 'Create the first olympiad exam from the question bank.' }}
        </p>
        <Link v-if="!hasFilters" :href="route('admin.exams.create')"
              class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
          Create First Exam
        </Link>
      </div>

      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Exam</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Subject</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Schedule</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Fee</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Questions</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Status</th>
            <th class="text-right px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="exam in exams.data" :key="exam.id" class="hover:bg-gray-50/50 transition-colors">
            <td class="px-5 py-4 max-w-xs">
              <p class="text-text-main font-semibold truncate">{{ exam.name }}</p>
              <p class="text-text-muted text-xs font-number mt-0.5">{{ exam.exam_code }}</p>
            </td>
            <td class="px-4 py-4 whitespace-nowrap">
              <span v-if="exam.subject" class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-md"
                    :style="{ background: exam.subject.color + '20', color: exam.subject.color }">
                {{ exam.subject.icon }} {{ exam.subject.name }}
              </span>
              <p class="text-text-muted text-xs mt-1 font-number">{{ exam.class_level?.label }}</p>
              <p v-if="exam.question_category" class="text-accent text-[11px] mt-1 truncate">{{ exam.question_category.name }}</p>
            </td>
            <td class="px-4 py-4 whitespace-nowrap">
              <p class="text-text-main text-xs font-semibold">{{ formatDateTime(exam.starts_at) }}</p>
              <p class="text-text-muted text-xs font-number mt-0.5">{{ exam.duration_minutes }} mins</p>
            </td>
            <td class="px-4 py-4 whitespace-nowrap">
              <p class="font-number text-text-main font-bold">{{ exam.fee_currency }} {{ money(exam.fee_amount) }}</p>
            </td>
            <td class="px-4 py-4 whitespace-nowrap">
              <span class="font-number font-bold text-sm" :class="exam.questions_count > 0 ? 'text-success' : 'text-danger'">
                {{ exam.questions_count }}
              </span>
            </td>
            <td class="px-4 py-4 whitespace-nowrap">
              <span class="text-xs font-semibold px-2.5 py-1 rounded-lg" :class="statusClass(exam.status)">
                {{ statuses[exam.status] || exam.status }}
              </span>
            </td>
            <td class="px-5 py-4">
              <div class="flex items-center justify-end gap-1.5">
                <Link :href="route('admin.exams.edit', exam.id)"
                      class="text-primary hover:text-primary-light text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-primary/5 transition-colors">
                  Edit
                </Link>
                <button v-if="exam.status !== 'published'" @click="publishExam(exam)"
                        class="text-success text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-green-50 transition-colors">
                  Publish
                </button>
                <button v-else @click="unpublishExam(exam)"
                        class="text-text-muted text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                  Unpublish
                </button>
                <button v-if="exam.status !== 'archived'" @click="archiveExam(exam)"
                        class="text-gold text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-amber-50 transition-colors">
                  Archive
                </button>
                <button @click="deleteTarget = exam"
                        class="text-danger text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-danger/5 transition-colors">
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="exams.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-text-muted text-xs">
          Showing {{ exams.from }}-{{ exams.to }} of {{ exams.total }} exams
        </p>
        <div class="flex gap-1">
          <Link
            v-for="(link, index) in exams.links"
            :key="index"
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

    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5);">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-danger/10 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Delete Exam?</h3>
        <p class="text-text-muted text-sm mb-5">{{ deleteTarget.name }}</p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">
            Cancel
          </button>
          <button @click="deleteExam" class="flex-1 bg-danger text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-red-700 transition-colors">
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
  exams: Object,
  subjects: Array,
  categories: Array,
  classLevels: Array,
  statuses: Object,
  filters: Object,
  stats: Object,
});

const filterForm = ref({
  search: props.filters.search || '',
  subject_id: props.filters.subject_id || '',
  question_category_id: props.filters.question_category_id || '',
  class_level_id: props.filters.class_level_id || '',
  status: props.filters.status || '',
});

const deleteTarget = ref(null);

const hasFilters = computed(() =>
  filterForm.value.search ||
  filterForm.value.subject_id ||
  filterForm.value.question_category_id ||
  filterForm.value.class_level_id ||
  filterForm.value.status
);

const categoryOptions = computed(() => (props.categories || []).filter((category) =>
  !filterForm.value.subject_id || Number(category.subject_id) === Number(filterForm.value.subject_id),
));

watch(() => filterForm.value.subject_id, () => {
  if (filterForm.value.question_category_id && !categoryOptions.value.some((category) => Number(category.id) === Number(filterForm.value.question_category_id))) {
    filterForm.value.question_category_id = '';
  }
});

const applyFilters = () => {
  router.get(route('admin.exams.index'), filterForm.value, {
    preserveState: true,
    replace: true,
  });
};

const clearFilters = () => {
  filterForm.value = { search: '', subject_id: '', question_category_id: '', class_level_id: '', status: '' };
  applyFilters();
};

const optionPrefix = (depth) => ''.padStart(depth * 2, '-');

const publishExam = (exam) => {
  router.patch(route('admin.exams.publish', exam.id), {}, { preserveScroll: true });
};

const unpublishExam = (exam) => {
  router.patch(route('admin.exams.unpublish', exam.id), {}, { preserveScroll: true });
};

const archiveExam = (exam) => {
  router.patch(route('admin.exams.archive', exam.id), {}, { preserveScroll: true });
};

const deleteExam = () => {
  router.delete(route('admin.exams.destroy', deleteTarget.value.id), {
    preserveScroll: true,
    onFinish: () => {
      deleteTarget.value = null;
    },
  });
};

const statusClass = (status) => ({
  draft: 'bg-gray-100 text-text-muted',
  published: 'bg-green-100 text-success',
  archived: 'bg-amber-100 text-amber-700',
}[status] || 'bg-gray-100 text-text-muted');

const money = (value) => Number(value || 0).toLocaleString('en-IN', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

const formatDateTime = (value) => {
  if (!value) return 'Not scheduled';

  return new Intl.DateTimeFormat('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
};
</script>
