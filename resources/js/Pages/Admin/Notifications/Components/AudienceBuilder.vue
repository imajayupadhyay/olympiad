<template>
  <section class="card" aria-labelledby="aud-h">
    <header class="card-h">
      <div class="flex items-center gap-3 min-w-0">
        <span class="step">1</span>
        <div class="min-w-0">
          <h2 id="aud-h" class="font-heading font-bold text-text-main text-sm">Who should receive it?</h2>
          <p class="text-[11px] text-text-muted">Combine filters (all must match) or hand-pick students. Untick anyone to leave them out.</p>
        </div>
      </div>
      <div class="seg" role="tablist" aria-label="Recipient mode">
        <button type="button" role="tab" :aria-selected="a.mode === 'filters'" :class="{ on: a.mode === 'filters' }" @click="setMode('filters')">Filter students</button>
        <button type="button" role="tab" :aria-selected="a.mode === 'selected'" :class="{ on: a.mode === 'selected' }" @click="setMode('selected')">Pick students</button>
      </div>
    </header>

    <!-- FILTERS -->
    <div v-if="a.mode === 'filters'" class="p-4 sm:p-5 space-y-4">
      <div class="flex flex-wrap gap-1.5" aria-label="Quick audiences">
        <button v-for="preset in presets" :key="preset.key" type="button" class="preset" @click="applyPreset(preset)">{{ preset.label }}</button>
        <button v-if="activeCount" type="button" class="preset ghost" @click="reset">Reset filters</button>
      </div>

      <div>
        <p class="lbl">Class</p>
        <div class="flex flex-wrap gap-1.5">
          <button v-for="c in options.classLevels" :key="c.id" type="button" class="pill" :class="{ on: a.class_level_ids.includes(c.id) }"
                  :aria-pressed="a.class_level_ids.includes(c.id)" @click="toggle('class_level_ids', c.id)">{{ c.label }}</button>
        </div>
      </div>

      <div>
        <p class="lbl">Olympiad subject <span class="normal-case font-normal">(course)</span></p>
        <div class="flex flex-wrap gap-1.5">
          <button v-for="s in options.subjects" :key="s.id" type="button" class="pill" :class="{ on: a.subject_ids.includes(s.id) }"
                  :aria-pressed="a.subject_ids.includes(s.id)" @click="toggle('subject_ids', s.id)">{{ s.name }}</button>
        </div>
      </div>

      <div>
        <p class="lbl">Exam</p>
        <div class="relative" ref="examBox">
          <div class="field flex flex-wrap items-center gap-1.5 min-h-[42px] cursor-text" @click="examOpen = true; $refs.examInput?.focus()">
            <span v-for="e in selectedExams" :key="e.id" class="tag">
              <span class="truncate max-w-[220px]">{{ e.name }}</span>
              <button type="button" class="opacity-60 hover:opacity-100" :aria-label="`Remove ${e.name}`" @click.stop="toggle('exam_ids', e.id)">×</button>
            </span>
            <input ref="examInput" v-model="examSearch" type="text" class="flex-1 min-w-[140px] bg-transparent outline-none text-sm"
                   :placeholder="selectedExams.length ? 'Add another exam…' : 'Any exam — search to pick one or more'"
                   @focus="examOpen = true" @keydown.esc="examOpen = false" />
          </div>
          <div v-if="examOpen" class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto bg-white border border-gray-200 rounded-xl shadow-lg">
            <p v-if="!examMatches.length" class="p-3 text-xs text-text-muted">No exams match.</p>
            <button v-for="e in examMatches" :key="e.id" type="button" class="w-full text-left px-3 py-2 hover:bg-gray-50 flex items-center gap-2 text-sm"
                    @click="toggle('exam_ids', e.id)">
              <span class="w-4 h-4 rounded border flex items-center justify-center text-[10px] shrink-0"
                    :class="a.exam_ids.includes(e.id) ? 'bg-primary border-primary text-white' : 'border-gray-300'">✓</span>
              <span class="min-w-0 flex-1">
                <span class="block truncate font-medium text-text-main">{{ e.name }}</span>
                <span class="block text-[11px] text-text-muted truncate">{{ [e.subject, e.class, e.status, e.starts_at ? formatLocalDate(e.starts_at) : null].filter(Boolean).join(' · ') }}</span>
              </span>
            </button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
        <label class="block">
          <span class="lbl">Enrollment {{ hasScope ? 'in chosen olympiad / exam' : '' }}</span>
          <select v-model="a.enrollment_status" class="field">
            <option value="all">{{ hasScope ? 'Enrolled' : 'Any' }}</option>
            <option v-if="!hasScope" value="enrolled">Enrolled in any exam</option>
            <option value="not_enrolled">Not enrolled{{ hasScope ? '' : ' in any exam' }}</option>
            <option value="cancelled">Enrollment cancelled</option>
          </select>
        </label>
        <label class="block">
          <span class="lbl">Exam attempt</span>
          <select v-model="a.attempt_status" class="field">
            <option value="all">Any</option>
            <option value="attempted">Started / submitted</option>
            <option value="not_attempted">Not attempted yet</option>
          </select>
        </label>
        <label class="block">
          <span class="lbl">Payment</span>
          <select v-model="a.payment_status" class="field">
            <option value="all">Any</option>
            <option value="paid">Paid</option>
            <option value="unpaid">No paid payment</option>
            <option value="pending">Payment pending</option>
            <option value="failed">Payment failed</option>
            <option value="refunded">Refunded</option>
          </select>
        </label>
        <label class="block">
          <span class="lbl">Hall ticket</span>
          <select v-model="a.hall_ticket" class="field">
            <option value="all">Any</option>
            <option value="approved">Approved</option>
            <option value="pending">Awaiting verification</option>
            <option value="rejected">Rejected</option>
            <option value="none">Not submitted</option>
          </select>
        </label>
        <label class="block">
          <span class="lbl">Registered via</span>
          <select v-model="a.registration_source" class="field">
            <option value="all">Any</option>
            <option value="website">Website</option>
            <option value="marketing">Marketing page</option>
            <option value="admin">Added by admin</option>
          </select>
        </label>
        <label class="block">
          <span class="lbl">Account</span>
          <select v-model="a.student_status" class="field">
            <option value="active">Active only</option>
            <option value="inactive">Inactive only</option>
            <option value="all">All accounts</option>
          </select>
        </label>
      </div>

      <details class="group" :open="hasPlaceFilters">
        <summary class="cursor-pointer select-none text-xs font-semibold text-primary list-none flex items-center gap-1">
          <span class="transition-transform group-open:rotate-90">›</span> School &amp; location
          <span v-if="hasPlaceFilters" class="ml-1 text-[10px] px-1.5 rounded bg-primary/10">on</span>
        </summary>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mt-3">
          <label class="block xl:col-span-2">
            <span class="lbl">School <span class="normal-case font-normal">(branch)</span></span>
            <input v-model.lazy="a.school" type="search" class="field" placeholder="Contains… e.g. Delhi Public" />
          </label>
          <label class="block">
            <span class="lbl">City</span>
            <input v-model.lazy="a.city" type="search" class="field" placeholder="Contains…" />
          </label>
          <label class="block">
            <span class="lbl">State</span>
            <select v-model="a.state" class="field">
              <option value="">All states</option>
              <option v-for="s in options.states" :key="s" :value="s">{{ s }}</option>
            </select>
          </label>
        </div>
      </details>

      <label class="block">
        <span class="sr-only">Search inside the filtered students</span>
        <input v-model="searchDraft" type="search" class="field" placeholder="Narrow by name, email, phone or school (optional)"
               @keydown.enter.prevent="a.search = searchDraft" @search="a.search = searchDraft" @blur="a.search = searchDraft" />
      </label>
    </div>

    <!-- PICK -->
    <div v-else class="p-4 sm:p-5 space-y-3">
      <input v-model="pickSearch" type="search" class="field" placeholder="Search students by name, email, phone, school or city" aria-label="Search students" />
      <div class="border border-gray-100 rounded-xl overflow-hidden">
        <div class="px-3 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between text-[11px] font-semibold text-text-muted uppercase tracking-wider">
          <span>{{ pickSearch ? 'Search results' : 'Recently registered' }}</span>
          <span class="flex items-center gap-3">
            <span v-if="pickLoading" class="text-accent normal-case">Searching…</span>
            <button v-if="pickResults.length" type="button" class="text-primary normal-case hover:underline" @click="addAllResults">Add all {{ pickResults.length }}</button>
          </span>
        </div>
        <p v-if="!pickResults.length && !pickLoading" class="p-4 text-xs text-text-muted">No matching students.</p>
        <ul class="max-h-64 overflow-y-auto divide-y divide-gray-50">
          <li v-for="s in pickResults" :key="s.id">
            <button type="button" class="w-full px-3 py-2.5 text-left hover:bg-gray-50 flex items-center justify-between gap-3" @click="togglePick(s)">
              <span class="min-w-0">
                <span class="block text-sm font-semibold text-text-main truncate">{{ s.name }}</span>
                <span class="block text-[11px] text-text-muted truncate">{{ [s.class, s.email, s.phone].filter(Boolean).join(' · ') }}</span>
              </span>
              <span class="shrink-0 text-[10px] font-bold px-2 py-1 rounded-lg"
                    :class="a.selected_ids.includes(s.id) ? 'bg-success/10 text-success' : 'bg-primary/10 text-primary'">
                {{ a.selected_ids.includes(s.id) ? 'Added ✓' : 'Add' }}
              </span>
            </button>
          </li>
        </ul>
      </div>
      <div v-if="a.selected_ids.length" class="flex items-center justify-between text-xs">
        <span class="text-text-muted">{{ plural(a.selected_ids.length, 'student') }} picked</span>
        <button type="button" class="text-danger font-semibold hover:underline" @click="a.selected_ids = []">Clear all</button>
      </div>
    </div>

    <!-- SUMMARY + REVIEW -->
    <div class="border-t border-gray-100 bg-gray-50/60 p-4 sm:p-5">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="lbl mb-0">Recipients on {{ channelLabel }}</p>
          <p class="font-number text-3xl font-bold leading-tight" :class="summary.recipients ? 'text-primary' : 'text-danger'" aria-live="polite">
            {{ loading && summary.recipients === null ? '…' : (summary.recipients ?? 0).toLocaleString('en-IN') }}
          </p>
          <p class="text-[11px] text-text-muted">
            {{ plural(summary.matched ?? 0, 'student') }} matched
            <template v-if="summary.excluded"> · {{ summary.excluded }} unticked</template>
            <template v-if="summary.unreachable"> · <span class="text-gold-dark font-semibold">{{ summary.unreachable }} without {{ reachLabel }}</span></template>
            <template v-if="summary.missing_selected"> · {{ summary.missing_selected }} picked id(s) not found</template>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <span v-if="loading" class="text-[11px] font-semibold text-accent">Counting…</span>
          <button type="button" class="btn-ghost" :aria-expanded="showList" @click="showList = !showList">
            {{ showList ? 'Hide students' : 'Review students' }}
          </button>
        </div>
      </div>
      <p v-if="error" class="text-danger text-xs mt-2">{{ error }}</p>
      <p v-if="a.excluded_ids.length" class="text-[11px] mt-2">
        <button type="button" class="text-primary font-semibold hover:underline" @click="a.excluded_ids = []">Include all {{ a.excluded_ids.length }} unticked again</button>
      </p>

      <div v-if="showList" class="mt-3 bg-white border border-gray-100 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[560px]">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="th w-10">
                  <input type="checkbox" class="rounded" :checked="pageAllIncluded" :aria-label="'Include every student on this page'" @change="togglePage($event.target.checked)" />
                </th>
                <th class="th">Student</th>
                <th class="th">Class</th>
                <th class="th">{{ channel === 'whatsapp' ? 'WhatsApp number' : channel === 'email' ? 'Email' : 'Contact' }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="!students.data.length"><td colspan="4" class="px-4 py-6 text-center text-xs text-text-muted">No students match.</td></tr>
              <tr v-for="s in students.data" :key="s.id" :class="{ 'opacity-50': !included(s) }">
                <td class="px-4 py-2.5">
                  <input type="checkbox" class="rounded" :checked="included(s)" :disabled="!s.reachable" :aria-label="`Include ${s.name}`" @change="toggleRow(s, $event.target.checked)" />
                </td>
                <td class="px-4 py-2.5 min-w-0">
                  <p class="font-semibold text-text-main truncate max-w-[240px]">{{ s.name }}</p>
                  <p class="text-[11px] text-text-muted truncate max-w-[240px]">{{ [s.school, s.city].filter(Boolean).join(' · ') || '—' }}</p>
                </td>
                <td class="px-4 py-2.5 text-xs text-text-muted whitespace-nowrap">{{ s.class || '—' }}</td>
                <td class="px-4 py-2.5 text-xs">
                  <span v-if="!s.reachable" class="text-gold-dark font-semibold">No {{ reachLabel }}</span>
                  <span v-else class="text-text-muted truncate">{{ channel === 'whatsapp' ? s.phone : s.email }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="students.last_page > 1" class="flex items-center justify-between px-4 py-2.5 border-t border-gray-100 text-xs">
          <span class="text-text-muted">{{ students.from }}–{{ students.to }} of {{ students.total.toLocaleString('en-IN') }}</span>
          <span class="flex gap-1">
            <button type="button" class="btn-ghost" :disabled="students.current_page <= 1" @click="loadPage(students.current_page - 1)">‹ Prev</button>
            <button type="button" class="btn-ghost" :disabled="students.current_page >= students.last_page" @click="loadPage(students.current_page + 1)">Next ›</button>
          </span>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { formatLocalDate } from '@/Services/localTime.service';
import { CHANNELS, defaultAudience, errorText, plural } from './notifications';

const props = defineProps({
  modelValue: { type: Object, required: true },
  channel: { type: String, required: true },
  options: { type: Object, required: true },
});
const emit = defineEmits(['update:modelValue', 'summary']);

const a = reactive({ ...defaultAudience(), ...props.modelValue });
watch(a, () => emit('update:modelValue', JSON.parse(JSON.stringify(a))), { deep: true });

const summary = ref({ recipients: null, matched: null, excluded: 0, unreachable: 0 });
const students = ref({ data: [], current_page: 1, last_page: 1, total: 0 });
const loading = ref(false);
const error = ref('');
const showList = ref(false);
const searchDraft = ref(a.search);

const channelLabel = computed(() => CHANNELS[props.channel]?.label ?? props.channel);
const reachLabel = computed(() => props.channel === 'whatsapp' ? 'WhatsApp number' : 'email');
const hasScope = computed(() => a.exam_ids.length > 0 || a.subject_ids.length > 0);
const hasPlaceFilters = computed(() => !!(a.school || a.city || a.state));
const activeCount = computed(() => {
  const d = defaultAudience();
  return Object.keys(d).filter((k) => !['mode', 'selected_ids', 'excluded_ids'].includes(k) && JSON.stringify(a[k]) !== JSON.stringify(d[k])).length;
});

const presets = [
  { key: 'all', label: 'All active students', set: {} },
  { key: 'enrolled', label: 'Enrolled students', set: { enrollment_status: 'enrolled' } },
  { key: 'unpaid', label: 'Not paid yet', set: { payment_status: 'unpaid' } },
  { key: 'pending', label: 'Payment pending', set: { payment_status: 'pending' } },
  { key: 'ticket', label: 'Hall ticket not submitted', set: { hall_ticket: 'none', enrollment_status: 'enrolled' } },
];
const applyPreset = (preset) => Object.assign(a, defaultAudience(), { mode: 'filters' }, preset.set);
const reset = () => { Object.assign(a, defaultAudience()); searchDraft.value = ''; };

const toggle = (key, id) => {
  a[key] = a[key].includes(id) ? a[key].filter((x) => x !== id) : [...a[key], id];
};

// exams
const examSearch = ref('');
const examOpen = ref(false);
const examBox = ref(null);
const selectedExams = computed(() => props.options.exams.filter((e) => a.exam_ids.includes(e.id)));
const examMatches = computed(() => {
  const q = examSearch.value.trim().toLowerCase();
  return props.options.exams
    .filter((e) => !a.subject_ids.length || a.subject_ids.includes(e.subject_id))
    .filter((e) => !q || `${e.name} ${e.subject} ${e.class}`.toLowerCase().includes(q))
    .slice(0, 60);
});
const closeExams = (event) => { if (examBox.value && !examBox.value.contains(event.target)) examOpen.value = false; };
onMounted(() => document.addEventListener('mousedown', closeExams));
onBeforeUnmount(() => document.removeEventListener('mousedown', closeExams));

// mode
const setMode = (mode) => {
  if (a.mode === mode) return;
  a.mode = mode;
  a.excluded_ids = [];
};

// pick mode
const pickSearch = ref('');
const pickResults = ref([]);
const pickLoading = ref(false);
let pickTimer = null;
const loadPick = async () => {
  pickLoading.value = true;
  try {
    const { data } = await window.axios.get(route('admin.notifications.students'), { params: { search: pickSearch.value } });
    pickResults.value = data.students ?? [];
  } catch (e) {
    pickResults.value = [];
  } finally {
    pickLoading.value = false;
  }
};
watch(pickSearch, () => { clearTimeout(pickTimer); pickTimer = setTimeout(loadPick, 250); });
watch(() => a.mode, (m) => { if (m === 'selected' && !pickResults.value.length) loadPick(); }, { immediate: true });
const togglePick = (s) => toggle('selected_ids', s.id);
const addAllResults = () => { a.selected_ids = [...new Set([...a.selected_ids, ...pickResults.value.map((s) => s.id)])]; };

// review list
const included = (s) => s.reachable && (a.mode === 'selected' ? a.selected_ids.includes(s.id) : !a.excluded_ids.includes(s.id));
const toggleRow = (s, on) => {
  if (a.mode === 'selected') {
    if (!on) a.selected_ids = a.selected_ids.filter((x) => x !== s.id);
    return;
  }
  a.excluded_ids = on ? a.excluded_ids.filter((x) => x !== s.id) : [...new Set([...a.excluded_ids, s.id])];
};
const pageAllIncluded = computed(() => students.value.data.filter((s) => s.reachable).every(included));
const togglePage = (on) => students.value.data.filter((s) => s.reachable).forEach((s) => toggleRow(s, on));

// live count
let timer = null;
let seq = 0;
const payload = (page = 1) => ({ channel: props.channel, audience: JSON.parse(JSON.stringify(a)), page });
const refresh = async (page = students.value.current_page || 1) => {
  const mine = ++seq;
  loading.value = true;
  error.value = '';
  try {
    const { data } = await window.axios.post(route('admin.notifications.audience'), payload(page));
    if (mine !== seq) return;
    summary.value = data.summary;
    students.value = data.students;
    emit('summary', { ...data.summary, chips: data.chips });
  } catch (e) {
    if (mine !== seq) return;
    error.value = errorText(e, 'Could not count recipients for these filters.');
    emit('summary', { recipients: 0, matched: 0, chips: [] });
  } finally {
    if (mine === seq) loading.value = false;
  }
};
const loadPage = (page) => refresh(page);
const schedule = () => { clearTimeout(timer); timer = setTimeout(() => refresh(1), 300); };

watch(() => JSON.stringify({ ...a, excluded_ids: [] }), schedule);
watch(() => a.excluded_ids.length, () => { clearTimeout(timer); timer = setTimeout(() => refresh(students.value.current_page), 200); });
watch(() => props.channel, schedule);
onMounted(() => refresh(1));

// parent resets after a send
watch(() => props.modelValue, (v) => {
  if (JSON.stringify(v) !== JSON.stringify(a)) {
    Object.assign(a, defaultAudience(), v);
    searchDraft.value = a.search;
  }
});
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm; }
.card-h { @apply flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100; }
.step { @apply w-7 h-7 shrink-0 rounded-full bg-primary text-white font-number font-bold text-sm flex items-center justify-center; }
.lbl { @apply block text-[11px] font-semibold text-text-muted uppercase tracking-wider mb-1.5; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main; }
.seg { @apply inline-flex p-1 rounded-xl bg-gray-100; }
.seg button { @apply px-3 py-1.5 rounded-lg text-xs font-semibold text-text-muted transition-colors; }
.seg button.on { @apply bg-white text-primary shadow-sm; }
.pill { @apply px-3 py-1.5 rounded-full border border-gray-200 text-xs font-semibold text-text-main bg-white hover:border-primary/50 transition-colors; }
.pill.on { @apply bg-primary text-white border-primary; }
.preset { @apply px-3 py-1.5 rounded-lg text-xs font-semibold bg-accent/10 text-accent-dark hover:bg-accent/20 transition-colors; }
.preset.ghost { @apply bg-transparent text-text-muted hover:text-danger; }
.tag { @apply inline-flex items-center gap-1.5 pl-2.5 pr-2 py-1 rounded-lg bg-primary/10 text-primary text-xs font-semibold; }
.th { @apply text-left px-4 py-2.5 text-text-muted text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap; }
.btn-ghost { @apply px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-text-main bg-white hover:border-primary hover:text-primary transition-colors disabled:opacity-40 disabled:pointer-events-none; }
</style>
