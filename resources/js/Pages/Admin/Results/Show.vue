<template>
  <AdminLayout :title="exam.name" subtitle="Exam Results">

    <!-- Back + actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <Link :href="route('admin.results')"
            class="inline-flex items-center gap-2 text-text-muted hover:text-text-main text-sm font-semibold transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        All Results
      </Link>
      <div class="flex items-center gap-3 flex-wrap">
        <!-- Process -->
        <button @click="confirmProcess = true"
                :disabled="stats.total_attempts === 0"
                class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-40 shadow-sm">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
          </svg>
          Process Results
        </button>
        <!-- Release -->
        <button @click="confirmRelease = true"
                :disabled="stats.processed === 0 || stats.released >= stats.processed"
                class="inline-flex items-center gap-2 bg-success text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-green-700 transition-colors disabled:opacity-40 shadow-sm">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
          </svg>
          Release to Students
        </button>
      </div>
    </div>

    <!-- Exam meta -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-5">
      <div class="flex items-center gap-3 flex-wrap">
        <span v-if="exam.subject" class="text-sm font-semibold px-3 py-1 rounded-lg"
              :style="{ background: exam.subject.color + '20', color: exam.subject.color }">
          {{ exam.subject.icon }} {{ exam.subject.name }}
        </span>
        <span class="text-sm font-semibold text-text-muted bg-gray-100 px-3 py-1 rounded-lg">{{ exam.class_level?.label }}</span>
        <span class="text-sm font-number text-text-muted">{{ exam.starts_at ? formatDate(exam.starts_at) : 'No date' }}</span>
        <span class="text-xs font-semibold px-2.5 py-1 rounded-full ml-auto"
              :class="exam.status === 'published' ? 'bg-success/10 text-success' : 'bg-gray-100 text-text-muted'">
          {{ exam.status }}
        </span>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-5">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 text-center">
        <p class="font-number text-2xl font-bold text-text-main">{{ stats.total_attempts }}</p>
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mt-1">Attempts</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 text-center">
        <p class="font-number text-2xl font-bold text-primary">{{ stats.processed }}</p>
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mt-1">Processed</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 text-center">
        <p class="font-number text-2xl font-bold text-success">{{ stats.released }}</p>
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mt-1">Released</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 text-center">
        <p class="font-number text-2xl font-bold text-gold">{{ fmt(stats.highest_score) }}</p>
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mt-1">Top Score</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 text-center">
        <p class="font-number text-2xl font-bold text-text-main">{{ fmt(stats.avg_score) }}</p>
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mt-1">Avg Score</p>
      </div>
    </div>

    <!-- Results table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

      <div v-if="results.data.length === 0" class="py-20 text-center">
        <div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <p class="font-heading font-bold text-text-main text-base mb-1">No results yet</p>
        <p class="text-text-muted text-sm">
          {{ stats.total_attempts > 0
            ? `${stats.total_attempts} attempt(s) ready. Click "Process Results" to calculate ranks.`
            : 'No student has attempted this exam yet.' }}
        </p>
      </div>

      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider w-16">Rank</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Student</th>
            <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">School</th>
            <th class="text-center px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Score</th>
            <th class="text-center px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">%</th>
            <th class="text-center px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Grade</th>
            <th class="text-center px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">C/W/S</th>
            <th class="text-center px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Status</th>
            <th class="text-right px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="r in results.data" :key="r.id" class="hover:bg-gray-50/40 transition-colors">

            <!-- Rank -->
            <td class="px-4 py-3.5">
              <div class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold font-number"
                   :class="rankStyle(r.rank_national)">
                {{ r.rank_national ?? '—' }}
              </div>
            </td>

            <!-- Student -->
            <td class="px-4 py-3.5">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0 text-primary font-bold text-xs">
                  {{ initials(r.user?.name) }}
                </div>
                <div class="min-w-0">
                  <p class="font-semibold text-text-main text-xs truncate">{{ r.user?.name }}</p>
                  <p class="text-text-muted text-[10px] truncate">{{ r.user?.email }}</p>
                </div>
              </div>
            </td>

            <td class="px-4 py-3.5 text-text-muted text-xs max-w-[120px] truncate">
              {{ r.user?.school || '—' }}
            </td>

            <!-- Score (effective — shows override if set) -->
            <td class="px-4 py-3.5 text-center">
              <span class="font-number font-bold text-sm" :class="r.score_override != null ? 'text-amber-600' : 'text-text-main'">
                {{ r.score_override != null ? fmt(r.score_override) : fmt(r.total_score) }}
              </span>
              <span class="text-text-muted text-xs"> / {{ fmt(r.max_score) }}</span>
              <span v-if="r.score_override != null" class="block text-[10px] text-amber-500 font-semibold">overridden</span>
            </td>

            <td class="px-4 py-3.5 text-center font-number text-sm text-text-main">
              {{ r.percentage }}%
            </td>

            <!-- Grade -->
            <td class="px-4 py-3.5 text-center">
              <span class="font-bold text-sm px-2 py-0.5 rounded" :class="gradeStyle(r.grade)">{{ r.grade }}</span>
            </td>

            <!-- Correct / Wrong / Skipped -->
            <td class="px-4 py-3.5 text-center font-number text-xs">
              <span class="text-success font-semibold">{{ r.attempt?.total_correct ?? '—' }}</span>
              <span class="text-text-muted mx-0.5">/</span>
              <span class="text-danger font-semibold">{{ r.attempt?.total_wrong ?? '—' }}</span>
              <span class="text-text-muted mx-0.5">/</span>
              <span class="text-text-muted">{{ r.attempt?.total_skipped ?? '—' }}</span>
            </td>

            <!-- Released -->
            <td class="px-4 py-3.5 text-center">
              <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-full"
                    :class="r.is_released ? 'bg-success/10 text-success' : 'bg-gray-100 text-text-muted'">
                <span class="w-1.5 h-1.5 rounded-full" :class="r.is_released ? 'bg-success' : 'bg-gray-400'"></span>
                {{ r.is_released ? 'Released' : 'Pending' }}
              </span>
            </td>

            <!-- Override action -->
            <td class="px-4 py-3.5 text-right">
              <button @click="openOverride(r)"
                      class="text-xs font-semibold text-amber-600 hover:text-amber-700 px-2.5 py-1 rounded-lg hover:bg-amber-50 transition-colors">
                Override
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="results.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-text-muted text-xs">Showing {{ results.from }}–{{ results.to }} of {{ results.total }}</p>
        <div class="flex gap-1">
          <Link
            v-for="link in results.links"
            :key="link.label"
            :href="link.url || '#'"
            :class="[
              'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
              link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100',
              !link.url ? 'opacity-40 pointer-events-none' : '',
            ]"
            v-html="link.label"
            preserve-scroll
          />
        </div>
      </div>
    </div>

    <!-- Process confirm modal -->
    <div v-if="confirmProcess" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Process Results?</h3>
        <p class="text-text-muted text-sm mb-1">This will calculate scores, assign national ranks, and compute grades for all <strong>{{ stats.total_attempts }}</strong> submitted attempts.</p>
        <p class="text-amber-600 text-xs mb-5">If results were previously processed, they will be recalculated.</p>
        <div class="flex gap-3">
          <button @click="confirmProcess = false" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
          <button @click="doProcess" class="flex-1 bg-primary text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">Process Now</button>
        </div>
      </div>
    </div>

    <!-- Release confirm modal -->
    <div v-if="confirmRelease" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-success/10 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Release Results?</h3>
        <p class="text-text-muted text-sm mb-5">All <strong>{{ stats.processed }}</strong> processed results will be made visible to students in their portal. This cannot be undone.</p>
        <div class="flex gap-3">
          <button @click="confirmRelease = false" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
          <button @click="doRelease" class="flex-1 bg-success text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-green-700 transition-colors">Release</button>
        </div>
      </div>
    </div>

    <!-- Override modal -->
    <div v-if="overrideTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-1">Override Score</h3>
        <p class="text-text-muted text-sm mb-4">{{ overrideTarget.user?.name }} — original: <span class="font-number font-bold">{{ fmt(overrideTarget.total_score) }}</span> / {{ fmt(overrideTarget.max_score) }}</p>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">New Score</label>
            <input v-model.number="overrideForm.score" type="number" step="0.5" :max="overrideTarget.max_score" min="0"
                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 font-number" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Reason <span class="text-danger">*</span></label>
            <textarea v-model="overrideForm.reason" rows="3" placeholder="e.g. Re-evaluation request approved…"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 resize-none"></textarea>
          </div>
        </div>
        <div class="flex gap-3 mt-4">
          <button @click="overrideTarget = null" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
          <button @click="doOverride" :disabled="!overrideForm.reason"
                  class="flex-1 bg-amber-500 text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-amber-600 transition-colors disabled:opacity-50">
            Apply Override
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  exam:    Object,
  results: Object,
  stats:   Object,
});

const fmt = (v) => (v != null ? parseFloat(v).toFixed(1) : '—');

const formatDate = (d) =>
  new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

const initials = (name) =>
  name?.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() || '?';

const rankStyle = (rank) => {
  if (rank === 1) return 'bg-gold/20 text-gold';
  if (rank === 2) return 'bg-gray-200 text-gray-600';
  if (rank === 3) return 'bg-amber-100 text-amber-700';
  return 'bg-gray-100 text-text-muted';
};

const gradeStyle = (g) => ({
  'A+': 'text-success bg-success/10',
  'A':  'text-success bg-success/10',
  'B':  'text-primary bg-primary/10',
  'C':  'text-amber-600 bg-amber-50',
  'D':  'text-amber-600 bg-amber-50',
  'F':  'text-danger bg-danger/10',
}[g] || 'text-text-muted bg-gray-100');

// Process
const confirmProcess = ref(false);
const doProcess = () => {
  router.post(route('admin.results.process', props.exam.id), {}, {
    onFinish: () => { confirmProcess.value = false; },
  });
};

// Release
const confirmRelease = ref(false);
const doRelease = () => {
  router.post(route('admin.results.release', props.exam.id), {}, {
    onFinish: () => { confirmRelease.value = false; },
  });
};

// Override
const overrideTarget = ref(null);
const overrideForm   = reactive({ score: 0, reason: '' });

const openOverride = (r) => {
  overrideTarget.value   = r;
  overrideForm.score     = r.score_override ?? r.total_score;
  overrideForm.reason    = r.override_reason ?? '';
};

const doOverride = () => {
  router.patch(route('admin.results.override', overrideTarget.value.id), {
    score_override:  overrideForm.score,
    override_reason: overrideForm.reason,
  }, {
    onFinish: () => { overrideTarget.value = null; },
  });
};
</script>
