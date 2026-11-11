<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import HallTicketCard from '@/Components/Shared/HallTicketCard.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    summary: { type: Object, required: true },
    ticket: { type: Object, default: null },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const el = computed(() => props.summary.eligibility ?? {});
const status = computed(() => props.summary.status);

const confirmed = ref(false);
const submitting = ref(false);
const submit = () => {
    if (!confirmed.value && status.value === 'none') return;
    router.post(route('student.hall-ticket.store'), {}, {
        preserveScroll: true,
        onStart: () => (submitting.value = true),
        onFinish: () => (submitting.value = false),
    });
};

/* 4-step tracker: profile → submit → verification → print */
const steps = computed(() => {
    const s = status.value;
    const done = (n) => ({
        1: el.value.eligible || s !== 'none',
        2: s === 'pending' || s === 'approved',
        3: s === 'approved',
        4: false,
    })[n];
    const current = s === 'approved' ? 4 : s === 'pending' ? 3 : (el.value.eligible ? 2 : 1);
    return [
        { n: 1, label: 'Complete profile', hint: `${el.value.percent ?? 0}% · need ${el.value.min_percent}%` },
        { n: 2, label: 'Submit details', hint: s === 'rejected' ? 'Resubmit after fixing' : 'Confirm & send' },
        { n: 3, label: 'Admin verification', hint: s === 'pending' ? 'In progress' : s === 'rejected' ? 'Changes needed' : 'Documents checked' },
        { n: 4, label: 'Print hall ticket', hint: s === 'approved' ? 'Ready now' : 'After approval' },
    ].map((step) => ({ ...step, done: done(step.n), current: step.n === current, failed: step.n === 3 && s === 'rejected' }));
});
</script>

<template>
    <Head title="Hall Ticket" />

    <StudentLayout title="Hall Ticket">
        <div v-if="flash.success" class="flash ok">{{ flash.success }}</div>
        <div v-if="flash.error" class="flash bad">{{ flash.error }}</div>

        <!-- hero + tracker -->
        <section class="hero">
            <div class="blob a"></div><div class="blob b"></div>
            <div class="hero-top">
                <div>
                    <p class="kicker">Examination{{ ticket?.header?.session ? ` · ${ticket.header.session}` : '' }}</p>
                    <h2>Your <span class="ital">hall ticket</span></h2>
                    <p class="sub">Your admit card for every olympiad you're enrolled in. Verified once, valid for all your exams.</p>
                </div>
                <span class="status" :class="status">{{ summary.label }}</span>
            </div>
            <ol class="track">
                <li v-for="st in steps" :key="st.n" :class="{ done: st.done, current: st.current, failed: st.failed }">
                    <span class="dot">
                        <svg v-if="st.done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
                        <svg v-else-if="st.failed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        <template v-else>{{ st.n }}</template>
                    </span>
                    <div><strong>{{ st.label }}</strong><small>{{ st.hint }}</small></div>
                </li>
            </ol>
        </section>

        <!-- state panel -->
        <section v-if="status === 'approved'" class="panel good">
            <span class="p-ic">🎉</span>
            <div class="p-body">
                <h3>Your hall ticket is approved</h3>
                <p>Roll number <b class="mono">{{ summary.roll_number }}</b>. Print it or save it as a PDF and keep it with your school ID card on exam day.</p>
            </div>
            <Link :href="route('student.hall-ticket.print')" class="btn saffron">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/></svg>
                Print / Download
            </Link>
        </section>

        <section v-else-if="status === 'pending'" class="panel info">
            <span class="p-ic">⏳</span>
            <div class="p-body">
                <h3>Submitted — awaiting admin verification</h3>
                <p>We received your details on {{ summary.submitted_at }}. The examination team is checking your photos and school ID card. You'll get a notification as soon as it's approved. Your details are locked until then; contact support if something needs changing.</p>
            </div>
        </section>

        <section v-else-if="status === 'rejected'" class="panel bad">
            <span class="p-ic">⚠️</span>
            <div class="p-body">
                <h3>Changes needed</h3>
                <p class="reason">“{{ summary.rejection_reason }}”</p>
                <p>Your profile is unlocked. Fix the details above, then submit again.</p>
            </div>
            <div class="p-actions">
                <Link :href="route('student.profile')" class="btn ghost">Update profile</Link>
                <button v-if="el.eligible" type="button" class="btn saffron" :disabled="submitting" @click="submit">{{ submitting ? 'Submitting…' : 'Resubmit' }}</button>
            </div>
        </section>

        <section v-else-if="!el.eligible" class="panel warn">
            <span class="p-ic">🪪</span>
            <div class="p-body">
                <h3>Complete your profile to generate your hall ticket</h3>
                <p>At least <b>{{ el.min_percent }}%</b> of your profile, including your photos, school ID card and coordinator details, is required before you can submit for verification.</p>
                <div class="meter"><span :style="{ width: (el.percent ?? 0) + '%' }"></span></div>
                <p class="meter-l"><b class="mono">{{ el.percent }}%</b> complete · {{ el.filled }} of {{ el.total }} items</p>
                <div v-if="el.missing?.length" class="chips">
                    <span v-for="m in el.missing" :key="m" class="chip" :class="{ req: el.required_missing?.includes(m) }">{{ m }}<template v-if="el.required_missing?.includes(m)"> · required</template></span>
                </div>
            </div>
            <Link :href="route('student.profile')" class="btn saffron">Complete my profile →</Link>
        </section>

        <section v-else class="panel ready">
            <span class="p-ic">✅</span>
            <div class="p-body">
                <h3>You're ready to generate your hall ticket</h3>
                <p>Check the preview below carefully. After you submit, your details are locked while the examination team verifies them.</p>
                <label class="confirm">
                    <input v-model="confirmed" type="checkbox" />
                    <span>I confirm that the details and documents I've provided are correct and belong to me.</span>
                </label>
            </div>
            <button type="button" class="btn saffron" :disabled="!confirmed || submitting" @click="submit">
                {{ submitting ? 'Submitting…' : 'Submit for verification' }}
            </button>
        </section>

        <!-- the ticket -->
        <section v-if="ticket" class="preview">
            <div class="preview-head">
                <h3>{{ status === 'approved' ? 'Hall ticket' : 'Preview' }}</h3>
                <small v-if="status !== 'approved'">The roll number is issued when your hall ticket is approved.</small>
            </div>
            <HallTicketCard :ticket="ticket" />
        </section>
    </StudentLayout>
</template>

<style scoped>
.mono { font-family: "Space Grotesk", monospace; }
.flash { margin-bottom: 1rem; padding: .85rem 1.1rem; border-radius: 14px; font-size: .9rem; font-weight: 600; }
.flash.ok { background: rgba(22,138,102,.1); color: #116b50; border: 1px solid rgba(22,138,102,.25); }
.flash.bad { background: rgba(220,38,38,.08); color: #b91c1c; border: 1px solid rgba(220,38,38,.22); }

.hero { position: relative; overflow: hidden; border-radius: 24px; padding: 1.8rem 1.9rem 1.5rem; color: #FBF6EC;
    background: linear-gradient(135deg, #1B2748 0%, #131C3D 50%, #0A1024 100%); box-shadow: 0 30px 70px -30px rgba(10,16,36,.55); }
.blob { position: absolute; border-radius: 50%; filter: blur(55px); pointer-events: none; }
.blob.a { width: 320px; height: 320px; top: -150px; right: -70px; background: radial-gradient(circle, rgba(238,106,44,.5), transparent 70%); }
.blob.b { width: 260px; height: 260px; bottom: -150px; left: 18%; background: radial-gradient(circle, rgba(44,73,166,.45), transparent 70%); }
.hero-top { position: relative; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
.kicker { margin: 0; font-family: "Space Grotesk", monospace; font-size: .78rem; color: rgba(251,246,236,.55); letter-spacing: .08em; text-transform: uppercase; }
.hero h2 { font-family: "Fraunces", serif; font-weight: 600; font-size: clamp(1.6rem, 3vw, 2.1rem); margin: .2rem 0 .35rem; }
.ital { font-style: italic; color: #F2C84B; }
.sub { margin: 0; color: rgba(251,246,236,.66); max-width: 520px; font-size: .92rem; }
.status { font-size: .78rem; font-weight: 700; padding: .4rem .85rem; border-radius: 999px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); white-space: nowrap; }
.status.pending { background: rgba(242,200,75,.18); color: #F2C84B; border-color: rgba(242,200,75,.4); }
.status.approved { background: rgba(22,138,102,.28); color: #8ff0c9; border-color: rgba(22,138,102,.5); }
.status.rejected { background: rgba(220,38,38,.22); color: #fecaca; border-color: rgba(220,38,38,.45); }

.track { position: relative; list-style: none; margin: 1.5rem 0 0; padding: 0; display: grid; grid-template-columns: repeat(4, 1fr); gap: .6rem; }
.track li { display: flex; gap: .65rem; align-items: center; padding: .7rem .8rem; border-radius: 14px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); }
.track li.current { background: rgba(238,106,44,.16); border-color: rgba(238,106,44,.45); }
.dot { width: 30px; height: 30px; flex-shrink: 0; border-radius: 10px; display: grid; place-items: center; font-family: "Space Grotesk", monospace; font-weight: 700; font-size: .85rem;
    background: rgba(255,255,255,.1); color: rgba(251,246,236,.7); }
.dot svg { width: 15px; height: 15px; }
.track li.done .dot { background: #168A66; color: #fff; }
.track li.current .dot { background: #EE6A2C; color: #fff; }
.track li.failed .dot { background: #DC2626; color: #fff; }
.track strong { display: block; font-size: .84rem; }
.track small { display: block; font-size: .72rem; color: rgba(251,246,236,.55); }
@media (max-width: 860px) { .track { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 420px) { .track { grid-template-columns: 1fr; } }

.panel { margin-top: 1.2rem; display: flex; align-items: center; gap: 1.1rem; flex-wrap: wrap; padding: 1.3rem 1.4rem; border-radius: 20px; background: #fff; border: 1px solid #E7D9BE; box-shadow: 0 2px 10px rgba(10,16,36,.04); }
.panel.good { border-color: rgba(22,138,102,.35); background: linear-gradient(135deg, #fff, rgba(22,138,102,.06)); }
.panel.info { border-color: rgba(44,73,166,.3); background: linear-gradient(135deg, #fff, rgba(44,73,166,.05)); }
.panel.bad { border-color: rgba(220,38,38,.3); background: linear-gradient(135deg, #fff, rgba(220,38,38,.04)); }
.panel.warn { border-color: rgba(214,153,31,.4); background: linear-gradient(135deg, #fff, rgba(214,153,31,.07)); }
.panel.ready { border-color: rgba(238,106,44,.35); background: linear-gradient(135deg, #fff, rgba(238,106,44,.05)); }
.p-ic { font-size: 2rem; width: 56px; height: 56px; border-radius: 16px; display: grid; place-items: center; background: #FBF6EC; flex-shrink: 0; }
.p-body { flex: 1; min-width: 240px; }
.p-body h3 { margin: 0 0 .3rem; font-family: "Fraunces", serif; font-weight: 600; font-size: 1.15rem; color: #0A1024; }
.p-body p { margin: 0; font-size: .9rem; color: rgba(10,16,36,.66); line-height: 1.5; }
.reason { font-weight: 600; color: #b91c1c !important; margin-bottom: .3rem !important; }
.p-actions { display: flex; gap: .6rem; flex-wrap: wrap; }
.meter { height: 10px; border-radius: 999px; background: #EADFC8; overflow: hidden; margin: .8rem 0 .35rem; max-width: 460px; }
.meter span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #F2C84B, #D6991F); transition: width .8s cubic-bezier(.2,.7,.2,1); }
.meter-l { font-size: .8rem !important; }
.chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .6rem; }
.chip { font-size: .74rem; font-weight: 600; color: #5B6373; background: #fff; border: 1px solid #EADFC8; padding: .25rem .6rem; border-radius: 999px; }
.chip.req { color: #C9501A; border-color: rgba(238,106,44,.45); background: rgba(238,106,44,.06); }
.confirm { display: flex; gap: .6rem; align-items: flex-start; margin-top: .8rem; font-size: .86rem; color: #0A1024; cursor: pointer; }
.confirm input { margin-top: .2rem; width: 17px; height: 17px; accent-color: #EE6A2C; }

.btn { display: inline-flex; align-items: center; gap: .5rem; border: 0; cursor: pointer; text-decoration: none; font-weight: 700; font-size: .9rem;
    padding: .75rem 1.25rem; border-radius: 13px; white-space: nowrap; transition: transform .15s, opacity .2s; }
.btn svg { width: 18px; height: 18px; }
.btn:hover:not(:disabled) { transform: translateY(-2px); }
.btn:disabled { opacity: .45; cursor: not-allowed; }
.btn.saffron { color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); box-shadow: 0 12px 26px -12px rgba(238,106,44,.7); }
.btn.ghost { color: #131C3D; background: #fff; border: 1.5px solid #E7D9BE; }

.preview { margin-top: 1.4rem; }
.preview-head { display: flex; align-items: baseline; justify-content: space-between; gap: .8rem; flex-wrap: wrap; margin-bottom: .7rem; }
.preview-head h3 { margin: 0; font-family: "Fraunces", serif; font-weight: 600; font-size: 1.2rem; color: #0A1024; }
.preview-head small { color: #5B6373; font-size: .8rem; }
@media (max-width: 560px) { .hero { padding: 1.4rem 1.2rem; } .panel { padding: 1.1rem; } .btn { width: 100%; justify-content: center; } }
</style>
