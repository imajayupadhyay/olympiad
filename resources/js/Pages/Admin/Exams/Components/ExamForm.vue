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

    <form @submit.prevent="submit">
      <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">
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
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Exam Name *</label>
                <input v-model="form.name" type="text" placeholder="e.g. National Science Olympiad - Class 6"
                       class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white"
                       :class="form.errors.name ? 'border-danger' : 'border-gray-200'" />
                <p v-if="form.errors.name" class="text-danger text-xs mt-1">{{ form.errors.name }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Short Description</label>
                <textarea v-model="form.description" rows="3" placeholder="Brief exam overview shown to students."
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary resize-none"></textarea>
                <p v-if="form.errors.description" class="text-danger text-xs mt-1">{{ form.errors.description }}</p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Subject *</label>
                  <select v-model="form.subject_id"
                          class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white"
                          :class="form.errors.subject_id ? 'border-danger' : 'border-gray-200'">
                    <option value="">Choose subject</option>
                    <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                      {{ subject.icon }} {{ subject.name }}
                    </option>
                  </select>
                  <p v-if="form.errors.subject_id" class="text-danger text-xs mt-1">{{ form.errors.subject_id }}</p>
                </div>

                <div>
                  <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Class *</label>
                  <select v-model="form.class_level_id"
                          class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white"
                          :class="form.errors.class_level_id ? 'border-danger' : 'border-gray-200'">
                    <option value="">Choose class</option>
                    <option v-for="classLevel in classLevels" :key="classLevel.id" :value="classLevel.id">
                      {{ classLevel.label }}
                    </option>
                  </select>
                  <p v-if="form.errors.class_level_id" class="text-danger text-xs mt-1">{{ form.errors.class_level_id }}</p>
                </div>

                <div>
                  <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Status *</label>
                  <select v-model="form.status"
                          class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white"
                          :class="form.errors.status ? 'border-danger' : 'border-gray-200'">
                    <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                  </select>
                  <p v-if="form.errors.status" class="text-danger text-xs mt-1">{{ form.errors.status }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-4">Schedule & Fees</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Starts At *</label>
                <input v-model="form.starts_at" type="datetime-local"
                       class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white"
                       :class="form.errors.starts_at ? 'border-danger' : 'border-gray-200'" />
                <p v-if="form.errors.starts_at" class="text-danger text-xs mt-1">{{ form.errors.starts_at }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Closes At</label>
                <input v-model="form.ends_at" type="datetime-local"
                       class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white"
                       :class="form.errors.ends_at ? 'border-danger' : 'border-gray-200'" />
                <p v-if="form.errors.ends_at" class="text-danger text-xs mt-1">{{ form.errors.ends_at }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Duration *</label>
                <div class="relative">
                  <input v-model.number="form.duration_minutes" type="number" min="5" max="360"
                         class="w-full pl-3 pr-16 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary font-number"
                         :class="form.errors.duration_minutes ? 'border-danger' : 'border-gray-200'" />
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted text-xs">mins</span>
                </div>
                <p v-if="form.errors.duration_minutes" class="text-danger text-xs mt-1">{{ form.errors.duration_minutes }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Exam Fee *</label>
                <div class="grid grid-cols-5 gap-2">
                  <input v-model.number="form.fee_amount" type="number" min="0" step="0.01"
                         class="col-span-3 px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary font-number"
                         :class="form.errors.fee_amount ? 'border-danger' : 'border-gray-200'" />
                  <input v-model="form.fee_currency" type="text" maxlength="3"
                         class="col-span-2 px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary uppercase font-semibold"
                         :class="form.errors.fee_currency ? 'border-danger' : 'border-gray-200'" />
                </div>
                <p v-if="form.errors.fee_amount" class="text-danger text-xs mt-1">{{ form.errors.fee_amount }}</p>
                <p v-if="form.errors.fee_currency" class="text-danger text-xs mt-1">{{ form.errors.fee_currency }}</p>
              </div>

              <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Result Release</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <label class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50">
                    <span class="text-sm text-text-main">Show result immediately</span>
                    <span class="relative w-10 h-5 shrink-0">
                      <span class="absolute inset-0 rounded-full transition-colors" :class="form.show_result_immediately ? 'bg-success' : 'bg-gray-300'"></span>
                      <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="form.show_result_immediately ? 'translate-x-5' : ''"></span>
                      <input v-model="form.show_result_immediately" type="checkbox" class="sr-only" />
                    </span>
                  </label>
                  <input v-model="form.result_release_at" type="datetime-local"
                         class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white"
                         :class="form.errors.result_release_at ? 'border-danger' : 'border-gray-200'" />
                </div>
                <p v-if="form.errors.result_release_at" class="text-danger text-xs mt-1">{{ form.errors.result_release_at }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-4">Student-Facing Content</h2>
            <div class="space-y-4">
              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Syllabus</label>
                <textarea v-model="form.syllabus" rows="4" placeholder="Topics, chapters, and coverage."
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary resize-y"></textarea>
              </div>
              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Eligibility</label>
                <textarea v-model="form.eligibility" rows="3" placeholder="Eligibility criteria for this exam."
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary resize-y"></textarea>
              </div>
              <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Instructions</label>
                <textarea v-model="form.instructions" rows="5" placeholder="Exam rules, allowed items, and submission instructions."
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary resize-y"></textarea>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-3">
              <div>
                <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider">Question Assignment</h2>
                <p class="text-text-muted text-xs mt-1">
                  {{ selectedCount }} selected, {{ totalMarks }} total marks
                </p>
              </div>
              <span v-if="form.errors.question_ids" class="text-danger text-xs font-semibold">{{ form.errors.question_ids }}</span>
            </div>

            <div class="p-5 border-b border-gray-100">
              <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div class="md:col-span-2 relative">
                  <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                  </svg>
                  <input v-model="questionFilter.search" type="text" placeholder="Search question text or topic"
                         class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50"
                         @keydown.enter.prevent="loadQuestions" />
                </div>
                <select v-model="questionFilter.difficulty"
                        class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50">
                  <option value="">All Difficulties</option>
                  <option v-for="(label, key) in difficulties" :key="key" :value="key">{{ label }}</option>
                </select>
                <button type="button" :disabled="!canLoadQuestions || isLoadingQuestions" @click="loadQuestions"
                        class="bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-50">
                  {{ isLoadingQuestions ? 'Loading...' : 'Load Questions' }}
                </button>
                <button type="button" @click="clearQuestionFilters"
                        class="bg-gray-100 text-text-muted px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">
                  Clear
                </button>
              </div>
            </div>

            <div v-if="!canLoadQuestions" class="py-14 text-center">
              <div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
              </div>
              <p class="font-heading font-bold text-text-main text-base mb-1">Choose subject and class</p>
              <p class="text-text-muted text-sm">Matching active questions will appear here.</p>
            </div>

            <div v-else-if="isLoadingQuestions" class="py-14 text-center">
              <div class="w-8 h-8 rounded-full border-2 border-primary/20 border-t-primary animate-spin mx-auto mb-3"></div>
              <p class="font-heading font-bold text-text-main text-base mb-1">Loading questions</p>
              <p class="text-text-muted text-sm">Fetching active questions for this subject and class.</p>
            </div>

            <div v-else-if="availableQuestionRows.length === 0" class="py-14 text-center">
              <p class="font-heading font-bold text-text-main text-base mb-1">No matching questions</p>
              <p class="text-text-muted text-sm">Add questions to the bank or adjust the filters.</p>
            </div>

            <div v-else class="divide-y divide-gray-50">
              <div v-for="question in availableQuestionRows" :key="question.id"
                   class="p-4 hover:bg-gray-50/50 transition-colors cursor-pointer"
                   role="button"
                   tabindex="0"
                   @click="toggleQuestion(question)"
                   @keydown.enter.prevent="toggleQuestion(question)"
                   @keydown.space.prevent="toggleQuestion(question)">
                <div class="flex items-start gap-3">
                  <img v-if="question.question_image_url" :src="question.question_image_url" alt=""
                       class="w-12 h-12 rounded-lg object-cover border border-gray-100 shrink-0" />
                  <div class="min-w-0 flex-1">
                    <p class="text-text-main text-sm font-medium leading-snug line-clamp-2">{{ stripHtml(question.question_text) }}</p>
                    <div class="flex items-center gap-1.5 flex-wrap mt-2">
                      <span v-if="question.topic" class="text-[10px] text-text-muted bg-gray-100 px-2 py-0.5 rounded">{{ question.topic }}</span>
                      <span class="text-[10px] font-semibold px-2 py-0.5 rounded" :class="difficultyClass(question.difficulty)">
                        {{ question.difficulty }}
                      </span>
                      <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-blue-50 text-primary">
                        {{ question.question_type === 'multiple' ? 'Multi correct' : 'Single correct' }}
                      </span>
                      <span class="text-[10px] font-number text-text-muted">
                        +{{ question.marks }}<span v-if="question.negative_marks > 0"> / -{{ question.negative_marks }}</span>
                      </span>
                    </div>
                  </div>
                  <button type="button" @click.stop="toggleQuestion(question)"
                          class="shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                          :class="isSelected(question.id)
                            ? 'bg-success/10 text-success hover:bg-success/15'
                            : 'bg-primary text-white hover:bg-primary-light'">
                    {{ isSelected(question.id) ? 'Selected' : 'Add' }}
                  </button>
                </div>
              </div>
            </div>

            <div v-if="availableQuestions.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
              <p class="text-text-muted text-xs">
                Showing {{ availableQuestions.from }}-{{ availableQuestions.to }} of {{ availableQuestions.total }}
              </p>
              <div class="flex gap-1">
                <Link v-for="(link, index) in availableQuestions.links" :key="index"
                      :href="link.url || '#'"
                      preserve-scroll
                      preserve-state
                      :only="['availableQuestions']"
                      :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                        link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100',
                        !link.url ? 'opacity-40 pointer-events-none' : '',
                      ]"
                      v-html="link.label" />
              </div>
            </div>
          </div>
        </div>

        <div class="xl:col-span-2 space-y-4 xl:sticky xl:top-20">
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider mb-4">Scoring Policy</h2>

            <div class="space-y-4">
              <div class="grid grid-cols-1 gap-2">
                <button v-for="(label, key) in scoringModes" :key="key" type="button"
                        @click="form.scoring_mode = key"
                        class="text-left px-3 py-2.5 rounded-xl border-2 text-sm font-semibold transition-all"
                        :class="form.scoring_mode === key
                          ? 'border-primary bg-primary/5 text-primary'
                          : 'border-gray-100 text-text-main hover:border-gray-200'">
                  {{ label }}
                </button>
              </div>
              <p v-if="form.errors.scoring_mode" class="text-danger text-xs">{{ form.errors.scoring_mode }}</p>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-success mb-1.5">+ Correct</label>
                  <input v-model.number="form.marks_per_question" type="number" min="0.25" max="100" step="0.25"
                         class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-success font-number text-success font-bold"
                         :class="form.errors.marks_per_question ? 'border-danger' : 'border-gray-200'" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-danger mb-1.5">- Incorrect</label>
                  <input v-model.number="form.negative_marks_per_question" type="number" min="0" :max="form.marks_per_question" step="0.25"
                         :disabled="!form.negative_marking_enabled"
                         class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-danger font-number text-danger font-bold disabled:bg-gray-50 disabled:text-text-muted"
                         :class="form.errors.negative_marks_per_question ? 'border-danger' : 'border-gray-200'" />
                </div>
              </div>
              <p v-if="form.errors.marks_per_question" class="text-danger text-xs">{{ form.errors.marks_per_question }}</p>
              <p v-if="form.errors.negative_marks_per_question" class="text-danger text-xs">{{ form.errors.negative_marks_per_question }}</p>

              <label class="flex items-center justify-between gap-3 py-1 cursor-pointer">
                <span class="text-sm text-text-main">Negative marking</span>
                <span class="relative w-10 h-5 shrink-0">
                  <span class="absolute inset-0 rounded-full transition-colors" :class="form.negative_marking_enabled ? 'bg-danger' : 'bg-gray-300'"></span>
                  <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="form.negative_marking_enabled ? 'translate-x-5' : ''"></span>
                  <input v-model="form.negative_marking_enabled" type="checkbox" class="sr-only" />
                </span>
              </label>

              <div class="border-t border-gray-100 pt-4 space-y-3">
                <label class="flex items-center justify-between gap-3 cursor-pointer">
                  <span class="text-sm text-text-main">Randomize questions</span>
                  <input v-model="form.randomize_questions" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
                </label>
                <label class="flex items-center justify-between gap-3 cursor-pointer">
                  <span class="text-sm text-text-main">Randomize options</span>
                  <input v-model="form.randomize_options" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
                </label>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
              <h2 class="font-heading font-bold text-text-main text-sm uppercase tracking-wider">Selected Questions</h2>
              <span class="font-number text-primary font-bold">{{ selectedCount }}</span>
            </div>
            <div v-if="selectedQuestions.length === 0" class="p-5 text-center">
              <p class="text-text-muted text-sm">No questions selected.</p>
            </div>
            <div v-else class="divide-y divide-gray-50 max-h-[420px] overflow-y-auto">
              <div v-for="(question, index) in selectedQuestions" :key="question.id" class="p-4 flex items-start gap-3">
                <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-number text-xs font-bold flex items-center justify-center shrink-0">
                  {{ index + 1 }}
                </span>
                <div class="min-w-0 flex-1">
                  <p class="text-xs text-text-main font-medium leading-snug line-clamp-2">{{ stripHtml(question.question_text) }}</p>
                  <p class="text-[10px] text-text-muted mt-1">
                    {{ question.topic || 'Untitled topic' }} | +{{ effectiveMarks(question) }}
                    <span v-if="effectiveNegativeMarks(question) > 0"> / -{{ effectiveNegativeMarks(question) }}</span>
                  </p>
                </div>
                <button type="button" @click="removeQuestion(question.id)"
                        class="text-danger hover:bg-danger/5 rounded-lg px-2 py-1 text-xs font-semibold">
                  Remove
                </button>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-2.5">
            <button type="submit" :disabled="form.processing"
                    class="w-full flex items-center justify-center gap-2 bg-primary text-white py-3 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60 shadow-sm">
              <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              {{ form.processing ? 'Saving...' : primaryActionLabel }}
            </button>

            <button v-if="form.status !== 'draft'" type="button" :disabled="form.processing" @click="saveAsDraft"
                    class="w-full py-2.5 rounded-xl text-sm font-semibold text-text-main bg-gray-100 hover:bg-gray-200 transition-colors">
              Save as Draft
            </button>
            <button v-if="form.status !== 'published'" type="button" :disabled="form.processing" @click="saveAndPublish"
                    class="w-full py-2.5 rounded-xl text-sm font-semibold text-accent border-2 border-accent/20 hover:bg-accent/5 transition-colors">
              Save & Publish
            </button>

            <Link :href="route('admin.exams.index')"
                  class="w-full flex items-center justify-center py-2.5 rounded-xl text-sm font-semibold text-text-muted hover:bg-gray-100 transition-colors">
              Cancel
            </Link>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
  mode: { type: String, required: true },
  exam: { type: Object, default: null },
  subjects: { type: Array, required: true },
  classLevels: { type: Array, required: true },
  statuses: { type: Object, required: true },
  scoringModes: { type: Object, required: true },
  difficulties: { type: Object, required: true },
  availableQuestions: { type: Object, required: true },
  questionFilters: { type: Object, default: () => ({ search: '', difficulty: '' }) },
  assignedQuestions: { type: Array, default: () => [] },
});

const form = useForm({
  subject_id: props.exam?.subject_id || '',
  class_level_id: props.exam?.class_level_id || '',
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
  question_ids: [...(props.exam?.question_ids || [])],
});

const questionFilter = ref({
  search: props.questionFilters.search || '',
  difficulty: props.questionFilters.difficulty || '',
});

const selectedQuestionCache = ref([...(props.assignedQuestions || [])]);
const isLoadingQuestions = ref(false);
let questionLoadTimer = null;

const pageRoute = computed(() => props.mode === 'edit'
  ? route('admin.exams.edit', props.exam.id)
  : route('admin.exams.create'));

const primaryActionLabel = computed(() => props.mode === 'edit' ? 'Update Exam' : 'Create Exam');
const selectedIds = computed(() => (form.question_ids || [])
  .map((id) => Number(id))
  .filter((id) => Number.isFinite(id)));
const selectedIdSet = computed(() => new Set(selectedIds.value));
const selectedCount = computed(() => selectedIds.value.length);
const canLoadQuestions = computed(() => Boolean(form.subject_id && form.class_level_id));
const availableQuestionRows = computed(() => props.availableQuestions?.data || []);

const selectedQuestions = computed(() => selectedIds.value
  .map((id) => selectedQuestionCache.value.find((question) => Number(question.id) === id))
  .filter(Boolean));

const totalMarks = computed(() => selectedQuestions.value
  .reduce((sum, question) => sum + Number(effectiveMarks(question) || 0), 0)
  .toLocaleString('en-IN'));

function cacheQuestion(question) {
  if (!question || selectedQuestionCache.value.some((cached) => Number(cached.id) === Number(question.id))) {
    return;
  }

  selectedQuestionCache.value.push(question);
}

watch(
  () => availableQuestionRows.value,
  (questions = []) => {
    questions.forEach(cacheQuestion);
  },
  { immediate: true },
);

watch(
  () => [form.subject_id, form.class_level_id],
  ([newSubject, newClass], [oldSubject, oldClass]) => {
    if (newSubject === oldSubject && newClass === oldClass) {
      return;
    }

    form.question_ids = [];
    selectedQuestionCache.value = [];
    form.clearErrors('question_ids');

    if (newSubject && newClass) {
      queueQuestionLoad();
    }
  },
);

const submit = () => {
  if (props.mode === 'edit') {
    form.put(route('admin.exams.update', props.exam.id), { preserveScroll: true });
    return;
  }

  form.post(route('admin.exams.store'), { preserveScroll: true });
};

const saveAsDraft = () => {
  form.status = 'draft';
  submit();
};

const saveAndPublish = () => {
  form.status = 'published';
  submit();
};

const queueQuestionLoad = () => {
  if (questionLoadTimer) {
    clearTimeout(questionLoadTimer);
  }

  questionLoadTimer = setTimeout(() => {
    loadQuestions();
  }, 150);
};

const loadQuestions = () => {
  if (!canLoadQuestions.value) return;

  isLoadingQuestions.value = true;

  router.get(pageRoute.value, {
    question_subject_id: form.subject_id,
    question_class_level_id: form.class_level_id,
    question_search: questionFilter.value.search,
    question_difficulty: questionFilter.value.difficulty,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['availableQuestions', 'questionFilters'],
    onFinish: () => {
      isLoadingQuestions.value = false;
    },
  });
};

const clearQuestionFilters = () => {
  questionFilter.value = { search: '', difficulty: '' };
  loadQuestions();
};

const isSelected = (questionId) => selectedIdSet.value.has(Number(questionId));

const toggleQuestion = (question) => {
  const questionId = Number(question?.id);

  if (!Number.isFinite(questionId)) {
    return;
  }

  form.clearErrors('question_ids');

  if (isSelected(questionId)) {
    removeQuestion(questionId);
    return;
  }

  cacheQuestion(question);
  form.question_ids = [...selectedIds.value, questionId];
};

const removeQuestion = (questionId) => {
  const idToRemove = Number(questionId);

  form.clearErrors('question_ids');
  form.question_ids = selectedIds.value.filter((id) => id !== idToRemove);
};

const effectiveMarks = (question) => Number(
  form.scoring_mode === 'uniform'
    ? form.marks_per_question
    : question.marks,
);

const effectiveNegativeMarks = (question) => {
  if (!form.negative_marking_enabled) return 0;

  return Number(
    form.scoring_mode === 'uniform'
      ? form.negative_marks_per_question
      : question.negative_marks,
  );
};

const stripHtml = (html) => {
  const tmp = document.createElement('div');
  tmp.innerHTML = html || '';
  return tmp.textContent || tmp.innerText || '';
};

const difficultyClass = (difficulty) => ({
  easy: 'bg-green-100 text-green-700',
  medium: 'bg-amber-100 text-amber-700',
  hard: 'bg-red-100 text-red-700',
}[difficulty] || 'bg-gray-100 text-gray-600');

const statusClass = (status) => ({
  draft: 'bg-gray-100 text-text-muted',
  published: 'bg-green-100 text-success',
  archived: 'bg-amber-100 text-amber-700',
}[status] || 'bg-gray-100 text-text-muted');
</script>
