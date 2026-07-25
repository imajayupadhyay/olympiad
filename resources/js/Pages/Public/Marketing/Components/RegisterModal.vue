<script setup>
/**
 * The entire /marketing funnel in one sheet: details → olympiads → referral →
 * pay. Submitting posts once to `marketing.register`, which creates the account
 * and returns a Razorpay order we open in place — the visitor never navigates.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    open: { type: Boolean, default: false },
    classLevels: { type: Array, default: () => [] },
    exams: { type: Array, default: () => [] },
    referral: { type: Object, default: null },
    // Set when the visitor arrived on a /marketing?ref=CODE link.
    referredBy: { type: Object, default: null },
    // Both sides of the referral program; null when the program is switched off.
    program: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const CHECKOUT_SRC = 'https://checkout.razorpay.com/v1/checkout.js';

/* ── form state ─────────────────────────────────────────────── */
const form = ref({
    name: '',
    email: '',
    phone: '',
    class_level_id: '',
});
const picked = ref([]);
const errors = ref({});
const banner = ref(null);
const processing = ref(false);
const pendingPaymentId = ref(null);
// Server-confirmed pricing, set once registration settles every calculation.
const confirmed = ref(null);
const sheetEl = ref(null);
const firstFieldEl = ref(null);

/* ── olympiads for the chosen class ─────────────────────────── */
const classExams = computed(() => {
    if (!form.value.class_level_id) return [];
    const id = Number(form.value.class_level_id);
    return props.exams.filter((e) => e.class_level_id === null || e.class_level_id === id);
});

const selected = computed(() => classExams.value.filter((e) => picked.value.includes(e.id)));
const total = computed(() => selected.value.reduce((sum, e) => sum + e.fee_amount, 0));

/** Templates can't `delete` off a setup binding, so clearing goes through here. */
const clearError = (field) => { delete errors.value[field]; };

const toggle = (exam) => {
    const i = picked.value.indexOf(exam.id);
    i === -1 ? picked.value.push(exam.id) : picked.value.splice(i, 1);
    clearError('exam_ids');
};

// Dropping a class invalidates picks that belong to the old one.
watch(() => form.value.class_level_id, () => {
    const allowed = classExams.value.map((e) => e.id);
    picked.value = picked.value.filter((id) => allowed.includes(id));
});

/* ── live price preview ─────────────────────────────────────────
   Mirrors CouponService::discountFor for the referee welcome coupon that gets
   minted at submit. Preview only — the server re-prices everything before a
   paisa is charged. Applies only when the visitor arrived on a referral link,
   which is the sole way a referral enters the funnel (same as /register). */
const discount = computed(() => {
    const rule = props.referral;
    const t = total.value;
    if (!rule || !t || !props.referredBy) return 0;
    if (t < (rule.min_order_amount || 0)) return 0;

    let amount = rule.type === 'percentage'
        ? Math.round((t * rule.value / 100) * 100) / 100
        : rule.value;
    if (rule.type === 'percentage' && rule.max_discount) amount = Math.min(amount, rule.max_discount);

    return Math.round(Math.min(amount, t) * 100) / 100;
});
const payable = computed(() => Math.max(0, Math.round((total.value - discount.value) * 100) / 100));
// Whole rupees stay clean (₹299); a discount that lands on paise shows both (₹269.10).
const inr = (n) => {
    const value = Number(n) || 0;
    return '₹' + value.toLocaleString('en-IN', {
        minimumFractionDigits: Number.isInteger(value) ? 0 : 2,
        maximumFractionDigits: 2,
    });
};

/* ── submit ─────────────────────────────────────────────────── */
async function submit() {
    if (processing.value) return;

    errors.value = {};
    banner.value = null;

    if (!picked.value.length) {
        errors.value.exam_ids = 'Select at least one olympiad to continue.';
        return;
    }

    processing.value = true;

    try {
        const { data } = await window.axios.post(route('marketing.register'), {
            ...form.value,
            email: form.value.email.trim().toLowerCase(),
            phone: form.value.phone.trim(),
            exam_ids: [...picked.value],
        });
        handleRegisterResponse(data);
    } catch (e) {
        processing.value = false;
        if (e.response?.status === 422) {
            const bag = e.response.data.errors || {};
            errors.value = Object.fromEntries(Object.entries(bag).map(([k, v]) => [k, v[0]]));
            focusFirstError();
            return;
        }
        if (e.response?.status === 409) {
            banner.value = { type: 'info', text: e.response.data.message };
            return;
        }
        banner.value = { type: 'error', text: 'Something went wrong. Please try again in a moment.' };
    }
}

/**
 * Registration outcome. Either the cart was free (straight to the dashboard) or
 * everything is priced and confirmed — in which case we show the summary and
 * wait for the student to press Pay. The gateway is never opened from here.
 */
function handleRegisterResponse(data) {
    if (data.status === 'free') {
        router.visit(data.redirect);
        return;
    }

    if (data.status === 'ready') {
        pendingPaymentId.value = data.payment_id;
        confirmed.value = data;
        if (data.referral) share.value = data.referral;
        processing.value = false;
    }
}

/**
 * The Pay button — the only thing that reaches the gateway. Asks the server for a
 * fresh order (which re-validates the coupon and re-prices) and then opens the
 * Razorpay modal, so what's charged always matches what was just shown.
 */
async function pay() {
    if (processing.value || !pendingPaymentId.value) return;
    processing.value = true;
    banner.value = null;

    try {
        const { data } = await window.axios.post(route('marketing.payment.order', pendingPaymentId.value));

        if (data.status === 'free') {
            router.visit(data.redirect);
            return;
        }

        if (data.status === 'failed') {
            processing.value = false;
            banner.value = { type: 'error', text: data.message };
            return;
        }

        if (data.status === 'ok') {
            // A lapsed coupon may have changed the price — reflect it before charging.
            if (confirmed.value && data.payable !== undefined && data.payable !== confirmed.value.payable) {
                confirmed.value = { ...confirmed.value, ...data };
                banner.value = { type: 'info', text: 'Your total was updated. Please review it before paying.' };
                processing.value = false;
                return;
            }
            await openRazorpay(data);
        }
    } catch {
        processing.value = false;
        banner.value = { type: 'error', text: 'Could not start the payment. Please try again shortly.' };
    }
}

/* ── Razorpay (same loader the checkout screen uses) ─────────── */
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

async function openRazorpay(data) {
    try {
        await loadCheckout();
    } catch {
        processing.value = false;
        banner.value = { type: 'error', text: 'Could not load the payment gateway. Check your connection and retry.' };
        return;
    }

    const rzp = new window.Razorpay({
        key: data.key_id,
        amount: data.amount,
        currency: data.currency,
        order_id: data.order_id,
        name: 'National Olympiad Hunt',
        description: 'Olympiad exam enrolment',
        prefill: data.prefill,
        theme: { color: '#EE6A2C' },
        handler(resp) {
            router.post(route('marketing.payment.verify', data.payment_id), {
                razorpay_payment_id: resp.razorpay_payment_id,
                razorpay_order_id: resp.razorpay_order_id,
                razorpay_signature: resp.razorpay_signature,
            }, { onFinish: () => { processing.value = false; } });
        },
        modal: {
            ondismiss: () => {
                processing.value = false;
                banner.value = {
                    type: 'info',
                    text: 'Payment cancelled. Your account is ready — retry whenever you like.',
                };
            },
        },
    });

    rzp.on('payment.failed', (r) => {
        processing.value = false;
        banner.value = { type: 'error', text: r?.error?.description || 'Payment failed. Please try again.' };
    });

    rzp.open();
}

/* ── sheet chrome ───────────────────────────────────────────── */
// Once the account exists the form is spent — only the review-and-pay step remains.
const accountCreated = computed(() => pendingPaymentId.value !== null);

/* ── Refer & earn card ──────────────────────────────────────────
   Mirrors the registration wizard's `.ref-share` block. Before the account
   exists there is no personal link or progress to show, so the card states the
   reward; `shareState` arrives with the register response and upgrades it to the
   real link + live counts. */
const share = ref(null);

const progressStatLabel = computed(() => ({
    link_click: 'Opens',
    link_share: 'Shares',
    first_paid_enrollment: 'Enrolled',
}[share.value?.stats?.mode] || 'Joined'));

const progressPct = computed(() => {
    const s = share.value?.stats;
    if (!s) return 0;
    // At a threshold boundary the meter reads full rather than snapping back to 0.
    if (s.rewarded > 0 && s.toward === 0) return 100;
    return Math.min(100, Math.round((s.toward / Math.max(1, s.threshold)) * 100));
});

const copied = ref(false);
async function copyLink() {
    if (!share.value?.link) return;
    try {
        await navigator.clipboard.writeText(share.value.link);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 1800);
    } catch { /* clipboard blocked */ }
}

const shareText = computed(() => {
    const welcome = share.value?.welcome || props.program?.welcome;
    return `Join me on National Olympiad Hunt!${welcome ? ` Sign up with my link and get ${welcome} on your first olympiad` : ' Sign up with my link'}: ${share.value?.link || ''}`;
});
const waLink = computed(() => `https://wa.me/?text=${encodeURIComponent(shareText.value)}`);
const mailLink = computed(() => `mailto:?subject=${encodeURIComponent('Join National Olympiad Hunt')}&body=${encodeURIComponent(shareText.value)}`);

async function nativeShare() {
    if (!navigator.share || !share.value?.link) return;
    try {
        await navigator.share({ title: 'National Olympiad Hunt', text: shareText.value, url: share.value.link });
    } catch { /* cancelled */ }
}

function focusFirstError() {
    nextTick(() => sheetEl.value?.querySelector('.field.err input, .field.err select')?.focus());
}

const close = () => {
    if (processing.value) return;
    emit('close');
};

const onKeydown = (e) => { if (e.key === 'Escape') close(); };

watch(() => props.open, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        window.addEventListener('keydown', onKeydown);
        nextTick(() => firstFieldEl.value?.focus());
    } else {
        window.removeEventListener('keydown', onKeydown);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition name="sheet">
            <div v-if="open" class="ov" role="dialog" aria-modal="true" aria-labelledby="reg-title" @click.self="close">
                <div ref="sheetEl" class="sheet">
                    <!-- header -->
                    <header class="sheet__top">
                        <div>
                            <span class="eyebrow">Registration</span>
                            <h2 id="reg-title">Join the <em>National Olympiad Hunt</em></h2>
                        </div>
                        <button class="x" type="button" aria-label="Close" @click="close">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </header>

                    <!-- body -->
                    <div class="sheet__body">
                        <p v-if="banner" class="banner" :class="banner.type">{{ banner.text }}</p>

                        <form v-show="!accountCreated" id="reg-form" @submit.prevent="submit">
                            <!-- 1 · details -->
                            <section class="blk">
                                <h3><i>1</i> Your details</h3>
                                <div class="grid2">
                                    <label class="field" :class="{ err: errors.name }">
                                        <span>Full name</span>
                                        <input ref="firstFieldEl" v-model="form.name" type="text" placeholder="Aarav Mehta"
                                               autocomplete="name" @input="clearError('name')">
                                        <em class="msg">{{ errors.name }}</em>
                                    </label>

                                    <label class="field" :class="{ err: errors.email }">
                                        <span>Email</span>
                                        <input v-model="form.email" type="email" placeholder="aarav@example.com"
                                               autocomplete="email" @input="clearError('email')">
                                        <em class="msg">{{ errors.email }}</em>
                                    </label>

                                    <label class="field" :class="{ err: errors.phone }">
                                        <span>Mobile number</span>
                                        <input v-model="form.phone" type="tel" inputmode="numeric" maxlength="10"
                                               placeholder="9876543210" autocomplete="tel" @input="clearError('phone')">
                                        <em class="msg">{{ errors.phone }}</em>
                                    </label>

                                    <label class="field" :class="{ err: errors.class_level_id }">
                                        <span>Class</span>
                                        <select v-model="form.class_level_id" @change="clearError('class_level_id')">
                                            <option value="" disabled>Select your class</option>
                                            <option v-for="c in classLevels" :key="c.id" :value="c.id">{{ c.label }}</option>
                                        </select>
                                        <em class="msg">{{ errors.class_level_id }}</em>
                                    </label>
                                </div>
                                <p class="hint">
                                    No password needed — we email your login details the moment you register.
                                </p>
                            </section>

                            <!-- 2 · olympiads -->
                            <section class="blk">
                                <h3><i>2</i> Choose your olympiads</h3>

                                <p v-if="!form.class_level_id" class="placeholder">
                                    Pick your class above and the olympiads open to you will appear here.
                                </p>

                                <p v-else-if="!classExams.length" class="placeholder">
                                    No olympiads are open for this class right now. Try another class, or register and
                                    we'll notify you the moment one opens.
                                </p>

                                <div v-else class="picks">
                                    <button v-for="exam in classExams" :key="exam.id" type="button" class="pick"
                                            :class="{ on: picked.includes(exam.id) }" @click="toggle(exam)">
                                        <span class="pick__box" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                                                 stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        <span class="pick__main">
                                            <b>{{ exam.name }}</b>
                                            <small>
                                                {{ exam.subject?.name || 'Olympiad' }}
                                                <template v-if="exam.questions_count">· {{ exam.questions_count }} questions</template>
                                                <template v-if="exam.duration_minutes">· {{ exam.duration_minutes }} min</template>
                                            </small>
                                        </span>
                                        <span class="pick__fee num">{{ exam.is_free ? 'FREE' : inr(exam.fee_amount) }}</span>
                                    </button>
                                </div>

                                <em v-if="errors.exam_ids" class="msg block">{{ errors.exam_ids }}</em>
                            </section>

                        </form>

                        <!-- Everything is registered and priced. Nothing has been charged
                             yet — Pay is the only thing that opens the gateway. -->
                        <div v-if="accountCreated && confirmed" class="review">
                            <div class="review__head">
                                <div class="review__ic">✓</div>
                                <div>
                                    <h3>You're registered</h3>
                                    <p>Login details are on their way to <b>{{ form.email }}</b>. Review your order below, then pay to confirm your seat.</p>
                                </div>
                            </div>

                            <div class="sum">
                                <div v-for="item in confirmed.items" :key="item.id" class="sum__row">
                                    <span>{{ item.name }}</span>
                                    <b class="num">{{ inr(item.fee_amount) }}</b>
                                </div>

                                <div class="sum__row sub">
                                    <span>Subtotal</span>
                                    <b class="num">{{ inr(confirmed.gross) }}</b>
                                </div>

                                <div v-if="confirmed.discount > 0" class="sum__row disc">
                                    <span>
                                        {{ confirmed.coupon?.source === 'referral_reward' ? 'Referral reward' : 'Welcome discount' }}
                                        <em v-if="confirmed.coupon">{{ confirmed.coupon.code }}</em>
                                    </span>
                                    <b class="num">− {{ inr(confirmed.discount) }}</b>
                                </div>

                                <div class="sum__row total">
                                    <span>Amount to pay</span>
                                    <b class="num">{{ inr(confirmed.payable) }}</b>
                                </div>
                            </div>

                            <button class="btn btn-primary btn-shine review__pay" type="button" :disabled="processing" @click="pay">
                                {{ processing ? 'Opening payment…' : `Pay ${inr(confirmed.payable)}` }}
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </button>
                            <a class="review__skip" :href="route('student.dashboard')">Pay later — go to my dashboard</a>
                        </div>

                        <!-- Referral — link-only, exactly as on /register: arriving on a
                             friend's link unlocks the welcome discount. Nothing to type. -->
                        <div v-if="program" class="referral">
                            <div v-if="referredBy" class="ref-applied">
                                <span class="ref-ic">🎁</span>
                                <span>
                                    <b>{{ referredBy.name }}</b> referred you — welcome discount unlocked<template v-if="referredBy.label">,
                                    you'll get <b>{{ referredBy.label }}</b> at payment</template>.
                                </span>
                            </div>

                            <div class="ref-share">
                                <div class="rs-glow" aria-hidden="true"></div>

                                <div class="rs-top">
                                    <span class="rs-eyebrow">Refer &amp; earn</span>
                                    <span v-if="program.reward" class="rs-reward">{{ program.reward }}</span>
                                </div>

                                <h3 class="rs-title">Invite friends, <em>earn rewards</em></h3>
                                <p class="rs-sub">
                                    <template v-if="program.welcome">Each friend who joins on your link gets {{ program.welcome }} —</template>
                                    <template v-else>Each friend who joins on your link</template>
                                    and brings you closer to your reward.
                                </p>

                                <!-- Before the account exists there is no link or progress yet. -->
                                <p v-if="!share" class="rs-soon">
                                    Refer {{ program.threshold }} friend<template v-if="program.threshold !== 1">s</template>
                                    to unlock {{ program.reward || 'your reward' }}. Your personal link appears
                                    the moment you register.
                                </p>

                                <template v-else>
                                    <div class="rs-meter">
                                        <div class="rs-meter-head">
                                            <span class="rs-meter-label">Reward progress</span>
                                            <span class="rs-frac"><strong>{{ progressPct >= 100 ? share.stats.threshold : share.stats.toward }}</strong><i>/{{ share.stats.threshold }}</i></span>
                                        </div>
                                        <div class="rs-track">
                                            <div class="rs-fill" :style="{ width: progressPct + '%' }"></div>
                                            <span class="rs-medal" :class="{ lit: progressPct >= 100 }">🏅</span>
                                        </div>
                                    </div>

                                    <div class="rs-stats">
                                        <div class="rs-stat"><strong>{{ share.stats.referred }}</strong><span>Invited</span></div>
                                        <div class="rs-stat"><strong>{{ share.stats.progress }}</strong><span>{{ progressStatLabel }}</span></div>
                                        <div class="rs-stat"><strong>{{ share.stats.rewarded }}</strong><span>Rewards</span></div>
                                    </div>

                                    <div class="ref-link-row">
                                        <div class="ref-link-box"><span>{{ share.link }}</span></div>
                                        <button type="button" class="ref-copy" :class="{ done: copied }" @click="copyLink">
                                            {{ copied ? 'Copied ✓' : 'Copy' }}
                                        </button>
                                    </div>

                                    <div class="ref-share-btns">
                                        <a class="sh wa" :href="waLink" target="_blank" rel="noopener">WhatsApp</a>
                                        <a class="sh mail" :href="mailLink">Email</a>
                                        <button type="button" class="sh more" @click="nativeShare">More…</button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- account exists, payment outstanding -->
                    </div>

                    <!-- sticky totals + CTA -->
                    <footer v-if="!accountCreated" class="sheet__foot">
                        <div class="tot">
                            <small>{{ selected.length }} olympiad<span v-if="selected.length !== 1">s</span> selected</small>
                            <div class="tot__val">
                                <template v-if="discount > 0">
                                    <s class="num">{{ inr(total) }}</s>
                                    <b class="num">{{ payable === 0 ? 'FREE' : inr(payable) }}</b>
                                    <span class="save num">{{ inr(discount) }} off</span>
                                </template>
                                <b v-else class="num">{{ total === 0 ? 'FREE' : inr(total) }}</b>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-shine" type="submit" form="reg-form" :disabled="processing">
                            <template v-if="processing">Processing…</template>
                            <template v-else-if="payable === 0">Register &amp; enrol free</template>
                            <template v-else>Register &amp; pay {{ inr(payable) }}</template>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </button>
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Scoped copy of the v2 brand tokens — the sheet is teleported to <body>, so it
   sits outside the page's .noh root and needs its own palette. */
.ov{
  --ink:#0A1024; --ink-2:#131C3D;
  --paper:#FBF6EC; --paper-2:#F3E9D6; --paper-line:#E7D9BE;
  --saffron:#EE6A2C; --saffron-dk:#C9501A; --gold:#D6991F; --gold-lt:#F2C84B;
  --royal:#2C49A6; --emerald:#168A66;
  --ink-70:rgba(10,16,36,.70); --ink-55:rgba(10,16,36,.55); --ink-35:rgba(10,16,36,.35); --ink-12:rgba(10,16,36,.12);
  --display:"Fraunces",Georgia,serif; --body:"Plus Jakarta Sans",system-ui,sans-serif; --mono:"Space Grotesk",monospace;
  --shadow-lg:0 40px 80px -28px rgba(10,16,36,.55);

  position:fixed; inset:0; z-index:200; display:grid; place-items:center; padding:24px;
  background:rgba(10,16,36,.62); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px);
  font-family:var(--body); color:var(--ink);
}
.ov *{ box-sizing:border-box; }
.ov svg{ display:block; }

.sheet{
  width:100%; max-width:820px; max-height:calc(100vh - 48px);
  display:flex; flex-direction:column; overflow:hidden;
  background:var(--paper); border-radius:28px; box-shadow:var(--shadow-lg);
  border:1px solid rgba(255,255,255,.5);
}

/* header */
.sheet__top{ display:flex; align-items:flex-start; justify-content:space-between; gap:20px; padding:28px 32px 20px; border-bottom:1px solid var(--paper-line); }
.eyebrow{ font:600 11px/1 var(--body); letter-spacing:.22em; text-transform:uppercase; display:inline-flex; align-items:center; gap:10px; color:var(--saffron-dk); }
.eyebrow::before{ content:""; width:22px; height:2px; background:var(--saffron); border-radius:2px; }
.sheet__top h2{ font-family:var(--display); font-weight:600; font-size:clamp(23px,3vw,30px); line-height:1.1; letter-spacing:-.02em; margin:12px 0 0; }
.sheet__top h2 em{ font-style:italic; color:var(--saffron-dk); font-weight:500; }
.x{ width:38px; height:38px; flex:none; border-radius:50%; border:1.5px solid var(--ink-12); background:transparent; color:var(--ink); display:grid; place-items:center; cursor:pointer; transition:.22s; }
.x svg{ width:17px; height:17px; }
.x:hover{ background:var(--ink); color:var(--paper); border-color:var(--ink); }

/* body */
.sheet__body{ padding:24px 32px 30px; overflow-y:auto; flex:1; }
.blk{ margin-bottom:30px; }
.blk:last-child{ margin-bottom:0; }
.blk h3{ display:flex; align-items:center; gap:11px; font:700 15px/1 var(--body); margin-bottom:18px; }
.blk h3 i{ width:26px; height:26px; border-radius:50%; flex:none; background:var(--ink); color:var(--gold-lt); font:700 12px/26px var(--mono); font-style:normal; text-align:center; }
.blk h3 small{ font:600 11px/1 var(--body); letter-spacing:.12em; text-transform:uppercase; color:var(--ink-35); }

.grid2{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.field{ display:block; }
.field > span{ display:block; font:600 12px/1 var(--body); letter-spacing:.04em; text-transform:uppercase; color:var(--ink-70); margin-bottom:8px; }
.field input, .field select{ width:100%; font:500 15px/1 var(--body); padding:14px 16px; border-radius:12px; border:1.5px solid var(--paper-line); background:rgba(255,255,255,.65); color:var(--ink); transition:.2s; }
.field input::placeholder{ color:var(--ink-35); }
.field input:focus, .field select:focus{ outline:none; border-color:var(--saffron); background:#fff; box-shadow:0 0 0 4px rgba(238,106,44,.12); }
.field.err input, .field.err select{ border-color:var(--saffron-dk); background:rgba(201,80,26,.05); }
.msg{ display:none; font:500 12px/1.4 var(--body); font-style:normal; color:var(--saffron-dk); margin-top:6px; }
.field.err .msg{ display:block; }
.msg.block{ display:block; margin-top:12px; }
.hint{ font-size:12.5px; color:var(--ink-55); margin-top:14px; }
.placeholder{ font-size:14px; color:var(--ink-55); background:var(--paper-2); border:1px dashed var(--paper-line); border-radius:14px; padding:20px 22px; }

/* olympiad picker */
.picks{ display:flex; flex-direction:column; gap:10px; }
.pick{ display:flex; align-items:center; gap:14px; width:100%; text-align:left; cursor:pointer; padding:15px 18px; border-radius:14px; border:1.5px solid var(--paper-line); background:rgba(255,255,255,.6); transition:.2s; font-family:var(--body); color:var(--ink); }
.pick:hover{ border-color:var(--ink-35); transform:translateY(-1px); }
.pick.on{ border-color:var(--saffron); background:rgba(238,106,44,.06); box-shadow:0 0 0 3px rgba(238,106,44,.1); }
.pick__box{ width:24px; height:24px; flex:none; border-radius:8px; border:1.5px solid var(--ink-12); background:#fff; display:grid; place-items:center; color:#fff; transition:.2s; }
.pick__box svg{ width:14px; height:14px; opacity:0; transition:.2s; }
.pick.on .pick__box{ background:var(--saffron); border-color:var(--saffron); }
.pick.on .pick__box svg{ opacity:1; }
.pick__main{ flex:1; min-width:0; }
.pick__main b{ display:block; font-size:15px; font-weight:700; }
.pick__main small{ display:block; font-size:12.5px; color:var(--ink-55); margin-top:3px; }
.pick__fee{ font:700 15px/1 var(--mono); font-variant-numeric:tabular-nums; color:var(--saffron-dk); flex:none; }

/* ── Referral — mirrors the wizard's .ref-applied + .ref-share blocks ───── */
.referral{ margin-top:26px; display:grid; gap:12px; }

.ref-applied{
  display:flex; align-items:flex-start; gap:11px;
  background:rgba(22,138,102,.08); border:1px solid rgba(22,138,102,.22);
  border-radius:14px; padding:14px 16px; font-size:13.5px; line-height:1.45; color:#0f5f47;
}
.ref-applied .ref-ic{ font-size:17px; line-height:1.2; flex:none; }
.ref-applied b{ font-weight:700; }

.ref-share{
  position:relative; overflow:hidden; padding:22px 22px 20px; border-radius:20px; color:var(--paper);
  background:linear-gradient(158deg,#1B2036 0%,#131C3D 55%,#0A1024 100%);
  border:1px solid rgba(242,200,75,.24);
}
.rs-glow{ position:absolute; width:280px; height:280px; border-radius:50%; top:-150px; right:-110px; pointer-events:none;
  background:radial-gradient(circle,var(--gold-lt),transparent 66%); opacity:.26; }
.rs-top{ position:relative; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.rs-eyebrow{ font:700 10.5px/1 var(--body); letter-spacing:.2em; text-transform:uppercase; color:var(--gold-lt); }
.rs-reward{ font:700 11.5px/1 var(--body); color:#3a2a05; background:linear-gradient(135deg,var(--gold-lt),var(--gold)); padding:6px 11px; border-radius:100px; }
.rs-title{ position:relative; font-family:var(--display); font-weight:600; font-size:21px; line-height:1.15; margin:14px 0 0; }
.rs-title em{ font-style:italic; color:var(--gold-lt); font-weight:500; }
.rs-sub{ position:relative; font-size:13px; line-height:1.5; color:rgba(251,246,236,.62); margin-top:8px; }
.rs-soon{ position:relative; font-size:12.5px; line-height:1.5; color:rgba(251,246,236,.5);
  margin-top:14px; padding-top:14px; border-top:1px dashed rgba(255,255,255,.14); }

.rs-meter{ position:relative; margin-top:18px; }
.rs-meter-head{ display:flex; align-items:baseline; justify-content:space-between; margin-bottom:8px; }
.rs-meter-label{ font:600 11px/1 var(--body); letter-spacing:.12em; text-transform:uppercase; color:rgba(251,246,236,.5); }
.rs-frac{ font-family:var(--mono); font-variant-numeric:tabular-nums; font-size:13px; color:rgba(251,246,236,.72); }
.rs-frac strong{ color:var(--gold-lt); font-weight:700; font-size:15px; }
.rs-frac i{ font-style:normal; }
.rs-track{ position:relative; height:8px; border-radius:100px; background:rgba(255,255,255,.09); }
.rs-fill{ height:100%; border-radius:100px; background:linear-gradient(90deg,var(--gold),var(--gold-lt)); transition:width .8s cubic-bezier(.2,.8,.2,1); }
.rs-medal{ position:absolute; right:-6px; top:50%; transform:translateY(-50%); font-size:16px; filter:grayscale(1); opacity:.45; transition:.4s; }
.rs-medal.lit{ filter:none; opacity:1; transform:translateY(-50%) scale(1.15); }

.rs-stats{ position:relative; display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-top:18px; }
.rs-stat{ text-align:center; padding:10px 6px; border-radius:12px; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); }
.rs-stat strong{ display:block; font-family:var(--mono); font-variant-numeric:tabular-nums; font-size:19px; font-weight:700; color:var(--gold-lt); }
.rs-stat span{ font-size:10.5px; letter-spacing:.1em; text-transform:uppercase; color:rgba(251,246,236,.5); }

.ref-link-row{ position:relative; display:flex; gap:8px; margin-top:16px; }
.ref-link-box{ flex:1; min-width:0; padding:11px 13px; border-radius:11px; background:rgba(0,0,0,.28); border:1px solid rgba(255,255,255,.12); }
.ref-link-box span{ display:block; font-family:var(--mono); font-size:12px; color:rgba(251,246,236,.8); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ref-copy{ flex:none; padding:11px 17px; border-radius:11px; cursor:pointer; font:700 12.5px/1 var(--body);
  background:var(--gold-lt); color:#3a2a05; border:none; transition:.22s; }
.ref-copy:hover{ transform:translateY(-1px); }
.ref-copy.done{ background:var(--emerald); color:#fff; }

.ref-share-btns{ position:relative; display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.ref-share-btns .sh{ display:inline-flex; align-items:center; gap:6px; padding:9px 14px; border-radius:10px; cursor:pointer;
  border:1px solid rgba(251,246,236,.16); background:rgba(255,255,255,.07); color:var(--paper);
  font:600 12.5px/1 var(--body); text-decoration:none; transition:.22s; }
.ref-share-btns .sh:hover{ background:rgba(251,246,236,.18); transform:translateY(-1px); }
.ref-share-btns .sh.wa{ background:rgba(37,211,102,.16); border-color:rgba(37,211,102,.42); }

/* banner */
.banner{ font:600 13.5px/1.5 var(--body); padding:13px 16px; border-radius:12px; margin-bottom:22px; }
.banner.error{ background:rgba(220,38,38,.1); color:#991B1B; }
.banner.info{ background:rgba(44,73,166,.1); color:var(--royal); }

/* registered — review the settled order, then pay */
.review__head{ display:flex; align-items:flex-start; gap:15px; margin-bottom:22px; }
.review__ic{ width:44px; height:44px; flex:none; border-radius:50%; background:rgba(22,138,102,.12); color:var(--emerald); font-size:21px; display:grid; place-items:center; }
.review__head h3{ font-family:var(--display); font-size:23px; font-weight:600; line-height:1.2; }
.review__head p{ font-size:13.5px; line-height:1.5; color:var(--ink-70); margin-top:6px; }

.sum{ border:1px solid var(--paper-line); border-radius:16px; overflow:hidden; background:rgba(255,255,255,.5); }
.sum__row{ display:flex; align-items:center; justify-content:space-between; gap:16px; padding:13px 18px; font-size:14px; }
.sum__row + .sum__row{ border-top:1px solid var(--paper-line); }
.sum__row b{ font-family:var(--mono); font-variant-numeric:tabular-nums; font-weight:700; white-space:nowrap; }
.sum__row.sub{ color:var(--ink-55); background:rgba(10,16,36,.02); }
.sum__row.disc{ color:var(--emerald); background:rgba(22,138,102,.05); }
.sum__row.disc em{ font-style:normal; font-family:var(--mono); font-size:11px; margin-left:7px; padding:3px 7px; border-radius:100px; background:rgba(22,138,102,.14); }
.sum__row.total{ background:var(--ink); color:var(--paper); font-weight:700; }
.sum__row.total b{ font-size:19px; color:var(--gold-lt); }

.review__pay{ width:100%; margin-top:18px; }
.review__skip{ display:block; text-align:center; margin-top:14px; font:600 13px/1 var(--body); color:var(--ink-55); text-decoration:underline; }

/* footer */
.sheet__foot{ display:flex; align-items:center; justify-content:space-between; gap:20px; padding:18px 32px; border-top:1px solid var(--paper-line); background:var(--paper-2); }
.tot small{ display:block; font:600 11.5px/1 var(--body); letter-spacing:.1em; text-transform:uppercase; color:var(--ink-55); margin-bottom:7px; }
.tot__val{ display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; }
.tot__val b{ font:700 24px/1 var(--mono); font-variant-numeric:tabular-nums; }
.tot__val s{ font:500 15px/1 var(--mono); color:var(--ink-35); }
.tot__val .save{ font:700 11.5px/1 var(--body); color:var(--emerald); background:rgba(22,138,102,.12); padding:5px 9px; border-radius:100px; }

.btn{ display:inline-flex; align-items:center; justify-content:center; gap:9px; font:700 15px/1 var(--body); padding:15px 26px; border-radius:100px; cursor:pointer; border:1.5px solid transparent; transition:.28s cubic-bezier(.2,.8,.2,1); white-space:nowrap; position:relative; overflow:hidden; }
.btn svg{ width:17px; height:17px; }
.btn-primary{ background:var(--saffron); color:#fff; box-shadow:0 12px 26px -10px var(--saffron); }
.btn-primary:hover:not(:disabled){ background:var(--saffron-dk); transform:translateY(-2px); }
.btn:disabled{ opacity:.6; cursor:not-allowed; }
.btn-shine::after{ content:""; position:absolute; top:0; left:-120%; width:60%; height:100%; background:linear-gradient(120deg,transparent,rgba(255,255,255,.45),transparent); transform:skewX(-20deg); transition:left .7s; }
.btn-shine:hover::after{ left:140%; }

/* transitions */
.sheet-enter-active, .sheet-leave-active{ transition:opacity .3s ease; }
.sheet-enter-active .sheet, .sheet-leave-active .sheet{ transition:transform .38s cubic-bezier(.2,.8,.2,1); }
.sheet-enter-from, .sheet-leave-to{ opacity:0; }
.sheet-enter-from .sheet, .sheet-leave-to .sheet{ transform:translateY(26px) scale(.98); }

@media (max-width:640px){
  .ov{ padding:0; align-items:flex-end; }
  .sheet{ max-width:none; max-height:96vh; border-radius:26px 26px 0 0; }
  .sheet__top{ padding:22px 20px 16px; }
  .sheet__body{ padding:20px 20px 26px; }
  .sheet__foot{ padding:16px 20px; flex-direction:column; align-items:stretch; gap:14px; }
  .sheet__foot .btn{ width:100%; }
  .grid2{ grid-template-columns:1fr; }
  .sheet-enter-from .sheet, .sheet-leave-to .sheet{ transform:translateY(100%); }
}

@media (prefers-reduced-motion: reduce){
  .ov *, .sheet{ transition:none !important; animation:none !important; }
}
</style>
