<template>
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
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
        <option v-for="category in categoriesForRow(categories, question)" :key="category.id" :value="category.id">{{ category.name }}</option>
      </select>
    </div>
    <div class="lg:col-span-4">
      <label class="label">Class levels *</label>
      <div class="flex flex-wrap gap-2 pt-1">
        <button v-for="level in classLevels" :key="level.id" type="button" @click="toggleClass(level.id)"
                :disabled="Number(level.id) === Number(lockedClassId)"
                :title="Number(level.id) === Number(lockedClassId) ? 'The exam\'s class is always included' : ''"
                class="px-2.5 py-1.5 text-xs rounded-lg border font-semibold disabled:cursor-not-allowed"
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
      <select v-model="question.question_type" class="field" @change="normalizeCorrect">
        <option v-for="(label, value) in types" :key="value" :value="value">{{ label }}</option>
      </select>
    </div>
    <div class="lg:col-span-3">
      <label class="label">Correct option(s) *</label>
      <div class="flex gap-1.5 pt-1">
        <button v-for="option in ['a', 'b', 'c', 'd']" :key="option" type="button" @click="toggleCorrect(option)"
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
    <div v-if="showStatus" class="lg:col-span-3">
      <label class="label">Status</label>
      <select v-model="question.is_active" class="field">
        <option :value="true">Active</option>
        <option :value="false">Inactive</option>
      </select>
    </div>
  </div>
</template>

<script setup>
import { categoriesForRow } from './importRows';

// Edits the row in place: rows are reactive objects owned by the parent's import form.
const props = defineProps({
  question: { type: Object, required: true },
  subjects: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  classLevels: { type: Array, default: () => [] },
  difficulties: { type: Object, default: () => ({}) },
  types: { type: Object, default: () => ({}) },
  showStatus: { type: Boolean, default: true },
  lockedClassId: { type: [Number, String], default: null },
});

const toggleClass = (id) => {
  if (Number(id) === Number(props.lockedClassId)) return;
  const ids = props.question.class_level_ids;
  props.question.class_level_ids = ids.includes(id) ? ids.filter((current) => current !== id) : [...ids, id];
};

const toggleCorrect = (option) => {
  const current = props.question.correct_options;
  props.question.correct_options = props.question.question_type === 'single'
    ? [option]
    : (current.includes(option) ? current.filter((value) => value !== option) : [...current, option]);
};

const normalizeCorrect = () => {
  if (props.question.question_type === 'single' && props.question.correct_options.length > 1) {
    props.question.correct_options = [props.question.correct_options[0]];
  }
};
</script>

<style scoped>
.label { @apply block mb-1.5 text-xs font-semibold text-text-muted; }
.field { @apply w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-text-main focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/20; }
</style>
