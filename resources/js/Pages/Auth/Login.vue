<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: '' },
    otpFlow: {
        type: Object,
        default: () => ({
            step: 'identifier',
            channel: null,
            maskedIdentifier: '',
            otpLength: 6,
            expiryMinutes: 5,
            resendIn: 0,
            candidates: [],
            availability: { email: true, whatsapp: false },
        }),
    },
});

const mode = ref('otp');
const showPassword = ref(false);
const otpInput = ref(null);

const identifierForm = useForm({ identifier: '', remember: false });
const passwordForm = useForm({ email: '', password: '', remember: false });
const otpForm = useForm({ otp: '' });
const resendForm = useForm({});
const selectForm = useForm({ user_id: null });
const cancelForm = useForm({});

const step = computed(() => props.otpFlow?.step || 'identifier');
const emailOtpAvailable = computed(() => props.otpFlow?.availability?.email !== false);

const heading = computed(() => {
    if (mode.value === 'password') return 'Welcome back';
    if (step.value === 'otp') return 'Check your inbox';
    if (step.value === 'select') return 'Choose a student';
    return 'Welcome back';
});

const subheading = computed(() => {
    if (mode.value === 'password') return 'Use your email and password to continue.';
    if (step.value === 'otp') {
        return `Enter the ${props.otpFlow.otpLength}-digit code we sent to your registered email.`;
    }
    if (step.value === 'select') return 'This parent number is linked to more than one student. Select who is signing in.';
    return 'Enter your registered email address. We’ll send you a secure one-time code.';
});

const sendCode = () => {
    if (!emailOtpAvailable.value) return;
    identifierForm.post(route('login.otp.send'), { preserveScroll: true });
};

const verifyCode = () => {
    otpForm.otp = otpForm.otp.replace(/\D/g, '').slice(0, props.otpFlow.otpLength || 6);
    otpForm.post(route('login.otp.verify'), {
        preserveScroll: true,
        onError: () => {
            otpForm.reset('otp');
            nextTick(() => otpInput.value?.focus());
        },
    });
};

const submitPassword = () => {
    passwordForm.post(route('login'), {
        onFinish: () => passwordForm.reset('password'),
    });
};

const resend = () => {
    if (countdown.value > 0) return;
    resendForm.post(route('login.otp.resend'), { preserveScroll: true });
};

const chooseStudent = (id) => {
    selectForm.user_id = id;
    selectForm.post(route('login.otp.select'), { preserveScroll: true });
};

const restart = () => cancelForm.post(route('login.otp.cancel'));

const wholeSeconds = (value) => Math.max(0, Math.ceil(Number(value) || 0));
const countdown = ref(wholeSeconds(props.otpFlow.resendIn));
let countdownTimer = null;
const startCountdown = () => {
    clearInterval(countdownTimer);
    countdownTimer = setInterval(() => {
        if (countdown.value > 0) countdown.value -= 1;
        else clearInterval(countdownTimer);
    }, 1000);
};

watch(() => props.otpFlow.resendIn, (value) => {
    countdown.value = wholeSeconds(value);
    startCountdown();
});
watch(step, (value) => {
    if (value === 'otp') nextTick(() => otpInput.value?.focus());
});
onMounted(() => {
    startCountdown();
    if (step.value === 'otp') nextTick(() => otpInput.value?.focus());
});
onBeforeUnmount(() => clearInterval(countdownTimer));
</script>

<template>
    <Head title="Log in" />

    <AuthLayout eyebrow="Student Portal" :heading="heading" :subheading="subheading">
        <div v-if="status && step !== 'otp'" class="status-pill">{{ status }}</div>

        <!-- Passwordless identifier -->
        <form v-if="mode === 'otp' && step === 'identifier'" class="auth-form" @submit.prevent="sendCode">
            <div class="field">
                <label for="identifier">Email address</label>
                <div class="control" :class="{ unavailable: !emailOtpAvailable }">
                    <span class="ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m3 7 9 6 9-6"/></svg>
                    </span>
                    <input
                        id="identifier"
                        v-model="identifierForm.identifier"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        inputmode="email"
                        placeholder="Enter your email"
                    />
                </div>
                <p v-if="identifierForm.errors.identifier" class="err">{{ identifierForm.errors.identifier }}</p>
                <p v-else-if="!emailOtpAvailable" class="err">
                    Email login is temporarily unavailable. Please use your password for now.
                </p>
                <p v-else class="hint">We’ll email you a secure one-time code. No password needed.</p>
            </div>

            <label class="remember">
                <input v-model="identifierForm.remember" type="checkbox" />
                <span>Keep me signed in on this device</span>
            </label>

            <button type="submit" class="cta" :class="{ busy: identifierForm.processing }" :disabled="identifierForm.processing || !emailOtpAvailable">
                <span>{{ identifierForm.processing ? 'Sending securely…' : 'Send login code' }}</span>
            </button>

            <div class="divider"><span>or</span></div>
            <button type="button" class="password-option" @click="mode = 'password'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                Use password instead
            </button>
        </form>

        <!-- OTP verification -->
        <form v-else-if="mode === 'otp' && step === 'otp'" class="auth-form" @submit.prevent="verifyCode">
            <div class="delivery-card">
                <span class="delivery-icon" :class="otpFlow.channel">
                    <svg v-if="otpFlow.channel === 'whatsapp'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.5 11.6a8.5 8.5 0 0 1-12.6 7.5L3 20.5l1.4-4.7A8.5 8.5 0 1 1 20.5 11.6Z"/><path d="M8.2 7.8c.2-.5.4-.5.8-.5h.4c.2 0 .4.1.5.4l.8 1.9c.1.3 0 .5-.2.7l-.7.8c-.2.2-.1.4 0 .6.7 1.3 1.7 2.3 3 2.9"/></svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m3 7 9 6 9-6"/></svg>
                </span>
                <div><strong>Email sent securely</strong><span>{{ otpFlow.maskedIdentifier }}</span></div>
            </div>

            <div class="field">
                <label for="otp">{{ otpFlow.otpLength }}-digit login code</label>
                <input
                    id="otp"
                    ref="otpInput"
                    v-model="otpForm.otp"
                    class="otp-input"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    :maxlength="otpFlow.otpLength"
                    :placeholder="'•'.repeat(otpFlow.otpLength)"
                    @input="otpForm.otp = otpForm.otp.replace(/\D/g, '').slice(0, otpFlow.otpLength)"
                />
                <p v-if="otpForm.errors.otp" class="err">{{ otpForm.errors.otp }}</p>
                <p v-if="resendForm.errors.identifier" class="err">{{ resendForm.errors.identifier }}</p>
            </div>

            <button type="submit" class="cta" :class="{ busy: otpForm.processing }" :disabled="otpForm.processing || otpForm.otp.length !== otpFlow.otpLength">
                <span>{{ otpForm.processing ? 'Verifying…' : 'Verify & log in' }}</span>
            </button>

            <div class="resend-row">
                <span v-if="countdown > 0" class="muted">Resend in {{ countdown }}s</span>
                <button v-else type="button" class="link-btn" :disabled="resendForm.processing" @click="resend">
                    {{ resendForm.processing ? 'Sending…' : 'Resend code' }}
                </button>
                <button type="button" class="link-btn" @click="restart">Use a different email</button>
            </div>

            <p class="security-note"><span>🔒</span> Never share this code. Our team will never ask you for it.</p>
            <button type="button" class="text-option" @click="mode = 'password'">Use password instead</button>
        </form>

        <!-- Shared parent number account chooser (only after OTP verification) -->
        <div v-else-if="mode === 'otp' && step === 'select'" class="auth-form">
            <div class="student-list">
                <button
                    v-for="student in otpFlow.candidates"
                    :key="student.id"
                    type="button"
                    class="student-card"
                    :disabled="selectForm.processing"
                    @click="chooseStudent(student.id)"
                >
                    <span class="avatar">{{ student.name.charAt(0).toUpperCase() }}</span>
                    <span class="student-copy">
                        <strong>{{ student.name }}</strong>
                        <small>{{ student.class }}<template v-if="student.school"> · {{ student.school }}</template></small>
                    </span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            </div>
            <p v-if="selectForm.errors.user_id" class="err">{{ selectForm.errors.user_id }}</p>
            <button type="button" class="text-option" @click="restart">Use a different email</button>
            <button type="button" class="text-option" @click="mode = 'password'">Use password instead</button>
        </div>

        <!-- Password fallback -->
        <form v-else class="auth-form" @submit.prevent="submitPassword">
            <div class="field">
                <label for="email">Email address</label>
                <div class="control">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m3 7 9 6 9-6"/></svg></span>
                    <input id="email" v-model="passwordForm.email" type="email" required autocomplete="username" placeholder="you@example.com" />
                </div>
                <p v-if="passwordForm.errors.email" class="err">{{ passwordForm.errors.email }}</p>
            </div>

            <div class="field">
                <div class="label-row">
                    <label for="password">Password</label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="link-mini">Forgot?</Link>
                </div>
                <div class="control">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span>
                    <input id="password" v-model="passwordForm.password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" placeholder="••••••••" />
                    <button type="button" class="toggle" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                        <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m2 2 20 20"/><path d="M9.9 4.2A9 9 0 0 1 12 4c6.5 0 10 7 10 7a13 13 0 0 1-2 3M6.6 6.6A13 13 0 0 0 2 11s3.5 7 10 7a9 9 0 0 0 4.1-.9"/></svg>
                    </button>
                </div>
                <p v-if="passwordForm.errors.password" class="err">{{ passwordForm.errors.password }}</p>
            </div>

            <label class="remember">
                <input v-model="passwordForm.remember" type="checkbox" />
                <span>Keep me signed in</span>
            </label>

            <button type="submit" class="cta" :class="{ busy: passwordForm.processing }" :disabled="passwordForm.processing">
                <span>{{ passwordForm.processing ? 'Signing in…' : 'Log in with password' }}</span>
            </button>
            <button type="button" class="password-option otp-option" @click="mode = 'otp'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v3M5.6 5.6l2.1 2.1M3 12h3m-.4 6.4 2.1-2.1M12 18v3m6.4-2.6-2.1-2.1M18 12h3m-4.7-4.3 2.1-2.1"/><circle cx="12" cy="12" r="3"/></svg>
                Use a one-time code instead
            </button>
        </form>

        <p class="switch">
            New to National Olympiad Hunt?
            <Link :href="route('register')">Create an account</Link>
        </p>
    </AuthLayout>
</template>

<style scoped>
.status-pill { background:rgba(22,138,102,.1);color:#126b50;border:1px solid rgba(22,138,102,.25);padding:.65rem .9rem;border-radius:12px;font-size:.86rem;font-weight:600;margin-bottom:1.15rem; }
.auth-form { display:grid;gap:1.05rem; }
.field { display:grid;gap:.45rem; }
.label-row { display:flex;align-items:center;justify-content:space-between; }
.field label { font-size:.85rem;font-weight:700;color:#0A1024; }
.control { position:relative;display:flex;align-items:center;background:#fff;border:1.5px solid #E7D9BE;border-radius:14px;transition:border-color .2s,box-shadow .2s; }
.control:focus-within { border-color:#EE6A2C;box-shadow:0 0 0 4px rgba(238,106,44,.12); }
.control.unavailable { border-color:rgba(220,38,38,.35); }
.control .ic { display:grid;place-items:center;width:44px;color:rgba(10,16,36,.4);flex-shrink:0; }
.control .ic svg { width:18px;height:18px; }
.control input { flex:1;min-width:0;border:0;background:transparent;outline:none;padding:.84rem .6rem .84rem 0;font-size:.94rem;color:#0A1024;font-family:"Plus Jakarta Sans",sans-serif; }
.control input::placeholder { color:rgba(10,16,36,.32); }
.hint { margin:0;font-size:.76rem;line-height:1.45;color:rgba(10,16,36,.5); }
.err { margin:0;color:#DC2626;font-size:.79rem;line-height:1.45; }
.remember { display:flex;align-items:center;gap:.55rem;font-size:.86rem;color:rgba(10,16,36,.68);cursor:pointer;user-select:none; }
.remember input { width:17px;height:17px;accent-color:#EE6A2C; }
.cta { position:relative;overflow:hidden;margin-top:.2rem;border:0;cursor:pointer;background:linear-gradient(135deg,#F2854E,#EE6A2C);color:#fff;font-weight:800;font-size:.96rem;padding:.92rem 1rem;border-radius:14px;box-shadow:0 14px 30px -12px rgba(238,106,44,.7);transition:transform .15s,box-shadow .2s,opacity .2s; }
.cta:hover:not(:disabled) { transform:translateY(-2px);box-shadow:0 18px 36px -12px rgba(238,106,44,.8); }
.cta:disabled { opacity:.52;cursor:not-allowed; }
.cta.busy { cursor:progress; }
.divider { display:flex;align-items:center;gap:.7rem;color:rgba(10,16,36,.35);font-size:.75rem; }
.divider::before,.divider::after { content:"";height:1px;flex:1;background:#E7D9BE; }
.password-option { display:flex;align-items:center;justify-content:center;gap:.55rem;width:100%;border:1.5px solid #E7D9BE;background:#fff;color:#0A1024;border-radius:14px;padding:.78rem;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;transition:border-color .2s,background .2s,transform .15s; }
.password-option:hover { border-color:#EE6A2C;background:#FEF6F0;transform:translateY(-1px); }
.password-option svg { width:17px;height:17px;color:#C9501A; }
.otp-option { margin-top:.1rem; }
.delivery-card { display:flex;align-items:center;gap:.75rem;background:#fff;border:1px solid #E7D9BE;border-radius:14px;padding:.75rem .85rem; }
.delivery-card div { display:grid;gap:.1rem; }
.delivery-card strong { font-size:.82rem;color:#0A1024; }
.delivery-card span:not(.delivery-icon) { font-size:.77rem;color:rgba(10,16,36,.55); }
.delivery-icon { display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:rgba(44,73,166,.1);color:#2C49A6; }
.delivery-icon.whatsapp { background:rgba(22,138,102,.1);color:#168A66; }
.delivery-icon svg { width:20px;height:20px; }
.otp-input { width:100%;box-sizing:border-box;text-align:center;border:1.5px solid #E7D9BE;border-radius:15px;background:#fff;color:#0A1024;padding:.75rem .5rem;font-family:"Space Grotesk",monospace;font-size:1.8rem;font-weight:700;letter-spacing:.55em;text-indent:.55em;font-variant-numeric:tabular-nums;outline:none;transition:border-color .2s,box-shadow .2s; }
.otp-input:focus { border-color:#EE6A2C;box-shadow:0 0 0 4px rgba(238,106,44,.12); }
.otp-input::placeholder { color:rgba(10,16,36,.16); }
.resend-row { display:flex;align-items:center;justify-content:space-between;gap:.7rem;flex-wrap:wrap; }
.muted { font-size:.82rem;color:rgba(10,16,36,.5); }
.link-btn,.text-option { border:0;background:transparent;padding:0;color:#C9501A;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer; }
.link-btn:hover,.text-option:hover { text-decoration:underline; }
.security-note { display:flex;justify-content:center;gap:.35rem;margin:0;padding:.65rem;border-radius:11px;background:rgba(22,138,102,.07);color:rgba(10,16,36,.6);font-size:.76rem;text-align:center; }
.text-option { justify-self:center; }
.student-list { display:grid;gap:.7rem; }
.student-card { display:flex;align-items:center;gap:.75rem;width:100%;text-align:left;border:1.5px solid #E7D9BE;background:#fff;border-radius:15px;padding:.8rem;cursor:pointer;font-family:inherit;transition:border-color .2s,box-shadow .2s,transform .15s; }
.student-card:hover { border-color:#EE6A2C;box-shadow:0 8px 24px rgba(10,16,36,.08);transform:translateY(-1px); }
.student-card:disabled { opacity:.6;cursor:progress; }
.student-card > svg { width:19px;height:19px;color:#C9501A;margin-left:auto; }
.avatar { display:grid;place-items:center;width:40px;height:40px;border-radius:12px;background:#0A1024;color:#F2C84B;font-family:"Fraunces",serif;font-size:1.05rem;font-weight:700; }
.student-copy { display:grid;gap:.12rem;min-width:0; }
.student-copy strong { color:#0A1024;font-size:.9rem; }
.student-copy small { color:rgba(10,16,36,.55);font-size:.73rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:18rem; }
.toggle { border:0;background:transparent;cursor:pointer;padding:0 .9rem;color:rgba(10,16,36,.4);display:grid;place-items:center; }
.toggle:hover { color:#EE6A2C; }
.toggle svg { width:18px;height:18px; }
.link-mini { font-size:.8rem;color:#C9501A;font-weight:700;text-decoration:none; }
.link-mini:hover { text-decoration:underline; }
.switch { text-align:center;margin:1.45rem 0 0;font-size:.88rem;color:rgba(10,16,36,.6); }
.switch a { color:#C9501A;font-weight:800;text-decoration:none; }
.switch a:hover { text-decoration:underline; }
@media (max-width:480px) { .otp-input { letter-spacing:.38em;text-indent:.38em; }.student-copy small { max-width:13rem; } }
@media (prefers-reduced-motion:reduce) { .cta,.password-option,.student-card,.control,.otp-input { transition:none; } }
</style>
