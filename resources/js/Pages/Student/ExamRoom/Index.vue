<script setup>
import ExamRoomLayout from '@/Layouts/ExamRoomLayout.vue';
import AppLogo from '@/Components/Shared/AppLogo.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({
    attempt: { type: Object, required: true },
    exam: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    remaining_seconds: { type: Number, default: 0 },
    // Live session state from ExamSessionService (null in the admin preview): token,
    // heartbeat, server strikes, resume point and pause.
    session: { type: Object, default: null },
    // Admin simulator (Admin/Exams/Preview): same room, but nothing is sent to the student
    // endpoints. Answers stay in memory and submit emits `finish` instead of posting.
    preview: { type: Boolean, default: false },
});

const emit = defineEmits(['finish', 'exit']);

// Network features run only for a real student session.
const live = !props.preview && !!props.session;
const MAX_STRIKES = props.session?.max_strikes ?? 3;
const HEARTBEAT_MS = (props.session?.heartbeat_seconds ?? 30) * 1000;
const PENDING_KEY = `exam-pending-${props.attempt.id}`;

/* ── state ── */
const begun = ref(false);
const current = ref(0);
const remaining = ref(props.remaining_seconds);
const strikes = ref(props.session?.violation_count ?? 0);
const submitting = ref(false);
const showSubmit = ref(false);
const saveState = ref('idle');          // idle | saving | saved | offline
const warning = ref(null);
const isFullscreen = ref(false);
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
const paused = ref(props.session?.paused ?? null);   // null | { rescheduled, window_label }
const superseded = ref(false);
const submitStuck = ref(false);                       // time is up but we can't reach the server

let timer = null;
let saveTimer = null;
let beatTimer = null;
let retryTimer = null;
let lastSyncAt = Date.now();
let warnedAt = { 300: false, 60: false };
let startedAt = null;

// answers: qid -> { selected:[], flagged:bool, visited:bool }
const answers = reactive({});
props.questions.forEach((q) => {
    answers[q.id] = {
        selected: Array.isArray(q.selected) ? [...q.selected] : [],
        flagged: !!q.flagged,
        visited: false,
    };
});

/* ── offline queue: answers the server hasn't confirmed yet survive reloads ── */
const pending = reactive({});            // qid -> payload
function readPending() {
    try { return JSON.parse(localStorage.getItem(PENDING_KEY) || '{}') || {}; } catch (_) { return {}; }
}
function writePending() {
    try {
        if (Object.keys(pending).length) localStorage.setItem(PENDING_KEY, JSON.stringify(pending));
        else localStorage.removeItem(PENDING_KEY);
    } catch (_) { /* storage blocked: the in-memory queue still retries */ }
}
if (live) {
    // Picks made offline before a reload are newer than what the server sent.
    Object.entries(readPending()).forEach(([qid, payload]) => {
        if (!answers[qid] || !payload) return;
        pending[qid] = payload;
        answers[qid].selected = [...(payload.selected_options || [])];
        answers[qid].flagged = !!payload.is_flagged;
    });
}
const pendingCount = computed(() => Object.keys(pending).length);

/* ── computed ── */
const q = computed(() => props.questions[current.value] ?? null);
const total = computed(() => props.questions.length || props.exam.total_questions || 0);
const isResume = computed(() => live && !!props.session?.is_resume);

// Single-section (legacy) papers keep the original flat look.
const showSections = computed(() => props.sections.length > 1);
const sectionGroups = computed(() => {
    const groups = props.sections.map((section) => ({ ...section, items: [] }));
    const byId = new Map(groups.map((group) => [group.id, group]));
    props.questions.forEach((question, index) => {
        (byId.get(question.section_id) ?? groups[groups.length - 1])?.items.push({ question, index });
    });
    return groups.filter((group) => group.items.length);
});
const currentSection = computed(() => props.sections.find((section) => section.id === q.value?.section_id) ?? null);
const currentSectionNumber = computed(() => props.sections.indexOf(currentSection.value) + 1);

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

const fmtTime = (secs) => {
    const s = Math.max(0, secs);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (n) => String(n).padStart(2, '0');
    return h > 0 ? `${pad(h)}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
};
const timeText = computed(() => fmtTime(remaining.value));
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

/* ── resume point: where the student was, else the first unanswered question ── */
function resumeIndex() {
    const byId = props.questions.findIndex((qq) => qq.id === props.session?.current_question_id);
    if (byId !== -1) return byId;
    const firstOpen = props.questions.findIndex((qq) => !answers[qq.id].selected.length);
    return firstOpen === -1 ? 0 : firstOpen;
}
if (isResume.value && props.questions.length) {
    const at = resumeIndex();
    props.questions.forEach((qq, i) => {
        const a = answers[qq.id];
        if (i <= at || a.selected.length || a.flagged) a.visited = true;
    });
    current.value = at;
}

/* ── lifecycle ── */
onMounted(() => {
    document.addEventListener('contextmenu', prevent);
    document.addEventListener('copy', prevent);
    document.addEventListener('cut', prevent);
    document.addEventListener('paste', prevent);
    document.addEventListener('fullscreenchange', onFsChange);
    if (live) {
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
        beatTimer = setInterval(maybeHeartbeat, 5000);
        if (pendingCount.value) flushPending();
    }
});
onBeforeUnmount(() => stopEverything());

function stopEverything() {
    clearInterval(timer);
    clearInterval(beatTimer);
    clearInterval(retryTimer);
    clearTimeout(saveTimer);
    document.removeEventListener('contextmenu', prevent);
    document.removeEventListener('copy', prevent);
    document.removeEventListener('cut', prevent);
    document.removeEventListener('paste', prevent);
    document.removeEventListener('visibilitychange', onVisibility);
    document.removeEventListener('fullscreenchange', onFsChange);
    window.removeEventListener('beforeunload', onBeforeUnload);
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
}

const prevent = (e) => { e.preventDefault(); };
const onBeforeUnload = (e) => { e.preventDefault(); e.returnValue = ''; };

function onFsChange() { isFullscreen.value = !!document.fullscreenElement; }

async function begin() {
    if (!q.value) return;
    begun.value = true;
    startedAt = Date.now();
    answers[q.value.id].visited = true;
    try { await document.documentElement.requestFullscreen(); } catch (_) { /* ignore */ }
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('beforeunload', onBeforeUnload);
    timer = setInterval(tick, 1000);
}

function tick() {
    if (paused.value) return;
    remaining.value--;
    if (!warnedAt[300] && remaining.value <= 300 && remaining.value > 60) { warnedAt[300] = true; flash('5 minutes remaining'); }
    if (!warnedAt[60] && remaining.value <= 60) { warnedAt[60] = true; flash('1 minute remaining!'); }
    if (remaining.value <= 0) doSubmit('timeout');
}

async function onVisibility() {
    if (!document.hidden || !begun.value || submitting.value || paused.value || superseded.value) return;

    if (live) {
        try {
            const { data } = await window.axios.post(route('student.exam-room.violation', props.attempt.id), { session_token: props.session.token });
            strikes.value = data.violation_count;
            if (data.auto_submitted) return goSubmitted();
            warnStrike();
            return;
        } catch (e) {
            if (handleConflict(e)) return;
            // Offline: count locally so the rule still holds; the server re-checks on submit.
        }
    }

    strikes.value++;
    if (strikes.value >= MAX_STRIKES) doSubmit('strikes');
    else warnStrike();
}
function warnStrike() {
    warning.value = `Warning ${strikes.value}/${MAX_STRIKES}: leaving the exam tab is not allowed. The test auto-submits after ${MAX_STRIKES} strikes.`;
}

function flash(msg) { warning.value = msg; setTimeout(() => { if (warning.value === msg) warning.value = null; }, 4000); }

/* ── server sync ── */
function onOnline() { online.value = true; heartbeat(); }
function onOffline() { online.value = false; saveState.value = 'offline'; }
function markOnline() { online.value = true; lastSyncAt = Date.now(); if (saveState.value === 'offline') saveState.value = 'saved'; }

function maybeHeartbeat() {
    if (submitting.value || superseded.value) return;
    const every = paused.value ? 10000 : HEARTBEAT_MS;
    if (Date.now() - lastSyncAt >= every) heartbeat();
}

async function heartbeat() {
    if (!live || submitting.value || superseded.value) return;
    try {
        const { data } = await window.axios.post(route('student.exam-room.heartbeat', props.attempt.id), {
            session_token: props.session.token,
            current_question_id: q.value?.id ?? null,
        });
        markOnline();
        if (data.resumable || (paused.value && !data.paused)) return reloadRoom();
        if (data.paused && !paused.value) return enterPaused({ rescheduled: false, window_label: null });
        // The server is the clock: picks up added time and corrects drift.
        if (!paused.value && Math.abs(remaining.value - data.remaining_seconds) > 2) {
            remaining.value = data.remaining_seconds;
            if (remaining.value > 300) warnedAt = { 300: false, 60: false };
        }
        strikes.value = data.violation_count ?? strikes.value;   // admins can clear warnings
        if (pendingCount.value) flushPending();
        if (submitStuck.value) retrySubmit();
    } catch (e) {
        if (!handleConflict(e)) { online.value = false; saveState.value = 'offline'; }
    }
}

/** 409s from the server: the session ended, was paused, or moved to another tab. */
function handleConflict(e) {
    if (e?.response?.status !== 409) return false;
    const reason = e.response.data?.reason;
    if (reason === 'closed') goSubmitted();
    else if (reason === 'superseded') { superseded.value = true; clearInterval(timer); window.removeEventListener('beforeunload', onBeforeUnload); }
    else if (reason === 'paused') enterPaused({ rescheduled: false, window_label: null });
    return true;
}

function enterPaused(info) {
    paused.value = info;
    showSubmit.value = false;
    warning.value = null;
}

function reloadRoom() {
    stopEverything();
    router.visit(route('student.exam-room', props.attempt.id), { preserveState: false, replace: true });
}

function goSubmitted() {
    stopEverything();
    leaveFullscreen();
    router.visit(route('student.exam-room.submitted', props.attempt.id), { replace: true });
}

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
    if (props.preview || !live) {          // simulator: keep the indicator, skip the network
        clearTimeout(saveTimer);
        saveState.value = 'saved';
        return;
    }
    clearTimeout(saveTimer);
    // Queue first, so the pick survives a failed request or a reload.
    const payload = {
        question_id: qid,
        selected_options: [...a.selected],
        is_flagged: a.flagged,
        stamp: Date.now(),
    };
    pending[qid] = payload;
    writePending();
    await send(payload);
}

/** POST one queued answer. Returns false when the network is down (stop flushing). */
async function send(payload) {
    try {
        await window.axios.post(route('student.exam-room.answer', props.attempt.id), {
            question_id: payload.question_id,
            selected_options: payload.selected_options,
            is_flagged: payload.is_flagged,
            session_token: props.session.token,
            current_question_id: q.value?.id ?? payload.question_id,
        });
        if (pending[payload.question_id]?.stamp === payload.stamp) {
            delete pending[payload.question_id];
            writePending();
        }
        markOnline();
        saveState.value = 'saved';
        return true;
    } catch (e) {
        if (handleConflict(e)) return false;
        if (e?.response) {        // validation etc.: drop it, retrying won't help
            delete pending[payload.question_id];
            writePending();
            saveState.value = 'idle';
            return true;
        }
        online.value = false;
        saveState.value = 'offline';
        return false;
    }
}

let flushing = false;
async function flushPending() {
    if (flushing || !live) return;
    flushing = true;
    try {
        for (const payload of Object.values({ ...pending })) {
            if (!(await send(payload))) break;
        }
    } finally {
        flushing = false;
    }
}

/* ── navigation ── */
function goTo(i) { if (i >= 0 && i < props.questions.length) { current.value = i; answers[q.value.id].visited = true; } }
function next() { goTo(current.value + 1); }
function prev() { goTo(current.value - 1); }
function saveAndNext() { save(q.value.id); next(); }
function markReviewNext() { answers[q.value.id].flagged = true; save(q.value.id); next(); }

/* ── submit ── */
function confirmSubmit() { showSubmit.value = true; }
async function doSubmit(reason = 'manual') {
    if (submitting.value) return;
    submitting.value = true;
    clearInterval(timer);
    window.removeEventListener('beforeunload', onBeforeUnload);
    if (props.preview) {
        stopEverything();
        leaveFullscreen();
        emit('finish', {
            reason,
            time_taken_seconds: startedAt ? Math.round((Date.now() - startedAt) / 1000) : 0,
            answers: props.questions.map((qq) => ({ question_id: qq.id, selected_options: [...answers[qq.id].selected] })),
        });
        return;
    }
    submitReason = reason;
    await postSubmit();
}

let submitReason = 'manual';
async function postSubmit() {
    // Push unsynced answers first; if the server is unreachable, keep trying.
    await flushPending();
    if (pendingCount.value || !navigator.onLine) return stuck();

    let landed = false;
    router.post(route('student.exam-room.submit', props.attempt.id), { reason: submitReason }, {
        onSuccess: () => { landed = true; },
        onFinish: () => {
            leaveFullscreen();
            if (!landed) stuck();
        },
    });
}
function stuck() {
    submitStuck.value = true;
    clearInterval(retryTimer);
    retryTimer = setInterval(retrySubmit, 5000);
}
function retrySubmit() {
    if (!navigator.onLine) return;
    clearInterval(retryTimer);
    submitStuck.value = false;
    postSubmit();
}
function leaveFullscreen() { if (document.fullscreenElement) document.exitFullscreen().catch(() => {}); }

/* ── preview only ── */
async function exitPreview() {
    if (begun.value) {
        const leave = await confirmDialog({
            title: 'Exit the preview?',
            message: 'Your answers in this preview will be discarded. Nothing is saved either way.',
            confirmText: 'Exit preview',
            cancelText: 'Keep going',
            tone: 'warning',
        });
        if (!leave) return;
    }
    stopEverything();
    leaveFullscreen();
    emit('exit');
}
</script>

<template>
    <Head title="Exam in progress" />

    <ExamRoomLayout>
        <!-- ── opened in another tab / device ── -->
        <div v-if="superseded" class="gate">
            <div class="gate-card state-card">
                <span class="gate-eyebrow">Exam open elsewhere</span>
                <h1>This exam is open in another tab or device</h1>
                <p class="state-text">Only one window can be used at a time. Your saved answers are safe. Continue here to close the other window.</p>
                <button class="begin" @click="reloadRoom">Continue here →</button>
            </div>
        </div>

        <!-- ── paused by the exam team / rescheduled ── -->
        <div v-else-if="paused" class="gate">
            <div class="gate-card state-card">
                <span class="gate-eyebrow">{{ paused.rescheduled ? 'Exam rescheduled' : 'Exam paused' }}</span>
                <h1>{{ exam.name }}</h1>
                <template v-if="paused.rescheduled">
                    <p class="state-text">The exam team has rescheduled your exam. Your answers and remaining time are saved.</p>
                    <p v-if="paused.window_label" class="state-window">Your new window: <strong>{{ paused.window_label }}</strong></p>
                    <p class="state-text">Come back during that window and you'll continue where you left off.</p>
                    <Link :href="route('student.dashboard')" class="begin as-link">Back to dashboard</Link>
                </template>
                <template v-else>
                    <p class="state-text">The exam team has paused your exam. Your answers and remaining time are saved and the timer is stopped.</p>
                    <p class="state-text"><strong>Keep this page open.</strong> It continues automatically when they resume it.</p>
                    <p class="state-wait"><span class="pulse-dot"></span> Waiting for the exam team…</p>
                </template>
            </div>
        </div>

        <!-- ── instructions / start gate ── -->
        <div v-else-if="!begun" class="gate">
            <div class="gate-card">
                <div v-if="preview" class="preview-note">
                    <strong>Admin preview</strong>
                    This is exactly what students see. Nothing you do here is recorded: no attempt, score, rank or result.
                </div>
                <span class="gate-eyebrow">{{ isResume ? 'Welcome back' : 'Test Portal' }}</span>
                <h1>{{ exam.name }}</h1>
                <div v-if="isResume" class="resume-note">
                    <strong>Your answers are saved.</strong>
                    You've answered {{ counts.answered }} of {{ total }} questions. You'll continue at question {{ current + 1 }}.
                </div>
                <ul class="gate-facts">
                    <li v-if="isResume"><strong>{{ counts.answered }}/{{ total }}</strong><span>Answered</span></li>
                    <li v-else><strong>{{ total }}</strong><span>Questions</span></li>
                    <li><strong>{{ exam.duration_minutes }}m</strong><span>Duration</span></li>
                    <li><strong>{{ Math.floor(remaining / 60) }}m</strong><span>Time left</span></li>
                </ul>
                <ol v-if="showSections" class="gate-sections">
                    <li v-for="group in sectionGroups" :key="group.id">
                        <span>{{ group.name }}</span><strong>{{ group.items.length }} Q</strong>
                    </li>
                </ol>
                <ul class="gate-rules">
                    <li v-if="isResume">The timer kept running while you were away. If you lost time because of a technical problem, contact the exam team.</li>
                    <li v-else>You have <strong>one attempt</strong>. The timer is already running.</li>
                    <li>Stay in full-screen. Leaving the tab <strong>{{ MAX_STRIKES }} times auto-submits</strong> your test.</li>
                    <li v-if="exam.negative_marking_enabled">This exam has <strong>negative marking</strong> for wrong answers.</li>
                    <li>Your answers save automatically. The test submits on its own when time runs out.</li>
                </ul>
                <button class="begin" @click="begin">{{ isResume ? 'Resume exam →' : 'Enter full-screen & start →' }}</button>
                <button v-if="preview" type="button" class="preview-exit-link" @click="exitPreview">← Exit preview</button>
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
                    <template v-if="preview">
                        <span class="preview-pill" title="Admin preview: nothing is recorded">Preview</span>
                        <button type="button" class="preview-exit" @click="exitPreview">Exit</button>
                    </template>
                    <span class="save" :class="saveState">{{ saveState === 'saving' ? 'Saving…' : (saveState === 'saved' ? 'Saved' : (saveState === 'offline' ? 'Offline' : '')) }}</span>
                    <div class="clock" :class="{ low: timeLow }">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        {{ timeText }}
                    </div>
                </div>
            </header>

            <!-- connection problems -->
            <div v-if="submitStuck" class="net-banner stuck" role="alert">
                Time's up. Submitting your test as soon as you're back online — don't close this tab.
            </div>
            <div v-else-if="live && (!online || pendingCount)" class="net-banner" role="status">
                <strong>{{ online ? 'Syncing answers…' : "You're offline." }}</strong>
                {{ pendingCount }} answer{{ pendingCount === 1 ? '' : 's' }} kept on this device will sync when you reconnect. Don't close this tab.
            </div>

            <div class="body">
                <!-- question -->
                <main class="q-area" v-if="q">
                    <div v-if="showSections && currentSection" class="q-section">
                        <span>Section {{ currentSectionNumber }} · {{ currentSection.name }}</span>
                        <p v-if="currentSection.instructions">{{ currentSection.instructions }}</p>
                    </div>
                    <div class="q-head">
                        <span class="q-no">Question {{ current + 1 }} <i>/ {{ total }}</i></span>
                        <span class="q-type">{{ q.question_type === 'multiple' ? 'Multiple correct' : 'Single correct' }}</span>
                        <button class="flag" :class="{ on: answers[q.id].flagged }" @click="toggleFlag">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 22V4h13l-2 4 2 4H4"/></svg>
                            {{ answers[q.id].flagged ? 'Marked' : 'Mark for review' }}
                        </button>
                    </div>

                    <div class="q-text rich-content" v-html="q.question_text"></div>
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
                            <span class="opt-text rich-content" v-html="opt.text"></span>
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

                    <div v-if="showSections" class="pal-sections">
                        <section v-for="group in sectionGroups" :key="group.id">
                            <h4>{{ group.name }}</h4>
                            <div class="pal-grid">
                                <button
                                    v-for="item in group.items"
                                    :key="item.question.id"
                                    class="pal-cell"
                                    :class="[paletteState(item.question), { current: item.index === current }]"
                                    @click="goTo(item.index)"
                                >{{ item.index + 1 }}</button>
                            </div>
                        </section>
                    </div>
                    <div v-else class="pal-grid">
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
.gate-sections { list-style: none; margin: 0 0 1.2rem; padding: 0; display: grid; gap: .35rem; counter-reset: sec; }
.gate-sections li { display: flex; justify-content: space-between; align-items: center; padding: .5rem .75rem; border: 1px solid #E7D9BE; border-radius: 10px; font-size: .86rem; color: #0A1024; counter-increment: sec; }
.gate-sections li span::before { content: counter(sec) ". "; color: #9aa0ad; font-family: "Space Grotesk", monospace; }
.gate-sections strong { font-family: "Space Grotesk", monospace; color: #5B6373; font-size: .8rem; }
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
.q-section { margin-bottom: .9rem; padding-bottom: .8rem; border-bottom: 1px dashed #E7D9BE; }
.q-section span { font-family: "Space Grotesk", monospace; font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; font-weight: 600; color: #EE6A2C; }
.q-section p { margin: .3rem 0 0; font-size: .85rem; color: #5B6373; line-height: 1.5; }
.q-head { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.1rem; }
.q-no { font-weight: 700; color: #0A1024; }
.q-no i { color: #9aa0ad; font-style: normal; font-weight: 500; }
.q-type { font-size: .74rem; font-weight: 600; color: #2C49A6; background: rgba(44,73,166,.1); padding: .2rem .6rem; border-radius: 999px; }
.flag { margin-left: auto; display: inline-flex; align-items: center; gap: .4rem; border: 1px solid #E7D9BE; background: #fff; color: #5B6373; font-size: .82rem; font-weight: 600; padding: .4rem .8rem; border-radius: 10px; cursor: pointer; }
.flag svg { width: 15px; height: 15px; }
.flag.on { color: #6C3FA0; border-color: #6C3FA0; background: rgba(108,63,160,.08); }

.q-text { font-size: 1.08rem; line-height: 1.6; color: #0A1024; margin: 0 0 1rem; }
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
:deep(.rich-content p) { margin: 0 0 .35rem; }
:deep(.rich-content p:last-child) { margin-bottom: 0; }
:deep(.rich-content ul) { padding-left: 1.15rem; margin: .35rem 0; }
:deep(.rich-content li) { margin: .15rem 0; }
:deep(.rich-content img) { display: block; max-width: 100%; max-height: 240px; object-fit: contain; margin: .5rem 0; border: 1px solid #E7D9BE; border-radius: 10px; background: #FBF6EC; }
:deep(.opt-text img) { max-height: 180px; }

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
.pal-sections { flex: 1; overflow: auto; display: grid; gap: .9rem; align-content: start; }
.pal-sections .pal-grid { overflow: visible; flex: none; }
.pal-sections h4 { margin: 0 0 .35rem; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: #5B6373; font-weight: 700; }
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

/* ── session states ── */
.state-card h1 { margin-bottom: .8rem; }
.state-text { font-size: .92rem; color: #41485a; line-height: 1.55; margin: 0 0 .8rem; }
.state-window { font-size: .92rem; color: #0A1024; background: rgba(108,63,160,.08); border: 1px solid rgba(108,63,160,.25); border-radius: 12px; padding: .7rem .85rem; margin: 0 0 .8rem; }
.state-wait { display: flex; align-items: center; gap: .55rem; font-size: .86rem; font-weight: 600; color: #6C3FA0; margin: 1rem 0 0; }
.pulse-dot { width: 10px; height: 10px; border-radius: 50%; background: #6C3FA0; animation: pulse 1.2s infinite; }
.begin.as-link { display: block; text-align: center; text-decoration: none; box-sizing: border-box; margin-top: .4rem; }
.resume-note { margin: -.4rem 0 1.1rem; padding: .7rem .85rem; border-radius: 12px; background: rgba(22,138,102,.08); border: 1px solid rgba(22,138,102,.25); font-size: .86rem; line-height: 1.5; color: #41485a; }
.resume-note strong { display: block; color: #168A66; }
.net-banner { flex-shrink: 0; padding: .55rem 1.3rem; font-size: .84rem; color: #7c4a03; background: #FEF3C7; border-bottom: 1px solid #F2C84B; }
.net-banner.stuck { color: #fff; background: #DC2626; border-color: #B91C1C; font-weight: 600; }
.save.offline { color: #B45309; font-weight: 600; }
@media (prefers-reduced-motion: reduce) { .pulse-dot { animation: none; } }

/* ── admin preview only ── */
.preview-note { margin: 0 0 1.1rem; padding: .7rem .85rem; border-radius: 12px; background: rgba(214,153,31,.12); border: 1px solid rgba(214,153,31,.35); font-size: .82rem; line-height: 1.5; color: #41485a; }
.preview-note strong { display: block; color: #B45309; font-size: .74rem; letter-spacing: .08em; text-transform: uppercase; margin-bottom: .15rem; }
.preview-exit-link { display: block; margin: .8rem auto 0; border: 0; background: none; color: #5B6373; font-weight: 600; font-size: .86rem; cursor: pointer; }
.preview-exit-link:hover { color: #0A1024; text-decoration: underline; }
.preview-pill { font-family: "Space Grotesk", monospace; font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #B45309; background: rgba(214,153,31,.15); border: 1px solid rgba(214,153,31,.4); padding: .25rem .55rem; border-radius: 999px; }
.preview-exit { border: 1px solid #E7D9BE; background: #fff; color: #41485a; font-weight: 600; font-size: .8rem; padding: .4rem .75rem; border-radius: 10px; cursor: pointer; }
.preview-exit:hover { border-color: #c9bda0; }
@media (max-width: 560px) { .preview-pill { display: none; } }

@media (max-width: 820px) {
    .body { flex-direction: column; }
    .palette { width: 100%; border-left: 0; border-top: 1px solid #E7D9BE; max-height: 38vh; }
}
</style>
