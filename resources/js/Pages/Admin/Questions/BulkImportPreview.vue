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

        <ImportQuestionFields :question="question" :subjects="subjects" :categories="categories" :class-levels="classLevels"
                              :difficulties="difficulties" :types="types" class="p-5" />
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
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ImportQuestionFields from './Components/ImportQuestionFields.vue';
import { normalizeImportRow, questionRowErrors, useServerRowErrors } from './Components/importRows';

const props = defineProps({
  questions: Array,
  subjects: Array,
  categories: Array,
  classLevels: Array,
  difficulties: Object,
  types: Object,
});

const form = useForm({
  questions: props.questions.map(normalizeImportRow),
});

const serverErrors = useServerRowErrors();
const rowErrors = (question) => [...new Set([...questionRowErrors(question, props.categories), ...serverErrors.errorsFor(question)])];
const invalidCount = computed(() => form.questions.filter((question) => rowErrors(question).length).length);
const hasErrors = computed(() => invalidCount.value > 0);

const remove = (index) => form.questions.splice(index, 1);
const submit = () => {
  if (!hasErrors.value && form.questions.length) {
    form.clearErrors();
    form.post(route('admin.questions.import.store'), {
      preserveScroll: true,
      onError: (errors) => {
        serverErrors.pin(errors, form.questions);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      },
    });
  }
};
</script>
