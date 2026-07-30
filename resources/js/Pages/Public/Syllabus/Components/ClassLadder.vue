<script setup>
/**
 * The class picker, drawn as a ladder rather than a row of buttons — the
 * syllabus really is a ten-year climb, and the rail says so before the copy does.
 */
defineProps({
    classes: { type: Array, required: true },
    modelValue: { type: [Number, String], required: true },
    accent: { type: String, default: '#EE6A2C' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="ladder" :style="{ '--accent': accent }">
        <span class="ladder__label">Class</span>
        <div class="ladder__rail" role="tablist" aria-label="Choose a class">
            <span class="ladder__line" aria-hidden="true"></span>
            <button
                v-for="c in classes"
                :key="c"
                type="button"
                role="tab"
                class="rung"
                :class="{ on: modelValue === c }"
                :aria-selected="modelValue === c"
                @click="$emit('update:modelValue', c)"
            >
                <span class="rung__n">{{ c }}</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.ladder { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
.ladder__label {
    font-family:var(--body); font-weight:700; font-size:.68rem; letter-spacing:.2em;
    text-transform:uppercase; color:var(--ink-55); flex:none;
}
/* sized to its rungs, so the connecting line ends at Class 10 rather than
   trailing off into empty space */
.ladder__rail { position:relative; display:flex; align-items:center; gap:.45rem; min-width:0; }

/* the hairline that turns ten buttons into one progression */
.ladder__line {
    position:absolute; left:22px; right:22px; top:50%; height:1px; transform:translateY(-.5px);
    background:repeating-linear-gradient(90deg, var(--ink-12) 0 5px, transparent 5px 11px);
}

.rung {
    position:relative; z-index:1; flex:none;
    width:44px; height:44px; border-radius:50%;
    display:grid; place-items:center; cursor:pointer;
    background:var(--paper); border:1.5px solid var(--paper-line);
    color:var(--ink-70); transition:transform .18s cubic-bezier(.2,.8,.2,1), background .18s, color .18s, border-color .18s, box-shadow .18s;
}
.rung__n { font-family:var(--mono); font-weight:600; font-size:1rem; font-variant-numeric:tabular-nums; line-height:1; }
.rung:hover { border-color:var(--ink-35); color:var(--ink); transform:translateY(-2px); }
.rung:focus-visible { outline:3px solid var(--accent); outline-offset:3px; }
.rung.on {
    background:var(--ink); border-color:var(--ink); color:var(--paper);
    transform:translateY(-3px);
    box-shadow:0 10px 20px -10px rgba(10,16,36,.55), 0 0 0 4px color-mix(in srgb, var(--accent) 16%, transparent);
}
.rung.on::after {
    content:""; position:absolute; bottom:-11px; left:50%; transform:translateX(-50%);
    width:18px; height:3px; border-radius:3px; background:var(--accent);
}

@media (max-width:760px){
    .ladder { gap:.6rem; }
    /* fade the right edge so it reads as scrollable rather than clipped */
    .ladder__rail {
        overflow-x:auto; padding:6px 2px 14px; scrollbar-width:none;
        mask-image:linear-gradient(90deg, #000 88%, transparent);
        -webkit-mask-image:linear-gradient(90deg, #000 88%, transparent);
    }
    .ladder__rail::-webkit-scrollbar { display:none; }
    .rung { width:40px; height:40px; }
}

@media (prefers-reduced-motion: reduce) {
    .rung { transition:background .18s, color .18s, border-color .18s; }
    .rung:hover, .rung.on { transform:none; }
}
</style>
