<template>
  <AdminLayout :title="`Broadcast #${log.id}`" :subtitle="`${CHANNELS[log.channel]?.label ?? log.channel} · ${formatLocal(log.created_at, DATE_FMT)}`">
    <div v-if="flash.success" class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl" role="status">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl" role="alert">{{ flash.error }}</div>
    <div v-if="flash.info" class="mb-4 bg-blue-50 border border-blue-200 text-royal text-sm px-4 py-3 rounded-xl" role="status">{{ flash.info }}</div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <Link :href="route('admin.notifications', { tab: 'history' })" class="text-sm font-semibold text-primary hover:underline">← All broadcasts</Link>
      <div class="flex flex-wrap gap-2">
        <button v-if="log.channel === 'whatsapp'" type="button" class="btn-ghost" :disabled="busy" @click="refreshStatus">↻ Check delivery with Bird</button>
        <button v-if="canRetry && retryable && canWrite" type="button" class="btn-primary" :disabled="busy" @click="retryAll">Retry {{ retryable }} failed</button>
        <button v-if="canDelete && !inProgress" type="button" class="btn-danger" :disabled="busy" @click="destroy">Delete</button>
      </div>
    </div>

    <p v-if="inProgress" class="mb-4 text-xs text-text-muted flex items-center gap-2">
      <span class="w-2 h-2 rounded-full bg-accent animate-pulse motion-reduce:animate-none"></span>
      Sending… this page refreshes every 5 seconds.
    </p>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
      <!-- summary + message -->
      <div class="xl:col-span-4 space-y-5 min-w-0">
        <section class="card p-5">
          <div class="flex flex-wrap items-center gap-2 mb-3">
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="CHANNELS[log.channel]?.cls">{{ CHANNELS[log.channel]?.label }}</span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="LOG_STATUS[log.status]?.cls">{{ LOG_STATUS[log.status]?.label ?? log.status }}</span>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div v-for="t in tiles" :key="t.label" class="rounded-xl bg-gray-50 p-3">
              <p class="text-[10px] font-semibold uppercase tracking-wider text-text-muted">{{ t.label }}</p>
              <p class="font-number text-xl font-bold" :class="t.cls">{{ Number(t.value || 0).toLocaleString('en-IN') }}</p>
            </div>
          </div>
          <div class="h-2 rounded-full bg-gray-100 overflow-hidden flex mt-4">
            <span class="bg-success" :style="{ width: pct(log.sent_count) }"></span>
            <span class="bg-danger" :style="{ width: pct(log.failed_count) }"></span>
            <span class="bg-gray-300" :style="{ width: pct(log.skipped_count) }"></span>
          </div>
          <dl class="mt-4 space-y-1.5 text-xs">
            <div class="flex justify-between gap-3"><dt class="text-text-muted">Sent by</dt><dd class="font-semibold text-text-main">{{ log.sent_by || '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-text-muted">Started</dt><dd class="font-number">{{ formatLocal(log.created_at, DATE_FMT) }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-text-muted">Finished</dt><dd class="font-number">{{ log.completed_at ? formatLocal(log.completed_at, DATE_FMT) : '—' }}</dd></div>
            <div v-if="log.exam" class="flex justify-between gap-3"><dt class="text-text-muted">Exam for placeholders</dt><dd class="text-right">{{ log.exam }}</dd></div>
          </dl>
          <div class="flex flex-wrap gap-1.5 mt-3">
            <span v-for="chip in log.audience_chips" :key="chip" class="text-[10px] px-1.5 py-0.5 rounded bg-royal/10 text-royal font-semibold">{{ chip }}</span>
          </div>
        </section>

        <section class="card p-5">
          <p class="lbl">{{ log.channel === 'whatsapp' ? 'Template' : log.channel === 'email' ? 'Email' : 'Notification' }}</p>
          <template v-if="log.channel === 'whatsapp' && log.whatsapp_template">
            <p class="font-semibold text-sm text-text-main">{{ log.whatsapp_template.name }}</p>
            <p class="text-[11px] font-mono text-text-muted mb-3">{{ log.whatsapp_template.slug }} · {{ log.whatsapp_template.language }}</p>
            <div class="rounded-xl wa-bg p-3">
              <div class="bg-white rounded-lg p-2.5 text-[12px] whitespace-pre-line break-words">
                <p v-if="log.whatsapp_template.header" class="font-bold mb-1">{{ log.whatsapp_template.header }}</p>{{ log.whatsapp_template.body }}
              </div>
            </div>
            <ul class="mt-3 space-y-1 text-[11px]">
              <li v-for="v in log.whatsapp_template.variables" :key="v.key" class="flex justify-between gap-2">
                <code class="text-primary">{{ '{' }}{{ '{' }}{{ v.key }}}}</code>
                <span class="text-text-muted text-right truncate">{{ v.source === 'placeholder' ? `Student field: ${v.value}` : `“${v.value}”` }}{{ v.fallback ? ` · fallback “${v.fallback}”` : '' }}</span>
              </li>
            </ul>
          </template>
          <template v-else>
            <p class="font-semibold text-sm text-text-main break-words">{{ log.subject || log.title }}</p>
            <div v-if="log.body_html" class="prose-sm text-xs text-text-muted mt-2 max-h-72 overflow-y-auto email-body" v-html="log.body_html"></div>
            <p v-else class="text-xs text-text-muted mt-2 whitespace-pre-line break-words">{{ log.message }}</p>
            <p v-if="log.link" class="text-[11px] text-text-muted mt-2">Opens <code>{{ log.link }}</code></p>
          </template>
          <p class="text-[10px] text-text-muted mt-3">Placeholders are filled per student at send time.</p>
        </section>
      </div>

      <!-- deliveries -->
      <section class="xl:col-span-8 card overflow-hidden min-w-0">
        <header class="px-4 sm:px-5 py-4 border-b border-gray-100 space-y-3">
          <h2 class="font-heading font-bold text-text-main text-sm">Recipients &amp; delivery status</h2>
          <div class="flex flex-wrap gap-1" role="tablist" aria-label="Delivery status">
            <button v-for="s in statusTabs" :key="s.key" type="button" role="tab" :aria-selected="(filters.status || '') === s.key"
                    class="px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                    :class="(filters.status || '') === s.key ? 'bg-primary text-white' : 'text-text-main hover:bg-gray-100'" @click="filter({ status: s.key })">
              {{ s.label }}
            </button>
          </div>
          <input v-model="q" type="search" class="field" placeholder="Search by student name, email or number" @keydown.enter="filter({ q })" @search="filter({ q })" />
        </header>

        <div v-if="!deliveries.data.length" class="py-14 text-center text-sm text-text-muted px-4">No recipients in this view.</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm min-w-[720px]">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="th">Student</th>
                <th class="th">{{ log.channel === 'whatsapp' ? 'Number' : log.channel === 'email' ? 'Email' : 'Class' }}</th>
                <th class="th">Status</th>
                <th class="th">When</th>
                <th class="th"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="d in deliveries.data" :key="d.id" class="align-top">
                <td class="px-4 py-3">
                  <Link v-if="d.user_id" :href="route('admin.users.show', d.user_id)" class="font-semibold text-text-main hover:text-primary">{{ d.name }}</Link>
                  <span v-else class="font-semibold text-text-muted">{{ d.name || 'Deleted student' }}</span>
                  <p v-if="d.channel !== log.channel || log.channel === 'both'" class="text-[10px] text-text-muted">{{ CHANNELS[d.channel]?.label }}</p>
                  <p v-if="d.variables" class="text-[10px] text-text-muted mt-0.5 truncate max-w-[260px]" :title="Object.values(d.variables).join(' | ')">{{ Object.values(d.variables).join(' · ') }}</p>
                </td>
                <td class="px-4 py-3 text-xs text-text-muted break-all">{{ d.channel === 'in_app' ? (d.class || '—') : (d.destination || '—') }}</td>
                <td class="px-4 py-3">
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="DELIVERY_STATUS[d.status]?.cls">{{ DELIVERY_STATUS[d.status]?.label ?? d.status }}</span>
                  <p v-if="d.error" class="text-[11px] mt-1 max-w-[280px] break-words" :class="d.status === 'failed' ? 'text-danger' : 'text-text-muted'">
                    {{ d.error }}<span v-if="d.permanent && d.status === 'failed'" class="text-text-muted"> (retry won’t help)</span>
                  </p>
                </td>
                <td class="px-4 py-3 text-[11px] text-text-muted font-number whitespace-nowrap">
                  <p v-if="d.read_at">Read {{ formatLocal(d.read_at, SHORT) }}</p>
                  <p v-else-if="d.delivered_at">Delivered {{ formatLocal(d.delivered_at, SHORT) }}</p>
                  <p v-else-if="d.sent_at">Sent {{ formatLocal(d.sent_at, SHORT) }}</p>
                  <p v-else-if="d.failed_at">Failed {{ formatLocal(d.failed_at, SHORT) }}</p>
                  <p v-else>—</p>
                  <p v-if="d.attempts > 1">{{ d.attempts }} attempts</p>
                </td>
                <td class="px-4 py-3 text-right">
                  <button v-if="canRetry && canWrite && d.status === 'failed' && !d.permanent" type="button" class="act" :disabled="busy" @click="retryOne(d)">Retry</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="deliveries.last_page > 1" class="px-4 sm:px-5 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2">
          <p class="text-text-muted text-xs">Showing {{ deliveries.from }}–{{ deliveries.to }} of {{ deliveries.total }}</p>
          <div class="flex flex-wrap gap-1">
            <Link v-for="link in deliveries.links" :key="link.label" :href="link.url || '#'" preserve-scroll preserve-state
                  :class="['px-3 py-1.5 rounded-lg text-xs font-semibold', link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100', !link.url ? 'opacity-40 pointer-events-none' : '']"
                  v-html="link.label" />
          </div>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirmDialog } from '@/composables/useConfirm';
import { formatLocal } from '@/Services/localTime.service';
import { CHANNELS, DELIVERY_STATUS, LOG_STATUS } from './Components/notifications';

const props = defineProps({
  log: { type: Object, required: true },
  deliveries: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  retryable: { type: Number, default: 0 },
  canRetry: { type: Boolean, default: false },
});

const DATE_FMT = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
const SHORT = { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' };

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const canWrite = computed(() => page.props.admin_is_super || page.props.admin_permissions?.notifications?.write);
const canDelete = computed(() => page.props.admin_is_super || page.props.admin_permissions?.notifications?.delete);
const inProgress = computed(() => ['queued', 'sending'].includes(props.log.status));
const busy = ref(false);
const q = ref(props.filters.q ?? '');

const tiles = computed(() => {
  const l = props.log;
  const list = [
    { label: 'Recipients', value: l.recipient_count, cls: 'text-primary' },
    { label: 'Sent', value: l.sent_count, cls: 'text-success' },
  ];
  if (l.channel === 'whatsapp') list.push({ label: 'Delivered', value: l.delivered_count, cls: 'text-success' });
  if (l.channel !== 'email') list.push({ label: 'Read', value: l.read_count, cls: 'text-success' });
  list.push({ label: 'Pending', value: l.pending_count, cls: l.pending_count ? 'text-accent-dark' : 'text-text-muted' });
  list.push({ label: 'Failed', value: l.failed_count, cls: l.failed_count ? 'text-danger' : 'text-text-muted' });
  if (l.skipped_count) list.push({ label: 'Skipped', value: l.skipped_count, cls: 'text-text-muted' });
  return list;
});

const statusTabs = computed(() => {
  const base = [{ key: '', label: 'All' }, { key: 'failed', label: 'Failed' }, { key: 'pending', label: 'Pending' }, { key: 'sent', label: 'Sent' }];
  if (props.log.channel === 'whatsapp') base.push({ key: 'delivered', label: 'Delivered' }, { key: 'read', label: 'Read' });
  base.push({ key: 'skipped', label: 'Skipped' });
  return base;
});

const pct = (n) => `${props.log.recipient_count ? Math.min(100, (n / props.log.recipient_count) * 100) : 0}%`;
const reloadOpts = { preserveScroll: true, preserveState: true, onStart: () => { busy.value = true; }, onFinish: () => { busy.value = false; } };
const filter = (patch) => router.get(route('admin.notifications.show', props.log.id), { ...props.filters, q: q.value, ...patch }, { preserveScroll: true, preserveState: true });

const retryAll = async () => {
  if (!(await confirmDialog({ title: `Retry ${props.retryable} failed deliveries?`, message: 'Only failures a retry can fix are re-sent. Students who already received the message are not contacted again.', confirmText: 'Retry now' }))) return;
  router.post(route('admin.notifications.retry', props.log.id), {}, reloadOpts);
};
const retryOne = (d) => router.post(route('admin.notifications.deliveries.retry', [props.log.id, d.id]), {}, reloadOpts);
const refreshStatus = () => router.post(route('admin.notifications.refresh-status', props.log.id), {}, reloadOpts);
const destroy = async () => {
  if (!(await confirmDialog({
    title: 'Delete this broadcast?',
    message: props.log.channel === 'in_app' || props.log.channel === 'both'
      ? 'Its in-app notifications are removed from every student portal. Delivery history is deleted.'
      : 'The delivery history is deleted. Messages already delivered stay with the students.',
    confirmText: 'Delete', tone: 'danger',
  }))) return;
  router.delete(route('admin.notifications.destroy', props.log.id), reloadOpts);
};

let timer = null;
onMounted(() => {
  timer = setInterval(() => {
    if (inProgress.value && !document.hidden && !busy.value) {
      router.reload({ only: ['log', 'deliveries', 'retryable'], preserveScroll: true, preserveState: true });
    }
  }, 5000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm; }
.lbl { @apply block text-[11px] font-semibold text-text-muted uppercase tracking-wider mb-2; }
.th { @apply text-left px-4 py-3 text-text-muted text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap; }
.field { @apply w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main; }
.act { @apply text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-gray-200 hover:border-primary hover:text-primary transition-colors disabled:opacity-40; }
.btn-ghost { @apply px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-text-main bg-white hover:border-primary hover:text-primary transition-colors disabled:opacity-40; }
.btn-primary { @apply px-3 py-2 rounded-xl text-xs font-semibold text-white bg-primary hover:bg-primary-light transition-colors disabled:opacity-40; }
.btn-danger { @apply px-3 py-2 rounded-xl text-xs font-semibold text-danger border border-danger/30 bg-white hover:bg-danger/5 transition-colors disabled:opacity-40; }
.wa-bg { background-color: #efeae2; }
.email-body :deep(p) { margin: 0 0 .5rem; }
</style>
