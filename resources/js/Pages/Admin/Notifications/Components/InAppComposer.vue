<template>
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
    <div class="xl:col-span-7 space-y-5 min-w-0">
      <slot name="audience" />

      <section class="card" aria-labelledby="inapp-h">
        <header class="card-h">
          <div class="flex items-center gap-3">
            <span class="step">2</span>
            <div>
              <h2 id="inapp-h" class="font-heading font-bold text-text-main text-sm">Write the notification</h2>
              <p class="text-[11px] text-text-muted">Shows in the student portal bell and on the Notifications page, instantly.</p>
            </div>
          </div>
        </header>
        <div class="p-4 sm:p-5 space-y-4">
          <ExamContextSelect v-model="examId" :exams="options.exams" />

          <div>
            <div class="flex items-center justify-between gap-2 mb-1.5">
              <label for="inapp-title" class="lbl mb-0">Title <span class="text-danger">*</span></label>
              <PlaceholderMenu :placeholders="options.placeholders" :has-exam="!!examId" align="right" @insert="(t) => insert('title', titleEl, t)" />
            </div>
            <input id="inapp-title" ref="titleEl" v-model="draft.title" type="text" maxlength="150" class="field"
                   :class="{ 'border-danger': err('in_app.title') }" placeholder="e.g. Results declared for {{exam_name}}" />
            <p v-if="err('in_app.title')" class="text-danger text-xs mt-1">{{ err('in_app.title') }}</p>
          </div>

          <div>
            <div class="flex items-center justify-between gap-2 mb-1.5">
              <label for="inapp-msg" class="lbl mb-0">Message <span class="text-danger">*</span></label>
              <PlaceholderMenu :placeholders="options.placeholders" :has-exam="!!examId" align="right" @insert="(t) => insert('message', msgEl, t)" />
            </div>
            <textarea id="inapp-msg" ref="msgEl" v-model="draft.message" rows="5" maxlength="2000" class="field resize-y"
                      :class="{ 'border-danger': err('in_app.message') }" placeholder="Keep it short. Line breaks are kept." />
            <div class="flex justify-between mt-1">
              <p v-if="err('in_app.message')" class="text-danger text-xs">{{ err('in_app.message') }}</p>
              <p class="text-[10px] text-text-muted ml-auto font-number">{{ draft.message.length }}/2000</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="block">
              <span class="lbl">When tapped, open</span>
              <select v-model="linkChoice" class="field">
                <option v-for="p in options.linkPresets" :key="p.value" :value="p.value">{{ p.label }}</option>
                <option value="__custom">Another portal page…</option>
              </select>
            </label>
            <label v-if="linkChoice === '__custom'" class="block">
              <span class="lbl">Portal path</span>
              <input v-model="draft.link" type="text" class="field font-mono text-xs" placeholder="/student/exams/12" :class="{ 'border-danger': err('in_app.link') }" />
              <span v-if="err('in_app.link')" class="text-danger text-xs mt-1 block">Must start with /student/</span>
            </label>
          </div>
        </div>
      </section>
    </div>

    <aside class="xl:col-span-5 min-w-0">
      <section class="card" aria-labelledby="inapp-prev-h">
        <header class="card-h">
          <div class="flex items-center gap-3">
            <span class="step">3</span>
            <div class="min-w-0">
              <h2 id="inapp-prev-h" class="font-heading font-bold text-text-main text-sm">Preview &amp; send</h2>
              <p class="text-[11px] text-text-muted truncate">
                {{ b.preview.value?.sample ? `As ${b.preview.value.sample.name} will see it` : 'With sample details' }}
                <span v-if="b.previewLoading.value" class="text-accent"> · updating…</span>
              </p>
            </div>
          </div>
        </header>
        <div class="p-4 sm:p-5 space-y-4">
          <!-- bell dropdown look -->
          <div class="rounded-2xl bg-[#FBF6EC] border border-[#E7D9BE] p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-text-muted mb-2">Student portal · notification bell</p>
            <div class="bg-white border border-[#f1cdb4] rounded-xl p-3.5 flex gap-3 shadow-sm">
              <span class="w-9 h-9 shrink-0 rounded-[10px] bg-accent/15 text-accent-dark flex items-center justify-center">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/></svg>
              </span>
              <div class="min-w-0 flex-1">
                <p class="font-semibold text-text-main text-sm break-words">{{ shown.title || 'Notification title' }}</p>
                <p class="text-xs text-text-muted whitespace-pre-line break-words mt-0.5 leading-relaxed">{{ shown.message || 'Your message appears here.' }}</p>
                <p class="text-[10px] text-text-muted mt-1.5">Just now<span v-if="draft.link"> · opens {{ draft.link }}</span></p>
              </div>
              <span class="w-2 h-2 rounded-full bg-accent shrink-0 mt-1.5"></span>
            </div>
          </div>

          <SendPanel :preview="b.preview.value" :recipients="summary.recipients || 0" :sending="b.sending.value" :ready="ready"
                     channel-label="in-app notification" :errors="b.errors.value"
                     @send="b.send(onSent)" />
        </div>
      </section>
    </aside>
  </div>
</template>

<script setup>
import { computed, ref, toRef, watch } from 'vue';
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

const titleEl = ref(null);
const msgEl = ref(null);
const presetValues = computed(() => props.options.linkPresets.map((p) => p.value));
const linkChoice = ref(presetValues.value.includes(props.draft.link || '') ? (props.draft.link || '') : '__custom');
watch(linkChoice, (v) => { props.draft.link = v === '__custom' ? (props.draft.link || '/student/') : v; });
watch(() => props.draft.link, (v) => { if (!v) linkChoice.value = ''; }); // reset after a send

const ready = computed(() => !!props.draft.title.trim() && !!props.draft.message.trim());
const b = useBroadcast({
  channel: 'in_app',
  content: () => (ready.value ? { title: props.draft.title, message: props.draft.message, link: props.draft.link || null } : null),
  audience: toRef(props, 'audience'),
  examId,
  summary: toRef(props, 'summary'),
});

const shown = computed(() => b.preview.value?.preview ?? { title: props.draft.title, message: props.draft.message });
const err = (key) => b.errors.value?.[key] || (key === 'in_app.message' ? b.errors.value?.content : null);
const insert = (field, el, token) => { props.draft[field] = insertAtCursor(el, props.draft[field], token); };
const onSent = () => emit('sent');
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm; }
.card-h { @apply flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100; }
.step { @apply w-7 h-7 shrink-0 rounded-full bg-primary text-white font-number font-bold text-sm flex items-center justify-center; }
.lbl { @apply block text-[11px] font-semibold text-text-muted uppercase tracking-wider mb-1.5; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main; }
</style>
