<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Stepper from './Components/Stepper.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    exams: { type: Array, default: () => [] },
    selected: { type: Array, default: () => [] },
    referral: { type: Object, default: null },
    discounts: { type: Array, default: () => [] }, // student's usable personal discount rules
});

// ── Referral sharing ──
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

const picked = ref([...props.selected]);
const toggle = (id) => {
    const i = picked.value.indexOf(id);
    if (i === -1) picked.value.push(id); else picked.value.splice(i, 1);
};

const total = computed(() =>
    props.exams.filter((e) => picked.value.includes(e.id)).reduce((s, e) => s + e.fee_amount, 0)
);

// Instant price preview — mirrors CouponService::discountFor + autoCouponFor (best rule).
const discountAmount = computed(() => {
    const t = total.value;
    if (!t || !props.discounts.length) return 0;
    let best = 0;
    for (const d of props.discounts) {
        if (t < (d.min_order_amount || 0)) continue;
        let amt = d.type === 'percentage' ? Math.round((t * d.value / 100) * 100) / 100 : d.value;
        if (d.type === 'percentage' && d.max_discount) amt = Math.min(amt, d.max_discount);
        amt = Math.min(amt, t);
        if (amt > best) best = amt;
    }
    return Math.round(best * 100) / 100;
});
const finalTotal = computed(() => Math.max(0, Math.round((total.value - discountAmount.value) * 100) / 100));
const fmtINR = (n) => '₹' + Number(n).toLocaleString('en-IN');

const form = useForm({ exam_ids: [] });
const next = () => {
    form.exam_ids = [...picked.value];
    form.post(route('register.olympiads.store'));
};
const skip = () => router.visit(route('student.dashboard'));
</script>

<template>
    <Head title="Choose olympiads" />

    <AuthLayout
        wide
        eyebrow="Step 2 of 3"
        heading="Choose your olympiads"
        subheading="Select the exams you'd like to enrol in. You can always add more later."
    >
        <Stepper :current="2" />

        <div v-if="exams.length" class="pick-list">
            <button
                v-for="e in exams"
                :key="e.id"
                type="button"
                class="pick"
                :class="{ on: picked.includes(e.id) }"
                @click="toggle(e.id)"
            >
                <span class="check" :class="{ on: picked.includes(e.id) }">
                    <svg v-if="picked.includes(e.id)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                <span class="subj">{{ e.subject?.icon || '📝' }}</span>
                <span class="info">
                    <strong>{{ e.subject?.name ?? e.name }}</strong>
                </span>
                <span class="fee" :class="{ free: e.is_free }">{{ e.is_free ? 'FREE' : '₹' + e.fee_amount.toLocaleString('en-IN') }}</span>
            </button>
        </div>

        <div v-else class="empty">
            <div class="ic">📭</div>
            <p>No olympiads are open for your class right now. You can explore all exams from your dashboard anytime.</p>
        </div>

        <!-- Referral: share your link & earn (predefined welcome shows if you were referred) -->
        <div v-if="referral" class="referral">
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

        <div class="foot">
            <button type="button" class="skip" @click="skip">Skip for now</button>
            <div class="foot-r">
                <span v-if="picked.length" class="total">
                    {{ picked.length }} selected ·
                    <template v-if="discountAmount > 0">
                        <s class="total-was">{{ fmtINR(total) }}</s>
                        <strong>{{ finalTotal === 0 ? 'FREE' : fmtINR(finalTotal) }}</strong>
                        <span class="total-save">{{ fmtINR(discountAmount) }} off</span>
                    </template>
                    <template v-else>
                        <strong>{{ total === 0 ? 'FREE' : fmtINR(total) }}</strong>
                    </template>
                </span>
                <button class="cta" :disabled="!picked.length || form.processing" @click="next">
                    {{ form.processing ? 'Saving…' : 'Save & Next →' }}
                </button>
            </div>
        </div>
    </AuthLayout>
</template>

<style scoped>
.pick-list { display: grid; gap: .7rem; max-height: 52vh; overflow: auto; padding: .2rem; }
.pick { display: flex; align-items: center; gap: .9rem; text-align: left; width: 100%; background: #fff; border: 1.5px solid #E7D9BE; border-radius: 14px; padding: .9rem 1rem; cursor: pointer; transition: border-color .15s, background .15s, transform .12s; }
.pick:hover { border-color: #F2854E; }
.pick.on { border-color: #EE6A2C; background: rgba(238,106,44,.05); }
.check { width: 24px; height: 24px; flex-shrink: 0; border-radius: 7px; border: 2px solid #D9C9A6; display: grid; place-items: center; color: #fff; }
.check.on { background: #EE6A2C; border-color: #EE6A2C; }
.check svg { width: 14px; height: 14px; }
.subj { width: 40px; height: 40px; flex-shrink: 0; border-radius: 11px; display: grid; place-items: center; font-size: 1.15rem; background: #F3E9D6; }
.info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.info strong { color: #0A1024; font-size: .95rem; }
.info small { color: #9aa0ad; font-size: .8rem; }
.fee { font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 1rem; color: #0A1024; flex-shrink: 0; }
.fee.free { color: #168A66; }

.empty { text-align: center; padding: 2.5rem 1rem; color: #5B6373; }
.empty .ic { font-size: 2.2rem; margin-bottom: .5rem; }
.empty p { font-size: .9rem; max-width: 34ch; margin: 0 auto; }

/* Referral */
.referral { margin-top: 1.2rem; display: grid; gap: .8rem; }
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

@media (prefers-reduced-motion: reduce) {
    .rs-fill, .rs-medal, .ref-share-btns .sh, .ref-copy { transition: none; }
}

.foot { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1.4rem; flex-wrap: wrap; }
.skip { border: 0; background: transparent; color: #9aa0ad; cursor: pointer; font-size: .9rem; font-weight: 600; }
.skip:hover { color: #5B6373; text-decoration: underline; }
.foot-r { display: flex; align-items: center; gap: 1rem; }
.total { font-size: .88rem; color: #5B6373; }
.total strong { font-family: "Space Grotesk", monospace; color: #0A1024; }
.total-was { font-family: "Space Grotesk", monospace; color: #9aa0ad; text-decoration: line-through; margin-right: .35rem; }
.total-save { display: inline-block; margin-left: .45rem; font-size: .72rem; font-weight: 700; color: #168A66; background: rgba(22,138,102,.12); padding: .12rem .5rem; border-radius: 999px; }
.cta { border: 0; cursor: pointer; font-weight: 700; font-size: .95rem; color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); padding: .8rem 1.5rem; border-radius: 13px; box-shadow: 0 12px 26px -12px rgba(238,106,44,.8); transition: transform .15s; }
.cta:hover:not(:disabled) { transform: translateY(-2px); }
.cta:disabled { opacity: .5; cursor: not-allowed; }
</style>
