<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { usePoll } from '@/composables/usePoll';

const props = defineProps({
    myExams: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    referral: { type: Object, default: null },
    onboarding: { type: Object, default: () => ({}) },
});

// Keep the referral widget counters fresh (≤5s) without a manual refresh.
usePoll(['referral']);

const referralProgressLabel = computed(() => ({
    link_share: 'shares',
    link_click: 'link opens',
    registration: 'joined',
    first_paid_enrollment: 'enrolled',
}[props.referral?.mode] || 'joined'));

// In "copy/share the link" mode, copying the link counts toward the reward.
// No-op in every other mode. Re-renders this page so the count updates instantly.
const trackShare = (channel) => {
    if (props.referral?.mode !== 'link_share') return;
    router.post(route('student.referrals.track-share'), { channel }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const referralCopied = ref(false);
const copyReferral = async () => {
    if (!props.referral?.link) return;
    try {
        await navigator.clipboard.writeText(props.referral.link);
        referralCopied.value = true;
        setTimeout(() => (referralCopied.value = false), 2000);
    } catch { /* clipboard unavailable */ }
    trackShare('copy');
};

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const firstName = computed(() => (user.value?.name ?? 'Student').split(/\s+/)[0]);

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
});
const today = new Date().toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

const fmt = (d) => d ? new Date(d).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : 'TBA';

const statCards = computed(() => [
    { label: 'Enrolled', value: props.stats.enrolled ?? 0, icon: '🎯' },
    { label: 'Completed', value: props.stats.completed ?? 0, icon: '✅' },
    { label: 'Results', value: props.stats.results ?? 0, icon: '📊' },
    { label: 'Certificates', value: props.stats.certificates ?? 0, icon: '🏅' },
]);

const startExam = (examId) => router.post(route('student.exams.start', examId));

/* ── First-login onboarding modal ──────────────────────────────
   Shown while the student is still on the auto-generated password we emailed.
   Nudges them to secure the account + finish their profile, then sends them
   to the profile page. "Maybe later" hides it for the rest of this session. */
const profile = computed(() => props.onboarding?.profile ?? { percent: 0, filled: 0, total: 0, missing: [] });
const dismissKey = computed(() => `noh_pw_nudge_${user.value?.id ?? 'anon'}`);

const showOnboarding = ref(false);
onMounted(() => {
    let dismissed = false;
    try { dismissed = sessionStorage.getItem(dismissKey.value) === '1'; } catch { /* ignore */ }
    if (props.onboarding?.must_change_password && !dismissed) {
        showOnboarding.value = true;
        // Fill the progress bar from 0 on the next frame so it animates in.
        requestAnimationFrame(() => { barWidth.value = profile.value.percent; });
    }
});

const barWidth = ref(0);

const dismissOnboarding = () => {
    showOnboarding.value = false;
    try { sessionStorage.setItem(dismissKey.value, '1'); } catch { /* ignore */ }
};

const goToProfile = () => {
    dismissOnboarding();
    router.visit(props.onboarding.profile_url);
};
</script>

<template>
    <Head title="Dashboard" />

    <StudentLayout title="Dashboard">
        <!-- hero -->
        <section class="hero">
            <div class="hero-blob a"></div>
            <div class="hero-blob b"></div>
            <div class="hero-body">
                <p class="hero-date">{{ today }}</p>
                <h2 class="hero-title">{{ greeting }}, <span class="ital">{{ firstName }}</span> 👋</h2>
                <p class="hero-sub">Here's everything happening with your olympiads.</p>
            </div>
            <div class="hero-trophy">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/>
                    <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
                    <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
                </svg>
            </div>
        </section>

        <!-- stats -->
        <section class="stats">
            <div v-for="s in statCards" :key="s.label" class="stat">
                <span class="stat-ic">{{ s.icon }}</span>
                <div><strong>{{ s.value }}</strong><span>{{ s.label }}</span></div>
            </div>
        </section>

        <!-- my exams -->
        <section class="block">
            <div class="block-head">
                <h3>My Exams</h3>
                <Link :href="route('student.exams')" class="link">Browse all →</Link>
            </div>

            <div v-if="myExams.length" class="ex-list">
                <div v-for="ex in myExams" :key="ex.exam.id" class="ex">
                    <span class="ex-subj">{{ ex.subject?.icon || '📝' }}</span>
                    <div class="ex-info">
                        <strong>{{ ex.exam.name }}</strong>
                        <small>
                            <template v-if="ex.availability === 'upcoming'">Opens {{ fmt(ex.starts_at) }}</template>
                            <template v-else-if="ex.availability === 'live'">Open until {{ fmt(ex.ends_at) }} · {{ ex.duration_minutes }} min</template>
                            <template v-else>Window closed</template>
                        </small>
                    </div>

                    <!-- CTA per state -->
                    <button v-if="ex.action === 'start'" class="cta start" @click="startExam(ex.exam.id)">Start →</button>
                    <Link v-else-if="ex.action === 'continue'" :href="route('student.exam-room', ex.attempt_id)" class="cta continue">Continue →</Link>
                    <Link v-else-if="ex.action === 'result'" :href="route('student.results.show', ex.result_id)" class="cta result">View result</Link>
                    <span v-else-if="ex.action === 'awaiting'" class="pill awaiting">Awaiting result</span>
                    <span v-else-if="ex.action === 'upcoming'" class="pill upcoming">Upcoming</span>
                    <span v-else class="pill closed">Closed</span>
                </div>
            </div>

            <div v-else class="empty">
                <p>You haven't enrolled in any exams yet.</p>
                <Link :href="route('student.exams')" class="cta start">Browse olympiads →</Link>
            </div>
        </section>

        <!-- refer & earn -->
        <section v-if="referral" class="refer">
            <div class="refer-l">
                <span class="refer-ic">🎁</span>
                <div>
                    <strong>Refer &amp; Earn</strong>
                    <small>{{ referral.progress }} {{ referralProgressLabel }} · {{ referral.rewards }} reward{{ referral.rewards === 1 ? '' : 's' }} ready to use</small>
                </div>
            </div>
            <div class="refer-r">
                <button class="refer-copy" :class="{ done: referralCopied }" @click="copyReferral">{{ referralCopied ? 'Copied ✓' : 'Copy link' }}</button>
                <Link :href="route('student.referrals')" class="refer-cta">Refer friends →</Link>
            </div>
        </section>

        <!-- first-login onboarding modal -->
        <Teleport to="body">
            <Transition name="onb">
                <div v-if="showOnboarding" class="onb-overlay" @click.self="dismissOnboarding">
                    <div class="onb-card" role="dialog" aria-modal="true" aria-labelledby="onb-title">
                        <button class="onb-x" @click="dismissOnboarding" aria-label="Close">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg>
                        </button>

                        <div class="onb-badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        </div>

                        <h3 id="onb-title" class="onb-title">Secure your <span class="ital">account</span></h3>
                        <p class="onb-sub">
                            You're signed in with the temporary password we emailed you. Set your own
                            password and finish your profile to unlock the full experience.
                        </p>

                        <!-- profile completion -->
                        <div class="onb-prog">
                            <div class="onb-prog-top">
                                <span>Profile completeness</span>
                                <strong>{{ profile.percent }}%</strong>
                            </div>
                            <div class="onb-bar"><span class="onb-bar-fill" :style="{ width: barWidth + '%' }"></span></div>
                            <p class="onb-prog-meta">
                                {{ profile.filled }} of {{ profile.total }} details added<template v-if="profile.missing.length"> · still needs:</template>
                            </p>
                            <div v-if="profile.missing.length" class="onb-chips">
                                <span v-for="m in profile.missing.slice(0, 4)" :key="m" class="onb-chip">{{ m }}</span>
                                <span v-if="profile.missing.length > 4" class="onb-chip more">+{{ profile.missing.length - 4 }}</span>
                            </div>
                        </div>

                        <button class="onb-cta" @click="goToProfile">Change password &amp; complete profile →</button>
                        <button class="onb-later" @click="dismissOnboarding">Maybe later</button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </StudentLayout>
</template>

<style scoped>
.hero { position: relative; overflow: hidden; border-radius: 24px; padding: 2rem 2.2rem; background: linear-gradient(135deg, #1B2748 0%, #131C3D 50%, #0A1024 100%); color: #FBF6EC; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; box-shadow: 0 30px 70px -28px rgba(10,16,36,.5); animation: fadeUp .6s cubic-bezier(.2,.7,.2,1) both; }
.hero-body { position: relative; z-index: 2; }
.hero-date { font-family: "Space Grotesk", monospace; font-size: .82rem; color: rgba(251,246,236,.55); margin: 0 0 .5rem; }
.hero-title { font-family: "Fraunces", serif; font-weight: 600; font-size: clamp(1.6rem, 3vw, 2.2rem); margin: 0 0 .5rem; }
.hero-title .ital { font-style: italic; color: #F2C84B; }
.hero-sub { color: rgba(251,246,236,.66); margin: 0; }
.hero-trophy { position: relative; z-index: 2; color: #F2C84B; flex-shrink: 0; }
.hero-trophy svg { width: 100px; height: 100px; opacity: .9; animation: floaty 5s ease-in-out infinite; }
@media (max-width: 768px) { .hero-trophy { display: none; } }
.hero-blob { position: absolute; border-radius: 50%; filter: blur(50px); z-index: 1; }
.hero-blob.a { width: 320px; height: 320px; top: -120px; right: -60px; background: radial-gradient(circle, rgba(238,106,44,.5), transparent 70%); }
.hero-blob.b { width: 260px; height: 260px; bottom: -120px; left: 20%; background: radial-gradient(circle, rgba(44,73,166,.45), transparent 70%); }

.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin: 1.3rem 0; }
.stat { display: flex; align-items: center; gap: .9rem; background: #fff; border: 1px solid #E7D9BE; border-radius: 16px; padding: 1.1rem; box-shadow: 0 2px 8px rgba(10,16,36,.04); }
.stat-ic { font-size: 1.6rem; }
.stat strong { display: block; font-family: "Space Grotesk", monospace; font-size: 1.5rem; color: #0A1024; }
.stat span { font-size: .82rem; color: #5B6373; }

.block { background: #fff; border: 1px solid #E7D9BE; border-radius: 18px; padding: 1.3rem; box-shadow: 0 2px 8px rgba(10,16,36,.04); }
.block-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.block-head h3 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.2rem; color: #0A1024; margin: 0; }
.link { font-size: .85rem; font-weight: 600; color: #C9501A; text-decoration: none; }
.link:hover { text-decoration: underline; }

.ex-list { display: grid; gap: .7rem; }
.ex { display: flex; align-items: center; gap: .9rem; border: 1px solid #EADFC8; border-radius: 13px; padding: .8rem 1rem; }
.ex-subj { width: 42px; height: 42px; flex-shrink: 0; border-radius: 11px; display: grid; place-items: center; font-size: 1.15rem; background: #F3E9D6; }
.ex-info { flex: 1; min-width: 0; }
.ex-info strong { display: block; color: #0A1024; font-size: .95rem; }
.ex-info small { color: #9aa0ad; font-size: .8rem; }

.cta { border: 0; cursor: pointer; text-decoration: none; font-weight: 700; font-size: .85rem; padding: .55rem 1.1rem; border-radius: 11px; color: #fff; white-space: nowrap; transition: transform .15s; }
.cta:hover { transform: translateY(-1px); }
.cta.start { background: linear-gradient(135deg, #1aa177, #168A66); }
.cta.continue { background: linear-gradient(135deg, #F2854E, #EE6A2C); }
.cta.result { background: #131C3D; }
.pill { font-size: .78rem; font-weight: 700; padding: .4rem .8rem; border-radius: 999px; white-space: nowrap; }
.pill.awaiting { color: #9a7b2e; background: rgba(214,153,31,.14); }
.pill.upcoming { color: #2C49A6; background: rgba(44,73,166,.12); }
.pill.closed { color: #5B6373; background: #F3E9D6; }

.empty { text-align: center; padding: 2rem 1rem; color: #5B6373; }
.empty p { margin: 0 0 1rem; font-size: .92rem; }

.refer { margin-top: 1.3rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
    border-radius: 18px; padding: 1.2rem 1.4rem; color: #FBF6EC;
    background: linear-gradient(135deg, #1B2748, #131C3D 60%, #0A1024);
    box-shadow: 0 20px 50px -28px rgba(10,16,36,.5); }
.refer-l { display: flex; align-items: center; gap: .9rem; }
.refer-ic { font-size: 1.7rem; }
.refer-l strong { display: block; font-family: "Fraunces", serif; font-weight: 600; font-size: 1.05rem; }
.refer-l small { color: rgba(251,246,236,.6); font-size: .8rem; }
.refer-r { display: flex; align-items: center; gap: .6rem; }
.refer-copy { border: 1px solid rgba(251,246,236,.2); background: rgba(251,246,236,.08); color: #fff; cursor: pointer;
    font-weight: 600; font-size: .82rem; padding: .55rem 1rem; border-radius: 11px; transition: background .2s; }
.refer-copy:hover { background: rgba(251,246,236,.16); }
.refer-copy.done { background: rgba(22,138,102,.3); border-color: rgba(22,138,102,.5); }
.refer-cta { text-decoration: none; font-weight: 700; font-size: .85rem; padding: .55rem 1.1rem; border-radius: 11px; color: #fff;
    background: linear-gradient(135deg, #F2854E, #EE6A2C); white-space: nowrap; }

@keyframes fadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
@keyframes floaty { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

/* ── onboarding modal ── */
.onb-overlay { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 1.2rem;
    background: rgba(10,16,36,.55); backdrop-filter: blur(6px) saturate(140%); }
.onb-card { position: relative; width: 100%; max-width: 440px; background: #fff; border-radius: 26px;
    padding: 2rem 1.8rem 1.6rem; text-align: center; box-shadow: 0 40px 90px -30px rgba(10,16,36,.55);
    border: 1px solid #F0E6D2; overflow: hidden; }
.onb-card::before { content: ""; position: absolute; top: -80px; right: -60px; width: 220px; height: 220px;
    background: radial-gradient(circle, rgba(238,106,44,.16), transparent 70%); pointer-events: none; }

.onb-x { position: absolute; top: 1rem; right: 1rem; width: 32px; height: 32px; display: grid; place-items: center;
    border: 0; border-radius: 10px; background: #F3E9D6; color: #5B6373; cursor: pointer; transition: background .2s, color .2s; }
.onb-x:hover { background: #EADFC8; color: #0A1024; }
.onb-x svg { width: 15px; height: 15px; }

.onb-badge { width: 62px; height: 62px; margin: .2rem auto 1rem; border-radius: 18px; display: grid; place-items: center;
    color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); box-shadow: 0 16px 30px -12px rgba(238,106,44,.7); }
.onb-badge svg { width: 28px; height: 28px; }

.onb-title { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.5rem; color: #0A1024; margin: 0 0 .5rem; }
.onb-title .ital { font-style: italic; color: #C9501A; }
.onb-sub { color: rgba(10,16,36,.62); font-size: .9rem; line-height: 1.5; margin: 0 auto 1.3rem; max-width: 340px; }

.onb-prog { text-align: left; background: #FBF6EC; border: 1px solid #EADFC8; border-radius: 16px; padding: 1rem 1.1rem; margin-bottom: 1.3rem; }
.onb-prog-top { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: .5rem; }
.onb-prog-top span { font-size: .82rem; font-weight: 600; color: #5B6373; }
.onb-prog-top strong { font-family: "Space Grotesk", monospace; font-size: 1.15rem; color: #C9501A; }
.onb-bar { height: 9px; border-radius: 999px; background: #EADFC8; overflow: hidden; }
.onb-bar-fill { display: block; height: 100%; border-radius: 999px;
    background: linear-gradient(90deg, #F2C84B, #D6991F); transition: width 1s cubic-bezier(.2,.7,.2,1); }
.onb-prog-meta { font-size: .78rem; color: #9aa0ad; margin: .55rem 0 0; }
.onb-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .6rem; }
.onb-chip { font-size: .74rem; font-weight: 600; color: #5B6373; background: #fff; border: 1px solid #EADFC8;
    padding: .25rem .6rem; border-radius: 999px; }
.onb-chip.more { color: #C9501A; }

.onb-cta { width: 100%; border: 0; cursor: pointer; color: #fff; font-weight: 700; font-size: .94rem;
    padding: .85rem 1rem; border-radius: 14px; background: linear-gradient(135deg, #1B2748, #0A1024);
    box-shadow: 0 16px 34px -14px rgba(10,16,36,.6); transition: transform .15s, box-shadow .2s; }
.onb-cta:hover { transform: translateY(-2px); box-shadow: 0 20px 40px -14px rgba(10,16,36,.7); }
.onb-later { margin-top: .7rem; border: 0; background: transparent; cursor: pointer; color: #9aa0ad; font-size: .85rem; font-weight: 600; }
.onb-later:hover { color: #5B6373; text-decoration: underline; }

.onb-enter-active, .onb-leave-active { transition: opacity .25s ease; }
.onb-enter-from, .onb-leave-to { opacity: 0; }
.onb-enter-active .onb-card { animation: fadeUp .35s cubic-bezier(.2,.7,.2,1) both; }
</style>
