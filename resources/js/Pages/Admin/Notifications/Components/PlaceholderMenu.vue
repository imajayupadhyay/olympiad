<template>
  <div class="relative inline-block" ref="root">
    <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-primary bg-white hover:border-primary transition-colors"
            :aria-expanded="open" aria-haspopup="menu" @click="open = !open">
      <span class="font-number">{{ '{ }' }}</span> {{ label }}
    </button>
    <div v-if="open" role="menu" class="absolute z-30 mt-1 w-72 max-h-80 overflow-y-auto bg-white border border-gray-200 rounded-xl shadow-lg p-1.5"
         :class="align === 'right' ? 'right-0' : 'left-0'">
      <template v-for="group in groups" :key="group.key">
        <p class="px-2 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-text-muted">{{ group.label }}</p>
        <p v-if="group.key === 'exam' && !hasExam" class="px-2 pb-1 text-[11px] text-gold-dark">Choose an “Exam for placeholders” to use these.</p>
        <button v-for="p in group.items" :key="p.key" type="button" role="menuitem"
                class="w-full text-left px-2 py-1.5 rounded-lg hover:bg-gray-50 flex items-center justify-between gap-2 disabled:opacity-40 disabled:pointer-events-none"
                :disabled="p.requires_exam && !hasExam" @click="pick(p)">
          <span class="text-xs text-text-main">{{ p.label }}</span>
          <code class="text-[10px] text-text-muted truncate max-w-[120px]">{{ p.token }}</code>
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
  placeholders: { type: Array, required: true },
  hasExam: { type: Boolean, default: false },
  label: { type: String, default: 'Insert field' },
  align: { type: String, default: 'left' },
  mode: { type: String, default: 'token' }, // 'token' emits {{key}}, 'key' emits key
});
const emit = defineEmits(['insert']);

const open = ref(false);
const root = ref(null);
const groups = computed(() => [
  { key: 'student', label: 'Student' },
  { key: 'exam', label: 'Exam' },
  { key: 'portal', label: 'Portal' },
].map((g) => ({ ...g, items: props.placeholders.filter((p) => p.group === g.key) })));

const pick = (p) => {
  emit('insert', props.mode === 'key' ? p.key : p.token);
  open.value = false;
};

const close = (e) => { if (root.value && !root.value.contains(e.target)) open.value = false; };
onMounted(() => document.addEventListener('mousedown', close));
onBeforeUnmount(() => document.removeEventListener('mousedown', close));
</script>
