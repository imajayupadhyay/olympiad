<template>
  <AdminLayout title="Emails" subtitle="Manage transactional email templates, toggles and Brevo delivery logs">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Templates</p>
        <p class="font-number text-2xl font-bold text-primary">{{ stats.templates }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Active</p>
        <p class="font-number text-2xl font-bold text-success">{{ stats.active }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Sent</p>
        <p class="font-number text-2xl font-bold text-accent">{{ stats.sent }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Failed</p>
        <p class="font-number text-2xl font-bold text-danger">{{ stats.failed }}</p>
      </div>
    </div>

    <div
      class="mb-6 rounded-2xl border p-4 flex flex-col md:flex-row md:items-center justify-between gap-3"
      :class="brevo.configured ? 'bg-success/5 border-success/20' : 'bg-danger/5 border-danger/20'"
    >
      <div>
        <p class="text-sm font-bold" :class="brevo.configured ? 'text-success' : 'text-danger'">
          Brevo {{ brevo.configured ? 'configured' : 'not configured' }}
        </p>
        <p class="text-xs text-text-muted mt-1">
          Sender: {{ brevo.sender_name }} &lt;{{ brevo.sender_email }}&gt;
        </p>
      </div>
      <p class="text-xs text-text-muted max-w-xl">
        Add `BREVO_API_KEY` in `.env` before production sends. Without it, queued emails are logged as failed with a clear reason.
      </p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
      <div class="xl:col-span-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-heading font-bold text-text-main text-sm">Templates</h2>
          </div>
          <div class="divide-y divide-gray-50">
            <button
              v-for="template in templates"
              :key="template.id"
              type="button"
              @click="selectTemplate(template)"
              class="w-full text-left px-5 py-4 transition-colors"
              :class="selected?.id === template.id ? 'bg-primary/5' : 'hover:bg-gray-50'"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-semibold text-sm text-text-main truncate">{{ template.name }}</p>
                  <p class="text-xs text-text-muted mt-1 line-clamp-2">{{ template.description }}</p>
                  <div class="flex items-center gap-2 mt-2">
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-gray-100 text-text-muted uppercase">
                      {{ template.category }}
                    </span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                          :class="template.is_active ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'">
                      {{ template.is_active ? 'On' : 'Off' }}
                    </span>
                    <span class="text-[10px] text-text-muted font-number">{{ template.logs_count }} logs</span>
                  </div>
                </div>
              </div>
            </button>
          </div>
        </div>
      </div>

      <div class="xl:col-span-8 space-y-6">
        <div v-if="selected" class="bg-white rounded-2xl border border-gray-100 shadow-sm">
          <div class="px-6 py-4 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <div>
              <h2 class="font-heading font-bold text-text-main text-base">{{ selected.name }}</h2>
              <p class="text-xs text-text-muted mt-1">{{ selected.key }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                @click="toggleTemplate"
                class="px-3 py-2 rounded-xl text-xs font-semibold border transition-colors"
                :class="form.is_active ? 'bg-success/10 text-success border-success/20' : 'bg-danger/10 text-danger border-danger/20'"
              >
                {{ form.is_active ? 'Enabled' : 'Disabled' }}
              </button>
              <button type="button" @click="loadPreview" class="px-3 py-2 rounded-xl text-xs font-semibold bg-gray-100 text-text-main hover:bg-gray-200">
                Preview
              </button>
              <button type="button" @click="showTest = true" class="px-3 py-2 rounded-xl text-xs font-semibold bg-accent text-white hover:bg-accent-dark">
                Send Test
              </button>
            </div>
          </div>

          <form @submit.prevent="saveTemplate" class="p-6 space-y-5">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Template Name</label>
                <input v-model="form.name" type="text" class="input" :class="form.errors.name ? 'border-danger' : 'border-gray-200'" />
                <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
              </div>
              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Subject</label>
                <input v-model="form.subject" type="text" class="input" :class="form.errors.subject ? 'border-danger' : 'border-gray-200'" />
                <p v-if="form.errors.subject" class="error">{{ form.errors.subject }}</p>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Description</label>
              <textarea v-model="form.description" rows="2" class="input resize-none" :class="form.errors.description ? 'border-danger' : 'border-gray-200'"></textarea>
              <p v-if="form.errors.description" class="error">{{ form.errors.description }}</p>
            </div>

            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider">HTML Body</label>
                <span class="text-[10px] text-text-muted">WYSIWYG editor</span>
              </div>
              <RichTextEditor v-model="form.html_body" min-height="260px" placeholder="Write the email body..." :error="Boolean(form.errors.html_body)" />
              <p v-if="form.errors.html_body" class="error">{{ form.errors.html_body }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Plain Text Body</label>
              <textarea v-model="form.text_body" rows="9" class="input font-mono text-xs leading-relaxed" :class="form.errors.text_body ? 'border-danger' : 'border-gray-200'"></textarea>
              <p v-if="form.errors.text_body" class="error">{{ form.errors.text_body }}</p>
            </div>

            <div>
              <p class="text-xs font-semibold text-text-muted uppercase tracking-wider mb-2">Available Variables</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="variable in selected.available_variables || []"
                  :key="variable"
                  type="button"
                  @click="copyVariable(variable)"
                  class="px-2.5 py-1 rounded-lg bg-primary/5 text-primary text-xs font-mono border border-primary/10 hover:bg-primary/10"
                >
                  {{ variableToken(variable) }}
                </button>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
              <button type="button" @click="resetForm" class="px-4 py-2.5 rounded-xl bg-gray-100 text-text-main text-sm font-semibold hover:bg-gray-200">
                Reset
              </button>
              <button type="submit" :disabled="form.processing" class="px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary-light disabled:opacity-60">
                {{ form.processing ? 'Saving...' : 'Save Template' }}
              </button>
            </div>
          </form>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-heading font-bold text-text-main text-sm">Recent Email Logs</h2>
            <span class="bg-gray-100 text-text-muted text-xs font-semibold px-2.5 py-1 rounded-lg font-number">{{ logs.total }}</span>
          </div>
          <div v-if="logs.data.length === 0" class="py-14 text-center text-text-muted text-sm">
            No email logs yet.
          </div>
          <div v-else class="divide-y divide-gray-50">
            <div v-for="log in logs.data" :key="log.id" class="px-5 py-4">
              <div class="flex flex-col md:flex-row md:items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <p class="font-semibold text-sm text-text-main truncate">{{ log.subject }}</p>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="statusClass(log.status)">
                      {{ log.status }}
                    </span>
                  </div>
                  <p class="text-xs text-text-muted mt-1">
                    {{ log.recipient_name || 'Recipient' }} &lt;{{ log.recipient_email }}&gt;
                  </p>
                  <p v-if="log.error_message" class="text-xs text-danger mt-1 line-clamp-2">{{ log.error_message }}</p>
                </div>
                <div class="text-right shrink-0">
                  <p class="text-[10px] text-text-muted font-number">{{ formatDate(log.created_at) }}</p>
                  <p class="text-[10px] text-text-muted mt-1">{{ log.template?.name || log.template_key }}</p>
                </div>
              </div>
            </div>
          </div>
          <div v-if="logs.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
            <p class="text-text-muted text-xs">Showing {{ logs.from }}-{{ logs.to }} of {{ logs.total }}</p>
            <div class="flex gap-1">
              <Link
                v-for="link in logs.links"
                :key="link.label"
                :href="link.url || '#'"
                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors', link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100', !link.url ? 'opacity-40 pointer-events-none' : '']"
                v-html="link.label"
                preserve-scroll
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="preview" class="modal-backdrop">
      <div class="modal-panel max-w-3xl">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-heading font-bold text-text-main">Email Preview</h3>
          <button type="button" @click="preview = null" class="text-text-muted hover:text-text-main">Close</button>
        </div>
        <p class="text-sm font-semibold text-text-main mb-3">{{ preview.subject }}</p>
        <iframe class="w-full h-[460px] border border-gray-200 rounded-xl bg-white" :srcdoc="preview.html_body"></iframe>
      </div>
    </div>

    <div v-if="showTest" class="modal-backdrop">
      <div class="modal-panel max-w-md">
        <h3 class="font-heading font-bold text-text-main mb-2">Send Test Email</h3>
        <p class="text-xs text-text-muted mb-5">Uses sample values for all variables.</p>
        <form @submit.prevent="sendTest" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Email</label>
            <input v-model="testForm.email" type="email" class="input" :class="testForm.errors.email ? 'border-danger' : 'border-gray-200'" />
            <p v-if="testForm.errors.email" class="error">{{ testForm.errors.email }}</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Name</label>
            <input v-model="testForm.name" type="text" class="input border-gray-200" />
          </div>
          <div class="flex gap-3 pt-2">
            <button type="button" @click="showTest = false" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200">Cancel</button>
            <button type="submit" :disabled="testForm.processing" class="flex-1 bg-accent text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-accent-dark disabled:opacity-60">
              {{ testForm.processing ? 'Queueing...' : 'Queue Test' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/Admin/RichTextEditor.vue';

const props = defineProps({
  templates: Array,
  logs: Object,
  stats: Object,
  brevo: Object,
});

const selected = ref(props.templates[0] || null);
const preview = ref(null);
const showTest = ref(false);

const form = useForm({
  name: '',
  description: '',
  subject: '',
  html_body: '',
  text_body: '',
  is_active: true,
});

const testForm = useForm({
  email: '',
  name: '',
});

const loadForm = (template) => {
  form.name = template?.name || '';
  form.description = template?.description || '';
  form.subject = template?.subject || '';
  form.html_body = template?.html_body || '';
  form.text_body = template?.text_body || '';
  form.is_active = Boolean(template?.is_active);
  form.clearErrors();
};

const selectTemplate = (template) => {
  selected.value = template;
  loadForm(template);
};

const resetForm = () => loadForm(selected.value);

const saveTemplate = () => {
  if (!selected.value) return;
  form.put(route('admin.emails.update', selected.value.id), {
    preserveScroll: true,
  });
};

const toggleTemplate = () => {
  form.is_active = !form.is_active;
  router.patch(route('admin.emails.toggle', selected.value.id), { is_active: form.is_active }, { preserveScroll: true });
};

const loadPreview = async () => {
  if (!selected.value) return;
  const { data } = await axios.get(route('admin.emails.preview', selected.value.id));
  preview.value = data;
};

const sendTest = () => {
  if (!selected.value) return;
  testForm.post(route('admin.emails.test', selected.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      showTest.value = false;
      testForm.reset();
    },
  });
};

const copyVariable = async (variable) => {
  await navigator.clipboard?.writeText(variableToken(variable));
};

const variableToken = (variable) => `{{${variable}}}`;

const statusClass = (status) => ({
  sent: 'bg-success/10 text-success',
  queued: 'bg-royal/10 text-royal',
  skipped: 'bg-gold/10 text-gold-dark',
  failed: 'bg-danger/10 text-danger',
}[status] || 'bg-gray-100 text-text-muted');

const formatDate = (date) => date ? new Date(date).toLocaleString('en-IN', {
  day: '2-digit',
  month: 'short',
  hour: '2-digit',
  minute: '2-digit',
}) : '-';

loadForm(selected.value);
</script>

<style scoped>
.input {
  width: 100%;
  border-width: 1px;
  border-radius: 0.75rem;
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.input:focus {
  border-color: #131C3D;
  box-shadow: 0 0 0 3px rgba(19,28,61,.08);
}
.error {
  color: #DC2626;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 50;
  background: rgba(0,0,0,.5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.modal-panel {
  width: 100%;
  background: #fff;
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 25px 60px rgba(10,16,36,.28);
}
</style>
