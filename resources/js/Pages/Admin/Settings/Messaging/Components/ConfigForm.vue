<template>
  <section class="card" aria-labelledby="cfg-h">
    <header class="card-h">
      <div class="min-w-0">
        <h2 id="cfg-h" class="font-heading font-bold text-text-main text-sm">{{ provider.label }} credentials &amp; settings</h2>
        <p class="text-[11px] text-text-muted">
          Values saved here are encrypted and override the server <code>.env</code>. Leave a field empty to use the <code>.env</code> value.
        </p>
      </div>
      <span v-if="!canEdit" class="text-[11px] font-semibold text-text-muted">View only · super admin edits</span>
    </header>

    <p v-if="provider.config.decrypt_error" class="mx-4 sm:mx-5 mt-4 rounded-xl border border-danger/20 bg-danger/5 p-3 text-xs text-danger" role="alert">
      The saved key can’t be decrypted (the server’s APP_KEY changed). Enter the key again.
    </p>

    <form class="p-4 sm:p-5 space-y-4" @submit.prevent="save">
      <div v-for="f in provider.config.fields" :key="f.key" class="space-y-1.5">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <label :for="`f-${provider.key}-${f.key}`" class="lbl mb-0">{{ f.label }}<span v-if="f.required" class="text-danger"> *</span></label>
          <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded" :class="SOURCE[f.source].cls">{{ SOURCE[f.source].label }}</span>
        </div>

        <template v-if="f.secret">
          <div class="flex gap-2">
            <input :id="`f-${provider.key}-${f.key}`" v-model="form[f.key]" :type="reveal[f.key] ? 'text' : 'password'" autocomplete="new-password"
                   class="field font-mono" :disabled="!canEdit || form.clear[f.key]"
                   :placeholder="f.masked ? `Current: ${f.masked} — type to replace` : 'Not set'" />
            <button v-if="canEdit" type="button" class="btn-ghost shrink-0" :aria-label="reveal[f.key] ? 'Hide' : 'Show'" @click="reveal[f.key] = !reveal[f.key]">
              {{ reveal[f.key] ? 'Hide' : 'Show' }}
            </button>
          </div>
          <label v-if="canEdit && f.saved" class="inline-flex items-center gap-2 text-[11px] text-text-muted">
            <input v-model="form.clear[f.key]" type="checkbox" class="rounded border-gray-300 text-danger focus:ring-danger/30" />
            Remove the saved key{{ f.env ? ` (fall back to ${f.env})` : '' }}
          </label>
        </template>

        <template v-else>
          <input :id="`f-${provider.key}-${f.key}`" v-model="form[f.key]" :type="f.type === 'url' ? 'url' : 'text'" class="field"
                 :class="{ 'font-mono': f.type === 'url' }" :disabled="!canEdit"
                 :placeholder="f.source === 'env' && f.effective ? `From .env: ${f.effective}` : (f.type === 'url' ? 'https://…' : '')" />
        </template>

        <p v-if="form.errors[f.key]" class="text-xs text-danger">{{ form.errors[f.key] }}</p>
        <p class="text-[11px] text-text-muted">
          {{ f.help }}<span v-if="f.env"> · .env: <code>{{ f.env }}</code></span>
        </p>
      </div>

      <div v-if="canEdit" class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-gray-100">
        <p class="text-[11px] text-text-muted">
          <template v-if="provider.config.updated_at">Last saved {{ formatLocal(provider.config.updated_at) }}<span v-if="provider.config.updated_by"> by {{ provider.config.updated_by }}</span>.</template>
          Keys are never shown again after saving.
        </p>
        <div class="flex gap-2">
          <button type="button" class="btn-ghost" :disabled="!form.isDirty || form.processing" @click="discard">Discard</button>
          <button type="submit" class="btn-primary" :disabled="!form.isDirty || form.processing">{{ form.processing ? 'Saving…' : 'Save settings' }}</button>
        </div>
      </div>
    </form>
  </section>
</template>

<script setup>
import { reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { confirmDialog } from '@/composables/useConfirm';
import { formatLocal } from '@/Services/localTime.service';

const props = defineProps({
  provider: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
});

const SOURCE = {
  database: { label: 'Saved here', cls: 'bg-success/10 text-success' },
  env: { label: 'From .env', cls: 'bg-royal/10 text-royal' },
  none: { label: 'Missing', cls: 'bg-danger/10 text-danger' },
};

// Secrets start blank (never sent to the browser); other fields show their saved value.
const initial = () => {
  const data = { clear: {} };
  for (const f of props.provider.config.fields) {
    data[f.key] = f.secret ? '' : (f.value ?? '');
    if (f.secret) data.clear[f.key] = false;
  }
  return data;
};

const form = useForm(initial());
const reveal = reactive({});

const discard = () => {
  form.defaults(initial());
  form.reset();
  form.clearErrors();
};

const save = async () => {
  const clearing = props.provider.config.fields.filter((f) => f.secret && form.clear[f.key]);
  if (clearing.length) {
    const ok = await confirmDialog({
      title: 'Remove the saved key?',
      message: `${props.provider.label} will use ${clearing.map((f) => f.env).join(', ')} from the server .env instead. If .env has no value, ${props.provider.label} stops sending.`,
      confirmText: 'Remove and save',
      tone: 'danger',
    });
    if (!ok) return;
  }

  form.put(route('admin.settings.messaging.providers.update', props.provider.key), {
    preserveScroll: true,
    onSuccess: () => {
      form.defaults(initial());
      form.reset();
    },
  });
};
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm; }
.card-h { @apply flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100; }
.lbl { @apply block text-[11px] font-semibold text-text-muted uppercase tracking-wider; }
.field { @apply w-full min-w-0 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main disabled:bg-gray-50 disabled:text-text-muted; }
.btn-ghost { @apply px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-text-main bg-white hover:border-primary hover:text-primary transition-colors disabled:opacity-40 disabled:pointer-events-none; }
.btn-primary { @apply px-4 py-2 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-light transition-colors disabled:opacity-50; }
</style>
