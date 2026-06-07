<template>
  <AdminLayout :title="student.name" subtitle="Student Profile">

    <!-- Back + actions bar -->
    <div class="flex items-center justify-between mb-6">
      <Link :href="route('admin.users.index')"
            class="inline-flex items-center gap-2 text-text-muted hover:text-text-main text-sm font-semibold transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to Students
      </Link>

      <div class="flex items-center gap-3">
        <Link :href="route('admin.users.edit', student.id)"
              class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
          Edit Profile
        </Link>
        <button
          @click="showToggleModal = true"
          class="text-sm font-semibold px-4 py-2 rounded-xl transition-colors border"
          :class="student.is_active
            ? 'border-amber-300 text-amber-600 hover:bg-amber-50'
            : 'border-success/30 text-success hover:bg-success/5'"
        >
          {{ student.is_active ? 'Disable Account' : 'Enable Account' }}
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Left: Profile card -->
      <div class="lg:col-span-1 space-y-5">

        <!-- Avatar + name -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 text-center">
          <div class="w-20 h-20 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4 text-primary font-heading font-bold text-2xl">
            {{ initials(student.name) }}
          </div>
          <h2 class="font-heading font-bold text-text-main text-lg">{{ student.name }}</h2>
          <p class="text-text-muted text-sm mt-0.5">{{ student.email }}</p>
          <div class="mt-3">
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full"
                  :class="student.is_active ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'">
              <span class="w-1.5 h-1.5 rounded-full" :class="student.is_active ? 'bg-success' : 'bg-danger'"></span>
              {{ student.is_active ? 'Active' : 'Disabled' }}
            </span>
          </div>
          <p class="text-text-muted text-xs mt-3 font-number">
            Joined {{ formatDate(student.created_at) }}
          </p>
        </div>

        <!-- Details -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h3 class="font-heading font-bold text-text-main text-sm mb-4">Profile Details</h3>
          <div class="space-y-3">
            <div class="flex justify-between items-center text-sm">
              <span class="text-text-muted">Class</span>
              <span class="text-text-main font-semibold font-number">
                {{ student.class_level?.label || '—' }}
              </span>
            </div>
            <div class="flex justify-between items-center text-sm">
              <span class="text-text-muted">Phone</span>
              <span class="text-text-main font-number">{{ student.phone || '—' }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
              <span class="text-text-muted">Date of Birth</span>
              <span class="text-text-main font-number">{{ student.dob ? formatDate(student.dob) : '—' }}</span>
            </div>
            <div class="border-t border-gray-50 pt-3">
              <p class="text-text-muted text-xs mb-1">School</p>
              <p class="text-text-main text-sm font-medium">{{ student.school || '—' }}</p>
            </div>
            <div>
              <p class="text-text-muted text-xs mb-1">Location</p>
              <p class="text-text-main text-sm">
                {{ [student.city, student.state].filter(Boolean).join(', ') || '—' }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Activity sections -->
      <div class="lg:col-span-2 space-y-5">

        <!-- Exam Enrollments placeholder -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-heading font-bold text-text-main text-sm">Exam Enrollments</h3>
            <span class="bg-gray-100 text-text-muted text-xs font-semibold px-2.5 py-1 rounded-lg font-number">0</span>
          </div>
          <div class="py-10 text-center text-text-muted text-sm">
            <svg class="w-10 h-10 mx-auto mb-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            No enrollments yet — available once the exam room is live.
          </div>
        </div>

        <!-- Results placeholder -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-heading font-bold text-text-main text-sm">Results & Scores</h3>
            <span class="bg-gray-100 text-text-muted text-xs font-semibold px-2.5 py-1 rounded-lg font-number">0</span>
          </div>
          <div class="py-10 text-center text-text-muted text-sm">
            <svg class="w-10 h-10 mx-auto mb-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Results will appear here after students attempt exams.
          </div>
        </div>

        <!-- Account info -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h3 class="font-heading font-bold text-text-main text-sm mb-4">Account Information</h3>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-text-muted text-xs mb-0.5">Email Verified</p>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-md"
                    :class="student.email_verified_at ? 'bg-success/10 text-success' : 'bg-amber-100 text-amber-600'">
                {{ student.email_verified_at ? 'Verified' : 'Pending' }}
              </span>
            </div>
            <div>
              <p class="text-text-muted text-xs mb-0.5">Account Role</p>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-primary/10 text-primary">
                {{ student.role }}
              </span>
            </div>
            <div>
              <p class="text-text-muted text-xs mb-0.5">Registered On</p>
              <p class="text-text-main font-number text-xs">{{ formatDate(student.created_at) }}</p>
            </div>
            <div>
              <p class="text-text-muted text-xs mb-0.5">Student ID</p>
              <p class="text-text-muted font-number text-xs">#{{ String(student.id).padStart(5, '0') }}</p>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Confirm toggle modal -->
    <div v-if="showToggleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4"
             :class="student.is_active ? 'bg-danger/10' : 'bg-success/10'">
          <svg class="w-6 h-6" :class="student.is_active ? 'text-danger' : 'text-success'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path v-if="student.is_active" stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">
          {{ student.is_active ? 'Disable Account?' : 'Enable Account?' }}
        </h3>
        <p class="text-text-muted text-sm mb-5">
          {{ student.is_active
            ? `${student.name} will not be able to log in until re-enabled.`
            : `${student.name} will be able to log in again.` }}
        </p>
        <div class="flex gap-3">
          <button @click="showToggleModal = false" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">
            Cancel
          </button>
          <button @click="doToggle"
                  class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white transition-colors"
                  :class="student.is_active ? 'bg-danger hover:bg-red-700' : 'bg-success hover:bg-green-700'">
            {{ student.is_active ? 'Disable' : 'Enable' }}
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  student: Object,
});

const showToggleModal = ref(false);
const doToggle = () => {
  router.patch(route('admin.users.toggle', props.student.id), {
    is_active: !props.student.is_active,
  }, {
    onFinish: () => { showToggleModal.value = false; },
  });
};

const initials = (name) =>
  name?.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() || '?';

const formatDate = (d) =>
  new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
</script>
