<template>
  <AdminLayout title="Dashboard" subtitle="Platform overview & analytics">

    <!-- ══════════ KPI CARDS ══════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
      <div v-for="k in kpis" :key="k.label"
           class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:shadow-md transition-shadow relative overflow-hidden">
        <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full opacity-[.07]" :style="{ background: k.color }"></div>
        <div class="flex items-start justify-between relative">
          <div>
            <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-2">{{ k.label }}</p>
            <p class="font-number font-bold text-3xl" :style="{ color: k.color }">{{ k.value }}</p>
            <p class="text-xs mt-1.5 flex items-center gap-1" :class="k.deltaUp ? 'text-success' : 'text-text-muted'">
              <svg v-if="k.deltaUp" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
              </svg>
              {{ k.sub }}
            </p>
          </div>
          <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0" :style="{ background: k.color + '14' }">
            <span v-html="k.icon" class="w-6 h-6" :style="{ color: k.color }"></span>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════ ROW: Registrations area + Exam status donut ══════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

      <!-- Registrations trend -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h2 class="font-heading font-bold text-text-main text-base">Student Registrations</h2>
            <p class="text-text-muted text-xs mt-0.5">New sign-ups over the last 14 days</p>
          </div>
          <div class="text-right">
            <p class="font-number font-bold text-2xl text-primary">{{ regTotal }}</p>
            <p class="text-text-muted text-[11px] uppercase tracking-wider font-semibold">this fortnight</p>
          </div>
        </div>

        <!-- Area chart -->
        <div class="relative">
          <svg :viewBox="`0 0 ${areaW} ${areaH}`" class="w-full" :style="{ height: areaH + 'px' }" preserveAspectRatio="none">
            <defs>
              <linearGradient id="areaFill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#EE6A2C" stop-opacity="0.28"/>
                <stop offset="100%" stop-color="#EE6A2C" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <!-- gridlines -->
            <line v-for="g in 4" :key="'g'+g" x1="0" :x2="areaW" :y1="(areaH/4)*g" :y2="(areaH/4)*g"
                  stroke="#E7D9BE" stroke-width="1" stroke-dasharray="3 5"/>
            <path :d="areaPath" fill="url(#areaFill)"/>
            <path :d="linePath" fill="none" stroke="#EE6A2C" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <circle v-for="(p,i) in points" :key="'pt'+i" :cx="p.x" :cy="p.y" r="3" fill="#fff" stroke="#EE6A2C" stroke-width="2"/>
          </svg>
          <!-- x labels -->
          <div class="flex justify-between mt-2 px-0.5">
            <span v-for="(d,i) in charts.registrations" :key="'lb'+i"
                  class="text-[9px] text-text-muted font-number"
                  :class="{ 'opacity-0': i % 2 !== 0 && charts.registrations.length > 10 }">{{ d.short }}</span>
          </div>
        </div>
      </div>

      <!-- Exam status donut -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col">
        <h2 class="font-heading font-bold text-text-main text-base mb-1">Exam Status</h2>
        <p class="text-text-muted text-xs mb-4">{{ stats.totalExams }} exams total</p>

        <div v-if="stats.totalExams === 0" class="flex-1 flex flex-col items-center justify-center text-center py-8">
          <div class="w-14 h-14 rounded-full bg-card flex items-center justify-center mb-3">
            <svg class="w-7 h-7 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
          </div>
          <p class="text-text-muted text-sm">No exams created yet</p>
        </div>

        <div v-else class="flex-1 flex flex-col items-center justify-center">
          <svg viewBox="0 0 120 120" class="w-40 h-40 -rotate-90">
            <circle cx="60" cy="60" r="46" fill="none" stroke="#F3E9D6" stroke-width="14"/>
            <circle v-for="(s,i) in donutSegments" :key="'d'+i"
                    cx="60" cy="60" r="46" fill="none" :stroke="s.color" stroke-width="14"
                    :stroke-dasharray="`${s.len} ${donutC - s.len}`" :stroke-dashoffset="-s.offset" stroke-linecap="butt"/>
          </svg>
          <div class="-mt-28 mb-16 text-center pointer-events-none">
            <p class="font-number font-bold text-2xl text-text-main">{{ stats.activeExams }}</p>
            <p class="text-[10px] text-text-muted uppercase tracking-wider font-semibold">live</p>
          </div>
          <div class="w-full space-y-1.5 mt-2">
            <div v-for="s in charts.examStatus" :key="s.label" class="flex items-center justify-between text-xs">
              <span class="flex items-center gap-2 text-text-main">
                <span class="w-2.5 h-2.5 rounded-sm" :style="{ background: s.color }"></span>{{ s.label }}
              </span>
              <span class="font-number font-semibold text-text-main">{{ s.value }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════ ROW: Questions by subject + difficulty + students by class ══════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

      <!-- Questions by subject -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-heading font-bold text-text-main text-base mb-1">Question Bank by Subject</h2>
        <p class="text-text-muted text-xs mb-5">{{ stats.totalQuestions }} questions across subjects</p>

        <div v-if="stats.totalQuestions === 0" class="py-10 text-center text-text-muted text-sm">No questions yet.</div>
        <div v-else class="space-y-3.5">
          <div v-for="s in charts.questionsBySubject" :key="s.name">
            <div class="flex items-center justify-between text-xs mb-1.5">
              <span class="flex items-center gap-1.5 text-text-main font-medium">{{ s.icon }} {{ s.name }}</span>
              <span class="font-number font-semibold text-text-muted">{{ s.count }}</span>
            </div>
            <div class="h-2 rounded-full bg-card overflow-hidden">
              <div class="h-full rounded-full transition-all duration-700"
                   :style="{ width: barPct(s.count, maxSubject) + '%', background: s.color }"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Difficulty split -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-heading font-bold text-text-main text-base mb-1">Difficulty Mix</h2>
        <p class="text-text-muted text-xs mb-5">Question difficulty distribution</p>

        <div v-if="stats.totalQuestions === 0" class="py-10 text-center text-text-muted text-sm">No questions yet.</div>
        <template v-else>
          <!-- stacked bar -->
          <div class="h-4 rounded-full overflow-hidden flex mb-5 bg-card">
            <div v-for="d in charts.difficulty" :key="d.label" class="h-full first:rounded-l-full last:rounded-r-full transition-all duration-700"
                 :style="{ width: barPct(d.value, diffTotal) + '%', background: d.color }"></div>
          </div>
          <div class="space-y-3">
            <div v-for="d in charts.difficulty" :key="d.label" class="flex items-center justify-between">
              <span class="flex items-center gap-2 text-sm text-text-main">
                <span class="w-2.5 h-2.5 rounded-sm" :style="{ background: d.color }"></span>{{ d.label }}
              </span>
              <span class="text-xs text-text-muted">
                <b class="font-number text-text-main">{{ d.value }}</b>
                ({{ diffTotal ? Math.round(d.value / diffTotal * 100) : 0 }}%)
              </span>
            </div>
          </div>
        </template>
      </div>

      <!-- Students by class -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-heading font-bold text-text-main text-base mb-1">Students by Class</h2>
        <p class="text-text-muted text-xs mb-5">Enrollment across class levels</p>

        <div v-if="stats.totalStudents === 0" class="py-10 text-center text-text-muted text-sm">No students yet.</div>
        <div v-else class="flex items-end justify-between gap-1" style="height:150px">
          <div v-for="c in charts.studentsByClass" :key="c.label" class="flex-1 flex flex-col items-center justify-end h-full group">
            <span class="text-[9px] font-number text-text-muted mb-1 opacity-0 group-hover:opacity-100 transition-opacity">{{ c.value }}</span>
            <div class="w-full rounded-t-md transition-all duration-700 relative"
                 :style="{ height: Math.max(2, barPct(c.value, maxClass)) + '%', background: c.value ? 'linear-gradient(180deg,#24315C,#131C3D)' : '#E7D9BE', minHeight:'3px' }"
                 :title="`${c.label}: ${c.value}`"></div>
            <span class="text-[9px] font-number text-text-muted mt-1.5">{{ c.short }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════ ROW: Recent students + Upcoming exams + Side panels ══════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Recent registrations -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="font-heading font-bold text-text-main text-base">Recent Registrations</h2>
          <Link :href="route('admin.users.index')" class="text-accent text-xs font-semibold hover:underline">View all →</Link>
        </div>
        <div v-if="recentStudents.length === 0" class="px-6 py-12 text-center">
          <p class="text-text-muted text-sm font-medium">No students registered yet</p>
        </div>
        <div v-else class="divide-y divide-gray-50">
          <div v-for="s in recentStudents" :key="s.id" class="px-6 py-3 flex items-center gap-4 hover:bg-gray-50/50 transition-colors">
            <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0" :style="{ background: avatarColor(s.name) }">
              {{ s.name.charAt(0).toUpperCase() }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-semibold text-text-main text-sm truncate">{{ s.name }}</p>
              <p class="text-text-muted text-xs truncate">{{ s.email }}</p>
            </div>
            <p class="text-text-muted text-xs shrink-0 font-number">{{ formatDate(s.created_at) }}</p>
          </div>
        </div>
      </div>

      <!-- Right column -->
      <div class="space-y-6">

        <!-- Upcoming exams -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
          <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-heading font-bold text-text-main text-base">Upcoming Exams</h2>
            <Link :href="route('admin.exams.index')" class="text-accent text-xs font-semibold hover:underline">All →</Link>
          </div>
          <div v-if="upcomingExams.length === 0" class="px-6 py-8 text-center">
            <p class="text-text-muted text-sm">No published exams scheduled.</p>
            <Link :href="route('admin.exams.create')" class="inline-block mt-3 text-accent text-xs font-semibold hover:underline">Create one →</Link>
          </div>
          <div v-else class="divide-y divide-gray-50">
            <Link v-for="e in upcomingExams" :key="e.id" :href="route('admin.exams.edit', e.id)"
                  class="px-5 py-3 flex items-center gap-3 hover:bg-gray-50/50 transition-colors">
              <span class="w-9 h-9 rounded-lg flex items-center justify-center text-sm shrink-0"
                    :style="{ background: (e.subject?.color || '#2C49A6') + '18' }">{{ e.subject?.icon || '📝' }}</span>
              <div class="min-w-0 flex-1">
                <p class="font-semibold text-text-main text-xs truncate">{{ e.name }}</p>
                <p class="text-text-muted text-[11px] truncate">{{ e.class_level?.label }}</p>
              </div>
              <span class="text-[11px] font-number text-text-muted shrink-0">{{ e.starts_at ? formatDate(e.starts_at) : 'TBD' }}</span>
            </Link>
          </div>
        </div>

        <!-- Engagement snapshot -->
        <div class="rounded-2xl p-5 text-white" style="background:linear-gradient(150deg,#0A1024,#131C3D);">
          <h2 class="font-heading font-bold text-base mb-4">Engagement Snapshot</h2>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="font-number font-bold text-2xl text-gold-light">{{ stats.totalAttempts }}</p>
              <p class="text-blue-200 text-[11px] mt-0.5">Exam attempts</p>
            </div>
            <div>
              <p class="font-number font-bold text-2xl text-gold-light">{{ stats.avgScore }}%</p>
              <p class="text-blue-200 text-[11px] mt-0.5">Avg score</p>
            </div>
            <div>
              <p class="font-number font-bold text-2xl text-gold-light">{{ stats.certsIssued }}</p>
              <p class="text-blue-200 text-[11px] mt-0.5">Certificates</p>
            </div>
            <div>
              <p class="font-number font-bold text-2xl text-gold-light">{{ stats.totalReach.toLocaleString() }}</p>
              <p class="text-blue-200 text-[11px] mt-0.5">Notification reach</p>
            </div>
          </div>
        </div>

        <!-- Top states -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
          <h2 class="font-heading font-bold text-text-main text-base mb-4">Top States</h2>
          <div v-if="charts.topStates.length === 0" class="py-4 text-center text-text-muted text-xs">No location data yet.</div>
          <div v-else class="space-y-3">
            <div v-for="(st,i) in charts.topStates" :key="st.state" class="flex items-center gap-3">
              <span class="font-number text-xs text-text-muted w-4">{{ i + 1 }}</span>
              <span class="text-sm text-text-main flex-1 truncate">{{ st.state }}</span>
              <div class="w-20 h-1.5 rounded-full bg-card overflow-hidden">
                <div class="h-full rounded-full bg-accent" :style="{ width: barPct(st.count, charts.topStates[0].count) + '%' }"></div>
              </div>
              <span class="font-number text-xs font-semibold text-text-main w-6 text-right">{{ st.count }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  stats:          { type: Object, default: () => ({}) },
  charts:         { type: Object, default: () => ({ registrations: [], questionsBySubject: [], difficulty: [], examStatus: [], studentsByClass: [], topStates: [] }) },
  recentStudents: { type: Array,  default: () => [] },
  upcomingExams:  { type: Array,  default: () => [] },
});

/* ── KPI cards ── */
const ic = {
  students: '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
  exam:     '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>',
  question: '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  attempts: '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>',
};
const kpis = computed(() => [
  { label: 'Total Students', value: (props.stats.totalStudents || 0).toLocaleString(), color: '#131C3D', icon: ic.students, deltaUp: props.stats.newToday > 0, sub: props.stats.newToday > 0 ? `+${props.stats.newToday} today` : `${props.stats.activeStudents || 0} active` },
  { label: 'Active Exams',   value: props.stats.activeExams || 0, color: '#EE6A2C', icon: ic.exam, deltaUp: false, sub: `${props.stats.totalExams || 0} total` },
  { label: 'Questions',      value: (props.stats.totalQuestions || 0).toLocaleString(), color: '#168A66', icon: ic.question, deltaUp: false, sub: `${props.stats.activeQuestions || 0} active` },
  { label: 'Exam Attempts',  value: (props.stats.totalAttempts || 0).toLocaleString(), color: '#D6991F', icon: ic.attempts, deltaUp: props.stats.attemptsWeek > 0, sub: props.stats.attemptsWeek > 0 ? `+${props.stats.attemptsWeek} this week` : 'all time' },
]);

/* ── Area chart (registrations) ── */
const areaW = 560, areaH = 170;
const regVals = computed(() => props.charts.registrations.map(d => d.value));
const regTotal = computed(() => regVals.value.reduce((a, b) => a + b, 0));
const regMax = computed(() => Math.max(1, ...regVals.value));
const points = computed(() => {
  const vals = regVals.value;
  if (!vals.length) return [];
  const n = vals.length;
  const padY = 16;
  return vals.map((v, i) => ({
    x: n === 1 ? areaW / 2 : (i / (n - 1)) * areaW,
    y: areaH - padY - (v / regMax.value) * (areaH - padY * 2),
  }));
});
const linePath = computed(() => points.value.map((p, i) => `${i ? 'L' : 'M'}${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' '));
const areaPath = computed(() => {
  if (!points.value.length) return '';
  const first = points.value[0], last = points.value[points.value.length - 1];
  return `M${first.x} ${areaH} ` + points.value.map(p => `L${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ') + ` L${last.x} ${areaH} Z`;
});

/* ── Donut (exam status) ── */
const donutR = 46;
const donutC = 2 * Math.PI * donutR;
const donutSegments = computed(() => {
  const total = props.charts.examStatus.reduce((a, s) => a + s.value, 0) || 1;
  let offset = 0;
  return props.charts.examStatus.filter(s => s.value > 0).map(s => {
    const len = (s.value / total) * donutC;
    const seg = { color: s.color, len, offset };
    offset += len;
    return seg;
  });
});

/* ── Bars ── */
const barPct = (v, max) => (max > 0 ? Math.round((v / max) * 100) : 0);
const maxSubject = computed(() => Math.max(1, ...props.charts.questionsBySubject.map(s => s.count)));
const maxClass = computed(() => Math.max(1, ...props.charts.studentsByClass.map(c => c.value)));
const diffTotal = computed(() => props.charts.difficulty.reduce((a, d) => a + d.value, 0));

/* ── helpers ── */
const avatarColors = ['#131C3D', '#EE6A2C', '#168A66', '#6C3FA0', '#2C49A6', '#DC2626', '#D6991F'];
const avatarColor = (name) => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length];
const formatDate = (d) => new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
</script>
