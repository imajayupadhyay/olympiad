<template>
  <AdminLayout :title="`Edit Q#${question.id}`" subtitle="Update this question">

    <form @submit.prevent="submit" enctype="multipart/form-data">
      <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">

        <!-- ── Main Column ─────────────────────────────────────── -->
        <div class="xl:col-span-3 space-y-5">

          <!-- Question Text -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 pt-5 pb-3">
              <div class="flex items-center justify-between mb-3">
                <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider">Question</h2>
                <div class="flex items-center bg-gray-100 rounded-xl p-1 gap-1">
                  <button v-for="(label, key) in types" :key="key" type="button"
                          @click="form.question_type = key"
                          class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                          :class="form.question_type === key
                            ? 'bg-white text-primary shadow-sm'
                            : 'text-text-muted hover:text-text-main'">
                    <span class="mr-1.5">{{ key === 'single' ? '◉' : '☑' }}</span>{{ label }}
                  </button>
                </div>
              </div>
              <RichTextEditor
                v-model="form.question_text"
                placeholder="Type your question here…"
                :error="!!form.errors.question_text"
                min-height="140px"
              />
              <p v-if="form.errors.question_text" class="text-danger text-xs mt-1.5">{{ form.errors.question_text }}</p>
            </div>

            <!-- Image -->
            <div class="px-5 pb-5">
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-2 mt-3">
                Question Image <span class="font-normal normal-case">(optional)</span>
              </label>

              <div v-if="!imagePreview"
                   class="relative border-2 border-dashed border-gray-200 rounded-xl p-6 text-center cursor-pointer hover:border-primary/40 hover:bg-primary/[0.02] transition-all"
                   @click="$refs.imageInput.click()" @dragover.prevent @drop.prevent="handleDrop">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                </svg>
                <p class="text-sm text-text-muted">Click to upload or drag & drop</p>
                <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="handleImageChange" />
              </div>

              <div v-else class="relative rounded-xl overflow-hidden border border-gray-200 group">
                <img :src="imagePreview" alt="Question image" class="w-full max-h-56 object-contain bg-gray-50" />
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all flex items-center justify-center gap-3 opacity-0 group-hover:opacity-100">
                  <button type="button" @click="$refs.imageInput.click()"
                          class="bg-white text-text-main text-xs font-semibold px-3 py-1.5 rounded-lg shadow">Replace</button>
                  <button type="button" @click="clearImage"
                          class="bg-danger text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow">Remove</button>
                </div>
                <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="handleImageChange" />
              </div>
            </div>
          </div>

          <!-- Answer Options -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
              <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider">Answer Options</h2>
              <p class="text-text-muted text-xs">
                {{ form.question_type === 'single' ? 'One correct answer' : 'Select all correct answers' }}
              </p>
            </div>

            <div class="space-y-3">
              <div v-for="opt in ['a','b','c','d']" :key="opt"
                   class="group flex items-start gap-3 p-3.5 rounded-xl border-2 transition-all cursor-pointer"
                   :class="isCorrect(opt) ? 'border-success bg-green-50' : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50/50'"
                   @click="toggleCorrect(opt)">
                <div class="mt-0.5 shrink-0">
                  <div v-if="form.question_type === 'single'"
                       class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
                       :class="isCorrect(opt) ? 'border-success' : 'border-gray-300'">
                    <div v-if="isCorrect(opt)" class="w-2.5 h-2.5 rounded-full bg-success"></div>
                  </div>
                  <div v-else class="w-5 h-5 rounded-md border-2 flex items-center justify-center"
                       :class="isCorrect(opt) ? 'border-success bg-success' : 'border-gray-300'">
                    <svg v-if="isCorrect(opt)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                  </div>
                </div>
                <span class="text-xs font-bold uppercase w-5 shrink-0 mt-1" :class="isCorrect(opt) ? 'text-success' : 'text-text-muted'">{{ opt }}</span>
                <div class="flex-1" @click.stop>
                  <RichTextEditor v-model="form['option_' + opt]" :placeholder="`Option ${opt.toUpperCase()}…`"
                                  :error="!!form.errors['option_' + opt]" min-height="44px" />
                </div>
                <span v-if="isCorrect(opt)"
                      class="shrink-0 mt-1 text-[10px] font-bold text-success bg-green-100 px-2 py-0.5 rounded-full whitespace-nowrap self-start">✓ Correct</span>
              </div>
            </div>
            <p v-if="form.errors.correct_options" class="text-danger text-xs mt-2">{{ form.errors.correct_options }}</p>
          </div>

          <!-- Explanation -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-3">
              Explanation <span class="font-normal normal-case text-text-muted">(shown after exam)</span>
            </h2>
            <RichTextEditor v-model="form.explanation" placeholder="Explain the correct answer…" min-height="80px" />
          </div>
        </div>

        <!-- ── Sidebar ──────────────────────────────────────────── -->
        <div class="xl:col-span-2 space-y-4 xl:sticky xl:top-20">

          <!-- Classification -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
              <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider">Classification</h2>
              <!-- Active toggle -->
              <div class="flex items-center gap-2 cursor-pointer" @click="form.is_active = !form.is_active">
                <span class="text-xs font-medium" :class="form.is_active ? 'text-success' : 'text-text-muted'">
                  {{ form.is_active ? 'Active' : 'Inactive' }}
                </span>
                <div class="relative w-10 h-5">
                  <div class="w-10 h-5 rounded-full transition-colors" :class="form.is_active ? 'bg-success' : 'bg-gray-300'"></div>
                  <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="form.is_active ? 'translate-x-5' : ''"></div>
                </div>
              </div>
            </div>
            <div class="space-y-3.5">
              <div>
                <label class="block text-xs font-semibold text-text-muted mb-1.5">Subject *</label>
                <select v-model="form.subject_id"
                        class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white"
                        :class="form.errors.subject_id ? 'border-danger' : 'border-gray-200'">
                  <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.icon }} {{ s.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-text-muted mb-1.5">Class Level *</label>
                <select v-model="form.class_level_id"
                        class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white"
                        :class="form.errors.class_level_id ? 'border-danger' : 'border-gray-200'">
                  <option v-for="cl in classLevels" :key="cl.id" :value="cl.id">{{ cl.label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-text-muted mb-1.5">Difficulty *</label>
                <div class="grid grid-cols-3 gap-2">
                  <button v-for="(label, key) in difficulties" :key="key" type="button"
                          @click="form.difficulty = key"
                          class="py-2 rounded-xl text-xs font-semibold border-2 transition-all"
                          :class="form.difficulty === key ? difficultyActive[key] : 'border-gray-100 text-text-muted hover:border-gray-200'">
                    {{ label }}
                  </button>
                </div>
              </div>
              <div>
                <label class="block text-xs font-semibold text-text-muted mb-1.5">Topic / Tag</label>
                <input v-model="form.topic" type="text" placeholder="e.g. Fractions…"
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
              {{ form.processing ? 'Saving…' : 'Update Question' }}
            </button>
            <Link :href="route('admin.questions.index')"
                  class="w-full flex items-center justify-center py-2.5 rounded-xl text-sm font-semibold text-text-muted hover:bg-gray-100 transition-colors">
              Cancel
            </Link>
            <button type="button" @click="confirmDelete = true"
                    class="w-full py-2.5 rounded-xl text-sm font-semibold text-danger hover:bg-danger/5 transition-colors border border-danger/10">
              Delete Question
            </button>
          </div>
        </div>
      </div>
    </form>

    <!-- Delete confirm -->
    <div v-if="confirmDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5);">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Delete this question?</h3>
        <p class="text-text-muted text-sm mb-5">This cannot be undone. Any exams using this question will lose it.</p>
        <div class="flex gap-3">
          <button @click="confirmDelete = false" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold">Cancel</button>
          <button @click="doDelete" class="flex-1 bg-danger text-white py-2.5 rounded-xl text-sm font-semibold">Delete</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/Admin/RichTextEditor.vue';

const props = defineProps({
  question:     Object,
  subjects:     Array,
  classLevels:  Array,
  difficulties: Object,
  types:        Object,
});

const imageInput   = ref(null);
const imagePreview = ref(props.question.question_image_url || null);
const confirmDelete = ref(false);
const removingImage = ref(false);

const difficultyActive = {
  easy:   'border-success bg-green-50 text-success',
  medium: 'border-amber-400 bg-amber-50 text-amber-700',
  hard:   'border-danger bg-red-50 text-danger',
};

const form = useForm({
  subject_id:      props.question.subject_id,
  class_level_id:  props.question.class_level_id,
  difficulty:      props.question.difficulty,
  question_type:   props.question.question_type,
  topic:           props.question.topic || '',
  question_text:   props.question.question_text,
  question_image:  null,
  remove_image:    false,
  option_a:        props.question.option_a,
  option_b:        props.question.option_b,
  option_c:        props.question.option_c,
  option_d:        props.question.option_d,
  correct_options: props.question.correct_options ?? [],
  explanation:     props.question.explanation || '',
  marks:           props.question.marks,
  negative_marks:  props.question.negative_marks,
  is_active:       props.question.is_active,
});

const isCorrect = (opt) => form.correct_options.includes(opt);

const toggleCorrect = (opt) => {
  if (form.question_type === 'single') {
    form.correct_options = [opt];
  } else {
    form.correct_options = isCorrect(opt)
      ? form.correct_options.filter(o => o !== opt)
      : [...form.correct_options, opt];
  }
};

const handleImageChange = (e) => {
  const file = e.target.files[0];
  if (!file) return;
  form.question_image = file;
  form.remove_image   = false;
  imagePreview.value  = URL.createObjectURL(file);
};

const handleDrop = (e) => {
  const file = e.dataTransfer.files[0];
  if (!file || !file.type.startsWith('image/')) return;
  form.question_image = file;
  form.remove_image   = false;
  imagePreview.value  = URL.createObjectURL(file);
};

const clearImage = () => {
  form.question_image = null;
  form.remove_image   = true;
  imagePreview.value  = null;
  if (imageInput.value) imageInput.value.value = '';
};

const submit = () => {
  // Use POST with _method=PUT for file uploads (multipart doesn't support PUT directly)
  form.transform(data => ({ ...data, _method: 'PUT' }))
      .post(route('admin.questions.update', props.question.id), { forceFormData: true });
};

const doDelete = () => {
  router.delete(route('admin.questions.destroy', props.question.id), {
    onSuccess: () => confirmDelete.value = false,
  });
};
</script>
