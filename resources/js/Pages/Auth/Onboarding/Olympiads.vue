<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Stepper from './Components/Stepper.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    exams: { type: Array, default: () => [] },
    selected: { type: Array, default: () => [] },
});

const picked = ref([...props.selected]);
const toggle = (id) => {
    const i = picked.value.indexOf(id);
    if (i === -1) picked.value.push(id); else picked.value.splice(i, 1);
};

const total = computed(() =>
    props.exams.filter((e) => picked.value.includes(e.id)).reduce((s, e) => s + e.fee_amount, 0)
);

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

        <div class="foot">
            <button type="button" class="skip" @click="skip">Skip for now</button>
            <div class="foot-r">
                <span v-if="picked.length" class="total">
                    {{ picked.length }} selected · <strong>{{ total === 0 ? 'FREE' : '₹' + total.toLocaleString('en-IN') }}</strong>
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

.foot { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1.4rem; flex-wrap: wrap; }
.skip { border: 0; background: transparent; color: #9aa0ad; cursor: pointer; font-size: .9rem; font-weight: 600; }
.skip:hover { color: #5B6373; text-decoration: underline; }
.foot-r { display: flex; align-items: center; gap: 1rem; }
.total { font-size: .88rem; color: #5B6373; }
.total strong { font-family: "Space Grotesk", monospace; color: #0A1024; }
.cta { border: 0; cursor: pointer; font-weight: 700; font-size: .95rem; color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); padding: .8rem 1.5rem; border-radius: 13px; box-shadow: 0 12px 26px -12px rgba(238,106,44,.8); transition: transform .15s; }
.cta:hover:not(:disabled) { transform: translateY(-2px); }
.cta:disabled { opacity: .5; cursor: not-allowed; }
</style>
