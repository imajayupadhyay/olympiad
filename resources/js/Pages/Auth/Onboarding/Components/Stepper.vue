<script setup>
defineProps({
    current: { type: Number, default: 1 },   // 1 = Details, 2 = Olympiads, 3 = Payment
});

const steps = [
    { n: 1, label: 'Details' },
    { n: 2, label: 'Olympiads' },
    { n: 3, label: 'Payment' },
];
</script>

<template>
    <ol class="stepper">
        <li v-for="s in steps" :key="s.n" :class="{ done: current > s.n, active: current === s.n }">
            <span class="bubble">
                <svg v-if="current > s.n" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
                <template v-else>{{ s.n }}</template>
            </span>
            <span class="lbl">{{ s.label }}</span>
            <span v-if="s.n < 3" class="line"></span>
        </li>
    </ol>
</template>

<style scoped>
.stepper { list-style: none; display: flex; align-items: center; gap: 0; padding: 0; margin: 0 0 1.8rem; }
.stepper li { display: flex; align-items: center; gap: .5rem; flex: 1; }
.stepper li:last-child { flex: 0; }
.bubble {
    width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
    display: grid; place-items: center;
    font-family: "Space Grotesk", monospace; font-weight: 700; font-size: .85rem;
    background: #F3E9D6; color: #9aa0ad; border: 2px solid #E7D9BE;
    transition: all .25s;
}
.bubble svg { width: 15px; height: 15px; }
.lbl { font-size: .85rem; font-weight: 600; color: #9aa0ad; white-space: nowrap; }
.line { flex: 1; height: 2px; background: #E7D9BE; margin: 0 .6rem; min-width: 16px; }

.active .bubble { background: #EE6A2C; border-color: #EE6A2C; color: #fff; box-shadow: 0 0 0 4px rgba(238,106,44,.15); }
.active .lbl { color: #0A1024; }
.done .bubble { background: #168A66; border-color: #168A66; color: #fff; }
.done .lbl { color: #0A1024; }
.done .line { background: #168A66; }
</style>
