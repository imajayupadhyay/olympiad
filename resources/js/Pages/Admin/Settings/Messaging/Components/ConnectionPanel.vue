<template>
  <section class="card" aria-labelledby="conn-h">
    <header class="card-h">
      <div class="min-w-0">
        <h2 id="conn-h" class="font-heading font-bold text-text-main text-sm">Configuration &amp; connection</h2>
        <p class="text-[11px] text-text-muted">A read-only check. Nothing is sent to students.</p>
      </div>
      <button type="button" class="btn-ghost" :disabled="checking" @click="check">{{ checking ? 'Checking…' : 'Test connection' }}</button>
    </header>

    <ul class="p-4 sm:p-5 space-y-2.5 text-sm">
      <li v-for="item in checklist" :key="item.label" class="flex items-start gap-2.5">
        <span class="mt-0.5 w-5 h-5 shrink-0 rounded-full grid place-items-center text-[11px] font-bold"
              :class="item.ok ? 'bg-success/10 text-success' : item.warn ? 'bg-gold/15 text-gold-dark' : 'bg-danger/10 text-danger'">
          {{ item.ok ? '✓' : item.warn ? '!' : '✕' }}
        </span>
        <span class="min-w-0">
          <span class="font-semibold text-text-main">{{ item.label }}</span>
          <span v-if="item.note" class="block text-[11px] text-text-muted">{{ item.note }}</span>
        </span>
      </li>
    </ul>

    <div v-if="result" class="mx-4 sm:mx-5 mb-5 rounded-xl border p-3 text-xs" role="status"
         :class="result.ok ? 'border-success/20 bg-success/5 text-success' : 'border-danger/20 bg-danger/5 text-danger'">
      <p class="font-semibold">{{ result.ok ? 'Connected' : 'Not connected' }}<span v-if="result.at" class="font-normal text-text-muted"> · {{ formatLocal(result.at) }}</span></p>
      <p class="mt-0.5 break-words">{{ result.message }}</p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { formatLocal } from '@/Services/localTime.service';
import { errorText } from '@/Pages/Admin/Notifications/Components/notifications';

const props = defineProps({ provider: { type: Object, required: true } });

const checking = ref(false);
const fresh = ref(null);
const result = computed(() => fresh.value
  ?? (props.provider.last_check?.at ? { ok: props.provider.last_check.ok, message: props.provider.last_check.message, at: props.provider.last_check.at } : null));

const checklist = computed(() => {
  const p = props.provider;
  const items = p.config.fields.filter((f) => f.required).map((f) => ({
    label: f.label,
    ok: f.source !== 'none',
    note: f.source === 'database' ? 'Saved in Settings' : f.source === 'env' ? `From .env (${f.env})` : `Missing — set it here or ${f.env} in .env`,
  }));
  items.push({
    label: 'Can send messages',
    ok: p.can_send,
    warn: !p.can_send && p.configured,
    note: p.can_send ? 'Ready for broadcasts and test messages' : p.unavailable_reason,
  });
  items.push({
    label: 'Delivery status tracking',
    ok: p.supports_status_sync,
    warn: !p.supports_status_sync,
    note: p.supports_status_sync ? 'Delivered / read / failed are polled every 5 minutes' : 'Not available yet for this provider',
  });
  return items;
});

const check = async () => {
  checking.value = true;
  try {
    const { data } = await window.axios.post(route('admin.settings.messaging.providers.check', props.provider.key));
    fresh.value = { ok: data.ok, message: data.message, at: data.checked_at };
  } catch (e) {
    fresh.value = { ok: false, message: errorText(e, 'The check could not run.'), at: null };
  } finally {
    checking.value = false;
  }
};
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm; }
.card-h { @apply flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100; }
.btn-ghost { @apply px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-text-main bg-white hover:border-primary hover:text-primary transition-colors disabled:opacity-40 disabled:pointer-events-none; }
</style>
