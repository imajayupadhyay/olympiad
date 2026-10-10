<template>
  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="open" class="fixed inset-0 z-50 flex justify-end bg-primary-dark/50 backdrop-blur-[2px]" @click.self="close">
        <div class="h-full w-full max-w-4xl bg-bg shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="bank-drawer-title">
          <header class="bg-white border-b border-gray-100 px-5 py-4 flex items-start justify-between gap-4">
            <div class="min-w-0">
              <p class="text-[11px] font-bold uppercase tracking-wider text-accent">Pick from question bank</p>
              <h2 id="bank-drawer-title" class="font-heading font-bold text-text-main text-lg truncate">Add to “{{ section?.name }}”</h2>
              <p class="text-xs text-text-muted mt-0.5">
                Active {{ subjectName }} questions for {{ classLabel }}. Change the section's bank subject to browse another subject.
              </p>
            </div>
            <button type="button" class="p-2 rounded-xl text-text-muted hover:bg-gray-100" aria-label="Close" @click="close">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </header>

          <div class="bg-white border-b border-gray-100 px-5 py-3 grid grid-cols-2 md:grid-cols-12 gap-2">
            <div class="col-span-2 md:col-span-4 relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              <input v-model="filters.search" type="search" placeholder="Search text, topic or tag" class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50 focus:outline-none focus:border-primary" />
            </div>
            <select v-model="filters.question_category_id" class="field md:col-span-3">
              <option value="">All categories</option>
              <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                {{ ''.padStart(category.depth * 2, '-') }} {{ category.path }}
              </option>
            </select>
            <select v-model="filters.difficulty" class="field md:col-span-2">
              <option value="">Any difficulty</option>
              <option v-for="(label, key) in difficulties" :key="key" :value="key">{{ label }}</option>
            </select>
            <select v-model="filters.question_type" class="field md:col-span-3">
              <option value="">Any type</option>
              <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
            </select>
            <label class="col-span-2 md:col-span-12 flex items-center gap-2 text-xs text-text-muted cursor-pointer select-none">
              <input v-model="hideUsed" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
              Hide questions already in this exam
              <span class="ml-auto font-number">{{ total }} found</span>
            </label>
          </div>

          <div class="flex-1 overflow-y-auto px-5 py-4 space-y-2">
            <p v-if="error" class="rounded-xl border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger">{{ error }}</p>

            <div v-else-if="!loading && visibleRows.length === 0" class="py-16 text-center">
              <p class="font-heading font-bold text-text-main">No matching questions</p>
              <p class="text-sm text-text-muted mt-1">Adjust the filters, or close this and use “Write new question”.</p>
            </div>

            <article v-for="question in visibleRows" :key="question.id"
                     class="bg-white rounded-xl border transition-colors"
                     :class="selected.has(question.id) ? 'border-primary ring-1 ring-primary/20' : 'border-gray-100'">
              <div class="flex items-start gap-3 p-3.5">
                <input type="checkbox" class="mt-1 rounded border-gray-300 text-primary focus:ring-primary disabled:opacity-40"
                       :checked="selected.has(question.id) || usedIds.has(question.id)"
                       :disabled="usedIds.has(question.id)"
                       :aria-label="`Select question ${question.id}`"
                       @change="toggle(question)" />
                <button type="button" class="min-w-0 flex-1 text-left" :disabled="usedIds.has(question.id)" @click="toggle(question)">
                  <p class="text-sm text-text-main font-medium leading-snug" :class="expanded.has(question.id) ? '' : 'line-clamp-2'">
                    {{ stripHtml(question.question_text) }}
                  </p>
                  <div class="flex flex-wrap items-center gap-1.5 mt-2">
                    <span class="chip" :class="difficultyClass(question.difficulty)">{{ question.difficulty }}</span>
                    <span class="chip bg-blue-50 text-primary">{{ question.question_type === 'multiple' ? 'Multi correct' : 'Single correct' }}</span>
                    <span v-if="question.question_category" class="chip bg-accent/10 text-accent-dark">{{ question.question_category.name }}</span>
                    <span class="text-[10px] font-number text-text-muted">+{{ question.marks }}<template v-if="question.negative_marks > 0"> / −{{ question.negative_marks }}</template></span>
                    <span v-if="usedIds.has(question.id)" class="chip bg-success/10 text-success">Already in this exam</span>
                  </div>
                </button>
                <button type="button" class="shrink-0 text-xs font-semibold text-text-muted hover:text-primary px-2 py-1 rounded-lg hover:bg-gray-50"
                        @click="toggleExpanded(question.id)">
                  {{ expanded.has(question.id) ? 'Less' : 'Preview' }}
                </button>
              </div>
              <ol v-if="expanded.has(question.id)" class="grid sm:grid-cols-2 gap-2 px-3.5 pb-3.5 pl-10">
                <li v-for="option in ['a', 'b', 'c', 'd']" :key="option"
                    class="rounded-lg border px-2.5 py-1.5 text-xs flex gap-2"
                    :class="question.correct_options?.includes(option) ? 'border-success/40 bg-green-50 text-success' : 'border-gray-100 text-text-main'">
                  <span class="font-bold uppercase">{{ option }}</span>
                  <span class="min-w-0">{{ stripHtml(question[`option_${option}`]) }}</span>
                </li>
              </ol>
            </article>

            <div v-if="loading" class="py-8 text-center">
              <div class="w-7 h-7 rounded-full border-2 border-primary/20 border-t-primary animate-spin mx-auto"></div>
            </div>
            <button v-else-if="page < lastPage" type="button" @click="load(page + 1)"
                    class="w-full py-2.5 rounded-xl border border-dashed border-gray-300 text-sm font-semibold text-text-muted hover:border-primary hover:text-primary">
              Load more questions
            </button>
          </div>

          <footer class="bg-white border-t border-gray-100 px-5 py-3 flex flex-wrap items-center gap-3">
            <span class="text-sm text-text-main"><strong class="font-number">{{ selected.size }}</strong> selected</span>
            <button type="button" class="text-xs font-semibold text-primary hover:underline" @click="selectAllVisible">Select all shown</button>
            <button v-if="selected.size" type="button" class="text-xs font-semibold text-text-muted hover:underline" @click="selected = new Map()">Clear</button>
            <div class="flex-1"></div>
            <button type="button" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-text-muted hover:bg-gray-100" @click="close">Cancel</button>
            <button type="button" :disabled="!selected.size" @click="confirm"
                    class="px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-light disabled:opacity-50 disabled:cursor-not-allowed">
              Add {{ selected.size || '' }} to section
            </button>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { difficultyClass, stripHtml } from './examBuilder';

const props = defineProps({
  open: Boolean,
  section: { type: Object, default: null },
  classLevelId: { type: [Number, String], default: '' },
  examCategoryId: { type: [Number, String], default: '' },
  examSubjectId: { type: [Number, String], default: '' },
  usedIds: { type: Set, default: () => new Set() },
  subjects: { type: Array, default: () => [] },
  classLevels: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  difficulties: { type: Object, default: () => ({}) },
  types: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'add']);

const filters = reactive({ search: '', question_category_id: '', difficulty: '', question_type: '' });
const hideUsed = ref(true);
const rows = ref([]);
const selected = ref(new Map());
const expanded = ref(new Set());
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
let searchTimer = null;
let requestId = 0;

const subjectName = computed(() => props.subjects.find((subject) => Number(subject.id) === Number(props.section?.subject_id))?.name || 'subject');
const classLabel = computed(() => props.classLevels.find((level) => Number(level.id) === Number(props.classLevelId))?.label || 'this class');
const categoryOptions = computed(() => props.categories.filter((category) =>
  Number(category.subject_id) === Number(props.section?.subject_id) && category.is_active));
const visibleRows = computed(() => (hideUsed.value ? rows.value.filter((question) => !props.usedIds.has(question.id)) : rows.value));

async function load(nextPage = 1) {
  if (!props.section?.subject_id || !props.classLevelId) return;
  const current = ++requestId;
  loading.value = true;
  error.value = '';

  try {
    const { data } = await window.axios.get(route('admin.exams.question-bank'), {
      params: {
        subject_id: props.section.subject_id,
        class_level_id: props.classLevelId,
        page: nextPage,
        ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')),
      },
    });
    if (current !== requestId) return;
    rows.value = nextPage === 1 ? data.data : [...rows.value, ...data.data];
    page.value = data.meta.current_page;
    lastPage.value = data.meta.last_page;
    total.value = data.meta.total;
  } catch (exception) {
    if (current !== requestId) return;
    error.value = exception.response?.status === 403
      ? 'You do not have permission to browse questions for exams.'
      : 'Questions could not be loaded. Check your connection and try again.';
  } finally {
    if (current === requestId) loading.value = false;
  }
}

watch(() => props.open, (isOpen) => {
  if (!isOpen) return;
  // Default the category to the exam's scope when this section draws from the exam's own subject.
  filters.search = '';
  filters.difficulty = '';
  filters.question_type = '';
  filters.question_category_id = Number(props.section?.subject_id) === Number(props.examSubjectId) ? (props.examCategoryId || '') : '';
  selected.value = new Map();
  expanded.value = new Set();
  rows.value = [];
  load(1);
});

watch(() => [filters.question_category_id, filters.difficulty, filters.question_type], () => {
  if (props.open) load(1);
});
watch(() => filters.search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => props.open && load(1), 300);
});

function toggle(question) {
  if (props.usedIds.has(question.id)) return;
  const next = new Map(selected.value);
  next.has(question.id) ? next.delete(question.id) : next.set(question.id, question);
  selected.value = next;
}

function toggleExpanded(id) {
  const next = new Set(expanded.value);
  next.has(id) ? next.delete(id) : next.add(id);
  expanded.value = next;
}

function selectAllVisible() {
  const next = new Map(selected.value);
  visibleRows.value.filter((question) => !props.usedIds.has(question.id)).forEach((question) => next.set(question.id, question));
  selected.value = next;
}

function confirm() {
  emit('add', [...selected.value.values()]);
  close();
}

function close() {
  emit('close');
}
</script>

<style scoped>
.field { @apply w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50 focus:outline-none focus:border-primary; }
.chip { @apply text-[10px] font-semibold px-2 py-0.5 rounded capitalize; }
.drawer-enter-active, .drawer-leave-active { transition: opacity .2s ease; }
.drawer-enter-active > div, .drawer-leave-active > div { transition: transform .25s ease; }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-from > div, .drawer-leave-to > div { transform: translateX(40px); }
</style>
