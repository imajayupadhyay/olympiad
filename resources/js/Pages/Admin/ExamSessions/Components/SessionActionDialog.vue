<script setup>
// One dialog for every Ongoing Exams action. The fields shown depend on the action;
// the server (ExamSessionService) re-checks everything inside a row lock.
import { computed, onMounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  action: { type: String, required: true },
  // A session row, a not-started enrolment row, or an exam group (bulk actions).
  target: { type: Object, required: true },
  limits: { type: Object, default: () => ({ max_minutes: 180, max_strikes: 3 }) },
});
const emit = defineEmits(['close']);

const defaultWindow = () => {
  const start = new Date(Date.now() + 10 * 60 * 1000);
  start.setSeconds(0, 0);
  const minutes = Math.max(60, (props.target.exam?.duration_minutes ?? props.target.duration_minutes ?? 60) + 30);
  const end = new Date(start.getTime() + minutes * 60 * 1000);
  const local = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
  return [local(start), local(end)];
};
const [ws, we] = defaultWindow();

const lostMinutes = Math.ceil((props.target.lost_seconds ?? 0) / 60);

const CONFIG = {
  suspend: { title: 'Pause this session', tone: 'warning', submit: 'Pause session', reason: true, body: 'The student\'s timer stops and they see a "paused" screen. Their answers stay saved. Resume them when the issue is sorted.' },
  resume: { title: 'Resume this session', tone: 'primary', submit: 'Resume session', reason: false, body: 'The timer continues from where it stopped. Paused time is not counted against the student.' },
  add_time: { title: 'Add time', tone: 'primary', submit: 'Add time', reason: false, minutes: lostMinutes || 10, body: 'Extends this student\'s deadline. Their timer updates within about 30 seconds.' },
  restore_time: { title: 'Restore lost time', tone: 'primary', submit: 'Add time back', reason: false, minutes: Math.max(1, lostMinutes), body: `This student was offline for about ${lostMinutes} minute(s) that haven't been given back yet.` },
  reopen: { title: 'Reopen this exam', tone: 'warning', submit: 'Reopen with answers kept', reason: true, minutes: props.target.suggested_reopen_minutes ?? 10, clear: props.target.status === 'auto_submitted', body: 'The student continues where they stopped, with every saved answer. The time below is pre-filled with what they had left when the session dropped.' },
  reset: { title: 'Reset — start from scratch', tone: 'danger', submit: 'Reset session', reason: true, window: 'optional', body: 'Deletes this attempt so the student starts again from question 1 with the full time. Their current answers are archived in the session timeline, not lost.' },
  reschedule: { title: 'Reschedule this student', tone: 'warning', submit: 'Reschedule', reason: true, window: 'required', mode: true, body: 'Give this student their own exam window.' },
  force_submit: { title: 'Submit this exam now', tone: 'danger', submit: 'Submit now', reason: true, body: 'Ends the session and scores it with the answers saved so far. You can reopen it later if needed (until results are released).' },
  clear_violations: { title: 'Clear tab-switch warnings', tone: 'primary', submit: 'Clear warnings', reason: true, notify: false, body: `Resets the warning count to 0. The test auto-submits after ${props.limits.max_strikes} warnings.` },
  enrollment_reschedule: { title: 'Give a personal exam window', tone: 'primary', submit: 'Set window', reason: true, window: 'required', body: 'This student never started. They can start the exam any time inside this window and get the full duration.' },
  bulk_suspend: { title: 'Pause every open session', tone: 'warning', submit: 'Pause all', reason: true, body: 'For whole-exam incidents (e.g. a server outage). Every open session of this exam stops its timer.' },
  bulk_resume: { title: 'Resume every paused session', tone: 'primary', submit: 'Resume all', reason: false, body: 'Every session paused by an admin continues. Rescheduled sessions are left alone.' },
  bulk_add_time: { title: 'Add time to every open session', tone: 'primary', submit: 'Add time to all', reason: false, minutes: 10, body: 'Extends the deadline of every open session of this exam.' },
};
const cfg = computed(() => CONFIG[props.action]);

const form = useForm({
  reason: '',
  notify: CONFIG[props.action].notify ?? true,
  minutes: CONFIG[props.action].minutes ?? null,
  clear_violations: CONFIG[props.action].clear ?? false,
  mode: 'resume',
  window_starts_at: CONFIG[props.action].window === 'required' ? ws : '',
  window_ends_at: CONFIG[props.action].window === 'required' ? we : '',
});
const giveWindow = ref(false);
const serverError = ref('');

const url = computed(() => {
  const id = props.target.id;
  return {
    suspend: route('admin.exam-sessions.suspend', id),
    resume: route('admin.exam-sessions.resume', id),
    add_time: route('admin.exam-sessions.add-time', id),
    restore_time: route('admin.exam-sessions.add-time', id),
    reopen: route('admin.exam-sessions.reopen', id),
    reset: route('admin.exam-sessions.reset', id),
    reschedule: route('admin.exam-sessions.reschedule', id),
    force_submit: route('admin.exam-sessions.force-submit', id),
    clear_violations: route('admin.exam-sessions.clear-violations', id),
    enrollment_reschedule: route('admin.exam-sessions.enrollments.reschedule', id),
    bulk_suspend: route('admin.exam-sessions.bulk', { exam: id, action: 'suspend' }),
    bulk_resume: route('admin.exam-sessions.bulk', { exam: id, action: 'resume' }),
    bulk_add_time: route('admin.exam-sessions.bulk', { exam: id, action: 'add_time' }),
  }[props.action];
});

const who = computed(() => props.target.student?.name
  ? `${props.target.student.name} · ${props.target.exam?.name ?? ''}`
  : `${props.target.name} (${props.target.exam_code})`);

function submit() {
  serverError.value = '';
  form.transform((data) => {
    const out = { reason: data.reason, notify: data.notify };
    if (cfg.value.minutes !== undefined) out.minutes = data.minutes;
    if (props.action === 'reopen') out.clear_violations = data.clear_violations;
    if (cfg.value.mode) out.mode = data.mode;
    if (cfg.value.window === 'required' || (cfg.value.window === 'optional' && giveWindow.value)) {
      out.window_starts_at = data.window_starts_at;
      out.window_ends_at = data.window_ends_at;
    }
    return out;
  }).post(url.value, {
    preserveScroll: true,
    // A refused action (state changed, result released…) comes back as a flash error.
    onSuccess: (page) => { if (page.props.flash?.error) serverError.value = page.props.flash.error; else emit('close'); },
  });
}

const reasonRef = ref(null);
onMounted(() => reasonRef.value?.focus());
const submitClass = computed(() => ({
  danger: 'bg-danger hover:bg-red-700',
  warning: 'bg-accent hover:bg-accent-dark',
  primary: 'bg-primary hover:bg-primary-light',
}[cfg.value.tone]));
const onKey = (e) => { if (e.key === 'Escape' && !form.processing) emit('close'); };
</script>

<template>
  <div class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-primary-dark/55 backdrop-blur-[3px]"
       role="dialog" aria-modal="true" :aria-label="cfg.title" @keydown="onKey" @click.self="!form.processing && emit('close')">
    <form class="bg-white w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[92vh] overflow-auto" @submit.prevent="submit">
      <div class="px-5 pt-5 pb-3 border-b border-gray-100">
        <h3 class="font-heading font-bold text-text-main text-base">{{ cfg.title }}</h3>
        <p class="text-text-muted text-xs mt-0.5 truncate">{{ who }}</p>
      </div>

      <div class="px-5 py-4 space-y-4">
        <p class="text-sm text-text-main/80 leading-relaxed">{{ cfg.body }}</p>

        <p v-if="action === 'reopen' && target.result === 'processed'" class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2">
          This student's result is already processed (not released). Reopening removes it — process results again after they submit.
        </p>

        <!-- reschedule mode -->
        <div v-if="cfg.mode" class="grid gap-2">
          <label class="flex gap-3 items-start p-3 rounded-xl border cursor-pointer" :class="form.mode === 'resume' ? 'border-primary bg-primary/5' : 'border-gray-200'">
            <input v-model="form.mode" type="radio" value="resume" class="mt-0.5 text-primary focus:ring-primary" />
            <span class="text-sm"><strong class="block text-text-main">Resume later</strong><span class="text-text-muted text-xs">Pauses now. In the new window they continue where they left off, with their answers and remaining time.</span></span>
          </label>
          <label class="flex gap-3 items-start p-3 rounded-xl border cursor-pointer" :class="form.mode === 'fresh' ? 'border-danger bg-red-50/50' : 'border-gray-200'">
            <input v-model="form.mode" type="radio" value="fresh" class="mt-0.5 text-danger focus:ring-danger" />
            <span class="text-sm"><strong class="block text-text-main">Start from scratch</strong><span class="text-text-muted text-xs">Resets the attempt (answers archived). In the new window they start again with the full time.</span></span>
          </label>
        </div>

        <!-- minutes -->
        <div v-if="cfg.minutes !== undefined">
          <label class="label">Minutes</label>
          <div class="flex flex-wrap items-center gap-2">
            <button v-for="m in [5, 10, 15, 30]" :key="m" type="button" @click="form.minutes = m"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                    :class="form.minutes === m ? 'bg-primary text-white border-primary' : 'border-gray-200 text-text-main hover:border-primary'">+{{ m }}</button>
            <input v-model.number="form.minutes" type="number" min="1" :max="limits.max_minutes" class="field w-24 font-number" aria-label="Minutes" />
          </div>
          <p v-if="form.errors.minutes" class="error">{{ form.errors.minutes }}</p>
        </div>

        <label v-if="action === 'reopen'" class="flex items-center gap-2 text-sm text-text-main">
          <input v-model="form.clear_violations" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
          Clear tab-switch warnings ({{ target.violation_count }}/{{ limits.max_strikes }})
        </label>

        <!-- window -->
        <label v-if="cfg.window === 'optional'" class="flex items-center gap-2 text-sm text-text-main">
          <input v-model="giveWindow" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
          Give a new window (required if the exam window has closed)
        </label>
        <div v-if="cfg.window === 'required' || (cfg.window === 'optional' && giveWindow)" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="label">Window opens</label>
            <input v-model="form.window_starts_at" type="datetime-local" class="field" />
            <p v-if="form.errors.window_starts_at" class="error">{{ form.errors.window_starts_at }}</p>
          </div>
          <div>
            <label class="label">Window closes</label>
            <input v-model="form.window_ends_at" type="datetime-local" class="field" />
            <p v-if="form.errors.window_ends_at" class="error">{{ form.errors.window_ends_at }}</p>
          </div>
        </div>

        <!-- reason -->
        <div>
          <label class="label">Reason {{ cfg.reason ? '*' : '(optional)' }}</label>
          <textarea ref="reasonRef" v-model="form.reason" rows="2" maxlength="500" class="field resize-none"
                    placeholder="e.g. Student's internet dropped at 10:42, confirmed on call"></textarea>
          <p v-if="form.errors.reason" class="error">{{ form.errors.reason }}</p>
          <p class="text-[11px] text-text-muted mt-1">Saved in the session timeline with your name.</p>
        </div>

        <label v-if="cfg.notify !== false" class="flex items-center gap-2 text-sm text-text-main">
          <input v-model="form.notify" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
          Notify the student (bell notification)
        </label>

        <p v-if="form.errors.mode" class="error">{{ form.errors.mode }}</p>
        <p v-if="serverError" role="alert" class="text-sm bg-red-50 border border-red-200 text-danger rounded-xl px-3 py-2">{{ serverError }}</p>
      </div>

      <div class="px-5 py-3 border-t border-gray-100 flex gap-2 justify-end sticky bottom-0 bg-white">
        <button type="button" :disabled="form.processing" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-text-main hover:bg-gray-100" @click="emit('close')">Cancel</button>
        <button type="submit" :disabled="form.processing" class="px-5 py-2.5 rounded-xl text-white text-sm font-bold transition-colors disabled:opacity-50" :class="submitClass">
          {{ form.processing ? 'Working…' : cfg.submit }}
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.label { @apply block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white; }
.error { @apply text-danger text-xs mt-1; }
</style>
