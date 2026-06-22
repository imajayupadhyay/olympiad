<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SeoHead from '@/Components/Shared/SeoHead.vue';

const props = defineProps({
    payment: { type: Object, required: true },   // { id, gross, discount, amount, currency, coupon }
    items:   { type: Array,  default: () => [] }, // [{ id, name, fee_amount }]
    keyId:   { type: String, default: '' },
    prefill: { type: Object, default: () => ({}) },
});

const page = usePage();
const CHECKOUT_SRC = 'https://checkout.razorpay.com/v1/checkout.js';

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
        <div class="bg-grid"></div>
        <div class="coin c1">🏅</div>
        <div class="coin c2">🥇</div>

        <div class="shell">
            <!-- Brand / trust panel -->
            <aside class="aside">
                <div class="aside-top">
                    <span class="kicker">National Olympiad Hunt</span>
                    <h1 class="headline">Secure <em>checkout</em></h1>
                    <p class="sub">You're one step from your seat. Payments are encrypted and processed by Razorpay.</p>
                </div>

                <ul class="trust">
                    <li><span class="ic">🔒</span> 256-bit encrypted payment</li>
                    <li><span class="ic">⚡</span> Instant enrolment on success</li>
                    <li><span class="ic">🎓</span> {{ items.length }} olympiad{{ items.length === 1 ? '' : 's' }} in this order</li>
                </ul>

                <div class="powered">Powered by <strong>Razorpay</strong> · UPI · Cards · Net Banking · Wallets</div>
            </aside>

            <!-- Order + coupon + pay -->
            <main class="panel">
                <div v-if="banner" :class="['banner', banner.type]">{{ banner.text }}</div>

                <h2 class="panel-title">Order summary</h2>

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
                    <div class="t-row grand">
                        <span>Total payable</span>
                        <strong class="num">₹{{ fmt(payment.amount) }}</strong>
                    </div>
                </div>

                <div v-if="payment.discount > 0" class="save-badge">
                    🎉 You save ₹{{ fmt(payment.discount) }} on this order
                </div>

                <button class="pay-btn" :class="{ free: isFree }" :disabled="processing" @click="pay">
                    <span v-if="processing" class="spin"></span>
                    <template v-if="processing">Processing…</template>
                    <template v-else-if="isFree">Enrol for Free →</template>
                    <template v-else>Pay ₹{{ fmt(payment.amount) }}</template>
                </button>

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
        radial-gradient(70% 50% at 90% 0%, rgba(214,153,31,.10), transparent 60%),
        radial-gradient(60% 50% at 0% 100%, rgba(44,73,166,.08), transparent 55%),
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
.coin { position: absolute; font-size: 3rem; filter: drop-shadow(0 12px 18px rgba(10,16,36,.22)); opacity: .85; animation: float 7s ease-in-out infinite; }
.coin.c1 { top: 8%; left: 7%; }
.coin.c2 { bottom: 10%; right: 8%; animation-delay: -3s; }
@keyframes float { 0%,100% { transform: translateY(0) rotate(-6deg); } 50% { transform: translateY(-16px) rotate(6deg); } }

.shell {
    position: relative; z-index: 1; width: 100%; max-width: 940px;
    display: grid; grid-template-columns: 1fr 1.15fr; border-radius: 28px; overflow: hidden;
    box-shadow: 0 50px 90px -40px rgba(10,16,36,.55); animation: pop .5s cubic-bezier(.2,.8,.2,1) both;
}
@keyframes pop { from { opacity: 0; transform: translateY(22px) scale(.985); } to { opacity: 1; transform: none; } }

/* Aside */
.aside {
    background: linear-gradient(160deg, var(--ink) 0%, var(--ink-2) 70%, #1B2748 100%);
    color: var(--paper); padding: 2.4rem 2rem; display: flex; flex-direction: column; justify-content: space-between; gap: 2rem;
    position: relative;
}
.aside::after {
    content: ""; position: absolute; right: -40px; top: -40px; width: 180px; height: 180px; border-radius: 50%;
    background: radial-gradient(circle, rgba(242,200,75,.28), transparent 70%);
}
.kicker { font-size: .72rem; letter-spacing: .16em; text-transform: uppercase; color: var(--gold-lt); font-weight: 700; }
.headline { font-family: "Fraunces", Georgia, serif; font-weight: 600; font-size: 2.1rem; line-height: 1.05; margin: .6rem 0 .7rem; }
.headline em { color: var(--gold-lt); font-style: italic; }
.sub { font-size: .9rem; line-height: 1.6; color: rgba(251,246,236,.7); max-width: 30ch; }
.trust { list-style: none; padding: 0; margin: 0; display: grid; gap: .85rem; }
.trust li { display: flex; align-items: center; gap: .7rem; font-size: .88rem; color: rgba(251,246,236,.92); }
.trust .ic { width: 30px; height: 30px; display: grid; place-items: center; border-radius: 9px; background: rgba(251,246,236,.08); border: 1px solid rgba(251,246,236,.12); font-size: .95rem; }
.powered { font-size: .72rem; color: rgba(251,246,236,.5); border-top: 1px solid rgba(251,246,236,.12); padding-top: 1rem; }
.powered strong { color: var(--paper); }

/* Panel */
.panel { background: #fff; padding: 2.2rem 2rem; display: flex; flex-direction: column; }
.panel-title { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.15rem; color: var(--ink); margin: 0 0 1rem; }

.banner { font-size: .82rem; font-weight: 600; border-radius: 11px; padding: .6rem .8rem; margin-bottom: 1.1rem; }
.banner.error { color: #b02a1f; background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.25); }
.banner.info { color: #9a7b2e; background: rgba(214,153,31,.12); border: 1px solid rgba(214,153,31,.3); }

.order { border: 1px solid var(--line); border-radius: 14px; padding: .4rem .9rem; margin-bottom: 1rem; background: #FBF9F3; }
.line { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .6rem 0; border-bottom: 1px dashed var(--line); }
.line:last-child { border-bottom: 0; }
.ln-name { font-size: .9rem; color: #3a4154; }
.ln-amt { font-family: "Space Grotesk", monospace; font-size: .9rem; color: var(--ink); }

/* Coupon */
.coupon { margin-bottom: 1.1rem; }
.cp-input { display: flex; gap: .5rem; }
.cp-input-stacked { margin-top: .6rem; }
.cp-input input {
    flex: 1; border: 1.5px solid var(--line); background: var(--paper); border-radius: 11px; padding: .7rem .85rem;
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

/* Totals */
.totals { border-top: 1px solid var(--line); padding-top: 1rem; }
.t-row { display: flex; align-items: center; justify-content: space-between; padding: .3rem 0; font-size: .9rem; color: var(--muted); }
.t-row .num { font-family: "Space Grotesk", monospace; color: var(--ink); }
.t-row.disc { color: var(--emerald); }
.t-row.disc .num { color: var(--emerald); }
.t-row.grand { border-top: 1px dashed var(--line); margin-top: .35rem; padding-top: .8rem; }
.t-row.grand span { font-weight: 700; color: var(--ink); }
.t-row.grand strong { font-family: "Space Grotesk", monospace; font-size: 1.5rem; color: var(--ink); }

.save-badge { margin-top: .9rem; font-size: .82rem; font-weight: 700; color: var(--emerald);
    background: rgba(22,138,102,.1); border: 1px solid rgba(22,138,102,.22); border-radius: 10px; padding: .55rem .8rem; text-align: center; }

.pay-btn {
    margin-top: 1.2rem; width: 100%; border: 0; cursor: pointer; color: #fff; font-weight: 800; font-size: 1.02rem;
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

@media (max-width: 760px) {
    .shell { grid-template-columns: 1fr; max-width: 460px; }
    .aside { padding: 1.8rem 1.6rem; gap: 1.3rem; }
    .headline { font-size: 1.7rem; }
    .trust { grid-template-columns: 1fr; }
}
</style>
