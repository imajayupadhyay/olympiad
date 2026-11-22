<template>
  <section class="card" aria-labelledby="usage-h">
    <header class="card-h">
      <div class="min-w-0">
        <h2 id="usage-h" class="font-heading font-bold text-text-main text-sm">{{ provider.label }} message log</h2>
        <p class="text-[11px] text-text-muted">Every WhatsApp message records the provider that sent it, so this survives switching.</p>
      </div>
      <Link v-if="canViewHistory" :href="route('admin.notifications', { tab: 'history', channel: 'whatsapp', provider: provider.key })" class="btn-ghost">View broadcasts →</Link>
    </header>
    <div class="p-4 sm:p-5">
      <dl class="grid grid-cols-3 gap-2">
        <div v-for="s in stats" :key="s.label" class="rounded-xl bg-gray-50 p-2.5 text-center">
          <dt class="text-[10px] uppercase tracking-wider text-text-muted">{{ s.label }}</dt>
          <dd class="font-number font-bold text-lg" :class="s.cls">{{ s.value.toLocaleString() }}</dd>
        </div>
      </dl>
      <p class="text-[11px] text-text-muted mt-3">
        Last 30 days · {{ provider.usage.broadcasts.toLocaleString() }} broadcast(s) in total<template v-if="provider.usage.last_broadcast_at">, latest {{ formatLocal(provider.usage.last_broadcast_at) }}</template>.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { formatLocal } from '@/Services/localTime.service';

const props = defineProps({
  provider: { type: Object, required: true },
  canViewHistory: { type: Boolean, default: false },
});

const stats = computed(() => {
  const u = props.provider.usage;
  return [
    { label: 'Sent', value: u.sent_30d, cls: 'text-text-main' },
    { label: 'Delivered', value: u.delivered_30d, cls: 'text-success' },
    { label: 'Read', value: u.read_30d, cls: 'text-success' },
    { label: 'Failed', value: u.failed_30d, cls: 'text-danger' },
    { label: 'Skipped', value: u.skipped_30d, cls: 'text-text-muted' },
    { label: 'Pending', value: u.pending_30d, cls: 'text-accent-dark' },
  ];
});
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm; }
.card-h { @apply flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100; }
.btn-ghost { @apply px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-text-main bg-white hover:border-primary hover:text-primary transition-colors; }
</style>
