<template>
  <AdminLayout title="Results" subtitle="Process and release exam results">

    <!-- Empty state -->
    <div v-if="exams.length === 0" class="py-24 text-center">
      <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
      </div>
      <p class="font-heading font-bold text-text-main text-base mb-1">No exams with results yet</p>
      <p class="text-text-muted text-sm">Published and archived exams will appear here once students start attempting them.</p>
    </div>

    <!-- Exam cards grid -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <div
        v-for="exam in exams"
        :key="exam.id"
        class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-shadow"
      >
        <!-- Header -->
        <div class="flex items-start justify-between gap-3 mb-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2 mb-1 flex-wrap">
              <span v-if="exam.subject" class="text-xs font-semibold px-2 py-0.5 rounded-md"
                    :style="{ background: exam.subject.color + '20', color: exam.subject.color }">
                {{ exam.subject.icon }} {{ exam.subject.name }}
              </span>
              <span class="text-xs font-semibold text-text-muted bg-gray-100 px-2 py-0.5 rounded-md">
                {{ exam.class_level?.label }}
              </span>
            </div>
            <h3 class="font-heading font-bold text-text-main text-sm leading-snug">{{ exam.name }}</h3>
            <p class="text-text-muted text-xs mt-0.5 font-number">
              {{ exam.starts_at ? formatDate(exam.starts_at) : 'No date set' }}
            </p>
          </div>
          <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full"
                :class="exam.status === 'published' ? 'bg-success/10 text-success' : 'bg-gray-100 text-text-muted'">
            {{ exam.status }}
          </span>
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-3 gap-3 mb-4">
          <div class="text-center bg-gray-50 rounded-xl py-2.5 px-2">
            <p class="font-number font-bold text-lg text-text-main">{{ exam.total_attempts }}</p>
            <p class="text-text-muted text-[10px] font-semibold uppercase tracking-wider">Attempts</p>
          </div>
          <div class="text-center bg-gray-50 rounded-xl py-2.5 px-2">
            <p class="font-number font-bold text-lg" :class="exam.processed_count > 0 ? 'text-primary' : 'text-text-muted'">
              {{ exam.processed_count }}
            </p>
            <p class="text-text-muted text-[10px] font-semibold uppercase tracking-wider">Processed</p>
          </div>
          <div class="text-center bg-gray-50 rounded-xl py-2.5 px-2">
            <p class="font-number font-bold text-lg" :class="exam.released_count > 0 ? 'text-success' : 'text-text-muted'">
              {{ exam.released_count }}
            </p>
            <p class="text-text-muted text-[10px] font-semibold uppercase tracking-wider">Released</p>
          </div>
        </div>

        <!-- Status indicator -->
        <div class="flex items-center gap-2 mb-4">
          <div v-if="exam.released_count > 0 && exam.released_count >= exam.processed_count"
               class="flex-1 flex items-center gap-2 bg-success/5 border border-success/20 rounded-xl px-3 py-2">
            <span class="w-2 h-2 rounded-full bg-success shrink-0"></span>
            <span class="text-success text-xs font-semibold">Results Released</span>
          </div>
          <div v-else-if="exam.processed_count > 0"
               class="flex-1 flex items-center gap-2 bg-primary/5 border border-primary/20 rounded-xl px-3 py-2">
            <span class="w-2 h-2 rounded-full bg-primary shrink-0"></span>
            <span class="text-primary text-xs font-semibold">Results Processed — Pending Release</span>
          </div>
          <div v-else-if="exam.total_attempts > 0"
               class="flex-1 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
            <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></span>
            <span class="text-amber-700 text-xs font-semibold">{{ exam.total_attempts }} attempts — not processed</span>
          </div>
          <div v-else
               class="flex-1 flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2">
            <span class="w-2 h-2 rounded-full bg-gray-300 shrink-0"></span>
            <span class="text-text-muted text-xs font-semibold">No attempts yet</span>
          </div>
        </div>

        <!-- Action -->
        <Link :href="route('admin.results.show', exam.id)"
              class="w-full flex items-center justify-center gap-2 bg-primary/5 hover:bg-primary/10 text-primary border border-primary/20 py-2.5 rounded-xl text-sm font-semibold transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          Manage Results
        </Link>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ exams: Array });

const formatDate = (d) =>
  new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
</script>
