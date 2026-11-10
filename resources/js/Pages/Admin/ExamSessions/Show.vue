<template>
  <AdminLayout :title="session.student.name" :subtitle="`${session.exam.name} · ${session.exam.exam_code}`">
    <div v-if="flash.success" class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">{{ flash.error }}</div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <Link :href="route('admin.exam-sessions.index', { exam_id: session.exam.id })" class="text-sm font-semibold text-primary hover:underline">← Ongoing Exams</Link>
      <div v-if="canWrite" class="flex flex-wrap gap-2">
        <template v-if="session.is_open">
          <button v-if="session.is_paused && session.connection !== 'rescheduled'" type="button" class="btn text-success" @click="open('resume')">Resume</button>
          <button v-if="!session.is_paused" type="button" class="btn text-purple-700" @click="open('suspend')">Pause</button>
          <button v-if="session.lost_seconds >= 60" type="button" class="btn text-success" @click="open('restore_time')">+{{ Math.round(session.lost_seconds / 60) }} min back</button>
          <button type="button" class="btn" @click="open('add_time')">Add time</button>
          <button v-if="session.violation_count && !session.is_paused" type="button" class="btn" @click="open('clear_violations')">Clear warnings</button>
          <button type="button" class="btn" @click="open('reschedule')">Reschedule</button>
          <button type="button" class="btn text-danger" @click="open('force_submit')">Submit now</button>
          <button type="button" class="btn text-danger" @click="open('reset')">Reset</button>
        </template>
        <template v-else-if="session.result !== 'released'">
          <button type="button" class="btn text-accent" @click="open('reopen')">Reopen</button>
          <button type="button" class="btn" @click="open('reschedule')">Reschedule</button>
          <button type="button" class="btn text-danger" @click="open('reset')">Reset</button>
        </template>
        <p v-else class="text-xs text-text-muted">Result released: recovery actions are locked. Use a score override on the Results page.</p>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
      <div class="xl:col-span-2 space-y-5">
        <!-- summary -->
        <section class="card">
          <div class="flex flex-wrap items-center gap-2 mb-4">
            <ConnectionChip :state="session.connection" :offline-seconds="session.offline_seconds" />
            <span class="text-xs text-text-muted">{{ session.is_open ? `Last seen ${session.last_seen}` : `Ended ${session.submitted_at}` }}</span>
            <span v-if="session.personal_window" class="text-xs text-purple-700 font-semibold">Personal window: {{ session.personal_window }}</span>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="stat"><span>Answered</span><strong>{{ session.answered }}/{{ session.total }}</strong></div>
            <div class="stat"><span>Time left</span><strong :class="session.is_open && timing.remaining_seconds <= 60 ? 'text-danger' : ''">{{ session.is_open ? dur(timing.remaining_seconds) : '—' }}</strong></div>
            <div class="stat"><span>Warnings</span><strong :class="session.violation_count ? 'text-danger' : ''">{{ session.violation_count }}/{{ limits.max_strikes }}</strong></div>
            <div class="stat"><span>Device switches</span><strong>{{ session.device_switches }}</strong></div>
          </div>
        </section>

        <!-- timing -->
        <section class="card">
          <h3 class="h3">Time</h3>
          <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3 text-sm">
            <div><dt class="dt">Exam duration</dt><dd class="dd">{{ dur(timing.duration_seconds) }}</dd></div>
            <div><dt class="dt">Extra time given</dt><dd class="dd">{{ timing.extra_seconds ? '+' + dur(timing.extra_seconds) : '—' }}</dd></div>
            <div><dt class="dt">Paused / closed</dt><dd class="dd">{{ timing.paused_seconds ? dur(timing.paused_seconds) : '—' }}</dd></div>
            <div><dt class="dt">Time used</dt><dd class="dd">{{ dur(timing.used_seconds) }}</dd></div>
            <div><dt class="dt">Lost offline (not restored)</dt><dd class="dd" :class="session.lost_seconds >= 60 ? 'text-amber-700' : ''">{{ session.lost_seconds ? dur(session.lost_seconds) : '—' }}</dd></div>
            <div><dt class="dt">Reopened</dt><dd class="dd">{{ session.reopen_count ? `${session.reopen_count}×` : '—' }}</dd></div>
            <div><dt class="dt">Started</dt><dd class="dd">{{ timing.started_at || '—' }}</dd></div>
            <div><dt class="dt">Deadline</dt><dd class="dd">{{ timing.deadline_at || '—' }}</dd></div>
            <div><dt class="dt">Submitted</dt><dd class="dd">{{ timing.submitted_at || '—' }}</dd></div>
          </dl>
        </section>

        <!-- question grid -->
        <section class="card">
          <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h3 class="h3 !mb-0">Answers so far</h3>
            <div class="flex flex-wrap gap-3 text-[11px] text-text-muted">
              <span class="flex items-center gap-1"><i class="w-2.5 h-2.5 rounded bg-success"></i>Answered</span>
              <span class="flex items-center gap-1"><i class="w-2.5 h-2.5 rounded bg-purple-600"></i>Marked</span>
              <span class="flex items-center gap-1"><i class="w-2.5 h-2.5 rounded bg-gray-200"></i>Not answered</span>
              <span class="flex items-center gap-1"><i class="w-2.5 h-2.5 rounded ring-2 ring-accent"></i>Current</span>
            </div>
          </div>
          <p class="text-[11px] text-text-muted mb-3">Paper order. Students may see a shuffled order. Admins can't change answers.</p>
          <template v-for="(group, gi) in questionGroups" :key="gi">
            <p v-if="questionGroups.length > 1" class="text-[11px] font-semibold uppercase tracking-wider text-text-muted mt-3 mb-1.5">{{ group.name }}</p>
            <div class="grid grid-cols-6 sm:grid-cols-10 gap-1.5">
              <div v-for="q in group.items" :key="q.id" :title="q.selected.length ? `Q${q.number}: ${q.selected.join(', ').toUpperCase()}` : `Q${q.number}: not answered`"
                   class="aspect-square rounded-lg grid place-items-center text-[11px] font-number font-semibold leading-none"
                   :class="[q.selected.length ? (q.flagged ? 'bg-purple-600 text-white' : 'bg-success text-white') : (q.flagged ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-text-muted'), q.current ? 'ring-2 ring-accent ring-offset-1' : '']">
                <span>{{ q.number }}<small v-if="q.selected.length" class="block text-[9px] opacity-80 uppercase">{{ q.selected.join('') }}</small></span>
              </div>
            </div>
          </template>
        </section>
      </div>

      <div class="space-y-5">
        <!-- student -->
        <section class="card">
          <h3 class="h3">Student</h3>
          <dl class="space-y-2 text-sm">
            <div><dt class="dt">Name</dt><dd class="dd">{{ session.student.name }}</dd></div>
            <div><dt class="dt">Class / school</dt><dd class="dd">{{ session.student.class }}<template v-if="session.student.school"> · {{ session.student.school }}</template></dd></div>
            <div><dt class="dt">Phone</dt><dd class="dd font-number">{{ session.student.phone || '—' }}</dd></div>
            <div><dt class="dt">Email</dt><dd class="dd break-all">{{ session.student.email }}</dd></div>
            <div><dt class="dt">Last IP</dt><dd class="dd font-number">{{ device.ip || '—' }}</dd></div>
            <div v-if="device.user_agent"><dt class="dt">Browser</dt><dd class="dd text-xs break-words">{{ device.user_agent }}</dd></div>
          </dl>
        </section>

        <!-- timeline -->
        <section class="card">
          <h3 class="h3">Timeline</h3>
          <ol class="relative border-l border-gray-200 ml-2 space-y-4">
            <li v-for="event in events" :key="event.id" class="ml-4">
              <span class="absolute -left-[5px] mt-1.5 w-2.5 h-2.5 rounded-full" :class="dotClass(event)"></span>
              <p class="text-sm font-semibold text-text-main">{{ event.label }}<span v-if="event.previous_attempt" class="ml-1 text-[10px] font-semibold text-text-muted">(earlier attempt)</span></p>
              <p v-if="event.summary" class="text-xs text-text-muted">{{ event.summary }}</p>
              <p v-if="event.reason" class="text-xs text-text-main/80 italic mt-0.5">“{{ event.reason }}”</p>
              <p class="text-[11px] text-text-muted mt-0.5 font-number">{{ event.at }}<template v-if="event.actor"> · {{ event.actor }}</template></p>
            </li>
          </ol>
        </section>
      </div>
    </div>

    <SessionActionDialog v-if="dialog" :action="dialog" :target="session" :limits="limits" @close="dialog = null" />
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConnectionChip from './Components/ConnectionChip.vue';
import SessionActionDialog from './Components/SessionActionDialog.vue';
import { usePoll } from '@/composables/usePoll';

const props = defineProps({
  session: Object,
  timing: Object,
  device: Object,
  questions: Array,
  events: Array,
  limits: Object,
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const canWrite = computed(() => page.props.admin_is_super || page.props.admin_permissions?.exam_sessions?.write);

usePoll(['session', 'timing', 'questions', 'events'], 10000);

const dialog = ref(null);
const open = (action) => { dialog.value = action; };

const questionGroups = computed(() => {
  const groups = [];
  props.questions.forEach((q) => {
    const name = q.section || 'Questions';
    if (!groups.length || groups[groups.length - 1].name !== name) groups.push({ name, items: [] });
    groups[groups.length - 1].items.push(q);
  });
  return groups;
});

const dur = (s) => {
  s = Math.max(0, Math.round(s || 0));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  return h ? `${h}h ${m}m` : (m ? `${m}m ${String(sec).padStart(2, '0')}s` : `${sec}s`);
};
const dotClass = (event) => ({
  violation: 'bg-danger', reconnected: 'bg-amber-500', device_switched: 'bg-amber-500', timed_out: 'bg-amber-500', auto_submitted: 'bg-amber-500',
  suspended: 'bg-purple-600', resumed: 'bg-success', time_added: 'bg-success', reopened: 'bg-accent', reset: 'bg-danger',
  force_submitted: 'bg-danger', rescheduled: 'bg-purple-400', submitted: 'bg-primary', started: 'bg-primary',
}[event.type] ?? 'bg-gray-300');
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm p-5; }
.h3 { @apply font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-3; }
.stat { @apply bg-gray-50 rounded-xl p-3; }
.stat span { @apply block text-[11px] text-text-muted font-semibold uppercase tracking-wider; }
.stat strong { @apply block font-number text-xl text-text-main mt-0.5; }
.dt { @apply text-[11px] text-text-muted font-semibold uppercase tracking-wider; }
.dd { @apply text-text-main; }
.btn { @apply text-xs font-semibold px-3 py-2 rounded-xl border border-gray-200 bg-white hover:border-primary transition-colors; }
</style>
