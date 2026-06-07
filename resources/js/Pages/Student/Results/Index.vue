<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    results: { type: Array, default: () => [] },
});

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '';

const gradeClass = (g) => ({
    'A+': 'g-aplus', 'A': 'g-a', 'B': 'g-b', 'C': 'g-c', 'D': 'g-d', 'F': 'g-f',
}[g] ?? 'g-b');
</script>

<template>
    <Head title="My Results" />

    <StudentLayout title="Results">
        <div class="head">
            <h2 class="h2">My Results</h2>
            <p class="sub">Scores and ranks appear here once the admin declares them.</p>
        </div>

        <div v-if="results.length" class="list">
            <component
                :is="row.released ? Link : 'div'"
                v-for="row in results"
                :key="row.attempt_id"
                :href="row.released ? route('student.results.show', row.result.id) : undefined"
                class="row"
                :class="{ clickable: row.released }"
            >
                <div class="row-l">
                    <span class="subj" :style="row.subject?.color ? { background: row.subject.color + '22', color: row.subject.color } : {}">
                        {{ row.subject?.icon || '📝' }}
                    </span>
                    <div>
                        <strong>{{ row.exam?.name }}</strong>
                        <small>Submitted {{ fmt(row.submitted_at) }}</small>
                    </div>
                </div>

                <div v-if="row.released" class="row-r">
                    <div class="metric">
                        <span>Score</span>
                        <strong>{{ row.result.score }}<i>/{{ row.result.max_score }}</i></strong>
                    </div>
                    <div class="metric">
                        <span>Rank</span>
                        <strong>#{{ row.result.rank }}</strong>
                    </div>
                    <span class="grade" :class="gradeClass(row.result.grade)">{{ row.result.grade }}</span>
                    <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"/></svg>
                </div>

                <div v-else class="awaiting">
                    <span class="dot"></span> Awaiting declaration
                </div>
            </component>
        </div>

        <div v-else class="empty">
            <div class="empty-ic">🏅</div>
            <h3>No results yet</h3>
            <p>Take an exam and your results will show up here once released.</p>
            <Link :href="route('student.exams')" class="cta">Browse exams →</Link>
        </div>
    </StudentLayout>
</template>

<style scoped>
.head { margin-bottom: 1.3rem; }
.h2 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.6rem; color: #0A1024; margin: 0; }
.sub { color: #5B6373; font-size: .92rem; margin: .25rem 0 0; }

.list { display: grid; gap: .8rem; }
.row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: #fff; border: 1px solid #E7D9BE; border-radius: 16px; padding: 1rem 1.2rem; text-decoration: none; color: inherit; box-shadow: 0 2px 8px rgba(10,16,36,.04); }
.row.clickable { cursor: pointer; transition: transform .15s, box-shadow .2s, border-color .2s; }
.row.clickable:hover { transform: translateY(-2px); border-color: #EE6A2C; box-shadow: 0 16px 34px -20px rgba(10,16,36,.3); }

.row-l { display: flex; align-items: center; gap: .9rem; min-width: 0; }
.subj { width: 44px; height: 44px; flex-shrink: 0; border-radius: 12px; display: grid; place-items: center; font-size: 1.2rem; background: #F3E9D6; }
.row-l strong { display: block; color: #0A1024; font-size: .98rem; }
.row-l small { color: #9aa0ad; font-size: .8rem; }

.row-r { display: flex; align-items: center; gap: 1.4rem; }
.metric { text-align: right; line-height: 1.1; }
.metric span { font-size: .7rem; color: #9aa0ad; display: block; }
.metric strong { font-family: "Space Grotesk", monospace; font-size: 1.1rem; color: #0A1024; }
.metric i { color: #9aa0ad; font-style: normal; font-size: .8rem; }

.grade { width: 42px; height: 42px; flex-shrink: 0; border-radius: 11px; display: grid; place-items: center; font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 1rem; color: #fff; }
.g-aplus { background: linear-gradient(135deg, #1aa177, #168A66); }
.g-a { background: linear-gradient(135deg, #34d399, #168A66); }
.g-b { background: linear-gradient(135deg, #3a5bd0, #2C49A6); }
.g-c { background: linear-gradient(135deg, #F2C84B, #D6991F); }
.g-d { background: linear-gradient(135deg, #F2854E, #EE6A2C); }
.g-f { background: linear-gradient(135deg, #ef4444, #DC2626); }
.chev { width: 18px; height: 18px; color: #c9bda0; }

.awaiting { display: inline-flex; align-items: center; gap: .5rem; font-size: .85rem; font-weight: 600; color: #9a7b2e; background: rgba(214,153,31,.12); padding: .4rem .8rem; border-radius: 999px; }
.awaiting .dot { width: 7px; height: 7px; border-radius: 50%; background: #D6991F; }

.empty { text-align: center; padding: 3.5rem 1rem; color: #5B6373; }
.empty-ic { font-size: 2.4rem; margin-bottom: .5rem; }
.empty h3 { font-family: "Fraunces", serif; color: #0A1024; margin: 0 0 .3rem; }
.empty p { font-size: .9rem; margin: 0 0 1rem; }
.cta { text-decoration: none; font-weight: 700; color: #C9501A; }
.cta:hover { text-decoration: underline; }
</style>
