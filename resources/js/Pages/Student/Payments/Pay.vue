<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    payment: { type: Object, required: true },     // { id, amount, currency }
    items: { type: Array, default: () => [] },      // [{ id, name, fee_amount }]
});

const form = useForm({ outcome: 'success' });

const pay = (outcome) => {
    form.outcome = outcome;
    form.post(route('student.payments.confirm', props.payment.id));
};
</script>

<template>
    <Head title="Checkout" />

    <div class="wrap">
        <div class="blob a"></div>
        <div class="blob b"></div>

        <div class="card">
            <div class="demo-banner">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
                Demo gateway — no real payment is processed
            </div>

            <div class="head">
                <span class="brand">NOH Pay</span>
                <span class="secure"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg> Secure</span>
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

            <!-- fake card form (visual only) -->
            <div class="fields">
                <label>Card number</label>
                <div class="inp"><input value="4242 4242 4242 4242" readonly /></div>
                <div class="row2">
                    <div>
                        <label>Expiry</label>
                        <div class="inp"><input value="12 / 28" readonly /></div>
                    </div>
                    <div>
                        <label>CVV</label>
                        <div class="inp"><input value="123" readonly /></div>
                    </div>
                </div>
            </div>

            <button class="pay" :disabled="form.processing" @click="pay('success')">
                {{ form.processing ? 'Processing…' : 'Pay ₹' + payment.amount.toLocaleString('en-IN') }}
            </button>

            <div class="alt">
                <button class="fail" :disabled="form.processing" @click="pay('fail')">Simulate failed payment</button>
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

.demo-banner { display: flex; align-items: center; gap: .5rem; font-size: .78rem; font-weight: 600; color: #9a7b2e; background: rgba(214,153,31,.12); border: 1px dashed rgba(214,153,31,.4); border-radius: 10px; padding: .5rem .7rem; margin-bottom: 1.2rem; }
.demo-banner svg { width: 16px; height: 16px; }

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

.fields { margin-bottom: 1.3rem; }
.fields label { display: block; font-size: .78rem; font-weight: 600; color: #5B6373; margin: 0 0 .3rem; }
.inp { background: #F3E9D6; border: 1.5px solid #E7D9BE; border-radius: 11px; padding: .65rem .8rem; margin-bottom: .8rem; }
.inp input { width: 100%; border: 0; background: transparent; outline: none; font-family: "Space Grotesk", monospace; font-size: .95rem; color: #5B6373; letter-spacing: .03em; }
.row2 { display: flex; gap: .8rem; }
.row2 > div { flex: 1; }

.pay { width: 100%; border: 0; cursor: pointer; font-weight: 700; font-size: 1rem; color: #fff; background: linear-gradient(135deg, #1aa177, #168A66); padding: .9rem; border-radius: 13px; box-shadow: 0 14px 30px -12px rgba(22,138,102,.6); transition: transform .15s; }
.pay:hover:not(:disabled) { transform: translateY(-2px); }
.pay:disabled { opacity: .7; cursor: progress; }

.alt { display: flex; align-items: center; justify-content: space-between; margin-top: 1rem; }
.fail { border: 0; background: transparent; color: #DC2626; cursor: pointer; font-size: .82rem; font-weight: 600; }
.fail:hover { text-decoration: underline; }
.cancel { font-size: .82rem; color: #9aa0ad; text-decoration: none; }
.cancel:hover { color: #5B6373; }

@keyframes pop { from { opacity: 0; transform: translateY(18px) scale(.98); } to { opacity: 1; transform: none; } }
</style>
