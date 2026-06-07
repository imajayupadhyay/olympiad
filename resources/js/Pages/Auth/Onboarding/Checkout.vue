<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Stepper from './Components/Stepper.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    summary: { type: Object, required: true },   // { items:[], total, currency }
});

const isFree = computed(() => (props.summary.total ?? 0) === 0);

const form = useForm({});
const complete = () => form.post(route('register.complete'));
</script>

<template>
    <Head title="Checkout" />

    <AuthLayout
        eyebrow="Step 3 of 3"
        heading="Review & confirm"
        subheading="Confirm your olympiad enrolments to finish setting up your account."
    >
        <Stepper :current="3" />

        <div class="summary">
            <div v-for="item in summary.items" :key="item.id" class="line">
                <span class="l-name">{{ item.name }}</span>
                <span class="l-fee" :class="{ free: item.is_free }">{{ item.is_free ? 'FREE' : '₹' + item.fee_amount.toLocaleString('en-IN') }}</span>
            </div>

            <div class="total-row">
                <span>Total</span>
                <strong :class="{ free: isFree }">{{ isFree ? 'FREE' : '₹' + summary.total.toLocaleString('en-IN') }}</strong>
            </div>
        </div>

        <div v-if="!isFree" class="pay-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
            This uses a <strong>demo payment gateway</strong> (no real charge). You'll confirm
            payment on the next screen; free exams are enrolled instantly.
        </div>

        <button class="cta" :disabled="form.processing" @click="complete">
            {{ form.processing ? 'Finishing…' : (isFree ? 'Complete enrollment →' : 'Continue →') }}
        </button>

        <Link :href="route('register.olympiads')" class="back">← Change selection</Link>
    </AuthLayout>
</template>

<style scoped>
.summary { background: #fff; border: 1.5px solid #E7D9BE; border-radius: 16px; padding: 1.1rem 1.2rem; }
.line { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-bottom: 1px dashed #EADFC8; }
.line:first-child { padding-top: 0; }
.l-name { font-size: .92rem; color: #0A1024; }
.l-fee { font-family: "Space Grotesk", monospace; font-weight: 600; color: #0A1024; }
.l-fee.free { color: #168A66; }
.total-row { display: flex; align-items: center; justify-content: space-between; padding-top: .8rem; margin-top: .3rem; }
.total-row span { font-weight: 600; color: #5B6373; }
.total-row strong { font-family: "Space Grotesk", monospace; font-size: 1.4rem; color: #0A1024; }
.total-row strong.free { color: #168A66; }

.pay-note { display: flex; gap: .6rem; background: rgba(44,73,166,.07); border: 1px solid rgba(44,73,166,.2); border-radius: 13px; padding: .9rem; font-size: .84rem; color: #41485a; line-height: 1.5; margin-top: 1rem; }
.pay-note svg { width: 19px; height: 19px; color: #2C49A6; flex-shrink: 0; }

.cta { width: 100%; border: 0; cursor: pointer; font-weight: 700; font-size: 1rem; color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); padding: .9rem; border-radius: 14px; box-shadow: 0 14px 30px -12px rgba(238,106,44,.7); margin-top: 1.2rem; transition: transform .15s; }
.cta:hover:not(:disabled) { transform: translateY(-2px); }
.cta:disabled { opacity: .6; cursor: progress; }

.back { display: block; text-align: center; margin-top: 1rem; font-size: .85rem; font-weight: 600; color: #C9501A; text-decoration: none; }
.back:hover { text-decoration: underline; }
</style>
