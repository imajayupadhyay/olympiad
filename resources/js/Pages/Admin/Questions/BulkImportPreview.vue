<template>
  <AdminLayout title="Review Bulk Import" subtitle="Review, correct and confirm every question before anything is saved">
    <div class="mb-5 rounded-2xl border border-primary/10 bg-primary/[0.03] p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="font-heading font-bold text-text-main">{{ form.questions.length }} questions ready for review</p>
        <p class="text-sm text-text-muted mt-0.5">{{ form.processing ? 'Saving the reviewed questions to the question bank…' : 'Changes stay in this review until you confirm the import.' }}</p>
      </div>
      <div class="flex items-center gap-2">
        <span :class="hasErrors ? 'bg-danger/10 text-danger' : 'bg-success/10 text-success'" class="rounded-lg px-3 py-1.5 text-xs font-bold">
          {{ hasErrors ? `${invalidCount} question${invalidCount === 1 ? '' : 's'} need attention` : 'All questions valid' }}
        </span>
        <Link :href="route('admin.questions.index')" class="text-sm font-semibold text-text-muted hover:text-text-main">Cancel</Link>
      </div>
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <div v-if="form.errors.questions" class="rounded-2xl border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger">
        <span class="font-bold">Import could not be completed.</span> {{ form.errors.questions }}
      </div>

      <section v-for="(question, index) in form.questions" :key="index" class="bg-white rounded-2xl border shadow-sm overflow-hidden"
               :class="rowErrors(question).length ? 'border-danger/50' : 'border-gray-100'">
        <header class="px-5 py-3 border-b flex flex-wrap gap-3 items-center justify-between"
                :class="rowErrors(question).length ? 'bg-danger/[0.03] border-danger/20' : 'bg-gray-50 border-gray-100'">
          <div class="flex items-center gap-3">
            <span class="font-number text-sm font-bold text-primary">#{{ index + 1 }}</span>
            <span class="text-xs text-text-muted">Excel row {{ question.source_row || index + 1 }}</span>
          </div>
          <button type="button" @click="remove(index)" class="text-xs font-bold text-danger hover:text-red-700">Remove</button>
        </header>

        <div v-if="rowErrors(question).length" class="mx-5 mt-4 rounded-xl bg-danger/5 border border-danger/15 px-3 py-2 text-xs text-danger">
          <span class="font-bold">Needs correction:</span> {{ rowErrors(question).join(' · ') }}
        </div>

        <div class="p-5 grid grid-cols-1 lg:grid-cols-12 gap-4">
          <div class="lg:col-span-4">
            <label class="label">Subject *</label>
            <select v-model.number="question.subject_id" class="field">
              <option value="">Choose subject</option>
              <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
            </select>
          </div>
          <div class="lg:col-span-4">
            <label class="label">Category</label>
            <select v-model.number="question.question_category_id" class="field">
              <option value="">Uncategorized</option>
              <option v-for="category in categoriesFor(question)" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
          </div>
          <div class="lg:col-span-4">
            <label class="label">Class levels *</label>
            <div class="flex flex-wrap gap-2 pt-1">
              <button v-for="level in classLevels" :key="level.id" type="button" @click="toggleClass(question, level.id)"
                      class="px-2.5 py-1.5 text-xs rounded-lg border font-semibold"
                      :class="question.class_level_ids.includes(level.id) ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 text-text-muted'">
                {{ level.label }}
              </button>
            </div>
          </div>

          <div class="lg:col-span-12">
            <label class="label">Question *</label>
            <textarea v-model="question.question_text" rows="3" class="field resize-y" />
          </div>
          <div v-for="option in ['a', 'b', 'c', 'd']" :key="option" class="lg:col-span-3">
            <label class="label">Option {{ option.toUpperCase() }} *</label>
            <textarea v-model="question[`option_${option}`]" rows="3" class="field resize-y" />
          </div>

          <div class="lg:col-span-3">
            <label class="label">Difficulty *</label>
            <select v-model="question.difficulty" class="field">
              <option v-for="(label, value) in difficulties" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="lg:col-span-3">
            <label class="label">Question type *</label>
            <select v-model="question.question_type" class="field" @change="normalizeCorrect(question)">
              <option v-for="(label, value) in types" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="lg:col-span-3">
            <label class="label">Correct option(s) *</label>
            <div class="flex gap-1.5 pt-1">
              <button v-for="option in ['a', 'b', 'c', 'd']" :key="option" type="button" @click="toggleCorrect(question, option)"
                      class="w-8 h-8 rounded-lg border text-xs font-bold uppercase"
                      :class="question.correct_options.includes(option) ? 'border-success bg-success text-white' : 'border-gray-200 text-text-muted'">
                {{ option }}
              </button>
            </div>
          </div>
          <div class="lg:col-span-1">
            <label class="label">Marks *</label>
            <input v-model.number="question.marks" type="number" min="1" max="10" class="field" />
          </div>
          <div class="lg:col-span-2">
            <label class="label">Negative</label>
            <input v-model.number="question.negative_marks" type="number" min="0" max="5" step="0.25" class="field" />
          </div>
          <div class="lg:col-span-12">
            <label class="label">Explanation</label>
            <textarea v-model="question.explanation" rows="2" class="field resize-y" />
          </div>
          <div class="lg:col-span-3">
            <label class="label">Status</label>
            <select v-model="question.is_active" class="field">
              <option :value="true">Active</option>
              <option :value="false">Inactive</option>
            </select>
          </div>
        </div>
      </section>

      <div class="sticky bottom-4 rounded-2xl bg-primary p-4 shadow-lg flex flex-col sm:flex-row items-center justify-between gap-3">
        <p class="text-sm text-white/80">Nothing has been saved yet.</p>
        <button type="submit" :disabled="hasErrors || form.processing || form.questions.length === 0"
                class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-accent text-white font-bold text-sm disabled:opacity-50 disabled:cursor-not-allowed">
          {{ form.processing ? 'Importing…' : `Confirm & Import ${form.questions.length} Questions` }}
        </button>
      </div>
    </form>
  </AdminLayout>
</template>

<script setup>
import { computed, shallowRef, toRaw } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  questions: Array,
  subjects: Array,
  categories: Array,
  classLevels: Array,
  difficulties: Object,
  types: Object,
});

const form = useForm({
  questions: props.questions.map((question) => ({
    ...question,
    subject_id: question.subject_id ? Number(question.subject_id) : '',
    question_category_id: question.question_category_id ? Number(question.question_category_id) : '',
    class_level_ids: (question.class_level_ids || []).map(Number),
    correct_options: question.correct_options || [],
    is_active: Boolean(question.is_active),
  })),
});

// Server errors are pinned to the row object plus a snapshot of it, so they survive row removal
// and disappear as soon as that row is edited (otherwise they would block re-submission forever).
const serverErrors = shallowRef(new WeakMap());
const snapshot = (question) => JSON.stringify(question);
const serverErrorsFor = (question) => {
  const entry = serverErrors.value.get(toRaw(question));

  return entry && entry.snapshot === snapshot(question) ? entry.messages : [];
};

const categoriesFor = (question) => (props.categories || []).filter((category) => Number(category.subject_id) === Number(question.subject_id));
const required = (value) => String(value ?? '').trim() !== '';
const rowErrors = (question) => {
  const errors = [];
  if (!question.subject_id) errors.push('Choose a subject');
  if (!question.class_level_ids.length) errors.push('Choose at least one class level');
  if (question.question_category_id && !categoriesFor(question).some((category) => Number(category.id) === Number(question.question_category_id))) errors.push('Category must belong to the subject');
  if (!['easy', 'medium', 'hard'].includes(question.difficulty)) errors.push('Choose a valid difficulty');
  if (!['single', 'multiple'].includes(question.question_type)) errors.push('Choose a valid question type');
  if (!required(question.question_text)) errors.push('Question text is required');
  for (const option of ['a', 'b', 'c', 'd']) if (!required(question[`option_${option}`])) errors.push(`Option ${option.toUpperCase()} is required`);
  if (!question.correct_options.length) errors.push('Choose the correct option');
  if (question.question_type === 'single' && question.correct_options.length !== 1) errors.push('Single-correct questions need one answer');
  if (!Number.isInteger(Number(question.marks)) || Number(question.marks) < 1 || Number(question.marks) > 10) errors.push('Marks must be between 1 and 10');
  if (required(question.negative_marks) && (Number.isNaN(Number(question.negative_marks)) || Number(question.negative_marks) < 0 || Number(question.negative_marks) > 5)) errors.push('Negative marks must be between 0 and 5');

  return [...new Set([...errors, ...serverErrorsFor(question)])];
};
const invalidCount = computed(() => form.questions.filter((question) => rowErrors(question).length).length);
const hasErrors = computed(() => invalidCount.value > 0);

const toggleClass = (question, id) => {
  question.class_level_ids = question.class_level_ids.includes(id)
    ? question.class_level_ids.filter((current) => current !== id)
    : [...question.class_level_ids, id];
};
const toggleCorrect = (question, option) => {
  if (question.question_type === 'single') {
    question.correct_options = [option];
  } else {
    question.correct_options = question.correct_options.includes(option)
      ? question.correct_options.filter((current) => current !== option)
      : [...question.correct_options, option];
  }
};
const normalizeCorrect = (question) => {
  if (question.question_type === 'single' && question.correct_options.length > 1) question.correct_options = [question.correct_options[0]];
};
const remove = (index) => form.questions.splice(index, 1);
const submit = () => {
  if (!hasErrors.value && form.questions.length) {
    form.clearErrors();
    form.post(route('admin.questions.import.store'), {
      preserveScroll: true,
      onError: (errors) => {
        const pinned = new WeakMap();
        form.questions.forEach((question, index) => {
          const messages = Object.entries(errors)
            .filter(([key]) => key.startsWith(`questions.${index}.`))
            .map(([, message]) => message);
          if (messages.length) pinned.set(toRaw(question), { snapshot: snapshot(question), messages });
        });
        serverErrors.value = pinned;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      },
    });
  }
};
</script>

<style scoped>
.label { @apply block mb-1.5 text-xs font-semibold text-text-muted; }
.field { @apply w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-text-main focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/20; }
</style>
