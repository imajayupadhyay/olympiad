<template>
  <AdminLayout title="Add Question" subtitle="Build a new MCQ for the question bank">

    <form @submit.prevent="submit" enctype="multipart/form-data">
      <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">

        <!-- ── Main Column ─────────────────────────────────────── -->
        <div class="xl:col-span-3 space-y-5">

          <!-- Question Text -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 pt-5 pb-3">
              <div class="flex items-center justify-between mb-3">
                <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider">Question</h2>
                <!-- Question type pill toggle -->
                <div class="flex items-center bg-gray-100 rounded-xl p-1 gap-1">
                  <button
                    v-for="(label, key) in types" :key="key"
                    type="button"
                    @click="form.question_type = key"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                    :class="form.question_type === key
                      ? 'bg-white text-primary shadow-sm'
                      : 'text-text-muted hover:text-text-main'"
                  >
                    <span class="mr-1.5">{{ key === 'single' ? '◉' : '☑' }}</span>{{ label }}
                  </button>
                </div>
              </div>

              <RichTextEditor
                v-model="form.question_text"
                placeholder="Type your question here. Use the toolbar for bold, superscript (x²), subscript (H₂O)…"
                :error="!!form.errors.question_text"
                min-height="140px"
              />
              <p v-if="form.errors.question_text" class="text-danger text-xs mt-1.5">{{ form.errors.question_text }}</p>
            </div>

            <!-- Image Upload -->
            <div class="px-5 pb-5">
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-2 mt-3">
                Question Image <span class="font-normal normal-case">(optional — PNG, JPG, max 2MB)</span>
              </label>

              <div v-if="!imagePreview"
                   class="relative border-2 border-dashed border-gray-200 rounded-xl p-6 text-center cursor-pointer hover:border-primary/40 hover:bg-primary/[0.02] transition-all"
                   @click="$refs.imageInput.click()"
                   @dragover.prevent
                   @drop.prevent="handleDrop">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                </svg>
                <p class="text-sm text-text-muted">Click to upload or drag & drop</p>
                <p class="text-xs text-gray-400 mt-0.5">PNG, JPG, GIF, WebP up to 2MB</p>
                <input ref="imageInput" type="file" accept="image/*" class="hidden"
                       @change="handleImageChange" />
              </div>

              <div v-else class="relative rounded-xl overflow-hidden border border-gray-200 group">
                <img :src="imagePreview" alt="Question image" class="w-full max-h-56 object-contain bg-gray-50" />
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all flex items-center justify-center gap-3 opacity-0 group-hover:opacity-100">
                  <button type="button" @click="$refs.imageInput.click()"
                          class="bg-white text-text-main text-xs font-semibold px-3 py-1.5 rounded-lg shadow hover:bg-gray-50">
                    Replace
                  </button>
                  <button type="button" @click="clearImage"
                          class="bg-danger text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow">
                    Remove
                  </button>
                </div>
                <input ref="imageInput" type="file" accept="image/*" class="hidden"
                       @change="handleImageChange" />
              </div>
            </div>
          </div>

          <!-- Answer Options -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
              <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider">Answer Options</h2>
              <p class="text-text-muted text-xs">
                {{ form.question_type === 'single' ? 'Click to select one correct answer' : 'Click to select all correct answers' }}
              </p>
            </div>

            <div class="space-y-3">
              <div
                v-for="opt in ['a','b','c','d']"
                :key="opt"
                class="group flex items-start gap-3 p-3.5 rounded-xl border-2 transition-all cursor-pointer"
                :class="isCorrect(opt)
                  ? 'border-success bg-green-50'
                  : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50/50'"
                @click="toggleCorrect(opt)"
              >
                <!-- Selection indicator -->
                <div class="mt-0.5 shrink-0">
                  <!-- Single: radio -->
                  <div v-if="form.question_type === 'single'"
                       class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                       :class="isCorrect(opt) ? 'border-success' : 'border-gray-300 group-hover:border-gray-400'">
                    <div v-if="isCorrect(opt)" class="w-2.5 h-2.5 rounded-full bg-success"></div>
                  </div>
                  <!-- Multiple: checkbox -->
                  <div v-else
                       class="w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all"
                       :class="isCorrect(opt) ? 'border-success bg-success' : 'border-gray-300 group-hover:border-gray-400'">
                    <svg v-if="isCorrect(opt)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                  </div>
                </div>

                <!-- Option label -->
                <span class="text-xs font-bold uppercase w-5 shrink-0 mt-1"
                      :class="isCorrect(opt) ? 'text-success' : 'text-text-muted'">
                  {{ opt }}
                </span>

                <!-- Option text -->
                <div class="flex-1" @click.stop>
                  <RichTextEditor
                    v-model="form['option_' + opt]"
                    :placeholder="`Option ${opt.toUpperCase()}…`"
                    :error="!!form.errors['option_' + opt]"
                    min-height="44px"
                  />
                </div>

                <!-- Correct badge -->
                <span v-if="isCorrect(opt)"
                      class="shrink-0 mt-1 text-[10px] font-bold text-success bg-green-100 px-2 py-0.5 rounded-full whitespace-nowrap self-start">
                  ✓ Correct
                </span>
              </div>
            </div>
            <p v-if="form.errors.correct_options" class="text-danger text-xs mt-2">{{ form.errors.correct_options }}</p>
          </div>

          <!-- Explanation -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-3">
              Explanation <span class="font-normal normal-case text-text-muted">(shown to students after the exam)</span>
            </h2>
            <RichTextEditor
              v-model="form.explanation"
              placeholder="Explain why the correct answer is right. You can include working steps, formulas, or key facts…"
              min-height="80px"
            />
          </div>
        </div>

        <!-- ── Sidebar ──────────────────────────────────────────── -->
        <div class="xl:col-span-2 space-y-4 xl:sticky xl:top-20">

          <!-- Classification -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-4">Classification</h2>

            <div class="space-y-3.5">
              <!-- Subject -->
              <div>
                <label class="block text-xs font-semibold text-text-muted mb-1.5">Subject *</label>
                <select v-model="form.subject_id"
                        class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white transition-colors"
                        :class="form.errors.subject_id ? 'border-danger' : 'border-gray-200'">
                  <option value="">Choose subject…</option>
                  <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.icon }} {{ s.name }}</option>
                </select>
                <p v-if="form.errors.subject_id" class="text-danger text-xs mt-1">{{ form.errors.subject_id }}</p>
              </div>

              <!-- Category -->
              <div>
                <label class="block text-xs font-semibold text-text-muted mb-1.5">Category / Topic</label>
                <select v-model="form.question_category_id"
                        :disabled="!form.subject_id"
                        class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white transition-colors disabled:bg-gray-50 disabled:text-text-muted"
                        :class="form.errors.question_category_id ? 'border-danger' : 'border-gray-200'">
                  <option value="">Uncategorized</option>
                  <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                    {{ optionPrefix(category.depth) }} {{ category.path }}
                  </option>
                </select>
                <p v-if="form.errors.question_category_id" class="text-danger text-xs mt-1">{{ form.errors.question_category_id }}</p>
              </div>

              <!-- Class -->
              <div>
                <label class="block text-xs font-semibold text-text-muted mb-1.5">Class Level *</label>
                <select v-model="form.class_level_id"
                        class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white transition-colors"
                        :class="form.errors.class_level_id ? 'border-danger' : 'border-gray-200'">
                  <option value="">Choose class…</option>
                  <option v-for="cl in classLevels" :key="cl.id" :value="cl.id">{{ cl.label }}</option>
                </select>
              </div>

              <!-- Difficulty -->
              <div>
                <label class="block text-xs font-semibold text-text-muted mb-1.5">Difficulty *</label>
                <div class="grid grid-cols-3 gap-2">
                  <button
                    v-for="(label, key) in difficulties" :key="key"
                    type="button"
                    @click="form.difficulty = key"
                    class="py-2 rounded-xl text-xs font-semibold border-2 transition-all"
                    :class="form.difficulty === key
                      ? difficultyActive[key]
                      : 'border-gray-100 text-text-muted hover:border-gray-200'"
                  >
                    {{ label }}
                  </button>
                </div>
              </div>

              <!-- Topic -->
              <div>
                <label class="block text-xs font-semibold text-text-muted mb-1.5">Topic / Tag <span class="font-normal">(optional)</span></label>
                <input v-model="form.topic" type="text" placeholder="e.g. Fractions, Photosynthesis…"
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary" />
              </div>
            </div>
          </div>

          <!-- Marks -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-4">Marks</h2>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-success mb-1.5">+ Correct</label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-success font-bold text-sm">+</span>
                  <input v-model.number="form.marks" type="number" min="1" max="10"
                         class="w-full pl-7 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-success font-number text-success font-bold" />
                </div>
              </div>
              <div>
                <label class="block text-xs font-semibold text-danger mb-1.5">− Incorrect</label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-danger font-bold text-sm">−</span>
                  <input v-model.number="form.negative_marks" type="number" min="0" max="5" step="0.25"
                         class="w-full pl-7 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-danger font-number text-danger font-bold" />
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-2.5">
            <button type="submit" :disabled="form.processing"
                    class="w-full flex items-center justify-center gap-2 bg-primary text-white py-3 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60 shadow-sm">
              <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
              </svg>
              {{ form.processing ? 'Saving…' : 'Save Question' }}
            </button>

            <button type="button" :disabled="form.processing" @click="submitAndNew"
                    class="w-full py-2.5 rounded-xl text-sm font-semibold text-accent border-2 border-accent/20 hover:bg-accent/5 transition-colors">
              Save & Add Another
            </button>

            <Link :href="route('admin.questions.index')"
                  class="w-full flex items-center justify-center py-2.5 rounded-xl text-sm font-semibold text-text-muted hover:bg-gray-100 transition-colors">
              Cancel
            </Link>
          </div>
        </div>
      </div>
    </form>
  </AdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/Admin/RichTextEditor.vue';

const props = defineProps({
  subjects:     Array,
  categories:   Array,
  classLevels:  Array,
  difficulties: Object,
  types:        Object,
});

const imageInput   = ref(null);
const imagePreview = ref(null);

const difficultyActive = {
  easy:   'border-success bg-green-50 text-success',
  medium: 'border-amber-400 bg-amber-50 text-amber-700',
  hard:   'border-danger bg-red-50 text-danger',
};

const blankForm = () => useForm({
  subject_id:      '',
  class_level_id:  '',
  question_category_id: '',
  difficulty:      'medium',
  question_type:   'single',
  topic:           '',
  question_text:   '',
  question_image:  null,
  option_a:        '',
  option_b:        '',
  option_c:        '',
  option_d:        '',
  correct_options: [],
  explanation:     '',
  marks:           1,
  negative_marks:  0,
});

let form = blankForm();

const categoryOptions = computed(() => (props.categories || []).filter((category) =>
  category.is_active && Number(category.subject_id) === Number(form.subject_id),
));

watch(() => form.subject_id, () => {
  if (form.question_category_id && !categoryOptions.value.some((category) => Number(category.id) === Number(form.question_category_id))) {
    form.question_category_id = '';
  }
});

const optionPrefix = (depth) => ''.padStart(depth * 2, '-');

const isCorrect = (opt) => form.correct_options.includes(opt);

const toggleCorrect = (opt) => {
  if (form.question_type === 'single') {
    form.correct_options = [opt];
  } else {
    if (isCorrect(opt)) {
      form.correct_options = form.correct_options.filter(o => o !== opt);
    } else {
      form.correct_options = [...form.correct_options, opt];
    }
  }
};

const handleImageChange = (e) => {
  const file = e.target.files[0];
  if (!file) return;
  form.question_image = file;
  imagePreview.value  = URL.createObjectURL(file);
};

const handleDrop = (e) => {
  const file = e.dataTransfer.files[0];
  if (!file || !file.type.startsWith('image/')) return;
  form.question_image = file;
  imagePreview.value  = URL.createObjectURL(file);
};

const clearImage = () => {
  form.question_image = null;
  imagePreview.value  = null;
  if (imageInput.value) imageInput.value.value = '';
};

const submit = () => {
  form.post(route('admin.questions.store'), { forceFormData: true });
};

const submitAndNew = () => {
  form.post(route('admin.questions.store'), {
    forceFormData: true,
    onSuccess: () => {
      form = blankForm();
      imagePreview.value = null;
    },
  });
};
</script>
