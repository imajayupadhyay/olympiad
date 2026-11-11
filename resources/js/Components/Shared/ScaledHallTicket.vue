<script setup>
/**
 * HallTicketCard rendered at its print width and scaled down to fit the container, so
 * admin previews look exactly like the printout. Phones (< 768 px) get the card's own
 * mobile layout instead of a tiny scaled copy.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';
import HallTicketCard from '@/Components/Shared/HallTicketCard.vue';

defineProps({ ticket: { type: Object, required: true } });

const TICKET_WIDTH = 860;
const frame = ref(null);
const inner = ref(null);
const scale = ref(1);
const innerHeight = ref(0);
let observer = null;

const measure = () => {
    if (!frame.value || !inner.value) return;
    const width = frame.value.clientWidth;
    scale.value = window.innerWidth >= 768 && width < TICKET_WIDTH ? width / TICKET_WIDTH : 1;
    innerHeight.value = inner.value.offsetHeight;
};

onMounted(() => {
    observer = new ResizeObserver(measure);
    observer.observe(frame.value);
    observer.observe(inner.value);
    measure();
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div ref="frame" class="overflow-hidden" :style="scale < 1 ? { height: innerHeight * scale + 'px' } : null">
        <div ref="inner" :style="scale < 1 ? { width: TICKET_WIDTH + 'px', transform: `scale(${scale})`, transformOrigin: 'top left' } : null">
            <HallTicketCard :ticket="ticket" />
        </div>
    </div>
</template>
