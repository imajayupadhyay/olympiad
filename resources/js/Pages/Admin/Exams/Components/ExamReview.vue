<template>
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-start">
    <div class="xl:col-span-2 space-y-5">
      <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider">Paper structure</h2>
          <button type="button" class="text-xs font-semibold text-primary hover:underline" @click="$emit('go', 'questions')">Edit sections</button>
        </div>
        <div v-if="!sections.length" class="p-8 text-center text-sm text-text-muted">No sections yet.</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-gray-50 text-[11px] uppercase tracking-wider text-text-muted">
            <tr>
              <th class="text-left font-semibold px-5 py-2.5">Section</th>
              <th class="text-left font-semibold px-3 py-2.5 hidden sm:table-cell">Bank subject</th>
              <th class="text-right font-semibold px-3 py-2.5">Questions</th>
              <th class="text-right font-semibold px-5 py-2.5">Marks</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="(row, index) in rows" :key="row.key">
              <td class="px-5 py-3">
                <span class="font-number text-text-muted mr-2">{{ index + 1 }}.</span>
                <span class="font-semibold text-text-main">{{ row.name || 'Untitled section' }}</span>
                <span v-if="row.range" class="block text-[11px] text-text-muted font-number ml-5">{{ row.range }}</span>
              </td>
              <td class="px-3 py-3 text-text-muted hidden sm:table-cell">{{ row.subject }}</td>
              <td class="px-3 py-3 text-right font-number" :class="row.count ? 'text-text-main' : 'text-danger font-bold'">{{ row.count }}</td>
              <td class="px-5 py-3 text-right font-number text-text-main">{{ formatMarks(row.marks) }}</td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-50 font-semibold">
            <tr>
              <td class="px-5 py-3 text-text-main" colspan="2">Total</td>
              <td class="px-3 py-3 text-right font-number text-text-main">{{ totalQuestions }}</td>
              <td class="px-5 py-3 text-right font-number text-text-main">{{ formatMarks(totalMarks) }}</td>
            </tr>
          </tfoot>
        </table>
      </section>

      <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-4">Exam at a glance</h2>
        <dl class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
          <div v-for="fact in facts" :key="fact.label">
            <dt class="text-[11px] uppercase tracking-wider text-text-muted font-semibold">{{ fact.label }}</dt>
            <dd class="mt-0.5 text-text-main font-medium">{{ fact.value }}</dd>
          </div>
        </dl>
      </section>
    </div>

    <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
      <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-4">Publish checklist</h2>
      <ul class="space-y-2.5">
        <li v-for="check in checks" :key="check.label" class="flex items-start gap-2.5 text-sm">
          <span class="mt-0.5 w-5 h-5 shrink-0 rounded-full grid place-items-center text-[11px] font-bold"
                :class="check.ok ? 'bg-success/10 text-success' : (check.blocking ? 'bg-danger/10 text-danger' : 'bg-gold/15 text-gold-dark')">
            {{ check.ok ? '✓' : (check.blocking ? '!' : 'i') }}
          </span>
          <span>
            <span :class="check.ok ? 'text-text-main' : (check.blocking ? 'text-danger font-semibold' : 'text-text-main')">{{ check.label }}</span>
            <button v-if="!check.ok && check.step" type="button" class="block text-xs text-primary font-semibold hover:underline mt-0.5" @click="$emit('go', check.step)">Fix this →</button>
          </span>
        </li>
      </ul>
      <p class="mt-5 text-xs text-text-muted">
        Status: <strong class="text-text-main">{{ statuses[status] || status }}</strong>.
        {{ blockingCount ? 'Resolve the red items to publish.' : 'Ready to publish.' }}
      </p>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatMarks, marksFor } from './examBuilder';

const props = defineProps({
  exam: { type: Object, required: true },
  sections: { type: Array, required: true },
  questionCache: { type: Object, required: true },
  checks: { type: Array, required: true },
  subjects: { type: Array, default: () => [] },
  classLevels: { type: Array, default: () => [] },
  statuses: { type: Object, default: () => ({}) },
  status: { type: String, default: 'draft' },
});

defineEmits(['go']);

const subjectName = (id) => props.subjects.find((subject) => Number(subject.id) === Number(id))?.name || '—';
const blockingCount = computed(() => props.checks.filter((check) => !check.ok && check.blocking).length);

const rows = computed(() => {
  let offset = 0;
  return props.sections.map((section) => {
    const count = section.question_ids.length;
    const range = count ? `Q${offset + 1}–Q${offset + count}` : '';
    offset += count;
    return {
      key: section.key,
      name: section.name,
      subject: subjectName(section.subject_id),
      count,
      range,
      marks: section.question_ids.reduce((sum, id) => sum + marksFor(props.exam, section, props.questionCache[id]), 0),
    };
  });
});
const totalQuestions = computed(() => rows.value.reduce((sum, row) => sum + row.count, 0));
const totalMarks = computed(() => rows.value.reduce((sum, row) => sum + row.marks, 0));

const formatDate = (value) => (value ? new Date(value).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : 'Not set');
const facts = computed(() => [
  { label: 'Olympiad subject', value: subjectName(props.exam.subject_id) },
  { label: 'Class', value: props.classLevels.find((level) => Number(level.id) === Number(props.exam.class_level_id))?.label || '—' },
  { label: 'Duration', value: `${props.exam.duration_minutes || 0} minutes` },
  { label: 'Opens', value: formatDate(props.exam.starts_at) },
  { label: 'Closes', value: formatDate(props.exam.ends_at) },
  { label: 'Fee', value: Number(props.exam.fee_amount) ? `${props.exam.fee_currency} ${props.exam.fee_amount}` : 'Free' },
  { label: 'Negative marking', value: props.exam.negative_marking_enabled ? 'On' : 'Off' },
  { label: 'Shuffle', value: [props.exam.randomize_questions && 'Questions (within sections)', props.exam.randomize_options && 'Options'].filter(Boolean).join(', ') || 'Off' },
  { label: 'Results', value: props.exam.show_result_immediately ? 'Immediately' : formatDate(props.exam.result_release_at) },
]);
</script>
