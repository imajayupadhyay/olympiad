<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    result: { type: Object, required: true },
    attempt: { type: Object, required: true },
    review: { type: Array, default: () => [] },
    certificate_id: { type: Number, default: null },
});

const timeText = computed(() => {
    const s = props.attempt.time_taken_seconds ?? 0;
    return `${Math.floor(s / 60)}m ${String(s % 60).padStart(2, '0')}s`;
});

const optClass = (opt, q) => {
    const isCorrect = q.correct.includes(opt.key);
    const isPicked = q.selected.includes(opt.key);
    if (isCorrect) return 'correct';
    if (isPicked && !isCorrect) return 'wrong';
    return '';
};
</script>

<template>
    <Head :title="result.exam?.name + ' — Result'" />

    <StudentLayout title="Result">
        <Link :href="route('student.results')" class="back">← All results</Link>

        <!-- scorecard -->
        <section class="scorecard">
            <div class="sc-blob a"></div>
            <div class="sc-blob b"></div>
            <div class="sc-inner">
                <div class="sc-left">
                    <p class="sc-exam">{{ result.subject?.name }} · {{ result.exam?.name }}</p>
                    <div class="sc-score">
                        <strong>{{ result.score }}</strong>
                        <span>/ {{ result.max_score }}</span>
                    </div>
                    <p class="sc-pct">{{ result.percentage }}% · Percentile {{ result.percentile ?? '—' }}</p>
                </div>
                <div class="sc-right">
                    <div class="grade-badge">{{ result.grade }}</div>
                    <div class="rank">
                        <span>National Rank</span>
                        <strong>#{{ result.rank }}</strong>
                    </div>
                </div>
            </div>

            <div class="sc-stats">
                <div><strong class="ok">{{ attempt.total_correct }}</strong><span>Correct</span></div>
                <div><strong class="bad">{{ attempt.total_wrong }}</strong><span>Wrong</span></div>
                <div><strong class="muted">{{ attempt.total_skipped }}</strong><span>Skipped</span></div>
                <div><strong>{{ timeText }}</strong><span>Time</span></div>
            </div>

            <a v-if="certificate_id" :href="route('student.certificates.download', certificate_id)" class="cert-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="9" r="6"/><path d="M9 14.5 7 22l5-3 5 3-2-7.5"/></svg>
                Download Certificate
            </a>
        </section>

        <!-- answer review -->
        <h3 class="rev-title">Answer Review</h3>
        <div class="review">
            <div v-for="(q, i) in review" :key="q.id" class="q-card" :class="q.is_correct ? 'ok' : (q.selected.length ? 'bad' : 'skip')">
                <div class="q-top">
                    <span class="q-no">Q{{ i + 1 }}</span>
                    <span class="q-verdict" :class="q.is_correct ? 'v-ok' : (q.selected.length ? 'v-bad' : 'v-skip')">
                        {{ q.is_correct ? 'Correct' : (q.selected.length ? 'Wrong' : 'Skipped') }}
                        <i>{{ q.marks_awarded >= 0 ? '+' : '' }}{{ q.marks_awarded }}</i>
                    </span>
                </div>
                <p class="q-text">{{ q.question_text }}</p>
                <img v-if="q.question_image_url" :src="q.question_image_url" alt="" class="q-img" />

                <div class="opts">
                    <div v-for="opt in q.options" :key="opt.key" class="opt" :class="optClass(opt, q)">
                        <span class="ok-key">{{ opt.key.toUpperCase() }}</span>
                        <span class="ok-text">{{ opt.text }}</span>
                        <span v-if="q.correct.includes(opt.key)" class="tag tag-correct">Correct</span>
                        <span v-else-if="q.selected.includes(opt.key)" class="tag tag-yours">Your answer</span>
                    </div>
                </div>

                <p v-if="q.explanation" class="explain"><strong>Explanation:</strong> {{ q.explanation }}</p>
            </div>
        </div>
    </StudentLayout>
</template>

<style scoped>
.back { font-size: .85rem; font-weight: 600; color: #C9501A; text-decoration: none; }
.back:hover { text-decoration: underline; }

.scorecard { position: relative; overflow: hidden; background: linear-gradient(135deg, #1B2748, #0A1024); color: #FBF6EC; border-radius: 22px; padding: 1.8rem; margin: 1rem 0 1.6rem; }
.sc-blob { position: absolute; border-radius: 50%; filter: blur(55px); }
.sc-blob.a { width: 320px; height: 320px; top: -130px; right: -60px; background: radial-gradient(circle, rgba(214,153,31,.45), transparent 70%); }
.sc-blob.b { width: 240px; height: 240px; bottom: -120px; left: 10%; background: radial-gradient(circle, rgba(44,73,166,.4), transparent 70%); }

.sc-inner { position: relative; z-index: 1; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.sc-exam { font-size: .85rem; color: rgba(251,246,236,.6); margin: 0 0 .5rem; }
.sc-score { display: flex; align-items: baseline; gap: .4rem; }
.sc-score strong { font-family: "Space Grotesk", monospace; font-size: 3rem; font-weight: 700; color: #F2C84B; line-height: 1; }
.sc-score span { font-size: 1.1rem; color: rgba(251,246,236,.5); }
.sc-pct { font-family: "Space Grotesk", monospace; font-size: .9rem; color: rgba(251,246,236,.7); margin: .5rem 0 0; }

.sc-right { display: flex; align-items: center; gap: 1.2rem; }
.grade-badge { width: 64px; height: 64px; border-radius: 16px; display: grid; place-items: center; font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 1.6rem; color: #0A1024; background: linear-gradient(135deg, #F2C84B, #D6991F); box-shadow: 0 14px 30px -12px rgba(214,153,31,.7); }
.rank { text-align: right; }
.rank span { display: block; font-size: .72rem; color: rgba(251,246,236,.5); }
.rank strong { font-family: "Space Grotesk", monospace; font-size: 1.6rem; color: #FBF6EC; }

.sc-stats { position: relative; z-index: 1; display: flex; gap: .8rem; margin-top: 1.6rem; }
.sc-stats div { flex: 1; background: rgba(251,246,236,.08); border-radius: 13px; padding: .7rem; text-align: center; }
.sc-stats strong { display: block; font-family: "Space Grotesk", monospace; font-size: 1.3rem; }
.sc-stats .ok { color: #6ee7b7; }
.sc-stats .bad { color: #fca5a5; }
.sc-stats .muted { color: rgba(251,246,236,.6); }
.sc-stats span { font-size: .72rem; color: rgba(251,246,236,.5); }

.cert-btn { position: relative; z-index: 1; display: inline-flex; align-items: center; gap: .5rem; margin-top: 1.3rem; text-decoration: none; font-weight: 700; font-size: .9rem; color: #0A1024; background: linear-gradient(135deg, #F2C84B, #D6991F); padding: .65rem 1.2rem; border-radius: 12px; box-shadow: 0 14px 30px -12px rgba(214,153,31,.7); transition: transform .15s; }
.cert-btn:hover { transform: translateY(-2px); }
.cert-btn svg { width: 17px; height: 17px; }

.rev-title { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.25rem; color: #0A1024; margin: 0 0 1rem; }
.review { display: grid; gap: 1rem; }
.q-card { background: #fff; border: 1px solid #E7D9BE; border-left-width: 4px; border-radius: 14px; padding: 1.2rem; }
.q-card.ok { border-left-color: #168A66; }
.q-card.bad { border-left-color: #DC2626; }
.q-card.skip { border-left-color: #c9bda0; }

.q-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .6rem; }
.q-no { font-family: "Space Grotesk", monospace; font-weight: 700; color: #5B6373; }
.q-verdict { display: inline-flex; align-items: center; gap: .5rem; font-size: .78rem; font-weight: 700; padding: .25rem .65rem; border-radius: 999px; }
.q-verdict i { font-style: normal; font-family: "Space Grotesk", monospace; opacity: .8; }
.v-ok { color: #168A66; background: rgba(22,138,102,.12); }
.v-bad { color: #DC2626; background: rgba(220,38,38,.1); }
.v-skip { color: #5B6373; background: #F3E9D6; }

.q-text { font-size: .96rem; color: #0A1024; line-height: 1.55; margin: 0 0 .8rem; white-space: pre-line; }
.q-img { max-width: 100%; border-radius: 10px; margin-bottom: .8rem; border: 1px solid #E7D9BE; }

.opts { display: grid; gap: .5rem; }
.opt { display: flex; align-items: center; gap: .7rem; border: 1.5px solid #EADFC8; border-radius: 11px; padding: .6rem .8rem; }
.opt.correct { border-color: #168A66; background: rgba(22,138,102,.07); }
.opt.wrong { border-color: #DC2626; background: rgba(220,38,38,.06); }
.ok-key { width: 26px; height: 26px; flex-shrink: 0; display: grid; place-items: center; border-radius: 7px; background: #F3E9D6; font-family: "Space Grotesk", monospace; font-weight: 700; font-size: .82rem; color: #5B6373; }
.opt.correct .ok-key { background: #168A66; color: #fff; }
.opt.wrong .ok-key { background: #DC2626; color: #fff; }
.ok-text { flex: 1; font-size: .9rem; color: #0A1024; }
.tag { font-size: .68rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px; }
.tag-correct { color: #168A66; background: rgba(22,138,102,.14); }
.tag-yours { color: #DC2626; background: rgba(220,38,38,.12); }

.explain { font-size: .85rem; color: #41485a; background: #FBF6EC; border-radius: 10px; padding: .7rem .85rem; margin: .8rem 0 0; line-height: 1.55; }
.explain strong { color: #0A1024; }
</style>
