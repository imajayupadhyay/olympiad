<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Stepper from './Components/Stepper.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

// ── Coupon (shared student endpoints) ──
const applyCoupon = () => {
    if (!couponForm.code.trim()) return;
    couponForm.transform((d) => ({ code: d.code.trim().toUpperCase() }))
        .post(route('student.payments.coupon.apply', props.payment.id), { preserveScroll: true });
};
const removeCoupon = () => {
    router.delete(route('student.payments.coupon.remove', props.payment.id), { preserveScroll: true });
};

// ── Razorpay checkout script ──
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
    <Head title="Checkout" />

    <AuthLayout
        eyebrow="Step 3 of 3"
        heading="Review & pay"
        subheading="Apply a coupon if you have one, then pay securely to lock in your seat."
    >
        <Stepper :current="3" />

        <div v-if="banner" :class="['banner', banner.type]">{{ banner.text }}</div>

        <!-- Admit-ticket: olympiads above the perforation, money below it -->
        <div class="ticket">
            <div class="ticket-head">
                <span class="t-kicker">Enrolment ticket</span>
                <span class="t-count">{{ items.length }} olympiad{{ items.length === 1 ? '' : 's' }}</span>
            </div>

            <ul class="t-items">
                <li v-for="it in items" :key="it.id">
                    <span class="it-name">{{ it.name }}</span>
                    <span class="it-fee" :class="{ free: it.fee_amount <= 0 }">
                        {{ it.fee_amount <= 0 ? 'FREE' : '₹' + fmt(it.fee_amount) }}
                    </span>
                </li>
            </ul>

            <div class="perf" aria-hidden="true"></div>

            <div class="ticket-foot">
                <!-- Coupon -->
                <div class="coupon">
                    <template v-if="payment.coupon">
                        <div class="cp-applied">
                            <div class="cp-left">
                                <span class="cp-tag">{{ payment.coupon.code }}</span>
                                <span class="cp-msg">Coupon applied</span>
                            </div>
                            <button type="button" class="cp-remove" @click="removeCoupon">Remove</button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="cp-input">
                            <input
                                v-model="couponForm.code"
                                type="text"
                                placeholder="Have a coupon code?"
                                spellcheck="false"
                                @keydown.enter.prevent="applyCoupon"
                            />
                            <button type="button" :disabled="couponForm.processing || !couponForm.code.trim()" @click="applyCoupon">
                                {{ couponForm.processing ? '…' : 'Apply' }}
                            </button>
                        </div>
                    </template>
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
                        <strong class="num" :class="{ free: isFree }">{{ isFree ? 'FREE' : '₹' + fmt(payment.amount) }}</strong>
                    </div>
                </div>

                <div v-if="payment.discount > 0" class="save-badge">
                    🎉 You save ₹{{ fmt(payment.discount) }} on this order
                </div>
            </div>
        </div>

        <button class="pay-btn" :class="{ free: isFree }" :disabled="processing" @click="pay">
            <span v-if="processing" class="spin"></span>
            <template v-if="processing">Processing…</template>
            <template v-else-if="isFree">Enrol for free →</template>
            <template v-else>Pay ₹{{ fmt(payment.amount) }}</template>
        </button>

        <p class="secured">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            Encrypted &amp; processed by <strong>Razorpay</strong> · UPI · Cards · Net Banking
        </p>

        <Link :href="route('register.olympiads')" class="back">← Change selection</Link>
    </AuthLayout>
</template>

<style scoped>
/* Brand tokens — self-contained so the component holds up anywhere. */
.banner, .ticket, .pay-btn, .secured, .back {
    --ink: #0A1024; --ink-2: #131C3D; --paper: #FBF6EC; --paper-2: #F3E9D6;
    --line: #E7D9BE; --saffron: #EE6A2C; --saffron-dk: #C9501A;
    --gold: #D6991F; --gold-lt: #F2C84B; --emerald: #168A66; --muted: #5B6373;
}

.banner { font-size: .82rem; font-weight: 600; border-radius: 11px; padding: .65rem .85rem; margin-bottom: 1rem; }
.banner.error { color: #b02a1f; background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.25); }
.banner.info { color: #9a7b2e; background: rgba(214,153,31,.12); border: 1px solid rgba(214,153,31,.3); }

/* ── Ticket ── */
.ticket {
    position: relative; background: #fff; border: 1px solid var(--line);
    border-radius: 18px; box-shadow: 0 20px 50px -28px rgba(10,16,36,.35);
}
.ticket-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.1rem 1.3rem .4rem;
}
.t-kicker {
    font-family: "Space Grotesk", monospace; font-size: .68rem; font-weight: 700;
    letter-spacing: .15em; text-transform: uppercase; color: var(--saffron);
}
.t-count { font-size: .76rem; font-weight: 600; color: var(--muted); }

.t-items { list-style: none; margin: 0; padding: .4rem 1.3rem .9rem; display: grid; gap: .1rem; }
.t-items li {
    display: flex; align-items: baseline; justify-content: space-between; gap: 1rem;
    padding: .55rem 0; border-bottom: 1px dashed var(--line);
}
.t-items li:last-child { border-bottom: 0; }
.it-name { font-size: .92rem; color: #2c3346; line-height: 1.35; }
.it-fee { font-family: "Space Grotesk", monospace; font-weight: 600; font-size: .9rem; color: var(--ink); white-space: nowrap; }
.it-fee.free { color: var(--emerald); }

/* Perforation: dashed seam with a notch punched out of each edge */
.perf { position: relative; height: 0; border-top: 2px dashed var(--line); margin: 0 .9rem; }
.perf::before, .perf::after {
    content: ""; position: absolute; top: -12px; width: 24px; height: 24px;
    border-radius: 50%; background: var(--paper); border: 1px solid var(--line);
}
.perf::before { left: -23px; }
.perf::after  { right: -23px; }

.ticket-foot { padding: 1.2rem 1.3rem 1.3rem; }

/* ── Coupon ── */
.coupon { margin-bottom: 1rem; }
.cp-input { display: flex; gap: .5rem; }
.cp-input input {
    flex: 1; border: 1.5px solid var(--line); background: var(--paper); border-radius: 11px;
    padding: .7rem .85rem; font-family: "Space Grotesk", monospace; font-size: .9rem;
    letter-spacing: .04em; text-transform: uppercase; color: var(--ink); outline: none; transition: border-color .15s;
}
.cp-input input::placeholder { text-transform: none; letter-spacing: 0; font-family: "Plus Jakarta Sans", sans-serif; color: var(--muted); }
.cp-input input:focus { border-color: var(--saffron); }
.cp-input button {
    border: 0; cursor: pointer; background: var(--ink); color: #fff; font-weight: 700; font-size: .85rem;
    padding: 0 1.2rem; border-radius: 11px; transition: background .15s; min-width: 76px;
}
.cp-input button:hover:not(:disabled) { background: var(--ink-2); }
.cp-input button:disabled { opacity: .45; cursor: not-allowed; }

.cp-applied {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    border: 1.5px dashed var(--gold); background: rgba(242,200,75,.12); border-radius: 12px; padding: .65rem .85rem;
}
.cp-left { display: flex; align-items: center; gap: .6rem; }
.cp-tag {
    font-family: "Space Grotesk", monospace; font-weight: 700; letter-spacing: .05em; color: var(--ink);
    background: var(--gold-lt); padding: .25rem .55rem; border-radius: 7px; font-size: .82rem;
}
.cp-msg { font-size: .82rem; font-weight: 600; color: #97751e; }
.cp-remove { border: 0; background: transparent; color: var(--saffron-dk); font-weight: 700; font-size: .8rem; cursor: pointer; }
.cp-remove:hover { text-decoration: underline; }
.cp-err { color: #b02a1f; font-size: .78rem; font-weight: 600; margin: .5rem 0 0; }
.cp-ok { color: var(--emerald); font-size: .78rem; font-weight: 600; margin: .5rem 0 0; }

/* ── Totals ── */
.totals { border-top: 1px solid var(--line); padding-top: .9rem; }
.t-row { display: flex; align-items: center; justify-content: space-between; padding: .3rem 0; font-size: .9rem; color: var(--muted); }
.t-row .num { font-family: "Space Grotesk", monospace; color: var(--ink); }
.t-row.disc, .t-row.disc .num { color: var(--emerald); }
.t-row.grand { border-top: 1px dashed var(--line); margin-top: .35rem; padding-top: .8rem; }
.t-row.grand span { font-weight: 700; color: var(--ink); }
.t-row.grand strong {
    font-family: "Space Grotesk", monospace; font-size: 1.5rem; color: var(--ink);
    padding: .1rem .55rem; border-radius: 9px;
    background: linear-gradient(135deg, rgba(242,200,75,.28), rgba(214,153,31,.16));
    box-shadow: inset 0 0 0 1px rgba(214,153,31,.3);
}
.t-row.grand strong.free { color: var(--emerald); background: rgba(22,138,102,.12); box-shadow: inset 0 0 0 1px rgba(22,138,102,.25); }

.save-badge {
    margin-top: .9rem; font-size: .82rem; font-weight: 700; color: var(--emerald);
    background: rgba(22,138,102,.1); border: 1px solid rgba(22,138,102,.22);
    border-radius: 10px; padding: .55rem .8rem; text-align: center;
}

/* ── Pay ── */
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

.secured {
    display: flex; align-items: center; justify-content: center; gap: .45rem;
    margin: 1rem 0 0; font-size: .78rem; color: var(--muted);
}
.secured svg { width: 15px; height: 15px; color: var(--emerald); }
.secured strong { color: var(--ink); font-weight: 700; }

.back { display: block; text-align: center; margin-top: .9rem; font-size: .85rem; font-weight: 600; color: var(--saffron-dk); text-decoration: none; }
.back:hover { text-decoration: underline; }

@media (prefers-reduced-motion: reduce) {
    .pay-btn { transition: none; }
    .pay-btn:hover:not(:disabled) { transform: none; }
    .spin { animation: none; }
}
</style>
