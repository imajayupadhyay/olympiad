<script setup>
import { computed } from 'vue';

const props = defineProps({
    subject: { type: Object, required: true },
    classNo: { type: Number, required: true },
    strands: { type: Array, default: () => [] },
    totalClasses: { type: Number, default: 10 },
    topicCount: { type: Number, default: 0 },
});

defineEmits(['go-class']);

const pad = (n) => String(n).padStart(2, '0');
const prevClass = computed(() => (props.classNo > 1 ? props.classNo - 1 : null));
const nextClass = computed(() => (props.classNo < props.totalClasses ? props.classNo + 1 : null));
</script>

<template>
    <article class="panel" :style="{ '--sc': subject.color }">
        <span class="panel__band" aria-hidden="true"></span>

        <header class="panel__head">
            <span class="panel__mark">{{ subject.short }}</span>
            <div class="panel__title">
                <h2>{{ subject.label }}</h2>
                <p>{{ subject.blurb }}</p>
            </div>
            <div class="panel__stamp">
                <span class="panel__cls">Class {{ pad(classNo) }}<i>/{{ totalClasses }}</i></span>
                <span class="panel__topics">{{ topicCount }} topics</span>
            </div>
        </header>

        <Transition name="swap" mode="out-in">
            <div class="panel__body" :key="subject.key + '-' + classNo">
                <section
                    v-for="strand in strands"
                    :key="strand.strand || 'all'"
                    class="strand"
                >
                    <h3 class="strand__name">{{ strand.strand || 'Topics covered' }}</h3>
                    <ul class="chips" :class="{ deva: subject.script === 'devanagari' }">
                        <li v-for="t in strand.topics" :key="t" class="chip">{{ t }}</li>
                    </ul>
                </section>
            </div>
        </Transition>

        <footer class="panel__foot">
            <button
                type="button"
                class="step"
                :disabled="!prevClass"
                @click="prevClass && $emit('go-class', prevClass)"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span v-if="prevClass">Class {{ prevClass }}</span>
                <span v-else>Start of ladder</span>
            </button>
            <span class="step__hint">{{ subject.label }} runs Class 1 to {{ totalClasses }}</span>
            <button
                type="button"
                class="step"
                :disabled="!nextClass"
                @click="nextClass && $emit('go-class', nextClass)"
            >
                <span v-if="nextClass">Class {{ nextClass }}</span>
                <span v-else>End of ladder</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </footer>
    </article>
</template>

<style scoped>
.panel {
    position:relative; overflow:hidden;
    background:#fff; border:1.5px solid var(--paper-line); border-radius:22px;
    box-shadow:0 24px 60px -40px rgba(10,16,36,.5);
}
.panel__band {
    position:absolute; top:0; left:0; right:0; height:4px;
    background:linear-gradient(90deg, var(--sc), color-mix(in srgb, var(--sc) 22%, transparent));
}

/* head */
.panel__head {
    display:flex; align-items:flex-start; gap:1rem;
    padding:1.7rem 1.8rem 1.3rem;
    border-bottom:1px solid var(--paper-line);
}
.panel__mark {
    flex:none; width:52px; height:52px; border-radius:15px; display:grid; place-items:center;
    font-family:var(--mono); font-weight:700; font-size:.95rem;
    background:color-mix(in srgb, var(--sc) 12%, #fff); color:var(--sc);
    border:1.5px solid color-mix(in srgb, var(--sc) 28%, transparent);
}
.panel__title { flex:1; min-width:0; }
.panel__title h2 {
    font-family:var(--display); font-weight:600; font-size:clamp(1.45rem, 2.6vw, 1.9rem);
    line-height:1.12; letter-spacing:-.015em; color:var(--ink); margin:0;
}
.panel__title p { margin:.4rem 0 0; font-size:.9rem; line-height:1.55; color:var(--ink-55); max-width:52ch; }

.panel__stamp { flex:none; text-align:right; display:flex; flex-direction:column; gap:.3rem; align-items:flex-end; }
.panel__cls {
    font-family:var(--mono); font-weight:700; font-size:.86rem; letter-spacing:.06em;
    color:var(--ink); background:var(--paper-2); border:1px solid var(--paper-line);
    border-radius:999px; padding:.32rem .7rem; font-variant-numeric:tabular-nums; white-space:nowrap;
}
.panel__cls i { font-style:normal; color:var(--ink-35); }
.panel__topics { font-family:var(--mono); font-size:.7rem; letter-spacing:.06em; color:var(--ink-35); }

/* body */
.panel__body { padding:1.6rem 1.8rem .4rem; }
.strand { margin-bottom:1.6rem; }
.strand__name {
    display:flex; align-items:center; gap:.7rem; margin:0 0 .8rem;
    font-family:var(--body); font-weight:700; font-size:.7rem; letter-spacing:.16em;
    text-transform:uppercase; color:var(--sc);
}
.strand__name::after { content:""; flex:1; height:1px; background:var(--paper-line); }

.chips { list-style:none; display:flex; flex-wrap:wrap; gap:.42rem; padding:0; margin:0; }
.chip {
    font-size:.845rem; line-height:1.35; padding:.36rem .72rem; border-radius:999px;
    color:var(--ink-70);
    /* some topics are a full parenthesised list — wrap inside the pill rather
       than pushing the page sideways on a phone */
    max-width:100%; overflow-wrap:anywhere;
    background:color-mix(in srgb, var(--sc) 6%, var(--paper));
    border:1px solid color-mix(in srgb, var(--sc) 20%, transparent);
    transition:background .16s, color .16s, border-color .16s;
}
.chip:hover { background:color-mix(in srgb, var(--sc) 13%, #fff); color:var(--ink); border-color:color-mix(in srgb, var(--sc) 38%, transparent); }
.chips.deva .chip { font-size:.94rem; line-height:1.5; padding:.3rem .72rem; }

/* foot */
.panel__foot {
    display:flex; align-items:center; justify-content:space-between; gap:1rem;
    padding:1rem 1.5rem; border-top:1px solid var(--paper-line); background:var(--paper);
}
.step {
    display:inline-flex; align-items:center; gap:.4rem; cursor:pointer;
    font-family:var(--body); font-weight:700; font-size:.83rem; color:var(--ink);
    background:#fff; border:1.5px solid var(--paper-line); border-radius:999px; padding:.5rem .9rem;
    transition:border-color .18s, color .18s, transform .18s;
}
.step svg { width:15px; height:15px; }
.step:hover:not(:disabled) { border-color:var(--sc); color:var(--sc); transform:translateY(-1px); }
.step:focus-visible { outline:3px solid var(--sc); outline-offset:2px; }
.step:disabled { opacity:.4; cursor:default; }
.step__hint { font-family:var(--mono); font-size:.72rem; color:var(--ink-35); letter-spacing:.03em; text-align:center; }

/* panel swap — the leave is near-instant on purpose: with out-in the heading has
   already changed, so a slow fade would show the new subject over old topics */
.swap-enter-active { transition:opacity .28s ease, transform .28s cubic-bezier(.2,.8,.2,1); }
.swap-leave-active { transition:opacity .07s ease; }
.swap-enter-from { opacity:0; transform:translateY(8px); }
.swap-leave-to { opacity:0; }

@media (max-width:700px){
    .panel__head { flex-wrap:wrap; padding:1.3rem 1.15rem 1rem; gap:.85rem; }
    .panel__stamp { flex-direction:row; align-items:center; gap:.6rem; width:100%; text-align:left; }
    .panel__body { padding:1.25rem 1.15rem .3rem; }
    .panel__foot { padding:.85rem 1rem; flex-wrap:wrap; justify-content:center; }
    .step__hint { order:3; width:100%; }
}

@media (prefers-reduced-motion: reduce) {
    .swap-enter-active, .swap-leave-active, .chip, .step { transition:none; }
    .swap-enter-from { transform:none; }
    .step:hover:not(:disabled) { transform:none; }
}

@media print {
    .panel { border:none; box-shadow:none; }
    .panel__foot { display:none; }
    .chip { border-color:#bbb; background:transparent; color:#000; }
}
</style>
