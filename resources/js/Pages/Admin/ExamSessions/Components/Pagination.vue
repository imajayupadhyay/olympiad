<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ page: { type: Object, required: true } });
</script>

<template>
  <div v-if="page.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2">
    <p class="text-text-muted text-xs">Showing {{ page.from }}–{{ page.to }} of {{ page.total }}</p>
    <div class="flex flex-wrap gap-1">
      <Link v-for="(link, index) in page.links" :key="index" :href="link.url || '#'" preserve-scroll preserve-state
            :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                     link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100',
                     !link.url ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" />
    </div>
  </div>
</template>
