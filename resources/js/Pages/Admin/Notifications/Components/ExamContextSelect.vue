<template>
  <label class="block">
    <span class="block text-[11px] font-semibold text-text-muted uppercase tracking-wider mb-1.5">Exam for placeholders <span class="normal-case font-normal">(optional)</span></span>
    <select :value="modelValue || ''" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main"
            @change="$emit('update:modelValue', $event.target.value ? Number($event.target.value) : null)">
      <option value="">None — exam fields unavailable</option>
      <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}{{ e.class ? ` · ${e.class}` : '' }}</option>
    </select>
    <span class="block text-[11px] text-text-muted mt-1">Fills {{ '{' }}{exam_name}}, {{ '{' }}{exam_window}}, {{ '{' }}{course_name}}… A student’s personal (rescheduled) window is used when they have one.</span>
  </label>
</template>

<script setup>
defineProps({
  modelValue: { type: Number, default: null },
  exams: { type: Array, required: true },
});
defineEmits(['update:modelValue']);
</script>
