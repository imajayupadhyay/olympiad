<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import ExamSelectCard from './Components/ExamSelectCard.vue';
import EnrolledExamCard from './Components/EnrolledExamCard.vue';
import SelectionSummary from './Components/SelectionSummary.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    enrolledExams: { type: Array, default: () => [] },
    available: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    classLevels: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    referral: { type: Object, default: null },
    discounts: { type: Array, default: () => [] }, // student's usable personal discount rules
});

const page = usePage();

/* ── Refer & Earn (same container + behaviour as registration Step 2) ── */
const showReferral = ref(false);
const toggleReferral = () => { showReferral.value = !showReferral.value; };

const stats = computed(() => props.referral?.stats ?? {});
// At a threshold boundary (toward resets to 0 but a reward was earned), show the
// meter as completed rather than empty.
const justEarned = computed(() => (stats.value.rewarded ?? 0) > 0 && stats.value.toward === 0);
const displayToward = computed(() => justEarned.value ? stats.value.threshold : stats.value.toward);
const progressPct = computed(() => {
    const s = stats.value;
    if (!s.threshold) return 0;
    if (justEarned.value) return 100;
    return Math.min(100, Math.round((s.toward / s.threshold) * 100));
});
// Middle stat label reflects how referrals qualify (admin setting).
const progressStatLabel = computed(() => ({
    link_click: 'Opens',
    registration: 'Joined',
    first_paid_enrollment: 'Enrolled',
}[stats.value.mode] || 'Joined'));

const copied = ref(false);
const copyLink = async () => {
    if (!props.referral?.link) return;
    try {
        await navigator.clipboard.writeText(props.referral.link);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch { /* clipboard unavailable */ }
};
const shareText = computed(() =>
    `Join me on National Olympiad Hunt!${props.referral?.welcome ? ` Sign up with my link and get ${props.referral.welcome} on your first olympiad` : ' Sign up with my link'}: ${props.referral?.link}`
);
const waLink = computed(() => `https://wa.me/?text=${encodeURIComponent(shareText.value)}`);
const mailLink = computed(() => `mailto:?subject=${encodeURIComponent('Join National Olympiad Hunt')}&body=${encodeURIComponent(shareText.value)}`);
const nativeShare = async () => {
    if (navigator.share) {
        try { await navigator.share({ title: 'National Olympiad Hunt', text: shareText.value, url: props.referral.link }); } catch { /* cancelled */ }
    } else {
        copyLink();
    }
};

/* ── filters ── */
const filters = reactive({
    search: props.filters.search ?? '',
    subject_id: props.filters.subject_id ?? '',
    class_level_id: props.filters.class_level_id ?? '',
});
let searchTimer = null;
const applyFilters = () => router.get(route('student.exams'), { ...filters }, { preserveState: true, replace: true, preserveScroll: true });
watch(() => [filters.subject_id, filters.class_level_id], applyFilters);
watch(() => filters.search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 350); });

const hasActiveFilters = computed(() => filters.search || filters.subject_id || (filters.class_level_id && filters.class_level_id !== 'all'));
const clearFilters = () => { filters.search = ''; filters.subject_id = ''; filters.class_level_id = 'all'; };

/* ── selection (available only) ── */
const selectedIds = ref([]);
const toggle = (id) => {
    const i = selectedIds.value.indexOf(id);
    i === -1 ? selectedIds.value.push(id) : selectedIds.value.splice(i, 1);
};
const selectedExams = computed(() => props.available.filter((e) => selectedIds.value.includes(e.id)));

const enrollForm = useForm({ exam_ids: [] });
const checkout = () => {
    enrollForm.exam_ids = [...selectedIds.value];
    enrollForm.post(route('student.exams.enroll'), { preserveScroll: true, onSuccess: () => { selectedIds.value = []; } });
};

/* ── toast ── */
const toast = ref(null);
let toastTimer = null;
watch(() => page.props.flash, (f) => {
    const msg = f?.success || f?.info || f?.error; if (!msg) return;
    toast.value = { type: f?.error ? 'error' : (f?.success ? 'success' : 'info'), msg };
    clearTimeout(toastTimer); toastTimer = setTimeout(() => (toast.value = null), 3600);
}, { deep: true, immediate: true });
</script>

<template>
    <Head title="Exams" />

    <StudentLayout title="Exams">
        <Transition name="toast"><div v-if="toast" class="toast" :class="toast.type">{{ toast.msg }}</div></Transition>

        <!-- ═══ My enrolled exams ═══ -->
        <section v-if="enrolledExams.length" class="block">
            <div class="block-head">
                <div class="bh-l">
                    <span class="bh-ic ic-green">🎯</span>
                    <div>
                        <h2>My Exams</h2>
                        <p>Exams you're enrolled in — start, continue or view results.</p>
                    </div>
                </div>
                <span class="count-pill">{{ enrolledExams.length }}</span>
            </div>
            <div class="grid">
                <EnrolledExamCard v-for="ex in enrolledExams" :key="ex.id" :exam="ex" />
            </div>
        </section>

        <!-- ═══ Browse / available ═══ -->
        <section class="block">
            <div class="block-head">
                <div class="bh-l">
                    <span class="bh-ic ic-saffron">🧭</span>
                    <div>
                        <h2>Browse Olympiads</h2>
                        <p>Discover new exams and enrol in one step.</p>
                    </div>
                </div>
                <button v-if="referral" type="button" class="refer-btn" :class="{ open: showReferral }" @click="toggleReferral">
                    <span class="rb-ic">🎁</span>
                    <span>{{ showReferral ? 'Hide' : 'Refer & Earn' }}</span>
                    <svg class="rb-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
                </button>
            </div>

            <!-- Referral: share your link & earn — revealed on button click. Same container/functionality as the registration Step 2 page. -->
            <Transition name="refslide">
                <div v-if="referral && showReferral" class="referral">
                    <div v-if="referral.applied" class="ref-applied">
                        <span class="ref-ic">🎁</span>
                        <span>Welcome discount unlocked{{ referral.welcome ? ` — you'll get ${referral.welcome} at payment` : '' }}.</span>
                    </div>

                    <div class="ref-share">
                        <div class="rs-glow" aria-hidden="true"></div>

                        <div class="rs-top">
                            <span class="rs-eyebrow">Refer &amp; earn</span>
                            <span v-if="referral.reward" class="rs-reward">{{ referral.reward }}</span>
                        </div>

                        <h3 class="rs-title">Invite friends, <em>earn rewards</em></h3>
                        <p class="rs-sub">
                            Share your link.<template v-if="referral.welcome"> Each friend who joins gets {{ referral.welcome }} —</template>
                            <template v-else> Each friend who joins</template> and brings you closer to your reward.
                        </p>

                        <!-- reward-progress meter -->
                        <div class="rs-meter">
                            <div class="rs-meter-head">
                                <span class="rs-meter-label">Reward progress</span>
                                <span class="rs-frac"><strong>{{ displayToward }}</strong><i>/{{ stats.threshold }}</i></span>
                            </div>
                            <div class="rs-track">
                                <div class="rs-fill" :style="{ width: progressPct + '%' }"></div>
                                <span class="rs-medal" :class="{ lit: progressPct >= 100 }">🏅</span>
                            </div>
                            <p class="rs-caption">
                                <template v-if="stats.rewarded > 0 && stats.toward === 0">
                                    Reward unlocked! Refer {{ stats.threshold }} more for another.
                                </template>
                                <template v-else>
                                    Refer <strong>{{ stats.remaining }}</strong> more friend{{ stats.remaining === 1 ? '' : 's' }} to unlock {{ referral.reward || 'your reward' }}.
                                </template>
                            </p>
                        </div>

                        <!-- live counts -->
                        <div class="rs-stats">
                            <div class="rs-stat"><strong>{{ stats.referred }}</strong><span>Invited</span></div>
                            <div class="rs-stat"><strong>{{ stats.progress }}</strong><span>{{ progressStatLabel }}</span></div>
                            <div class="rs-stat"><strong>{{ stats.rewarded }}</strong><span>Rewards</span></div>
                        </div>

                        <div class="ref-link-row">
                            <div class="ref-link-box"><span>{{ referral.link }}</span></div>
                            <button type="button" class="ref-copy" :class="{ done: copied }" @click="copyLink">{{ copied ? 'Copied ✓' : 'Copy' }}</button>
                        </div>

                        <div class="ref-share-btns">
                            <a class="sh wa" :href="waLink" target="_blank" rel="noopener">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.607zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                WhatsApp
                            </a>
                            <a class="sh mail" :href="mailLink">Email</a>
                            <button type="button" class="sh more" @click="nativeShare">More…</button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- modern filter bar -->
            <div class="filterbar">
                <div class="search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input v-model="filters.search" type="text" placeholder="Search exams…" />
                </div>
                <div class="selset">
                    <label class="sel">
                        <span class="sel-ic">📚</span>
                        <select v-model="filters.subject_id">
                            <option value="">All subjects</option>
                            <option v-for="s in subjects" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                        </select>
                        <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </label>
                    <label class="sel">
                        <span class="sel-ic">🎓</span>
                        <select v-model="filters.class_level_id">
                            <option value="all">All classes</option>
                            <option v-for="c in classLevels" :key="c.id" :value="String(c.id)">{{ c.label }}</option>
                        </select>
                        <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </label>
                    <button v-if="hasActiveFilters" class="clear" @click="clearFilters">Clear</button>
                </div>
            </div>

            <div v-if="available.length" class="grid">
                <ExamSelectCard
                    v-for="exam in available"
                    :key="exam.id"
                    :exam="exam"
                    :selected="selectedIds.includes(exam.id)"
                    @toggle="toggle"
                />
            </div>
            <div v-else class="empty">
                <div class="empty-ic">📭</div>
                <h3>No exams found</h3>
                <p>{{ hasActiveFilters ? 'Try a different filter.' : 'You\'re enrolled in everything available for now — check back soon!' }}</p>
            </div>
        </section>

        <SelectionSummary :items="selectedExams" :discounts="discounts" :processing="enrollForm.processing" @checkout="checkout" @clear="selectedIds = []" />
    </StudentLayout>
</template>

<style scoped>
.toast { position: fixed; top: 1.1rem; right: 1.1rem; z-index: 60; padding: .8rem 1.1rem; border-radius: 13px; font-size: .9rem; font-weight: 600; color: #fff; box-shadow: 0 18px 40px -14px rgba(10,16,36,.5); }
.toast.success { background: linear-gradient(135deg, #1aa177, #168A66); }
.toast.info { background: linear-gradient(135deg, #3a5bd0, #2C49A6); }
.toast.error { background: linear-gradient(135deg, #ef4444, #DC2626); }
.toast-enter-active, .toast-leave-active { transition: all .35s cubic-bezier(.2,.7,.2,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(30px); }

.block { margin-bottom: 2rem; }
.block-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.1rem; }
.bh-l { display: flex; align-items: center; gap: .8rem; }
.bh-ic { width: 44px; height: 44px; flex-shrink: 0; border-radius: 13px; display: grid; place-items: center; font-size: 1.3rem; }
.ic-green { background: rgba(22,138,102,.12); }
.ic-saffron { background: rgba(238,106,44,.12); }
.block-head h2 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.4rem; color: #0A1024; margin: 0; line-height: 1.1; }
.block-head p { color: #5B6373; font-size: .86rem; margin: .15rem 0 0; }
.count-pill { font-family: "Space Grotesk", monospace; font-weight: 700; font-size: .95rem; color: #168A66; background: rgba(22,138,102,.12); padding: .35rem .8rem; border-radius: 999px; }

/* modern filter bar */
.filterbar { display: flex; flex-wrap: wrap; align-items: center; gap: .7rem; background: #fff; border: 1px solid #E7D9BE; border-radius: 16px; padding: .7rem; margin-bottom: 1.3rem; box-shadow: 0 2px 8px rgba(10,16,36,.04); }
.search { position: relative; flex: 1; min-width: 200px; display: flex; align-items: center; background: #FBF9F3; border: 1.5px solid transparent; border-radius: 11px; transition: border-color .2s, background .2s; }
.search:focus-within { border-color: #EE6A2C; background: #fff; }
.search svg { width: 18px; height: 18px; margin-left: .8rem; color: #9aa0ad; }
.search input { flex: 1; border: 0; background: transparent; outline: none; padding: .65rem .8rem; font-size: .92rem; font-family: "Plus Jakarta Sans", sans-serif; color: #0A1024; }
.selset { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.sel { position: relative; display: inline-flex; align-items: center; background: #FBF9F3; border: 1.5px solid transparent; border-radius: 11px; padding-left: .7rem; transition: border-color .2s, background .2s; }
.sel:focus-within { border-color: #EE6A2C; background: #fff; }
.sel-ic { font-size: .95rem; }
.sel select { appearance: none; -webkit-appearance: none; border: 0; background: transparent; outline: none; padding: .65rem 2rem .65rem .5rem; font-size: .9rem; font-family: "Plus Jakarta Sans", sans-serif; color: #0A1024; cursor: pointer; }
.sel .chev { position: absolute; right: .6rem; width: 16px; height: 16px; color: #9aa0ad; pointer-events: none; }
.clear { border: 0; background: transparent; color: #C9501A; font-weight: 700; font-size: .85rem; cursor: pointer; padding: .4rem .7rem; }
.clear:hover { text-decoration: underline; }

.grid { display: grid; gap: 1.1rem; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }

.empty { text-align: center; padding: 3rem 1rem; color: #5B6373; background: #fff; border: 1px dashed #E7D9BE; border-radius: 16px; }
.empty-ic { font-size: 2.2rem; margin-bottom: .4rem; }
.empty h3 { font-family: "Fraunces", serif; color: #0A1024; margin: 0 0 .3rem; }
.empty p { font-size: .9rem; margin: 0; }

/* Refer & Earn toggle button */
.refer-btn { display: inline-flex; align-items: center; gap: .5rem; flex-shrink: 0; cursor: pointer; font-weight: 700; font-size: .88rem; color: #C9501A;
    background: rgba(238,106,44,.1); border: 1.5px solid rgba(238,106,44,.28); border-radius: 12px; padding: .6rem 1rem; transition: background .2s, border-color .2s, transform .15s; }
.refer-btn:hover { background: rgba(238,106,44,.16); border-color: rgba(238,106,44,.45); transform: translateY(-1px); }
.refer-btn .rb-ic { font-size: 1rem; }
.refer-btn .rb-chev { width: 16px; height: 16px; transition: transform .25s; }
.refer-btn.open .rb-chev { transform: rotate(180deg); }

/* Referral card (same container as registration Step 2) */
.referral { margin-bottom: 1.3rem; display: grid; gap: .8rem; }
.ref-applied { display: flex; align-items: center; gap: .6rem; font-size: .88rem; font-weight: 600; color: #168A66;
    background: rgba(22,138,102,.1); border: 1px solid rgba(22,138,102,.22); border-radius: 12px; padding: .65rem .85rem; }
.ref-applied .ref-ic { font-size: 1.1rem; }

.ref-share { position: relative; overflow: hidden; padding: 1.3rem 1.3rem 1.2rem; border-radius: 20px; color: #FBF6EC;
    background: linear-gradient(150deg, #1B2748 0%, #131C3D 58%, #0A1024 100%);
    border: 1px solid rgba(242,200,75,.18);
    box-shadow: 0 24px 56px -30px rgba(10,16,36,.7), inset 0 1px 0 rgba(251,246,236,.05); }
.rs-glow { position: absolute; top: -70px; right: -50px; width: 200px; height: 200px; border-radius: 50%; pointer-events: none;
    background: radial-gradient(circle, rgba(242,200,75,.22), transparent 70%); }

.rs-top { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.rs-eyebrow { font-family: "Space Grotesk", monospace; font-size: .68rem; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; color: #F2C84B; }
.rs-reward { font-family: "Space Grotesk", monospace; font-weight: 700; font-size: .82rem; color: #0A1024;
    background: linear-gradient(135deg, #F2C84B, #D6991F); padding: .28rem .65rem; border-radius: 8px;
    box-shadow: 0 6px 16px -6px rgba(214,153,31,.7); white-space: nowrap; }

.rs-title { position: relative; margin: .65rem 0 .3rem; font-family: "Fraunces", Georgia, serif; font-weight: 600; font-size: 1.25rem; line-height: 1.15; }
.rs-title em { font-style: italic; color: #F2C84B; }
.rs-sub { position: relative; margin: 0 0 1.1rem; font-size: .82rem; line-height: 1.55; color: rgba(251,246,236,.66); max-width: 46ch; }

/* reward-progress meter — the signature element */
.rs-meter { position: relative; margin-bottom: 1.1rem; }
.rs-meter-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: .5rem; }
.rs-meter-label { font-size: .74rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: rgba(251,246,236,.55); }
.rs-frac { font-family: "Space Grotesk", monospace; color: rgba(251,246,236,.5); font-size: .9rem; }
.rs-frac strong { color: #F2C84B; font-size: 1.15rem; }
.rs-frac i { font-style: normal; }
.rs-track { position: relative; height: 12px; border-radius: 999px; background: rgba(251,246,236,.1);
    box-shadow: inset 0 1px 2px rgba(0,0,0,.35); }
.rs-fill { height: 100%; min-width: 12px; border-radius: 999px;
    background: linear-gradient(90deg, #D6991F, #F2C84B); box-shadow: 0 0 14px -2px rgba(242,200,75,.7);
    transition: width .6s cubic-bezier(.2,.8,.2,1); }
.rs-medal { position: absolute; top: 50%; right: -6px; transform: translateY(-50%); font-size: 1.25rem; line-height: 1;
    filter: grayscale(1) brightness(.7); opacity: .8; transition: filter .3s, transform .3s; }
.rs-medal.lit { filter: none; transform: translateY(-50%) scale(1.18); }
.rs-caption { margin: .6rem 0 0; font-size: .78rem; color: rgba(251,246,236,.62); }
.rs-caption strong { color: #fff; font-family: "Space Grotesk", monospace; }

/* live counts */
.rs-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; margin-bottom: 1.1rem;
    border-top: 1px solid rgba(251,246,236,.1); border-bottom: 1px solid rgba(251,246,236,.1); padding: .8rem 0; }
.rs-stat { text-align: center; position: relative; }
.rs-stat + .rs-stat::before { content: ""; position: absolute; left: 0; top: 50%; transform: translateY(-50%); height: 60%; width: 1px; background: rgba(251,246,236,.1); }
.rs-stat strong { display: block; font-family: "Space Grotesk", monospace; font-size: 1.5rem; font-weight: 700; color: #fff; line-height: 1; font-variant-numeric: tabular-nums; }
.rs-stat span { font-size: .7rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: rgba(251,246,236,.5); }

.ref-link-row { display: flex; gap: .5rem; }
.ref-link-box { flex: 1; min-width: 0; display: flex; align-items: center; background: rgba(251,246,236,.08);
    border: 1px solid rgba(251,246,236,.16); border-radius: 11px; padding: .6rem .8rem; overflow: hidden; }
.ref-link-box span { font-family: "Space Grotesk", monospace; font-size: .82rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ref-copy { border: 0; cursor: pointer; font-weight: 700; font-size: .82rem; padding: 0 1.1rem; border-radius: 11px; min-width: 72px;
    background: linear-gradient(135deg, #F2854E, #EE6A2C); color: #fff; transition: transform .15s, background .2s; }
.ref-copy:hover { transform: translateY(-1px); }
.ref-copy.done { background: linear-gradient(135deg, #1aa177, #168A66); }

.ref-share-btns { display: flex; gap: .5rem; margin-top: .7rem; flex-wrap: wrap; }
.ref-share-btns .sh { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid rgba(251,246,236,.16); cursor: pointer; text-decoration: none; font-weight: 600; font-size: .8rem;
    padding: .5rem .9rem; border-radius: 10px; background: rgba(251,246,236,.1); color: #fff; transition: background .2s, transform .15s; }
.ref-share-btns .sh:hover { background: rgba(251,246,236,.18); transform: translateY(-1px); }
.ref-share-btns .sh svg { width: 15px; height: 15px; }
.ref-share-btns .sh.wa { background: rgba(37,211,102,.16); border-color: rgba(37,211,102,.42); }
.ref-share-btns .sh.wa svg { color: #25D366; }

/* reveal transition */
.refslide-enter-active, .refslide-leave-active { transition: opacity .3s ease, transform .3s cubic-bezier(.2,.8,.2,1); }
.refslide-enter-from, .refslide-leave-to { opacity: 0; transform: translateY(-10px); }

@media (prefers-reduced-motion: reduce) {
    .rs-fill, .rs-medal, .ref-share-btns .sh, .ref-copy, .refer-btn .rb-chev, .refslide-enter-active, .refslide-leave-active { transition: none; }
}
</style>
