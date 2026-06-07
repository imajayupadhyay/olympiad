<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    exam: { type: Object, required: true },
});

const fmt = (d) => d ? new Date(d).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : 'TBA';

const availMeta = computed(() => ({
    upcoming: { label: 'Upcoming', cls: 'av-upcoming' },
    live:     { label: 'Live now', cls: 'av-live' },
    closed:   { label: 'Closed',   cls: 'av-closed' },
}[props.exam.availability] ?? { label: '', cls: '' }));

const start = () => router.post(route('student.exams.start', props.exam.id));
</script>

<template>
    <div class="card" :class="{ live: exam.availability === 'live' }">
        <div class="top">
            <span class="subj" :style="exam.subject?.color ? { background: exam.subject.color + '22', color: exam.subject.color } : {}">
                <span v-if="exam.subject?.icon">{{ exam.subject.icon }}</span> {{ exam.subject?.name ?? 'General' }}
            </span>
            <span class="avail" :class="availMeta.cls"><span class="dot"></span>{{ availMeta.label }}</span>
        </div>

        <h3 class="name">{{ exam.name }}</h3>

        <div class="meta">
            <span>🎓 {{ exam.class_level?.label ?? '—' }}</span>
            <span>📝 {{ exam.questions_count }} Qs</span>
            <span>⏱ {{ exam.duration_minutes }}m</span>
        </div>
        <div class="win">{{ fmt(exam.starts_at) }} → {{ fmt(exam.ends_at) }}</div>

        <!-- state CTA -->
        <div class="cta-row">
            <button v-if="exam.action === 'start'" class="cta start" @click="start">Start exam →</button>
            <Link v-else-if="exam.action === 'continue'" :href="route('student.exam-room', exam.attempt_id)" class="cta continue">Continue →</Link>
            <Link v-else-if="exam.action === 'result'" :href="route('student.results.show', exam.result_id)" class="cta result">View result →</Link>
            <span v-else-if="exam.action === 'awaiting'" class="pill awaiting"><span class="d"></span> Awaiting result</span>
            <span v-else-if="exam.action === 'upcoming'" class="pill upcoming">Starts {{ fmt(exam.starts_at) }}</span>
            <span v-else class="pill closed">Window closed</span>
        </div>
    </div>
</template>

<style scoped>
.card { position: relative; background: #fff; border: 1.5px solid #E7D9BE; border-radius: 18px; padding: 1.2rem; box-shadow: 0 2px 8px rgba(10,16,36,.04); display: flex; flex-direction: column; gap: .65rem; transition: transform .18s, box-shadow .2s; }
.card:hover { transform: translateY(-3px); box-shadow: 0 22px 44px -24px rgba(10,16,36,.3); }
.card.live { border-color: rgba(22,138,102,.4); box-shadow: 0 0 0 3px rgba(22,138,102,.1), 0 2px 8px rgba(10,16,36,.04); }

.top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
.subj { display: inline-flex; align-items: center; gap: .35rem; font-size: .76rem; font-weight: 700; padding: .25rem .6rem; border-radius: 999px; background: #F3E9D6; color: #5B6373; }
.avail { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px; }
.avail .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.av-upcoming { background: rgba(44,73,166,.12); color: #2C49A6; }
.av-live { background: rgba(22,138,102,.14); color: #168A66; }
.av-closed { background: rgba(91,99,115,.14); color: #5B6373; }

.name { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.12rem; color: #0A1024; margin: 0; line-height: 1.25; }
.meta { display: flex; flex-wrap: wrap; gap: .8rem; font-size: .82rem; color: #5B6373; }
.win { font-family: "Space Grotesk", monospace; font-size: .8rem; color: #5B6373; }

.cta-row { margin-top: .4rem; padding-top: .8rem; border-top: 1px solid #F0E6D2; }
.cta { display: inline-flex; align-items: center; justify-content: center; width: 100%; border: 0; cursor: pointer; text-decoration: none; font-weight: 700; font-size: .9rem; padding: .65rem 1rem; border-radius: 12px; color: #fff; transition: transform .15s; }
.cta:hover { transform: translateY(-1px); }
.cta.start { background: linear-gradient(135deg, #1aa177, #168A66); box-shadow: 0 12px 26px -12px rgba(22,138,102,.7); }
.cta.continue { background: linear-gradient(135deg, #F2854E, #EE6A2C); box-shadow: 0 12px 26px -12px rgba(238,106,44,.8); }
.cta.result { background: #131C3D; }

.pill { display: inline-flex; align-items: center; gap: .45rem; width: 100%; justify-content: center; font-size: .85rem; font-weight: 700; padding: .6rem 1rem; border-radius: 12px; }
.pill .d { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.pill.awaiting { color: #9a7b2e; background: rgba(214,153,31,.14); }
.pill.upcoming { color: #2C49A6; background: rgba(44,73,166,.1); }
.pill.closed { color: #5B6373; background: #F3E9D6; }
</style>
