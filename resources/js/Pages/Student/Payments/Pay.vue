<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SeoHead from '@/Components/Shared/SeoHead.vue';

const props = defineProps({
    payment: { type: Object, required: true },   // { id, gross, discount, amount, currency, coupon }
    items:   { type: Array,  default: () => [] }, // [{ id, name, fee_amount }]
    keyId:   { type: String, default: '' },
    prefill: { type: Object, default: () => ({}) },
    referral:{ type: Object, default: null },     // Refer & Earn share state (null when program off)
});

const page = usePage();
const CHECKOUT_SRC = 'https://checkout.razorpay.com/v1/checkout.js';

/* ── Refer & Earn (same container + behaviour as the exams page) ── */
const stats = computed(() => props.referral?.stats ?? {});
const justEarned = computed(() => (stats.value.rewarded ?? 0) > 0 && stats.value.toward === 0);
const displayToward = computed(() => justEarned.value ? stats.value.threshold : stats.value.toward);
const progressPct = computed(() => {
    const s = stats.value;
    if (!s.threshold) return 0;
    if (justEarned.value) return 100;
    return Math.min(100, Math.round((s.toward / s.threshold) * 100));
});
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

const couponForm = useForm({ code: '' });
const processing = ref(false);
const banner = ref(null); // { type: 'error' | 'info', text }

const flashError = computed(() => page.props.flash?.error);
const flashSuccess = computed(() => page.props.flash?.success);

const fmt = (n) => Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const isFree = computed(() => Number(props.payment.amount) <= 0);
const savedPct = computed(() => {
    const g = Number(props.payment.gross);
    return g > 0 ? Math.round((Number(props.payment.discount) / g) * 100) : 0;
});

// Label varies for auto-applied referral coupons.
const couponLabel = computed(() => {
    const src = props.payment.coupon?.source;
    if (src === 'referral_welcome') return 'Welcome discount applied';
    if (src === 'referral_reward') return 'Referral reward applied';
    return 'Coupon applied';
});
const isReferralCoupon = computed(() => {
    const src = props.payment.coupon?.source;
    return src === 'referral_welcome' || src === 'referral_reward';
});

// ── Coupon ──
const applyCoupon = () => {
    if (!couponForm.code.trim()) return;
    couponForm.transform((d) => ({ code: d.code.trim().toUpperCase() }))
        .post(route('student.payments.coupon.apply', props.payment.id), { preserveScroll: true });
};
const removeCoupon = () => {
    router.delete(route('student.payments.coupon.remove', props.payment.id), { preserveScroll: true });
};

// ── Checkout script ──
function loadCheckout() {
    return new Promise((resolve, reject) => {
        if (window.Razorpay) return resolve();
        const existing = document.querySelector(`script[src="${CHECKOUT_SRC}"]`);
        if (existing) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject(new Error('load failed')));
            return;
        }
        const s = document.createElement('script');
        s.src = CHECKOUT_SRC; s.async = true;
        s.onload = () => resolve();
        s.onerror = () => reject(new Error('load failed'));
        document.head.appendChild(s);
    });
}

// ── Pay ──
async function pay() {
    if (processing.value) return;
    processing.value = true;
    banner.value = null;

    try {
        const { data } = await window.axios.post(route('student.payments.order', props.payment.id));

        if (data.status === 'free') {
            router.visit(data.redirect);
            return;
        }
        if (data.status === 'coupon_dropped') {
            banner.value = { type: 'error', text: `${data.message} The price has been updated.` };
            processing.value = false;
            router.reload({ only: ['payment'] });
            return;
        }
        if (data.status === 'ok') {
            await openRazorpay(data);
        }
    } catch (e) {
        banner.value = { type: 'error', text: e.response?.data?.message || 'Could not start the payment. Please try again.' };
        processing.value = false;
    }
}

async function openRazorpay(data) {
    try {
        await loadCheckout();
    } catch {
        banner.value = { type: 'error', text: 'Could not load the payment gateway. Check your connection and retry.' };
        processing.value = false;
        return;
    }

    const rzp = new window.Razorpay({
        key: data.key_id,
        amount: data.amount,
        currency: data.currency,
        order_id: data.order_id,
        name: 'National Olympiad Hunt',
        description: 'Olympiad exam enrolment',
        prefill: data.prefill || props.prefill,
        theme: { color: '#EE6A2C' },
        handler(resp) {
            router.post(route('student.payments.verify', props.payment.id), {
                razorpay_payment_id: resp.razorpay_payment_id,
                razorpay_order_id: resp.razorpay_order_id,
                razorpay_signature: resp.razorpay_signature,
            }, { onFinish: () => { processing.value = false; } });
        },
        modal: {
            ondismiss: () => {
                processing.value = false;
                banner.value = { type: 'info', text: 'Payment cancelled — you can try again whenever you like.' };
            },
        },
    });
    rzp.on('payment.failed', (r) => {
        processing.value = false;
        banner.value = { type: 'error', text: r?.error?.description || 'Payment failed. Please try again.' };
    });
    rzp.open();
}
</script>

<template>
    <SeoHead title="Checkout" noindex />

    <div class="noh-pay">
        <div class="bg-grid" aria-hidden="true"></div>

        <div class="shell">
            <!-- Left rail: Refer & Earn (always visible). Falls back to the prestige
                 trust panel only when the referral program is switched off. -->
            <aside class="aside" :class="{ 'is-ref': referral }">
                <div v-if="referral" class="rail-referral">
                    <div class="rs-glow" aria-hidden="true"></div>

                    <div class="rs-top">
                        <span class="rs-eyebrow">Refer &amp; earn</span>
                        <span v-if="referral.reward" class="rs-reward">{{ referral.reward }}</span>
                    </div>

                    <h1 class="rs-title">Invite friends, <em>earn rewards</em></h1>
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

                    <div class="rail-trust">🔒 Secured by <strong>Razorpay</strong> · 256-bit encrypted</div>
                </div>

                <!-- fallback when the referral program is off -->
                <template v-else>
                    <div class="aside-top">
                        <span class="kicker">National Olympiad Hunt</span>
                        <h1 class="headline">Reserve your <em>seat</em></h1>
                        <p class="sub">You're one step from the exam hall. Your admission is confirmed the moment payment clears.</p>
                    </div>

                    <div class="seal" aria-hidden="true">
                        <svg viewBox="0 0 120 120" role="img">
                            <defs>
                                <radialGradient id="sealG" cx="50%" cy="38%" r="70%">
                                    <stop offset="0%" stop-color="#F7DA84" />
                                    <stop offset="48%" stop-color="#E7B23C" />
                                    <stop offset="100%" stop-color="#B6831A" />
                                </radialGradient>
                            </defs>
                            <g>
                                <circle cx="60" cy="60" r="46" fill="url(#sealG)" />
                                <circle cx="60" cy="60" r="46" fill="none" stroke="#8a6314" stroke-width="1" opacity=".55" />
                                <circle cx="60" cy="60" r="37" fill="none" stroke="#7c5810" stroke-width="1.4" stroke-dasharray="2 4" opacity=".6" />
                                <path class="seal-star" d="M60 30 l7.5 15.6 17.2 2.3 -12.6 11.9 3.2 17-15.3-8.2 -15.3 8.2 3.2-17 -12.6-11.9 17.2-2.3z"
                                      fill="#3a2a08" />
                            </g>
                        </svg>
                        <div class="seal-tag">Verified entry</div>
                    </div>

                    <ul class="trust">
                        <li><span class="ic">🔒</span> 256-bit encrypted payment</li>
                        <li><span class="ic">⚡</span> Seat confirmed the instant you pay</li>
                        <li><span class="ic">🎓</span> {{ items.length }} olympiad{{ items.length === 1 ? '' : 's' }} on this ticket</li>
                    </ul>

                    <div class="powered">Secured by <strong>Razorpay</strong> · UPI · Cards · Net Banking · Wallets</div>
                </template>
            </aside>

            <!-- Admit ticket -->
            <main class="panel">
                <div v-if="banner" :class="['banner', banner.type]">{{ banner.text }}</div>

                <div class="ticket">
                    <!-- ticket body -->
                    <div class="ticket-body">
                        <div class="t-head">
                            <span class="t-eyebrow">Admission ticket</span>
                            <span class="t-code">#NEO-{{ payment.id }}</span>
                        </div>

                        <div class="order">
                            <div v-for="it in items" :key="it.id" class="line">
                                <span class="ln-name">{{ it.name }}</span>
                                <span class="ln-amt">₹{{ fmt(it.fee_amount) }}</span>
                            </div>
                        </div>

                        <!-- Coupon -->
                        <div class="coupon">
                            <!-- referral discount applied automatically -->
                            <div v-if="payment.coupon && isReferralCoupon" class="cp-applied referral">
                                <div class="cp-left">
                                    <span class="cp-tag">🎁</span>
                                    <span class="cp-msg">{{ couponLabel }}</span>
                                </div>
                                <button class="cp-remove" @click="removeCoupon">Remove</button>
                            </div>
                            <!-- manual coupon applied -->
                            <div v-else-if="payment.coupon" class="cp-applied">
                                <div class="cp-left">
                                    <span class="cp-tag">{{ payment.coupon.code }}</span>
                                    <span class="cp-msg">{{ couponLabel }}</span>
                                </div>
                                <button class="cp-remove" @click="removeCoupon">Remove</button>
                            </div>
                            <!-- manual code entry (also offered when a referral discount is on, to replace it) -->
                            <div v-if="!payment.coupon || isReferralCoupon" class="cp-input" :class="{ 'cp-input-stacked': isReferralCoupon }">
                                <input
                                    v-model="couponForm.code"
                                    type="text"
                                    :placeholder="isReferralCoupon ? 'Use a coupon code instead?' : 'Have a coupon code?'"
                                    spellcheck="false"
                                    @keydown.enter.prevent="applyCoupon"
                                />
                                <button :disabled="couponForm.processing || !couponForm.code.trim()" @click="applyCoupon">
                                    {{ couponForm.processing ? '…' : 'Apply' }}
                                </button>
                            </div>
                            <p v-if="couponForm.errors.code" class="cp-err">{{ couponForm.errors.code }}</p>
                            <p v-else-if="flashError" class="cp-err">{{ flashError }}</p>
                            <p v-else-if="flashSuccess && payment.coupon" class="cp-ok">{{ flashSuccess }}</p>
                        </div>

                        <!-- Totals -->
                        <div class="totals">
                            <div class="t-row">
                                <span>Subtotal</span>
                                <span class="num">₹{{ fmt(payment.gross) }}</span>
                            </div>
                            <div v-if="payment.discount > 0" class="t-row disc">
                                <span>Discount<template v-if="savedPct"> · {{ savedPct }}% off</template></span>
                                <span class="num">− ₹{{ fmt(payment.discount) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- perforated tear line -->
                    <div class="perf" aria-hidden="true"></div>

                    <!-- ticket stub -->
                    <div class="ticket-stub">
                        <div class="grand">
                            <div class="grand-l">
                                <span class="grand-lbl">Total payable</span>
                                <span v-if="payment.discount > 0" class="grand-save">You save ₹{{ fmt(payment.discount) }}</span>
                            </div>
                            <strong class="grand-amt">₹{{ fmt(payment.amount) }}</strong>
                        </div>

                        <button class="pay-btn" :class="{ free: isFree }" :disabled="processing" @click="pay">
                            <span v-if="processing" class="spin"></span>
                            <template v-if="processing">Processing…</template>
                            <template v-else-if="isFree">Enrol for free →</template>
                            <template v-else>Pay ₹{{ fmt(payment.amount) }}</template>
                        </button>
                    </div>
                </div>

                <Link :href="route('student.exams')" class="cancel">Cancel and go back</Link>
            </main>
        </div>
    </div>
</template>

<style scoped>
.noh-pay {
    --ink: #0A1024; --ink-2: #131C3D; --paper: #FBF6EC; --paper-2: #F3E9D6;
    --line: #E7D9BE; --saffron: #EE6A2C; --saffron-dk: #C9501A; --gold: #D6991F;
    --gold-lt: #F2C84B; --emerald: #168A66; --muted: #5B6373;
    position: fixed; inset: 0; overflow: auto; display: grid; place-items: center; padding: 1.5rem;
    background:
        radial-gradient(70% 50% at 90% 0%, rgba(214,153,31,.12), transparent 60%),
        radial-gradient(60% 50% at 0% 100%, rgba(44,73,166,.07), transparent 55%),
        var(--paper);
    font-family: "Plus Jakarta Sans", system-ui, sans-serif; color: var(--ink);
}
.bg-grid {
    position: absolute; inset: 0; pointer-events: none; opacity: .5;
    background-image: linear-gradient(rgba(10,16,36,.04) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(10,16,36,.04) 1px, transparent 1px);
    background-size: 38px 38px;
    -webkit-mask-image: radial-gradient(circle at 50% 40%, #000, transparent 75%);
            mask-image: radial-gradient(circle at 50% 40%, #000, transparent 75%);
}

.shell {
    position: relative; z-index: 1; width: 100%; max-width: 940px;
    display: grid; grid-template-columns: .92fr 1.15fr; border-radius: 28px; overflow: hidden;
    box-shadow: 0 50px 90px -40px rgba(10,16,36,.55); animation: pop .5s cubic-bezier(.2,.8,.2,1) both;
}
@keyframes pop { from { opacity: 0; transform: translateY(22px) scale(.985); } to { opacity: 1; transform: none; } }

/* ── Prestige rail ── */
.aside {
    background: linear-gradient(160deg, var(--ink) 0%, var(--ink-2) 70%, #1B2748 100%);
    color: var(--paper); padding: 2.4rem 2rem; display: flex; flex-direction: column; justify-content: space-between; gap: 1.7rem;
    position: relative; overflow: hidden;
}
.aside::after {
    content: ""; position: absolute; right: -50px; top: -50px; width: 200px; height: 200px; border-radius: 50%;
    background: radial-gradient(circle, rgba(242,200,75,.22), transparent 70%); pointer-events: none;
}
/* left rail in referral mode — the box fills and centres */
.aside.is-ref { justify-content: center; gap: 0; }
.rail-referral { position: relative; z-index: 1; width: 100%; display: flex; flex-direction: column; }
.rail-trust { margin-top: 1.3rem; font-size: .72rem; color: rgba(251,246,236,.5); border-top: 1px solid rgba(251,246,236,.12); padding-top: .95rem; }
.rail-trust strong { color: var(--paper); }
.kicker { font-size: .72rem; letter-spacing: .16em; text-transform: uppercase; color: var(--gold-lt); font-weight: 700; }
.headline { font-family: "Fraunces", Georgia, serif; font-weight: 600; font-size: 2.1rem; line-height: 1.05; margin: .6rem 0 .7rem; }
.headline em { color: var(--gold-lt); font-style: italic; }
.sub { font-size: .9rem; line-height: 1.6; color: rgba(251,246,236,.7); max-width: 30ch; }

/* signature: wax-seal medallion */
.seal { position: relative; align-self: center; display: flex; flex-direction: column; align-items: center; gap: .55rem; margin: .2rem 0; }
.seal svg { width: 108px; height: 108px; filter: drop-shadow(0 14px 22px rgba(0,0,0,.4)); animation: sealIn .7s cubic-bezier(.2,.9,.3,1.2) both .15s; }
.seal::before { content: ""; position: absolute; top: 6px; left: 50%; transform: translateX(-50%); width: 96px; height: 96px; border-radius: 50%;
    background: radial-gradient(circle, rgba(242,200,75,.4), transparent 68%); filter: blur(8px); z-index: 0; }
.seal svg { position: relative; z-index: 1; }
.seal-star { transform-origin: 60px 60px; }
.seal-tag { font-family: "Space Grotesk", monospace; font-size: .64rem; font-weight: 700; letter-spacing: .26em; text-transform: uppercase; color: var(--gold-lt); }
@keyframes sealIn { from { opacity: 0; transform: scale(.6) rotate(-22deg); } to { opacity: 1; transform: none; } }

.trust { list-style: none; padding: 0; margin: 0; display: grid; gap: .8rem; }
.trust li { display: flex; align-items: center; gap: .7rem; font-size: .86rem; color: rgba(251,246,236,.92); }
.trust .ic { width: 30px; height: 30px; flex-shrink: 0; display: grid; place-items: center; border-radius: 9px; background: rgba(251,246,236,.08); border: 1px solid rgba(251,246,236,.12); font-size: .95rem; }
.powered { font-size: .7rem; color: rgba(251,246,236,.5); border-top: 1px solid rgba(251,246,236,.12); padding-top: 1rem; }
.powered strong { color: var(--paper); }

/* ── Ticket panel ── */
.panel { background: var(--paper); padding: 2rem 1.9rem; display: flex; flex-direction: column; justify-content: center; }

.banner { font-size: .82rem; font-weight: 600; border-radius: 11px; padding: .6rem .8rem; margin-bottom: 1.1rem; }
.banner.error { color: #b02a1f; background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.25); }
.banner.info { color: #9a7b2e; background: rgba(214,153,31,.12); border: 1px solid rgba(214,153,31,.3); }

.ticket { position: relative; background: #fff; border-radius: 18px; border: 1px solid var(--line);
    box-shadow: 0 24px 50px -28px rgba(10,16,36,.4); }
.ticket-body { padding: 1.5rem 1.5rem 1.2rem; }

.t-head { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.t-eyebrow { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.18rem; color: var(--ink); }
.t-code { font-family: "Space Grotesk", monospace; font-size: .76rem; font-weight: 600; letter-spacing: .08em; color: var(--muted);
    background: var(--paper-2); border: 1px dashed var(--line); padding: .2rem .55rem; border-radius: 7px; }

.order { margin-bottom: 1rem; }
.line { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .6rem 0; border-bottom: 1px dashed var(--line); }
.line:first-child { padding-top: .2rem; }
.line:last-child { border-bottom: 0; padding-bottom: 0; }
.ln-name { font-size: .9rem; color: #3a4154; }
.ln-amt { font-family: "Space Grotesk", monospace; font-size: .9rem; font-weight: 600; color: var(--ink); white-space: nowrap; }

/* Coupon */
.coupon { margin-bottom: 1.1rem; }
.cp-input { display: flex; gap: .5rem; }
.cp-input-stacked { margin-top: .6rem; }
.cp-input input {
    flex: 1; min-width: 0; border: 1.5px solid var(--line); background: var(--paper); border-radius: 11px; padding: .7rem .85rem;
    font-family: "Space Grotesk", monospace; font-size: .9rem; letter-spacing: .04em; text-transform: uppercase; color: var(--ink); outline: none; transition: border-color .15s;
}
.cp-input input::placeholder { text-transform: none; letter-spacing: 0; font-family: "Plus Jakarta Sans", sans-serif; color: var(--muted); }
.cp-input input:focus { border-color: var(--saffron); }
.cp-input button {
    border: 0; cursor: pointer; background: var(--ink); color: #fff; font-weight: 700; font-size: .85rem;
    padding: 0 1.2rem; border-radius: 11px; transition: background .15s; min-width: 76px;
}
.cp-input button:hover:not(:disabled) { background: var(--ink-2); }
.cp-input button:disabled { opacity: .45; cursor: not-allowed; }

.cp-applied { display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    border: 1.5px dashed var(--gold); background: rgba(242,200,75,.12); border-radius: 12px; padding: .65rem .85rem; }
.cp-left { display: flex; align-items: center; gap: .6rem; }
.cp-tag { font-family: "Space Grotesk", monospace; font-weight: 700; letter-spacing: .05em; color: var(--ink);
    background: var(--gold-lt); padding: .25rem .55rem; border-radius: 7px; font-size: .82rem; }
.cp-msg { font-size: .82rem; font-weight: 600; color: #97751e; }
.cp-applied.referral { border-color: var(--emerald); background: rgba(22,138,102,.1); }
.cp-applied.referral .cp-tag { background: rgba(22,138,102,.18); color: var(--emerald); }
.cp-applied.referral .cp-msg { color: var(--emerald); }
.cp-remove { border: 0; background: transparent; color: var(--saffron-dk); font-weight: 700; font-size: .8rem; cursor: pointer; }
.cp-remove:hover { text-decoration: underline; }
.cp-err { color: #b02a1f; font-size: .78rem; font-weight: 600; margin: .5rem 0 0; }
.cp-ok { color: var(--emerald); font-size: .78rem; font-weight: 600; margin: .5rem 0 0; }

/* Totals (subtotal + discount only; grand total lives on the stub) */
.totals { border-top: 1px solid var(--line); padding-top: .9rem; }
.t-row { display: flex; align-items: center; justify-content: space-between; padding: .26rem 0; font-size: .9rem; color: var(--muted); }
.t-row .num { font-family: "Space Grotesk", monospace; color: var(--ink); }
.t-row.disc { color: var(--emerald); }
.t-row.disc .num { color: var(--emerald); }

/* perforated tear line with punched edge notches */
.perf { position: relative; height: 0; border-top: 2px dashed var(--line); margin: 0; }
.perf::before, .perf::after { content: ""; position: absolute; top: 50%; width: 22px; height: 22px; border-radius: 50%;
    background: var(--paper); transform: translateY(-50%); }
.perf::before { left: -12px; box-shadow: inset -2px 0 3px rgba(10,16,36,.07); }
.perf::after { right: -12px; box-shadow: inset 2px 0 3px rgba(10,16,36,.07); }

/* ticket stub — the "admit" tear-off */
.ticket-stub { padding: 1.2rem 1.5rem 1.5rem; background: rgba(242,200,75,.06); border-radius: 0 0 18px 18px; }
.grand { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.grand-l { display: flex; flex-direction: column; gap: .2rem; }
.grand-lbl { font-weight: 700; font-size: .95rem; color: var(--ink); }
.grand-save { font-size: .74rem; font-weight: 700; color: var(--emerald); }
.grand-amt { font-family: "Space Grotesk", monospace; font-size: 1.9rem; font-weight: 700; color: var(--ink); line-height: 1; letter-spacing: -.01em; }

.pay-btn {
    width: 100%; border: 0; cursor: pointer; color: #fff; font-weight: 800; font-size: 1.02rem;
    padding: 1rem; border-radius: 14px; letter-spacing: .01em; position: relative; overflow: hidden;
    background: linear-gradient(135deg, var(--saffron) 0%, var(--saffron-dk) 100%);
    box-shadow: 0 16px 34px -12px rgba(238,106,44,.6); transition: transform .15s, box-shadow .15s;
    display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
}
.pay-btn.free { background: linear-gradient(135deg, #1aa177, var(--emerald)); box-shadow: 0 16px 34px -12px rgba(22,138,102,.6); }
.pay-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 22px 40px -14px rgba(238,106,44,.7); }
.pay-btn:disabled { opacity: .8; cursor: progress; }
.spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: rot .7s linear infinite; }
@keyframes rot { to { transform: rotate(360deg); } }

.cancel { display: block; text-align: center; margin-top: 1rem; font-size: .82rem; color: var(--muted); text-decoration: none; }
.cancel:hover { color: var(--ink); }

/* ── Refer & Earn content (lives directly in the left rail) ── */
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

@media (max-width: 760px) {
    .noh-pay { padding: 1rem; }
    .shell { grid-template-columns: 1fr; max-width: 460px; }
    .aside { padding: 1.8rem 1.6rem; gap: 1.3rem; }
    .aside.is-ref { gap: 0; }
    .headline { font-size: 1.7rem; }
    .seal svg { width: 92px; height: 92px; }
    .panel { padding: 1.6rem 1.4rem; }
    .ticket-body { padding: 1.3rem 1.2rem 1rem; }
    .ticket-stub { padding: 1.1rem 1.2rem 1.3rem; }
}

@media (prefers-reduced-motion: reduce) {
    .shell, .seal svg { animation: none; }
    .pay-btn, .rs-fill, .rs-medal, .ref-copy, .ref-share-btns .sh { transition: none; }
}
</style>
