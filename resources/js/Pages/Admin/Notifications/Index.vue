<template>
  <AdminLayout title="Notifications" subtitle="Email, in-app and WhatsApp messages to students, with delivery tracking">
    <div v-if="flash.success" class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl" role="status">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl" role="alert">{{ flash.error }}</div>
    <div v-if="flash.info" class="mb-4 bg-blue-50 border border-blue-200 text-royal text-sm px-4 py-3 rounded-xl" role="status">{{ flash.info }}</div>

    <!-- stats -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
      <div v-for="tile in tiles" :key="tile.label" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-[11px] font-semibold uppercase tracking-wider mb-1">{{ tile.label }}</p>
        <p class="font-number text-2xl font-bold" :class="tile.cls">{{ Number(tile.value || 0).toLocaleString('en-IN') }}</p>
      </div>
    </div>

    <!-- tabs -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-1.5 mb-5 flex flex-wrap gap-1" role="tablist" aria-label="Notification channel">
      <button v-for="t in tabs" :key="t.key" type="button" role="tab" :aria-selected="active === t.key" :id="`tab-${t.key}`"
              class="flex-1 min-w-[120px] flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors"
              :class="active === t.key ? 'bg-primary text-white shadow-sm' : 'text-text-main hover:bg-gray-100'" @click="go(t.key)">
        <span aria-hidden="true" v-html="t.icon"></span>
        {{ t.label }}
        <span v-if="t.note" class="text-[10px] font-number px-1.5 py-0.5 rounded-md" :class="active === t.key ? 'bg-white/20' : 'bg-gray-100 text-text-muted'">{{ t.note }}</span>
      </button>
    </div>

    <p v-if="!canWrite && active !== 'history'" class="mb-4 text-xs text-text-muted">View only. You can build an audience and preview, but sending needs “Notifications” write access.</p>
    <p v-if="options.queue === 'sync' && active !== 'history' && active !== 'in_app'" class="mb-4 text-[11px] text-gold-dark bg-gold/10 border border-gold/30 rounded-xl px-3 py-2">
      This server runs the queue in <b>sync</b> mode, so {{ active === 'email' ? 'emails' : 'WhatsApp messages' }} go out while the Send request waits. Production must run a queue worker (workflow.md §14).
    </p>

    <template v-if="active === 'email'">
      <EmailComposer v-model:exam-id="examId" :draft="drafts.email" :audience="audience" :summary="summary" :options="options" @sent="afterSend('email')">
        <template #audience><AudienceBuilder v-model="audience" channel="email" :options="options" @summary="summary = $event" /></template>
      </EmailComposer>
    </template>
    <template v-else-if="active === 'in_app'">
      <InAppComposer v-model:exam-id="examId" :draft="drafts.in_app" :audience="audience" :summary="summary" :options="options" @sent="afterSend('in_app')">
        <template #audience><AudienceBuilder v-model="audience" channel="in_app" :options="options" @summary="summary = $event" /></template>
      </InAppComposer>
    </template>
    <template v-else-if="active === 'whatsapp'">
      <WhatsAppComposer v-model:exam-id="examId" :draft="drafts.whatsapp" :audience="audience" :summary="summary" :options="options" @sent="afterSend('whatsapp')">
        <template #audience><AudienceBuilder v-model="audience" channel="whatsapp" :options="options" @summary="summary = $event" /></template>
      </WhatsAppComposer>
    </template>
    <HistoryPanel v-else :logs="logs" :filters="historyFilters" />
  </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AudienceBuilder from './Components/AudienceBuilder.vue';
import EmailComposer from './Components/EmailComposer.vue';
import HistoryPanel from './Components/HistoryPanel.vue';
import InAppComposer from './Components/InAppComposer.vue';
import WhatsAppComposer from './Components/WhatsAppComposer.vue';
import { defaultAudience } from './Components/notifications';

const props = defineProps({
  tab: { type: String, default: 'email' },
  historyFilters: { type: Object, default: () => ({}) },
  logs: { type: Object, required: true },
  stats: { type: Object, required: true },
  options: { type: Object, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const canWrite = computed(() => page.props.admin_is_super || page.props.admin_permissions?.notifications?.write);

const active = ref(props.tab);
watch(() => props.tab, (t) => { active.value = t; });
const go = (key) => {
  active.value = key;
  const url = new URL(window.location.href);
  url.searchParams.set('tab', key);
  if (key !== 'history') ['channel', 'status', 'q', 'page'].forEach((k) => url.searchParams.delete(k));
  window.history.replaceState(window.history.state, '', url);
};

// Shared across channel tabs so switching channel keeps the audience and drafts.
const audience = ref(defaultAudience());
const summary = ref({ recipients: null });
const examId = ref(null);
const blankDrafts = () => ({
  email: { subject: '', body_html: '<p>Dear {{first_name}},</p><p></p><p>Warm regards,<br>Team {{app_name}}</p>' },
  in_app: { title: '', message: '', link: '' },
  whatsapp: { template_id: null, variables: {} },
});
const drafts = reactive(blankDrafts());

// A single exam in the filters is the natural placeholder exam.
watch(() => audience.value.exam_ids, (ids) => {
  if (ids?.length === 1 && !examId.value) examId.value = ids[0];
}, { deep: true });

const afterSend = (channel) => {
  Object.assign(drafts[channel], blankDrafts()[channel]);
};

const icons = {
  email: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
  in_app: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3C7.7 6.2 6 8.4 6 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>',
  whatsapp: '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2a9.9 9.9 0 00-8.5 14.98L2 22l5.17-1.5A9.93 9.93 0 1012.04 2zm0 18.13a8.2 8.2 0 01-4.2-1.15l-.3-.18-3.07.89.9-2.98-.2-.31a8.22 8.22 0 1113.07-6.49 8.24 8.24 0 01-6.2 10.22zm4.5-6.16c-.25-.12-1.46-.72-1.69-.8s-.39-.12-.55.13-.63.8-.78.96-.29.19-.53.06a6.75 6.75 0 01-3.36-2.94c-.25-.44.25-.41.72-1.36a.45.45 0 00-.02-.43c-.06-.12-.55-1.33-.76-1.82s-.4-.41-.55-.42h-.47a.9.9 0 00-.65.3 2.74 2.74 0 00-.86 2.04 4.76 4.76 0 001 2.53 10.9 10.9 0 004.17 3.68c1.55.67 2.16.73 2.93.61a2.5 2.5 0 001.64-1.16 2 2 0 00.14-1.16c-.06-.1-.22-.16-.47-.28z"/></svg>',
  history: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
};
const tabs = computed(() => [
  { key: 'email', label: 'Email', icon: icons.email, note: props.stats.by_channel?.email || null },
  { key: 'in_app', label: 'In-App', icon: icons.in_app, note: props.stats.by_channel?.in_app || null },
  { key: 'whatsapp', label: 'WhatsApp', icon: icons.whatsapp, note: props.stats.by_channel?.whatsapp || null },
  { key: 'history', label: 'History', icon: icons.history, note: props.stats.in_progress ? `${props.stats.in_progress} sending` : null },
]);

const tiles = computed(() => [
  { label: 'Broadcasts', value: props.stats.broadcasts, cls: 'text-primary' },
  { label: 'This month', value: props.stats.this_month, cls: 'text-accent' },
  { label: 'Messages sent', value: props.stats.reach, cls: 'text-success' },
  { label: 'Sending now', value: props.stats.in_progress, cls: 'text-gold-dark' },
  { label: 'Failed (30 days)', value: props.stats.failed_30d, cls: props.stats.failed_30d ? 'text-danger' : 'text-text-muted' },
]);
</script>
