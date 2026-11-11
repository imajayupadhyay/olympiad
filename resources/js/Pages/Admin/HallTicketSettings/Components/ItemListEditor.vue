<script setup>
/**
 * Ordered on/off list for ticket details or schedule columns: switch, custom label,
 * full-width toggle (details only) and drag / arrow reordering.
 */
import { computed, ref } from 'vue';
import ToggleSwitch from './ToggleSwitch.vue';
import { useDragSort } from './useDragSort';

const items = defineModel({ type: Array, required: true });
const props = defineProps({
    catalogue: { type: Object, required: true }, // key => { label, hint, group }
    withWide: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    noun: { type: String, default: 'field' },
});

const filter = ref('all');
const query = ref('');
const shown = computed(() => items.value
    .map((item, index) => ({ item, index }))
    .filter(({ item }) => filter.value === 'all' || (filter.value === 'on' ? item.enabled : !item.enabled))
    .filter(({ item }) => {
        const q = query.value.trim().toLowerCase();
        if (!q) return true;
        const meta = props.catalogue[item.key] ?? {};
        return [meta.label, item.label, meta.hint].filter(Boolean).some((v) => v.toLowerCase().includes(q));
    }));
const enabledCount = computed(() => items.value.filter((i) => i.enabled).length);
const canReorder = computed(() => !props.disabled && filter.value === 'all' && !query.value.trim());

const { over, dragging, move, handle, row } = useDragSort(() => items.value);
const setAll = (on) => items.value.forEach((i) => { i.enabled = on; });
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <div class="flex rounded-xl bg-gray-100 p-0.5 text-xs font-semibold" role="tablist" aria-label="Filter">
                <button v-for="f in [['all', 'All'], ['on', 'Shown'], ['off', 'Hidden']]" :key="f[0]" type="button" role="tab" :aria-selected="filter === f[0]"
                        class="px-3 py-1.5 rounded-lg transition-colors" :class="filter === f[0] ? 'bg-white shadow-sm text-text-main' : 'text-text-muted hover:text-text-main'"
                        @click="filter = f[0]">{{ f[1] }}</button>
            </div>
            <input v-model="query" type="search" :placeholder="`Search ${noun}s`" class="field !py-1.5 !w-44" />
            <span class="text-xs text-text-muted ml-auto"><b class="font-number text-text-main">{{ enabledCount }}</b> of {{ items.length }} shown</span>
            <template v-if="!disabled">
                <button type="button" class="mini" @click="setAll(true)">All on</button>
                <button type="button" class="mini" @click="setAll(false)">All off</button>
            </template>
        </div>
        <p v-if="!canReorder && !disabled" class="text-[11px] text-text-muted mb-2">Clear the filter and search to reorder.</p>

        <ul class="space-y-1.5">
            <li v-for="{ item, index } in shown" :key="item.key" v-bind="canReorder ? row(index) : {}"
                class="group flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border px-3 py-2.5 transition-colors"
                :class="[item.enabled ? 'bg-white border-gray-200' : 'bg-gray-50 border-gray-100',
                         over === index && dragging !== index ? 'border-accent ring-2 ring-accent/20' : '',
                         dragging === index ? 'opacity-50' : '']">
                <button v-if="canReorder" type="button" v-bind="handle(index)" class="cursor-grab active:cursor-grabbing text-gray-400 hover:text-text-main -ml-1 touch-none" :aria-label="`Drag to reorder ${catalogue[item.key]?.label}`">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
                </button>
                <ToggleSwitch v-model="item.enabled" :disabled="disabled" size="sm" :label="`Show ${catalogue[item.key]?.label}`" />
                <div class="min-w-0 flex-1 basis-40">
                    <p class="text-sm font-semibold truncate" :class="item.enabled ? 'text-text-main' : 'text-text-muted'">
                        {{ catalogue[item.key]?.label ?? item.key }}
                        <span v-if="catalogue[item.key]?.group && catalogue[item.key].group !== 'Student & exam'" class="ml-1 align-middle text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded"
                              :class="catalogue[item.key].group === 'Text row' ? 'bg-blue-50 text-royal' : 'bg-amber-50 text-amber-700'">{{ catalogue[item.key].group }}</span>
                    </p>
                    <p class="text-[11px] text-text-muted truncate">{{ catalogue[item.key]?.hint }}</p>
                </div>
                <input v-model="item.label" type="text" maxlength="60" :disabled="disabled || !item.enabled" :placeholder="catalogue[item.key]?.label"
                       class="field !py-1.5 !text-xs basis-40 grow sm:grow-0 sm:!w-48" :aria-label="`Label printed for ${catalogue[item.key]?.label}`" />
                <label v-if="withWide" class="flex items-center gap-1.5 text-[11px] font-semibold text-text-muted cursor-pointer select-none">
                    <ToggleSwitch v-model="item.wide" :disabled="disabled || !item.enabled" size="sm" label="Full width" />
                    Full row
                </label>
                <div v-if="canReorder" class="flex gap-0.5">
                    <button type="button" class="arrow" :disabled="index === 0" aria-label="Move up" @click="move(index, index - 1)">▲</button>
                    <button type="button" class="arrow" :disabled="index === items.length - 1" aria-label="Move down" @click="move(index, index + 1)">▼</button>
                </div>
            </li>
        </ul>
        <p v-if="!shown.length" class="text-sm text-text-muted text-center py-6">Nothing matches.</p>
    </div>
</template>

<style scoped>
.field { @apply w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main disabled:opacity-60; }
.mini { @apply text-[11px] font-semibold px-2 py-1 rounded-lg border border-gray-200 text-text-main hover:border-primary; }
.arrow { @apply w-6 h-6 rounded-md text-[9px] text-text-muted hover:bg-gray-100 hover:text-text-main disabled:opacity-30 disabled:hover:bg-transparent; }
</style>
