<script setup>
/**
 * Renders a single leaf CMS field (text / textarea / number / select / color)
 * bound directly to obj[field.key]. `obj` is a reactive object owned by the
 * parent form, so mutating obj[field.key] via v-model updates the form in place.
 */
defineProps({
    field:       { type: Object, required: true },
    obj:         { type: Object, required: true },
    iconOptions: { type: Array, default: () => [] },
    compact:     { type: Boolean, default: false }, // smaller styling inside list cards
});
</script>

<template>
    <div :class="field.full ? 'md:col-span-2' : ''">
        <label class="block font-semibold text-text-muted mb-1" :class="compact ? 'text-[11px]' : 'text-xs uppercase tracking-wider mb-1.5'">
            {{ field.label }}
        </label>

        <textarea
            v-if="field.type === 'textarea'"
            v-model="obj[field.key]"
            :rows="compact ? 3 : 4"
            class="w-full border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary bg-white resize-y"
            :class="compact ? 'px-3 py-2' : 'px-4 py-2.5 rounded-xl focus:ring-1 focus:ring-primary/20'"
        ></textarea>

        <select
            v-else-if="field.type === 'select'"
            v-model="obj[field.key]"
            class="w-full border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary bg-white"
            :class="compact ? 'px-3 py-2' : 'px-4 py-2.5 rounded-xl'"
        >
            <option value="">— none —</option>
            <option v-for="opt in (field.options === 'icons' ? iconOptions : (field.options || []))" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>

        <div v-else-if="field.type === 'color'" class="flex items-center gap-2">
            <input
                type="color"
                :value="/^#[0-9a-fA-F]{6}$/.test(obj[field.key]) ? obj[field.key] : '#2C49A6'"
                @input="obj[field.key] = $event.target.value"
                class="h-9 w-11 flex-none rounded-lg border border-gray-200 bg-white cursor-pointer p-0.5"
            />
            <input
                v-model="obj[field.key]"
                type="text"
                placeholder="#2C49A6"
                class="w-full border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary bg-white"
                :class="compact ? 'px-3 py-2' : 'px-4 py-2.5 rounded-xl'"
            />
        </div>

        <input
            v-else
            v-model="obj[field.key]"
            :type="field.type === 'number' ? 'number' : 'text'"
            :step="field.type === 'number' ? '1' : undefined"
            class="w-full border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary bg-white"
            :class="compact ? 'px-3 py-2' : 'px-4 py-2.5 rounded-xl focus:ring-1 focus:ring-primary/20'"
        />
    </div>
</template>
