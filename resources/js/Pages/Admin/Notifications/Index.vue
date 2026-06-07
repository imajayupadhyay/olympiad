<template>
  <AdminLayout title="Notifications" subtitle="Send announcements and alerts to students">

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Total Sent</p>
        <p class="font-number text-2xl font-bold text-primary">{{ stats.total_sent }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">This Month</p>
        <p class="font-number text-2xl font-bold text-accent">{{ stats.this_month }}</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-text-muted text-xs font-semibold uppercase tracking-wider mb-1">Total Reach</p>
        <p class="font-number text-2xl font-bold text-success">{{ stats.total_reach.toLocaleString() }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

      <!-- Left: Compose -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sticky top-6">
          <h2 class="font-heading font-bold text-text-main text-sm mb-5 flex items-center gap-2">
            <span class="w-6 h-6 rounded-lg bg-primary/10 flex items-center justify-center">
              <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
              </svg>
            </span>
            Compose Notification
          </h2>

          <form @submit.prevent="submit" class="space-y-4">

            <!-- Title -->
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">
                Subject / Title <span class="text-danger">*</span>
              </label>
              <input v-model="form.title" type="text" placeholder="e.g. Results Released for Math Olympiad"
                     class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition-colors"
                     :class="form.errors.title ? 'border-danger bg-danger/5' : 'border-gray-200'" />
              <p v-if="form.errors.title" class="text-danger text-xs mt-1">{{ form.errors.title }}</p>
            </div>

            <!-- Message -->
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">
                Message <span class="text-danger">*</span>
              </label>
              <textarea v-model="form.message" rows="5"
                        placeholder="Write your announcement here…"
                        class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 resize-none transition-colors"
                        :class="form.errors.message ? 'border-danger bg-danger/5' : 'border-gray-200'"></textarea>
              <div class="flex justify-between mt-1">
                <p v-if="form.errors.message" class="text-danger text-xs">{{ form.errors.message }}</p>
                <p class="text-text-muted text-[10px] ml-auto">{{ form.message.length }}/2000</p>
              </div>
            </div>

            <!-- Channel -->
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-2">Channel</label>
              <div class="grid grid-cols-3 gap-2">
                <button
                  v-for="ch in channels" :key="ch.value" type="button"
                  @click="form.channel = ch.value"
                  class="flex flex-col items-center gap-1.5 py-2.5 px-2 rounded-xl border text-xs font-semibold transition-colors"
                  :class="form.channel === ch.value
                    ? 'bg-primary text-white border-primary'
                    : 'bg-gray-50 text-text-muted border-gray-200 hover:border-primary/40'"
                >
                  <span class="text-base">{{ ch.icon }}</span>
                  {{ ch.label }}
                </button>
              </div>
              <p class="text-text-muted text-[10px] mt-1.5">
                <span v-if="form.channel === 'email'">Delivered to student email inboxes via queue.</span>
                <span v-else-if="form.channel === 'in_app'">Appears in student portal notification bell.</span>
                <span v-else>Both email + in-app notification bell.</span>
              </p>
            </div>

            <!-- Audience -->
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-2">Audience</label>
              <div class="grid grid-cols-3 gap-2 mb-3">
                <button
                  v-for="a in audiences" :key="a.value" type="button"
                  @click="form.audience = a.value; form.exam_id = ''; form.class_level_id = ''"
                  class="flex flex-col items-center gap-1 py-2.5 px-2 rounded-xl border text-xs font-semibold transition-colors"
                  :class="form.audience === a.value
                    ? 'bg-accent text-white border-accent'
                    : 'bg-gray-50 text-text-muted border-gray-200 hover:border-accent/40'"
                >
                  <span class="text-base">{{ a.icon }}</span>
                  {{ a.label }}
                </button>
              </div>

              <!-- Exam selector -->
              <div v-if="form.audience === 'exam'">
                <select v-model="form.exam_id"
                        class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main"
                        :class="form.errors.exam_id ? 'border-danger' : 'border-gray-200'">
                  <option value="">Select Exam…</option>
                  <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <p v-if="form.errors.exam_id" class="text-danger text-xs mt-1">{{ form.errors.exam_id }}</p>
              </div>

              <!-- Class selector -->
              <div v-if="form.audience === 'class'">
                <select v-model="form.class_level_id"
                        class="w-full px-3 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main"
                        :class="form.errors.class_level_id ? 'border-danger' : 'border-gray-200'">
                  <option value="">Select Class…</option>
                  <option v-for="cl in classLevels" :key="cl.id" :value="cl.id">{{ cl.label }}</option>
                </select>
                <p v-if="form.errors.class_level_id" class="text-danger text-xs mt-1">{{ form.errors.class_level_id }}</p>
              </div>
            </div>

            <!-- Preview box -->
            <div v-if="form.title || form.message" class="bg-gray-50 border border-gray-200 rounded-xl p-4">
              <p class="text-text-muted text-[10px] font-semibold uppercase tracking-wider mb-2">Preview</p>
              <p class="text-text-main text-xs font-bold mb-1">{{ form.title || 'Subject…' }}</p>
              <p class="text-text-muted text-xs whitespace-pre-line leading-relaxed">{{ form.message || 'Message…' }}</p>
            </div>

            <button type="submit" :disabled="form.processing"
                    class="w-full bg-primary text-white py-3 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60 shadow-sm flex items-center justify-center gap-2">
              <svg v-if="!form.processing" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
              </svg>
              {{ form.processing ? 'Sending…' : 'Send Notification' }}
            </button>
          </form>
        </div>
      </div>

      <!-- Right: History -->
      <div class="lg:col-span-3">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-heading font-bold text-text-main text-sm">Notification History</h2>
            <span class="bg-gray-100 text-text-muted text-xs font-semibold px-2.5 py-1 rounded-lg font-number">
              {{ logs.total }}
            </span>
          </div>

          <!-- Empty -->
          <div v-if="logs.data.length === 0" class="py-16 text-center">
            <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <p class="text-text-muted text-sm">No notifications sent yet.</p>
          </div>

          <!-- List -->
          <div v-else class="divide-y divide-gray-50">
            <div v-for="log in logs.data" :key="log.id"
                 class="px-5 py-4 hover:bg-gray-50/50 transition-colors">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <!-- Title + badges -->
                  <div class="flex items-center gap-2 flex-wrap mb-1">
                    <p class="font-semibold text-text-main text-sm truncate">{{ log.title }}</p>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0"
                          :class="channelClass(log.channel)">
                      {{ channelLabel(log.channel) }}
                    </span>
                  </div>

                  <!-- Message preview -->
                  <p class="text-text-muted text-xs leading-snug line-clamp-2 mb-2">{{ log.message }}</p>

                  <!-- Meta row -->
                  <div class="flex items-center gap-3 flex-wrap text-[10px] text-text-muted">
                    <span class="font-number">{{ formatDate(log.sent_at) }}</span>
                    <span class="font-semibold px-1.5 py-0.5 rounded"
                          :class="audienceClass(log.audience)">
                      {{ audienceLabel(log) }}
                    </span>
                    <span class="flex items-center gap-1">
                      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                      </svg>
                      <span class="font-number font-semibold text-text-main">{{ log.recipient_count }}</span> recipients
                    </span>
                    <span v-if="log.sent_by">by {{ log.sent_by.name }}</span>
                  </div>
                </div>

                <!-- Delete -->
                <button @click="openDelete(log)"
                        class="shrink-0 text-text-muted hover:text-danger transition-colors p-1.5 rounded-lg hover:bg-danger/5">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="logs.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
            <p class="text-text-muted text-xs">Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }}</p>
            <div class="flex gap-1">
              <Link
                v-for="link in logs.links" :key="link.label"
                :href="link.url || '#'"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                  link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100',
                  !link.url ? 'opacity-40 pointer-events-none' : '',
                ]"
                v-html="link.label"
                preserve-scroll
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete modal -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-danger/10 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Delete Log?</h3>
        <p class="text-text-muted text-sm mb-1 font-semibold">{{ deleteTarget.title }}</p>
        <p class="text-text-muted text-xs mb-5">This will also remove the in-app notifications from all student portals.</p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
          <button @click="confirmDelete" class="flex-1 bg-danger text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-red-700 transition-colors">Delete</button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  logs:        Object,
  exams:       Array,
  classLevels: Array,
  stats:       Object,
});

const form = useForm({
  title:          '',
  message:        '',
  channel:        'in_app',
  audience:       'all',
  exam_id:        '',
  class_level_id: '',
});

const channels = [
  { value: 'in_app', label: 'In-App', icon: '🔔' },
  { value: 'email',  label: 'Email',  icon: '📧' },
  { value: 'both',   label: 'Both',   icon: '📣' },
];

const audiences = [
  { value: 'all',   label: 'All Students', icon: '👥' },
  { value: 'exam',  label: 'By Exam',      icon: '📝' },
  { value: 'class', label: 'By Class',     icon: '🎓' },
];

const submit = () => {
  form.post(route('admin.notifications.send'), {
    onSuccess: () => form.reset(),
  });
};

// Helpers
const formatDate = (d) =>
  d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

const channelLabel = (ch) => ({ email: 'Email', in_app: 'In-App', both: 'Email + In-App' }[ch] || ch);
const channelClass = (ch) => ({
  email:  'bg-blue-100 text-blue-600',
  in_app: 'bg-primary/10 text-primary',
  both:   'bg-accent/10 text-accent',
}[ch] || 'bg-gray-100 text-text-muted');

const audienceLabel = (log) => {
  if (log.audience === 'exam')  return log.exam?.name    ? `Exam: ${log.exam.name}` : 'By Exam';
  if (log.audience === 'class') return log.class_level?.label ? `Class: ${log.class_level.label}` : 'By Class';
  return 'All Students';
};
const audienceClass = (a) => ({
  all:   'bg-gray-100 text-text-muted',
  exam:  'bg-purple-100 text-purple-600',
  class: 'bg-amber-100 text-amber-600',
}[a] || '');

// Delete
const deleteTarget  = ref(null);
const openDelete    = (log) => { deleteTarget.value = log; };
const confirmDelete = () => {
  router.delete(route('admin.notifications.destroy', deleteTarget.value.id), {
    onFinish: () => { deleteTarget.value = null; },
  });
};
</script>
