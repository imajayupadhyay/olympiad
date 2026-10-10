<template>
  <AdminLayout title="Create Exam" subtitle="Start with the details — sections and questions come next">
    <ExamForm
      mode="create"
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
  subjects: Array,
  categories: Array,
  classLevels: Array,
  statuses: Object,
  scoringModes: Object,
  difficulties: Object,
  types: Object,
});
</script>
