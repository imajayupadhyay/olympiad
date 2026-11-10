<template>
  <AdminLayout title="Ongoing Exams" subtitle="Watch live exam sessions and recover interrupted ones without losing answers">
    <div v-if="flash.success" class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">{{ flash.error }}</div>
    <div v-if="flash.info" class="mb-4 flex items-center gap-3 bg-blue-50 border border-blue-200 text-royal text-sm px-4 py-3 rounded-xl">{{ flash.info }}</div>

    <!-- live indicator -->
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
      <p class="text-xs text-text-muted flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-success animate-pulse motion-reduce:animate-none"></span>
        Live · refreshes every 10 seconds · server time <span class="font-number">{{ serverTime }}</span>
      </p>
      <p v-if="!canWrite" class="text-xs text-text-muted">View only. Ask a super admin for "Ongoing Exams" write access to take actions.</p>
    </div>

    <!-- stats -->
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-5">
      <button v-for="tile in tiles" :key="tile.key" type="button" @click="go({ tab: tile.tab })"
              class="text-left bg-white rounded-2xl border border-gray-100 shadow-sm p-4 hover:border-primary/30 transition-colors">
        <p class="text-text-muted text-[11px] font-semibold uppercase tracking-wider mb-1.5">{{ tile.label }}</p>
        <p class="font-number font-bold text-2xl" :class="tile.cls">{{ (stats?.[tile.key] ?? 0).toLocaleString() }}</p>
      </button>
    </div>

    <!-- exam groups -->
    <div v-if="groups?.length" class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-3 mb-5">
      <div v-for="group in groups" :key="group.id" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <div class="flex items-start justify-between gap-3">
          <button type="button" class="text-left min-w-0" @click="go({ exam_id: group.id })">
            <p class="font-semibold text-text-main truncate hover:text-primary">{{ group.name }}</p>
            <p class="text-[11px] text-text-muted font-number">{{ group.exam_code }} · {{ group.class }} · {{ group.duration_minutes }} min</p>
            <p class="text-[11px] text-text-muted mt-0.5">{{ group.window_label }}</p>
          </button>
          <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-md"
                :class="group.availability === 'live' ? 'bg-green-100 text-success' : 'bg-gray-100 text-text-muted'">{{ group.availability }}</span>
        </div>
        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3 text-xs">
          <span><b class="font-number text-success">{{ group.online }}</b> online</span>
          <span><b class="font-number text-text-main">{{ group.open }}</b> open</span>
          <span><b class="font-number text-purple-700">{{ group.paused }}</b> paused</span>
          <span><b class="font-number text-text-muted">{{ group.not_started }}</b> not started</span>
        </div>
        <div v-if="canWrite && group.open" class="flex flex-wrap gap-1.5 mt-3 pt-3 border-t border-gray-100">
          <button type="button" class="chip" @click="open('bulk_suspend', group)">Pause all</button>
          <button v-if="group.paused" type="button" class="chip" @click="open('bulk_resume', group)">Resume all</button>
          <button type="button" class="chip" @click="open('bulk_add_time', group)">+ Time to all</button>
        </div>
      </div>
    </div>

    <!-- filters + tabs -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 mb-4 space-y-3">
      <div class="flex flex-wrap gap-1" role="tablist" aria-label="Session views">
        <button v-for="t in tabs" :key="t.key" type="button" role="tab" :aria-selected="tab === t.key" @click="go({ tab: t.key })"
                class="px-3 py-2 rounded-xl text-sm font-semibold transition-colors"
                :class="tab === t.key ? 'bg-primary text-white' : 'text-text-main hover:bg-gray-100'">
          {{ t.label }} <span v-if="t.count !== null" class="font-number text-xs opacity-70 ml-0.5">{{ t.count }}</span>
        </button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
        <select v-model="examFilter" class="field" aria-label="Exam" @change="go({ exam_id: examFilter })">
          <option value="">All live & recent exams</option>
          <option v-for="exam in exams" :key="exam.id" :value="String(exam.id)">{{ exam.name }} ({{ exam.exam_code }})</option>
        </select>
        <input v-model="searchText" type="search" class="field md:col-span-2" placeholder="Search student name, email, phone or school"
               @keydown.enter="go({ search: searchText })" @search="go({ search: searchText })" />
      </div>
    </div>

    <!-- sessions -->
    <div v-if="tab !== 'not_started'" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div v-if="!rows.length" class="py-16 text-center px-4">
        <p class="font-heading font-bold text-text-main">{{ emptyText.title }}</p>
        <p class="text-text-muted text-sm mt-1">{{ emptyText.body }}</p>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm min-w-[900px]">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="th">Student</th>
              <th class="th">Exam</th>
              <th class="th">Status</th>
              <th class="th">Progress</th>
              <th class="th">Time left</th>
              <th class="th">Flags</th>
              <th class="th text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50/50 align-top">
              <td class="px-4 py-3 max-w-[220px]">
                <Link :href="route('admin.exam-sessions.show', row.id)" class="font-semibold text-text-main hover:text-primary truncate block">{{ row.student.name }}</Link>
                <p class="text-[11px] text-text-muted truncate">{{ row.student.class }}<template v-if="row.student.school"> · {{ row.student.school }}</template></p>
                <p class="text-[11px] text-text-muted font-number truncate">{{ row.student.phone || row.student.email }}</p>
              </td>
              <td class="px-4 py-3 max-w-[200px]">
                <p class="text-text-main truncate">{{ row.exam.name }}</p>
                <p class="text-[11px] text-text-muted">Started {{ row.started_at }}</p>
                <p v-if="row.personal_window" class="text-[11px] text-purple-700 font-semibold">Window: {{ row.personal_window }}</p>
              </td>
              <td class="px-4 py-3">
                <ConnectionChip :state="row.connection" :offline-seconds="row.offline_seconds" />
                <p class="text-[11px] text-text-muted mt-1">{{ row.is_open ? `Seen ${row.last_seen}` : `Ended ${row.submitted_at}` }}</p>
                <p v-if="row.result" class="text-[11px] mt-0.5" :class="row.result === 'released' ? 'text-success' : 'text-amber-700'">Result {{ row.result }}</p>
              </td>
              <td class="px-4 py-3 w-40">
                <div class="flex items-center justify-between text-xs mb-1">
                  <span class="font-number font-semibold text-text-main">{{ row.answered }}/{{ row.total }}</span>
                  <span v-if="row.current_number" class="text-text-muted">at Q{{ row.current_number }}</span>
                </div>
                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full bg-accent rounded-full" :style="{ width: pct(row) + '%' }"></div></div>
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <template v-if="row.is_open">
                  <p class="font-number font-bold" :class="left(row) <= 60 ? 'text-danger' : 'text-text-main'">{{ clock(left(row)) }}</p>
                  <p v-if="row.is_paused" class="text-[11px] text-purple-700">frozen</p>
                  <p v-else-if="row.lost_seconds >= 60" class="text-[11px] text-amber-700">{{ Math.round(row.lost_seconds / 60) }} min lost</p>
                </template>
                <span v-else class="text-text-muted text-xs">—</span>
              </td>
              <td class="px-4 py-3 text-[11px] space-y-0.5">
                <p :class="row.violation_count ? 'text-danger font-semibold' : 'text-text-muted'">{{ row.violation_count }}/{{ limits.max_strikes }} warnings</p>
                <p v-if="row.device_switches" class="text-amber-700">{{ row.device_switches }} device switch{{ row.device_switches > 1 ? 'es' : '' }}</p>
                <p v-if="row.reopen_count" class="text-royal">Reopened ×{{ row.reopen_count }}</p>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <div class="inline-flex flex-wrap justify-end gap-1">
                  <template v-if="canWrite">
                    <button v-for="item in primaryActions(row)" :key="item.action" type="button" class="act" :class="item.cls" @click="open(item.action, row)">{{ item.label }}</button>
                    <div class="relative" v-if="moreActions(row).length">
                      <button type="button" class="act text-text-main" :aria-expanded="menu === row.id" @click.stop="menu = menu === row.id ? null : row.id">More ▾</button>
                      <div v-if="menu === row.id" class="absolute right-0 top-full mt-1 z-20 w-52 bg-white border border-gray-200 rounded-xl shadow-lg py-1 text-left">
                        <button v-for="item in moreActions(row)" :key="item.action" type="button"
                                class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50" :class="item.cls"
                                @click="open(item.action, row)">{{ item.label }}</button>
                      </div>
                    </div>
                  </template>
                  <Link :href="route('admin.exam-sessions.show', row.id)" class="act text-primary">View</Link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination v-if="sessions" :page="sessions" />
    </div>

    <!-- not started -->
    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div v-if="!enrolRows.length" class="py-16 text-center px-4">
        <p class="font-heading font-bold text-text-main">Everyone enrolled has started</p>
        <p class="text-text-muted text-sm mt-1">Enrolled students who never began a live or recently closed exam appear here, so you can give them a personal window.</p>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr><th class="th">Student</th><th class="th">Exam</th><th class="th">Window</th><th class="th text-right">Actions</th></tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="row in enrolRows" :key="row.id" class="align-top">
              <td class="px-4 py-3">
                <p class="font-semibold text-text-main">{{ row.student.name }}</p>
                <p class="text-[11px] text-text-muted">{{ row.student.class }}<template v-if="row.student.school"> · {{ row.student.school }}</template></p>
                <p class="text-[11px] text-text-muted font-number">{{ row.student.phone || row.student.email }}</p>
              </td>
              <td class="px-4 py-3"><p class="text-text-main">{{ row.exam.name }}</p><p class="text-[11px] text-text-muted font-number">{{ row.exam.exam_code }}</p></td>
              <td class="px-4 py-3 text-xs">
                <p v-if="row.personal_window" class="text-purple-700 font-semibold">Personal: {{ row.personal_window }}</p>
                <p class="text-text-muted">Exam: {{ row.exam_window || 'open-ended' }}</p>
                <p class="mt-0.5 font-semibold" :class="row.window_state === 'live' ? 'text-success' : (row.window_state === 'upcoming' ? 'text-royal' : 'text-text-muted')">
                  {{ { live: 'Can start now', upcoming: 'Opens later', closed: 'Window closed' }[row.window_state] }}
                </p>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <template v-if="canWrite">
                  <button type="button" class="act text-primary" @click="open('enrollment_reschedule', row)">{{ row.personal_window ? 'Change window' : 'Reschedule' }}</button>
                  <button v-if="row.personal_window" type="button" class="act text-text-muted" @click="unschedule(row)">Remove window</button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination v-if="notStarted" :page="notStarted" />
    </div>

    <SessionActionDialog v-if="dialog" :action="dialog.action" :target="dialog.target" :limits="limits" @close="dialog = null" />
  </AdminLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConnectionChip from './Components/ConnectionChip.vue';
import SessionActionDialog from './Components/SessionActionDialog.vue';
import Pagination from './Components/Pagination.vue';
import { usePoll } from '@/composables/usePoll';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({
  tab: String,
  filters: Object,
  exams: Array,
  groups: Array,
  stats: Object,
  sessions: Object,
  notStarted: Object,
  limits: Object,
  serverTime: String,
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const canWrite = computed(() => page.props.admin_is_super || page.props.admin_permissions?.exam_sessions?.write);

const rows = computed(() => props.sessions?.data ?? []);
const enrolRows = computed(() => props.notStarted?.data ?? []);

const tiles = [
  { key: 'live', label: 'Online now', tab: 'live', cls: 'text-success' },
  { key: 'offline', label: 'Offline / unstable', tab: 'attention', cls: 'text-danger' },
  { key: 'paused', label: 'Paused', tab: 'live', cls: 'text-purple-700' },
  { key: 'time_over', label: 'Time over', tab: 'attention', cls: 'text-amber-700' },
  { key: 'not_started', label: 'Not started', tab: 'not_started', cls: 'text-text-main' },
  { key: 'ended_today', label: 'Ended today', tab: 'ended', cls: 'text-primary' },
];
const tabs = computed(() => [
  { key: 'live', label: 'Live', count: (props.stats?.live ?? 0) + (props.stats?.offline ?? 0) + (props.stats?.paused ?? 0) + (props.stats?.time_over ?? 0) },
  { key: 'attention', label: 'Needs attention', count: null },
  { key: 'ended', label: 'Ended (7 days)', count: null },
  { key: 'not_started', label: 'Not started', count: props.stats?.not_started ?? 0 },
]);
const emptyText = computed(() => ({
  live: { title: 'No one is sitting an exam right now', body: 'Open sessions of live exams appear here as soon as students start.' },
  attention: { title: 'Nothing needs attention', body: 'Offline students, expired timers, repeated tab switches and recent auto-submits show up here.' },
  ended: { title: 'No finished sessions in the last 7 days', body: 'Submitted, timed-out and auto-submitted sessions can be reopened from here until results are released.' },
}[props.tab]));

/* ── filters ── */
const examFilter = ref(String(props.filters?.exam_id ?? ''));
const searchText = ref(props.filters?.search ?? '');
function go(changes) {
  const query = { tab: props.tab, exam_id: examFilter.value || undefined, search: searchText.value || undefined, ...changes };
  if (changes.exam_id !== undefined) examFilter.value = String(changes.exam_id || '');
  Object.keys(query).forEach((k) => (query[k] === '' || query[k] === undefined) && delete query[k]);
  router.get(route('admin.exam-sessions.index'), query, { preserveState: true, preserveScroll: true, replace: true });
}

/* ── live refresh + local countdown between polls ── */
usePoll(['groups', 'stats', 'sessions', 'notStarted', 'serverTime'], 10000);
const loadedAt = ref(Date.now());
const now = ref(Date.now());
watch(() => props.sessions, () => { loadedAt.value = Date.now(); });
let clockTimer = null;
const closeMenu = () => { menu.value = null; };
onMounted(() => {
  clockTimer = setInterval(() => { now.value = Date.now(); }, 1000);
  document.addEventListener('click', closeMenu);
});
onBeforeUnmount(() => { clearInterval(clockTimer); document.removeEventListener('click', closeMenu); });

const left = (row) => (row.is_paused ? row.remaining_seconds : Math.max(0, row.remaining_seconds - Math.floor((now.value - loadedAt.value) / 1000)));
const clock = (s) => {
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  const pad = (n) => String(n).padStart(2, '0');
  return h ? `${h}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
};
const pct = (row) => (row.total ? Math.round((row.answered / row.total) * 100) : 0);

/* ── actions ── */
const menu = ref(null);
const dialog = ref(null);
function open(action, target) { menu.value = null; dialog.value = { action, target }; }

function primaryActions(row) {
  if (!row.is_open) return row.result === 'released' ? [] : [{ action: 'reopen', label: 'Reopen', cls: 'text-accent' }];
  if (row.connection === 'rescheduled') return [];
  if (row.is_paused) return [{ action: 'resume', label: 'Resume', cls: 'text-success' }];
  const list = [];
  if (row.lost_seconds >= 60) list.push({ action: 'restore_time', label: `+${Math.round(row.lost_seconds / 60)} min back`, cls: 'text-success' });
  list.push({ action: 'suspend', label: 'Pause', cls: 'text-purple-700' });
  return list;
}
function moreActions(row) {
  if (!row.is_open) {
    return row.result === 'released' ? [] : [
      { action: 'reschedule', label: 'Reschedule…', cls: 'text-text-main' },
      { action: 'reset', label: 'Reset — start from scratch', cls: 'text-danger' },
    ];
  }
  const list = [{ action: 'add_time', label: 'Add time…', cls: 'text-text-main' }];
  if (row.violation_count && !row.is_paused) list.push({ action: 'clear_violations', label: 'Clear warnings', cls: 'text-text-main' });
  list.push({ action: 'reschedule', label: row.connection === 'rescheduled' ? 'Change window…' : 'Reschedule…', cls: 'text-text-main' });
  list.push({ action: 'force_submit', label: 'Submit now', cls: 'text-danger' });
  list.push({ action: 'reset', label: 'Reset — start from scratch', cls: 'text-danger' });
  return list;
}

async function unschedule(row) {
  const ok = await confirmDialog({
    title: 'Remove the personal window?',
    message: `${row.student.name} goes back to the exam's own window (${row.exam_window || 'open-ended'}).`,
    confirmText: 'Remove window',
    tone: 'warning',
  });
  if (ok) router.delete(route('admin.exam-sessions.enrollments.unschedule', row.id), { preserveScroll: true });
}
</script>

<style scoped>
.th { @apply text-left px-4 py-3 text-text-muted text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main; }
.act { @apply text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-gray-100 transition-colors; }
.chip { @apply text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-gray-200 text-text-main hover:border-primary hover:text-primary transition-colors; }
</style>
