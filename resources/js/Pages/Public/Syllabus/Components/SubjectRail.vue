<script setup>
/**
 * Subject picker. A binder spine on desktop (the active tab slides out of the
 * stack toward the panel it opens); a horizontal scroller on small screens.
 */
defineProps({
    subjects: { type: Array, required: true },
    modelValue: { type: String, required: true },
    /** { [subjectKey]: number } — topics in the currently selected class. */
    counts: { type: Object, default: () => ({}) },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="rail" role="tablist" aria-label="Choose a subject">
        <button
            v-for="s in subjects"
            :key="s.key"
            type="button"
            role="tab"
            class="stab"
            :class="{ on: modelValue === s.key }"
            :style="{ '--sc': s.color }"
            :aria-selected="modelValue === s.key"
            @click="$emit('update:modelValue', s.key)"
        >
            <span class="stab__mark">{{ s.short }}</span>
            <span class="stab__text">
                <span class="stab__label">{{ s.label }}</span>
                <span v-if="counts[s.key]" class="stab__count">{{ counts[s.key] }} topics</span>
            </span>
        </button>
    </div>
</template>

<style scoped>
.rail { display:flex; flex-direction:column; gap:.4rem; }

.stab {
    position:relative; display:flex; align-items:center; gap:.7rem;
    text-align:left; width:100%; cursor:pointer; font-family:var(--body);
    background:transparent; border:1.5px solid transparent; border-radius:14px;
    padding:.6rem .7rem; color:var(--ink-70);
    transition:background .18s, color .18s, border-color .18s, transform .18s cubic-bezier(.2,.8,.2,1);
}
.stab:hover { background:rgba(255,255,255,.6); color:var(--ink); }
.stab:focus-visible { outline:3px solid var(--sc); outline-offset:2px; }

.stab__mark {
    flex:none; width:38px; height:38px; border-radius:11px; display:grid; place-items:center;
    font-family:var(--mono); font-weight:700; font-size:.78rem; letter-spacing:.01em;
    background:color-mix(in srgb, var(--sc) 12%, var(--paper));
    color:var(--sc); border:1.5px solid color-mix(in srgb, var(--sc) 26%, transparent);
    transition:background .18s, color .18s, border-color .18s;
}
.stab__text { min-width:0; display:flex; flex-direction:column; gap:2px; }
.stab__label { font-weight:600; font-size:.93rem; line-height:1.2; }
.stab__count {
    font-family:var(--mono); font-size:.68rem; letter-spacing:.04em; color:var(--ink-35);
    font-variant-numeric:tabular-nums;
}

/* active: the tab leaves the stack and points at its panel */
.stab.on {
    background:#fff; border-color:var(--paper-line); color:var(--ink);
    box-shadow:-3px 6px 18px -12px rgba(10,16,36,.5);
}
.stab.on::before {
    content:""; position:absolute; left:-1.5px; top:12%; bottom:12%; width:3px;
    border-radius:3px; background:var(--sc);
}
.stab.on .stab__mark { background:var(--sc); color:#fff; border-color:var(--sc); }
.stab.on .stab__count { color:var(--ink-55); }

@media (min-width:961px){
    .stab.on { transform:translateX(6px); }
}

/* mobile: horizontal scroller */
@media (max-width:960px){
    .rail {
        flex-direction:row; gap:.5rem; overflow-x:auto; padding:2px 2px 12px;
        scrollbar-width:none; scroll-snap-type:x proximity;
        /* fade the right edge so it reads as scrollable rather than clipped */
        mask-image:linear-gradient(90deg, #000 90%, transparent);
        -webkit-mask-image:linear-gradient(90deg, #000 90%, transparent);
    }
    .rail::-webkit-scrollbar { display:none; }
    .stab { width:auto; flex:none; scroll-snap-align:start; border-color:var(--paper-line); background:var(--paper); padding:.5rem .85rem .5rem .55rem; }
    .stab__mark { width:32px; height:32px; border-radius:9px; font-size:.7rem; }
    .stab.on::before { display:none; }
    .stab.on { border-color:var(--sc); box-shadow:0 0 0 3px color-mix(in srgb, var(--sc) 14%, transparent); }
}

@media (prefers-reduced-motion: reduce) {
    .stab, .stab.on { transition:background .18s, color .18s, border-color .18s; transform:none; }
}
</style>
