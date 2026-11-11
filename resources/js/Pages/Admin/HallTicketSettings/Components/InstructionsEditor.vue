<script setup>
import { nextTick, ref } from 'vue';
import { confirmDialog } from '@/composables/useConfirm';
import { useDragSort } from './useDragSort';

const items = defineModel({ type: Array, required: true }); // list of strings
const props = defineProps({
    defaults: { type: Array, default: () => [] },
    max: { type: Number, default: 25 },
    maxLength: { type: Number, default: 400 },
    disabled: { type: Boolean, default: false },
});

const { over, dragging, move, handle, row } = useDragSort(() => items.value);
const boxes = ref([]);

const add = async () => {
    if (items.value.length >= props.max) return;
    items.value.push('');
    await nextTick();
    boxes.value[items.value.length - 1]?.focus();
};

const remove = async (i) => {
    if (items.value[i]?.trim() && !(await confirmDialog({
        title: 'Delete this instruction?',
        message: `“${items.value[i].slice(0, 120)}${items.value[i].length > 120 ? '…' : ''}”`,
        confirmText: 'Delete',
        tone: 'danger',
    }))) return;
    items.value.splice(i, 1);
};

const restore = async () => {
    if (!(await confirmDialog({
        title: 'Restore the default instructions?',
        message: 'Your current list is replaced by the 9 default instructions. Nothing is saved until you press Save.',
        confirmText: 'Restore defaults',
        tone: 'warning',
    }))) return;
    items.value.splice(0, items.value.length, ...props.defaults);
};

const autosize = (e) => { e.target.style.height = 'auto'; e.target.style.height = e.target.scrollHeight + 'px'; };
</script>

<template>
    <div>
        <ol class="space-y-2">
            <li v-for="(text, i) in items" :key="i" v-bind="disabled ? {} : row(i)"
                class="flex items-start gap-2 rounded-xl border bg-white px-2.5 py-2 transition-colors"
                :class="[over === i && dragging !== i ? 'border-accent ring-2 ring-accent/20' : 'border-gray-200', dragging === i ? 'opacity-50' : '']">
                <button v-if="!disabled" type="button" v-bind="handle(i)" class="mt-2 cursor-grab active:cursor-grabbing text-gray-400 hover:text-text-main touch-none" aria-label="Drag to reorder">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
                </button>
                <span class="mt-2 w-6 shrink-0 text-right font-number text-xs font-bold text-text-muted">{{ i + 1 }}.</span>
                <div class="flex-1 min-w-0">
                    <textarea :ref="(el) => (boxes[i] = el)" v-model="items[i]" :rows="Math.max(1, Math.ceil((text.length || 1) / 58))" :maxlength="maxLength" :disabled="disabled"
                              class="instr w-full resize-none border-0 bg-transparent px-1 py-1.5 text-sm text-text-main focus:outline-none focus:ring-0"
                              :aria-label="`Instruction ${i + 1}`" placeholder="Type the instruction…" @input="autosize" @focus="autosize"></textarea>
                    <p v-if="text.length > maxLength * 0.8" class="text-[10px] text-right" :class="text.length >= maxLength ? 'text-danger' : 'text-text-muted'">{{ text.length }}/{{ maxLength }}</p>
                </div>
                <div v-if="!disabled" class="flex items-center gap-0.5 mt-1">
                    <button type="button" class="ic" :disabled="i === 0" aria-label="Move up" @click="move(i, i - 1)">▲</button>
                    <button type="button" class="ic" :disabled="i === items.length - 1" aria-label="Move down" @click="move(i, i + 1)">▼</button>
                    <button type="button" class="ic hover:!text-danger" aria-label="Delete instruction" @click="remove(i)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </li>
        </ol>
        <p v-if="!items.length" class="rounded-xl border border-dashed border-gray-200 py-6 text-center text-sm text-text-muted">No instructions. The section is hidden on the ticket.</p>

        <div v-if="!disabled" class="flex flex-wrap items-center gap-2 mt-3">
            <button type="button" class="btn-add" :disabled="items.length >= max" @click="add">+ Add instruction</button>
            <button type="button" class="text-xs font-semibold text-text-muted hover:text-primary" @click="restore">Restore defaults</button>
            <span class="ml-auto text-[11px] text-text-muted">{{ items.length }}/{{ max }} · empty lines are dropped on save</span>
        </div>
    </div>
</template>

<style scoped>
.instr { field-sizing: content; min-height: 2.1rem; }
.ic { @apply w-7 h-7 grid place-items-center rounded-md text-[9px] text-text-muted hover:bg-gray-100 hover:text-text-main disabled:opacity-30; }
.btn-add { @apply text-sm font-semibold px-3.5 py-2 rounded-xl border border-dashed border-primary/40 text-primary hover:bg-primary/5 disabled:opacity-40; }
</style>
