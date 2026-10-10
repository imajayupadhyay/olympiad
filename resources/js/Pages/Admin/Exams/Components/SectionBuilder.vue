<template>
  <div>
    <div v-if="attemptsCount > 0" class="mb-4 rounded-2xl border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-dark">
      <strong>{{ attemptsCount }} student{{ attemptsCount === 1 ? ' has' : 's have' }} already attempted this exam.</strong>
      Changing its questions or marks now affects how their results are processed.
    </div>
    <div v-if="errors.sections" class="mb-4 rounded-2xl border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger">{{ errors.sections }}</div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
      <!-- ── Section outline ── -->
      <aside class="lg:col-span-4 xl:col-span-3 lg:sticky lg:top-20 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-heading font-bold text-text-main text-xs uppercase tracking-wider">Sections</h3>
          <span class="text-[11px] text-text-muted">Drag to reorder</span>
        </div>

        <ol class="p-2 space-y-1">
          <li v-for="(section, index) in sections" :key="section.key"
              draggable="true"
              @dragstart="startDrag($event, 'section', section.key)"
              @dragend="endDrag"
              @dragover.prevent="dragOverSection = section.key"
              @dragleave="dragOverSection = null"
              @drop.prevent="dropOnSection(section)"
              class="group rounded-xl border transition-colors"
              :class="[
                activeKey === section.key ? 'border-primary bg-primary/[0.04]' : 'border-transparent hover:bg-gray-50',
                dragOverSection === section.key ? 'ring-2 ring-accent/40' : '',
                sectionHasErrors(index) ? 'border-danger/40' : '',
              ]">
            <button type="button" class="w-full text-left px-3 py-2.5 flex items-start gap-2.5" @click="activeKey = section.key">
              <span class="mt-0.5 text-gray-300 cursor-grab select-none" aria-hidden="true">⋮⋮</span>
              <span class="w-6 h-6 shrink-0 rounded-lg bg-primary/10 text-primary font-number text-xs font-bold grid place-items-center">{{ index + 1 }}</span>
              <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-text-main truncate">{{ section.name || 'Untitled section' }}</span>
                <span class="block text-[11px] text-text-muted">
                  <template v-if="section.question_ids.length">Q{{ offsets[index] + 1 }}–Q{{ offsets[index] + section.question_ids.length }} · </template>
                  {{ section.question_ids.length }} Q · {{ formatMarks(sectionMarks(section)) }} marks
                </span>
              </span>
              <span v-if="sectionHasErrors(index)" class="mt-1 w-2 h-2 rounded-full bg-danger shrink-0" title="Needs attention"></span>
            </button>
            <div class="hidden group-hover:flex gap-1 px-3 pb-2 -mt-1 justify-end">
              <button type="button" class="mini" :disabled="index === 0" @click="moveSection(index, -1)" aria-label="Move section up">↑</button>
              <button type="button" class="mini" :disabled="index === sections.length - 1" @click="moveSection(index, 1)" aria-label="Move section down">↓</button>
            </div>
          </li>
        </ol>

        <div class="p-3 border-t border-gray-100 space-y-3">
          <button type="button" @click="addSection"
                  class="w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-semibold text-text-muted hover:border-primary hover:text-primary transition-colors">
            + Add section
          </button>
          <div class="flex justify-between text-xs text-text-muted">
            <span><strong class="font-number text-text-main">{{ totalQuestions }}</strong> questions</span>
            <span><strong class="font-number text-text-main">{{ formatMarks(totalMarks) }}</strong> marks</span>
          </div>
        </div>
      </aside>

      <!-- ── Active section ── -->
      <div class="lg:col-span-8 xl:col-span-9 space-y-4">
        <div v-if="!activeSection" class="bg-white rounded-2xl border-2 border-dashed border-gray-200 py-16 text-center">
          <p class="font-heading font-bold text-text-main text-lg">Build the paper section by section</p>
          <p class="text-sm text-text-muted mt-1 max-w-md mx-auto">Add a section such as Reasoning, Mathematics or English, then fill it with questions in the order students should see them.</p>
          <div class="mt-5 flex flex-wrap justify-center gap-2">
            <button type="button" @click="addSection" class="px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-light">+ Add first section</button>
            <button type="button" @click="importOpen = true" class="px-5 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-bold text-text-main hover:border-primary">⇪ Import whole paper from Excel</button>
          </div>
        </div>

        <template v-else>
          <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex flex-wrap items-start gap-3">
              <div class="flex-1 min-w-[220px]">
                <label class="label">Section {{ activeIndex + 1 }} name *</label>
                <input v-model="activeSection.name" type="text" maxlength="120" placeholder="e.g. Logical Reasoning" class="field"
                       :class="sectionError('name') ? 'border-danger' : ''" />
                <p v-if="sectionError('name')" class="error">{{ sectionError('name') }}</p>
              </div>
              <div class="w-full sm:w-56">
                <label class="label">Draws questions from *</label>
                <select v-model.number="activeSection.subject_id" class="field" :class="sectionError('subject_id') ? 'border-danger' : ''">
                  <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.icon }} {{ subject.name }}</option>
                </select>
                <p v-if="sectionError('subject_id')" class="error">{{ sectionError('subject_id') }}</p>
              </div>
              <button type="button" @click="toggleSettings" class="self-end px-3 py-2.5 rounded-xl text-sm font-semibold text-text-muted hover:bg-gray-100">
                {{ showSettings ? 'Hide settings' : 'Marks & instructions' }}
              </button>
            </div>

            <div v-if="showSettings" class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-4 gap-3">
              <div>
                <label class="label">+ Marks override</label>
                <input v-model="activeSection.marks_per_question" type="number" min="0.25" max="100" step="0.25" placeholder="Exam default" class="field font-number" />
                <p v-if="sectionError('marks_per_question')" class="error">{{ sectionError('marks_per_question') }}</p>
              </div>
              <div>
                <label class="label">− Negative override</label>
                <input v-model="activeSection.negative_marks_per_question" type="number" min="0" max="100" step="0.25" placeholder="Exam default"
                       :disabled="!exam.negative_marking_enabled" class="field font-number disabled:bg-gray-50" />
                <p v-if="sectionError('negative_marks_per_question')" class="error">{{ sectionError('negative_marks_per_question') }}</p>
                <p v-else-if="!exam.negative_marking_enabled" class="text-[11px] text-text-muted mt-1">Turn on negative marking in Scoring first.</p>
              </div>
              <div class="md:col-span-2">
                <label class="label">Section instructions <span class="font-normal">(shown to students)</span></label>
                <textarea v-model="activeSection.instructions" rows="2" placeholder="e.g. Each question carries 3 marks." class="field resize-y"></textarea>
              </div>
              <div class="md:col-span-4 flex justify-end">
                <button type="button" @click="removeSection" class="text-xs font-bold px-3 py-2 rounded-lg"
                        :class="deleteArmed ? 'bg-danger text-white' : 'text-danger hover:bg-danger/5'">
                  {{ deleteArmed ? `Click again to delete with ${activeSection.question_ids.length} question(s)` : 'Delete section' }}
                </button>
              </div>
            </div>

            <p v-if="mismatchCount" class="mt-3 text-xs text-danger">
              {{ mismatchCount }} question{{ mismatchCount === 1 ? ' does' : 's do' }} not belong to this section's subject. Remove {{ mismatchCount === 1 ? 'it' : 'them' }} or switch the subject back.
            </p>
            <p v-if="sectionError('question_ids')" class="mt-3 text-xs text-danger font-semibold">{{ sectionError('question_ids') }}</p>
          </section>

          <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="writerOpen = true" class="action bg-primary text-white hover:bg-primary-light">
              <span aria-hidden="true">✎</span> Write new question
            </button>
            <button type="button" @click="bankOpen = true" class="action bg-white border border-gray-200 text-text-main hover:border-primary">
              <span aria-hidden="true">＋</span> Pick from question bank
            </button>
            <button type="button" @click="importOpen = true" class="action bg-white border border-gray-200 text-text-main hover:border-primary">
              <span aria-hidden="true">⇪</span> Import from Excel
            </button>
            <div class="flex-1"></div>
            <div class="flex items-center bg-gray-100 rounded-xl p-1 text-xs font-semibold">
              <button type="button" class="px-3 py-1.5 rounded-lg" :class="compact ? 'bg-white shadow-sm text-primary' : 'text-text-muted'" @click="compact = true">Compact</button>
              <button type="button" class="px-3 py-1.5 rounded-lg" :class="!compact ? 'bg-white shadow-sm text-primary' : 'text-text-muted'" @click="compact = false">Full preview</button>
            </div>
          </div>

          <div v-if="activeSection.question_ids.length === 0"
               class="rounded-2xl border-2 border-dashed py-12 text-center transition-colors"
               :class="dragOverEmpty ? 'border-accent bg-accent/5' : 'border-gray-200 bg-white'"
               @dragover.prevent="dragOverEmpty = true" @dragleave="dragOverEmpty = false" @drop.prevent="dropOnSection(activeSection)">
            <p class="font-heading font-bold text-text-main">No questions in this section yet</p>
            <p class="text-sm text-text-muted mt-1">Write new ones or pick existing questions from the bank. They appear here in order.</p>
          </div>

          <ol v-else class="space-y-2">
            <li v-for="(questionId, position) in activeSection.question_ids" :key="questionId"
                draggable="true"
                @dragstart="startDrag($event, 'question', questionId)"
                @dragend="endDrag"
                @dragover.prevent="dragOverQuestion = questionId"
                @dragleave="dragOverQuestion = null"
                @drop.prevent="dropOnQuestion(position)"
                class="bg-white rounded-xl border shadow-sm transition-all"
                :class="[
                  dragOverQuestion === questionId && dragging?.type === 'question' ? 'border-accent ring-2 ring-accent/30' : 'border-gray-100',
                  questionProblem(questionId) ? 'border-danger/50' : '',
                  dragging?.id === questionId ? 'opacity-40' : '',
                ]">
              <div class="flex items-start gap-3 p-3">
                <span class="mt-1 text-gray-300 cursor-grab select-none" aria-hidden="true">⋮⋮</span>
                <span class="mt-0.5 min-w-[2.75rem] text-center font-number text-sm font-bold text-primary">Q{{ offsets[activeIndex] + position + 1 }}</span>
                <div class="min-w-0 flex-1">
                  <template v-if="question(questionId)">
                    <div v-if="!compact" class="rich text-sm text-text-main" v-html="question(questionId).question_text"></div>
                    <p v-else class="text-sm text-text-main font-medium leading-snug line-clamp-2">{{ stripHtml(question(questionId).question_text) }}</p>
                    <ol v-if="!compact" class="grid sm:grid-cols-2 gap-1.5 mt-2.5">
                      <li v-for="option in ['a', 'b', 'c', 'd']" :key="option"
                          class="rounded-lg border px-2.5 py-1.5 text-xs flex gap-2"
                          :class="question(questionId).correct_options?.includes(option) ? 'border-success/40 bg-green-50 text-success' : 'border-gray-100'">
                        <span class="font-bold uppercase">{{ option }}</span>
                        <span class="rich min-w-0" v-html="question(questionId)[`option_${option}`]"></span>
                      </li>
                    </ol>
                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                      <span class="chip" :class="difficultyClass(question(questionId).difficulty)">{{ question(questionId).difficulty }}</span>
                      <span class="chip bg-blue-50 text-primary">{{ question(questionId).question_type === 'multiple' ? 'Multi correct' : 'Single correct' }}</span>
                      <span v-if="question(questionId).question_category" class="chip bg-accent/10 text-accent-dark normal-case">{{ question(questionId).question_category.name }}</span>
                      <span class="text-[11px] font-number text-text-muted">
                        +{{ formatMarks(marksFor(exam, activeSection, question(questionId))) }}
                        <template v-if="negativeMarksFor(exam, activeSection, question(questionId)) > 0"> / −{{ formatMarks(negativeMarksFor(exam, activeSection, question(questionId))) }}</template>
                      </span>
                      <span v-if="questionProblem(questionId)" class="chip bg-danger/10 text-danger normal-case">{{ questionProblem(questionId) }}</span>
                    </div>
                  </template>
                  <p v-else class="text-sm text-text-muted">Question #{{ questionId }}</p>
                </div>

                <div class="shrink-0 flex items-center gap-0.5">
                  <button type="button" class="icon" :disabled="position === 0" @click="moveWithin(position, -1)" aria-label="Move up">↑</button>
                  <button type="button" class="icon" :disabled="position === activeSection.question_ids.length - 1" @click="moveWithin(position, 1)" aria-label="Move down">↓</button>
                  <button type="button" class="icon" :class="moveMenu === questionId ? 'bg-gray-100' : ''" @click="openMoveMenu(questionId, position)" aria-label="Move to…">⋯</button>
                </div>
              </div>

              <div v-if="moveMenu === questionId" class="border-t border-gray-100 bg-gray-50/70 px-3 py-2.5 flex flex-wrap items-end gap-2 rounded-b-xl">
                <div>
                  <label class="label">Position in section</label>
                  <input v-model.number="moveTarget.position" type="number" min="1" :max="activeSection.question_ids.length" class="field w-24 font-number" @keydown.enter.prevent="applyMove" />
                </div>
                <div v-if="sections.length > 1">
                  <label class="label">Or move to section</label>
                  <select v-model="moveTarget.sectionKey" class="field">
                    <option v-for="(section, index) in sections" :key="section.key" :value="section.key">{{ index + 1 }}. {{ section.name || 'Untitled' }}</option>
                  </select>
                </div>
                <button type="button" class="px-3 py-2 rounded-lg bg-primary text-white text-xs font-bold" @click="applyMove">Move</button>
                <div class="flex-1"></div>
                <a :href="route('admin.questions.edit', questionId)" target="_blank" rel="noopener" class="px-3 py-2 rounded-lg text-xs font-semibold text-primary hover:bg-white">Edit in bank ↗</a>
                <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold text-danger hover:bg-danger/5" @click="removeQuestion(position)">Remove from exam</button>
              </div>
            </li>
          </ol>
        </template>
      </div>
    </div>

    <QuestionBankDrawer
      :open="bankOpen"
      :section="activeSection"
      :class-level-id="exam.class_level_id"
      :exam-subject-id="exam.subject_id"
      :exam-category-id="exam.question_category_id"
      :used-ids="usedIds"
      :subjects="subjects"
      :class-levels="classLevels"
      :categories="categories"
      :difficulties="difficulties"
      :types="types"
      @add="addQuestions"
      @close="bankOpen = false"
    />
    <ExamImportDialog :open="importOpen" :exam-id="examId" :dirty="dirty" @close="importOpen = false" />
    <QuestionWriterDrawer
      :open="writerOpen"
      :section="activeSection"
      :class-level-id="exam.class_level_id"
      :subjects="subjects"
      :class-levels="classLevels"
      :categories="categories"
      :difficulties="difficulties"
      :types="types"
      @created="(question) => addQuestions([question])"
      @close="writerOpen = false"
    />
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import QuestionBankDrawer from './QuestionBankDrawer.vue';
import QuestionWriterDrawer from './QuestionWriterDrawer.vue';
import ExamImportDialog from './ExamImportDialog.vue';
import { blankSection, difficultyClass, formatMarks, marksFor, negativeMarksFor, stripHtml } from './examBuilder';

const props = defineProps({
  sections: { type: Array, required: true },
  questionCache: { type: Object, required: true },
  exam: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  attemptsCount: { type: Number, default: 0 },
  examId: { type: Number, required: true },
  dirty: { type: Boolean, default: false },
  subjects: { type: Array, default: () => [] },
  classLevels: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  difficulties: { type: Object, default: () => ({}) },
  types: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['changed']);

const activeKey = ref(props.sections[0]?.key ?? null);
const compact = ref(true);
const showSettings = ref(false);
const deleteArmed = ref(false);
const bankOpen = ref(false);
const writerOpen = ref(false);
// Arriving from "Import" (exams list) or "Save & import from Excel" opens the dialog straight away.
const importOpen = ref(new URLSearchParams(window.location.search).get('import') === '1');
if (importOpen.value) {
  const url = new URL(window.location.href);
  url.searchParams.delete('import');
  window.history.replaceState(window.history.state, '', url);
}
const dragging = ref(null);
const dragOverSection = ref(null);
const dragOverQuestion = ref(null);
const dragOverEmpty = ref(false);
const moveMenu = ref(null);
const moveTarget = reactive({ position: 1, sectionKey: null });

const activeIndex = computed(() => props.sections.findIndex((section) => section.key === activeKey.value));
const activeSection = computed(() => props.sections[activeIndex.value] ?? null);
const offsets = computed(() => props.sections.reduce((list, section, index) => {
  list.push(index === 0 ? 0 : list[index - 1] + props.sections[index - 1].question_ids.length);
  return list;
}, []));
const usedIds = computed(() => new Set(props.sections.flatMap((section) => section.question_ids)));
const totalQuestions = computed(() => usedIds.value.size);
const totalMarks = computed(() => props.sections.reduce((sum, section) => sum + sectionMarks(section), 0));
const mismatchCount = computed(() => (activeSection.value?.question_ids || [])
  .filter((id) => question(id) && Number(question(id).subject_id) !== Number(activeSection.value.subject_id)).length);

watch(() => props.sections.length, () => {
  if (!activeSection.value) activeKey.value = props.sections[0]?.key ?? null;
});
watch(activeKey, () => {
  deleteArmed.value = false;
  moveMenu.value = null;
});

const question = (id) => props.questionCache[id];
const sectionMarks = (section) => section.question_ids.reduce((sum, id) => sum + marksFor(props.exam, section, question(id)), 0);
const sectionError = (field) => props.errors[`sections.${activeIndex.value}.${field}`];
const sectionHasErrors = (index) => Object.keys(props.errors).some((key) => key.startsWith(`sections.${index}.`));

function questionProblem(id) {
  const item = question(id);
  if (!item) return null;
  if (!item.is_active) return 'Inactive in bank';
  if (Number(item.subject_id) !== Number(activeSection.value?.subject_id)) return `From ${item.subject?.name || 'another subject'}`;
  if (!(item.class_levels || []).some((level) => Number(level.id) === Number(props.exam.class_level_id))) return 'Not offered to this class';
  return null;
}

function changed() {
  emit('changed');
}

function addSection() {
  const section = blankSection(props.exam.subject_id, props.sections.length + 1);
  props.sections.push(section);
  activeKey.value = section.key;
  showSettings.value = false;
  changed();
}

function toggleSettings() {
  showSettings.value = !showSettings.value;
  deleteArmed.value = false;
}

function removeSection() {
  if (activeSection.value.question_ids.length && !deleteArmed.value) {
    deleteArmed.value = true;
    return;
  }
  const index = activeIndex.value;
  props.sections.splice(index, 1);
  activeKey.value = props.sections[Math.max(0, index - 1)]?.key ?? null;
  deleteArmed.value = false;
  changed();
}

function moveSection(index, direction) {
  const [section] = props.sections.splice(index, 1);
  props.sections.splice(index + direction, 0, section);
  changed();
}

function addQuestions(questions) {
  if (!activeSection.value) return;
  questions.forEach((item) => {
    props.questionCache[item.id] = item;
    if (!usedIds.value.has(item.id)) activeSection.value.question_ids.push(item.id);
  });
  changed();
}

function moveWithin(position, direction) {
  const ids = activeSection.value.question_ids;
  const [id] = ids.splice(position, 1);
  ids.splice(position + direction, 0, id);
  changed();
}

function removeQuestion(position) {
  activeSection.value.question_ids.splice(position, 1);
  moveMenu.value = null;
  changed();
}

function openMoveMenu(id, position) {
  if (moveMenu.value === id) {
    moveMenu.value = null;
    return;
  }
  moveMenu.value = id;
  moveTarget.position = position + 1;
  moveTarget.sectionKey = activeKey.value;
}

function applyMove() {
  const id = moveMenu.value;
  const source = activeSection.value;
  const target = props.sections.find((section) => section.key === moveTarget.sectionKey) ?? source;
  source.question_ids.splice(source.question_ids.indexOf(id), 1);

  if (target === source) {
    const position = Math.min(Math.max(Number(moveTarget.position) || 1, 1), source.question_ids.length + 1);
    source.question_ids.splice(position - 1, 0, id);
  } else {
    target.question_ids.push(id);
  }
  moveMenu.value = null;
  changed();
}

/* ── native drag & drop ── */
function startDrag(event, type, id) {
  dragging.value = { type, id };
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', String(id));
}

function endDrag() {
  dragging.value = null;
  dragOverSection.value = null;
  dragOverQuestion.value = null;
  dragOverEmpty.value = false;
}

function dropOnSection(target) {
  const drag = dragging.value;
  if (!drag) return;

  if (drag.type === 'section') {
    const from = props.sections.findIndex((section) => section.key === drag.id);
    const to = props.sections.indexOf(target);
    if (from !== -1 && from !== to) {
      const [section] = props.sections.splice(from, 1);
      props.sections.splice(to, 0, section);
      changed();
    }
  } else if (drag.type === 'question') {
    const source = props.sections.find((section) => section.question_ids.includes(drag.id));
    if (source && source !== target) {
      source.question_ids.splice(source.question_ids.indexOf(drag.id), 1);
      target.question_ids.push(drag.id);
      changed();
    }
  }
  endDrag();
}

function dropOnQuestion(position) {
  const drag = dragging.value;
  if (drag?.type !== 'question') {
    endDrag();
    return;
  }
  const ids = activeSection.value.question_ids;
  const from = ids.indexOf(drag.id);
  if (from !== -1 && from !== position) {
    ids.splice(from, 1);
    ids.splice(position, 0, drag.id);
    changed();
  }
  endDrag();
}
</script>

<style scoped>
.label { @apply block mb-1.5 text-xs font-semibold text-text-muted; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:border-primary; }
.error { @apply text-danger text-xs mt-1; }
.chip { @apply text-[10px] font-semibold px-2 py-0.5 rounded capitalize; }
.action { @apply inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition-colors; }
.icon { @apply w-8 h-8 grid place-items-center rounded-lg text-sm text-text-muted hover:bg-gray-100 hover:text-text-main disabled:opacity-30 disabled:hover:bg-transparent; }
.mini { @apply w-6 h-6 grid place-items-center rounded-md text-xs text-text-muted bg-white border border-gray-200 hover:text-primary disabled:opacity-30; }
.rich :deep(p) { margin: 0; }
.rich :deep(img) { max-height: 140px; max-width: 100%; border-radius: 8px; margin: .25rem 0; }
</style>
