<script setup>
import PublicHeader from '@/Components/Public/PublicHeader.vue';
import SeoHead from '@/Components/Shared/SeoHead.vue';
import AppLogo from '@/Components/Shared/AppLogo.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    exams: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    classLevels: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const year = new Date().getFullYear();

/* ── filters (client-side: grouping makes the list small, so filtering is instant) ── */
const search = ref('');
const classId = ref('');

const filteredExams = computed(() => props.exams.filter((e) => {
    if (classId.value && String(e.class_level?.id) !== String(classId.value)) return false;
    if (search.value.trim()) {
        const q = search.value.trim().toLowerCase();
        const hay = `${e.subject?.name ?? ''} ${e.name ?? ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
    }
    return true;
}));

/* ── group the flat exam list into one entry per subject ── */
const classNum = (label) => {
    const m = String(label ?? '').match(/\d+/);
    return m ? parseInt(m[0], 10) : null;
};

const groups = computed(() => {
    const bySubject = new Map();
    filteredExams.value.forEach((e) => {
        const key = e.subject?.id ?? e.subject?.name ?? 'other';
        if (!bySubject.has(key)) bySubject.set(key, { subject: e.subject ?? { name: e.name }, exams: [] });
        bySubject.get(key).exams.push(e);
    });

    // Order groups by the admin-defined subject sort order.
    const order = new Map(props.subjects.map((s, i) => [s.id, i]));
    const list = [...bySubject.values()].sort((a, b) =>
        (order.get(a.subject?.id) ?? 99) - (order.get(b.subject?.id) ?? 99));

    return list.map((g) => {
        const exams = [...g.exams].sort((a, b) => (classNum(a.class_level?.label) ?? 99) - (classNum(b.class_level?.label) ?? 99));
        const open = exams.filter((e) => e.availability !== 'closed');
        const fees = open.length ? open.map((e) => e.fee_amount) : exams.map((e) => e.fee_amount);
        const nums = exams.map((e) => classNum(e.class_level?.label)).filter((n) => n !== null);
        return {
            subject: g.subject,
            exams,
            monogram: (g.subject?.icon || g.subject?.name || '?').trim().slice(0, 2),
            color: g.subject?.color || '#2C49A6',
            classRange: nums.length ? (nums.length > 1 ? `Class ${Math.min(...nums)}–${Math.max(...nums)}` : `Class ${nums[0]}`) : `${exams.length} classes`,
            minFee: Math.min(...fees),
            allFree: fees.every((f) => f === 0),
            liveCount: exams.filter((e) => e.availability === 'live').length,
            upcomingCount: exams.filter((e) => e.availability === 'upcoming').length,
            allClosed: exams.every((e) => e.availability === 'closed'),
        };
    });
});

/* ── selection cart (unchanged pipeline: picked ids → exams.enroll) ── */
const picked = ref([]);
const selectable = (e) => !e.is_enrolled && e.availability !== 'closed';
const toggle = (e) => { if (!selectable(e)) return; const i = picked.value.indexOf(e.id); i === -1 ? picked.value.push(e.id) : picked.value.splice(i, 1); };
const selected = computed(() => props.exams.filter((e) => picked.value.includes(e.id)));
const total = computed(() => selected.value.reduce((s, e) => s + e.fee_amount, 0));
const hasPaid = computed(() => selected.value.some((e) => !e.is_free));
const pickedIn = (g) => g.exams.filter((e) => picked.value.includes(e.id)).length;

const enrollForm = useForm({ exam_ids: [] });
const enroll = () => { enrollForm.exam_ids = [...picked.value]; enrollForm.post(route('exams.enroll')); };

/* ── class-picker modal ── */
const openKey = ref(null); // subject id of the open group
const openGroup = computed(() => groups.value.find((g) => (g.subject?.id ?? g.subject?.name) === openKey.value) ?? null);
const openModal = (g) => { openKey.value = g.subject?.id ?? g.subject?.name; };
const closeModal = () => { openKey.value = null; };

const onKeydown = (ev) => { if (ev.key === 'Escape') closeModal(); };
watch(openKey, (v) => {
    document.body.style.overflow = v !== null ? 'hidden' : '';
    v !== null ? window.addEventListener('keydown', onKeydown) : window.removeEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => { document.body.style.overflow = ''; window.removeEventListener('keydown', onKeydown); });

/* ── helpers ── */
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }) : '';
const fmtFee = (n) => n === 0 ? 'FREE' : '₹' + Number(n).toLocaleString('en-IN');
const tint = (hex, a) => hex + a; // 6-digit hex + alpha byte, e.g. tint('#2C49A6','14')
const availMeta = (a) => ({ upcoming: { l: 'Upcoming', c: 'av-up' }, live: { l: 'Live now', c: 'av-live' }, closed: { l: 'Closed', c: 'av-closed' } }[a] ?? { l: '', c: '' });

/* ── toast ── */
const toast = ref(null);
let tt = null;
watch(() => page.props.flash, (f) => {
    const msg = f?.success || f?.info || f?.error; if (!msg) return;
    toast.value = { type: f?.error ? 'error' : (f?.success ? 'success' : 'info'), msg };
    clearTimeout(tt); tt = setTimeout(() => (toast.value = null), 4000);
}, { deep: true, immediate: true });
</script>

<template>
    <SeoHead
        title="Olympiad Exams"
        description="Browse National Olympiad Hunt exams by subject, pick your class, and register online in minutes. Compare fees and schedules across every olympiad." />

    <div class="noh">
        <!-- toast -->
        <Transition name="toast"><div v-if="toast" class="toast" :class="toast.type">{{ toast.msg }}</div></Transition>

        <!-- global header -->
        <PublicHeader />

        <!-- hero -->
        <section class="hero">
            <!-- minimal school line-art, 50% opacity -->
            <div class="hero-art" aria-hidden="true">
                <svg class="v-pencil" viewBox="0 0 64 64" fill="none" stroke="#EE6A2C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M42 10l12 12-30 30-15 3 3-15z"/><path d="M38 14l12 12"/><path d="M12 55l-3 3"/>
                </svg>
                <svg class="v-book" viewBox="0 0 64 64" fill="none" stroke="#2C49A6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 14c8-4 16-4 24 0v36c-8-4-16-4-24 0z"/><path d="M56 14c-8-4-16-4-24 0v36c8-4 16-4 24 0z"/><path d="M14 22c5-2 9-2 12 0M14 30c5-2 9-2 12 0M38 22c5-2 9-2 12 0M38 30c5-2 9-2 12 0"/>
                </svg>
                <svg class="v-protractor" viewBox="0 0 64 40" fill="none" stroke="#D6991F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 36a26 26 0 0 1 52 0z"/><path d="M32 36V22M18 36l4-8M46 36l-4-8"/>
                </svg>
                <svg class="v-medal" viewBox="0 0 64 64" fill="none" stroke="#D6991F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="32" cy="42" r="13"/><path d="M32 37l2 4 4 .5-3 3 .8 4.5-3.8-2.2-3.8 2.2.8-4.5-3-3 4-.5z"/><path d="M24 31l-5-23M40 31l5-23M19 8h9l4 9 4-9h9"/>
                </svg>
                <svg class="v-atom" viewBox="0 0 64 64" fill="none" stroke="#168A66" stroke-width="2" stroke-linecap="round">
                    <ellipse cx="32" cy="32" rx="24" ry="9"/><ellipse cx="32" cy="32" rx="24" ry="9" transform="rotate(60 32 32)"/><ellipse cx="32" cy="32" rx="24" ry="9" transform="rotate(-60 32 32)"/><circle cx="32" cy="32" r="3"/>
                </svg>
            </div>
            <div class="wrap hero__inner">
                <span class="eyebrow">Olympiad Catalogue</span>
                <h1>Pick your <span class="ital">subject</span>. Then your class.</h1>
                <p>Every olympiad, one card per subject. Open a subject to choose the classes you want and enrol in a single step.</p>

                <div class="filters">
                    <div class="search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <input v-model="search" type="text" placeholder="Search subjects…" />
                    </div>
                    <select v-model="classId" aria-label="Filter by class">
                        <option value="">All classes</option>
                        <option v-for="c in classLevels" :key="c.id" :value="String(c.id)">{{ c.label }}</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- subject grid: one card per subject -->
        <section class="wrap list-wrap">
            <div v-if="groups.length" class="grid">
                <button
                    v-for="g in groups"
                    :key="g.subject?.id ?? g.subject?.name"
                    type="button"
                    class="scard"
                    :class="{ muted: g.allClosed }"
                    @click="openModal(g)"
                >
                    <span class="scard__band" :style="{ background: `linear-gradient(90deg, ${tint(g.color,'E6')}, ${tint(g.color,'59')})` }"></span>
                    <div class="scard__top">
                        <span class="crest" :style="{ background: tint(g.color,'14'), color: g.color, borderColor: tint(g.color,'2E') }">{{ g.monogram }}</span>
                        <span v-if="g.liveCount" class="avail av-live"><span class="dot"></span>{{ g.liveCount }} live</span>
                        <span v-else-if="g.upcomingCount" class="avail av-up"><span class="dot"></span>Upcoming</span>
                        <span v-else class="avail av-closed"><span class="dot"></span>Closed</span>
                    </div>
                    <h3>{{ g.subject?.name }}</h3>
                    <p class="meta">{{ g.classRange }} · {{ g.exams.length }} exam{{ g.exams.length > 1 ? 's' : '' }}</p>
                    <div class="scard__foot">
                        <span class="fee" :class="{ free: g.allFree }">{{ g.allFree ? 'FREE' : 'from ' + (g.minFee === 0 ? '₹0' : fmtFee(g.minFee)) }}</span>
                        <span v-if="pickedIn(g)" class="pickbadge" :style="{ background: tint(g.color,'14'), color: g.color }">{{ pickedIn(g) }} selected</span>
                        <span v-else class="choose">Choose class <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    </div>
                </button>
            </div>
            <div v-else class="empty">
                <div class="ic">📭</div><h3>No exams found</h3><p>Try a different search or class filter.</p>
            </div>
        </section>

        <!-- class-picker modal -->
        <Transition name="modal">
            <div v-if="openGroup" class="overlay" @click.self="closeModal">
                <div class="sheet" role="dialog" aria-modal="true" :aria-label="openGroup.subject?.name + ' — choose your class'">
                    <div class="sheet__head">
                        <span class="crest lg" :style="{ background: tint(openGroup.color,'14'), color: openGroup.color, borderColor: tint(openGroup.color,'2E') }">{{ openGroup.monogram }}</span>
                        <div class="sheet__title">
                            <h2>{{ openGroup.subject?.name }}</h2>
                            <p>Choose your class — you can pick more than one.</p>
                        </div>
                        <button type="button" class="x" @click="closeModal" aria-label="Close">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/></svg>
                        </button>
                    </div>

                    <div class="chips">
                        <button
                            v-for="e in openGroup.exams"
                            :key="e.id"
                            type="button"
                            class="chip"
                            :class="{ on: picked.includes(e.id), off: !selectable(e) }"
                            :style="picked.includes(e.id) ? { borderColor: openGroup.color, boxShadow: `0 0 0 3px ${tint(openGroup.color,'22')}` } : {}"
                            :disabled="!selectable(e)"
                            @click="toggle(e)"
                        >
                            <span class="chip__cls">{{ e.class_level?.label ?? e.name }}</span>
                            <span class="chip__win">{{ fmtDate(e.starts_at) }} – {{ fmtDate(e.ends_at) }}</span>
                            <span class="chip__row">
                                <span class="chip__fee" :class="{ free: e.is_free }">{{ fmtFee(e.fee_amount) }}</span>
                                <span v-if="e.is_enrolled" class="chip__enr">✓ Enrolled</span>
                                <span v-else-if="e.availability === 'closed'" class="chip__closed">Closed</span>
                                <span v-else class="chip__check" :class="{ on: picked.includes(e.id) }" :style="picked.includes(e.id) ? { background: openGroup.color, borderColor: openGroup.color } : {}">
                                    <svg v-if="picked.includes(e.id)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
                                </span>
                            </span>
                        </button>
                    </div>

                    <div class="sheet__foot">
                        <span class="sheet__count">
                            <template v-if="pickedIn(openGroup)">{{ pickedIn(openGroup) }} class{{ pickedIn(openGroup) > 1 ? 'es' : '' }} selected</template>
                            <template v-else>Tap a class to select it</template>
                        </span>
                        <button type="button" class="btn btn-primary" @click="closeModal">Done</button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- sticky summary (unchanged enrol pipeline) -->
        <Transition name="bar">
            <div v-if="selected.length && !openGroup" class="summary">
                <div class="wrap summary__inner">
                    <div class="s-l">
                        <span class="cnt">{{ selected.length }}</span>
                        <div><strong>{{ selected.length }} exam{{ selected.length > 1 ? 's' : '' }} selected</strong>
                        <small v-if="!user">You'll log in to complete enrolment</small></div>
                    </div>
                    <div class="s-r">
                        <div class="tot"><small>Total</small><strong :class="{ free: total === 0 }">{{ total === 0 ? 'FREE' : '₹' + total.toLocaleString('en-IN') }}</strong></div>
                        <button class="btn btn-ghost light" @click="picked = []">Clear</button>
                        <button class="btn btn-primary" :disabled="enrollForm.processing" @click="enroll">{{ enrollForm.processing ? 'Please wait…' : (hasPaid ? 'Proceed to Pay' : 'Enroll now') }}</button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- footer -->
        <footer class="foot-band">
            <div class="wrap foot-band__inner">
                <Link href="/" class="brand light"><AppLogo :size="54" variant="light" /></Link>
                <p>© {{ year }} National Olympiad Hunt. All rights reserved.</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.noh {
    --ink:#0A1024; --ink-2:#131C3D; --paper:#FBF6EC; --paper-2:#F3E9D6; --paper-line:#E7D9BE;
    --saffron:#EE6A2C; --saffron-dk:#C9501A; --gold:#D6991F; --gold-lt:#F2C84B; --royal:#2C49A6; --emerald:#168A66;
    --ink-70:rgba(10,16,36,.7); --ink-55:rgba(10,16,36,.55);
    --display:"Fraunces",Georgia,serif; --body:"Plus Jakarta Sans",system-ui,sans-serif; --mono:"Space Grotesk",monospace;
    font-family:var(--body); background:var(--paper); color:var(--ink); min-height:100vh; padding-top:74px; padding-bottom:80px;
}
.wrap { max-width:1180px; margin:0 auto; padding:0 24px; }
.brand { display:flex; align-items:center; gap:.6rem; text-decoration:none; }

.btn { display:inline-flex; align-items:center; justify-content:center; font:700 14px/1 var(--body); padding:11px 18px; border-radius:11px; border:0; cursor:pointer; text-decoration:none; transition:transform .15s, box-shadow .2s; }
.btn-primary { background:linear-gradient(135deg,#F2854E,var(--saffron)); color:#fff; box-shadow:0 12px 26px -12px rgba(238,106,44,.8); }
.btn-primary:hover { transform:translateY(-2px); }
.btn-primary:disabled { opacity:.6; cursor:progress; }
.btn-ghost { background:transparent; color:var(--ink-70); border:1px solid var(--paper-line); }
.btn-ghost.light { color:rgba(251,246,236,.85); border-color:rgba(251,246,236,.25); }

/* hero */
.hero { position:relative; overflow:hidden; padding:3rem 0 2.5rem; }

/* minimal school line-art (50% opacity), hand-scattered around the copy */
.hero-art { position:absolute; inset:0; z-index:0; pointer-events:none; }
.hero-art svg { position:absolute; opacity:.5; }
.v-pencil     { width:62px; top:14%;  right:7%;  transform:rotate(8deg); }
.v-book       { width:74px; bottom:16%; left:2.5%; transform:rotate(-7deg); }
.v-protractor { width:64px; bottom:10%; right:17%; transform:rotate(-4deg); }
.v-medal      { width:54px; top:46%;  right:2.5%; transform:rotate(6deg); }
.v-atom       { width:50px; top:8%;   right:30%; transform:rotate(14deg); }
@media (max-width:1100px){ .v-medal, .v-atom { display:none; } }
@media (max-width:760px){ .v-protractor { display:none; } .v-pencil { width:46px; right:4%; } .v-book { width:54px; } }
.hero__inner { position:relative; z-index:1; }
.eyebrow { display:inline-block; font-family:var(--mono); font-weight:600; font-size:.74rem; letter-spacing:.14em; text-transform:uppercase; color:var(--saffron); background:rgba(238,106,44,.1); padding:.3rem .7rem; border-radius:999px; }
.hero h1 { font-family:var(--display); font-weight:600; font-size:clamp(2rem,4vw,3rem); line-height:1.08; margin:.9rem 0 .6rem; }
.hero h1 .ital { font-style:italic; color:var(--saffron); }
.hero p { color:var(--ink-55); font-size:1.05rem; max-width:52ch; margin:0 0 1.6rem; }

.filters { display:flex; flex-wrap:wrap; gap:.7rem; }
.search { position:relative; flex:1; min-width:220px; max-width:420px; display:flex; align-items:center; background:#fff; border:1.5px solid var(--paper-line); border-radius:12px; }
.search svg { width:18px; height:18px; margin-left:.8rem; color:#9aa0ad; }
.search input { flex:1; border:0; background:transparent; outline:none; padding:.75rem .8rem; font-size:.95rem; font-family:var(--body); color:var(--ink); }
.filters select { background:#fff; border:1.5px solid var(--paper-line); border-radius:12px; padding:.75rem .9rem; font-size:.92rem; color:var(--ink); cursor:pointer; font-family:var(--body); outline:none; }
.search:focus-within, .filters select:focus { border-color:var(--saffron); }

/* subject grid */
.list-wrap { padding-top:.5rem; }
.grid { display:grid; gap:1.1rem; grid-template-columns:repeat(auto-fill,minmax(265px,1fr)); }

.scard { position:relative; overflow:hidden; text-align:left; background:#fff; border:1.5px solid var(--paper-line); border-radius:18px; padding:1.25rem 1.2rem 1.1rem; cursor:pointer; transition:transform .18s, box-shadow .2s, border-color .2s; box-shadow:0 2px 8px rgba(10,16,36,.04); display:flex; flex-direction:column; gap:.55rem; font-family:var(--body); }
.scard:hover { transform:translateY(-3px); box-shadow:0 22px 44px -24px rgba(10,16,36,.3); }
.scard:focus-visible { outline:3px solid var(--saffron); outline-offset:2px; }
.scard.muted { opacity:.62; }
.scard__band { position:absolute; top:0; left:0; right:0; height:4px; }
.scard__top { display:flex; align-items:center; justify-content:space-between; gap:.5rem; }

.crest { width:52px; height:52px; flex:none; display:grid; place-items:center; border-radius:15px; border:1.5px solid; font-family:var(--mono); font-weight:700; font-size:1.25rem; letter-spacing:.02em; }
.crest.lg { width:58px; height:58px; font-size:1.4rem; }

.avail { display:inline-flex; align-items:center; gap:.3rem; font-size:.72rem; font-weight:700; padding:.2rem .55rem; border-radius:999px; }
.avail .dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
.av-up { background:rgba(44,73,166,.12); color:var(--royal); }
.av-live { background:rgba(22,138,102,.14); color:var(--emerald); }
.av-closed { background:rgba(91,99,115,.14); color:#5B6373; }

.scard h3 { font-family:var(--display); font-weight:600; font-size:1.28rem; color:var(--ink); margin:.15rem 0 0; line-height:1.2; }
.meta { font-family:var(--mono); font-size:.8rem; color:#5B6373; margin:0; }
.scard__foot { display:flex; align-items:center; justify-content:space-between; gap:.6rem; padding-top:.75rem; border-top:1px solid #F0E6D2; margin-top:.35rem; }
.fee { font-family:var(--mono); font-weight:700; font-size:1rem; color:var(--ink); }
.fee.free { color:var(--emerald); }
.pickbadge { font-size:.76rem; font-weight:700; padding:.28rem .6rem; border-radius:999px; }
.choose { display:inline-flex; align-items:center; gap:.35rem; font-size:.82rem; font-weight:700; color:var(--saffron-dk); }
.choose svg { width:15px; height:15px; transition:transform .18s; }
.scard:hover .choose svg { transform:translateX(3px); }

.empty { text-align:center; padding:4rem 1rem; color:#5B6373; }
.empty .ic { font-size:2.4rem; }
.empty h3 { font-family:var(--display); color:var(--ink); margin:.5rem 0 .3rem; }

/* class-picker modal */
.overlay { position:fixed; inset:0; z-index:85; display:grid; place-items:center; padding:1.2rem; background:rgba(10,16,36,.45); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); }
.sheet { width:100%; max-width:640px; max-height:min(84vh, 720px); display:flex; flex-direction:column; background:var(--paper); border:1px solid var(--paper-line); border-radius:24px; box-shadow:0 40px 90px -30px rgba(10,16,36,.55); overflow:hidden; }
.sheet__head { display:flex; align-items:center; gap:.9rem; padding:1.2rem 1.3rem 1rem; border-bottom:1px solid var(--paper-line); background:#fff; }
.sheet__title { flex:1; min-width:0; }
.sheet__title h2 { font-family:var(--display); font-weight:600; font-size:1.35rem; margin:0; color:var(--ink); line-height:1.15; }
.sheet__title p { margin:.2rem 0 0; font-size:.84rem; color:#5B6373; }
.x { flex:none; width:38px; height:38px; display:grid; place-items:center; border:1px solid var(--paper-line); background:#fff; border-radius:12px; cursor:pointer; color:var(--ink-70); transition:background .2s, color .2s; }
.x:hover { background:var(--paper-2); color:var(--ink); }
.x:focus-visible { outline:3px solid var(--saffron); outline-offset:2px; }
.x svg { width:17px; height:17px; }

.chips { overflow-y:auto; padding:1.1rem 1.3rem; display:grid; gap:.7rem; grid-template-columns:repeat(auto-fill,minmax(158px,1fr)); }
.chip { display:flex; flex-direction:column; gap:.3rem; text-align:left; background:#fff; border:1.5px solid var(--paper-line); border-radius:14px; padding:.8rem .85rem; cursor:pointer; transition:transform .15s, border-color .15s, box-shadow .15s; font-family:var(--body); }
.chip:hover:not(.off) { transform:translateY(-2px); }
.chip:focus-visible { outline:3px solid var(--saffron); outline-offset:2px; }
.chip.off { cursor:default; opacity:.6; }
.chip__cls { font-family:var(--mono); font-weight:700; font-size:.98rem; color:var(--ink); }
.chip__win { font-size:.72rem; color:#8a90a0; }
.chip__row { display:flex; align-items:center; justify-content:space-between; margin-top:.15rem; }
.chip__fee { font-family:var(--mono); font-weight:700; font-size:.88rem; color:var(--ink); }
.chip__fee.free { color:var(--emerald); }
.chip__enr { font-size:.72rem; font-weight:700; color:var(--emerald); }
.chip__closed { font-size:.72rem; color:#9aa0ad; }
.chip__check { width:21px; height:21px; border-radius:7px; border:2px solid #D9C9A6; display:grid; place-items:center; color:#fff; transition:.15s; }
.chip__check svg { width:12px; height:12px; }

.sheet__foot { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.95rem 1.3rem; border-top:1px solid var(--paper-line); background:#fff; }
.sheet__count { font-size:.86rem; font-weight:600; color:var(--ink-70); }

/* modal transitions: fade overlay, scale sheet (slide-up on mobile) */
.modal-enter-active, .modal-leave-active { transition:opacity .25s ease; }
.modal-enter-active .sheet, .modal-leave-active .sheet { transition:transform .28s cubic-bezier(.2,.8,.2,1), opacity .25s; }
.modal-enter-from, .modal-leave-to { opacity:0; }
.modal-enter-from .sheet, .modal-leave-to .sheet { transform:scale(.94) translateY(14px); opacity:0; }

@media (max-width:640px){
    .overlay { place-items:end center; padding:0; }
    .sheet { max-width:none; border-radius:22px 22px 0 0; max-height:88vh; }
    .modal-enter-from .sheet, .modal-leave-to .sheet { transform:translateY(100%); }
}

/* sticky summary */
.summary { position:fixed; bottom:18px; left:0; right:0; z-index:70; }
.summary__inner { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; background:linear-gradient(135deg,#1B2748,#0A1024); color:var(--paper); border-radius:18px; padding:.9rem 1.2rem; box-shadow:0 26px 50px -18px rgba(10,16,36,.6); }
.s-l { display:flex; align-items:center; gap:.8rem; min-width:0; }
.cnt { width:38px; height:38px; border-radius:11px; flex-shrink:0; display:grid; place-items:center; font-family:var(--mono); font-weight:700; color:var(--ink); background:linear-gradient(135deg,var(--gold-lt),var(--gold)); }
.s-l strong { font-size:.92rem; } .s-l small { display:block; font-size:.76rem; color:rgba(251,246,236,.55); }
.s-r { display:flex; align-items:center; gap:1rem; }
.tot { display:flex; flex-direction:column; align-items:flex-end; line-height:1.1; }
.tot small { font-size:.68rem; color:rgba(251,246,236,.5); } .tot strong { font-family:var(--mono); font-size:1.2rem; color:var(--gold-lt); }
.tot strong.free { color:#34d399; }

/* footer */
.foot-band { background:var(--ink); color:rgba(251,246,236,.7); margin-top:3rem; }
.foot-band__inner { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding-top:1.6rem; padding-bottom:1.6rem; flex-wrap:wrap; }
.foot-band p { font-size:.84rem; margin:0; }

/* toast */
.toast { position:fixed; top:84px; right:1.1rem; z-index:90; padding:.8rem 1.1rem; border-radius:13px; font-size:.9rem; font-weight:600; color:#fff; box-shadow:0 18px 40px -14px rgba(10,16,36,.5); }
.toast.success { background:linear-gradient(135deg,#1aa177,#168A66); }
.toast.info { background:linear-gradient(135deg,#3a5bd0,#2C49A6); }
.toast.error { background:linear-gradient(135deg,#ef4444,#DC2626); }
.toast-enter-active,.toast-leave-active { transition:all .35s cubic-bezier(.2,.7,.2,1); }
.toast-enter-from,.toast-leave-to { opacity:0; transform:translateX(30px); }
.bar-enter-active,.bar-leave-active { transition:all .3s cubic-bezier(.2,.7,.2,1); }
.bar-enter-from,.bar-leave-to { opacity:0; transform:translateY(20px); }

@media (prefers-reduced-motion: reduce) {
    .scard, .chip, .choose svg, .btn, .modal-enter-active, .modal-leave-active,
    .modal-enter-active .sheet, .modal-leave-active .sheet,
    .toast-enter-active, .toast-leave-active, .bar-enter-active, .bar-leave-active { transition:none !important; }
}
</style>
