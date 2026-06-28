<script setup>
import ExamRoomLayout from '@/Layouts/ExamRoomLayout.vue';
import AppLogo from '@/Components/Shared/AppLogo.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

const props = defineProps({
    attempt: { type: Object, required: true },
    exam: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    remaining_seconds: { type: Number, default: 0 },
});

const MAX_STRIKES = 3;

/* ── state ── */
const begun = ref(false);
const current = ref(0);
const remaining = ref(props.remaining_seconds);
const strikes = ref(0);
const submitting = ref(false);
const showSubmit = ref(false);
const saveState = ref('idle');          // idle | saving | saved
const warning = ref(null);
const isFullscreen = ref(false);

let timer = null;
let saveTimer = null;
let warnedAt = { 300: false, 60: false };

// answers: qid -> { selected:[], flagged:bool, visited:bool }
const answers = reactive({});
props.questions.forEach((q) => {
    answers[q.id] = {
        selected: Array.isArray(q.selected) ? [...q.selected] : [],
        flagged: !!q.flagged,
        visited: false,
    };
});

/* ── computed ── */
const q = computed(() => props.questions[current.value] ?? null);
const total = computed(() => props.questions.length);

const counts = computed(() => {
    let answered = 0, marked = 0, notVisited = 0, notAnswered = 0;
    props.questions.forEach((qq) => {
        const a = answers[qq.id];
        const has = a.selected.length > 0;
        if (a.flagged) marked++;
        if (has) answered++;
        else if (!a.visited) notVisited++;
        else notAnswered++;
    });
    return { answered, marked, notVisited, notAnswered };
});

const timeText = computed(() => {
    const s = Math.max(0, remaining.value);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (n) => String(n).padStart(2, '0');
    return h > 0 ? `${pad(h)}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
});
const timeLow = computed(() => remaining.value <= 60);

function paletteState(qq) {
    const a = answers[qq.id];
    const has = a.selected.length > 0;
    if (a.flagged && has) return 'ans-marked';
    if (a.flagged) return 'marked';
    if (has) return 'answered';
    if (!a.visited) return 'not-visited';
    return 'not-answered';
}

/* ── lifecycle ── */
onMounted(() => {
    document.addEventListener('contextmenu', prevent);
    document.addEventListener('copy', prevent);
    document.addEventListener('cut', prevent);
    document.addEventListener('paste', prevent);
    document.addEventListener('fullscreenchange', onFsChange);
});
onBeforeUnmount(() => stopEverything());

function stopEverything() {
    clearInterval(timer);
    clearTimeout(saveTimer);
    document.removeEventListener('contextmenu', prevent);
    document.removeEventListener('copy', prevent);
    document.removeEventListener('cut', prevent);
    document.removeEventListener('paste', prevent);
    document.removeEventListener('visibilitychange', onVisibility);
    document.removeEventListener('fullscreenchange', onFsChange);
    window.removeEventListener('beforeunload', onBeforeUnload);
}

const prevent = (e) => { e.preventDefault(); };
const onBeforeUnload = (e) => { e.preventDefault(); e.returnValue = ''; };

function onFsChange() { isFullscreen.value = !!document.fullscreenElement; }

async function begin() {
    begun.value = true;
    answers[q.value.id].visited = true;
    try { await document.documentElement.requestFullscreen(); } catch (_) { /* ignore */ }
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('beforeunload', onBeforeUnload);
    timer = setInterval(tick, 1000);
}

function tick() {
    remaining.value--;
    if (!warnedAt[300] && remaining.value <= 300 && remaining.value > 60) { warnedAt[300] = true; flash('5 minutes remaining'); }
    if (!warnedAt[60] && remaining.value <= 60) { warnedAt[60] = true; flash('1 minute remaining!'); }
    if (remaining.value <= 0) doSubmit('timeout');
}

function onVisibility() {
    if (document.hidden && begun.value && !submitting.value) {
        strikes.value++;
        if (strikes.value >= MAX_STRIKES) {
            doSubmit('strikes');
        } else {
            warning.value = `Warning ${strikes.value}/${MAX_STRIKES}: leaving the exam tab is not allowed. The test auto-submits after ${MAX_STRIKES} strikes.`;
        }
    }
}

function flash(msg) { warning.value = msg; setTimeout(() => { if (warning.value === msg) warning.value = null; }, 4000); }

/* ── answering ── */
function pick(key) {
    const a = answers[q.value.id];
    if (q.value.question_type === 'multiple') {
        const i = a.selected.indexOf(key);
        if (i === -1) a.selected.push(key); else a.selected.splice(i, 1);
    } else {
        a.selected = [key];
    }
    queueSave(q.value.id);
}
function clearResponse() { answers[q.value.id].selected = []; queueSave(q.value.id); }
function toggleFlag() { answers[q.value.id].flagged = !answers[q.value.id].flagged; queueSave(q.value.id); }

function queueSave(qid) {
    saveState.value = 'saving';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => save(qid), 500);
}
async function save(qid) {
    const a = answers[qid];
    try {
        await window.axios.post(route('student.exam-room.answer', props.attempt.id), {
            question_id: qid,
            selected_options: a.selected,
            is_flagged: a.flagged,
        });
        saveState.value = 'saved';
    } catch (_) {
        saveState.value = 'idle';
    }
}

/* ── navigation ── */
function goTo(i) { if (i >= 0 && i < total.value) { current.value = i; answers[q.value.id].visited = true; } }
function next() { goTo(current.value + 1); }
function prev() { goTo(current.value - 1); }
function saveAndNext() { save(q.value.id); next(); }
function markReviewNext() { answers[q.value.id].flagged = true; save(q.value.id); next(); }

/* ── submit ── */
function confirmSubmit() { showSubmit.value = true; }
function doSubmit(reason = 'manual') {
    if (submitting.value) return;
    submitting.value = true;
    clearInterval(timer);
    window.removeEventListener('beforeunload', onBeforeUnload);
    router.post(route('student.exam-room.submit', props.attempt.id), { reason }, {
        onFinish: () => { if (document.fullscreenElement) document.exitFullscreen().catch(() => {}); },
    });
}
</script>

<template>
    <Head title="Exam in progress" />

    <ExamRoomLayout>
        <!-- ── instructions / start gate ── -->
        <div v-if="!begun" class="gate">
            <div class="gate-card">
                <span class="gate-eyebrow">Test Portal</span>
                <h1>{{ exam.name }}</h1>
                <ul class="gate-facts">
                    <li><strong>{{ total }}</strong><span>Questions</span></li>
                    <li><strong>{{ exam.duration_minutes }}m</strong><span>Duration</span></li>
                    <li><strong>{{ Math.floor(remaining / 60) }}m</strong><span>Time left</span></li>
                </ul>
                <ul class="gate-rules">
                    <li>You have <strong>one attempt</strong>. The timer is already running.</li>
                    <li>Stay in full-screen. Leaving the tab <strong>{{ MAX_STRIKES }} times auto-submits</strong> your test.</li>
                    <li v-if="exam.negative_marking_enabled">This exam has <strong>negative marking</strong> for wrong answers.</li>
                    <li>Your answers save automatically. The test submits on its own when time runs out.</li>
                </ul>
                <button class="begin" @click="begin">Enter full-screen &amp; start →</button>
            </div>
        </div>

        <!-- ── exam room ── -->
        <template v-else>
            <!-- header -->
            <header class="bar">
                <div class="bar-l">
                    <AppLogo :size="54" variant="dark" />
                    <h2>{{ exam.name }}</h2>
                </div>
                <div class="bar-r">
                    <span class="save" :class="saveState">{{ saveState === 'saving' ? 'Saving…' : (saveState === 'saved' ? 'Saved' : '') }}</span>
                    <div class="clock" :class="{ low: timeLow }">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        {{ timeText }}
                    </div>
                </div>
            </header>

            <div class="body">
                <!-- question -->
                <main class="q-area" v-if="q">
                    <div class="q-head">
                        <span class="q-no">Question {{ current + 1 }} <i>/ {{ total }}</i></span>
                        <span class="q-type">{{ q.question_type === 'multiple' ? 'Multiple correct' : 'Single correct' }}</span>
                        <button class="flag" :class="{ on: answers[q.id].flagged }" @click="toggleFlag">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 22V4h13l-2 4 2 4H4"/></svg>
                            {{ answers[q.id].flagged ? 'Marked' : 'Mark for review' }}
                        </button>
                    </div>

                    <p class="q-text">{{ q.question_text }}</p>
                    <img v-if="q.question_image_url" :src="q.question_image_url" alt="" class="q-img" />

                    <div class="options">
                        <button
                            v-for="opt in q.options"
                            :key="opt.key"
                            class="opt"
                            :class="{ on: answers[q.id].selected.includes(opt.key) }"
                            @click="pick(opt.key)"
                        >
                            <span class="opt-key">{{ opt.key.toUpperCase() }}</span>
                            <span class="opt-text">{{ opt.text }}</span>
                            <span class="opt-tick" :class="q.question_type">
                                <svg v-if="answers[q.id].selected.includes(opt.key)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                        </button>
                    </div>

                    <div class="q-actions">
                        <button class="ghost" @click="clearResponse">Clear response</button>
                        <div class="spacer"></div>
                        <button class="ghost" :disabled="current === 0" @click="prev">Previous</button>
                        <button class="ghost amber" @click="markReviewNext">Mark &amp; Next</button>
                        <button class="solid" @click="saveAndNext">Save &amp; Next</button>
                    </div>
                </main>

                <!-- palette -->
                <aside class="palette">
                    <div class="pal-summary">
                        <span class="leg answered"><i></i>{{ counts.answered }} Answered</span>
                        <span class="leg not-answered"><i></i>{{ counts.notAnswered }} Not answered</span>
                        <span class="leg marked"><i></i>{{ counts.marked }} Marked</span>
                        <span class="leg not-visited"><i></i>{{ counts.notVisited }} Not visited</span>
                    </div>

                    <div class="pal-grid">
                        <button
                            v-for="(qq, i) in questions"
                            :key="qq.id"
                            class="pal-cell"
                            :class="[paletteState(qq), { current: i === current }]"
                            @click="goTo(i)"
                        >{{ i + 1 }}</button>
                    </div>

                    <button class="submit-btn" @click="confirmSubmit">Submit Test</button>
                </aside>
            </div>
        </template>

        <!-- warning toast -->
        <Transition name="warn">
            <div v-if="warning" class="warn-toast" @click="warning = null">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                {{ warning }}
            </div>
        </Transition>

        <!-- submit confirm modal -->
        <Transition name="modal">
            <div v-if="showSubmit" class="overlay" @click.self="showSubmit = false">
                <div class="modal">
                    <h3>Submit your test?</h3>
                    <p>Once submitted you cannot change your answers. Results will be declared by the admin.</p>
                    <div class="modal-stats">
                        <div><strong>{{ counts.answered }}</strong><span>Answered</span></div>
                        <div><strong>{{ counts.notAnswered + counts.notVisited }}</strong><span>Unanswered</span></div>
                        <div><strong>{{ counts.marked }}</strong><span>Marked</span></div>
                    </div>
                    <div class="modal-actions">
                        <button class="ghost" @click="showSubmit = false">Keep working</button>
                        <button class="solid" :disabled="submitting" @click="doSubmit('manual')">{{ submitting ? 'Submitting…' : 'Submit now' }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </ExamRoomLayout>
</template>

<style scoped>
/* ── start gate ── */
.gate { flex: 1; display: grid; place-items: center; padding: 1.5rem; overflow: auto; }
.gate-card { background: #fff; border: 1px solid #E7D9BE; border-radius: 22px; padding: 2rem; max-width: 480px; width: 100%; box-shadow: 0 30px 70px -28px rgba(10,16,36,.4); }
.gate-eyebrow { font-family: "Space Grotesk", monospace; font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; color: #EE6A2C; font-weight: 600; }
.gate-card h1 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.6rem; color: #0A1024; margin: .5rem 0 1.2rem; }
.gate-facts { list-style: none; display: flex; gap: 1rem; padding: 0; margin: 0 0 1.3rem; }
.gate-facts li { flex: 1; background: #F3E9D6; border-radius: 13px; padding: .8rem; text-align: center; }
.gate-facts strong { display: block; font-family: "Space Grotesk", monospace; font-size: 1.3rem; color: #0A1024; }
.gate-facts span { font-size: .74rem; color: #5B6373; }
.gate-rules { margin: 0 0 1.5rem; padding-left: 1.1rem; display: grid; gap: .5rem; }
.gate-rules li { font-size: .88rem; color: #41485a; line-height: 1.5; }
.begin { width: 100%; border: 0; cursor: pointer; font-weight: 700; font-size: 1rem; color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); padding: .9rem; border-radius: 14px; box-shadow: 0 14px 30px -12px rgba(238,106,44,.7); }
.begin:hover { transform: translateY(-2px); }

/* ── header ── */
.bar { display: flex; align-items: center; justify-content: space-between; padding: .8rem 1.3rem; background: #fff; border-bottom: 1px solid #E7D9BE; flex-shrink: 0; }
.bar-l { display: flex; align-items: center; gap: .8rem; }
.logo { font-family: "Fraunces", serif; font-weight: 700; color: #0A1024; background: linear-gradient(135deg, #F2C84B, #D6991F); padding: .25rem .5rem; border-radius: 9px; font-size: .8rem; }
.bar-l h2 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.05rem; color: #0A1024; margin: 0; }
.bar-r { display: flex; align-items: center; gap: 1rem; }
.save { font-size: .8rem; color: #9aa0ad; min-width: 52px; text-align: right; }
.save.saved { color: #168A66; }
.clock { display: flex; align-items: center; gap: .45rem; font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 1.15rem; color: #0A1024; background: #F3E9D6; padding: .4rem .8rem; border-radius: 11px; }
.clock svg { width: 17px; height: 17px; }
.clock.low { color: #fff; background: #DC2626; animation: pulse 1s infinite; }
@keyframes pulse { 50% { opacity: .7; } }

/* ── body ── */
.body { flex: 1; display: flex; min-height: 0; }
.q-area { flex: 1; padding: 1.6rem 2rem; overflow: auto; }
.q-head { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.1rem; }
.q-no { font-weight: 700; color: #0A1024; }
.q-no i { color: #9aa0ad; font-style: normal; font-weight: 500; }
.q-type { font-size: .74rem; font-weight: 600; color: #2C49A6; background: rgba(44,73,166,.1); padding: .2rem .6rem; border-radius: 999px; }
.flag { margin-left: auto; display: inline-flex; align-items: center; gap: .4rem; border: 1px solid #E7D9BE; background: #fff; color: #5B6373; font-size: .82rem; font-weight: 600; padding: .4rem .8rem; border-radius: 10px; cursor: pointer; }
.flag svg { width: 15px; height: 15px; }
.flag.on { color: #6C3FA0; border-color: #6C3FA0; background: rgba(108,63,160,.08); }

.q-text { font-size: 1.08rem; line-height: 1.6; color: #0A1024; margin: 0 0 1rem; white-space: pre-line; }
.q-img { max-width: 100%; border-radius: 12px; margin-bottom: 1rem; border: 1px solid #E7D9BE; }

.options { display: grid; gap: .7rem; max-width: 720px; }
.opt { display: flex; align-items: center; gap: .9rem; text-align: left; background: #fff; border: 1.5px solid #E7D9BE; border-radius: 13px; padding: .85rem 1rem; cursor: pointer; transition: border-color .15s, background .15s; }
.opt:hover { border-color: #F2854E; }
.opt.on { border-color: #EE6A2C; background: rgba(238,106,44,.06); }
.opt-key { width: 30px; height: 30px; flex-shrink: 0; display: grid; place-items: center; border-radius: 8px; background: #F3E9D6; font-family: "Space Grotesk", monospace; font-weight: 700; font-size: .9rem; color: #5B6373; }
.opt.on .opt-key { background: #EE6A2C; color: #fff; }
.opt-text { flex: 1; font-size: .95rem; color: #0A1024; }
.opt-tick { width: 22px; height: 22px; flex-shrink: 0; display: grid; place-items: center; border: 2px solid #D9C9A6; color: #fff; }
.opt-tick.single { border-radius: 50%; }
.opt-tick.multiple { border-radius: 6px; }
.opt.on .opt-tick { background: #EE6A2C; border-color: #EE6A2C; }
.opt-tick svg { width: 13px; height: 13px; }

.q-actions { display: flex; align-items: center; gap: .6rem; margin-top: 1.5rem; max-width: 720px; flex-wrap: wrap; }
.q-actions .spacer { flex: 1; }
.ghost { border: 1px solid #E7D9BE; background: #fff; color: #41485a; font-weight: 600; font-size: .88rem; padding: .6rem 1rem; border-radius: 11px; cursor: pointer; }
.ghost:hover:not(:disabled) { border-color: #c9bda0; }
.ghost:disabled { opacity: .5; cursor: not-allowed; }
.ghost.amber { color: #6C3FA0; border-color: #d8c8ef; }
.solid { border: 0; background: linear-gradient(135deg, #F2854E, #EE6A2C); color: #fff; font-weight: 700; font-size: .88rem; padding: .6rem 1.3rem; border-radius: 11px; cursor: pointer; box-shadow: 0 10px 22px -12px rgba(238,106,44,.8); }
.solid:hover { transform: translateY(-1px); }

/* ── palette ── */
.palette { width: 280px; flex-shrink: 0; background: #fff; border-left: 1px solid #E7D9BE; display: flex; flex-direction: column; padding: 1.1rem; }
.pal-summary { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-bottom: 1rem; }
.leg { display: flex; align-items: center; gap: .4rem; font-size: .74rem; color: #5B6373; }
.leg i { width: 11px; height: 11px; border-radius: 4px; flex-shrink: 0; }
.leg.answered i { background: #168A66; }
.leg.not-answered i { background: #DC2626; }
.leg.marked i { background: #6C3FA0; }
.leg.not-visited i { background: #e4dcc9; }

.pal-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: .5rem; overflow: auto; flex: 1; align-content: start; padding: .2rem; }
.pal-cell { aspect-ratio: 1; border: 0; border-radius: 9px; font-family: "Space Grotesk", monospace; font-weight: 600; font-size: .85rem; cursor: pointer; background: #e4dcc9; color: #5B6373; position: relative; }
.pal-cell.not-answered { background: #DC2626; color: #fff; }
.pal-cell.answered { background: #168A66; color: #fff; }
.pal-cell.marked { background: #6C3FA0; color: #fff; }
.pal-cell.ans-marked { background: #6C3FA0; color: #fff; }
.pal-cell.ans-marked::after { content: ""; position: absolute; bottom: 3px; right: 3px; width: 6px; height: 6px; border-radius: 50%; background: #34d399; }
.pal-cell.current { outline: 3px solid #EE6A2C; outline-offset: 1px; }

.submit-btn { margin-top: 1rem; border: 0; background: #0A1024; color: #fff; font-weight: 700; font-size: .92rem; padding: .8rem; border-radius: 12px; cursor: pointer; }
.submit-btn:hover { background: #131C3D; }

/* ── warning toast ── */
.warn-toast { position: fixed; top: 1rem; left: 50%; transform: translateX(-50%); z-index: 70; display: flex; align-items: center; gap: .6rem; background: #DC2626; color: #fff; font-size: .88rem; font-weight: 600; padding: .8rem 1.2rem; border-radius: 12px; box-shadow: 0 18px 40px -14px rgba(220,38,38,.6); cursor: pointer; max-width: 90vw; }
.warn-toast svg { width: 18px; height: 18px; flex-shrink: 0; }
.warn-enter-active, .warn-leave-active { transition: all .3s; }
.warn-enter-from, .warn-leave-to { opacity: 0; transform: translateX(-50%) translateY(-16px); }

/* ── submit modal ── */
.overlay { position: fixed; inset: 0; z-index: 70; background: rgba(10,16,36,.55); backdrop-filter: blur(3px); display: grid; place-items: center; padding: 1rem; }
.modal { background: #fff; border-radius: 20px; padding: 1.8rem; max-width: 420px; width: 100%; box-shadow: 0 40px 80px -28px rgba(10,16,36,.5); }
.modal h3 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.3rem; color: #0A1024; margin: 0 0 .5rem; }
.modal p { font-size: .9rem; color: #5B6373; margin: 0 0 1.2rem; line-height: 1.5; }
.modal-stats { display: flex; gap: .7rem; margin-bottom: 1.4rem; }
.modal-stats div { flex: 1; background: #F3E9D6; border-radius: 12px; padding: .7rem; text-align: center; }
.modal-stats strong { display: block; font-family: "Space Grotesk", monospace; font-size: 1.3rem; color: #0A1024; }
.modal-stats span { font-size: .72rem; color: #5B6373; }
.modal-actions { display: flex; gap: .7rem; justify-content: flex-end; }
.modal-enter-active, .modal-leave-active { transition: opacity .25s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

@media (max-width: 820px) {
    .body { flex-direction: column; }
    .palette { width: 100%; border-left: 0; border-top: 1px solid #E7D9BE; max-height: 38vh; }
}
</style>
