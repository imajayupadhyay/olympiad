<script setup>
// Result of an admin preview run. Computed in memory by ExamPreviewController@score;
// none of it is stored. Visual language mirrors Student/Results/Show.vue.
import { computed, ref } from 'vue';

const props = defineProps({
    exam: { type: Object, required: true },
    result: { type: Object, required: true },
});
defineEmits(['retake', 'exit']);

const summary = computed(() => props.result.summary);
const sections = computed(() => props.result.sections || []);
const showSections = computed(() => sections.value.length > 1);

const filter = ref('all');
const filters = computed(() => [
    { key: 'all', label: 'All', count: props.result.review.length },
    { key: 'correct', label: 'Correct', count: summary.value.correct },
    { key: 'wrong', label: 'Wrong', count: summary.value.wrong },
    { key: 'skipped', label: 'Skipped', count: summary.value.skipped },
]);
const rows = computed(() => props.result.review
    .map((q, index) => ({ ...q, number: index + 1 }))
    .filter((q) => filter.value === 'all' || q.state === filter.value));
const sectionStarts = (i) => showSections.value && rows.value[i]?.section && rows.value[i].section !== rows.value[i - 1]?.section;

const endedBy = computed(() => ({
    timeout: 'Time ran out, so the test auto-submitted.',
    strikes: 'You left the exam tab 3 times, so the test auto-submitted (students get the same rule).',
}[props.result.reason] || null));

const fmt = (value) => Number(value || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
const signed = (value) => `${value > 0 ? '+' : ''}${fmt(value)}`;
const timeText = computed(() => {
    const s = summary.value.time_taken_seconds ?? 0;
    return `${Math.floor(s / 60)}m ${String(s % 60).padStart(2, '0')}s`;
});
const verdict = { correct: 'Correct', wrong: 'Wrong', skipped: 'Skipped' };
const optClass = (opt, q) => {
    if (q.correct.includes(opt.key)) return 'correct';
    if (q.selected.includes(opt.key)) return 'wrong';
    return '';
};
</script>

<template>
    <div class="page">
        <header class="top">
            <div class="top-l">
                <span class="pill">Preview</span>
                <div class="top-title">
                    <strong>{{ exam.name }}</strong>
                    <span>Nothing from this run was recorded</span>
                </div>
            </div>
            <div class="top-r">
                <button type="button" class="ghost" @click="$emit('exit')">Exit preview</button>
                <button type="button" class="solid" @click="$emit('retake')">Take again</button>
            </div>
        </header>

        <main class="wrap">
            <div class="note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                <p>
                    Students don't see a score here. After submitting they get a <strong>“Test submitted”</strong> screen,
                    and results appear only after you process and release them. This scorecard is for checking the paper.
                    <template v-if="endedBy"><br>{{ endedBy }}</template>
                </p>
            </div>

            <!-- scorecard -->
            <section class="scorecard">
                <div class="sc-blob a"></div>
                <div class="sc-blob b"></div>
                <div class="sc-inner">
                    <div>
                        <p class="sc-exam">{{ exam.exam_code }} · Preview score</p>
                        <div class="sc-score"><strong>{{ fmt(summary.score) }}</strong><span>/ {{ fmt(summary.max_score) }}</span></div>
                        <p class="sc-pct">{{ fmt(summary.percentage) }}% · {{ summary.attempted }} of {{ summary.total }} attempted</p>
                    </div>
                </div>
                <div class="sc-stats">
                    <div><strong class="ok">{{ summary.correct }}</strong><span>Correct</span></div>
                    <div><strong class="bad">{{ summary.wrong }}</strong><span>Wrong</span></div>
                    <div><strong class="muted">{{ summary.skipped }}</strong><span>Skipped</span></div>
                    <div><strong>{{ timeText }}</strong><span>Time</span></div>
                </div>
            </section>

            <!-- section-wise performance -->
            <section v-if="showSections" class="sections">
                <h3 class="rev-title">Section-wise Performance</h3>
                <div class="sec-grid">
                    <article v-for="(section, i) in sections" :key="section.section_id ?? i" class="sec-card">
                        <div class="sec-head">
                            <span class="sec-no">{{ i + 1 }}</span>
                            <h4>{{ section.name }}</h4>
                        </div>
                        <div class="sec-score"><strong>{{ fmt(section.score) }}</strong><span>/ {{ fmt(section.max_score) }}</span></div>
                        <div class="sec-bar" role="img" :aria-label="`${section.percentage}% in ${section.name}`">
                            <i :style="{ width: Math.max(0, Math.min(100, section.percentage)) + '%' }"></i>
                        </div>
                        <p class="sec-meta">
                            <span class="ok">{{ section.correct }} correct</span> ·
                            <span class="bad">{{ section.wrong }} wrong</span> ·
                            <span>{{ section.skipped }} skipped</span>
                            <b>{{ fmt(section.percentage) }}%</b>
                        </p>
                    </article>
                </div>
            </section>

            <!-- answer review -->
            <div class="rev-head">
                <h3 class="rev-title">Answer Review</h3>
                <div class="chips" role="tablist" aria-label="Filter questions">
                    <button v-for="item in filters" :key="item.key" type="button" role="tab"
                            :aria-selected="filter === item.key" class="chip" :class="{ on: filter === item.key }"
                            @click="filter = item.key">
                        {{ item.label }} <i>{{ item.count }}</i>
                    </button>
                </div>
            </div>

            <p v-if="!rows.length" class="empty">No {{ filter }} questions in this run.</p>
            <div class="review">
                <template v-for="(q, i) in rows" :key="q.id">
                    <h4 v-if="sectionStarts(i)" class="rev-section">{{ q.section }}</h4>
                    <div class="q-card" :class="q.state">
                        <div class="q-top">
                            <span class="q-no">Q{{ q.number }} <em>{{ q.question_type === 'multiple' ? 'Multiple correct' : 'Single correct' }}</em></span>
                            <span class="q-verdict" :class="`v-${q.state}`">
                                {{ verdict[q.state] }}
                                <i>{{ signed(q.marks_awarded) }} / {{ fmt(q.max_marks) }}</i>
                            </span>
                        </div>
                        <div class="q-text rich-content" v-html="q.question_text"></div>
                        <img v-if="q.question_image_url" :src="q.question_image_url" alt="" class="q-img" />

                        <div class="opts">
                            <div v-for="opt in q.options" :key="opt.key" class="opt" :class="optClass(opt, q)">
                                <span class="ok-key">{{ opt.key.toUpperCase() }}</span>
                                <span class="ok-text rich-content" v-html="opt.text"></span>
                                <span v-if="q.correct.includes(opt.key) && q.selected.includes(opt.key)" class="tag tag-correct">Correct · your answer</span>
                                <span v-else-if="q.correct.includes(opt.key)" class="tag tag-correct">Correct</span>
                                <span v-else-if="q.selected.includes(opt.key)" class="tag tag-yours">Your answer</span>
                            </div>
                        </div>

                        <div v-if="q.explanation" class="explain">
                            <strong>Explanation:</strong>
                            <div class="rich-content" v-html="q.explanation"></div>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>
</template>

<style scoped>
.page { position: fixed; inset: 0; overflow: auto; background: radial-gradient(80% 60% at 100% 0%, rgba(238,106,44,.05), transparent 55%), #FBF6EC; font-family: "Plus Jakarta Sans", system-ui, sans-serif; color: #0A1024; }

.top { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: .8rem; flex-wrap: wrap; padding: .8rem 1.3rem; background: rgba(255,255,255,.92); backdrop-filter: blur(14px) saturate(150%); border-bottom: 1px solid #E7D9BE; }
.top-l { display: flex; align-items: center; gap: .7rem; min-width: 0; }
.top-title { min-width: 0; }
.top-title strong { display: block; font-family: "Fraunces", serif; font-weight: 600; font-size: 1.05rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.top-title span { font-size: .76rem; color: #5B6373; }
.top-r { display: flex; gap: .5rem; }
.pill { flex-shrink: 0; font-family: "Space Grotesk", monospace; font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #B45309; background: rgba(214,153,31,.15); border: 1px solid rgba(214,153,31,.4); padding: .25rem .55rem; border-radius: 999px; }
.ghost, .solid { font-weight: 700; font-size: .86rem; padding: .6rem 1rem; border-radius: 11px; cursor: pointer; white-space: nowrap; }
.ghost { border: 1px solid #E7D9BE; background: #fff; color: #41485a; }
.ghost:hover { border-color: #c9bda0; }
.solid { border: 0; color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); box-shadow: 0 10px 22px -12px rgba(238,106,44,.8); }

.wrap { max-width: 960px; margin: 0 auto; padding: 1.4rem 1rem 3rem; }

.note { display: flex; gap: .6rem; background: rgba(44,73,166,.07); border: 1px solid rgba(44,73,166,.2); border-radius: 13px; padding: .85rem .95rem; margin-bottom: 1.2rem; }
.note svg { width: 20px; height: 20px; color: #2C49A6; flex-shrink: 0; }
.note p { margin: 0; font-size: .84rem; color: #41485a; line-height: 1.55; }

.scorecard { position: relative; overflow: hidden; background: linear-gradient(135deg, #1B2748, #0A1024); color: #FBF6EC; border-radius: 22px; padding: 1.6rem; margin-bottom: 1.6rem; }
.sc-blob { position: absolute; border-radius: 50%; filter: blur(55px); }
.sc-blob.a { width: 320px; height: 320px; top: -130px; right: -60px; background: radial-gradient(circle, rgba(214,153,31,.45), transparent 70%); }
.sc-blob.b { width: 240px; height: 240px; bottom: -120px; left: 10%; background: radial-gradient(circle, rgba(44,73,166,.4), transparent 70%); }
.sc-inner { position: relative; z-index: 1; }
.sc-exam { font-size: .85rem; color: rgba(251,246,236,.6); margin: 0 0 .5rem; font-family: "Space Grotesk", monospace; }
.sc-score { display: flex; align-items: baseline; gap: .4rem; }
.sc-score strong { font-family: "Space Grotesk", monospace; font-size: 3rem; font-weight: 700; color: #F2C84B; line-height: 1; font-variant-numeric: tabular-nums; }
.sc-score span { font-size: 1.1rem; color: rgba(251,246,236,.5); }
.sc-pct { font-family: "Space Grotesk", monospace; font-size: .9rem; color: rgba(251,246,236,.7); margin: .5rem 0 0; }
.sc-stats { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(4, 1fr); gap: .7rem; margin-top: 1.4rem; }
.sc-stats div { background: rgba(251,246,236,.08); border-radius: 13px; padding: .7rem; text-align: center; }
.sc-stats strong { display: block; font-family: "Space Grotesk", monospace; font-size: 1.3rem; }
.sc-stats .ok { color: #6ee7b7; }
.sc-stats .bad { color: #fca5a5; }
.sc-stats .muted { color: rgba(251,246,236,.6); }
.sc-stats span { font-size: .72rem; color: rgba(251,246,236,.5); }

.sections { margin-bottom: 1.8rem; }
.sec-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .9rem; }
.sec-card { background: #fff; border: 1px solid #E7D9BE; border-radius: 16px; padding: 1.1rem; }
.sec-head { display: flex; align-items: center; gap: .55rem; margin-bottom: .7rem; }
.sec-head h4 { flex: 1; min-width: 0; margin: 0; font-size: .95rem; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sec-no { width: 24px; height: 24px; flex-shrink: 0; display: grid; place-items: center; border-radius: 7px; background: #F3E9D6; font-family: "Space Grotesk", monospace; font-weight: 700; font-size: .78rem; color: #5B6373; }
.sec-score { display: flex; align-items: baseline; gap: .3rem; }
.sec-score strong { font-family: "Space Grotesk", monospace; font-size: 1.7rem; line-height: 1; }
.sec-score span { font-size: .85rem; color: #9aa0ad; }
.sec-bar { height: 8px; border-radius: 999px; background: #F3E9D6; overflow: hidden; margin: .75rem 0 .6rem; }
.sec-bar i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #F2854E, #EE6A2C); }
.sec-meta { margin: 0; display: flex; flex-wrap: wrap; gap: .3rem; align-items: baseline; font-size: .76rem; color: #5B6373; }
.sec-meta .ok { color: #168A66; font-weight: 600; }
.sec-meta .bad { color: #DC2626; font-weight: 600; }
.sec-meta b { margin-left: auto; font-family: "Space Grotesk", monospace; color: #0A1024; }

.rev-head { display: flex; align-items: center; justify-content: space-between; gap: .8rem; flex-wrap: wrap; margin-bottom: 1rem; }
.rev-title { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.25rem; margin: 0 0 1rem; }
.rev-head .rev-title { margin: 0; }
.chips { display: flex; gap: .4rem; flex-wrap: wrap; }
.chip { border: 1px solid #E7D9BE; background: #fff; color: #41485a; font-size: .8rem; font-weight: 600; padding: .35rem .7rem; border-radius: 999px; cursor: pointer; }
.chip i { font-style: normal; font-family: "Space Grotesk", monospace; color: #9aa0ad; margin-left: .2rem; }
.chip.on { background: #0A1024; border-color: #0A1024; color: #fff; }
.chip.on i { color: rgba(255,255,255,.7); }
.empty { color: #5B6373; font-size: .9rem; }

.rev-section { margin: .6rem 0 -.2rem; font-family: "Space Grotesk", monospace; font-size: .76rem; letter-spacing: .1em; text-transform: uppercase; font-weight: 600; color: #EE6A2C; }
.review { display: grid; gap: 1rem; }
.q-card { background: #fff; border: 1px solid #E7D9BE; border-left-width: 4px; border-radius: 14px; padding: 1.2rem; min-width: 0; }
.q-card.correct { border-left-color: #168A66; }
.q-card.wrong { border-left-color: #DC2626; }
.q-card.skipped { border-left-color: #c9bda0; }
.q-top { display: flex; align-items: center; justify-content: space-between; gap: .6rem; flex-wrap: wrap; margin-bottom: .6rem; }
.q-no { font-family: "Space Grotesk", monospace; font-weight: 700; color: #5B6373; }
.q-no em { font-style: normal; font-family: "Plus Jakarta Sans", sans-serif; font-size: .72rem; font-weight: 600; color: #2C49A6; background: rgba(44,73,166,.1); padding: .15rem .5rem; border-radius: 999px; margin-left: .35rem; }
.q-verdict { display: inline-flex; align-items: center; gap: .5rem; font-size: .78rem; font-weight: 700; padding: .25rem .65rem; border-radius: 999px; }
.q-verdict i { font-style: normal; font-family: "Space Grotesk", monospace; opacity: .8; }
.v-correct { color: #168A66; background: rgba(22,138,102,.12); }
.v-wrong { color: #DC2626; background: rgba(220,38,38,.1); }
.v-skipped { color: #5B6373; background: #F3E9D6; }
.q-text { font-size: .96rem; line-height: 1.55; margin: 0 0 .8rem; overflow-wrap: anywhere; }
.q-img { max-width: 100%; border-radius: 10px; margin-bottom: .8rem; border: 1px solid #E7D9BE; }
.opts { display: grid; gap: .5rem; }
.opt { display: flex; align-items: center; gap: .7rem; border: 1.5px solid #EADFC8; border-radius: 11px; padding: .6rem .8rem; flex-wrap: wrap; }
.opt.correct { border-color: #168A66; background: rgba(22,138,102,.07); }
.opt.wrong { border-color: #DC2626; background: rgba(220,38,38,.06); }
.ok-key { width: 26px; height: 26px; flex-shrink: 0; display: grid; place-items: center; border-radius: 7px; background: #F3E9D6; font-family: "Space Grotesk", monospace; font-weight: 700; font-size: .82rem; color: #5B6373; }
.opt.correct .ok-key { background: #168A66; color: #fff; }
.opt.wrong .ok-key { background: #DC2626; color: #fff; }
.ok-text { flex: 1; min-width: 0; font-size: .9rem; overflow-wrap: anywhere; }
.tag { font-size: .68rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px; }
.tag-correct { color: #168A66; background: rgba(22,138,102,.14); }
.tag-yours { color: #DC2626; background: rgba(220,38,38,.12); }
.explain { font-size: .85rem; color: #41485a; background: #FBF6EC; border-radius: 10px; padding: .7rem .85rem; margin: .8rem 0 0; line-height: 1.55; }
.explain strong { color: #0A1024; }
:deep(.rich-content p) { margin: 0 0 .35rem; }
:deep(.rich-content p:last-child) { margin-bottom: 0; }
:deep(.rich-content ul) { padding-left: 1.15rem; margin: .35rem 0; }
:deep(.rich-content img) { display: block; max-width: 100%; max-height: 220px; object-fit: contain; margin: .45rem 0; border: 1px solid #E7D9BE; border-radius: 9px; background: #FBF6EC; }
:deep(.ok-text img) { max-height: 160px; }

@media (max-width: 560px) {
    .top { padding: .7rem 1rem; }
    .top-r { width: 100%; }
    .top-r button { flex: 1; }
    .sc-stats { grid-template-columns: repeat(2, 1fr); }
    .sc-score strong { font-size: 2.4rem; }
}
</style>
