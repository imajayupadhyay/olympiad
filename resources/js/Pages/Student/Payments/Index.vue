<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    payments: { type: Array, default: () => [] },
});

const fmt = (d) => d ? new Date(d).toLocaleString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

const statusMeta = {
    paid:     { label: 'Paid',     cls: 's-paid' },
    created:  { label: 'Pending',  cls: 's-pending' },
    failed:   { label: 'Failed',   cls: 's-failed' },
    refunded: { label: 'Refunded', cls: 's-refunded' },
};
</script>

<template>
    <Head title="Payments" />

    <StudentLayout title="Payments">
        <div class="head">
            <h2 class="h2">Payment History</h2>
            <p class="sub">All your olympiad enrolment transactions.</p>
        </div>

        <div v-if="payments.length" class="list">
            <div v-for="p in payments" :key="p.id" class="row">
                <div class="row-l">
                    <span class="ic" :class="statusMeta[p.status]?.cls">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    </span>
                    <div>
                        <strong>{{ p.exams.length ? p.exams.join(', ') : 'Exam enrolment' }}</strong>
                        <small>{{ fmt(p.created_at) }} · <span class="ref">{{ p.reference }}</span></small>
                    </div>
                </div>
                <div class="row-r">
                    <span class="amount">₹{{ p.amount.toLocaleString('en-IN') }}</span>
                    <span class="status" :class="statusMeta[p.status]?.cls">{{ statusMeta[p.status]?.label ?? p.status }}</span>
                    <Link v-if="p.status === 'created'" :href="route('student.payments.show', p.id)" class="resume">Complete →</Link>
                </div>
            </div>
        </div>

        <div v-else class="empty">
            <div class="empty-ic">💳</div>
            <h3>No payments yet</h3>
            <p>Your paid olympiad enrolments will appear here.</p>
            <Link :href="route('student.exams')" class="cta">Browse exams →</Link>
        </div>
    </StudentLayout>
</template>

<style scoped>
.head { margin-bottom: 1.3rem; }
.h2 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.6rem; color: #0A1024; margin: 0; }
.sub { color: #5B6373; font-size: .92rem; margin: .25rem 0 0; }

.list { display: grid; gap: .8rem; }
.row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: #fff; border: 1px solid #E7D9BE; border-radius: 16px; padding: 1rem 1.2rem; box-shadow: 0 2px 8px rgba(10,16,36,.04); flex-wrap: wrap; }
.row-l { display: flex; align-items: center; gap: .9rem; min-width: 0; }
.ic { width: 44px; height: 44px; flex-shrink: 0; border-radius: 12px; display: grid; place-items: center; background: #F3E9D6; color: #5B6373; }
.ic svg { width: 20px; height: 20px; }
.ic.s-paid { background: rgba(22,138,102,.12); color: #168A66; }
.ic.s-failed { background: rgba(220,38,38,.1); color: #DC2626; }
.ic.s-pending { background: rgba(214,153,31,.14); color: #B45309; }
.row-l strong { display: block; color: #0A1024; font-size: .95rem; }
.row-l small { color: #9aa0ad; font-size: .8rem; }
.ref { font-family: "Space Grotesk", monospace; }

.row-r { display: flex; align-items: center; gap: 1rem; }
.amount { font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 1.05rem; color: #0A1024; }
.status { font-size: .74rem; font-weight: 700; padding: .25rem .65rem; border-radius: 999px; }
.s-paid { background: rgba(22,138,102,.14); color: #168A66; }
.s-pending { background: rgba(214,153,31,.16); color: #B45309; }
.s-failed { background: rgba(220,38,38,.12); color: #DC2626; }
.s-refunded { background: rgba(91,99,115,.14); color: #5B6373; }
.resume { font-size: .82rem; font-weight: 700; color: #C9501A; text-decoration: none; }
.resume:hover { text-decoration: underline; }

.empty { text-align: center; padding: 3.5rem 1rem; color: #5B6373; }
.empty-ic { font-size: 2.4rem; margin-bottom: .5rem; }
.empty h3 { font-family: "Fraunces", serif; color: #0A1024; margin: 0 0 .3rem; }
.empty p { font-size: .9rem; margin: 0 0 1rem; }
.cta { text-decoration: none; font-weight: 700; color: #C9501A; }
.cta:hover { text-decoration: underline; }
</style>
