<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    tickets:    { type: Object, default: () => ({ data: [], links: [] }) },
    categories: { type: Object, default: () => ({}) },
    priorities: { type: Object, default: () => ({}) },
});

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '';

const statusMeta = {
    open:     { label: 'Open',      cls: 's-open' },
    pending:  { label: 'Awaiting reply', cls: 's-pending' },
    answered: { label: 'Answered',  cls: 's-answered' },
    resolved: { label: 'Resolved',  cls: 's-resolved' },
    closed:   { label: 'Closed',    cls: 's-closed' },
};
const priorityCls = (p) => ({ low: 'p-low', medium: 'p-medium', high: 'p-high' }[p] ?? 'p-medium');

const showForm = ref(false);
const form = useForm({ subject: '', category: 'other', priority: 'medium', body: '' });

const submit = () => {
    form.post(route('student.support.store'), {
        onSuccess: () => { form.reset(); showForm.value = false; },
    });
};
</script>

<template>
    <Head title="Support" />

    <StudentLayout title="Support">
        <div v-if="$page.props.flash?.success" class="flash flash-ok">{{ $page.props.flash.success }}</div>
        <div v-if="$page.props.flash?.error" class="flash flash-err">{{ $page.props.flash.error }}</div>

        <div class="head">
            <div>
                <h2 class="h2">Help &amp; Support</h2>
                <p class="sub">Raise a ticket and our team will get back to you here.</p>
            </div>
            <button class="btn" @click="showForm = true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                New ticket
            </button>
        </div>

        <div v-if="tickets.data.length" class="list">
            <Link
                v-for="t in tickets.data"
                :key="t.id"
                :href="route('student.support.show', t.id)"
                class="row clickable"
            >
                <div class="row-l">
                    <span class="cat">{{ (categories[t.category] || t.category)[0] }}</span>
                    <div class="row-info">
                        <strong>
                            {{ t.subject }}
                            <span v-if="t.student_unread > 0" class="unread-dot" title="New reply"></span>
                        </strong>
                        <small>
                            {{ categories[t.category] || t.category }} ·
                            {{ t.messages_count }} message{{ t.messages_count === 1 ? '' : 's' }} ·
                            Updated {{ fmt(t.last_reply_at || t.created_at) }}
                        </small>
                    </div>
                </div>
                <div class="row-r">
                    <span class="pill" :class="priorityCls(t.priority)">{{ priorities[t.priority] || t.priority }}</span>
                    <span class="pill" :class="statusMeta[t.status]?.cls">{{ statusMeta[t.status]?.label || t.status }}</span>
                    <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"/></svg>
                </div>
            </Link>
        </div>

        <div v-else class="empty">
            <div class="empty-ic">💬</div>
            <h3>No tickets yet</h3>
            <p>Have a question or facing an issue? Raise your first support ticket.</p>
            <button class="cta" @click="showForm = true">Raise a ticket →</button>
        </div>

        <!-- pagination -->
        <div v-if="tickets.links && tickets.links.length > 3" class="pager">
            <template v-for="(l, i) in tickets.links" :key="i">
                <Link
                    v-if="l.url"
                    :href="l.url"
                    class="pg"
                    :class="{ active: l.active }"
                    v-html="l.label"
                    preserve-scroll
                />
                <span v-else class="pg disabled" v-html="l.label" />
            </template>
        </div>

        <!-- New ticket modal -->
        <div v-if="showForm" class="modal-bg" @click.self="showForm = false">
            <div class="modal">
                <div class="modal-head">
                    <h3>Raise a support ticket</h3>
                    <button class="x" @click="showForm = false" aria-label="Close">&times;</button>
                </div>

                <form class="modal-body" @submit.prevent="submit">
                    <label class="fld">
                        <span>Subject</span>
                        <input v-model="form.subject" type="text" maxlength="150" placeholder="Brief summary of your issue" :class="{ err: form.errors.subject }" />
                        <small v-if="form.errors.subject" class="err-msg">{{ form.errors.subject }}</small>
                    </label>

                    <div class="fld-row">
                        <label class="fld">
                            <span>Category</span>
                            <select v-model="form.category" :class="{ err: form.errors.category }">
                                <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </label>
                        <label class="fld">
                            <span>Priority</span>
                            <select v-model="form.priority" :class="{ err: form.errors.priority }">
                                <option v-for="(label, key) in priorities" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </label>
                    </div>

                    <label class="fld">
                        <span>Message</span>
                        <textarea v-model="form.body" rows="5" maxlength="5000" placeholder="Describe your issue in detail…" :class="{ err: form.errors.body }"></textarea>
                        <small v-if="form.errors.body" class="err-msg">{{ form.errors.body }}</small>
                    </label>

                    <div class="modal-foot">
                        <button type="button" class="btn-ghost" @click="showForm = false">Cancel</button>
                        <button type="submit" class="btn" :disabled="form.processing">
                            {{ form.processing ? 'Submitting…' : 'Submit ticket' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </StudentLayout>
</template>

<style scoped>
.flash { padding: .8rem 1.1rem; border-radius: 12px; font-size: .88rem; margin-bottom: 1rem; }
.flash-ok { background: #e8f6ef; border: 1px solid #b6e3cf; color: #0f6b4c; }
.flash-err { background: #fdeaea; border: 1px solid #f4c4c4; color: #b42323; }

.head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: 1.3rem; flex-wrap: wrap; }
.h2 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.6rem; color: #0A1024; margin: 0; }
.sub { color: #5B6373; font-size: .92rem; margin: .25rem 0 0; }

.btn { display: inline-flex; align-items: center; gap: .45rem; background: #EE6A2C; color: #fff; border: 0; border-radius: 12px; padding: .65rem 1.1rem; font-size: .9rem; font-weight: 700; cursor: pointer; transition: background .2s, transform .15s; font-family: inherit; }
.btn:hover { background: #C9501A; transform: translateY(-1px); }
.btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
.btn svg { width: 16px; height: 16px; }
.btn-ghost { background: transparent; border: 1px solid #E7D9BE; color: #5B6373; border-radius: 12px; padding: .65rem 1.1rem; font-size: .9rem; font-weight: 600; cursor: pointer; font-family: inherit; }
.btn-ghost:hover { background: #F3E9D6; }

.list { display: grid; gap: .8rem; }
.row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: #fff; border: 1px solid #E7D9BE; border-radius: 16px; padding: 1rem 1.2rem; text-decoration: none; color: inherit; box-shadow: 0 2px 8px rgba(10,16,36,.04); }
.row.clickable { cursor: pointer; transition: transform .15s, box-shadow .2s, border-color .2s; }
.row.clickable:hover { transform: translateY(-2px); border-color: #EE6A2C; box-shadow: 0 16px 34px -20px rgba(10,16,36,.3); }
.row-l { display: flex; align-items: center; gap: .9rem; min-width: 0; }
.cat { width: 44px; height: 44px; flex-shrink: 0; border-radius: 12px; display: grid; place-items: center; font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 1.1rem; background: #F3E9D6; color: #C9501A; text-transform: uppercase; }
.row-info { min-width: 0; }
.row-info strong { display: flex; align-items: center; gap: .4rem; color: #0A1024; font-size: .98rem; }
.row-info small { color: #9aa0ad; font-size: .8rem; }
.unread-dot { width: 8px; height: 8px; border-radius: 50%; background: #EE6A2C; flex-shrink: 0; }

.row-r { display: flex; align-items: center; gap: .6rem; flex-shrink: 0; }
.pill { font-size: .72rem; font-weight: 700; padding: .3rem .65rem; border-radius: 999px; white-space: nowrap; }
.p-low { background: rgba(91,99,115,.12); color: #5B6373; }
.p-medium { background: rgba(44,73,166,.12); color: #2C49A6; }
.p-high { background: rgba(220,38,38,.12); color: #DC2626; }
.s-open { background: rgba(238,106,44,.14); color: #C9501A; }
.s-pending { background: rgba(214,153,31,.16); color: #9a7b2e; }
.s-answered { background: rgba(44,73,166,.12); color: #2C49A6; }
.s-resolved { background: rgba(22,138,102,.14); color: #168A66; }
.s-closed { background: rgba(91,99,115,.14); color: #5B6373; }
.chev { width: 18px; height: 18px; color: #c9bda0; }

.empty { text-align: center; padding: 3.5rem 1rem; color: #5B6373; }
.empty-ic { font-size: 2.4rem; margin-bottom: .5rem; }
.empty h3 { font-family: "Fraunces", serif; color: #0A1024; margin: 0 0 .3rem; }
.empty p { font-size: .9rem; margin: 0 0 1rem; }
.cta { border: 0; background: transparent; text-decoration: none; font-weight: 700; color: #C9501A; cursor: pointer; font-family: inherit; font-size: .92rem; }
.cta:hover { text-decoration: underline; }

.pager { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: 1.4rem; justify-content: center; }
.pg { min-width: 34px; text-align: center; padding: .4rem .6rem; border-radius: 9px; border: 1px solid #E7D9BE; background: #fff; color: #5B6373; font-size: .82rem; text-decoration: none; }
.pg.active { background: #0A1024; color: #fff; border-color: #0A1024; }
.pg.disabled { opacity: .45; }

.modal-bg { position: fixed; inset: 0; z-index: 60; background: rgba(10,16,36,.55); backdrop-filter: blur(3px); display: grid; place-items: center; padding: 1rem; }
.modal { width: 100%; max-width: 520px; background: #fff; border-radius: 20px; box-shadow: 0 40px 90px -30px rgba(10,16,36,.6); overflow: hidden; }
.modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.3rem; border-bottom: 1px solid #E7D9BE; }
.modal-head h3 { font-family: "Fraunces", serif; font-size: 1.2rem; color: #0A1024; margin: 0; }
.x { border: 0; background: transparent; font-size: 1.6rem; line-height: 1; color: #9aa0ad; cursor: pointer; }
.modal-body { padding: 1.3rem; display: grid; gap: 1rem; }
.fld { display: grid; gap: .35rem; }
.fld > span { font-size: .82rem; font-weight: 600; color: #0A1024; }
.fld input, .fld select, .fld textarea { width: 100%; border: 1px solid #E7D9BE; border-radius: 11px; padding: .65rem .8rem; font-size: .9rem; font-family: inherit; color: #0A1024; background: #FBF6EC; resize: vertical; }
.fld input:focus, .fld select:focus, .fld textarea:focus { outline: none; border-color: #EE6A2C; background: #fff; }
.fld .err { border-color: #DC2626; background: #fdeaea; }
.err-msg { color: #DC2626; font-size: .76rem; }
.fld-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.modal-foot { display: flex; justify-content: flex-end; gap: .6rem; margin-top: .2rem; }
</style>
