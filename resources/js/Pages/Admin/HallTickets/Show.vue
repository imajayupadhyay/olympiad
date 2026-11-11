<template>
  <AdminLayout :title="`Hall Ticket · ${student.name}`" subtitle="Check the documents against the submitted details, then approve or reject">
    <div v-if="flash.success" class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 bg-red-50 border border-red-200 text-danger text-sm px-4 py-3 rounded-xl">{{ flash.error }}</div>

    <!-- action bar -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5 flex flex-wrap items-center gap-3">
      <Link :href="route('admin.hall-tickets.index')" class="text-sm font-semibold text-text-muted hover:text-primary">← All hall tickets</Link>
      <span class="hidden sm:block w-px h-6 bg-gray-200"></span>
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <p class="font-heading font-bold text-text-main truncate">{{ student.name }}</p>
          <span class="pill" :class="pillCls">{{ review.status_label }}</span>
          <span v-if="ticket.header.roll_number" class="font-number text-sm font-semibold text-text-main">Roll No. {{ ticket.header.roll_number }}</span>
        </div>
        <p class="text-xs text-text-muted">
          Submitted {{ review.submitted_at }}<template v-if="review.submission_count > 1"> · submission #{{ review.submission_count }}</template>
          <template v-if="review.reviewer"> · reviewed by {{ review.reviewer }} on {{ review.reviewed_at }}</template>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a v-if="review.status === 'approved'" :href="route('admin.hall-tickets.print', ticketId)" class="btn-ink">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          Print Hall Ticket
        </a>
        <template v-if="canWrite">
          <button v-if="review.status !== 'rejected'" type="button" class="btn-outline-danger" @click="rejectOpen = true">
            {{ review.status === 'approved' ? 'Revoke approval' : 'Reject' }}
          </button>
          <button v-if="review.status === 'pending'" type="button" class="btn-success" :disabled="busy" @click="approve">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Approve hall ticket
          </button>
        </template>
      </div>
    </div>

    <div v-if="review.status === 'rejected' && review.rejection_reason" class="mb-5 bg-red-50 border border-red-200 rounded-2xl px-4 py-3 text-sm">
      <p class="font-semibold text-danger">Rejected: waiting for the student to resubmit</p>
      <p class="text-text-main mt-0.5">“{{ review.rejection_reason }}”</p>
    </div>
    <div v-else-if="review.status === 'pending' && review.missing.length" class="mb-5 bg-amber-50 border border-amber-200 rounded-2xl px-4 py-3 text-sm text-amber-800">
      Profile is {{ review.percent }}% complete. Not filled: {{ review.missing.join(', ') }}.
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">
      <!-- left: verification -->
      <div class="xl:col-span-5 space-y-5">
        <section class="card">
          <div class="flex items-center justify-between mb-3">
            <h3 class="card-h">Identity check</h3>
            <span class="text-[11px] text-text-muted">Click a photo to enlarge</span>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <figure v-for="d in photoDocs" :key="d.type" class="m-0">
              <button type="button" class="doc-frame aspect-[3/4]" :disabled="!d.url" @click="zoom(d)">
                <img v-if="d.url" :src="d.url" :alt="d.label" class="w-full h-full object-cover" />
                <span v-else class="text-xs text-danger font-semibold">Missing</span>
              </button>
              <figcaption class="text-xs font-semibold text-text-main mt-1.5 text-center">{{ d.label }}</figcaption>
            </figure>
          </div>
          <p class="text-[11px] text-text-muted mt-3">Make sure the live photo and the passport photo show the same student, and that the face is clearly visible.</p>
        </section>

        <section class="card">
          <h3 class="card-h mb-3">School ID card</h3>
          <template v-if="idCard.url">
            <a v-if="idCard.is_pdf" :href="idCard.url" target="_blank" rel="noopener" class="doc-frame aspect-[16/10] flex-col gap-2 text-primary font-semibold text-sm">
              <svg class="w-10 h-10 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
              Open PDF in a new tab
            </a>
            <button v-else type="button" class="doc-frame aspect-[16/10]" @click="zoom(idCard)">
              <img :src="idCard.url" alt="School ID card" class="w-full h-full object-contain bg-gray-50" />
            </button>
          </template>
          <div v-else class="doc-frame aspect-[16/10]"><span class="text-xs text-danger font-semibold">Missing</span></div>
          <p class="text-[11px] text-text-muted mt-3">Compare the name, school and photo on the card with the details below.</p>
        </section>

        <section class="card">
          <h3 class="card-h mb-3">Submitted details</h3>
          <dl class="divide-y divide-gray-50 text-sm">
            <div v-for="row in detailRows" :key="row.label" class="flex justify-between gap-4 py-2">
              <dt class="text-text-muted shrink-0">{{ row.label }}</dt>
              <dd class="text-text-main text-right font-medium break-words min-w-0" :class="{ 'font-number': row.mono }">
                {{ row.value || '—' }}
              </dd>
            </div>
          </dl>
          <Link :href="route('admin.users.show', student.id)" class="inline-block mt-3 text-xs font-semibold text-primary hover:underline">Open student record →</Link>
        </section>
      </div>

      <!-- right: the ticket -->
      <div class="xl:col-span-7">
        <section class="card !p-3 sm:!p-4 bg-bg">
          <div class="flex items-center justify-between mb-3 px-1">
            <h3 class="card-h">Hall ticket {{ review.status === 'approved' ? '' : 'preview' }}</h3>
            <span class="text-[11px] text-text-muted">What the student prints</span>
          </div>
          <ScaledHallTicket :ticket="ticket" />
        </section>
      </div>
    </div>

    <!-- lightbox -->
    <Teleport to="body">
      <div v-if="zoomed" class="fixed inset-0 z-50 bg-black/80 grid place-items-center p-4" @click="zoomed = null">
        <figure class="m-0 max-w-3xl w-full" @click.stop>
          <img :src="zoomed.url" :alt="zoomed.label" class="max-h-[80vh] w-auto mx-auto rounded-xl bg-white" />
          <figcaption class="flex items-center justify-between mt-3 text-white text-sm">
            <span class="font-semibold">{{ zoomed.label }}</span>
            <span class="flex gap-3">
              <a :href="zoomed.url" target="_blank" rel="noopener" class="underline">Open original</a>
              <button type="button" class="underline" @click="zoomed = null">Close</button>
            </span>
          </figcaption>
        </figure>
      </div>
    </Teleport>

    <!-- reject dialog -->
    <Teleport to="body">
      <div v-if="rejectOpen" class="fixed inset-0 z-50 bg-black/50 grid place-items-center p-4" @click.self="rejectOpen = false">
        <form class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-5" role="dialog" aria-modal="true" aria-labelledby="rej-title" @submit.prevent="reject">
          <h3 id="rej-title" class="font-heading font-bold text-lg text-text-main">{{ review.status === 'approved' ? 'Revoke this approval?' : 'Reject this hall ticket?' }}</h3>
          <p class="text-sm text-text-muted mt-1">The student sees this reason, gets a notification, and can correct their profile and resubmit.<template v-if="review.status === 'approved'"> Their roll number is kept.</template></p>
          <div class="flex flex-wrap gap-1.5 mt-4">
            <button v-for="q in quickReasons" :key="q" type="button" class="chip" @click="rejectForm.reason = q">{{ q }}</button>
          </div>
          <label for="rej-reason" class="block text-xs font-semibold text-text-main mt-4 mb-1.5">Reason</label>
          <textarea id="rej-reason" v-model="rejectForm.reason" rows="3" maxlength="500" class="field" placeholder="Tell the student exactly what to fix"></textarea>
          <p v-if="rejectForm.errors.reason" class="text-xs text-danger mt-1">{{ rejectForm.errors.reason }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button type="button" class="btn-ghost" @click="rejectOpen = false">Cancel</button>
            <button type="submit" class="btn-danger" :disabled="rejectForm.processing || rejectForm.reason.trim().length < 5">
              {{ rejectForm.processing ? 'Saving…' : (review.status === 'approved' ? 'Revoke approval' : 'Reject') }}
            </button>
          </div>
        </form>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ScaledHallTicket from '@/Components/Shared/ScaledHallTicket.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({
  ticketId: { type: Number, required: true },
  ticket: { type: Object, required: true },
  review: { type: Object, required: true },
  student: { type: Object, required: true },
  documents: { type: Array, required: true },
  details: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const canWrite = computed(() => page.props.admin_is_super || page.props.admin_permissions?.hall_tickets?.write);
const pillCls = computed(() => ({ pending: 'bg-amber-100 text-amber-800', approved: 'bg-green-100 text-success', rejected: 'bg-red-100 text-danger' }[props.review.status] ?? 'bg-gray-100 text-text-muted'));

const doc = (type) => props.documents.find((d) => d.type === type) ?? { label: type, url: null };
const photoDocs = computed(() => [doc('live_photo'), doc('passport_photo')]);
const idCard = computed(() => doc('school_id_card'));

const detailRows = computed(() => [
  ...props.details,
  { label: 'Email verified', value: props.student.email_verified ? 'Yes' : 'No' },
  { label: 'Mobile verified', value: props.student.phone_verified ? 'Yes' : 'No' },
  { label: 'Registered on', value: props.student.registered_at },
  { label: 'Olympiads on ticket', value: String(props.ticket.schedule?.rows?.length ?? 0) },
]);

const zoomed = ref(null);
const zoom = (d) => { if (d.url && !d.is_pdf) zoomed.value = d; };

const busy = ref(false);
const approve = async () => {
  if (!(await confirmDialog({
    title: 'Approve this hall ticket?',
    message: `A roll number will be issued and ${props.student.name} will be able to print the hall ticket. Their verified details stay locked.`,
    confirmText: 'Approve',
    tone: 'primary',
  }))) return;
  router.post(route('admin.hall-tickets.approve', props.ticketId), {}, {
    preserveScroll: true,
    onStart: () => (busy.value = true),
    onFinish: () => (busy.value = false),
  });
};

const rejectOpen = ref(false);
const rejectForm = useForm({ reason: '' });
const quickReasons = [
  'Passport photo is unclear or not a passport-size photo.',
  'Live photo does not match the passport photo.',
  'School ID card is unreadable or does not belong to the student.',
  'Name or school does not match the school ID card.',
  'School coordinator details are incorrect.',
];
const reject = () => {
  rejectForm.post(route('admin.hall-tickets.reject', props.ticketId), {
    preserveScroll: true,
    onSuccess: () => { rejectOpen.value = false; rejectForm.reset(); },
  });
};
</script>

<style scoped>
.card { @apply bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5; }
.card-h { @apply font-heading font-bold text-text-main text-base; }
.pill { @apply inline-block text-[11px] font-bold px-2 py-1 rounded-md whitespace-nowrap; }
.doc-frame { @apply w-full rounded-xl overflow-hidden border border-gray-200 bg-gray-50 grid place-items-center hover:border-primary transition-colors; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main; }
.chip { @apply text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-gray-200 text-text-main hover:border-primary hover:text-primary transition-colors text-left; }
.btn-success { @apply inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2.5 rounded-xl bg-success text-white hover:opacity-90 disabled:opacity-50; }
.btn-ink { @apply inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2.5 rounded-xl bg-primary text-white hover:bg-primary-light; }
.btn-outline-danger { @apply text-sm font-semibold px-4 py-2.5 rounded-xl border border-red-200 text-danger hover:bg-red-50; }
.btn-danger { @apply text-sm font-semibold px-4 py-2.5 rounded-xl bg-danger text-white hover:opacity-90 disabled:opacity-50; }
.btn-ghost { @apply text-sm font-semibold px-4 py-2.5 rounded-xl text-text-main hover:bg-gray-100; }
</style>
