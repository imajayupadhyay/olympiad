<template>
  <img
    :src="src"
    :style="{ height: size + 'px', width: 'auto' }"
    alt="National Excellence Olympiad"
    class="block select-none"
    draggable="false"
  />
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  // Height of the logo in px (width scales automatically — the logo is a wide lockup).
  size:    { type: Number, default: 40 },
  // 'light' = white-text logo for dark backgrounds · 'dark' = navy-text logo for light backgrounds.
  variant: { type: String, default: 'light' },
});

const src = computed(() =>
  props.variant === 'dark'
    ? '/NEO_logo_horizontal_transparent.svg'
    : '/NEO_logo_horizontal_light.svg'
);
</script>
