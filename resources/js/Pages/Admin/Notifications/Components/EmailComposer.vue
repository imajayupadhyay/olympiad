<template>
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
    <div class="xl:col-span-7 space-y-5 min-w-0">
      <slot name="audience" />

      <section class="card" aria-labelledby="email-h">
        <header class="card-h">
          <div class="flex items-center gap-3">
            <span class="step">2</span>
            <div>
              <h2 id="email-h" class="font-heading font-bold text-text-main text-sm">Write the email</h2>
              <p class="text-[11px] text-text-muted">Sent through Brevo from {{ options.email.sender || 'the configured sender' }} inside the branded email frame.</p>
            </div>
          </div>
        </header>
        <div class="p-4 sm:p-5 space-y-4">
          <p v-if="!options.email.configured" class="rounded-xl bg-danger/5 border border-danger/20 text-danger text-xs p-3">
            BREVO_API_KEY is not set on this server. Emails will be recorded as failed until it is configured.
          </p>

          <ExamContextSelect v-model="examId" :exams="options.exams" />

          <div>
            <div class="flex items-center justify-between gap-2 mb-1.5">
              <label for="email-subject" class="lbl mb-0">Subject <span class="text-danger">*</span></label>
              <PlaceholderMenu :placeholders="options.placeholders" :has-exam="!!examId" align="right" @insert="insertSubject" />
            </div>
            <input id="email-subject" ref="subjectEl" v-model="draft.subject" type="text" maxlength="200" class="field"
                   :class="{ 'border-danger': err('email.subject') }" placeholder="e.g. {{first_name}}, your hall ticket is ready" />
            <p v-if="err('email.subject')" class="text-danger text-xs mt-1">{{ err('email.subject') }}</p>
          </div>

          <div>
            <div class="flex items-center justify-between gap-2 mb-1.5">
              <span class="lbl mb-0">Message <span class="text-danger">*</span></span>
              <PlaceholderMenu :placeholders="options.placeholders" :has-exam="!!examId" align="right" @insert="(t) => editor?.insertText(t)" />
            </div>
            <RichTextEditor ref="editor" v-model="draft.body_html" min-height="220px" :error="!!err('email.body_html')"
                            placeholder="Write your announcement…" />
            <p v-if="err('email.body_html')" class="text-danger text-xs mt-1">{{ err('email.body_html') }}</p>
            <p class="text-[11px] text-text-muted mt-1.5">The logo header, “Open Student Portal” button and support footer are added automatically.</p>
          </div>
        </div>
      </section>
    </div>

    <aside class="xl:col-span-5 min-w-0">
      <section class="card" aria-labelledby="email-prev-h">
        <header class="card-h">
          <div class="flex items-center gap-3">
            <span class="step">3</span>
            <div class="min-w-0">
              <h2 id="email-prev-h" class="font-heading font-bold text-text-main text-sm">Preview &amp; send</h2>
              <p class="text-[11px] text-text-muted truncate">
                {{ b.preview.value?.sample ? `As ${b.preview.value.sample.name} will see it` : 'With sample details' }}
                <span v-if="b.previewLoading.value" class="text-accent"> · updating…</span>
              </p>
            </div>
          </div>
        </header>
        <div class="p-4 sm:p-5 space-y-4">
          <div class="rounded-xl border border-gray-200 overflow-hidden bg-[#FBF6EC]">
            <div class="px-3 py-2 bg-white border-b border-gray-100 text-xs">
              <p class="text-text-muted">Subject</p>
              <p class="font-semibold text-text-main break-words">{{ b.preview.value?.preview?.subject || draft.subject || '—' }}</p>
            </div>
            <iframe v-if="b.preview.value?.preview?.html" :srcdoc="b.preview.value.preview.html" title="Email preview"
                    sandbox="" class="w-full h-[460px] bg-white block" />
            <p v-else class="p-8 text-center text-xs text-text-muted">{{ b.previewError.value || 'Write a subject and message to see the email.' }}</p>
          </div>

          <SendPanel :preview="b.preview.value" :recipients="summary.recipients || 0" :sending="b.sending.value" :ready="ready"
                     channel-label="email" :errors="b.errors.value" blank-hint="that part of the email will be empty for them."
                     test-label="Send a test email" test-type="email" :test-default="adminEmail" test-placeholder="you@example.com"
                     :testing="b.testing.value" :test-result="b.testResult.value"
                     @test="b.sendTest" @send="b.send(onSent)" />
        </div>
      </section>
    </aside>
  </div>
</template>

<script setup>
import { computed, ref, toRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import RichTextEditor from '@/Components/Admin/RichTextEditor.vue';
import ExamContextSelect from './ExamContextSelect.vue';
import PlaceholderMenu from './PlaceholderMenu.vue';
import SendPanel from './SendPanel.vue';
import { insertAtCursor } from './notifications';
import { useBroadcast } from './useBroadcast';

const props = defineProps({
  draft: { type: Object, required: true },
  audience: { type: Object, required: true },
  summary: { type: Object, required: true },
  options: { type: Object, required: true },
});
const examId = defineModel('examId', { type: Number, default: null });
const emit = defineEmits(['sent']);

const editor = ref(null);
const subjectEl = ref(null);
const adminEmail = usePage().props.auth?.user?.email ?? '';

const ready = computed(() => !!props.draft.subject.trim() && !!props.draft.body_html.replace(/<[^>]*>/g, '').trim());
const b = useBroadcast({
  channel: 'email',
  content: () => (ready.value ? { subject: props.draft.subject, body_html: props.draft.body_html } : null),
  audience: toRef(props, 'audience'),
  examId,
  summary: toRef(props, 'summary'),
});

const err = (key) => b.errors.value?.[key] || (key === 'email.body_html' ? b.errors.value?.content : null);
const insertSubject = (token) => { props.draft.subject = insertAtCursor(subjectEl.value, props.draft.subject, token); };
const onSent = () => emit('sent');
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm; }
.card-h { @apply flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100; }
.step { @apply w-7 h-7 shrink-0 rounded-full bg-primary text-white font-number font-bold text-sm flex items-center justify-center; }
.lbl { @apply block text-[11px] font-semibold text-text-muted uppercase tracking-wider mb-1.5; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main; }
</style>
