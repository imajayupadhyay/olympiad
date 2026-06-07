<template>
  <AdminLayout title="Content">
    <div class="flex flex-col items-center justify-center py-20 text-center">
      <div class="w-20 h-20 rounded-2xl bg-primary/10 flex items-center justify-center mb-5">
        <svg class="w-10 h-10 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
        </svg>
      </div>
      <h2 class="font-heading font-bold text-text-main text-xl mb-2">Content</h2>
      <p class="text-text-muted text-sm max-w-sm">This section is under development and will be available in the next build phase.</p>
      <Link :href="route('admin.dashboard')" class="mt-6 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">
        Back to Dashboard
      </Link>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
</script>
