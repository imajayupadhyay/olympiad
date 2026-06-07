<template>
  <StudentLayout title="Dashboard">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <p class="text-text-muted text-sm">Registered Exams</p>
        <p class="font-number font-bold text-3xl text-primary mt-1">0</p>
      </div>
      <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <p class="text-text-muted text-sm">Results Available</p>
        <p class="font-number font-bold text-3xl text-success mt-1">0</p>
      </div>
      <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <p class="text-text-muted text-sm">Certificates Earned</p>
        <p class="font-number font-bold text-3xl text-gold mt-1">0</p>
      </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
      <h2 class="font-heading font-bold text-text-main mb-4">Upcoming Exams</h2>
      <p class="text-text-muted text-sm">No upcoming exams. <Link href="/student/exams" class="text-accent font-semibold hover:underline">Browse available exams →</Link></p>
    </div>
  </StudentLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';
</script>
