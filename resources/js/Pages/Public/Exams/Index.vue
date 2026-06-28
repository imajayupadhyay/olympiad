<script setup>
import PublicHeader from '@/Components/Public/PublicHeader.vue';
import SeoHead from '@/Components/Shared/SeoHead.vue';
import AppLogo from '@/Components/Shared/AppLogo.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    exams: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    classLevels: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const year = new Date().getFullYear();

/* filters */
const filters = reactive({
    search: props.filters.search ?? '',
    subject_id: props.filters.subject_id ?? '',
    class_level_id: props.filters.class_level_id ?? '',
});
let t = null;
const apply = () => router.get(route('exams'), { ...filters }, { preserveState: true, replace: true, preserveScroll: true });
watch(() => [filters.subject_id, filters.class_level_id], apply);
watch(() => filters.search, () => { clearTimeout(t); t = setTimeout(apply, 350); });

/* selection */
const picked = ref([]);
const selectable = (e) => !e.is_enrolled && e.availability !== 'closed';
const toggle = (e) => { if (!selectable(e)) return; const i = picked.value.indexOf(e.id); i === -1 ? picked.value.push(e.id) : picked.value.splice(i, 1); };
const selected = computed(() => props.exams.filter((e) => picked.value.includes(e.id)));
const total = computed(() => selected.value.reduce((s, e) => s + e.fee_amount, 0));
const hasPaid = computed(() => selected.value.some((e) => !e.is_free));

const enrollForm = useForm({ exam_ids: [] });
const enroll = () => { enrollForm.exam_ids = [...picked.value]; enrollForm.post(route('exams.enroll')); };

/* toast */
const toast = ref(null);
let tt = null;
watch(() => page.props.flash, (f) => {
    const msg = f?.success || f?.info || f?.error; if (!msg) return;
    toast.value = { type: f?.error ? 'error' : (f?.success ? 'success' : 'info'), msg };
    clearTimeout(tt); tt = setTimeout(() => (toast.value = null), 4000);
}, { deep: true, immediate: true });

const availMeta = (a) => ({ upcoming: { l: 'Upcoming', c: 'av-up' }, live: { l: 'Live now', c: 'av-live' }, closed: { l: 'Closed', c: 'av-closed' } }[a] ?? { l: '', c: '' });
</script>

<template>
    <SeoHead
        title="Olympiad Exams"
        description="Browse and enrol in upcoming National Olympiad Hunt exams by subject and class. Compare fees, schedules and syllabus, then register online in minutes." />

    <div class="noh">
        <!-- toast -->
        <Transition name="toast"><div v-if="toast" class="toast" :class="toast.type">{{ toast.msg }}</div></Transition>

        <!-- global header -->
        <PublicHeader />

        <!-- hero -->
        <section class="hero">
            <div class="blob a"></div><div class="blob b"></div>
            <div class="wrap hero__inner">
                <span class="eyebrow">Olympiad Catalogue</span>
                <h1>Explore every <span class="ital">olympiad</span>.</h1>
                <p>Browse all live and upcoming exams across subjects and classes. Select the ones you want and enrol in a single step.</p>

                <div class="filters">
                    <div class="search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <input v-model="filters.search" type="text" placeholder="Search exams…" />
                    </div>
                    <select v-model="filters.subject_id"><option value="">All subjects</option><option v-for="s in subjects" :key="s.id" :value="String(s.id)">{{ s.name }}</option></select>
                    <select v-model="filters.class_level_id"><option value="">All classes</option><option v-for="c in classLevels" :key="c.id" :value="String(c.id)">{{ c.label }}</option></select>
                </div>
            </div>
        </section>

        <!-- grid -->
        <section class="wrap list-wrap">
            <div v-if="exams.length" class="grid">
                <div v-for="e in exams" :key="e.id" class="card" :class="{ on: picked.includes(e.id), enrolled: e.is_enrolled }" @click="toggle(e)">
                    <div class="top">
                        <span class="avail" :class="availMeta(e.availability).c"><span class="dot"></span>{{ availMeta(e.availability).l }}</span>
                    </div>
                    <h3>{{ e.subject?.name ?? e.name }}</h3>
                    <p v-if="e.description" class="desc">{{ e.description }}</p>
                    <div class="foot">
                        <span class="fee" :class="{ free: e.is_free }">{{ e.is_free ? 'FREE' : '₹' + e.fee_amount.toLocaleString('en-IN') }}</span>
                        <span v-if="e.is_enrolled" class="enr">✓ Enrolled</span>
                        <span v-else-if="e.availability === 'closed'" class="cl">Closed</span>
                        <span v-else class="check" :class="{ on: picked.includes(e.id) }"><svg v-if="picked.includes(e.id)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg></span>
                    </div>
                </div>
            </div>
            <div v-else class="empty">
                <div class="ic">📭</div><h3>No exams found</h3><p>Try a different subject or class filter.</p>
            </div>
        </section>

        <!-- sticky summary -->
        <Transition name="bar">
            <div v-if="selected.length" class="summary">
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

/* nav */
.nav { position:fixed; top:0; left:0; right:0; z-index:80; transition:.3s; background:transparent; border-bottom:1px solid transparent; }
.nav.scrolled { background:rgba(251,246,236,.78); backdrop-filter:blur(14px) saturate(150%); -webkit-backdrop-filter:blur(14px) saturate(150%); border-bottom-color:var(--paper-line); }
.nav__inner { display:flex; align-items:center; justify-content:space-between; height:72px; }
.brand { display:flex; align-items:center; gap:.6rem; text-decoration:none; }
.brand-mark { font-family:var(--display); font-weight:700; color:var(--ink); background:linear-gradient(135deg,var(--gold-lt),var(--gold)); padding:.3rem .55rem; border-radius:11px; font-size:.9rem; }
.brand-name { font-weight:700; color:var(--ink); font-size:.95rem; }
.nav__links { display:flex; gap:2px; }
.nav__links :deep(a) { font:600 14.5px/1 var(--body); padding:10px 14px; border-radius:10px; color:var(--ink-70); text-decoration:none; transition:.2s; }
.nav__links :deep(a:hover) { color:var(--ink); }
.nav__links :deep(a.active) { color:var(--saffron-dk); }
.nav__cta { display:flex; align-items:center; gap:10px; }
@media (max-width:820px){ .nav__links{ display:none; } }

.btn { display:inline-flex; align-items:center; justify-content:center; font:700 14px/1 var(--body); padding:11px 18px; border-radius:11px; border:0; cursor:pointer; text-decoration:none; transition:transform .15s, box-shadow .2s; }
.btn-primary { background:linear-gradient(135deg,#F2854E,var(--saffron)); color:#fff; box-shadow:0 12px 26px -12px rgba(238,106,44,.8); }
.btn-primary:hover { transform:translateY(-2px); }
.btn-primary:disabled { opacity:.6; cursor:progress; }
.btn-ghost { background:transparent; color:var(--ink-70); border:1px solid var(--paper-line); }
.btn-ghost.light { color:rgba(251,246,236,.85); border-color:rgba(251,246,236,.25); }

/* hero */
.hero { position:relative; overflow:hidden; padding:3rem 0 2.5rem; }
.blob { position:absolute; border-radius:50%; filter:blur(70px); z-index:0; }
.blob.a { width:420px; height:420px; top:-180px; right:-100px; background:radial-gradient(circle,rgba(238,106,44,.28),transparent 70%); }
.blob.b { width:360px; height:360px; bottom:-200px; left:-120px; background:radial-gradient(circle,rgba(44,73,166,.22),transparent 70%); }
.hero__inner { position:relative; z-index:1; }
.eyebrow { display:inline-block; font-family:var(--mono); font-weight:600; font-size:.74rem; letter-spacing:.14em; text-transform:uppercase; color:var(--saffron); background:rgba(238,106,44,.1); padding:.3rem .7rem; border-radius:999px; }
.hero h1 { font-family:var(--display); font-weight:600; font-size:clamp(2rem,4vw,3rem); line-height:1.08; margin:.9rem 0 .6rem; }
.hero h1 .ital { font-style:italic; color:var(--saffron); }
.hero p { color:var(--ink-55); font-size:1.05rem; max-width:46ch; margin:0 0 1.6rem; }

.filters { display:flex; flex-wrap:wrap; gap:.7rem; }
.search { position:relative; flex:1; min-width:220px; display:flex; align-items:center; background:#fff; border:1.5px solid var(--paper-line); border-radius:12px; }
.search svg { width:18px; height:18px; margin-left:.8rem; color:#9aa0ad; }
.search input { flex:1; border:0; background:transparent; outline:none; padding:.75rem .8rem; font-size:.95rem; font-family:var(--body); color:var(--ink); }
.filters select { background:#fff; border:1.5px solid var(--paper-line); border-radius:12px; padding:.75rem .9rem; font-size:.92rem; color:var(--ink); cursor:pointer; font-family:var(--body); outline:none; }
.search:focus-within, .filters select:focus { border-color:var(--saffron); }

/* grid */
.list-wrap { padding-top:.5rem; }
.grid { display:grid; gap:1.1rem; grid-template-columns:repeat(auto-fill,minmax(290px,1fr)); }
.card { background:#fff; border:1.5px solid var(--paper-line); border-radius:18px; padding:1.2rem; cursor:pointer; transition:transform .18s, box-shadow .2s, border-color .2s; box-shadow:0 2px 8px rgba(10,16,36,.04); display:flex; flex-direction:column; gap:.65rem; }
.card:hover { transform:translateY(-3px); box-shadow:0 22px 44px -24px rgba(10,16,36,.3); }
.card.on { border-color:var(--saffron); box-shadow:0 0 0 3px rgba(238,106,44,.14); }
.card.enrolled { cursor:default; }
.top { display:flex; align-items:center; justify-content:flex-end; gap:.5rem; }
.avail { display:inline-flex; align-items:center; gap:.3rem; font-size:.72rem; font-weight:700; padding:.2rem .55rem; border-radius:999px; }
.avail .dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
.av-up { background:rgba(44,73,166,.12); color:var(--royal); }
.av-live { background:rgba(22,138,102,.14); color:var(--emerald); }
.av-closed { background:rgba(91,99,115,.14); color:#5B6373; }
.card h3 { font-family:var(--display); font-weight:600; font-size:1.12rem; color:var(--ink); margin:0; line-height:1.25; }
.desc { font-size:.86rem; color:#5B6373; line-height:1.45; margin:0; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
.foot { display:flex; align-items:center; justify-content:space-between; padding-top:.7rem; border-top:1px solid #F0E6D2; margin-top:.2rem; }
.fee { font-family:var(--mono); font-weight:700; font-size:1.05rem; color:var(--ink); }
.fee.free { color:var(--emerald); }
.enr { font-size:.8rem; font-weight:700; color:var(--emerald); }
.cl { font-size:.78rem; color:#9aa0ad; }
.check { width:24px; height:24px; border-radius:8px; border:2px solid #D9C9A6; display:grid; place-items:center; color:#fff; transition:.15s; }
.check.on { background:var(--saffron); border-color:var(--saffron); }
.check svg { width:14px; height:14px; }

.empty { text-align:center; padding:4rem 1rem; color:#5B6373; }
.empty .ic { font-size:2.4rem; }
.empty h3 { font-family:var(--display); color:var(--ink); margin:.5rem 0 .3rem; }

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
.brand.light .brand-name { color:#fff; }
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
</style>
