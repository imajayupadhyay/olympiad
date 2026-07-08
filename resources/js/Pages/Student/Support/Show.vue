<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({
    ticket: { type: Object, required: true },
});

const fmt = (d) => d ? new Date(d).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '';

const statusMeta = {
    open:     { label: 'Open',      cls: 's-open' },
    pending:  { label: 'Awaiting reply', cls: 's-pending' },
    answered: { label: 'Answered',  cls: 's-answered' },
    resolved: { label: 'Resolved',  cls: 's-resolved' },
    closed:   { label: 'Closed',    cls: 's-closed' },
};

const form = useForm({ body: '' });
const submit = () => {
    if (!form.body.trim()) return;
    form.post(route('student.support.reply', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
};

// Poll for new admin replies every ~15s while the thread is open.
let poll = null;
onMounted(() => {
    poll = setInterval(() => {
        router.reload({ only: ['ticket'], preserveScroll: true, preserveState: true });
    }, 15000);
});
onBeforeUnmount(() => poll && clearInterval(poll));
</script>

<template>
    <Head :title="`Support · ${ticket.subject}`" />

    <StudentLayout title="Support">
        <div v-if="$page.props.flash?.success" class="flash flash-ok">{{ $page.props.flash.success }}</div>

        <Link :href="route('student.support')" class="back">← Back to tickets</Link>

        <div class="card">
            <div class="t-head">
                <div>
                    <h2 class="h2">{{ ticket.subject }}</h2>
                    <p class="meta">Opened {{ fmt(ticket.created_at) }} · Ticket #{{ ticket.id }}</p>
                </div>
                <span class="pill" :class="statusMeta[ticket.status]?.cls">{{ statusMeta[ticket.status]?.label || ticket.status }}</span>
            </div>

            <div class="thread">
                <div
                    v-for="m in ticket.messages"
                    :key="m.id"
                    class="msg"
                    :class="m.author_role === 'student' ? 'mine' : 'theirs'"
                >
                    <div class="bubble">
                        <div class="bubble-head">
                            <strong>{{ m.author_role === 'student' ? 'You' : (m.author_name || 'Support Team') }}</strong>
                            <small>{{ fmt(m.created_at) }}</small>
                        </div>
                        <p>{{ m.body }}</p>
                    </div>
                </div>
            </div>

            <form v-if="ticket.status !== 'closed'" class="composer" @submit.prevent="submit">
                <textarea v-model="form.body" rows="3" maxlength="5000" placeholder="Type your reply…"></textarea>
                <div class="composer-foot">
                    <small v-if="form.errors.body" class="err-msg">{{ form.errors.body }}</small>
                    <button type="submit" class="btn" :disabled="form.processing || !form.body.trim()">
                        {{ form.processing ? 'Sending…' : 'Send reply' }}
                    </button>
                </div>
            </form>
            <div v-else class="closed-note">This ticket is closed. Raise a new ticket if you need further help.</div>
        </div>
    </StudentLayout>
</template>

<style scoped>
.flash { padding: .8rem 1.1rem; border-radius: 12px; font-size: .88rem; margin-bottom: 1rem; background: #e8f6ef; border: 1px solid #b6e3cf; color: #0f6b4c; }
.back { display: inline-block; margin-bottom: 1rem; color: #C9501A; font-weight: 600; font-size: .88rem; text-decoration: none; }
.back:hover { text-decoration: underline; }

.card { background: #fff; border: 1px solid #E7D9BE; border-radius: 18px; box-shadow: 0 2px 8px rgba(10,16,36,.04); overflow: hidden; }
.t-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.2rem 1.4rem; border-bottom: 1px solid #F3E9D6; }
.h2 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.35rem; color: #0A1024; margin: 0; }
.meta { color: #9aa0ad; font-size: .8rem; margin: .25rem 0 0; }
.pill { font-size: .74rem; font-weight: 700; padding: .35rem .7rem; border-radius: 999px; white-space: nowrap; flex-shrink: 0; }
.s-open { background: rgba(238,106,44,.14); color: #C9501A; }
.s-pending { background: rgba(214,153,31,.16); color: #9a7b2e; }
.s-answered { background: rgba(44,73,166,.12); color: #2C49A6; }
.s-resolved { background: rgba(22,138,102,.14); color: #168A66; }
.s-closed { background: rgba(91,99,115,.14); color: #5B6373; }

.thread { padding: 1.4rem; display: grid; gap: 1rem; background: #FBF6EC; }
.msg { display: flex; }
.msg.mine { justify-content: flex-end; }
.msg.theirs { justify-content: flex-start; }
.bubble { max-width: 78%; border-radius: 16px; padding: .8rem 1rem; border: 1px solid #E7D9BE; }
.msg.mine .bubble { background: #fff; border-color: #E7D9BE; border-bottom-right-radius: 5px; }
.msg.theirs .bubble { background: #0A1024; color: #FBF6EC; border-color: #0A1024; border-bottom-left-radius: 5px; }
.bubble-head { display: flex; align-items: baseline; gap: .6rem; margin-bottom: .3rem; }
.bubble-head strong { font-size: .82rem; }
.msg.mine .bubble-head strong { color: #0A1024; }
.msg.theirs .bubble-head strong { color: #F2C84B; }
.bubble-head small { font-size: .7rem; opacity: .6; }
.bubble p { margin: 0; font-size: .9rem; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }

.composer { padding: 1.1rem 1.4rem; border-top: 1px solid #F3E9D6; }
.composer textarea { width: 100%; border: 1px solid #E7D9BE; border-radius: 12px; padding: .7rem .9rem; font-size: .9rem; font-family: inherit; color: #0A1024; background: #FBF6EC; resize: vertical; }
.composer textarea:focus { outline: none; border-color: #EE6A2C; background: #fff; }
.composer-foot { display: flex; align-items: center; justify-content: flex-end; gap: .8rem; margin-top: .7rem; }
.err-msg { color: #DC2626; font-size: .78rem; margin-right: auto; }
.btn { display: inline-flex; align-items: center; gap: .45rem; background: #EE6A2C; color: #fff; border: 0; border-radius: 12px; padding: .6rem 1.1rem; font-size: .9rem; font-weight: 700; cursor: pointer; transition: background .2s, transform .15s; font-family: inherit; }
.btn:hover { background: #C9501A; transform: translateY(-1px); }
.btn:disabled { opacity: .55; cursor: not-allowed; transform: none; }
.closed-note { padding: 1.1rem 1.4rem; border-top: 1px solid #F3E9D6; color: #9aa0ad; font-size: .85rem; text-align: center; }
</style>
