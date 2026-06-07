<template>
  <AdminLayout title="Certificates" subtitle="Upload templates and issue certificates to students">

    <!-- Empty -->
    <div v-if="exams.length === 0" class="py-24 text-center">
      <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
      </div>
      <p class="font-heading font-bold text-text-main text-base mb-1">No exams yet</p>
      <p class="text-text-muted text-sm">Published and archived exams will appear here for certificate management.</p>
    </div>

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
              <span class="text-xs text-text-muted bg-gray-100 font-semibold px-2 py-0.5 rounded-md">
                {{ exam.class_level?.label }}
              </span>
            </div>
            <h3 class="font-heading font-bold text-text-main text-sm leading-snug">{{ exam.name }}</h3>
          </div>
          <!-- Template badge -->
          <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full"
                :class="exam.template ? 'bg-success/10 text-success' : 'bg-gray-100 text-text-muted'">
            {{ exam.template ? 'Template ✓' : 'No Template' }}
          </span>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-3 mb-4">
          <div class="text-center bg-gray-50 rounded-xl py-2.5">
            <p class="font-number font-bold text-lg text-text-main">{{ exam.released_results }}</p>
            <p class="text-text-muted text-[10px] font-semibold uppercase tracking-wider">Released</p>
          </div>
          <div class="text-center bg-gray-50 rounded-xl py-2.5">
            <p class="font-number font-bold text-lg" :class="exam.student_certs > 0 ? 'text-success' : 'text-text-muted'">
              {{ exam.student_certs }}
            </p>
            <p class="text-text-muted text-[10px] font-semibold uppercase tracking-wider">Issued</p>
          </div>
          <div class="text-center bg-gray-50 rounded-xl py-2.5">
            <p class="font-number font-bold text-lg"
               :class="exam.released_results - exam.student_certs > 0 ? 'text-amber-500' : 'text-text-muted'">
              {{ Math.max(0, exam.released_results - exam.student_certs) }}
            </p>
            <p class="text-text-muted text-[10px] font-semibold uppercase tracking-wider">Pending</p>
          </div>
        </div>

        <!-- Template info -->
        <div v-if="exam.template" class="flex items-center gap-2 mb-4 bg-success/5 border border-success/20 rounded-xl px-3 py-2.5">
          <svg class="w-4 h-4 text-success shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <div class="min-w-0 flex-1">
            <p class="text-success text-xs font-semibold truncate">{{ exam.template.original_name }}</p>
            <p class="text-text-muted text-[10px]">{{ formatSize(exam.template.file_size) }}</p>
          </div>
        </div>
        <div v-else class="flex items-center gap-2 mb-4 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2.5">
          <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
          <p class="text-amber-700 text-xs font-semibold">No template uploaded yet</p>
        </div>

        <!-- Action -->
        <Link :href="route('admin.certificates.show', exam.id)"
              class="w-full flex items-center justify-center gap-2 bg-primary/5 hover:bg-primary/10 text-primary border border-primary/20 py-2.5 rounded-xl text-sm font-semibold transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          Manage Certificates
        </Link>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ exams: Array });

const formatSize = (bytes) => {
  if (!bytes) return '';
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
};
</script>
