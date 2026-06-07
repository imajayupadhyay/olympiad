<template>
  <AdminLayout title="Create Exam" subtitle="Configure schedule, scoring, and assigned questions">
    <ExamForm
      mode="create"
      :subjects="subjects"
      :categories="categories"
      :class-levels="classLevels"
      :statuses="statuses"
      :scoring-modes="scoringModes"
      :difficulties="difficulties"
      :available-questions="availableQuestions"
      :question-filters="questionFilters"
      :assigned-questions="assignedQuestions"
    />
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ExamForm from './Components/ExamForm.vue';

defineProps({
  subjects: Array,
  categories: Array,
  classLevels: Array,
  statuses: Object,
  scoringModes: Object,
  difficulties: Object,
  availableQuestions: Object,
  questionFilters: Object,
  assignedQuestions: Array,
});
</script>
