<template>
  <AdminLayout :title="ticket.subject" subtitle="Support ticket conversation">

    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.success }}
    </div>

    <Link :href="route('admin.support.index')" class="inline-flex items-center gap-1.5 text-text-muted hover:text-primary text-sm font-semibold mb-4 transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      Back to tickets
    </Link>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Conversation -->
      <div class="lg:col-span-2 space-y-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
              <h2 class="font-heading font-bold text-text-main text-base">{{ ticket.subject }}</h2>
              <p class="text-text-muted text-xs mt-0.5">Ticket #{{ ticket.id }} · opened {{ formatDate(ticket.created_at) }}</p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold shrink-0" :class="statusBadge(ticket.status)">
              <span class="w-1.5 h-1.5 rounded-full" :class="statusDot(ticket.status)"></span>
              {{ statusLabel(ticket.status) }}
            </span>
          </div>

          <div class="p-5 space-y-4 bg-gray-50/40">
            <div
              v-for="m in ticket.messages"
              :key="m.id"
              class="flex"
              :class="m.author_role === 'admin' ? 'justify-end' : 'justify-start'"
            >
              <div
                class="max-w-[80%] rounded-2xl px-4 py-3 border"
                :class="m.author_role === 'admin' ? 'bg-primary text-white border-primary rounded-br-sm' : 'bg-white border-gray-200 rounded-bl-sm'"
              >
                <div class="flex items-baseline gap-2 mb-1">
                  <span class="text-xs font-bold" :class="m.author_role === 'admin' ? 'text-gold-light' : 'text-primary'">
                    {{ m.author_role === 'admin' ? (m.author_name || 'Support Team') : (m.author_name || 'Student') }}
                  </span>
                  <span class="text-[10px]" :class="m.author_role === 'admin' ? 'text-white/60' : 'text-text-muted'">{{ formatDateTime(m.created_at) }}</span>
                </div>
                <p class="text-sm leading-relaxed whitespace-pre-wrap break-words" :class="m.author_role === 'admin' ? 'text-white/95' : 'text-text-main'">{{ m.body }}</p>
              </div>
            </div>
          </div>

          <!-- Reply -->
          <form v-if="ticket.status !== 'closed'" class="p-5 border-t border-gray-100" @submit.prevent="submitReply">
            <textarea
              v-model="replyForm.body" rows="3" maxlength="5000"
              placeholder="Write a reply to the student…"
              class="w-full border rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
              :class="replyForm.errors.body ? 'border-danger bg-danger/5' : 'border-gray-200 bg-gray-50'"
            ></textarea>
            <div class="flex items-center justify-between mt-3">
              <p v-if="replyForm.errors.body" class="text-danger text-xs">{{ replyForm.errors.body }}</p>
              <span v-else class="text-text-muted text-xs">The student is notified by email and in-app.</span>
              <button
                type="submit"
                :disabled="replyForm.processing || !replyForm.body.trim()"
                class="bg-accent text-white px-5 py-2 rounded-xl text-sm font-semibold hover:bg-accent-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ replyForm.processing ? 'Sending…' : 'Send Reply' }}
              </button>
            </div>
          </form>
          <div v-else class="p-5 border-t border-gray-100 text-center text-text-muted text-sm">
            This ticket is closed. Change the status to reopen the conversation.
          </div>
        </div>
      </div>

      <!-- Side panel -->
      <div class="space-y-6">
        <!-- Student -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-3">Student</p>
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-bold shrink-0">
              {{ (ticket.student?.name || '?').charAt(0).toUpperCase() }}
            </div>
            <div class="min-w-0">
              <p class="font-semibold text-text-main truncate">{{ ticket.student?.name || '—' }}</p>
              <p class="text-text-muted text-xs truncate">{{ ticket.student?.email }}</p>
            </div>
          </div>
          <p v-if="ticket.student?.phone" class="text-text-muted text-xs">📞 {{ ticket.student.phone }}</p>
          <Link v-if="ticket.student" :href="route('admin.users.show', ticket.student.id)" class="text-primary text-xs font-semibold hover:text-accent transition-colors mt-2 inline-block">View profile →</Link>
        </div>

        <!-- Meta + controls -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-text-muted text-xs font-semibold uppercase tracking-wider">Category</span>
            <span class="text-text-main text-sm font-semibold capitalize">{{ ticket.category }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-text-muted text-xs font-semibold uppercase tracking-wider">Priority</span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold capitalize" :class="priorityBadge(ticket.priority)">{{ ticket.priority }}</span>
          </div>

          <div class="border-t border-gray-100 pt-4">
            <label class="text-text-muted text-xs font-semibold uppercase tracking-wider block mb-1.5">Status</label>
            <select v-model="controlForm.status" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
              <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel(s) }}</option>
            </select>
          </div>

          <div>
            <label class="text-text-muted text-xs font-semibold uppercase tracking-wider block mb-1.5">Assigned to</label>
            <select v-model="controlForm.assigned_to" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
              <option :value="null">Unassigned</option>
              <option v-for="a in admins" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>

          <button
            @click="saveControls"
            :disabled="controlForm.processing"
            class="w-full bg-primary text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-50"
          >
            {{ controlForm.processing ? 'Saving…' : 'Update Ticket' }}
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { onMounted, onBeforeUnmount } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  ticket:   Object,
  admins:   Array,
  statuses: Array,
});

const replyForm = useForm({ body: '' });
const submitReply = () => {
  if (!replyForm.body.trim()) return;
  replyForm.post(route('admin.support.reply', props.ticket.id), {
    preserveScroll: true,
    onSuccess: () => replyForm.reset('body'),
  });
};

const controlForm = useForm({
  status:      props.ticket.status,
  assigned_to: props.ticket.assigned_to ?? null,
});
const saveControls = () => {
  controlForm.patch(route('admin.support.status', props.ticket.id), { preserveScroll: true });
};

// Poll for new student replies every ~15s.
let poll = null;
onMounted(() => {
  poll = setInterval(() => {
    router.reload({ only: ['ticket'], preserveScroll: true, preserveState: true });
  }, 15000);
});
onBeforeUnmount(() => poll && clearInterval(poll));

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const formatDateTime = (d) => d ? new Date(d).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '';

const statusLabel = (s) => ({ open: 'Open', pending: 'Awaiting reply', answered: 'Answered', resolved: 'Resolved', closed: 'Closed' }[s] || s);
const statusBadge = (s) => ({
  open:     'bg-accent/10 text-accent',
  pending:  'bg-gold/10 text-gold-dark',
  answered: 'bg-royal/10 text-royal',
  resolved: 'bg-success/10 text-success',
  closed:   'bg-gray-100 text-text-muted',
}[s] || 'bg-gray-100 text-text-muted');
const statusDot = (s) => ({
  open: 'bg-accent', pending: 'bg-gold', answered: 'bg-royal', resolved: 'bg-success', closed: 'bg-gray-400',
}[s] || 'bg-gray-400');
const priorityBadge = (p) => ({
  high: 'bg-danger/10 text-danger', medium: 'bg-royal/10 text-royal', low: 'bg-gray-100 text-text-muted',
}[p] || 'bg-gray-100 text-text-muted');
</script>
