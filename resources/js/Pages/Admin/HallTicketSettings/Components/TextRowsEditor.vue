<script setup>
/**
 * Admin-typed rows printed in the candidate details grid (e.g. "Reporting centre:
 * Online"). Same text on every ticket. Each row also appears in the Ticket fields list
 * as `text:{id}` so it can be switched off, relabelled and positioned like any field.
 */
import { confirmDialog } from '@/composables/useConfirm';

const rows = defineModel({ type: Array, required: true });
const emit = defineEmits(['added', 'removed']);
const props = defineProps({
    max: { type: Number, default: 20 },
    disabled: { type: Boolean, default: false },
});

const newId = () => Array.from(crypto.getRandomValues(new Uint8Array(6)), (b) => 'abcdefghijklmnopqrstuvwxyz0123456789'[b % 36]).join('');

const add = () => {
    if (rows.value.length >= props.max) return;
    const row = { id: newId(), label: '', value: '' };
    rows.value.push(row);
    emit('added', row);
};

const remove = async (i) => {
    const row = rows.value[i];
    if ((row.label || row.value) && !(await confirmDialog({
        title: `Delete “${row.label || 'this row'}”?`,
        message: 'It disappears from every hall ticket after you save.',
        confirmText: 'Delete',
        tone: 'danger',
    }))) return;
    rows.value.splice(i, 1);
    emit('removed', row);
};
</script>

<template>
    <div>
        <div v-for="(row, i) in rows" :key="row.id" class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)_auto] gap-2 items-start mb-2">
            <input v-model="row.label" type="text" maxlength="60" :disabled="disabled" placeholder="Label, e.g. Reporting centre" class="field" :aria-label="`Text row ${i + 1} label`" />
            <input v-model="row.value" type="text" maxlength="200" :disabled="disabled" placeholder="Printed value, e.g. Online — from home" class="field" :aria-label="`Text row ${i + 1} value`" />
            <button v-if="!disabled" type="button" class="h-10 px-3 rounded-xl text-sm font-semibold text-danger hover:bg-red-50" @click="remove(i)">Delete</button>
        </div>
        <p v-if="!rows.length" class="rounded-xl border border-dashed border-gray-200 py-5 text-center text-sm text-text-muted">No text rows yet.</p>
        <p v-if="rows.some((r) => !r.label.trim())" class="text-[11px] text-amber-700 mt-1">Rows without a label are dropped on save.</p>
        <button v-if="!disabled" type="button" class="btn-add mt-2" :disabled="rows.length >= max" @click="add">+ Add text row</button>
    </div>
</template>

<style scoped>
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main disabled:opacity-60; }
.btn-add { @apply text-sm font-semibold px-3.5 py-2 rounded-xl border border-dashed border-primary/40 text-primary hover:bg-primary/5 disabled:opacity-40; }
</style>
