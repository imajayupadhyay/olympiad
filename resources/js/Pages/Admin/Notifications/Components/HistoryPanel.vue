<template>
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" aria-labelledby="hist-h">
    <header class="px-4 sm:px-5 py-4 border-b border-gray-100 space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="hist-h" class="font-heading font-bold text-text-main text-sm">Broadcast history</h2>
        <span class="text-xs text-text-muted font-number">{{ logs.total.toLocaleString('en-IN') }} total</span>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
        <select v-model="f.channel" class="field" aria-label="Channel" @change="apply">
          <option value="">All channels</option>
          <option v-for="(c, key) in CHANNELS" :key="key" :value="key">{{ c.label }}</option>
        </select>
        <select v-model="f.status" class="field" aria-label="Status" @change="apply">
          <option value="">Any status</option>
          <option v-for="(s, key) in LOG_STATUS" :key="key" :value="key">{{ s.label }}</option>
        </select>
        <input v-model="f.q" type="search" class="field sm:col-span-2" placeholder="Search by subject, title or template" @keydown.enter="apply" @search="apply" />
      </div>
    </header>

    <div v-if="!logs.data.length" class="py-16 text-center px-4">
      <p class="font-heading font-bold text-text-main">No broadcasts yet</p>
      <p class="text-text-muted text-sm mt-1">Messages you send from the Email, In-App and WhatsApp tabs appear here with their delivery status.</p>
    </div>

    <ul v-else class="divide-y divide-gray-50">
      <li v-for="log in logs.data" :key="log.id">
        <Link :href="route('admin.notifications.show', log.id)" class="block px-4 sm:px-5 py-4 hover:bg-gray-50/70 transition-colors">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2 mb-1">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="CHANNELS[log.channel]?.cls">{{ CHANNELS[log.channel]?.label ?? log.channel }}</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="LOG_STATUS[log.status]?.cls">{{ LOG_STATUS[log.status]?.label ?? log.status }}</span>
                <p class="font-semibold text-text-main text-sm truncate max-w-full">{{ log.title }}</p>
              </div>
              <p class="text-text-muted text-xs line-clamp-1">{{ log.excerpt }}</p>
              <div class="flex flex-wrap items-center gap-1.5 mt-2 text-[10px]">
                <span v-for="chip in log.audience_chips" :key="chip" class="px-1.5 py-0.5 rounded bg-royal/10 text-royal font-semibold truncate max-w-[220px]">{{ chip }}</span>
              </div>
            </div>
            <div class="text-right shrink-0">
              <p class="text-[11px] text-text-muted font-number">{{ formatLocal(log.created_at, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</p>
              <p v-if="log.sent_by" class="text-[11px] text-text-muted">by {{ log.sent_by }}</p>
            </div>
          </div>

          <div class="mt-3">
            <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden flex" role="img" :aria-label="`${log.sent_count} sent, ${log.failed_count} failed, ${log.pending_count} pending of ${log.recipient_count}`">
              <span class="bg-success" :style="{ width: pct(log.sent_count, log) }"></span>
              <span class="bg-danger" :style="{ width: pct(log.failed_count, log) }"></span>
              <span class="bg-gray-300" :style="{ width: pct(log.skipped_count, log) }"></span>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1.5 text-[11px]">
              <span><b class="font-number text-text-main">{{ log.recipient_count.toLocaleString('en-IN') }}</b> recipients</span>
              <span class="text-success"><b class="font-number">{{ log.sent_count.toLocaleString('en-IN') }}</b> sent</span>
              <span v-if="log.channel === 'whatsapp' && log.delivered_count" class="text-success"><b class="font-number">{{ log.delivered_count }}</b> delivered</span>
              <span v-if="log.read_count" class="text-success"><b class="font-number">{{ log.read_count }}</b> read</span>
              <span v-if="log.pending_count" class="text-accent-dark"><b class="font-number">{{ log.pending_count }}</b> pending</span>
              <span v-if="log.failed_count" class="text-danger"><b class="font-number">{{ log.failed_count }}</b> failed</span>
              <span v-if="log.skipped_count" class="text-text-muted"><b class="font-number">{{ log.skipped_count }}</b> skipped</span>
            </div>
          </div>
        </Link>
      </li>
    </ul>

    <div v-if="logs.last_page > 1" class="px-4 sm:px-5 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2">
      <p class="text-text-muted text-xs">Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }}</p>
      <div class="flex flex-wrap gap-1">
        <Link v-for="link in logs.links" :key="link.label" :href="link.url || '#'" preserve-scroll
              :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors', link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100', !link.url ? 'opacity-40 pointer-events-none' : '']"
              v-html="link.label" />
      </div>
    </div>
  </section>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { formatLocal } from '@/Services/localTime.service';
import { CHANNELS, LOG_STATUS } from './notifications';

const props = defineProps({
  logs: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const f = reactive({ channel: props.filters.channel ?? '', status: props.filters.status ?? '', q: props.filters.q ?? '' });
const apply = () => router.get(route('admin.notifications'), { tab: 'history', ...Object.fromEntries(Object.entries(f).filter(([, v]) => v)) }, { preserveScroll: true, preserveState: true, only: ['logs', 'historyFilters', 'tab'] });
const pct = (n, log) => `${log.recipient_count ? Math.min(100, (n / log.recipient_count) * 100) : 0}%`;
</script>

<style scoped>
.field { @apply w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main; }
</style>
