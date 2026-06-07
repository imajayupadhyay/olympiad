<template>
  <Link
    :href="href"
    class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-all duration-150 text-sm"
    :class="active
      ? 'bg-accent/90 text-white font-semibold shadow-sm'
      : 'text-blue-200 hover:bg-white/10 hover:text-white'"
  >
    <span class="shrink-0 opacity-80">
      <slot name="icon" />
    </span>
    <slot />
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  href:   { type: String, required: true },
  active: { type: Boolean, default: false },
});
</script>
