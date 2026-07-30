<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import PublicHeader from '@/Components/Public/PublicHeader.vue';
import AppLogo from '@/Components/Shared/AppLogo.vue';
import SeoHead from '@/Components/Shared/SeoHead.vue';
import ClassLadder from './Components/ClassLadder.vue';
import SubjectRail from './Components/SubjectRail.vue';
import SyllabusPanel from './Components/SyllabusPanel.vue';
import { CLASSES, SUBJECTS, countTopics, toStrands } from './syllabus.data';

/**
 * Static today: the page falls back to the bundled reference data. When the
 * syllabus moves into the admin panel the controller passes the same shape
 * through `syllabus` and nothing else here has to change.
 */
const props = defineProps({
    syllabus: { type: Array, default: null },
    classes: { type: Array, default: null },
});

const subjects = computed(() => props.syllabus ?? SUBJECTS);
const classes = computed(() => props.classes ?? CLASSES);
const year = new Date().getFullYear();

const selectedClass = ref(classes.value[0]);
const selectedKey = ref(subjects.value[0]?.key);

const subject = computed(() => subjects.value.find((s) => s.key === selectedKey.value) ?? subjects.value[0]);
const entry = computed(() => subject.value?.topics?.[selectedClass.value]);
const strands = computed(() => toStrands(entry.value));
const topicCount = computed(() => countTopics(entry.value));

/* topic counts for the whole rail, so a student can see scope before clicking */
const counts = computed(() => Object.fromEntries(
    subjects.value.map((s) => [s.key, countTopics(s.topics?.[selectedClass.value])]),
));

const totals = computed(() => ({
    subjects: subjects.value.length,
    classes: classes.value.length,
    topics: subjects.value.reduce(
        (sum, s) => sum + classes.value.reduce((n, c) => n + countTopics(s.topics?.[c]), 0),
        0,
    ),
}));

/* ── deep links: /syllabus#maths-7 opens Mathematics, Class 7 ────────────── */
const readHash = () => {
    const raw = (typeof window !== 'undefined' ? window.location.hash : '').replace('#', '');
    if (!raw) return;
    const [key, cls] = raw.split('-');
    if (subjects.value.some((s) => s.key === key)) selectedKey.value = key;
    const n = Number(cls);
    if (classes.value.includes(n)) selectedClass.value = n;
};

const writeHash = () => {
    if (typeof window === 'undefined') return;
    // replaceState, not location.hash — the panel updates in place, no scroll jump.
    // Keep the existing state object: it holds Inertia's page, and dropping it
    // would break back/forward navigation.
    window.history.replaceState(window.history.state, '', `#${selectedKey.value}-${selectedClass.value}`);
};

onMounted(() => {
    readHash();
    // Also react to back/forward and to a shared link opened while already here.
    window.addEventListener('hashchange', readHash);
});
onUnmounted(() => window.removeEventListener('hashchange', readHash));
watch([selectedKey, selectedClass], writeHash);

const printSyllabus = () => window.print();

/* the three things every National Excellence paper is built from */
const pillars = [
    {
        title: 'The year’s syllabus',
        body: 'Mapped to what schools actually teach in that class, so preparation runs alongside the school year instead of competing with it.',
    },
    {
        title: 'A reasoning track of its own',
        body: 'Logical Reasoning & Aptitude runs as a full Class 1–10 subject: patterns, coding-decoding, arrangements, figures and quantitative aptitude, harder each year.',
    },
    {
        title: 'Application and awareness',
        body: 'Case-based questions, current events and real-life problems, because the paper rewards understanding over recall.',
    },
];
</script>

<template>
    <SeoHead
        title="Olympiad Syllabus — Class 1 to 10"
        description="The full National Excellence Olympiad syllabus: 8 subjects across Class 1–10, with every topic listed by class. English, Hindi, Mathematics, Science, Social Studies, Computer Science, General Knowledge and Logical Reasoning &amp; Aptitude."
        keywords="olympiad syllabus, olympiad syllabus class 1 to 10, maths olympiad syllabus, science olympiad syllabus, english olympiad syllabus, logical reasoning olympiad syllabus, GK olympiad syllabus India"
        canonical="https://neoexam.org/syllabus" />

    <div class="noh">
        <PublicHeader />

        <!-- ═══════════ HERO ═══════════ -->
        <section class="hero">
            <span class="hero__glow" aria-hidden="true"></span>
            <div class="wrap hero__inner">
                <span class="eyebrow">Syllabus Handbook</span>
                <h1>Every topic.<br><span class="ital">Every class.</span></h1>
                <p class="lede">
                    The complete National Excellence Olympiad syllabus, class by class and subject by
                    subject. Pick your class, open a subject, and see exactly what the paper draws from.
                </p>

                <div class="hero__meta">
                    <div class="fact">
                        <span class="fact__n">{{ totals.subjects }}</span>
                        <span class="fact__l">Subjects</span>
                    </div>
                    <div class="fact">
                        <span class="fact__n">1–{{ totals.classes }}</span>
                        <span class="fact__l">Classes</span>
                    </div>
                    <div class="fact">
                        <span class="fact__n">{{ totals.topics.toLocaleString('en-IN') }}</span>
                        <span class="fact__l">Topics mapped</span>
                    </div>
                    <button type="button" class="printbtn" @click="printSyllabus">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path d="M6 9V3h12v6M6 18H4v-6h16v6h-2" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="8" y="14" width="8" height="7" rx="1"/>
                        </svg>
                        Print this syllabus
                    </button>
                </div>
            </div>
        </section>

        <!-- ═══════════ EXPLORER ═══════════ -->
        <section class="explorer">
            <div class="ladderbar">
                <div class="wrap">
                    <ClassLadder v-model="selectedClass" :classes="classes" :accent="subject.color" />
                </div>
            </div>

            <div class="wrap explorer__grid">
                <aside class="explorer__rail">
                    <span class="railhead">Subjects</span>
                    <SubjectRail v-model="selectedKey" :subjects="subjects" :counts="counts" />
                </aside>

                <SyllabusPanel
                    :subject="subject"
                    :class-no="selectedClass"
                    :strands="strands"
                    :topic-count="topicCount"
                    :total-classes="classes.length"
                    @go-class="selectedClass = $event" />
            </div>
        </section>

        <!-- ═══════════ WHAT EVERY PAPER DRAWS FROM ═══════════ -->
        <section class="pillars">
            <div class="wrap">
                <div class="pillars__head">
                    <span class="eyebrow light">Reading the syllabus</span>
                    <h2>Three things sit behind every paper</h2>
                </div>
                <div class="pillars__grid">
                    <div v-for="(p, i) in pillars" :key="p.title" class="pillar">
                        <span class="pillar__n">{{ String(i + 1).padStart(2, '0') }}</span>
                        <h3>{{ p.title }}</h3>
                        <p>{{ p.body }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════ CTA ═══════════ -->
        <section class="cta">
            <div class="wrap cta__inner">
                <div>
                    <h2>Know the syllabus. Now sit the paper.</h2>
                    <p>Registration takes two minutes. Pick your class, choose your subjects, and start preparing against a national field.</p>
                </div>
                <div class="cta__btns">
                    <Link href="/register" class="btn btn-primary">Register free</Link>
                    <Link href="/exams" class="btn btn-ghost">Browse olympiads</Link>
                </div>
            </div>
        </section>

        <!-- ═══════════ FOOTER ═══════════ -->
        <footer class="foot-band">
            <div class="wrap foot-band__inner">
                <Link href="/" class="brand"><AppLogo :size="54" variant="light" /></Link>
                <p>© {{ year }} National Olympiad Hunt. All rights reserved.</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* ============================================================
   NATIONAL OLYMPIAD HUNT — Syllabus (Editorial / Prestige v2)
   Fonts load globally in app.blade.php; palette scoped to .noh
   ============================================================ */
.noh {
    --ink:#0A1024; --ink-2:#131C3D; --paper:#FBF6EC; --paper-2:#F3E9D6; --paper-line:#E7D9BE;
    --saffron:#EE6A2C; --saffron-dk:#C9501A; --gold:#D6991F; --gold-lt:#F2C84B;
    --royal:#2C49A6; --emerald:#168A66;
    --ink-70:rgba(10,16,36,.70); --ink-55:rgba(10,16,36,.55); --ink-35:rgba(10,16,36,.35); --ink-12:rgba(10,16,36,.12);
    --paper-45:rgba(251,246,236,.45); --paper-70:rgba(251,246,236,.72);
    --display:"Fraunces",Georgia,serif; --body:"Plus Jakarta Sans",system-ui,sans-serif; --mono:"Space Grotesk",monospace;

    font-family:var(--body); background:var(--paper); color:var(--ink);
    line-height:1.6; min-height:100vh; padding-top:74px; -webkit-font-smoothing:antialiased;
    background-image:radial-gradient(var(--ink-12) .5px, transparent .5px);
    background-size:22px 22px;
}
.noh *, .noh *::before, .noh *::after { box-sizing:border-box; }
.noh a { color:inherit; text-decoration:none; }
.noh ::selection { background:var(--saffron); color:#fff; }

.wrap { width:100%; max-width:1180px; margin-inline:auto; padding-inline:24px; }

.eyebrow {
    display:inline-flex; align-items:center; gap:10px;
    font:700 12px/1 var(--body); letter-spacing:.22em; text-transform:uppercase; color:var(--saffron-dk);
}
.eyebrow::before { content:""; width:26px; height:2px; border-radius:2px; background:var(--saffron); }
.eyebrow.light { color:var(--gold-lt); }
.eyebrow.light::before { background:var(--gold); }

.btn {
    display:inline-flex; align-items:center; justify-content:center; gap:9px;
    font:700 15px/1 var(--body); padding:14px 24px; border-radius:100px; cursor:pointer;
    border:1.5px solid transparent; white-space:nowrap;
    transition:background .28s, color .28s, border-color .28s, transform .28s cubic-bezier(.2,.8,.2,1), box-shadow .28s;
}
.btn-primary { background:var(--saffron); color:#fff; box-shadow:0 12px 26px -10px var(--saffron); }
.btn-primary:hover { background:var(--saffron-dk); transform:translateY(-2px); }
.btn-ghost { background:transparent; color:var(--ink); border-color:var(--ink-12); }
.btn-ghost:hover { background:var(--ink); color:var(--paper); border-color:var(--ink); transform:translateY(-2px); }
.noh a.btn-primary { color:#fff; }
.noh a.btn-ghost:hover { color:var(--paper); }

/* ── hero ─────────────────────────────────────────────────── */
.hero { position:relative; overflow:hidden; padding:3.4rem 0 2.6rem; }
.hero__glow {
    position:absolute; top:-190px; right:-120px; width:480px; height:480px; border-radius:50%;
    background:radial-gradient(circle, rgba(242,200,75,.5), transparent 66%);
    filter:blur(60px); pointer-events:none;
}
.hero__inner { position:relative; z-index:1; }
.hero h1 {
    font-family:var(--display); font-weight:600; font-size:clamp(2.4rem, 6vw, 4.1rem);
    line-height:1.0; letter-spacing:-.028em; margin:1rem 0 0;
}
.hero h1 .ital { font-style:italic; font-weight:500; color:var(--saffron-dk); }
.lede { font-size:1.06rem; color:var(--ink-70); max-width:56ch; margin:1.2rem 0 0; }

.hero__meta { display:flex; align-items:flex-end; gap:2.2rem; flex-wrap:wrap; margin-top:2.1rem; }
.fact { display:flex; flex-direction:column; gap:.25rem; }
.fact__n {
    font-family:var(--mono); font-weight:700; font-size:1.85rem; line-height:1;
    color:var(--ink); font-variant-numeric:tabular-nums;
}
.fact__l { font-size:.72rem; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:var(--ink-35); }

.printbtn {
    display:inline-flex; align-items:center; gap:.5rem; margin-left:auto; cursor:pointer;
    font:700 .82rem/1 var(--body); color:var(--ink-70);
    background:rgba(251,246,236,.55); backdrop-filter:blur(14px) saturate(150%); -webkit-backdrop-filter:blur(14px) saturate(150%);
    border:1px solid rgba(255,255,255,.55); box-shadow:inset 0 1px 0 rgba(255,255,255,.6), 0 2px 8px rgba(10,16,36,.06);
    border-radius:100px; padding:.7rem 1.1rem; transition:color .2s, border-color .2s, transform .2s;
}
.printbtn svg { width:16px; height:16px; }
.printbtn:hover { color:var(--ink); border-color:var(--ink-12); transform:translateY(-1px); }
.printbtn:focus-visible { outline:3px solid var(--saffron); outline-offset:2px; }

/* ── explorer ─────────────────────────────────────────────── */
.explorer { padding-bottom:4.5rem; }

.ladderbar {
    position:sticky; top:74px; z-index:20; margin-bottom:1.8rem;
    background:rgba(251,246,236,.94); backdrop-filter:blur(16px) saturate(150%); -webkit-backdrop-filter:blur(16px) saturate(150%);
    border-top:1px solid var(--paper-line); border-bottom:1px solid var(--paper-line);
    box-shadow:0 8px 20px -18px rgba(10,16,36,.5);
    padding:.85rem 0 .95rem;
}

.explorer__grid { display:grid; grid-template-columns:232px minmax(0, 1fr); gap:2rem; align-items:start; }
/* min-width:0 so the horizontal subject scroller can actually scroll on mobile
   instead of stretching its grid column to the width of all eight tabs */
.explorer__rail { position:sticky; top:168px; min-width:0; }
.railhead {
    display:block; margin:0 0 .7rem .7rem;
    font:700 .66rem/1 var(--body); letter-spacing:.2em; text-transform:uppercase; color:var(--ink-35);
}

/* ── pillars ──────────────────────────────────────────────── */
.pillars { background:var(--ink); color:var(--paper); padding:4.6rem 0; position:relative; overflow:hidden; }
.pillars::before {
    content:""; position:absolute; inset:0; pointer-events:none;
    background-image:linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px);
    background-size:48px 48px;
    mask-image:radial-gradient(ellipse 70% 60% at 50% 40%, #000, transparent);
    -webkit-mask-image:radial-gradient(ellipse 70% 60% at 50% 40%, #000, transparent);
}
.pillars .wrap { position:relative; }
.pillars__head { max-width:640px; margin-bottom:2.6rem; }
.pillars__head h2 {
    font-family:var(--display); font-weight:600; font-size:clamp(1.7rem, 3.4vw, 2.6rem);
    line-height:1.08; letter-spacing:-.02em; margin:1rem 0 0; color:var(--paper);
}
.pillars__grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:1.4rem; }
.pillar {
    background:rgba(255,255,255,.05); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
    border:1px solid rgba(255,255,255,.10); border-radius:20px; padding:1.7rem 1.5rem;
}
.pillar__n {
    display:block; font-family:var(--mono); font-weight:700; font-size:.82rem;
    letter-spacing:.1em; color:var(--gold-lt); margin-bottom:.85rem;
}
.pillar h3 { font-family:var(--display); font-weight:600; font-size:1.22rem; margin:0 0 .5rem; color:var(--paper); }
.pillar p { font-size:.9rem; line-height:1.62; color:var(--paper-45); margin:0; }

/* ── cta ──────────────────────────────────────────────────── */
.cta { background:var(--paper-2); padding:3.6rem 0; }
.cta__inner { display:flex; align-items:center; justify-content:space-between; gap:2rem; flex-wrap:wrap; }
.cta__inner h2 {
    font-family:var(--display); font-weight:600; font-size:clamp(1.6rem, 3.2vw, 2.3rem);
    line-height:1.1; letter-spacing:-.02em; margin:0;
}
.cta__inner p { font-size:.98rem; color:var(--ink-70); margin:.6rem 0 0; max-width:50ch; }
.cta__btns { display:flex; gap:.75rem; flex-wrap:wrap; }

/* ── footer ───────────────────────────────────────────────── */
.foot-band { background:var(--ink); color:var(--paper-70); }
.foot-band__inner {
    display:flex; align-items:center; justify-content:space-between; gap:1rem;
    padding-top:1.6rem; padding-bottom:1.6rem; flex-wrap:wrap;
}
.foot-band p { font-size:.84rem; margin:0; }
.brand { display:flex; align-items:center; gap:.6rem; }

/* ── responsive ───────────────────────────────────────────── */
@media (max-width:960px){
    .explorer__grid { grid-template-columns:minmax(0, 1fr); gap:1.1rem; }
    .explorer__rail { position:static; }
    .railhead { display:none; }
    .pillars__grid { grid-template-columns:1fr; }
}
@media (max-width:700px){
    .wrap { padding-inline:18px; }
    .hero { padding:2.4rem 0 1.8rem; }
    .hero__meta { gap:1.4rem 1.8rem; margin-top:1.7rem; }
    .fact__n { font-size:1.5rem; }
    .printbtn { margin-left:0; width:100%; justify-content:center; }
    .pillars { padding:3.4rem 0; }
    .cta__inner { flex-direction:column; align-items:flex-start; }
    .cta__btns { width:100%; }
    .cta__btns .btn { flex:1; }
}

@media (prefers-reduced-motion: reduce) {
    .btn, .printbtn { transition:background .2s, color .2s, border-color .2s; }
    .btn:hover, .printbtn:hover { transform:none; }
}

/* ── print: the open syllabus, nothing else ───────────────── */
@media print {
    .noh { padding-top:0; background:#fff; background-image:none; }
    .hero__glow, .ladderbar, .explorer__rail, .pillars, .cta, .foot-band { display:none !important; }
    :deep(.ph) { display:none !important; }
    .hero { padding:0 0 1rem; }
    .hero__meta, .lede { display:none; }
    .hero h1 { font-size:1.7rem; }
    .explorer { padding-bottom:0; }
    .explorer__grid { display:block; }
    .wrap { max-width:none; padding-inline:0; }
}
</style>
