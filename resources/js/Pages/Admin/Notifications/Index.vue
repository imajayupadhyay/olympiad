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
                <p class="text-text-muted text-[10px] ml-auto">{{ form.message.length }}/5000</p>
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

            <!-- Recipient mode -->
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-2">Recipients</label>
              <div class="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  @click="form.recipient_mode = 'filters'"
                  class="py-2.5 px-3 rounded-xl border text-xs font-semibold transition-colors"
                  :class="form.recipient_mode === 'filters'
                    ? 'bg-primary text-white border-primary'
                    : 'bg-gray-50 text-text-muted border-gray-200 hover:border-primary/40'"
                >
                  Use filters
                </button>
                <button
                  type="button"
                  @click="switchToSelectedMode"
                  class="py-2.5 px-3 rounded-xl border text-xs font-semibold transition-colors"
                  :class="form.recipient_mode === 'selected'
                    ? 'bg-primary text-white border-primary'
                    : 'bg-gray-50 text-text-muted border-gray-200 hover:border-primary/40'"
                >
                  Pick students
                </button>
              </div>
            </div>

            <!-- Student picker -->
            <div v-if="form.recipient_mode === 'selected'" class="space-y-3 border border-primary/10 rounded-xl p-4 bg-primary/5">
              <div>
                <label class="block text-[10px] font-semibold text-text-muted uppercase tracking-wider mb-1.5">Search Students</label>
                <input
                  v-model="studentSearch"
                  type="search"
                  placeholder="Search name, email, phone, school…"
                  class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:border-primary"
                />
                <p v-if="form.errors.selected_user_ids" class="text-danger text-xs mt-1">{{ form.errors.selected_user_ids }}</p>
              </div>

              <div v-if="selectedStudents.length" class="space-y-2">
                <p class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">Selected {{ selectedStudents.length }}</p>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="student in selectedStudents"
                    :key="student.id"
                    type="button"
                    @click="removeStudent(student.id)"
                    class="group flex items-center gap-2 max-w-full px-3 py-1.5 rounded-full bg-white border border-primary/15 text-xs font-semibold text-text-main hover:border-danger/40"
                  >
                    <span class="truncate max-w-[180px]">{{ student.name }}</span>
                    <span class="text-text-muted group-hover:text-danger">×</span>
                  </button>
                </div>
              </div>

              <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                  <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">Results</span>
                  <span v-if="studentSearchLoading" class="text-[10px] font-semibold text-accent">Searching…</span>
                </div>
                <div v-if="studentOptions.length === 0" class="p-4 text-xs text-text-muted">
                  No matching students found.
                </div>
                <button
                  v-for="student in studentOptions"
                  :key="student.id"
                  type="button"
                  @click="toggleStudent(student)"
                  class="w-full px-3 py-2.5 text-left hover:bg-gray-50 border-b border-gray-50 last:border-b-0 flex items-center justify-between gap-3"
                >
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-text-main truncate">{{ student.name }}</p>
                    <p class="text-[11px] text-text-muted truncate">{{ student.email }}</p>
                    <p class="text-[10px] text-text-muted truncate">
                      {{ [student.class, student.city, student.state].filter(Boolean).join(' · ') || 'No profile details' }}
                    </p>
                  </div>
                  <span
                    class="shrink-0 px-2 py-1 rounded-lg text-[10px] font-bold"
                    :class="isSelected(student.id) ? 'bg-success/10 text-success' : 'bg-primary/10 text-primary'"
                  >
                    {{ isSelected(student.id) ? 'Selected' : 'Add' }}
                  </span>
                </button>
              </div>
            </div>

            <!-- Audience -->
            <div v-if="form.recipient_mode === 'filters'">
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

            <!-- Filters -->
            <div v-if="form.recipient_mode === 'filters'" class="space-y-3 border border-gray-100 rounded-xl p-4 bg-gray-50/70">
              <div class="flex items-center justify-between">
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider">Recipient Filters</label>
                <span v-if="preview.loading" class="text-[10px] font-semibold text-accent">Counting…</span>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-[10px] font-semibold text-text-muted uppercase tracking-wider mb-1.5">Students</label>
                  <select v-model="form.student_status" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs bg-white focus:outline-none focus:border-primary">
                    <option value="active">Active only</option>
                    <option value="inactive">Inactive only</option>
                    <option value="all">All accounts</option>
                  </select>
                </div>
                <div>
                  <label class="block text-[10px] font-semibold text-text-muted uppercase tracking-wider mb-1.5">Enrollment</label>
                  <select v-model="form.enrollment_status" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs bg-white focus:outline-none focus:border-primary">
                    <option value="all">Any enrollment</option>
                    <option value="enrolled">Enrolled</option>
                    <option value="not_enrolled">Not enrolled</option>
                    <option value="cancelled">Cancelled access</option>
                  </select>
                </div>
              </div>

              <div>
                <label class="block text-[10px] font-semibold text-text-muted uppercase tracking-wider mb-1.5">Payment Status</label>
                <div class="grid grid-cols-3 gap-2">
                  <button
                    v-for="status in paymentStatuses"
                    :key="status.value"
                    type="button"
                    @click="form.payment_status = status.value"
                    class="py-2 px-2 rounded-lg border text-[11px] font-semibold transition-colors"
                    :class="form.payment_status === status.value
                      ? 'bg-primary text-white border-primary'
                      : 'bg-white text-text-muted border-gray-200 hover:border-primary/40'"
                  >
                    {{ status.label }}
                  </button>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-[10px] font-semibold text-text-muted uppercase tracking-wider mb-1.5">State</label>
                  <select v-model="form.state" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs bg-white focus:outline-none focus:border-primary">
                    <option value="">All states</option>
                    <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-[10px] font-semibold text-text-muted uppercase tracking-wider mb-1.5">Search</label>
                  <input
                    v-model="form.search"
                    type="search"
                    placeholder="Name, email, school…"
                    class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs bg-white focus:outline-none focus:border-primary"
                  />
                </div>
              </div>
            </div>

            <!-- Recipient preview -->
            <div class="rounded-xl border p-4" :class="preview.count === 0 ? 'bg-danger/5 border-danger/20' : 'bg-primary/5 border-primary/10'">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="text-text-muted text-[10px] font-semibold uppercase tracking-wider">Matched Recipients</p>
                  <p class="font-number text-2xl font-bold" :class="preview.count === 0 ? 'text-danger' : 'text-primary'">
                    {{ preview.count === null ? '—' : preview.count.toLocaleString() }}
                  </p>
                </div>
                <span class="text-[10px] font-semibold px-2.5 py-1 rounded-lg" :class="preview.count === 0 ? 'bg-danger/10 text-danger' : 'bg-white text-text-muted'">
                  {{ form.channel === 'in_app' ? 'Portal only' : form.channel === 'email' ? 'Email queue' : 'Email + portal' }}
                </span>
              </div>
              <p v-if="preview.error" class="text-danger text-xs mt-2">{{ preview.error }}</p>
              <div v-else-if="preview.sample.length" class="mt-3 space-y-1.5">
                <div v-for="student in preview.sample" :key="student.id" class="flex items-center justify-between gap-3 text-xs">
                  <span class="font-semibold text-text-main truncate">{{ student.name }}</span>
                  <span class="text-text-muted truncate">{{ student.email }}</span>
                </div>
              </div>
              <p v-else-if="preview.count === 0" class="text-danger text-xs mt-2">No student will receive this broadcast with the selected filters.</p>
            </div>

            <!-- Preview box -->
            <div v-if="form.title || form.message" class="bg-gray-50 border border-gray-200 rounded-xl p-4">
              <p class="text-text-muted text-[10px] font-semibold uppercase tracking-wider mb-2">Preview</p>
              <p class="text-text-main text-xs font-bold mb-1">{{ form.title || 'Subject…' }}</p>
              <p class="text-text-muted text-xs whitespace-pre-line leading-relaxed">{{ form.message || 'Message…' }}</p>
            </div>

            <button type="submit" :disabled="form.processing || preview.count === 0"
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
                    <span
                      v-for="chip in filterChips(log.audience_filters)"
                      :key="chip"
                      class="font-semibold px-1.5 py-0.5 rounded bg-royal/10 text-royal"
                    >
                      {{ chip }}
                    </span>
                    <span class="flex items-center gap-1">
                      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                      </svg>
                      <span class="font-number font-semibold text-text-main">{{ log.recipient_count }}</span> recipients
                    </span>
                    <span v-if="log.sent_by">by {{ log.sent_by.name }}</span>
                  </div>

                  <div v-if="log.channel !== 'in_app'" class="flex items-center gap-2 flex-wrap mt-2 text-[10px]">
                    <span class="font-semibold px-1.5 py-0.5 rounded bg-success/10 text-success">
                      Sent {{ log.email_sent_count || 0 }}
                    </span>
                    <span class="font-semibold px-1.5 py-0.5 rounded bg-gold/10 text-gold-dark">
                      Queued {{ log.email_queued_count || 0 }}
                    </span>
                    <span v-if="log.email_failed_count" class="font-semibold px-1.5 py-0.5 rounded bg-danger/10 text-danger">
                      Failed {{ log.email_failed_count }}
                    </span>
                    <span v-if="log.email_skipped_count" class="font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-text-muted">
                      Skipped {{ log.email_skipped_count }}
                    </span>
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
import { ref, watch } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  logs:        Object,
  exams:       Array,
  classLevels: Array,
  states:      Array,
  stats:       Object,
});

const form = useForm({
  title:          '',
  message:        '',
  channel:        'in_app',
  recipient_mode: 'filters',
  selected_user_ids: [],
  audience:       'all',
  exam_id:        '',
  class_level_id: '',
  student_status: 'active',
  payment_status: 'all',
  enrollment_status: 'all',
  state:          '',
  search:         '',
});

const selectedStudents = ref([]);
const studentOptions = ref([]);
const studentSearch = ref('');
const studentSearchLoading = ref(false);
let studentSearchTimer = null;

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

const paymentStatuses = [
  { value: 'all',      label: 'Any' },
  { value: 'paid',     label: 'Paid' },
  { value: 'unpaid',   label: 'Unpaid' },
  { value: 'pending',  label: 'Pending' },
  { value: 'failed',   label: 'Failed' },
  { value: 'refunded', label: 'Refunded' },
];

const preview = ref({
  count:   null,
  sample:  [],
  loading: false,
  error:   '',
});

let previewTimer = null;

const previewPayload = () => ({
  title:             form.title,
  message:           form.message,
  channel:           form.channel,
  recipient_mode:    form.recipient_mode,
  selected_user_ids: form.selected_user_ids,
  audience:          form.audience,
  exam_id:           form.exam_id,
  class_level_id:    form.class_level_id,
  student_status:    form.student_status,
  payment_status:    form.payment_status,
  enrollment_status: form.enrollment_status,
  state:             form.state,
  search:            form.search,
});

const schedulePreview = () => {
  clearTimeout(previewTimer);
  previewTimer = setTimeout(loadPreview, 250);
};

const loadPreview = async () => {
  preview.value.loading = true;
  preview.value.error = '';

  try {
    const { data } = await window.axios.post(route('admin.notifications.preview'), previewPayload());
    preview.value.count = data.count;
    preview.value.sample = data.sample || [];
  } catch (error) {
    preview.value.count = null;
    preview.value.sample = [];
    preview.value.error = 'Unable to calculate recipients for the current filters.';
  } finally {
    preview.value.loading = false;
  }
};

const loadStudentOptions = async () => {
  studentSearchLoading.value = true;

  try {
    const { data } = await window.axios.get(route('admin.notifications.students'), {
      params: { search: studentSearch.value },
    });
    studentOptions.value = data.students || [];
  } finally {
    studentSearchLoading.value = false;
  }
};

const scheduleStudentSearch = () => {
  clearTimeout(studentSearchTimer);
  studentSearchTimer = setTimeout(loadStudentOptions, 250);
};

const syncSelectedIds = () => {
  form.selected_user_ids = selectedStudents.value.map((student) => student.id);
};

const isSelected = (id) => selectedStudents.value.some((student) => student.id === id);

const addStudent = (student) => {
  if (isSelected(student.id)) return;
  selectedStudents.value.push(student);
  syncSelectedIds();
};

const removeStudent = (id) => {
  selectedStudents.value = selectedStudents.value.filter((student) => student.id !== id);
  syncSelectedIds();
};

const toggleStudent = (student) => {
  if (isSelected(student.id)) {
    removeStudent(student.id);
  } else {
    addStudent(student);
  }
};

const switchToSelectedMode = () => {
  form.recipient_mode = 'selected';
  form.audience = 'all';
  form.exam_id = '';
  form.class_level_id = '';
};

watch(
  () => [
    form.channel,
    form.recipient_mode,
    (form.selected_user_ids || []).join(','),
    form.audience,
    form.exam_id,
    form.class_level_id,
    form.student_status,
    form.payment_status,
    form.enrollment_status,
    form.state,
    form.search,
  ],
  schedulePreview,
  { immediate: true }
);

watch(studentSearch, scheduleStudentSearch, { immediate: true });

const submit = () => {
  const count = preview.value.count ?? 0;

  if (count === 0) return;

  if (! window.confirm(`Send this broadcast to ${count.toLocaleString()} matched student(s)?`)) {
    return;
  }

  form.post(route('admin.notifications.send'), {
    onSuccess: () => {
      form.reset();
      selectedStudents.value = [];
      syncSelectedIds();
      schedulePreview();
    },
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
  if (log.audience_filters?.recipient_mode === 'selected') return 'Selected students';
  if (log.audience === 'exam')  return log.exam?.name    ? `Exam: ${log.exam.name}` : 'By Exam';
  if (log.audience === 'class') return log.class_level?.label ? `Class: ${log.class_level.label}` : 'By Class';
  return 'All Students';
};
const audienceClass = (a) => ({
  all:   'bg-gray-100 text-text-muted',
  exam:  'bg-purple-100 text-purple-600',
  class: 'bg-amber-100 text-amber-600',
}[a] || '');

const filterChips = (filters = {}) => {
  if (!filters || Array.isArray(filters)) return [];

  const labels = {
    student_status: {
      inactive: 'Inactive accounts',
      all: 'All accounts',
    },
    payment_status: {
      paid: 'Paid',
      unpaid: 'Unpaid',
      pending: 'Payment pending',
      failed: 'Payment failed',
      refunded: 'Refunded',
    },
    enrollment_status: {
      enrolled: 'Enrolled',
      not_enrolled: 'Not enrolled',
      cancelled: 'Cancelled access',
    },
  };

  return [
    filters.recipient_mode === 'selected' ? `${filters.selected_user_ids?.length || 0} picked` : null,
    labels.student_status?.[filters.student_status],
    labels.payment_status?.[filters.payment_status],
    labels.enrollment_status?.[filters.enrollment_status],
    filters.state ? `State: ${filters.state}` : null,
    filters.search ? `Search: ${filters.search}` : null,
  ].filter(Boolean);
};

// Delete
const deleteTarget  = ref(null);
const openDelete    = (log) => { deleteTarget.value = log; };
const confirmDelete = () => {
  router.delete(route('admin.notifications.destroy', deleteTarget.value.id), {
    onFinish: () => { deleteTarget.value = null; },
  });
};
</script>
