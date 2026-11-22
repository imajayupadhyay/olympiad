<template>
  <section class="card" aria-labelledby="tpl-h">
    <header class="card-h">
      <div class="min-w-0">
        <h2 id="tpl-h" class="font-heading font-bold text-text-main text-sm">{{ provider.label }} WhatsApp templates</h2>
        <p class="text-[11px] text-text-muted">
          <template v-if="live">Read live from {{ provider.label }}. Create and approve templates in {{ provider.label }}’s console; they appear here after a refresh.</template>
          <template v-else>Add each template exactly as it was approved in {{ provider.label }} (name, language and body). Only {{ provider.label }} broadcasts use them.</template>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button v-if="live" type="button" class="btn-ghost" :disabled="loading || !provider.configured" @click="loadLive(true)">{{ loading ? 'Loading…' : `↻ Refresh from ${provider.label}` }}</button>
        <template v-else>
          <button v-if="canWrite" type="button" class="btn-ghost" :disabled="!provider.can_sync_templates || syncing"
                  :title="provider.can_sync_templates ? '' : provider.unavailable_reason" @click="sync">
            {{ syncing ? 'Syncing…' : `↻ Sync from ${provider.label}` }}
          </button>
          <button v-if="canWrite" type="button" class="btn-primary" @click="openForm()">+ Add template</button>
        </template>
      </div>
    </header>

    <div class="p-4 sm:p-5 space-y-3">
      <p v-if="error" class="rounded-xl bg-danger/5 border border-danger/20 text-danger text-xs p-3" role="alert">{{ error }}</p>
      <input v-if="list.length > 4" v-model="search" type="search" class="field" placeholder="Search templates" aria-label="Search templates" />

      <p v-if="live && !provider.configured" class="text-xs text-text-muted py-6 text-center">Configure {{ provider.label }} to load its templates.</p>
      <p v-else-if="loading && !list.length" class="text-xs text-text-muted py-6 text-center">Loading templates…</p>
      <div v-else-if="!filtered.length" class="py-8 text-center">
        <p class="text-sm font-semibold text-text-main">{{ search ? 'No templates match.' : 'No templates yet' }}</p>
        <p v-if="!live && !search" class="text-xs text-text-muted mt-1">Add the templates approved in your {{ provider.label }} account to use them in broadcasts.</p>
      </div>

      <ul v-else class="divide-y divide-gray-100 rounded-xl border border-gray-100">
        <li v-for="t in filtered" :key="t.id" class="p-3 sm:p-4">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="font-semibold text-sm text-text-main break-words">{{ t.name }}</p>
              <p class="text-[11px] text-text-muted font-mono truncate">{{ t.slug }}</p>
              <div class="flex flex-wrap gap-1 mt-1.5 text-[10px] font-semibold">
                <span class="px-1.5 py-0.5 rounded bg-gray-100 text-text-muted capitalize">{{ t.category }}</span>
                <span class="px-1.5 py-0.5 rounded bg-gray-100 text-text-muted uppercase">{{ t.language }}</span>
                <span v-if="!live" class="px-1.5 py-0.5 rounded bg-gray-100 text-text-muted capitalize">{{ t.approval_status }}</span>
                <span v-if="t.variables?.length" class="px-1.5 py-0.5 rounded bg-gray-100 text-text-muted">{{ t.variables.length }} variable(s)</span>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded" :class="t.usable ? 'bg-success/10 text-success' : 'bg-gray-200 text-text-muted'">{{ t.usable ? 'Usable' : 'Not usable' }}</span>
              <template v-if="!live">
                <button v-if="canWrite" type="button" class="btn-ghost" @click="openForm(t)">Edit</button>
                <button v-if="canDelete" type="button" class="btn-ghost hover:!border-danger hover:!text-danger" @click="remove(t)">Delete</button>
              </template>
            </div>
          </div>
          <p v-if="!t.usable && t.unusable_reason" class="text-[11px] text-text-muted mt-1.5">{{ t.unusable_reason }}</p>
          <p v-if="t.body" class="mt-2 text-xs text-text-main whitespace-pre-line break-words rounded-lg bg-gray-50 p-2.5">{{ t.body }}</p>
        </li>
      </ul>
      <p v-if="live && fetchedAt" class="text-[11px] text-text-muted">{{ list.length }} template(s) · fetched {{ formatLocal(fetchedAt, { hour: '2-digit', minute: '2-digit' }) }}</p>
    </div>

    <TemplateFormModal v-if="!live" :show="formOpen" :provider="provider" :template="editing" :options="options" @close="formOpen = false" />
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { confirmDialog } from '@/composables/useConfirm';
import { formatLocal } from '@/Services/localTime.service';
import { errorText } from '@/Pages/Admin/Notifications/Components/notifications';
import TemplateFormModal from './TemplateFormModal.vue';

const props = defineProps({
  provider: { type: Object, required: true },
  canWrite: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  options: { type: Object, required: true },
});

const live = computed(() => props.provider.template_source === 'live');
const liveTemplates = ref([]);
const loading = ref(false);
const syncing = ref(false);
const error = ref('');
const fetchedAt = ref(null);
const search = ref('');
const formOpen = ref(false);
const editing = ref(null);

const list = computed(() => (live.value ? liveTemplates.value : props.provider.templates ?? []));
const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  return list.value.filter((t) => !q || `${t.name} ${t.slug} ${t.category} ${t.language}`.toLowerCase().includes(q));
});

const loadLive = async (refresh = false) => {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await window.axios.get(route('admin.settings.messaging.providers.templates', props.provider.key), { params: refresh ? { refresh: 1 } : {} });
    liveTemplates.value = data.templates ?? [];
    fetchedAt.value = data.fetched_at ?? null;
    if (data.message) error.value = data.message;
  } catch (e) {
    error.value = errorText(e, `Could not load templates from ${props.provider.label}.`);
  } finally {
    loading.value = false;
  }
};

const openForm = (t = null) => {
  editing.value = t;
  formOpen.value = true;
};

const remove = async (t) => {
  const ok = await confirmDialog({
    title: `Delete “${t.slug}”?`,
    message: `It is removed from this list only; the template in ${props.provider.label} itself and broadcasts already sent with it are not affected.`,
    confirmText: 'Delete template',
    tone: 'danger',
  });
  if (ok) router.delete(route('admin.settings.messaging.templates.destroy', t.id), { preserveScroll: true });
};

const sync = async () => {
  const ok = await confirmDialog({
    title: `Sync templates from ${props.provider.label}?`,
    message: 'Approved templates are added or updated here. Templates you added by hand are kept.',
    confirmText: 'Sync now',
  });
  if (!ok) return;
  syncing.value = true;
  router.post(route('admin.settings.messaging.providers.templates.sync', props.provider.key), {}, { preserveScroll: true, onFinish: () => { syncing.value = false; } });
};

onMounted(() => { if (live.value && props.provider.configured) loadLive(); });
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm; }
.card-h { @apply flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main; }
.btn-ghost { @apply px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-text-main bg-white hover:border-primary hover:text-primary transition-colors disabled:opacity-40 disabled:pointer-events-none; }
.btn-primary { @apply px-3.5 py-1.5 rounded-lg bg-primary text-white text-xs font-bold hover:bg-primary-light transition-colors disabled:opacity-50; }
</style>
