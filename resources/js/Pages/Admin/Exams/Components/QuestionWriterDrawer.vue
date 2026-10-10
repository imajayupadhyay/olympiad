<template>
  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="open" class="fixed inset-0 z-50 flex justify-end bg-primary-dark/50 backdrop-blur-[2px]" @click.self="close">
        <form class="h-full w-full max-w-5xl bg-bg shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="writer-title" @submit.prevent="save(false)">
          <header class="bg-white border-b border-gray-100 px-5 py-4 flex items-start justify-between gap-4">
            <div class="min-w-0">
              <p class="text-[11px] font-bold uppercase tracking-wider text-accent">Write new question</p>
              <h2 id="writer-title" class="font-heading font-bold text-text-main text-lg truncate">Add to “{{ section?.name }}”</h2>
              <p class="text-xs text-text-muted mt-0.5">Saved to the Question Bank ({{ subjectName }}) and added to the end of this section.</p>
            </div>
            <button type="button" class="p-2 rounded-xl text-text-muted hover:bg-gray-100" aria-label="Close" @click="close">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </header>

          <div class="flex-1 overflow-y-auto p-5">
            <p v-if="message" class="mb-4 rounded-xl border px-4 py-3 text-sm"
               :class="messageTone === 'success' ? 'border-success/20 bg-success/5 text-success' : 'border-danger/20 bg-danger/5 text-danger'">
              {{ message }}
            </p>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
              <div class="lg:col-span-2 space-y-4">
                <section class="card">
                  <div class="flex items-center justify-between mb-3">
                    <h3 class="card-title">Question</h3>
                    <div class="flex items-center bg-gray-100 rounded-xl p-1 gap-1">
                      <button v-for="(label, key) in types" :key="key" type="button" @click="setType(key)"
                              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                              :class="draft.question_type === key ? 'bg-white text-primary shadow-sm' : 'text-text-muted hover:text-text-main'">
                        {{ label }}
                      </button>
                    </div>
                  </div>
                  <RichTextEditor :key="`q-${resetKey}`" v-model="draft.question_text" placeholder="Type the question…" :error="!!errors.question_text" allow-images min-height="120px" />
                  <p v-if="errors.question_text" class="error">{{ errors.question_text }}</p>
                </section>

                <section class="card">
                  <div class="flex items-center justify-between mb-3">
                    <h3 class="card-title">Options</h3>
                    <p class="text-xs text-text-muted">{{ draft.question_type === 'single' ? 'Click a letter to mark the one correct answer' : 'Click letters to mark every correct answer' }}</p>
                  </div>
                  <div class="space-y-2.5">
                    <div v-for="option in ['a', 'b', 'c', 'd']" :key="option" class="flex items-start gap-3">
                      <button type="button" @click="toggleCorrect(option)"
                              class="mt-1 w-9 h-9 shrink-0 rounded-xl border-2 text-sm font-bold uppercase transition-colors"
                              :class="draft.correct_options.includes(option) ? 'border-success bg-success text-white' : 'border-gray-200 text-text-muted hover:border-gray-300'"
                              :aria-pressed="draft.correct_options.includes(option)"
                              :aria-label="`Mark option ${option.toUpperCase()} correct`">
                        {{ option }}
                      </button>
                      <div class="flex-1 min-w-0">
                        <RichTextEditor :key="`${option}-${resetKey}`" v-model="draft[`option_${option}`]" :placeholder="`Option ${option.toUpperCase()}…`" :error="!!errors[`option_${option}`]" allow-images min-height="44px" />
                      </div>
                    </div>
                  </div>
                  <p v-if="errors.correct_options" class="error">{{ errors.correct_options }}</p>
                  <p v-for="option in ['a', 'b', 'c', 'd']" v-show="errors[`option_${option}`]" :key="`e-${option}`" class="error">{{ errors[`option_${option}`] }}</p>
                </section>

                <section class="card">
                  <h3 class="card-title mb-3">Explanation <span class="font-normal normal-case text-text-muted">(optional)</span></h3>
                  <RichTextEditor :key="`x-${resetKey}`" v-model="draft.explanation" placeholder="Why is the answer correct?" min-height="70px" />
                </section>
              </div>

              <aside class="space-y-4">
                <section class="card space-y-3.5">
                  <h3 class="card-title">Classification</h3>
                  <div>
                    <label class="label">Bank subject</label>
                    <p class="px-3 py-2.5 rounded-xl bg-gray-50 border border-gray-100 text-sm text-text-main">{{ subjectName }}</p>
                  </div>
                  <div>
                    <label class="label">Category</label>
                    <select v-model="draft.question_category_id" class="field">
                      <option value="">Uncategorized</option>
                      <option v-for="category in categoryOptions" :key="category.id" :value="category.id">{{ ''.padStart(category.depth * 2, '-') }} {{ category.path }}</option>
                    </select>
                    <p v-if="errors.question_category_id" class="error">{{ errors.question_category_id }}</p>
                  </div>
                  <div>
                    <label class="label">Class levels *</label>
                    <div class="grid grid-cols-3 gap-1.5">
                      <button v-for="level in classLevels" :key="level.id" type="button" @click="toggleClass(level.id)"
                              :disabled="Number(level.id) === Number(classLevelId)"
                              class="py-1.5 rounded-lg text-[11px] font-semibold border transition-colors disabled:cursor-not-allowed"
                              :class="draft.class_level_ids.includes(level.id) ? 'border-primary bg-primary/10 text-primary' : 'border-gray-100 text-text-muted hover:border-gray-200'">
                        {{ level.label }}
                      </button>
                    </div>
                    <p class="text-[11px] text-text-muted mt-1">This exam's class is always included.</p>
                    <p v-if="errors.class_level_ids" class="error">{{ errors.class_level_ids }}</p>
                  </div>
                  <div>
                    <label class="label">Difficulty *</label>
                    <div class="grid grid-cols-3 gap-1.5">
                      <button v-for="(label, key) in difficulties" :key="key" type="button" @click="draft.difficulty = key"
                              class="py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                              :class="draft.difficulty === key ? 'border-primary bg-primary/10 text-primary' : 'border-gray-100 text-text-muted hover:border-gray-200'">
                        {{ label }}
                      </button>
                    </div>
                  </div>
                  <QuestionTagPicker v-model="draft.tag_ids" :subject-id="section?.subject_id" :class-level-ids="draft.class_level_ids" :subjects="subjects" :class-levels="classLevels" />
                  <p v-if="errors.tag_ids" class="error">{{ errors.tag_ids }}</p>
                </section>

                <section class="card">
                  <h3 class="card-title mb-3">Bank marks</h3>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs font-semibold text-success mb-1.5">+ Correct</label>
                      <input v-model.number="draft.marks" type="number" min="1" max="10" class="field font-number" />
                    </div>
                    <div>
                      <label class="block text-xs font-semibold text-danger mb-1.5">− Incorrect</label>
                      <input v-model.number="draft.negative_marks" type="number" min="0" max="5" step="0.25" class="field font-number" />
                    </div>
                  </div>
                  <p v-if="errors.marks || errors.negative_marks" class="error">{{ errors.marks || errors.negative_marks }}</p>
                  <p class="text-[11px] text-text-muted mt-2">The exam's scoring policy or a section override can replace these in this exam.</p>
                </section>
              </aside>
            </div>
          </div>

          <footer class="bg-white border-t border-gray-100 px-5 py-3 flex flex-wrap items-center gap-3">
            <span v-if="createdCount" class="text-xs text-success font-semibold">{{ createdCount }} added this session</span>
            <div class="flex-1"></div>
            <button type="button" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-text-muted hover:bg-gray-100" @click="close">
              {{ createdCount ? 'Done' : 'Cancel' }}
            </button>
            <button type="button" :disabled="saving" @click="save(true)"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold text-accent border-2 border-accent/20 hover:bg-accent/5 disabled:opacity-50">
              Save &amp; write another
            </button>
            <button type="submit" :disabled="saving"
                    class="px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-light disabled:opacity-50">
              {{ saving ? 'Saving…' : 'Save & add to section' }}
            </button>
          </footer>
        </form>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import RichTextEditor from '@/Components/Admin/RichTextEditor.vue';
import QuestionTagPicker from '@/Components/Admin/QuestionTagPicker.vue';

const props = defineProps({
  open: Boolean,
  section: { type: Object, default: null },
  classLevelId: { type: [Number, String], default: '' },
  subjects: { type: Array, default: () => [] },
  classLevels: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  difficulties: { type: Object, default: () => ({}) },
  types: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'created']);

const blankDraft = () => ({
  question_type: 'single',
  question_text: '',
  option_a: '',
  option_b: '',
  option_c: '',
  option_d: '',
  correct_options: [],
  explanation: '',
  question_category_id: '',
  class_level_ids: props.classLevelId ? [Number(props.classLevelId)] : [],
  difficulty: 'medium',
  tag_ids: [],
  marks: 1,
  negative_marks: 0,
});

const draft = ref(blankDraft());
const errors = ref({});
const saving = ref(false);
const message = ref('');
const messageTone = ref('error');
const createdCount = ref(0);
// Remounts the editors so they clear between questions.
const resetKey = ref(0);

const subjectName = computed(() => props.subjects.find((subject) => Number(subject.id) === Number(props.section?.subject_id))?.name || 'subject');
const categoryOptions = computed(() => props.categories.filter((category) =>
  Number(category.subject_id) === Number(props.section?.subject_id) && category.is_active));

watch(() => props.open, (isOpen) => {
  if (!isOpen) return;
  reset();
  createdCount.value = 0;
  message.value = '';
});

function reset() {
  draft.value = blankDraft();
  errors.value = {};
  resetKey.value++;
}

function setType(type) {
  draft.value.question_type = type;
  if (type === 'single' && draft.value.correct_options.length > 1) {
    draft.value.correct_options = [draft.value.correct_options[0]];
  }
}

function toggleCorrect(option) {
  const current = draft.value.correct_options;
  if (draft.value.question_type === 'single') {
    draft.value.correct_options = [option];
  } else {
    draft.value.correct_options = current.includes(option) ? current.filter((value) => value !== option) : [...current, option];
  }
}

function toggleClass(id) {
  if (Number(id) === Number(props.classLevelId)) return;
  const ids = draft.value.class_level_ids;
  draft.value.class_level_ids = ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id];
}

async function save(another) {
  if (saving.value) return;
  saving.value = true;
  errors.value = {};
  message.value = '';

  try {
    const { data } = await window.axios.post(route('admin.questions.store'), {
      ...draft.value,
      subject_id: props.section.subject_id,
      question_category_id: draft.value.question_category_id || null,
      is_active: true,
    });
    emit('created', data.question);
    createdCount.value++;

    if (another) {
      reset();
      messageTone.value = 'success';
      message.value = 'Saved to the Question Bank and added to the section. Write the next one.';
    } else {
      close();
    }
  } catch (exception) {
    const status = exception.response?.status;
    messageTone.value = 'error';
    if (status === 422) {
      errors.value = Object.fromEntries(Object.entries(exception.response.data.errors || {}).map(([key, value]) => [key, value[0]]));
      message.value = 'Please fix the highlighted fields.';
    } else if (status === 403) {
      message.value = 'You need write access to the Question Bank to create questions here.';
    } else if (status === 419) {
      message.value = 'Your session expired. Reload the page and try again.';
    } else {
      message.value = 'The question could not be saved. Check your connection and try again.';
    }
  } finally {
    saving.value = false;
  }
}

function close() {
  emit('close');
}
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm p-4; }
.card-title { @apply font-heading font-bold text-text-main text-xs uppercase tracking-wider; }
.label { @apply block text-xs font-semibold text-text-muted mb-1.5; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:border-primary; }
.error { @apply text-danger text-xs mt-1.5; }
.drawer-enter-active, .drawer-leave-active { transition: opacity .2s ease; }
.drawer-enter-active > form, .drawer-leave-active > form { transition: transform .25s ease; }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-from > form, .drawer-leave-to > form { transform: translateX(40px); }
</style>
