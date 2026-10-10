<template>
  <AdminLayout title="Review Exam Import" :subtitle="`${exam.name} · ${exam.exam_code}`">
    <!-- ── Summary ── -->
    <div class="mb-5 rounded-2xl border border-primary/10 bg-primary/[0.03] p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <p class="font-heading font-bold text-text-main">
          {{ form.questions.length }} questions in {{ groups.length }} section{{ groups.length === 1 ? '' : 's' }}
        </p>
        <p class="text-sm text-text-muted mt-0.5">
          {{ newCount }} new to the Question Bank · {{ form.questions.length - newCount }} reused from the bank.
          Nothing is saved until you confirm.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <span :class="hasErrors ? 'bg-danger/10 text-danger' : 'bg-success/10 text-success'" class="rounded-lg px-3 py-1.5 text-xs font-bold">
          {{ hasErrors ? `${invalidCount} question${invalidCount === 1 ? '' : 's'} need attention` : 'All questions valid' }}
        </span>
        <Link :href="route('admin.exams.edit', { exam: exam.id, step: 'questions' })" class="text-sm font-semibold text-text-muted hover:text-text-main">Cancel</Link>
      </div>
    </div>

    <!-- ── Mode ── -->
    <section v-if="exam.sections.length" class="mb-5 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
      <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-1">How should this import change the paper?</h2>
      <p class="text-xs text-text-muted mb-4">
        Current paper: {{ exam.sections.map((section) => `${section.name} (${section.question_count})`).join(' · ') }}
      </p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <label v-for="option in modes" :key="option.value"
               class="flex items-start gap-3 rounded-xl border-2 p-3.5 cursor-pointer transition-colors"
               :class="form.mode === option.value ? 'border-primary bg-primary/[0.04]' : 'border-gray-100 hover:border-gray-200'">
          <input v-model="form.mode" type="radio" :value="option.value" class="mt-1 text-primary focus:ring-primary" />
          <span>
            <span class="block text-sm font-semibold text-text-main">{{ option.label }}</span>
            <span class="block text-xs text-text-muted mt-0.5">{{ option.help }}</span>
          </span>
        </label>
      </div>
      <p v-if="form.mode === 'replace' && exam.attempts_count" class="mt-3 text-xs font-semibold text-gold-dark">
        {{ exam.attempts_count }} student attempt(s) exist for this exam. Replacing the paper changes how their results are processed.
      </p>
    </section>

    <form class="space-y-6" @submit.prevent="submit">
      <div v-if="generalError" class="rounded-2xl border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger">
        <span class="font-bold">Import could not be completed.</span> {{ generalError }}
      </div>

      <!-- ── Sections ── -->
      <section v-for="(group, groupIndex) in groups" :key="group.key" class="space-y-3">
        <header class="flex flex-wrap items-end gap-3 bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
          <span class="w-8 h-8 rounded-lg bg-primary text-white font-number text-sm font-bold grid place-items-center">{{ groupIndex + 1 }}</span>
          <div class="flex-1 min-w-[200px]">
            <label class="label">Section name</label>
            <input :value="group.name" type="text" maxlength="120" class="field font-semibold"
                   @change="renameGroup(group, $event.target.value)" />
          </div>
          <div class="text-xs text-text-muted pb-2.5">
            <span class="font-semibold text-text-main">{{ subjectName(group.subjectId) }}</span> · {{ group.rows.length }} question{{ group.rows.length === 1 ? '' : 's' }}
          </div>
          <span class="mb-2 text-[11px] font-bold px-2.5 py-1 rounded-lg"
                :class="destination(group) === 'existing' ? 'bg-royal/10 text-royal' : 'bg-success/10 text-success'">
            {{ destination(group) === 'existing' ? 'Adds to existing section' : 'New section' }}
          </span>
        </header>

        <article v-for="(item, position) in group.rows" :key="item.index"
                 class="bg-white rounded-2xl border shadow-sm overflow-hidden ml-0 md:ml-6"
                 :class="rowErrors(item.question).length ? 'border-danger/50' : 'border-gray-100'">
          <div class="px-5 py-3 border-b flex flex-wrap gap-3 items-center"
               :class="rowErrors(item.question).length ? 'bg-danger/[0.03] border-danger/20' : 'bg-gray-50 border-gray-100'">
            <span class="font-number text-sm font-bold text-primary">#{{ position + 1 }}</span>
            <span class="text-xs text-text-muted">Excel row {{ item.question.source_row }}</span>
            <span v-if="item.question.bank_question" class="text-[11px] font-bold px-2 py-0.5 rounded bg-royal/10 text-royal">From bank #{{ item.question.question_bank_id }}</span>
            <span v-else class="text-[11px] font-bold px-2 py-0.5 rounded bg-success/10 text-success">New question</span>
            <div class="flex-1"></div>
            <select v-if="groups.length > 1" :value="item.question.section" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white"
                    aria-label="Move to section" @change="item.question.section = $event.target.value">
              <option v-for="target in groups" :key="target.key" :value="target.name">{{ target.name }}</option>
            </select>
            <button v-if="hasBankId(item.question) && !item.question.bank_question" type="button"
                    class="text-xs font-bold text-primary hover:underline px-2" @click="item.question.question_bank_id = null">
              Clear ID &amp; write a new question
            </button>
            <button type="button" class="icon" :disabled="position === 0" aria-label="Move up" @click="moveRow(group, position, -1)">↑</button>
            <button type="button" class="icon" :disabled="position === group.rows.length - 1" aria-label="Move down" @click="moveRow(group, position, 1)">↓</button>
            <button type="button" class="text-xs font-bold text-danger hover:text-red-700 px-2" @click="remove(item.index)">Remove</button>
          </div>

          <div v-if="rowErrors(item.question).length" class="mx-5 mt-4 rounded-xl bg-danger/5 border border-danger/15 px-3 py-2 text-xs text-danger">
            <span class="font-bold">Needs correction:</span> {{ rowErrors(item.question).join(' · ') }}
          </div>

          <div v-if="item.question.duplicate_question && !item.question.bank_question && !item.question.keep_as_new"
               class="mx-5 mt-4 rounded-xl border border-gold/30 bg-gold/10 px-3 py-2.5 text-xs text-gold-dark flex flex-wrap items-center gap-2">
            <span class="flex-1 min-w-[200px]"><strong>Looks identical to bank question #{{ item.question.duplicate_of }}.</strong> Reuse it instead of creating a copy?</span>
            <button type="button" class="px-3 py-1.5 rounded-lg bg-white border border-gold/30 font-bold hover:bg-gold/5" @click="useExisting(item.question)">Use existing question</button>
            <button type="button" class="px-3 py-1.5 rounded-lg font-semibold hover:underline" @click="item.question.keep_as_new = true">Keep as new</button>
          </div>

          <!-- Reused bank question: read-only -->
          <div v-if="item.question.bank_question" class="p-5">
            <div class="rich text-sm text-text-main" v-html="item.question.bank_question.question_text"></div>
            <ol class="grid sm:grid-cols-2 gap-2 mt-3">
              <li v-for="option in ['a', 'b', 'c', 'd']" :key="option"
                  class="rounded-lg border px-3 py-2 text-xs flex gap-2"
                  :class="item.question.bank_question.correct_options?.includes(option) ? 'border-success/40 bg-green-50 text-success' : 'border-gray-100 text-text-main'">
                <span class="font-bold uppercase">{{ option }}</span>
                <span class="rich min-w-0" v-html="item.question.bank_question[`option_${option}`]"></span>
              </li>
            </ol>
            <p class="mt-3 text-xs text-text-muted">
              {{ item.question.bank_question.subject?.name }}
              <template v-if="item.question.bank_question.question_category"> · {{ item.question.bank_question.question_category.name }}</template>
              · {{ item.question.bank_question.difficulty }} · +{{ item.question.bank_question.marks }}
              <button v-if="item.question.switched_from_new" type="button" class="ml-2 font-semibold text-primary hover:underline" @click="undoUseExisting(item.question)">Create as a new question instead</button>
            </p>
          </div>

          <ImportQuestionFields v-else :question="item.question" :subjects="subjects" :categories="categories" :class-levels="classLevels"
                                :difficulties="difficulties" :types="types" :show-status="false" :locked-class-id="exam.class_level_id" class="p-5" />
        </article>
      </section>

      <div v-if="!form.questions.length" class="rounded-2xl border-2 border-dashed border-gray-200 bg-white py-12 text-center text-sm text-text-muted">
        Every row was removed. Cancel and upload the workbook again.
      </div>

      <div class="sticky bottom-4 rounded-2xl bg-primary p-4 shadow-lg flex flex-col sm:flex-row items-center justify-between gap-3">
        <p class="text-sm text-white/80">
          {{ form.mode === 'replace' ? 'The current paper will be replaced. Its questions stay in the Question Bank.' : 'Nothing has been saved yet.' }}
        </p>
        <button type="submit" :disabled="hasErrors || form.processing || !form.questions.length"
                class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-accent text-white font-bold text-sm disabled:opacity-50 disabled:cursor-not-allowed">
          {{ form.processing ? 'Importing…' : `Confirm & Import ${form.questions.length} Questions` }}
        </button>
      </div>
    </form>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ImportQuestionFields from '@/Pages/Admin/Questions/Components/ImportQuestionFields.vue';
import { normalizeImportRow, questionRowErrors, useServerRowErrors } from '@/Pages/Admin/Questions/Components/importRows';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({
  exam: { type: Object, required: true },
  questions: { type: Array, required: true },
  subjects: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  classLevels: { type: Array, default: () => [] },
  difficulties: { type: Object, default: () => ({}) },
  types: { type: Object, default: () => ({}) },
});

const modes = [
  { value: 'append', label: 'Add to the current paper', help: 'Rows join the section with the same name and subject; any other section is added after the existing ones.' },
  { value: 'replace', label: 'Replace the current paper', help: 'Removes the current sections from this exam and builds the paper only from this workbook.' },
];

const form = useForm({
  mode: 'append',
  questions: props.questions.map((question) => ({ ...normalizeImportRow(question), keep_as_new: false, switched_from_new: false })),
});

// rowErrors() repeats every upload-time check except the exam's category scope, so only that one is
// carried over (it clears once the row is edited). Bank-ID problems are read-only facts about the bank
// question and are read straight from question.errors in rowErrors().
const serverErrors = useServerRowErrors();
serverErrors.pin(Object.fromEntries(form.questions.flatMap((question, index) => Object.entries(question.errors || {})
  .filter(([field]) => field === 'question_category_id')
  .map(([field, message]) => [`questions.${index}.${field}`, message]))), form.questions);
const hasBankId = (question) => question.question_bank_id !== null && question.question_bank_id !== undefined && question.question_bank_id !== '';

const subjectName = (id) => props.subjects.find((subject) => Number(subject.id) === Number(id))?.name || 'No subject yet';
const effectiveSubject = (question) => Number(question.bank_question ? question.bank_question.subject_id : question.subject_id) || null;
const sectionKey = (name) => String(name ?? '').trim().toLowerCase();

const groups = computed(() => {
  const map = new Map();
  form.questions.forEach((question, index) => {
    const key = sectionKey(question.section);
    if (!map.has(key)) map.set(key, { key, name: String(question.section ?? '').trim(), subjectId: effectiveSubject(question), rows: [] });
    map.get(key).rows.push({ question, index });
  });
  return [...map.values()];
});

const destination = (group) => (form.mode === 'append' && props.exam.sections.some((section) =>
  sectionKey(section.name) === group.key && Number(section.subject_id) === Number(group.subjectId)) ? 'existing' : 'new');

function rowErrors(question) {
  const errors = [];
  if (!String(question.section ?? '').trim()) errors.push('Enter a section name');

  if (question.bank_question) {
    if (form.mode === 'append' && question.in_exam) errors.push('This question is already in the exam');
    if (!question.switched_from_new && question.errors?.question_bank_id) errors.push(question.errors.question_bank_id);
  } else if (hasBankId(question)) {
    errors.push(question.errors?.question_bank_id || 'Bank question not found');
  } else {
    errors.push(...questionRowErrors(question, props.categories));
    if (!question.class_level_ids.includes(Number(props.exam.class_level_id))) errors.push(`Include ${props.exam.class_label}`);
  }

  const group = groups.value.find((item) => item.key === sectionKey(question.section));
  if (group?.subjectId && effectiveSubject(question) && group.subjectId !== effectiveSubject(question)) {
    errors.push(`Section "${group.name}" uses ${subjectName(group.subjectId)} — every question in a section needs the same subject`);
  }

  return [...new Set([...errors, ...serverErrors.errorsFor(question)])];
}

const invalidCount = computed(() => form.questions.filter((question) => rowErrors(question).length).length);
const hasErrors = computed(() => invalidCount.value > 0);
const newCount = computed(() => form.questions.filter((question) => !question.bank_question).length);
const generalError = computed(() => Object.entries(form.errors).find(([key]) => !key.startsWith('questions.'))?.[1]);

function renameGroup(group, value) {
  const name = value.trim();
  if (!name) return;
  group.rows.forEach(({ question }) => { question.section = name; });
}

function moveRow(group, position, direction) {
  const from = group.rows[position].index;
  const to = group.rows[position + direction].index;
  const [row] = form.questions.splice(from, 1);
  form.questions.splice(to, 0, row);
}

const remove = (index) => form.questions.splice(index, 1);

function useExisting(question) {
  question.question_bank_id = question.duplicate_of;
  question.bank_question = question.duplicate_question;
  question.in_exam = props.exam.question_ids.includes(question.duplicate_of);
  question.switched_from_new = true;
}

function undoUseExisting(question) {
  question.question_bank_id = null;
  question.bank_question = null;
  question.in_exam = false;
  question.switched_from_new = false;
  question.keep_as_new = true;
}

async function submit() {
  if (hasErrors.value || !form.questions.length) return;

  if (form.mode === 'replace' && !(await confirmDialog({
    title: 'Replace the current paper?',
    message: `The exam's ${props.exam.sections.length} current section(s) will be removed and rebuilt from this workbook. Their questions stay in the Question Bank.`,
    confirmText: 'Replace paper',
    tone: 'warning',
  }))) return;

  form.clearErrors();
  form.post(route('admin.exams.import.store', props.exam.id), {
    preserveScroll: true,
    onError: (errors) => {
      serverErrors.pin(errors, form.questions);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
  });
}
</script>

<style scoped>
.label { @apply block mb-1.5 text-xs font-semibold text-text-muted; }
.field { @apply w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-text-main focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/20; }
.icon { @apply w-7 h-7 grid place-items-center rounded-lg text-sm text-text-muted hover:bg-white hover:text-text-main disabled:opacity-30; }
.rich :deep(p) { margin: 0; }
.rich :deep(img) { max-height: 140px; max-width: 100%; border-radius: 8px; margin: .25rem 0; }
</style>
