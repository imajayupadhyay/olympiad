<template>
  <AdminLayout title="Homepage CMS" subtitle="Manage the public homepage section by section">
    <div class="space-y-6">
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
          <p class="text-text-muted text-xs font-semibold uppercase tracking-wider">Sections</p>
          <p class="font-number text-2xl font-bold text-primary mt-1">{{ stats.total }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
          <p class="text-text-muted text-xs font-semibold uppercase tracking-wider">Visible</p>
          <p class="font-number text-2xl font-bold text-success mt-1">{{ stats.enabled }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
          <p class="text-text-muted text-xs font-semibold uppercase tracking-wider">Hidden</p>
          <p class="font-number text-2xl font-bold text-accent mt-1">{{ stats.disabled }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
          <p class="text-text-muted text-xs font-semibold uppercase tracking-wider">Last Updated</p>
          <p class="font-number text-sm font-bold text-text-main mt-2">{{ formatDate(stats.last_updated) }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <aside class="xl:col-span-3">
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 xl:sticky xl:top-24">
            <button
              v-for="section in sections"
              :key="section.key"
              type="button"
              @click="selectSection(section.key)"
              class="w-full text-left rounded-xl px-3 py-3 mb-1 transition-colors border"
              :class="selectedKey === section.key ? 'bg-primary text-white border-primary shadow-sm' : 'bg-white text-text-main border-transparent hover:bg-gray-50'"
            >
              <span class="flex items-center justify-between gap-3">
                <span class="font-semibold text-sm">{{ section.title }}</span>
                <span
                  class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                  :class="selectedKey === section.key
                    ? 'bg-white/15 text-white'
                    : section.is_enabled ? 'bg-success/10 text-success' : 'bg-accent/10 text-accent'"
                >
                  {{ section.is_enabled ? 'ON' : 'OFF' }}
                </span>
              </span>
              <span class="block text-[11px] mt-1" :class="selectedKey === section.key ? 'text-white/65' : 'text-text-muted'">
                {{ section.key }}
              </span>
            </button>
          </div>
        </aside>

        <section class="xl:col-span-9">
          <form v-if="selectedSection" @submit.prevent="submit" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
              <div>
                <h2 class="font-heading font-bold text-text-main text-lg">{{ selectedSection.title }}</h2>
                <p class="text-text-muted text-xs mt-1">
                  Updated {{ formatDate(selectedSection.updated_at) }}
                  <span v-if="selectedSection.updater">by {{ selectedSection.updater.name }}</span>
                </p>
              </div>
              <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-sm font-semibold text-text-main cursor-pointer">
                  <input v-model="form.is_enabled" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-accent focus:ring-accent" />
                  Visible on homepage
                </label>
                <a href="/" target="_blank" class="bg-gray-100 text-text-main px-4 py-2 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Preview</a>
              </div>
            </div>

            <div class="p-5 space-y-6">
              <div v-if="schemaForSelected.description" class="bg-bg border border-gray-100 rounded-xl p-4">
                <p class="text-sm text-text-muted">{{ schemaForSelected.description }}</p>
              </div>

              <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <template v-for="field in schemaForSelected.fields" :key="field.key">
                  <div v-if="field.type !== 'list' && field.type !== 'json'" :class="field.full ? 'lg:col-span-2' : ''">
                    <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">{{ field.label }}</label>
                    <textarea
                      v-if="field.type === 'textarea'"
                      v-model="form.content[field.key]"
                      rows="4"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 resize-y"
                    ></textarea>
                    <input
                      v-else
                      v-model="form.content[field.key]"
                      :type="field.type === 'number' ? 'number' : 'text'"
                      :step="field.type === 'number' ? '1' : undefined"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
                    />
                  </div>

                  <div v-else-if="field.type === 'list'" class="lg:col-span-2">
                    <ContentListField :field="field" :items="listFor(field.key)" :icon-options="iconOptions" />
                  </div>

                  <div v-else class="lg:col-span-2 border border-gray-100 rounded-2xl p-4">
                    <div class="flex items-start justify-between gap-3 mb-3">
                      <div>
                        <h3 class="font-heading font-bold text-text-main text-sm">{{ field.label }}</h3>
                        <p class="text-text-muted text-xs mt-1">{{ field.hint }}</p>
                      </div>
                      <button type="button" @click="applyJson(field.key)" class="bg-gray-100 text-text-main px-3 py-2 rounded-xl text-xs font-semibold hover:bg-gray-200 transition-colors">
                        Apply JSON
                      </button>
                    </div>
                    <textarea
                      v-model="jsonDrafts[field.key]"
                      rows="12"
                      spellcheck="false"
                      class="font-mono w-full px-4 py-3 border rounded-xl text-xs leading-relaxed focus:outline-none focus:ring-1 resize-y"
                      :class="jsonErrors[field.key] ? 'border-danger bg-danger/5 focus:border-danger focus:ring-danger/20' : 'border-gray-200 focus:border-primary focus:ring-primary/20'"
                    ></textarea>
                    <p v-if="jsonErrors[field.key]" class="text-danger text-xs mt-2">{{ jsonErrors[field.key] }}</p>
                  </div>
                </template>
              </div>
            </div>

            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <button type="button" @click="resetSection" class="text-danger text-sm font-semibold hover:underline w-fit">
                Restore this section to defaults
              </button>
              <div class="flex items-center gap-3">
                <span v-if="saved" class="text-success text-sm font-semibold">Saved</span>
                <button type="submit" :disabled="form.processing" class="bg-primary text-white px-6 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60">
                  {{ form.processing ? 'Saving...' : 'Save Section' }}
                </button>
              </div>
            </div>
          </form>
        </section>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ContentListField from './Components/ContentListField.vue';

// Icon keys understood by the homepage (mirrors iconMap in Pages/Public/Home/Index.vue).
// Shown with an emoji preview so the admin picks from a list instead of typing.
const ICON_EMOJI = {
  Trophy: '🏆', Students: '🎓', Medal: '🏅', Calendar: '📅', Target: '🎯', Certificate: '📜',
  Brain: '🧠', Language: '🗣️', Rocket: '🚀', Math: '📐', Science: '🔬', World: '🌍',
  Book: '📖', Hindi: 'अ', Civics: '🏛️', Computer: '💻', Puzzle: '🧩', Art: '🎨',
  Register: '📝', Lightning: '⚡', Gift: '🎁', Books: '📚', Lock: '🔒',
};
const iconOptions = Object.keys(ICON_EMOJI).map((key) => ({ value: key, label: `${ICON_EMOJI[key]}  ${key}` }));

const props = defineProps({
  sections: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({}) },
  defaults: { type: Object, default: () => ({}) },
});

const clone = (value) => JSON.parse(JSON.stringify(value ?? {}));
const selectedKey = ref(props.sections[0]?.key || '');
const selectedSection = computed(() => props.sections.find((section) => section.key === selectedKey.value));
const saved = ref(false);
const jsonDrafts = reactive({});
const jsonErrors = reactive({});

const schemas = {
  seo: {
    description: 'Controls the browser title and meta description used for the public homepage.',
    fields: [
      { key: 'page_title', label: 'Page Title', full: true },
      { key: 'meta_description', label: 'Meta Description', type: 'textarea', full: true },
    ],
  },
  marquee: {
    fields: [
      { key: 'items', label: 'Announcement Items', type: 'list', fields: [
        { key: 'icon', label: 'Icon' },
        { key: 'text', label: 'Text' },
        { key: 'hl', label: 'Highlighted Text' },
      ] },
    ],
  },
  hero: {
    description: 'The first viewport: headline, CTAs, trust copy, rank card, progress bars and floating chips.',
    fields: [
      { key: 'badge_text', label: 'Badge Text' },
      { key: 'badge_highlight', label: 'Badge Highlight' },
      { key: 'headline_line_1', label: 'Headline Line 1' },
      { key: 'headline_highlight', label: 'Underlined Headline Text' },
      { key: 'headline_line_2', label: 'Headline Line 2' },
      { key: 'headline_italic', label: 'Italic Headline Word' },
      { key: 'lede', label: 'Lead Paragraph', type: 'textarea', full: true },
      { key: 'primary_cta_label', label: 'Primary Button Label' },
      { key: 'primary_cta_url', label: 'Primary Button URL' },
      { key: 'secondary_cta_label', label: 'Secondary Button Label' },
      { key: 'secondary_cta_url', label: 'Secondary Button URL' },
      { key: 'trust_text', label: 'Trust Text' },
      { key: 'rating_text', label: 'Rating Text' },
      { key: 'rank_number', label: 'Rank Number' },
      { key: 'rank_label', label: 'Rank Label' },
      { key: 'winner_name', label: 'Winner Name' },
      { key: 'winner_meta', label: 'Winner Meta' },
      { key: 'bars', label: 'Rank Card Bars', type: 'list', fields: [
        { key: 'label', label: 'Label' },
        { key: 'val', label: 'Value' },
        { key: 'w', label: 'Width, e.g. 98%' },
      ] },
      { key: 'chips', label: 'Floating Chips', type: 'list', hint: 'Keep this to three items for the current design.', fields: [
        { key: 'icon', label: 'Icon' },
        { key: 'title', label: 'Title' },
        { key: 'subtitle', label: 'Subtitle' },
        { key: 'color', label: 'Icon Background' },
      ] },
    ],
  },
  stats: {
    fields: [
      { key: 'items', label: 'Stats', type: 'list', fields: [
        { key: 'label', label: 'Label' },
        { key: 'target', label: 'Target Number', type: 'number' },
        { key: 'suffix', label: 'Suffix' },
      ] },
    ],
  },
  subjects: {
    description: 'Edit the top copy, then manage the subject tabs (groups) and the subject cards inside each group — add, remove and reorder without touching any code.',
    fields: [
      { key: 'eyebrow', label: 'Eyebrow' },
      { key: 'title', label: 'Title' },
      { key: 'description', label: 'Description', type: 'textarea', full: true },
      { key: 'groups', label: 'Subject Groups', type: 'list', item_label: 'Group',
        hint: 'Each group is a tab on the homepage. Give it a label, an icon and a unique key, then add its subject cards.',
        fields: [
          { key: 'label', label: 'Group Label (tab)' },
          { key: 'key', label: 'Group Key (unique, lowercase, no spaces)' },
          { key: 'icon', label: 'Group Icon', type: 'select', options: 'icons' },
          { key: 'subjects', label: 'Subjects in this group', type: 'list', item_label: 'Subject',
            fields: [
              { key: 'name', label: 'Subject Name' },
              { key: 'icon', label: 'Icon', type: 'select', options: 'icons' },
              { key: 'range', label: 'Class Range (e.g. Class 1-12)' },
              { key: 'color', label: 'Accent Color', type: 'color' },
              { key: 'desc', label: 'Short Description', type: 'textarea', full: true },
            ] },
        ] },
    ],
  },
  how_it_works: {
    fields: [
      { key: 'eyebrow', label: 'Eyebrow' },
      { key: 'title', label: 'Title', full: true },
      { key: 'steps', label: 'Steps', type: 'list', fields: [
        { key: 'icon', label: 'Icon' },
        { key: 'title', label: 'Title' },
        { key: 'desc', label: 'Description', type: 'textarea', full: true },
      ] },
    ],
  },
  exams: {
    description: 'Exam cards are pulled automatically from published exams. These fields control the wrapper copy and empty state.',
    fields: [
      { key: 'eyebrow', label: 'Eyebrow' },
      { key: 'title', label: 'Title' },
      { key: 'view_all_label', label: 'View All Button Label' },
      { key: 'empty_title', label: 'Empty State Title' },
      { key: 'empty_description', label: 'Empty State Description', type: 'textarea', full: true },
    ],
  },
  rewards: {
    fields: [
      { key: 'eyebrow', label: 'Eyebrow' },
      { key: 'title', label: 'Title' },
      { key: 'description', label: 'Description', type: 'textarea', full: true },
      { key: 'tiers', label: 'Reward Tiers JSON', type: 'json', hint: 'Array with silver, gold and bronze keys. Each tier supports label, headline and perks[].' },
    ],
  },
  testimonials: {
    fields: [
      { key: 'eyebrow', label: 'Eyebrow' },
      { key: 'title', label: 'Title' },
      { key: 'items', label: 'Testimonials', type: 'list', fields: [
        { key: 'quote', label: 'Quote', type: 'textarea', full: true },
        { key: 'name', label: 'Name' },
        { key: 'role', label: 'Role / Location' },
        { key: 'initials', label: 'Initials' },
        { key: 'color', label: 'Avatar Color' },
      ] },
    ],
  },
  faq: {
    fields: [
      { key: 'eyebrow', label: 'Eyebrow' },
      { key: 'title', label: 'Title' },
      { key: 'aside_title', label: 'Support Card Title' },
      { key: 'aside_description', label: 'Support Card Description', type: 'textarea', full: true },
      { key: 'contact_label', label: 'Contact Button Label' },
      { key: 'contact_url', label: 'Contact Button URL' },
      { key: 'support_email', label: 'Support Email' },
      { key: 'support_phone', label: 'Support Phone / Hours' },
      { key: 'items', label: 'Questions', type: 'list', fields: [
        { key: 'q', label: 'Question', full: true },
        { key: 'a', label: 'Answer', type: 'textarea', full: true },
      ] },
    ],
  },
  register: {
    fields: [
      { key: 'eyebrow', label: 'Eyebrow' },
      { key: 'title', label: 'Title' },
      { key: 'description', label: 'Description', type: 'textarea', full: true },
      { key: 'form_title', label: 'Form Title' },
      { key: 'form_description', label: 'Form Description' },
      { key: 'button_label', label: 'Button Label' },
      { key: 'note', label: 'Legal Note', full: true },
      { key: 'success_title', label: 'Success Title' },
      { key: 'success_description', label: 'Success Description', type: 'textarea', full: true },
      { key: 'features', label: 'Feature Bullets', type: 'list', fields: [
        { key: 'icon', label: 'Icon' },
        { key: 'title', label: 'Title' },
        { key: 'sub', label: 'Subtitle' },
      ] },
      { key: 'subject_options', label: 'Subject Options JSON', type: 'json', hint: 'Array of subject names shown in the registration form subject dropdown.' },
    ],
  },
  final_cta: {
    fields: [
      { key: 'title', label: 'Title', full: true },
      { key: 'description', label: 'Description', type: 'textarea', full: true },
      { key: 'primary_label', label: 'Primary Button Label' },
      { key: 'primary_url', label: 'Primary Button URL' },
      { key: 'secondary_label', label: 'Secondary Button Label' },
      { key: 'secondary_url', label: 'Secondary Button URL' },
    ],
  },
  footer: {
    fields: [
      { key: 'description', label: 'Brand Description', type: 'textarea', full: true },
      { key: 'copyright', label: 'Copyright Text', full: true },
      { key: 'socials', label: 'Social Links', type: 'list', fields: [
        { key: 'label', label: 'Label' },
        { key: 'url', label: 'URL' },
      ] },
      { key: 'columns', label: 'Footer Columns JSON', type: 'json', hint: 'Array of columns. Each column has title and links[]. Each link has label and url.' },
    ],
  },
};

const schemaForSelected = computed(() => schemas[selectedKey.value] || { fields: [] });
const form = useForm({
  is_enabled: true,
  content: {},
});

const loadSection = () => {
  if (!selectedSection.value) return;
  form.clearErrors();
  form.is_enabled = !!selectedSection.value.is_enabled;
  form.content = clone(selectedSection.value.content);
  syncJsonDrafts();
};

const syncJsonDrafts = () => {
  Object.keys(jsonDrafts).forEach((key) => delete jsonDrafts[key]);
  Object.keys(jsonErrors).forEach((key) => delete jsonErrors[key]);
  schemaForSelected.value.fields
    .filter((field) => field.type === 'json')
    .forEach((field) => {
      jsonDrafts[field.key] = JSON.stringify(form.content[field.key] ?? [], null, 2);
      jsonErrors[field.key] = '';
    });
};

watch(selectedKey, loadSection, { immediate: true });

const selectSection = (key) => {
  selectedKey.value = key;
  saved.value = false;
};

const listFor = (key) => {
  if (!Array.isArray(form.content[key])) form.content[key] = [];
  return form.content[key];
};

const applyJson = (key) => {
  try {
    form.content[key] = JSON.parse(jsonDrafts[key] || '[]');
    jsonErrors[key] = '';
  } catch (error) {
    jsonErrors[key] = error.message;
  }
};

const applyAllJson = () => {
  let valid = true;
  schemaForSelected.value.fields
    .filter((field) => field.type === 'json')
    .forEach((field) => {
      applyJson(field.key);
      if (jsonErrors[field.key]) valid = false;
    });
  return valid;
};

const submit = () => {
  if (!selectedSection.value || !applyAllJson()) return;

  form.put(route('admin.content.homepage.update', selectedSection.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      saved.value = true;
      setTimeout(() => (saved.value = false), 2500);
    },
  });
};

const resetSection = () => {
  if (!selectedSection.value || !confirm(`Restore "${selectedSection.value.title}" to defaults?`)) return;

  router.post(route('admin.content.homepage.reset', selectedSection.value.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      form.content = clone(props.defaults[selectedSection.value.key] || {});
      form.is_enabled = true;
      syncJsonDrafts();
    },
  });
};

const formatDate = (date) => {
  if (!date) return 'Not yet';
  return new Date(date).toLocaleDateString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};
</script>
