<template>
  <AdminLayout title="Messaging" subtitle="Choose and configure the WhatsApp provider used for student broadcasts">
    <div v-if="flashError" class="mb-5 flex items-start gap-3 rounded-xl border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger" role="alert">
      <span class="min-w-0 flex-1">{{ flashError }}</span>
      <button type="button" class="text-xs font-semibold underline" @click="flashError = ''">Dismiss</button>
    </div>

    <!-- ① Active provider -->
    <section class="card mb-6" aria-labelledby="switch-h">
      <header class="card-h">
        <div class="min-w-0">
          <h2 id="switch-h" class="font-heading font-bold text-text-main text-sm">WhatsApp provider</h2>
          <p class="text-[11px] text-text-muted">
            New WhatsApp broadcasts from Notifications go through the active provider.
            Broadcasts already sent or queued keep the provider they started with.
          </p>
        </div>
        <p v-if="active.switched_at" class="text-[11px] text-text-muted">
          Last switched {{ formatLocal(active.switched_at) }}<span v-if="active.switched_by"> by {{ active.switched_by }}</span>
        </p>
      </header>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 sm:p-5" role="radiogroup" aria-label="Active WhatsApp provider">
        <ProviderCard v-for="p in providers" :key="p.key" :provider="p" :active="p.key === active.key"
                      :selected="p.key === tab" :can-switch="can.write" :switching="switching === p.key"
                      @select="tab = p.key" @activate="activate(p)" />
      </div>
    </section>

    <!-- ② Selected provider -->
    <div class="flex flex-wrap items-center gap-2 mb-4" role="tablist" aria-label="Provider details">
      <button v-for="p in providers" :key="p.key" type="button" role="tab" :aria-selected="tab === p.key"
              class="px-4 py-2 rounded-xl text-sm font-semibold border transition-colors"
              :class="tab === p.key ? 'bg-primary text-white border-primary' : 'bg-white text-text-main border-gray-200 hover:border-primary'"
              @click="tab = p.key">
        {{ p.label }}
        <span v-if="p.key === active.key" class="ml-1.5 text-[10px] uppercase tracking-wider" :class="tab === p.key ? 'text-gold-light' : 'text-success'">Active</span>
      </button>
    </div>

    <div v-if="current" :key="current.key" class="grid grid-cols-1 xl:grid-cols-12 gap-6">
      <div class="xl:col-span-7 space-y-6 min-w-0">
        <ConfigForm :provider="current" :can-edit="can.credentials" />
        <Templates :provider="current" :can-write="can.write" :can-delete="can.delete" :options="templateOptions" />
      </div>
      <div class="xl:col-span-5 space-y-6 min-w-0">
        <ConnectionPanel :provider="current" />
        <TestMessage :provider="current" :can-write="can.write" />
        <UsagePanel :provider="current" :can-view-history="can.notifications" />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirmDialog, noticeDialog } from '@/composables/useConfirm';
import { formatLocal } from '@/Services/localTime.service';
import ConfigForm from './Components/ConfigForm.vue';
import ConnectionPanel from './Components/ConnectionPanel.vue';
import ProviderCard from './Components/ProviderCard.vue';
import Templates from './Components/Templates.vue';
import TestMessage from './Components/TestMessage.vue';
import UsagePanel from './Components/UsagePanel.vue';

const props = defineProps({
  active: { type: Object, required: true },
  providers: { type: Array, required: true },
  templateOptions: { type: Object, required: true },
  can: { type: Object, required: true },
});

const page = usePage();
const tab = ref(props.active.key);
const switching = ref(null);
const flashError = ref('');
const current = computed(() => props.providers.find((p) => p.key === tab.value) ?? props.providers[0]);

// Every save on this page redirects back with a flash: show it as a modal / banner.
watch(() => page.props.flash, (flash) => {
  if (flash?.success) noticeDialog({ title: 'Saved', message: flash.success });
  else if (flash?.info) noticeDialog({ title: 'No change', message: flash.info, tone: 'primary', confirmText: 'OK' });
  flashError.value = flash?.error || '';
}, { immediate: true });

const activate = async (p) => {
  const lines = [
    `New WhatsApp broadcasts will be sent through ${p.label} with ${p.label}'s credentials and templates.`,
    'Broadcasts already sent or queued, their retries and their delivery tracking stay on the provider they started with. No templates or settings are changed or deleted.',
  ];
  if (!p.can_send) lines.push(`Warning: ${p.unavailable_reason}`);

  const ok = await confirmDialog({
    title: `Switch WhatsApp to ${p.label}?`,
    message: lines.join(' '),
    confirmText: `Use ${p.label}`,
    tone: p.can_send ? 'primary' : 'warning',
  });
  if (!ok) return;

  switching.value = p.key;
  router.put(route('admin.settings.messaging.switch'), { provider: p.key }, {
    preserveScroll: true,
    onFinish: () => { switching.value = null; },
  });
};
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm; }
.card-h { @apply flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100; }
</style>
