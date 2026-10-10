<template>
  <div>
    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">
      <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
      {{ $page.props.flash.error }}
    </div>

    <!-- ── Stepper ── -->
    <nav class="mb-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-2 flex flex-wrap gap-1" aria-label="Exam builder steps">
      <button v-for="(item, index) in steps" :key="item.key" type="button"
              :disabled="item.locked"
              :aria-current="step === item.key ? 'step' : undefined"
              @click="goTo(item.key)"
              class="flex-1 min-w-[150px] flex items-center gap-3 px-3 py-2.5 rounded-xl text-left transition-colors disabled:cursor-not-allowed"
              :class="step === item.key ? 'bg-primary text-white' : (item.locked ? 'text-text-muted/60' : 'text-text-main hover:bg-gray-50')">
        <span class="w-7 h-7 shrink-0 rounded-lg grid place-items-center font-number text-sm font-bold"
              :class="step === item.key ? 'bg-white/15' : (stepHasErrors(item.key) ? 'bg-danger/10 text-danger' : 'bg-primary/10 text-primary')">
          {{ stepHasErrors(item.key) ? '!' : index + 1 }}
        </span>
        <span class="min-w-0">
          <span class="block text-sm font-semibold truncate">{{ item.label }}</span>
          <span class="block text-[11px] truncate" :class="step === item.key ? 'text-white/70' : 'text-text-muted'">{{ item.hint }}</span>
        </span>
      </button>
    </nav>

    <form @submit.prevent="save()">
      <!-- ── 1. Details ── -->
      <div v-show="step === 'details'" class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">
        <div class="xl:col-span-3 space-y-5">
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-start justify-between gap-4 mb-5">
              <div>
                <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider">Exam Details</h2>
                <p v-if="exam?.exam_code" class="text-text-muted text-xs mt-1 font-number">{{ exam.exam_code }}</p>
              </div>
              <span class="text-xs font-semibold px-2.5 py-1 rounded-lg" :class="statusClass(form.status)">
                {{ statuses[form.status] || form.status }}
              </span>
            </div>

            <div class="space-y-4">
              <div>
                <label class="label">Exam Name *</label>
                <input v-model="form.name" type="text" placeholder="e.g. National Science Olympiad - Class 6"
                       class="field" :class="form.errors.name ? 'border-danger' : 'border-gray-200'" />
                <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
              </div>

              <div>
                <label class="label">Short Description</label>
                <textarea v-model="form.description" rows="3" placeholder="Brief exam overview shown to students."
                          class="field border-gray-200 resize-none"></textarea>
                <p v-if="form.errors.description" class="error">{{ form.errors.description }}</p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                  <label class="label">Olympiad Subject *</label>
                  <select v-model="form.subject_id" class="field" :class="form.errors.subject_id ? 'border-danger' : 'border-gray-200'">
                    <option value="">Choose subject</option>
                    <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.icon }} {{ subject.name }}</option>
                  </select>
                  <p v-if="form.errors.subject_id" class="error">{{ form.errors.subject_id }}</p>
                </div>

                <div>
                  <label class="label">Category Scope</label>
                  <select v-model="form.question_category_id" :disabled="!form.subject_id"
                          class="field disabled:bg-gray-50 disabled:text-text-muted"
                          :class="form.errors.question_category_id ? 'border-danger' : 'border-gray-200'">
                    <option value="">Whole subject</option>
                    <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                      {{ ''.padStart(category.depth * 2, '-') }} {{ category.path }}{{ category.is_active ? '' : ' (inactive)' }}
                    </option>
                  </select>
                  <p v-if="form.errors.question_category_id" class="error">{{ form.errors.question_category_id }}</p>
                </div>

                <div>
                  <label class="label">Class *</label>
                  <select v-model="form.class_level_id" class="field" :class="form.errors.class_level_id ? 'border-danger' : 'border-gray-200'">
                    <option value="">Choose class</option>
                    <option v-for="classLevel in classLevels" :key="classLevel.id" :value="classLevel.id">{{ classLevel.label }}</option>
                  </select>
                  <p v-if="form.errors.class_level_id" class="error">{{ form.errors.class_level_id }}</p>
                </div>
              </div>
              <p class="text-xs text-text-muted -mt-1">
                The olympiad subject decides where this exam is listed for students. Each section can draw questions from any subject.
                Category scope limits sections that use the olympiad subject.
              </p>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-4">Student-Facing Content</h2>
            <div class="space-y-4">
              <div>
                <label class="label">Syllabus</label>
                <textarea v-model="form.syllabus" rows="4" placeholder="Topics, chapters, and coverage." class="field border-gray-200 resize-y"></textarea>
              </div>
              <div>
                <label class="label">Eligibility</label>
                <textarea v-model="form.eligibility" rows="3" placeholder="Eligibility criteria for this exam." class="field border-gray-200 resize-y"></textarea>
              </div>
              <div>
                <label class="label">General Instructions</label>
                <textarea v-model="form.instructions" rows="5" placeholder="Exam rules, allowed items, and submission instructions." class="field border-gray-200 resize-y"></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="xl:col-span-2 space-y-5">
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-4">Schedule & Fees</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
              <div>
                <label class="label">Opens At *</label>
                <input v-model="form.starts_at" type="datetime-local" class="field" :class="form.errors.starts_at ? 'border-danger' : 'border-gray-200'" />
                <p v-if="form.errors.starts_at" class="error">{{ form.errors.starts_at }}</p>
              </div>
              <div>
                <label class="label">Closes At</label>
                <input v-model="form.ends_at" type="datetime-local" class="field" :class="form.errors.ends_at ? 'border-danger' : 'border-gray-200'" />
                <p v-if="form.errors.ends_at" class="error">{{ form.errors.ends_at }}</p>
              </div>
              <div>
                <label class="label">Duration *</label>
                <div class="relative">
                  <input v-model.number="form.duration_minutes" type="number" min="5" max="360"
                         class="w-full pl-3 pr-16 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white font-number" :class="form.errors.duration_minutes ? 'border-danger' : 'border-gray-200'" />
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted text-xs">mins</span>
                </div>
                <p v-if="form.errors.duration_minutes" class="error">{{ form.errors.duration_minutes }}</p>
              </div>
              <div>
                <label class="label">Exam Fee *</label>
                <div class="grid grid-cols-5 gap-2">
                  <input v-model.number="form.fee_amount" type="number" min="0" step="0.01"
                         class="field col-span-3 font-number" :class="form.errors.fee_amount ? 'border-danger' : 'border-gray-200'" />
                  <input v-model="form.fee_currency" type="text" maxlength="3"
                         class="field col-span-2 uppercase font-semibold" :class="form.errors.fee_currency ? 'border-danger' : 'border-gray-200'" />
                </div>
                <p v-if="form.errors.fee_amount" class="error">{{ form.errors.fee_amount }}</p>
                <p v-if="form.errors.fee_currency" class="error">{{ form.errors.fee_currency }}</p>
              </div>
            </div>
          </div>

          <div v-if="mode === 'create'" class="rounded-2xl border p-5 text-sm text-text-muted"
               :class="importIntent ? 'border-accent/30 bg-accent/5' : 'border-primary/10 bg-primary/[0.03]'">
            <p class="font-heading font-bold text-text-main mb-1">Next: sections & questions</p>
            <template v-if="importIntent">
              Fill in these details and click <strong class="text-text-main">Save &amp; import from Excel</strong>. You'll then download this exam's template,
              fill one row per question (with its section) and upload it to review before anything is saved.
            </template>
            <template v-else>
              Saving creates a draft. Build the paper section by section — write new questions, pick from the bank,
              or <strong class="text-text-main">import the whole paper from Excel</strong> — and publish when it's ready.
            </template>
          </div>
        </div>
      </div>

      <!-- ── 2. Sections & questions ── -->
      <SectionBuilder
        v-if="mode === 'edit'"
        v-show="step === 'questions'"
        :sections="form.sections"
        :question-cache="questionCache"
        :exam="form"
        :errors="form.errors"
        :attempts-count="exam?.attempts_count || 0"
        :exam-id="exam?.id"
        :dirty="form.isDirty"
        :subjects="subjects"
        :class-levels="classLevels"
        :categories="categories"
        :difficulties="difficulties"
        :types="types"
      />

      <!-- ── 3. Scoring & rules ── -->
      <div v-show="step === 'scoring'" class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
          <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider">Scoring Policy</h2>
          <div class="grid grid-cols-1 gap-2">
            <button v-for="(label, key) in scoringModes" :key="key" type="button" @click="form.scoring_mode = key"
                    class="text-left px-3 py-2.5 rounded-xl border-2 text-sm font-semibold transition-all"
                    :class="form.scoring_mode === key ? 'border-primary bg-primary/5 text-primary' : 'border-gray-100 text-text-main hover:border-gray-200'">
              {{ label }}
            </button>
          </div>
          <p v-if="form.errors.scoring_mode" class="error">{{ form.errors.scoring_mode }}</p>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-success mb-1.5">+ Correct</label>
              <input v-model.number="form.marks_per_question" type="number" min="0.25" max="100" step="0.25"
                     class="field font-number text-success font-bold" :class="form.errors.marks_per_question ? 'border-danger' : 'border-gray-200'" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-danger mb-1.5">− Incorrect</label>
              <input v-model.number="form.negative_marks_per_question" type="number" min="0" :max="form.marks_per_question" step="0.25"
                     :disabled="!form.negative_marking_enabled"
                     class="field font-number text-danger font-bold disabled:bg-gray-50 disabled:text-text-muted"
                     :class="form.errors.negative_marks_per_question ? 'border-danger' : 'border-gray-200'" />
            </div>
          </div>
          <p v-if="form.errors.marks_per_question" class="error">{{ form.errors.marks_per_question }}</p>
          <p v-if="form.errors.negative_marks_per_question" class="error">{{ form.errors.negative_marks_per_question }}</p>

          <label class="flex items-center justify-between gap-3 py-1 cursor-pointer">
            <span class="text-sm text-text-main">Negative marking</span>
            <span class="relative w-10 h-5 shrink-0">
              <span class="absolute inset-0 rounded-full transition-colors" :class="form.negative_marking_enabled ? 'bg-danger' : 'bg-gray-300'"></span>
              <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="form.negative_marking_enabled ? 'translate-x-5' : ''"></span>
              <input v-model="form.negative_marking_enabled" type="checkbox" class="sr-only" />
            </span>
          </label>
          <p class="text-xs text-text-muted">A section's own marks override these values for that section only.</p>
        </div>

        <div class="space-y-5">
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3">
            <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-1">Paper Rules</h2>
            <label class="flex items-center justify-between gap-3 cursor-pointer">
              <span>
                <span class="block text-sm text-text-main">Shuffle questions</span>
                <span class="block text-xs text-text-muted">Within each section — sections always stay in order.</span>
              </span>
              <input v-model="form.randomize_questions" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
            </label>
            <label class="flex items-center justify-between gap-3 cursor-pointer">
              <span class="text-sm text-text-main">Shuffle options</span>
              <input v-model="form.randomize_options" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
            </label>
          </div>

          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3">
            <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-1">Result Release</h2>
            <label class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50">
              <span class="text-sm text-text-main">Show result immediately</span>
              <span class="relative w-10 h-5 shrink-0">
                <span class="absolute inset-0 rounded-full transition-colors" :class="form.show_result_immediately ? 'bg-success' : 'bg-gray-300'"></span>
                <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="form.show_result_immediately ? 'translate-x-5' : ''"></span>
                <input v-model="form.show_result_immediately" type="checkbox" class="sr-only" />
              </span>
            </label>
            <div>
              <label class="label">Release results at</label>
              <input v-model="form.result_release_at" type="datetime-local" class="field" :class="form.errors.result_release_at ? 'border-danger' : 'border-gray-200'" />
              <p v-if="form.errors.result_release_at" class="error">{{ form.errors.result_release_at }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ── 4. Review ── -->
      <ExamReview
        v-if="mode === 'edit'"
        v-show="step === 'review'"
        :exam="form"
        :sections="form.sections"
        :question-cache="questionCache"
        :checks="checks"
        :subjects="subjects"
        :class-levels="classLevels"
        :statuses="statuses"
        :status="form.status"
        @go="goTo"
      />

      <!-- ── Sticky action bar ── -->
      <div class="sticky bottom-3 z-30 mt-6 rounded-2xl border border-gray-200 bg-white/95 backdrop-blur shadow-lg px-4 py-3">
        <div class="flex flex-wrap items-center gap-2">
          <button v-if="previousStep" type="button" @click="goTo(previousStep)" class="btn-ghost">← Back</button>
          <span class="text-xs" :class="form.isDirty ? 'text-gold-dark font-semibold' : 'text-text-muted'">
            {{ form.processing ? 'Saving…' : (form.isDirty ? 'Unsaved changes' : (mode === 'edit' ? 'All changes saved' : '')) }}
          </span>
          <div class="flex-1"></div>
          <Link :href="route('admin.exams.index')" class="btn-ghost">{{ mode === 'edit' ? 'Back to exams' : 'Cancel' }}</Link>

          <template v-if="mode === 'create'">
            <button type="button" :disabled="form.processing" @click="save(null, 'import')"
                    :class="importIntent ? 'btn-primary' : 'btn-ghost border border-gray-200'">
              ⇪ Save &amp; import from Excel
            </button>
            <button type="submit" :disabled="form.processing" :class="importIntent ? 'btn-ghost border border-gray-200' : 'btn-primary'">
              Save &amp; build manually →
            </button>
          </template>
          <template v-else-if="step === 'review'">
            <button v-if="form.status === 'published'" type="button" :disabled="form.processing" @click="save('draft')" class="btn-ghost border border-gray-200">Unpublish</button>
            <button type="button" :disabled="form.processing" @click="save()" class="btn-ghost border border-gray-200">
              {{ form.status === 'published' ? 'Save changes' : 'Save draft' }}
            </button>
            <button v-if="form.status !== 'published'" type="button" :disabled="form.processing || blockingCount > 0" @click="save('published')"
                    class="btn-accent" :title="blockingCount ? 'Resolve the checklist items first' : ''">
              Publish exam
            </button>
          </template>
          <template v-else>
            <button type="submit" :disabled="form.processing || !form.isDirty" class="btn-ghost border border-gray-200">Save</button>
            <button type="button" @click="goTo(nextStep)" class="btn-primary">Next: {{ stepLabel(nextStep) }} →</button>
          </template>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SectionBuilder from './SectionBuilder.vue';
import ExamReview from './ExamReview.vue';
import { hydrateSections } from './examBuilder';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({
  mode: { type: String, required: true },
  exam: { type: Object, default: null },
  subjects: { type: Array, required: true },
  categories: { type: Array, default: () => [] },
  classLevels: { type: Array, required: true },
  statuses: { type: Object, required: true },
  scoringModes: { type: Object, required: true },
  difficulties: { type: Object, required: true },
  types: { type: Object, default: () => ({ single: 'Single Correct', multiple: 'Multiple Correct' }) },
});

const form = useForm({
  subject_id: props.exam?.subject_id || '',
  class_level_id: props.exam?.class_level_id || '',
  question_category_id: props.exam?.question_category_id || '',
  name: props.exam?.name || '',
  description: props.exam?.description || '',
  syllabus: props.exam?.syllabus || '',
  eligibility: props.exam?.eligibility || '',
  instructions: props.exam?.instructions || '',
  starts_at: props.exam?.starts_at || '',
  ends_at: props.exam?.ends_at || '',
  duration_minutes: Number(props.exam?.duration_minutes || 60),
  fee_amount: Number(props.exam?.fee_amount || 0),
  fee_currency: props.exam?.fee_currency || 'INR',
  scoring_mode: props.exam?.scoring_mode || 'question_bank',
  marks_per_question: Number(props.exam?.marks_per_question || 1),
  negative_marking_enabled: Boolean(props.exam?.negative_marking_enabled || false),
  negative_marks_per_question: Number(props.exam?.negative_marks_per_question || 0),
  randomize_questions: Boolean(props.exam?.randomize_questions || false),
  randomize_options: Boolean(props.exam?.randomize_options || false),
  show_result_immediately: props.exam?.show_result_immediately ?? true,
  result_release_at: props.exam?.result_release_at || '',
  status: props.exam?.status || 'draft',
  sections: hydrateSections(props.exam?.sections),
});

// Question payloads by id, for display only — the form submits ids.
const questionCache = reactive({});
const cacheQuestions = (sections = []) => sections.forEach((section) =>
  (section.questions || []).forEach((question) => { questionCache[question.id] = question; }));
cacheQuestions(props.exam?.sections);

const totalQuestions = computed(() => form.sections.reduce((sum, section) => sum + section.question_ids.length, 0));

/* ── publish checklist ── */
const questionIssues = computed(() => form.sections.reduce((count, section) => count + section.question_ids.filter((id) => {
  const question = questionCache[id];
  return question && (!question.is_active
    || Number(question.subject_id) !== Number(section.subject_id)
    || !(question.class_levels || []).some((level) => Number(level.id) === Number(form.class_level_id)));
}).length, 0));
const checks = computed(() => {
  const emptySections = form.sections.filter((section) => !section.question_ids.length);
  return [
    { label: 'Name, subject and class are set', ok: Boolean(form.name && form.subject_id && form.class_level_id), blocking: true, step: 'details' },
    { label: 'Opening time and duration are set', ok: Boolean(form.starts_at && form.duration_minutes), blocking: true, step: 'details' },
    { label: totalQuestions.value ? `${totalQuestions.value} questions in ${form.sections.length} section(s)` : 'Add at least one question', ok: totalQuestions.value > 0, blocking: true, step: 'questions' },
    { label: emptySections.length ? `${emptySections.length} empty section(s): ${emptySections.map((section) => section.name || 'Untitled').join(', ')}` : 'No empty sections', ok: !emptySections.length, blocking: true, step: 'questions' },
    { label: questionIssues.value ? `${questionIssues.value} question(s) inactive, for another class, or from the wrong subject` : 'Every question fits its section and class', ok: !questionIssues.value, blocking: true, step: 'questions' },
    { label: form.ends_at ? 'Closing time is set' : 'No closing time — the exam stays open after it starts', ok: Boolean(form.ends_at), blocking: false, step: 'details' },
  ];
});
const blockingCount = computed(() => checks.value.filter((check) => !check.ok && check.blocking).length);

/* ── steps ── */
const DETAIL_FIELDS = ['name', 'description', 'subject_id', 'question_category_id', 'class_level_id', 'syllabus', 'eligibility', 'instructions', 'starts_at', 'ends_at', 'duration_minutes', 'fee_amount', 'fee_currency'];
const SCORING_FIELDS = ['scoring_mode', 'marks_per_question', 'negative_marks_per_question', 'negative_marking_enabled', 'randomize_questions', 'randomize_options', 'show_result_immediately', 'result_release_at'];

const steps = computed(() => [
  { key: 'details', label: 'Details', hint: 'Name, class, schedule', locked: false },
  { key: 'questions', label: 'Sections & Questions', hint: props.mode === 'edit' ? `${form.sections.length} sections · ${totalQuestions.value} questions` : 'Available after saving details', locked: props.mode !== 'edit' },
  { key: 'scoring', label: 'Scoring & Rules', hint: 'Marks, shuffle, results', locked: false },
  { key: 'review', label: 'Review & Publish', hint: props.mode === 'edit' ? (blockingCount.value ? `${blockingCount.value} item(s) to fix` : 'Ready to publish') : 'Available after saving details', locked: props.mode !== 'edit' },
]);
const stepKeys = computed(() => steps.value.filter((item) => !item.locked).map((item) => item.key));
const initialStep = new URLSearchParams(window.location.search).get('step');
const step = ref(stepKeys.value.includes(initialStep) ? initialStep : 'details');
const previousStep = computed(() => stepKeys.value[stepKeys.value.indexOf(step.value) - 1] ?? null);
const nextStep = computed(() => stepKeys.value[stepKeys.value.indexOf(step.value) + 1] ?? null);
const stepLabel = (key) => steps.value.find((item) => item.key === key)?.label ?? '';

function goTo(key) {
  if (!key || !stepKeys.value.includes(key)) return;
  step.value = key;
  const url = new URL(window.location.href);
  url.searchParams.set('step', key);
  window.history.replaceState(window.history.state, '', url);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function stepFor(field) {
  if (field === 'sections' || field === 'question_ids' || field.startsWith('sections.')) return 'questions';
  if (SCORING_FIELDS.includes(field)) return 'scoring';
  if (DETAIL_FIELDS.includes(field)) return 'details';
  return 'review';
}
const stepHasErrors = (key) => Object.keys(form.errors).some((field) => stepFor(field) === key);

/* ── saving ── */
let saving = false;
const importIntent = new URLSearchParams(window.location.search).get('import') === '1';

function save(status = null, then = null) {
  const previousStatus = form.status;
  if (status) form.status = status;
  saving = true;

  const options = {
    preserveScroll: true,
    onError: (errors) => {
      if (status) form.status = previousStatus;
      const firstStep = steps.value.map((item) => item.key).find((key) => Object.keys(errors).some((field) => stepFor(field) === key));
      if (firstStep && firstStep !== step.value) goTo(firstStep);
    },
    onSuccess: (page) => {
      if (props.mode !== 'edit') return;
      // Pick up ids of newly created sections, keeping the builder's selection stable.
      const fresh = hydrateSections(page.props.exam?.sections);
      fresh.forEach((section, index) => { section.key = form.sections[index]?.key ?? section.key; });
      cacheQuestions(page.props.exam?.sections);
      form.sections = fresh;
      form.status = page.props.exam?.status ?? form.status;
      form.defaults();
    },
    onFinish: () => { saving = false; },
  };

  if (props.mode === 'edit') {
    form.put(route('admin.exams.update', props.exam.id), options);
  } else {
    form.transform((data) => (then ? { ...data, then } : data)).post(route('admin.exams.store'), options);
  }
}

/* ── unsaved-changes guard ── */
// Closing the tab or reloading can only use the browser's own prompt; in-app navigation
// is paused, confirmed with our dialog, then replayed.
const onBeforeUnload = (event) => {
  if (!form.isDirty) return;
  event.preventDefault();
  event.returnValue = '';
};
let removeNavigationGuard = null;
let leaveConfirmed = false;

async function confirmLeave(visit) {
  const leave = await confirmDialog({
    title: 'Leave without saving?',
    message: 'You have unsaved changes to this exam. If you leave now, those changes will be lost.',
    confirmText: 'Leave without saving',
    cancelText: 'Keep editing',
    tone: 'warning',
  });
  if (!leave) return;

  leaveConfirmed = true;
  router.visit(visit.url.href, {
    method: visit.method,
    replace: visit.replace,
    preserveScroll: visit.preserveScroll,
    preserveState: visit.preserveState,
    only: visit.only,
    onFinish: () => { leaveConfirmed = false; },
  });
}

onMounted(() => {
  window.addEventListener('beforeunload', onBeforeUnload);
  removeNavigationGuard = router.on('before', (event) => {
    const visit = event.detail.visit;
    if (saving || leaveConfirmed || !form.isDirty || visit.method !== 'get') return;
    event.preventDefault();
    confirmLeave(visit);
  });
});
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', onBeforeUnload);
  removeNavigationGuard?.();
});

/* ── details helpers ── */
const categoryOptions = computed(() => (props.categories || []).filter((category) =>
  Number(category.subject_id) === Number(form.subject_id)
    && (category.is_active || Number(category.id) === Number(form.question_category_id))));

watch(() => form.subject_id, () => {
  if (form.question_category_id && !categoryOptions.value.some((category) => Number(category.id) === Number(form.question_category_id))) {
    form.question_category_id = '';
  }
});

const statusClass = (status) => ({
  draft: 'bg-gray-100 text-text-muted',
  published: 'bg-green-100 text-success',
  archived: 'bg-amber-100 text-amber-700',
}[status] || 'bg-gray-100 text-text-muted');
</script>

<style scoped>
.label { @apply block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5; }
.field { @apply w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white; }
.error { @apply text-danger text-xs mt-1; }
.btn-ghost { @apply px-4 py-2.5 rounded-xl text-sm font-semibold text-text-main hover:bg-gray-100 transition-colors disabled:opacity-50; }
.btn-primary { @apply px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-light transition-colors disabled:opacity-50; }
.btn-accent { @apply px-5 py-2.5 rounded-xl bg-accent text-white text-sm font-bold hover:bg-accent-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed; }
</style>
