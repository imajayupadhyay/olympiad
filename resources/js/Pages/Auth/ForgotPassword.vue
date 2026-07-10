<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    step:          { type: String, default: 'email' },   // 'email' | 'otp' | 'reset'
    email:         { type: String, default: '' },
    maskedEmail:   { type: String, default: '' },
    otpLength:     { type: Number, default: 6 },
    expiryMinutes: { type: Number, default: 10 },
    resendIn:      { type: Number, default: 0 },
    status:        { type: String, default: '' },
});

const heading = computed(() => ({
    email: 'Reset your password',
    otp:   'Enter your code',
    reset: 'Choose a new password',
}[props.step]));

const subheading = computed(() => ({
    email: 'Enter your account email and we\'ll send you a one-time code.',
    otp:   `We sent a ${props.otpLength}-digit code to ${props.maskedEmail}. It expires in ${props.expiryMinutes} minutes.`,
    reset: 'Your code is verified. Set a new password to finish.',
}[props.step]));

/* ── Step 1: email ── */
const emailForm = useForm({ email: props.email || '' });
const submitEmail = () => emailForm.post(route('password.otp.send'), { preserveScroll: true });

/* ── Step 2: OTP ── */
const otpForm = useForm({ otp: '' });
const digits = ref(Array(props.otpLength).fill(''));
const boxes = ref([]);

const setBox = (i, val) => {
    const clean = (val || '').replace(/\D/g, '');
    digits.value[i] = clean.slice(-1);
    if (clean && i < props.otpLength - 1) nextTick(() => boxes.value[i + 1]?.focus());
};
const onKeydown = (i, e) => {
    if (e.key === 'Backspace' && !digits.value[i] && i > 0) {
        nextTick(() => boxes.value[i - 1]?.focus());
    }
    if (e.key === 'ArrowLeft' && i > 0) boxes.value[i - 1]?.focus();
    if (e.key === 'ArrowRight' && i < props.otpLength - 1) boxes.value[i + 1]?.focus();
};
const onPaste = (e) => {
    e.preventDefault();
    const chars = (e.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, props.otpLength).split('');
    chars.forEach((c, idx) => { digits.value[idx] = c; });
    nextTick(() => boxes.value[Math.min(chars.length, props.otpLength - 1)]?.focus());
};
watch(digits, (d) => { otpForm.otp = d.join(''); }, { deep: true });

const otpComplete = computed(() => digits.value.every((d) => d !== ''));
const submitOtp = () => {
    if (!otpComplete.value) return;
    otpForm.post(route('password.otp.verify'), {
        preserveScroll: true,
        onError: () => {
            digits.value = Array(props.otpLength).fill('');
            nextTick(() => boxes.value[0]?.focus());
        },
    });
};

/* resend countdown */
const countdown = ref(props.resendIn);
let timer = null;
const startTimer = () => {
    clearInterval(timer);
    timer = setInterval(() => { if (countdown.value > 0) countdown.value--; else clearInterval(timer); }, 1000);
};
watch(() => props.resendIn, (v) => { countdown.value = v; startTimer(); });
const resend = () => {
    if (countdown.value > 0) return;
    emailForm.email = props.email;
    emailForm.post(route('password.otp.send'), { preserveScroll: true });
};

/* ── Step 3: new password ── */
const resetForm = useForm({ password: '', password_confirmation: '' });
const showPw = ref(false);
const submitReset = () => resetForm.post(route('password.otp.reset'), { preserveScroll: true });

const restart = () => router.post(route('password.otp.cancel'));

/* focus management on step change */
const focusStep = () => nextTick(() => {
    if (props.step === 'otp') boxes.value[0]?.focus();
});
onMounted(() => { startTimer(); focusStep(); });
watch(() => props.step, focusStep);
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <Head title="Reset password" />

    <AuthLayout eyebrow="Student Portal" :heading="heading" :subheading="subheading">
        <div v-if="status" class="status-pill">{{ status }}</div>

        <!-- Step 1 — email -->
        <form v-if="step === 'email'" class="auth-form" @submit.prevent="submitEmail">
            <div class="field">
                <label for="email">Email address</label>
                <div class="control">
                    <span class="ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m3 7 9 6 9-6"/></svg>
                    </span>
                    <input id="email" type="email" v-model="emailForm.email" required autofocus autocomplete="username" placeholder="you@example.com" />
                </div>
                <p v-if="emailForm.errors.email" class="err">{{ emailForm.errors.email }}</p>
            </div>

            <button type="submit" class="cta" :class="{ busy: emailForm.processing }" :disabled="emailForm.processing">
                <span>{{ emailForm.processing ? 'Sending code…' : 'Send code' }}</span>
            </button>

            <p class="switch"><Link :href="route('login')">← Back to log in</Link></p>
        </form>

        <!-- Step 2 — OTP -->
        <form v-else-if="step === 'otp'" class="auth-form" @submit.prevent="submitOtp">
            <div class="field">
                <label>Verification code</label>
                <div class="otp" @paste="onPaste">
                    <input
                        v-for="(d, i) in digits"
                        :key="i"
                        ref="boxes"
                        class="otp-box"
                        :class="{ filled: d }"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="1"
                        :value="d"
                        @input="setBox(i, $event.target.value)"
                        @keydown="onKeydown(i, $event)"
                        aria-label="Digit"
                    />
                </div>
                <p v-if="otpForm.errors.otp" class="err">{{ otpForm.errors.otp }}</p>
            </div>

            <button type="submit" class="cta" :class="{ busy: otpForm.processing }" :disabled="otpForm.processing || !otpComplete">
                <span>{{ otpForm.processing ? 'Verifying…' : 'Verify code' }}</span>
            </button>

            <div class="resend-row">
                <span v-if="countdown > 0" class="muted">Resend code in {{ countdown }}s</span>
                <button v-else type="button" class="link-btn" @click="resend">Resend code</button>
                <button type="button" class="link-btn" @click="restart">Use a different email</button>
            </div>

            <p class="note">🔒 Never share this code. Our team will never ask you for it.</p>
        </form>

        <!-- Step 3 — new password -->
        <form v-else class="auth-form" @submit.prevent="submitReset">
            <div class="field">
                <label for="password">New password</label>
                <div class="control">
                    <span class="ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    </span>
                    <input :type="showPw ? 'text' : 'password'" id="password" v-model="resetForm.password" required autocomplete="new-password" placeholder="At least 8 characters" />
                    <button type="button" class="toggle" @click="showPw = !showPw" :aria-label="showPw ? 'Hide password' : 'Show password'">
                        <svg v-if="!showPw" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c6.5 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.36M6.6 6.6A13.2 13.2 0 0 0 2 11s3.5 7 10 7a9.1 9.1 0 0 0 4.1-.94"/><path d="m2 2 20 20"/></svg>
                    </button>
                </div>
                <p v-if="resetForm.errors.password" class="err">{{ resetForm.errors.password }}</p>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <div class="control">
                    <span class="ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    </span>
                    <input :type="showPw ? 'text' : 'password'" id="password_confirmation" v-model="resetForm.password_confirmation" required autocomplete="new-password" placeholder="Re-enter your new password" />
                </div>
            </div>

            <button type="submit" class="cta" :class="{ busy: resetForm.processing }" :disabled="resetForm.processing">
                <span>{{ resetForm.processing ? 'Updating…' : 'Reset password & sign in' }}</span>
            </button>
        </form>
    </AuthLayout>
</template>

<style scoped>
.status-pill {
    background: rgba(22,138,102,.1); color: #126b50;
    border: 1px solid rgba(22,138,102,.25);
    padding: .6rem .9rem; border-radius: 12px;
    font-size: .88rem; font-weight: 600; margin-bottom: 1.2rem;
}

.auth-form { display: grid; gap: 1.1rem; }
.field { display: grid; gap: .45rem; }
.field label { font-size: .85rem; font-weight: 600; color: #0A1024; }

.control {
    position: relative; display: flex; align-items: center;
    background: #fff; border: 1.5px solid #E7D9BE; border-radius: 14px;
    transition: border-color .2s, box-shadow .2s;
}
.control:focus-within { border-color: #EE6A2C; box-shadow: 0 0 0 4px rgba(238,106,44,.12); }
.control .ic { display: grid; place-items: center; width: 44px; color: rgba(10,16,36,.4); flex-shrink: 0; }
.control .ic svg { width: 18px; height: 18px; }
.control input {
    flex: 1; border: 0; background: transparent; outline: none;
    padding: .8rem .9rem .8rem 0; font-size: .96rem; color: #0A1024;
    font-family: "Plus Jakarta Sans", sans-serif;
}
.control input::placeholder { color: rgba(10,16,36,.32); }
.toggle { border: 0; background: transparent; cursor: pointer; padding: 0 .9rem; color: rgba(10,16,36,.4); display: grid; place-items: center; }
.toggle:hover { color: #EE6A2C; }
.toggle svg { width: 18px; height: 18px; }

.err { color: #DC2626; font-size: .8rem; margin: 0; }

/* OTP boxes */
.otp { display: flex; gap: .5rem; justify-content: space-between; }
.otp-box {
    width: 100%; aspect-ratio: 1 / 1; max-width: 52px;
    text-align: center; border: 1.5px solid #E7D9BE; border-radius: 13px;
    background: #fff; color: #0A1024;
    font-family: "Space Grotesk", monospace; font-size: 1.5rem; font-weight: 700;
    transition: border-color .18s, box-shadow .18s, transform .12s;
}
.otp-box:focus { outline: none; border-color: #EE6A2C; box-shadow: 0 0 0 4px rgba(238,106,44,.12); transform: translateY(-1px); }
.otp-box.filled { border-color: #EE6A2C; background: #FEF6F0; }

.cta {
    position: relative; overflow: hidden; margin-top: .3rem;
    border: 0; cursor: pointer;
    background: linear-gradient(135deg, #F2854E, #EE6A2C);
    color: #fff; font-weight: 700; font-size: 1rem;
    padding: .9rem 1rem; border-radius: 14px;
    box-shadow: 0 14px 30px -12px rgba(238,106,44,.7);
    transition: transform .15s, box-shadow .2s, opacity .2s;
}
.cta:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 18px 36px -12px rgba(238,106,44,.8); }
.cta:active { transform: translateY(0); }
.cta:disabled { opacity: .55; cursor: not-allowed; }
.cta.busy { opacity: .7; cursor: progress; }
.cta::after {
    content: ""; position: absolute; top: 0; left: -120%; width: 60%; height: 100%;
    background: linear-gradient(120deg, transparent, rgba(255,255,255,.35), transparent);
    transition: left .6s;
}
.cta:hover:not(:disabled)::after { left: 130%; }

.resend-row { display: flex; align-items: center; justify-content: space-between; gap: .8rem; flex-wrap: wrap; }
.muted { font-size: .85rem; color: rgba(10,16,36,.5); }
.link-btn { border: 0; background: transparent; cursor: pointer; color: #C9501A; font-weight: 700; font-size: .85rem; font-family: inherit; padding: 0; }
.link-btn:hover { text-decoration: underline; }

.note { font-size: .8rem; color: rgba(10,16,36,.55); text-align: center; margin: .2rem 0 0; }

.switch { text-align: center; margin: .4rem 0 0; font-size: .92rem; }
.switch a { color: #C9501A; font-weight: 700; text-decoration: none; }
.switch a:hover { text-decoration: underline; }

@media (prefers-reduced-motion: reduce) {
    .cta, .otp-box { transition: none; }
    .cta::after { display: none; }
}
</style>
