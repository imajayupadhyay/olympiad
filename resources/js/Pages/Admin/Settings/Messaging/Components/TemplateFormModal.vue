<template>
  <Modal :show="show" max-width="2xl" @close="close">
    <form class="p-5 sm:p-6 space-y-4" @submit.prevent="submit">
      <div>
        <h2 class="font-heading font-bold text-lg text-text-main">{{ template ? 'Edit template' : `Add a ${provider.label} template` }}</h2>
        <p class="text-xs text-text-muted mt-1">Copy these from the template approved in {{ provider.label }}. The name and language are sent to {{ provider.label }} as-is; the body is used for previews and to find the variables.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <label class="block">
          <span class="lbl">Template name *</span>
          <input v-model="form.name" type="text" maxlength="100" class="field font-mono" placeholder="exam_reminder" autocomplete="off" />
          <span class="hint">Lowercase letters, numbers and _ only, exactly as in {{ provider.label }}.</span>
          <span v-if="form.errors.name" class="err">{{ form.errors.name }}</span>
        </label>
        <label class="block">
          <span class="lbl">Display name</span>
          <input v-model="form.display_name" type="text" maxlength="150" class="field" placeholder="Exam reminder (optional)" />
        </label>
        <label class="block">
          <span class="lbl">Language code *</span>
          <input v-model="form.language" type="text" maxlength="12" class="field font-mono" placeholder="en" />
          <span class="hint">e.g. en, en_US, hi</span>
          <span v-if="form.errors.language" class="err">{{ form.errors.language }}</span>
        </label>
        <label class="block">
          <span class="lbl">Category *</span>
          <select v-model="form.category" class="field">
            <option v-for="(label, key) in options.categories" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
        <label class="block">
          <span class="lbl">Approval status *</span>
          <select v-model="form.approval_status" class="field">
            <option v-for="(label, key) in options.approvalStatuses" :key="key" :value="key">{{ label }}</option>
          </select>
          <span class="hint">Only approved templates can be sent.</span>
        </label>
        <label class="block">
          <span class="lbl">{{ provider.label }} template ID</span>
          <input v-model="form.external_id" type="text" maxlength="100" class="field font-mono" placeholder="Optional" />
          <span v-if="form.errors.external_id" class="err">{{ form.errors.external_id }}</span>
        </label>
      </div>

      <label class="block">
        <span class="lbl">Header text</span>
        <input v-model="form.header" type="text" maxlength="60" class="field" placeholder="Optional, up to 60 characters" />
        <span v-if="form.errors.header" class="err">{{ form.errors.header }}</span>
      </label>
      <label class="block">
        <span class="lbl">Body *</span>
        <textarea v-model="form.body" rows="5" maxlength="1024" class="field resize-y" placeholder="Hi {{1}}, your {{2}} olympiad opens on {{3}}."></textarea>
        <span class="hint">Write variables as in {{ provider.label }}: <code v-pre>{{1}}</code>, <code v-pre>{{2}}</code> … {{ (form.body || '').length }}/1024</span>
        <span v-if="form.errors.body" class="err">{{ form.errors.body }}</span>
      </label>
      <label class="block">
        <span class="lbl">Footer text</span>
        <input v-model="form.footer" type="text" maxlength="60" class="field" placeholder="Optional" />
      </label>

      <div v-if="variables.length" class="rounded-xl border border-gray-100 p-3 space-y-2">
        <p class="lbl mb-0">Example values (shown in the broadcast composer)</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <label v-for="key in variables" :key="key" class="flex items-center gap-2">
            <code class="text-xs font-semibold text-primary shrink-0 w-14">{{ '{' }}{{ '{' }}{{ key }}}}</code>
            <input v-model="form.variable_examples[key]" type="text" maxlength="200" class="field" placeholder="Example" />
          </label>
        </div>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="lbl mb-0">Buttons</p>
          <button type="button" class="btn-ghost" :disabled="form.buttons.length >= 10" @click="form.buttons.push({ type: 'quick_reply', text: '', url: '' })">+ Button</button>
        </div>
        <div v-for="(b, i) in form.buttons" :key="i" class="grid grid-cols-12 gap-2 items-start">
          <select v-model="b.type" class="field col-span-4 sm:col-span-3" aria-label="Button type">
            <option value="quick_reply">Quick reply</option>
            <option value="url">Link</option>
            <option value="phone_number">Call</option>
          </select>
          <input v-model="b.text" type="text" maxlength="25" class="field col-span-8 sm:col-span-4" placeholder="Button text" aria-label="Button text" />
          <input v-if="b.type === 'url'" v-model="b.url" type="text" maxlength="2000" class="field col-span-10 sm:col-span-4" placeholder="https://…" aria-label="Button link" />
          <button type="button" class="btn-ghost col-span-2 sm:col-span-1 sm:col-start-12 h-[42px]" aria-label="Remove button" @click="form.buttons.splice(i, 1)">✕</button>
          <span v-if="form.errors[`buttons.${i}.text`]" class="err col-span-12">{{ form.errors[`buttons.${i}.text`] }}</span>
        </div>
      </div>

      <label class="inline-flex items-center gap-2 text-sm text-text-main">
        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-success focus:ring-success/30" />
        Available in the broadcast composer
      </label>

      <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-gray-100">
        <button type="button" class="btn-ghost px-4 py-2.5" @click="close">Cancel</button>
        <button type="submit" class="btn-primary" :disabled="form.processing">{{ form.processing ? 'Saving…' : (template ? 'Save changes' : 'Add template') }}</button>
      </div>
    </form>
  </Modal>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({
  show: { type: Boolean, default: false },
  provider: { type: Object, required: true },
  template: { type: Object, default: null },
  options: { type: Object, required: true },
});
const emit = defineEmits(['close']);

const blank = () => ({
  name: '', display_name: '', language: 'en', category: 'utility', external_id: '', header: '', body: '', footer: '',
  buttons: [], variable_examples: {}, approval_status: 'approved', is_active: true,
});

const fromTemplate = (t) => ({
  name: t.slug, display_name: t.display_name ?? '', language: t.language, category: t.category,
  external_id: t.external_id ?? '', header: t.header ?? '', body: t.body ?? '', footer: t.footer ?? '',
  buttons: (t.buttons ?? []).map((b) => ({ type: b.type === 'phone' ? 'phone_number' : b.type, text: b.text, url: b.url ?? '' })),
  variable_examples: { ...(t.variable_examples ?? {}) }, approval_status: t.approval_status, is_active: !!t.is_active,
});

const form = useForm(blank());

watch(() => props.show, (open) => {
  if (!open) return;
  const data = props.template ? fromTemplate(props.template) : blank();
  form.defaults(data);
  form.reset();
  form.clearErrors();
});

const variables = computed(() => [...new Set([...(form.body || '').matchAll(/{{\s*([a-zA-Z0-9_]+)\s*}}/g)].map((m) => m[1]))]);

const close = async () => {
  if (form.isDirty && !(await confirmDialog({ title: 'Discard changes?', message: 'This template has unsaved changes.', confirmText: 'Discard', tone: 'warning' }))) return;
  emit('close');
};

const submit = () => {
  const options = { preserveScroll: true, onSuccess: () => { form.defaults(); emit('close'); } };
  form.transform((d) => ({ ...d, buttons: d.buttons.filter((b) => b.text.trim() !== '') }));
  props.template
    ? form.put(route('admin.settings.messaging.templates.update', props.template.id), options)
    : form.post(route('admin.settings.messaging.templates.store', props.provider.key), options);
};
</script>

<style scoped>
.lbl { @apply block text-[11px] font-semibold text-text-muted uppercase tracking-wider mb-1.5; }
.hint { @apply block text-[11px] text-text-muted mt-1; }
.err { @apply block text-xs text-danger mt-1; }
.field { @apply w-full min-w-0 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main; }
.btn-ghost { @apply px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-text-main bg-white hover:border-primary hover:text-primary transition-colors disabled:opacity-40 disabled:pointer-events-none; }
.btn-primary { @apply px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-light transition-colors disabled:opacity-50; }
</style>
