<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/*
 * Floating support chat — bottom-right launcher + concierge panel.
 * Talks to the JSON widget endpoints so the whole conversation happens
 * without leaving the current page. Mirrors Pages/Student/Support exactly.
 */

const page = usePage();
const STORE_KEY = 'noh_support_widget';

const open = ref(false);
const view = ref('list');              // 'list' | 'thread' | 'new'
const tickets = ref([]);
const categories = ref({});
const priorities = ref({});
const activeTicket = ref(null);

const listLoading = ref(false);
const threadLoading = ref(false);
const sending = ref(false);
const errorMsg = ref('');

// Seed the badge from the shared prop so it's correct before the first fetch.
const unread = ref(page.props.support_unread ?? 0);
watch(() => page.props.support_unread, (v) => { if (!open.value) unread.value = v ?? 0; });

const reply = ref('');
const newForm = ref({ subject: '', category: 'other', priority: 'medium', body: '' });
const formErrors = ref({});

const threadScroll = ref(null);
const replyBox = ref(null);
const panelRef = ref(null);

/* ── formatting ── */
const statusMeta = {
    open:     { label: 'Open',           cls: 's-open' },
    pending:  { label: 'Awaiting reply',  cls: 's-pending' },
    answered: { label: 'Answered',        cls: 's-answered' },
    resolved: { label: 'Resolved',        cls: 's-resolved' },
    closed:   { label: 'Closed',          cls: 's-closed' },
};
const priorityCls = (p) => ({ low: 'p-low', medium: 'p-medium', high: 'p-high' }[p] ?? 'p-medium');

const relTime = (v) => {
    if (!v) return '';
    const diff = Math.floor((Date.now() - new Date(v).getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return new Date(v).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
};
const msgTime = (v) => v ? new Date(v).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '';

const openTickets = computed(() => tickets.value.filter(t => !['resolved', 'closed'].includes(t.status)));
const doneTickets = computed(() => tickets.value.filter(t => ['resolved', 'closed'].includes(t.status)));

/* ── data ── */
async function loadList(silent = false) {
    if (!silent) listLoading.value = true;
    errorMsg.value = '';
    try {
        const { data } = await window.axios.get(route('student.support.widget'));
        tickets.value = data.tickets ?? [];
        categories.value = data.categories ?? {};
        priorities.value = data.priorities ?? {};
        unread.value = data.unread ?? 0;
    } catch (e) {
        if (!silent) errorMsg.value = 'Could not load your conversations. Please try again.';
    } finally {
        listLoading.value = false;
    }
}

async function openThread(id, silent = false) {
    if (!silent) { threadLoading.value = true; view.value = 'thread'; }
    errorMsg.value = '';
    try {
        const { data } = await window.axios.get(route('student.support.widget.show', id));
        activeTicket.value = data.ticket;
        // Opening clears the server-side unread; reflect that locally.
        const row = tickets.value.find(t => t.id === id);
        if (row) row.student_unread = 0;
        unread.value = openTickets.value.filter(t => t.student_unread > 0).length;
        await scrollThreadToEnd();
    } catch (e) {
        if (!silent) errorMsg.value = 'Could not open this conversation.';
    } finally {
        threadLoading.value = false;
    }
}

async function sendReply() {
    const body = reply.value.trim();
    if (!body || sending.value || !activeTicket.value) return;
    sending.value = true;
    errorMsg.value = '';
    try {
        const { data } = await window.axios.post(
            route('student.support.widget.reply', activeTicket.value.id),
            { body },
        );
        activeTicket.value = data.ticket;
        reply.value = '';
        resizeReply();
        await scrollThreadToEnd();
        loadList(true);
    } catch (e) {
        errorMsg.value = e?.response?.status === 403
            ? 'This conversation is closed.'
            : 'Your reply could not be sent. Please try again.';
    } finally {
        sending.value = false;
    }
}

async function submitNew() {
    if (sending.value) return;
    formErrors.value = {};
    sending.value = true;
    try {
        const { data } = await window.axios.post(route('student.support.widget.store'), { ...newForm.value });
        activeTicket.value = data.ticket;
        newForm.value = { subject: '', category: 'other', priority: 'medium', body: '' };
        view.value = 'thread';
        await scrollThreadToEnd();
        loadList(true);
    } catch (e) {
        if (e?.response?.status === 422) {
            formErrors.value = e.response.data.errors ?? {};
        } else {
            errorMsg.value = 'Your ticket could not be submitted. Please try again.';
        }
    } finally {
        sending.value = false;
    }
}

/* ── navigation within the panel ── */
function toggle() {
    open.value = !open.value;
    if (open.value) {
        if (view.value === 'list') loadList();
        else if (view.value === 'thread' && activeTicket.value) openThread(activeTicket.value.id, true);
        nextTick(() => panelRef.value?.querySelector('[data-autofocus]')?.focus());
    }
}
function goList() { view.value = 'list'; activeTicket.value = null; errorMsg.value = ''; loadList(true); }
function goNew() { view.value = 'new'; formErrors.value = {}; errorMsg.value = ''; nextTick(() => panelRef.value?.querySelector('[data-autofocus]')?.focus()); }
function selectTicket(t) { openThread(t.id); }
function goToFullPage() { open.value = false; router.visit(route('student.support')); }

function onEsc(e) {
    if (e.key !== 'Escape' || !open.value) return;
    if (view.value === 'list') open.value = false;
    else goList();
}

/* ── helpers ── */
async function scrollThreadToEnd() {
    await nextTick();
    const el = threadScroll.value;
    if (el) el.scrollTop = el.scrollHeight;
}
function resizeReply() {
    const el = replyBox.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}
function onReplyKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
}

/* ── persistence across Inertia navigations (layout remounts) ── */
function persist() {
    try {
        sessionStorage.setItem(STORE_KEY, JSON.stringify({
            open: open.value, view: view.value, ticketId: activeTicket.value?.id ?? null,
        }));
    } catch (_) { /* ignore */ }
}
watch([open, view, activeTicket], persist, { deep: false });

/* ── ~12s polling for the active thread / list while open ── */
let poll = null;
onMounted(() => {
    document.addEventListener('keydown', onEsc);
    try {
        const saved = JSON.parse(sessionStorage.getItem(STORE_KEY) || '{}');
        if (saved.open) {
            open.value = true;
            if (saved.view === 'new') { view.value = 'new'; loadList(true); }
            else if (saved.view === 'thread' && saved.ticketId) { view.value = 'thread'; openThread(saved.ticketId, false); loadList(true); }
            else { view.value = 'list'; loadList(); }
        }
    } catch (_) { /* ignore */ }

    poll = setInterval(() => {
        if (!open.value) return;
        if (view.value === 'thread' && activeTicket.value) openThread(activeTicket.value.id, true);
        else if (view.value === 'list') loadList(true);
    }, 12000);
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onEsc);
    poll && clearInterval(poll);
});
</script>

<template>
    <div class="noh-support">
        <!-- ───── Panel ───── -->
        <transition name="panel">
            <section
                v-if="open"
                ref="panelRef"
                class="panel"
                role="dialog"
                aria-label="Help and support chat"
            >
                <!-- Header (deep-ink concierge bar) -->
                <header class="ph">
                    <div class="ph-blob"></div>

                    <button
                        v-if="view !== 'list'"
                        class="ph-back"
                        aria-label="Back to conversations"
                        @click="goList"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                    </button>

                    <div class="ph-mark" v-else aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 14a8 8 0 0 1 16 0"/><rect x="2" y="14" width="4" height="6" rx="1.4"/><rect x="18" y="14" width="4" height="6" rx="1.4"/><path d="M20 19a3 3 0 0 1-3 3h-2"/></svg>
                    </div>

                    <div class="ph-title">
                        <template v-if="view === 'list'">
                            <strong>Help &amp; Support</strong>
                            <span class="ph-status"><i class="live"></i> Online — replies within a few hours</span>
                        </template>
                        <template v-else-if="view === 'new'">
                            <strong>New conversation</strong>
                            <span class="ph-status ph-sub">Tell us how we can help</span>
                        </template>
                        <template v-else>
                            <strong class="ph-clip">{{ activeTicket?.subject || 'Conversation' }}</strong>
                            <span v-if="activeTicket" class="ph-status ph-sub">
                                {{ categories[activeTicket.category] || activeTicket.category }} · Ticket #{{ activeTicket.id }}
                            </span>
                        </template>
                    </div>

                    <button class="ph-x" aria-label="Minimise chat" @click="open = false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                </header>

                <!-- ── LIST ── -->
                <div v-if="view === 'list'" class="body list">
                    <button class="new-cta" data-autofocus @click="goNew">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        Start a new conversation
                    </button>

                    <p v-if="errorMsg" class="err-banner">{{ errorMsg }}</p>

                    <div v-if="listLoading && !tickets.length" class="loading">
                        <span class="spin"></span> Loading your conversations…
                    </div>

                    <div v-else-if="!tickets.length" class="empty">
                        <div class="empty-ic">💬</div>
                        <strong>No conversations yet</strong>
                        <p>Ask us anything about exams, payments or your account — we're here to help.</p>
                    </div>

                    <template v-else>
                        <template v-if="openTickets.length">
                            <p class="eyebrow">Active</p>
                            <button
                                v-for="t in openTickets"
                                :key="t.id"
                                class="tk"
                                @click="selectTicket(t)"
                            >
                                <span class="tk-ic">{{ (categories[t.category] || t.category || '?')[0] }}</span>
                                <span class="tk-info">
                                    <span class="tk-subj">
                                        {{ t.subject }}
                                        <i v-if="t.student_unread > 0" class="tk-dot" title="New reply"></i>
                                    </span>
                                    <span class="tk-meta">{{ t.messages_count }} message{{ t.messages_count === 1 ? '' : 's' }} · {{ relTime(t.last_reply_at || t.created_at) }}</span>
                                </span>
                                <span class="pill" :class="statusMeta[t.status]?.cls">{{ statusMeta[t.status]?.label || t.status }}</span>
                            </button>
                        </template>

                        <template v-if="doneTickets.length">
                            <p class="eyebrow">Resolved</p>
                            <button
                                v-for="t in doneTickets"
                                :key="t.id"
                                class="tk tk-muted"
                                @click="selectTicket(t)"
                            >
                                <span class="tk-ic">{{ (categories[t.category] || t.category || '?')[0] }}</span>
                                <span class="tk-info">
                                    <span class="tk-subj">{{ t.subject }}</span>
                                    <span class="tk-meta">{{ t.messages_count }} message{{ t.messages_count === 1 ? '' : 's' }} · {{ relTime(t.last_reply_at || t.created_at) }}</span>
                                </span>
                                <span class="pill" :class="statusMeta[t.status]?.cls">{{ statusMeta[t.status]?.label || t.status }}</span>
                            </button>
                        </template>
                    </template>

                    <button class="fullpage" @click="goToFullPage">Open the full support page →</button>
                </div>

                <!-- ── THREAD ── -->
                <div v-else-if="view === 'thread'" class="body thread-wrap">
                    <div ref="threadScroll" class="thread">
                        <div v-if="threadLoading && !activeTicket" class="loading"><span class="spin"></span> Loading…</div>

                        <template v-else-if="activeTicket">
                            <div class="day-tag">Opened {{ msgTime(activeTicket.created_at) }}</div>
                            <div
                                v-for="m in activeTicket.messages"
                                :key="m.id"
                                class="msg"
                                :class="m.author_role === 'student' ? 'mine' : 'theirs'"
                            >
                                <div class="bubble">
                                    <div class="bhead">
                                        <strong>{{ m.author_role === 'student' ? 'You' : (m.author_name || 'Support Team') }}</strong>
                                        <small>{{ msgTime(m.created_at) }}</small>
                                    </div>
                                    <p>{{ m.body }}</p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <p v-if="errorMsg" class="err-banner err-inline">{{ errorMsg }}</p>

                    <form
                        v-if="activeTicket && activeTicket.status !== 'closed'"
                        class="composer"
                        @submit.prevent="sendReply"
                    >
                        <textarea
                            ref="replyBox"
                            v-model="reply"
                            data-autofocus
                            rows="1"
                            maxlength="5000"
                            placeholder="Type your reply…"
                            @input="resizeReply"
                            @keydown="onReplyKey"
                        ></textarea>
                        <button type="submit" class="send" :disabled="sending || !reply.trim()" aria-label="Send reply">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
                        </button>
                    </form>
                    <div v-else-if="activeTicket" class="closed-note">This conversation is closed. Start a new one if you need more help.</div>
                </div>

                <!-- ── NEW ── -->
                <form v-else class="body new-form" @submit.prevent="submitNew">
                    <label class="fld">
                        <span>Subject</span>
                        <input v-model="newForm.subject" data-autofocus type="text" maxlength="150" placeholder="Brief summary of your issue" :class="{ err: formErrors.subject }" />
                        <small v-if="formErrors.subject" class="fld-err">{{ formErrors.subject[0] }}</small>
                    </label>

                    <div class="fld-row">
                        <label class="fld">
                            <span>Category</span>
                            <select v-model="newForm.category">
                                <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </label>
                        <label class="fld">
                            <span>Priority</span>
                            <select v-model="newForm.priority">
                                <option v-for="(label, key) in priorities" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </label>
                    </div>

                    <label class="fld fld-grow">
                        <span>Message</span>
                        <textarea v-model="newForm.body" maxlength="5000" placeholder="Describe your issue in detail…" :class="{ err: formErrors.body }"></textarea>
                        <small v-if="formErrors.body" class="fld-err">{{ formErrors.body[0] }}</small>
                    </label>

                    <p v-if="errorMsg" class="err-banner">{{ errorMsg }}</p>

                    <div class="new-foot">
                        <button type="button" class="ghost" @click="goList">Cancel</button>
                        <button type="submit" class="primary" :disabled="sending">
                            {{ sending ? 'Sending…' : 'Send message' }}
                        </button>
                    </div>
                </form>
            </section>
        </transition>

        <!-- ───── Launcher ───── -->
        <button
            class="launcher"
            :class="{ 'is-open': open, 'has-unread': unread > 0 && !open }"
            :aria-label="open ? 'Minimise support chat' : 'Open support chat'"
            :aria-expanded="open"
            @click="toggle"
        >
            <span v-if="unread > 0 && !open" class="l-pulse" aria-hidden="true"></span>
            <span class="l-ico" aria-hidden="true">
                <svg v-if="!open" class="ico-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2Z"/><path d="M8.5 9.5h7M8.5 12.5h4"/></svg>
                <svg v-else class="ico-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </span>
            <span v-if="unread > 0 && !open" class="l-badge">{{ unread > 9 ? '9+' : unread }}</span>
        </button>
    </div>
</template>

<style scoped>
.noh-support {
    --ink: #0A1024; --ink-2: #131C3D; --ink-soft: #2A335A;
    --paper: #FBF6EC; --paper-2: #F3E9D6; --paper-line: #E7D9BE;
    --saffron: #EE6A2C; --saffron-dk: #C9501A;
    --gold: #D6991F; --gold-lt: #F2C84B;
    --emerald: #168A66; --royal: #2C49A6;
    --ink-55: rgba(10,16,36,.55); --ink-70: rgba(10,16,36,.7);
    font-family: "Plus Jakarta Sans", system-ui, sans-serif;
}

/* ── Launcher ── */
.launcher {
    position: fixed; right: 24px; bottom: 24px; z-index: 90;
    width: 60px; height: 60px; border: 0; cursor: pointer; padding: 0;
    border-radius: 20px;
    background: linear-gradient(150deg, #1B2748 0%, #131C3D 50%, #0A1024 100%);
    box-shadow:
        0 0 0 1px rgba(242,200,75,.35) inset,
        0 18px 42px -14px rgba(238,106,44,.55),
        0 10px 26px -12px rgba(10,16,36,.7);
    display: grid; place-items: center;
    transition: transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s, border-radius .3s;
}
.launcher:hover {
    transform: translateY(-3px);
    box-shadow:
        0 0 0 1px rgba(242,200,75,.6) inset,
        0 24px 52px -14px rgba(238,106,44,.7),
        0 14px 30px -12px rgba(10,16,36,.75);
}
.launcher:active { transform: translateY(-1px) scale(.97); }
.launcher:focus-visible { outline: 3px solid var(--gold-lt); outline-offset: 3px; }
.launcher.is-open { border-radius: 18px; }

.l-ico { display: grid; place-items: center; color: #fff; }
.l-ico svg { width: 26px; height: 26px; display: block; }
.ico-chat :nth-child(2) { stroke: var(--gold-lt); }

.l-badge {
    position: absolute; top: -6px; right: -6px; min-width: 22px; height: 22px; padding: 0 6px;
    border-radius: 11px; background: var(--saffron); color: #fff;
    font-family: "Space Grotesk", monospace; font-weight: 700; font-size: .74rem;
    display: grid; place-items: center; box-shadow: 0 0 0 2.5px var(--paper), 0 6px 14px -4px rgba(238,106,44,.7);
}
.l-pulse {
    position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
    box-shadow: 0 0 0 0 rgba(238,106,44,.5);
    animation: sonar 2.4s ease-out infinite;
}
@keyframes sonar {
    0%   { box-shadow: 0 0 0 0 rgba(238,106,44,.45); }
    70%  { box-shadow: 0 0 0 16px rgba(238,106,44,0); }
    100% { box-shadow: 0 0 0 0 rgba(238,106,44,0); }
}

/* ── Panel ── */
.panel {
    position: fixed; right: 24px; bottom: 98px; z-index: 89;
    width: 384px; max-width: calc(100vw - 32px);
    height: min(640px, calc(100vh - 130px));
    display: flex; flex-direction: column;
    background: var(--paper);
    border: 1px solid var(--paper-line);
    border-radius: 26px; overflow: hidden;
    box-shadow: 0 44px 90px -30px rgba(10,16,36,.55), 0 18px 40px -22px rgba(10,16,36,.4);
}

.panel-enter-active { transition: transform .28s cubic-bezier(.2,.8,.2,1), opacity .28s; }
.panel-leave-active { transition: transform .2s ease, opacity .2s; }
.panel-enter-from, .panel-leave-to { opacity: 0; transform: translateY(14px) scale(.97); }
.panel { transform-origin: bottom right; }

/* ── Panel header ── */
.ph {
    position: relative; flex-shrink: 0;
    display: flex; align-items: center; gap: .7rem;
    padding: 1rem 1.05rem;
    background: linear-gradient(135deg, #1B2748 0%, #131C3D 48%, #0A1024 100%);
    color: #fff; overflow: hidden;
}
.ph-blob {
    position: absolute; top: -60px; right: -30px; width: 160px; height: 160px; border-radius: 50%;
    background: radial-gradient(circle, rgba(242,200,75,.3), transparent 68%);
    filter: blur(26px); pointer-events: none;
}
.ph-mark {
    width: 40px; height: 40px; flex-shrink: 0; border-radius: 12px; display: grid; place-items: center;
    background: rgba(242,200,75,.14); color: var(--gold-lt);
    box-shadow: inset 0 0 0 1px rgba(242,200,75,.3); position: relative; z-index: 1;
}
.ph-mark svg { width: 22px; height: 22px; }
.ph-back, .ph-x {
    flex-shrink: 0; border: 0; background: rgba(251,246,236,.08); color: #fff; cursor: pointer;
    width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center;
    position: relative; z-index: 1; transition: background .18s;
}
.ph-back:hover, .ph-x:hover { background: rgba(251,246,236,.18); }
.ph-back:focus-visible, .ph-x:focus-visible { outline: 2px solid var(--gold-lt); outline-offset: 2px; }
.ph-back svg { width: 18px; height: 18px; }
.ph-x svg { width: 18px; height: 18px; }
.ph-title { flex: 1; min-width: 0; position: relative; z-index: 1; display: flex; flex-direction: column; gap: .12rem; }
.ph-title strong { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.08rem; line-height: 1.15; }
.ph-clip { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ph-status { font-size: .74rem; color: rgba(251,246,236,.62); display: flex; align-items: center; gap: .35rem; }
.ph-status.ph-sub { color: rgba(251,246,236,.5); }
.live { width: 7px; height: 7px; border-radius: 50%; background: var(--emerald); box-shadow: 0 0 0 3px rgba(22,138,102,.25); }

/* ── Body base ── */
.body { flex: 1; min-height: 0; }
.list { overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .55rem; }

.eyebrow { margin: .5rem .1rem .05rem; font-size: .68rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--ink-55); }

.new-cta {
    display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
    background: var(--saffron); color: #fff; border: 0; border-radius: 14px;
    padding: .8rem 1rem; font-family: inherit; font-size: .92rem; font-weight: 700; cursor: pointer;
    box-shadow: 0 14px 30px -14px rgba(238,106,44,.8); transition: background .18s, transform .15s;
}
.new-cta:hover { background: var(--saffron-dk); transform: translateY(-1px); }
.new-cta:focus-visible { outline: 3px solid var(--gold-lt); outline-offset: 2px; }
.new-cta svg { width: 17px; height: 17px; }

.tk {
    display: flex; align-items: center; gap: .7rem; width: 100%; text-align: left;
    background: #fff; border: 1px solid var(--paper-line); border-radius: 15px;
    padding: .7rem .8rem; cursor: pointer; font-family: inherit;
    box-shadow: 0 2px 8px rgba(10,16,36,.04); transition: transform .14s, border-color .18s, box-shadow .18s;
}
.tk:hover { transform: translateY(-1px); border-color: var(--saffron); box-shadow: 0 14px 28px -18px rgba(10,16,36,.4); }
.tk:focus-visible { outline: 2px solid var(--saffron); outline-offset: 1px; }
.tk-muted { background: transparent; box-shadow: none; }
.tk-ic {
    width: 38px; height: 38px; flex-shrink: 0; border-radius: 11px; display: grid; place-items: center;
    background: var(--paper-2); color: var(--saffron-dk);
    font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 1rem; text-transform: uppercase;
}
.tk-info { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: .12rem; }
.tk-subj { display: flex; align-items: center; gap: .4rem; font-size: .9rem; font-weight: 700; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tk-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--saffron); flex-shrink: 0; }
.tk-meta { font-size: .74rem; color: var(--ink-55); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.pill { flex-shrink: 0; font-size: .68rem; font-weight: 700; padding: .28rem .55rem; border-radius: 999px; white-space: nowrap; }
.s-open { background: rgba(238,106,44,.14); color: var(--saffron-dk); }
.s-pending { background: rgba(214,153,31,.18); color: #9a7b2e; }
.s-answered { background: rgba(44,73,166,.13); color: var(--royal); }
.s-resolved { background: rgba(22,138,102,.15); color: var(--emerald); }
.s-closed { background: rgba(91,99,115,.15); color: #5B6373; }
.p-low { background: rgba(91,99,115,.12); color: #5B6373; }
.p-medium { background: rgba(44,73,166,.12); color: var(--royal); }
.p-high { background: rgba(220,38,38,.12); color: #DC2626; }

.fullpage {
    margin-top: .35rem; border: 0; background: transparent; color: var(--saffron-dk);
    font-family: inherit; font-size: .8rem; font-weight: 600; cursor: pointer; padding: .5rem; align-self: center;
}
.fullpage:hover { text-decoration: underline; }

.empty { text-align: center; padding: 2rem 1rem 1rem; color: var(--ink-55); }
.empty-ic { font-size: 2rem; margin-bottom: .3rem; }
.empty strong { display: block; font-family: "Fraunces", serif; font-size: 1.05rem; color: var(--ink); margin-bottom: .25rem; }
.empty p { font-size: .84rem; margin: 0 auto; max-width: 260px; line-height: 1.45; }

.loading { display: flex; align-items: center; justify-content: center; gap: .5rem; padding: 2rem 1rem; color: var(--ink-55); font-size: .85rem; }
.spin { width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--paper-line); border-top-color: var(--saffron); animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.err-banner { background: #fdeaea; border: 1px solid #f4c4c4; color: #b42323; border-radius: 11px; padding: .55rem .75rem; font-size: .8rem; margin: 0; }
.err-inline { margin: 0 1rem; }

/* ── Thread ── */
.thread-wrap { display: flex; flex-direction: column; min-height: 0; }
.thread {
    flex: 1; min-height: 0; overflow-y: auto; padding: 1.1rem 1rem; display: flex; flex-direction: column; gap: .7rem;
    background:
        radial-gradient(60% 40% at 100% 0%, rgba(238,106,44,.05), transparent 60%),
        var(--paper);
}
.day-tag { align-self: center; font-size: .7rem; color: var(--ink-55); background: var(--paper-2); padding: .25rem .7rem; border-radius: 999px; margin-bottom: .2rem; }
.msg { display: flex; }
.msg.mine { justify-content: flex-end; }
.msg.theirs { justify-content: flex-start; }
.bubble { max-width: 82%; border-radius: 16px; padding: .65rem .85rem; border: 1px solid var(--paper-line); }
.msg.mine .bubble { background: #fff; border-bottom-right-radius: 5px; }
.msg.theirs .bubble { background: var(--ink); color: var(--paper); border-color: var(--ink); border-bottom-left-radius: 5px; }
.bhead { display: flex; align-items: baseline; gap: .5rem; margin-bottom: .2rem; }
.bhead strong { font-size: .76rem; }
.msg.mine .bhead strong { color: var(--ink); }
.msg.theirs .bhead strong { color: var(--gold-lt); }
.bhead small { font-size: .66rem; opacity: .6; }
.bubble p { margin: 0; font-size: .86rem; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }

.composer { flex-shrink: 0; display: flex; align-items: flex-end; gap: .5rem; padding: .7rem .8rem; border-top: 1px solid var(--paper-line); background: var(--paper); }
.composer textarea {
    flex: 1; resize: none; border: 1px solid var(--paper-line); border-radius: 13px;
    padding: .6rem .8rem; font-family: inherit; font-size: .88rem; color: var(--ink); background: #fff;
    max-height: 120px; line-height: 1.4;
}
.composer textarea:focus { outline: none; border-color: var(--saffron); }
.send {
    flex-shrink: 0; width: 42px; height: 42px; border: 0; border-radius: 12px; cursor: pointer;
    background: var(--saffron); color: #fff; display: grid; place-items: center;
    box-shadow: 0 10px 22px -10px rgba(238,106,44,.8); transition: background .18s, transform .15s;
}
.send:hover:not(:disabled) { background: var(--saffron-dk); transform: translateY(-1px); }
.send:disabled { opacity: .45; cursor: not-allowed; }
.send:focus-visible { outline: 2px solid var(--gold-lt); outline-offset: 2px; }
.send svg { width: 18px; height: 18px; }
.closed-note { flex-shrink: 0; padding: .9rem 1rem; border-top: 1px solid var(--paper-line); text-align: center; font-size: .8rem; color: var(--ink-55); }

/* ── New form ── */
.new-form { overflow-y: auto; padding: 1.1rem; display: flex; flex-direction: column; gap: .85rem; }
.fld { display: flex; flex-direction: column; gap: .3rem; }
.fld > span { font-size: .78rem; font-weight: 600; color: var(--ink); }
.fld input, .fld select, .fld textarea {
    width: 100%; border: 1px solid var(--paper-line); border-radius: 11px;
    padding: .6rem .75rem; font-family: inherit; font-size: .88rem; color: var(--ink); background: #fff;
}
.fld textarea { resize: vertical; min-height: 120px; line-height: 1.45; }
.fld-grow { flex: 1; }
.fld input:focus, .fld select:focus, .fld textarea:focus { outline: none; border-color: var(--saffron); }
.fld .err { border-color: #DC2626; background: #fdeaea; }
.fld-err { color: #DC2626; font-size: .74rem; }
.fld-row { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }
.new-foot { display: flex; justify-content: flex-end; gap: .5rem; margin-top: .1rem; }
.ghost { border: 1px solid var(--paper-line); background: transparent; color: var(--ink-70); border-radius: 11px; padding: .6rem 1rem; font-family: inherit; font-size: .86rem; font-weight: 600; cursor: pointer; }
.ghost:hover { background: var(--paper-2); }
.primary { border: 0; background: var(--saffron); color: #fff; border-radius: 11px; padding: .6rem 1.2rem; font-family: inherit; font-size: .86rem; font-weight: 700; cursor: pointer; box-shadow: 0 12px 26px -12px rgba(238,106,44,.8); }
.primary:hover:not(:disabled) { background: var(--saffron-dk); }
.primary:disabled { opacity: .55; cursor: not-allowed; }

/* ── Mobile: near-fullscreen sheet ── */
@media (max-width: 480px) {
    .panel {
        right: 12px; left: 12px; bottom: 88px; width: auto; max-width: none;
        height: min(76vh, calc(100vh - 110px));
    }
    .launcher { right: 16px; bottom: 16px; }
}

/* ── Reduced motion ── */
@media (prefers-reduced-motion: reduce) {
    .launcher, .new-cta, .tk, .send { transition: none; }
    .l-pulse { animation: none; }
    .spin { animation: none; }
    .panel-enter-active, .panel-leave-active { transition: opacity .15s; }
    .panel-enter-from, .panel-leave-to { transform: none; }
}
</style>
