<template>
  <AdminLayout :title="exam.name" :subtitle="`${exam.exam_code} · Exam builder`">
    <ExamForm
      mode="edit"
      :exam="exam"
      :subjects="subjects"
      :categories="categories"
      :class-levels="classLevels"
      :statuses="statuses"
      :scoring-modes="scoringModes"
      :difficulties="difficulties"
      :types="types"
    />
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ExamForm from './Components/ExamForm.vue';

defineProps({
  exam: Object,
  subjects: Array,
  categories: Array,
  classLevels: Array,
  statuses: Object,
  scoringModes: Object,
  difficulties: Object,
  types: Object,
});
</script>
