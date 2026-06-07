<template>
  <AdminLayout :title="`Edit ${exam.name}`" :subtitle="exam.exam_code">
    <ExamForm
      mode="edit"
      :exam="exam"
      :subjects="subjects"
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
  exam: Object,
  subjects: Array,
  classLevels: Array,
  statuses: Object,
  scoringModes: Object,
  difficulties: Object,
  availableQuestions: Object,
  questionFilters: Object,
  assignedQuestions: Array,
});
</script>
