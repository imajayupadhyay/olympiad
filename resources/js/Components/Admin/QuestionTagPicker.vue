<template>
  <div class="tag-picker-root">
    <div class="flex items-center justify-between mb-1.5">
      <label class="block text-xs font-semibold text-text-muted">
        Topics / Tags <span class="font-normal">(optional)</span>
      </label>
      <span v-if="ready && !loading" class="text-[10px] text-gray-400">
        {{ available.length }} {{ available.length === 1 ? 'tag' : 'tags' }} for {{ scopeLabel }}
      </span>
    </div>

    <!-- Locked until the scope exists -->
    <div v-if="!ready"
         class="rounded-xl border border-dashed border-gray-200 bg-gray-50/60 px-3 py-3 text-xs text-text-muted">
      {{ lockedHint }}
    </div>

    <div v-else>
      <!-- Selected chips -->
      <div v-if="selectedTags.length" class="flex flex-wrap gap-1.5 mb-2">
        <span v-for="tag in selectedTags" :key="tag.id"
              class="inline-flex items-center gap-1.5 bg-primary/10 text-primary text-xs font-semibold pl-2.5 pr-1.5 py-1 rounded-lg">
          {{ tag.name }}
          <span v-if="multipleClasses && tag.class_label" class="font-normal text-primary/60">· {{ tag.class_label }}</span>
          <button type="button" @click="deselect(tag.id)"
                  class="w-4 h-4 rounded-md flex items-center justify-center hover:bg-primary/20 transition-colors"
                  :title="`Remove ${tag.name}`">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </span>
      </div>

      <!-- Search / add -->
      <div class="relative">
        <input
          v-model="query"
          type="text"
          :disabled="loading || creating"
          :placeholder="loading ? 'Loading tags…' : 'Search tags, or type a new one…'"
          class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary disabled:bg-gray-50"
          @focus="open = true"
          @keydown.enter.prevent="onEnter"
          @keydown.esc="open = false"
        />
        <svg v-if="loading || creating"
             class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin text-text-muted" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>

        <!-- Dropdown -->
        <div v-if="open && !loading"
             class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-y-auto">
          <button
            v-for="tag in matches" :key="tag.id"
            type="button"
            @click="select(tag.id)"
            class="w-full flex items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 transition-colors"
            :class="isSelected(tag.id) ? 'text-primary font-semibold' : 'text-text-main'"
          >
            <span class="truncate">{{ tag.name }}</span>
            <span class="shrink-0 flex items-center gap-2">
              <span v-if="multipleClasses && tag.class_label" class="text-[10px] text-text-muted">{{ tag.class_label }}</span>
              <svg v-if="isSelected(tag.id)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button
            v-if="canCreate"
            type="button"
            @click="createTag"
            class="w-full px-3 py-2 text-left text-sm text-accent font-semibold hover:bg-accent/5 transition-colors border-t border-gray-100"
          >
            + Add “{{ trimmedQuery }}” to {{ scopeLabel }}
          </button>

          <p v-if="!matches.length && !canCreate" class="px-3 py-2.5 text-xs text-text-muted">
            No tags yet for {{ scopeLabel }}. Type a name to create the first one.
          </p>
        </div>
      </div>

      <p v-if="error" class="text-danger text-xs mt-1.5">{{ error }}</p>
      <p v-else class="text-[11px] text-gray-400 mt-1.5">
        Tags belong to a subject and a class.
        <template v-if="multipleClasses">A new tag is created for all {{ classLevelIds.length }} selected classes.</template>
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  modelValue:    { type: Array,  default: () => [] },
  subjectId:     { type: [Number, String], default: '' },
  classLevelIds: { type: Array,  default: () => [] },
  subjects:      { type: Array,  default: () => [] },
  classLevels:   { type: Array,  default: () => [] },
  // Tags already attached to the question (edit page) — keeps the chips
  // labelled before the first fetch lands, and keeps a deactivated tag
  // attached instead of silently dropping it on save.
  initialTags:   { type: Array,  default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const available = ref([]);
const known     = ref(new Map(props.initialTags.map((tag) => [tag.id, tag])));
const query     = ref('');
const open      = ref(false);
const loading   = ref(false);
const creating  = ref(false);
const error     = ref('');

const numericClassIds = computed(() => props.classLevelIds.map(Number));
const ready = computed(() => !!props.subjectId && numericClassIds.value.length > 0);
const multipleClasses = computed(() => numericClassIds.value.length > 1);

const lockedHint = computed(() => {
  if (!props.subjectId && !numericClassIds.value.length) return 'Choose a subject and at least one class to tag this question.';
  if (!props.subjectId) return 'Choose a subject to see its tags for the selected class.';
  return 'Select at least one class to see its tags.';
});

const subjectName = computed(() => {
  const subject = props.subjects.find((s) => Number(s.id) === Number(props.subjectId));
  return subject?.name ?? 'this subject';
});

const scopeLabel = computed(() => {
  const labels = props.classLevels
    .filter((cl) => numericClassIds.value.includes(Number(cl.id)))
    .map((cl) => cl.label);

  if (!labels.length) return subjectName.value;
  if (labels.length === 1) return `${labels[0]} · ${subjectName.value}`;
  if (labels.length <= 3) return `${labels.join(', ')} · ${subjectName.value}`;
  return `${labels.length} classes · ${subjectName.value}`;
});

const remember = (tags) => {
  tags.forEach((tag) => known.value.set(tag.id, tag));
};

// Chips render from whatever we know about each selected id.
const selectedTags = computed(() => props.modelValue
  .map((id) => known.value.get(Number(id)))
  .filter(Boolean));

const isSelected = (id) => props.modelValue.map(Number).includes(Number(id));

const trimmedQuery = computed(() => query.value.trim().replace(/\s+/g, ' '));

const matches = computed(() => {
  const needle = trimmedQuery.value.toLowerCase();
  if (!needle) return available.value;
  return available.value.filter((tag) => tag.name.toLowerCase().includes(needle));
});

const exactMatch = computed(() => {
  const needle = trimmedQuery.value.toLowerCase();
  if (!needle) return null;
  // A name already covering every selected class needs no new tag.
  const hits = available.value.filter((tag) => tag.name.toLowerCase() === needle);
  return hits.length === numericClassIds.value.length ? hits : null;
});

const canCreate = computed(() => trimmedQuery.value.length > 0 && !exactMatch.value && !creating.value);

const select = (id) => {
  if (isSelected(id)) return deselect(id);
  emit('update:modelValue', [...props.modelValue, Number(id)]);
};

const deselect = (id) => {
  emit('update:modelValue', props.modelValue.filter((value) => Number(value) !== Number(id)));
};

const inScope = (tag) => tag
  && Number(tag.subject_id) === Number(props.subjectId)
  && numericClassIds.value.includes(Number(tag.class_level_id));

const pruneSelection = () => {
  const kept = props.modelValue.filter((id) => {
    const tag = known.value.get(Number(id));
    return available.value.some((t) => Number(t.id) === Number(id)) || inScope(tag);
  });

  if (kept.length !== props.modelValue.length) {
    emit('update:modelValue', kept);
  }
};

const fetchTags = async () => {
  if (!ready.value) {
    available.value = [];
    pruneSelection();
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    const { data } = await window.axios.get(route('admin.question-tags.index'), {
      params: { subject_id: props.subjectId, class_level_ids: numericClassIds.value },
    });
    available.value = data.tags ?? [];
    remember(available.value);
  } catch (e) {
    available.value = [];
    error.value = e.response?.status === 403
      ? 'You do not have permission to read question tags.'
      : 'Could not load tags. Check your connection and try again.';
  } finally {
    loading.value = false;
    pruneSelection();
  }
};

const createTag = async () => {
  const name = trimmedQuery.value;
  if (!name || creating.value) return;

  creating.value = true;
  error.value = '';

  try {
    const { data } = await window.axios.post(route('admin.question-tags.store'), {
      name,
      subject_id: props.subjectId,
      class_level_ids: numericClassIds.value,
    });

    available.value = data.tags ?? available.value;
    remember(available.value);

    const fresh = (data.created_ids ?? []).map(Number).filter((id) => !isSelected(id));
    if (fresh.length) emit('update:modelValue', [...props.modelValue, ...fresh]);

    query.value = '';
  } catch (e) {
    error.value = e.response?.data?.message
      || e.response?.data?.errors?.name?.[0]
      || 'Could not create that tag. Please try again.';
  } finally {
    creating.value = false;
  }
};

const onEnter = () => {
  if (matches.value.length === 1) return select(matches.value[0].id);
  if (canCreate.value) return createTag();
};

let fetchTimer = null;
watch(
  () => [props.subjectId, numericClassIds.value.join(',')].join('|'),
  () => {
    clearTimeout(fetchTimer);
    fetchTimer = setTimeout(fetchTags, 120);
  },
  { immediate: true },
);

// Close on an outside click rather than on blur: blur fires before the
// dropdown's own click handler and would swallow the selection.
const onDocumentClick = (event) => {
  if (!event.target.closest?.('.tag-picker-root')) open.value = false;
};

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick);
  clearTimeout(fetchTimer);
});
</script>
