<script setup>
const model = defineModel({ type: Boolean, default: false });
defineProps({
    label: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // md | sm
});
</script>

<template>
    <button type="button" role="switch" :aria-checked="model" :aria-label="label || undefined" :disabled="disabled"
            class="relative inline-flex shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/50 disabled:opacity-50 disabled:cursor-not-allowed"
            :class="[model ? 'bg-success' : 'bg-gray-300', size === 'sm' ? 'h-5 w-9' : 'h-6 w-11']"
            @click="model = !model">
        <span class="inline-block transform rounded-full bg-white shadow transition-transform duration-200 motion-reduce:transition-none"
              :class="size === 'sm' ? ['h-4 w-4', model ? 'translate-x-[18px]' : 'translate-x-0.5'] : ['h-5 w-5', model ? 'translate-x-[22px]' : 'translate-x-0.5']"></span>
    </button>
</template>
