<script setup>
import { computed } from 'vue';

const props = defineProps({
  state: { type: String, required: true },
  offlineSeconds: { type: Number, default: 0 },
});

const duration = (s) => (s >= 3600 ? `${Math.floor(s / 3600)}h ${Math.floor((s % 3600) / 60)}m` : `${Math.max(1, Math.round(s / 60))} min`);

const meta = computed(() => ({
  online: { label: 'Online', cls: 'bg-green-50 text-success border-green-200', dot: 'bg-success animate-pulse' },
  unstable: { label: `Unstable · ${duration(props.offlineSeconds)}`, cls: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
  offline: { label: `Offline · ${duration(props.offlineSeconds)}`, cls: 'bg-red-50 text-danger border-red-200', dot: 'bg-danger' },
  paused: { label: 'Paused', cls: 'bg-purple-50 text-purple-700 border-purple-200', dot: 'bg-purple-600' },
  rescheduled: { label: 'Rescheduled', cls: 'bg-purple-50 text-purple-700 border-purple-200', dot: 'bg-purple-400' },
  time_over: { label: 'Time over', cls: 'bg-red-50 text-danger border-red-200', dot: 'bg-danger' },
  submitted: { label: 'Submitted', cls: 'bg-gray-100 text-text-muted border-gray-200', dot: 'bg-gray-400' },
  timed_out: { label: 'Timed out', cls: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
  auto_submitted: { label: 'Auto-submitted', cls: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
}[props.state] ?? { label: props.state, cls: 'bg-gray-100 text-text-muted border-gray-200', dot: 'bg-gray-400' }));
</script>

<template>
  <span class="inline-flex items-center gap-1.5 whitespace-nowrap text-[11px] font-semibold px-2 py-1 rounded-lg border" :class="meta.cls">
    <span class="w-1.5 h-1.5 rounded-full motion-reduce:animate-none" :class="meta.dot"></span>
    {{ meta.label }}
  </span>
</template>
