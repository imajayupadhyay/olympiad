<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    exam: { type: Object, required: true },
});

const fmt = (d) =>
    d ? new Date(d).toLocaleString('en-IN', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : 'TBA';

const availMeta = computed(() => ({
    upcoming: { label: 'Upcoming', cls: 'av-upcoming' },
    live:     { label: 'Live now', cls: 'av-live' },
    closed:   { label: 'Closed',   cls: 'av-closed' },
}[props.exam.availability] ?? { label: '', cls: '' }));

const enrollForm = useForm({ exam_ids: [props.exam.id] });
const enroll = () => enrollForm.post(route('student.exams.enroll'), { preserveScroll: true });

const startForm = useForm({});
const startExam = () => startForm.post(route('student.exams.start', props.exam.id));

const sections = computed(() => [
    { title: 'Description', body: props.exam.description },
    { title: 'Syllabus', body: props.exam.syllabus },
    { title: 'Eligibility', body: props.exam.eligibility },
    { title: 'Instructions', body: props.exam.instructions },
].filter((s) => s.body));
</script>

<template>
    <Head :title="exam.name" />

    <StudentLayout title="Exam Details">
        <Link :href="route('student.exams')" class="back">← Back to exams</Link>

        <div class="layout">
            <!-- main -->
            <div class="main">
                <div class="hero">
                    <div class="hero-top">
                        <span class="subj" :style="exam.subject?.color ? { background: exam.subject.color + '22', color: exam.subject.color } : {}">
                            <span v-if="exam.subject?.icon">{{ exam.subject.icon }}</span> {{ exam.subject?.name ?? 'General' }}
                        </span>
                        <span class="avail" :class="availMeta.cls"><span class="dot"></span>{{ availMeta.label }}</span>
                    </div>
                    <h1 class="title">{{ exam.name }}</h1>
                    <p class="code">{{ exam.exam_code }} · {{ exam.class_level?.label }}</p>
                </div>

                <div v-for="s in sections" :key="s.title" class="block">
                    <h3>{{ s.title }}</h3>
                    <p>{{ s.body }}</p>
                </div>

                <div v-if="!sections.length" class="block muted">
                    <p>No additional details provided for this exam.</p>
                </div>
            </div>

            <!-- sidebar CTA -->
            <aside class="side">
                <div class="cta-card">
                    <div class="fee-row">
                        <span class="fee" :class="{ free: exam.is_free }">{{ exam.is_free ? 'FREE' : '₹' + exam.fee_amount.toLocaleString('en-IN') }}</span>
                        <span v-if="!exam.is_free" class="fee-cap">per student</span>
                    </div>

                    <ul class="facts">
                        <li><span>Questions</span><strong>{{ exam.questions_count }}</strong></li>
                        <li><span>Duration</span><strong>{{ exam.duration_minutes }} min</strong></li>
                        <li><span>Marks / Q</span><strong>{{ exam.marks_per_question }}</strong></li>
                        <li v-if="exam.negative_marking_enabled"><span>Negative</span><strong>-{{ exam.negative_marks_per_question }}</strong></li>
                        <li><span>Opens</span><strong>{{ fmt(exam.starts_at) }}</strong></li>
                        <li><span>Closes</span><strong>{{ fmt(exam.ends_at) }}</strong></li>
                    </ul>

                    <!-- CTA states -->
                    <template v-if="exam.is_enrolled">
                        <div class="enrolled-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6 9 17l-5-5"/></svg>
                            <div>
                                <strong>You're enrolled</strong>
                                <small v-if="exam.availability === 'live'">The exam is live — start whenever you're ready.</small>
                                <small v-else-if="exam.availability === 'upcoming'">Starts {{ fmt(exam.starts_at) }}.</small>
                                <small v-else>The exam window has closed.</small>
                            </div>
                        </div>
                        <button v-if="exam.availability === 'live'" class="cta start" :disabled="startForm.processing" @click="startExam">
                            {{ startForm.processing ? 'Starting…' : 'Start exam →' }}
                        </button>
                    </template>

                    <button v-else-if="exam.availability === 'closed'" class="cta closed" disabled>Window closed</button>

                    <button v-else class="cta" :disabled="enrollForm.processing" @click="enroll">
                        {{ enrollForm.processing ? 'Please wait…' : (exam.is_free ? 'Enroll for free' : 'Enroll — ₹' + exam.fee_amount.toLocaleString('en-IN')) }}
                    </button>

                    <p v-if="!exam.is_enrolled && !exam.is_free" class="pay-note">You'll confirm payment on the next step (demo checkout).</p>
                </div>
            </aside>
        </div>
    </StudentLayout>
</template>

<style scoped>
.back { font-size: .85rem; font-weight: 600; color: #C9501A; text-decoration: none; }
.back:hover { text-decoration: underline; }

.layout { display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem; margin-top: 1rem; align-items: start; }
@media (max-width: 900px) { .layout { grid-template-columns: 1fr; } }

.hero { background: linear-gradient(135deg, #1B2748, #0A1024); color: #FBF6EC; border-radius: 20px; padding: 1.6rem; margin-bottom: 1.2rem; }
.hero-top { display: flex; align-items: center; gap: .6rem; margin-bottom: .9rem; flex-wrap: wrap; }
.subj { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 700; padding: .25rem .65rem; border-radius: 999px; background: rgba(251,246,236,.12); color: #F2C84B; }
.avail { display: inline-flex; align-items: center; gap: .35rem; font-size: .74rem; font-weight: 700; padding: .2rem .6rem; border-radius: 999px; }
.avail .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.av-upcoming { background: rgba(120,150,240,.2); color: #b9c8ff; }
.av-live { background: rgba(52,211,153,.18); color: #6ee7b7; }
.av-closed { background: rgba(251,246,236,.12); color: rgba(251,246,236,.6); }
.title { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.8rem; line-height: 1.15; margin: 0; }
.code { font-family: "Space Grotesk", monospace; font-size: .82rem; color: rgba(251,246,236,.55); margin: .5rem 0 0; }

.block { background: #fff; border: 1px solid #E7D9BE; border-radius: 16px; padding: 1.2rem 1.3rem; margin-bottom: 1rem; }
.block h3 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.1rem; color: #0A1024; margin: 0 0 .5rem; }
.block p { color: #41485a; font-size: .92rem; line-height: 1.65; margin: 0; white-space: pre-line; }
.block.muted p { color: #9aa0ad; }

.side { position: sticky; top: 84px; }
.cta-card { background: #fff; border: 1px solid #E7D9BE; border-radius: 18px; padding: 1.3rem; box-shadow: 0 14px 36px -20px rgba(10,16,36,.3); }
.fee-row { display: flex; align-items: baseline; gap: .5rem; margin-bottom: 1rem; }
.fee { font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 1.9rem; color: #0A1024; }
.fee.free { color: #168A66; }
.fee-cap { font-size: .8rem; color: #9aa0ad; }

.facts { list-style: none; margin: 0 0 1.2rem; padding: 0; display: grid; gap: .6rem; }
.facts li { display: flex; align-items: center; justify-content: space-between; font-size: .85rem; padding-bottom: .55rem; border-bottom: 1px dashed #EADFC8; }
.facts li:last-child { border-bottom: 0; padding-bottom: 0; }
.facts span { color: #5B6373; }
.facts strong { color: #0A1024; font-family: "Space Grotesk", monospace; font-weight: 600; text-align: right; }

.cta { width: 100%; border: 0; cursor: pointer; font-weight: 700; font-size: .98rem; color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); padding: .85rem 1rem; border-radius: 13px; box-shadow: 0 12px 26px -12px rgba(238,106,44,.8); transition: transform .15s; }
.cta:hover:not(:disabled) { transform: translateY(-2px); }
.cta:disabled { cursor: not-allowed; }
.cta.closed { background: #e9e3d4; color: #9aa0ad; box-shadow: none; }

.cta.start { margin-top: .8rem; background: linear-gradient(135deg, #1aa177, #168A66); box-shadow: 0 12px 26px -12px rgba(22,138,102,.7); }
.enrolled-box { display: flex; gap: .7rem; align-items: flex-start; background: rgba(22,138,102,.08); border: 1px solid rgba(22,138,102,.25); border-radius: 13px; padding: .9rem; }
.enrolled-box svg { width: 20px; height: 20px; color: #168A66; flex-shrink: 0; margin-top: .1rem; }
.enrolled-box strong { display: block; color: #126b50; font-size: .92rem; }
.enrolled-box small { color: #2f7a60; font-size: .8rem; }

.pay-note { font-size: .76rem; color: #9aa0ad; text-align: center; margin: .7rem 0 0; }
</style>
