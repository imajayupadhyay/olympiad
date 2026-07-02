<script setup>
/**
 * Renders a repeatable list of items (Add / Remove), each item being a set of
 * child fields. A child field of type "list" is rendered by recursing into this
 * same component, so nested structures (e.g. subject groups -> subjects) get a
 * full form UI with no JSON. `items` is a reactive array owned by the parent;
 * push/splice mutate it in place so the parent form stays in sync.
 */
import ContentField from './ContentField.vue';
import ContentListField from './ContentListField.vue'; // self-reference for nesting

const props = defineProps({
    field:       { type: Object, required: true },
    items:       { type: Array, required: true },
    iconOptions: { type: Array, default: () => [] },
    level:       { type: Number, default: 0 },
});

const itemLabel = props.field.item_label || 'Item';

const blank = () => {
    const item = {};
    (props.field.fields || []).forEach((child) => {
        if (child.type === 'list') item[child.key] = [];
        else if (child.type === 'number') item[child.key] = 0;
        else item[child.key] = '';
    });
    return item;
};

const add = () => props.items.push(blank());
const remove = (index) => props.items.splice(index, 1);

// Ensure a nested list child is always an array before handing it to the child.
const childItems = (item, key) => {
    if (!Array.isArray(item[key])) item[key] = [];
    return item[key];
};
</script>

<template>
    <div class="border border-gray-100 rounded-2xl p-4" :class="level > 0 ? 'bg-white' : ''">
        <div class="flex items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="font-heading font-bold text-text-main text-sm">{{ field.label }}</h3>
                <p v-if="field.hint" class="text-text-muted text-xs mt-1">{{ field.hint }}</p>
            </div>
            <button type="button" @click="add" class="bg-primary text-white px-3 py-2 rounded-xl text-xs font-semibold hover:bg-primary-light transition-colors flex-none">
                Add {{ itemLabel }}
            </button>
        </div>

        <div class="space-y-3">
            <div
                v-for="(item, index) in items"
                :key="index"
                class="rounded-xl border border-gray-100 p-4"
                :class="level > 0 ? 'bg-gray-50/70' : 'bg-gray-50'"
            >
                <div class="flex items-center justify-between mb-3">
                    <span class="font-number text-xs font-bold text-text-muted">{{ itemLabel }} #{{ index + 1 }}</span>
                    <button type="button" @click="remove(index)" class="text-danger text-xs font-semibold hover:underline">Remove</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <template v-for="child in field.fields" :key="child.key">
                        <!-- Nested list (e.g. a group's subjects) -->
                        <div v-if="child.type === 'list'" class="md:col-span-2">
                            <ContentListField
                                :field="child"
                                :items="childItems(item, child.key)"
                                :icon-options="iconOptions"
                                :level="level + 1"
                            />
                        </div>
                        <!-- Leaf field -->
                        <ContentField
                            v-else
                            :field="child"
                            :obj="item"
                            :icon-options="iconOptions"
                            compact
                        />
                    </template>
                </div>
            </div>

            <p v-if="!items.length" class="text-text-muted text-xs italic py-2">
                No {{ itemLabel.toLowerCase() }}s yet — click “Add {{ itemLabel }}”.
            </p>
        </div>
    </div>
</template>
