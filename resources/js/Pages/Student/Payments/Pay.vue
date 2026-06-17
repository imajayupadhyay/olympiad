<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    payment: { type: Object, required: true },     // { id, amount, currency }
    items: { type: Array, default: () => [] },      // [{ id, name, fee_amount }]
    razorpay: { type: Object, required: true },     // { key_id, order_id, amount_paise, currency }
    prefill: { type: Object, default: () => ({}) }, // { name, email, contact }
});

const CHECKOUT_SRC = 'https://checkout.razorpay.com/v1/checkout.js';

const scriptReady = ref(false);
const processing = ref(false);
const banner = ref(null); // { type: 'error' | 'info', text }

// Load the Razorpay checkout script once, on demand.
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
        s.src = CHECKOUT_SRC;
        s.async = true;
        s.onload = () => resolve();
        s.onerror = () => reject(new Error('load failed'));
        document.head.appendChild(s);
    });
}

onMounted(async () => {
    try {
        await loadCheckout();
        scriptReady.value = true;
    } catch {
        banner.value = { type: 'error', text: 'Could not load the payment gateway. Check your connection and refresh.' };
    }
});

function openCheckout() {
    if (!scriptReady.value || processing.value) return;
    banner.value = null;

    const rzp = new window.Razorpay({
        key: props.razorpay.key_id,
        amount: props.razorpay.amount_paise,
        currency: props.razorpay.currency,
        order_id: props.razorpay.order_id,
        name: 'National Olympiad Hunt',
        description: 'Olympiad exam enrollment',
        prefill: props.prefill,
        theme: { color: '#EE6A2C' },
        handler(resp) {
            processing.value = true;
            router.post(
                route('student.payments.verify', props.payment.id),
                {
                    razorpay_payment_id: resp.razorpay_payment_id,
                    razorpay_order_id: resp.razorpay_order_id,
                    razorpay_signature: resp.razorpay_signature,
                },
                { onFinish: () => { processing.value = false; } },
            );
        },
        modal: {
            ondismiss() {
                banner.value = { type: 'info', text: 'Payment cancelled. You can complete it anytime from your payments.' };
            },
        },
    });

    rzp.on('payment.failed', (r) => {
        banner.value = { type: 'error', text: r?.error?.description || 'Payment failed. Please try again.' };
    });

    rzp.open();
}
</script>

<template>
    <Head title="Checkout" />

    <div class="wrap">
        <div class="blob a"></div>
        <div class="blob b"></div>

        <div class="card">
            <div v-if="banner" :class="['banner', banner.type]">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
                {{ banner.text }}
            </div>

            <div class="head">
                <span class="brand">NOH Pay</span>
                <span class="secure"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg> Secured by Razorpay</span>
            </div>

            <!-- order -->
            <div class="order">
                <div v-for="it in items" :key="it.id" class="line">
                    <span>{{ it.name }}</span>
                    <span class="amt">₹{{ it.fee_amount.toLocaleString('en-IN') }}</span>
                </div>
                <div class="line total">
                    <span>Total payable</span>
                    <strong>₹{{ payment.amount.toLocaleString('en-IN') }}</strong>
                </div>
            </div>

            <p class="hint">You'll complete payment securely in the Razorpay window. UPI, cards, net banking and wallets are supported.</p>

            <button class="pay" :disabled="!scriptReady || processing" @click="openCheckout">
                <template v-if="processing">Verifying…</template>
                <template v-else-if="!scriptReady">Loading…</template>
                <template v-else>Pay ₹{{ payment.amount.toLocaleString('en-IN') }}</template>
            </button>

            <div class="alt">
                <Link :href="route('student.exams')" class="cancel">Cancel</Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wrap { position: fixed; inset: 0; display: grid; place-items: center; padding: 1.5rem; overflow: auto;
    background: radial-gradient(80% 60% at 0% 0%, rgba(44,73,166,.07), transparent 55%), #FBF6EC;
    font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
.blob { position: absolute; border-radius: 50%; filter: blur(60px); z-index: 0; }
.blob.a { width: 340px; height: 340px; top: -120px; left: -80px; background: radial-gradient(circle, rgba(238,106,44,.35), transparent 70%); }
.blob.b { width: 300px; height: 300px; bottom: -120px; right: -60px; background: radial-gradient(circle, rgba(44,73,166,.35), transparent 70%); }

.card { position: relative; z-index: 1; background: #fff; border: 1px solid #E7D9BE; border-radius: 22px; padding: 1.6rem; max-width: 420px; width: 100%; box-shadow: 0 40px 80px -30px rgba(10,16,36,.45); animation: pop .4s cubic-bezier(.2,.8,.2,1) both; }

.banner { display: flex; align-items: center; gap: .5rem; font-size: .78rem; font-weight: 600; border-radius: 10px; padding: .5rem .7rem; margin-bottom: 1.2rem; }
.banner svg { width: 16px; height: 16px; flex-shrink: 0; }
.banner.error { color: #b02a1f; background: rgba(220,38,38,.1); border: 1px dashed rgba(220,38,38,.4); }
.banner.info { color: #9a7b2e; background: rgba(214,153,31,.12); border: 1px dashed rgba(214,153,31,.4); }

.head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.2rem; }
.brand { font-family: "Fraunces", serif; font-weight: 700; font-size: 1.2rem; color: #0A1024; }
.secure { display: inline-flex; align-items: center; gap: .35rem; font-size: .76rem; color: #168A66; font-weight: 600; }
.secure svg { width: 14px; height: 14px; }

.order { background: #FBF9F3; border: 1px solid #EADFC8; border-radius: 13px; padding: .9rem 1rem; margin-bottom: 1.2rem; }
.line { display: flex; align-items: center; justify-content: space-between; gap: 1rem; font-size: .9rem; color: #41485a; padding: .35rem 0; }
.line .amt { font-family: "Space Grotesk", monospace; color: #0A1024; }
.line.total { border-top: 1px dashed #EADFC8; margin-top: .3rem; padding-top: .7rem; }
.line.total span { font-weight: 600; color: #5B6373; }
.line.total strong { font-family: "Space Grotesk", monospace; font-size: 1.25rem; color: #0A1024; }

.hint { font-size: .8rem; color: #5B6373; line-height: 1.5; margin: 0 0 1.3rem; }

.pay { width: 100%; border: 0; cursor: pointer; font-weight: 700; font-size: 1rem; color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); padding: .9rem; border-radius: 13px; box-shadow: 0 14px 30px -12px rgba(238,106,44,.6); transition: transform .15s; }
.pay:hover:not(:disabled) { transform: translateY(-2px); }
.pay:disabled { opacity: .7; cursor: progress; }

.alt { display: flex; align-items: center; justify-content: center; margin-top: 1rem; }
.cancel { font-size: .82rem; color: #9aa0ad; text-decoration: none; }
.cancel:hover { color: #5B6373; }

@keyframes pop { from { opacity: 0; transform: translateY(18px) scale(.98); } to { opacity: 1; transform: none; } }
</style>
