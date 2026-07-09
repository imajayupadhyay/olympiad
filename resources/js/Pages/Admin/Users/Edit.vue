<template>
  <AdminLayout :title="`Edit — ${student.name}`" subtitle="Update student profile">

    <div class="max-w-3xl">

      <!-- Back -->
      <Link :href="route('admin.users.show', student.id)"
            class="inline-flex items-center gap-2 text-text-muted hover:text-text-main text-sm font-semibold mb-6 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to Profile
      </Link>

      <form @submit.prevent="submit" class="space-y-5">

        <!-- Basic Info -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h2 class="font-heading font-bold text-text-main text-sm mb-5 flex items-center gap-2">
            <span class="w-6 h-6 rounded-lg bg-primary/10 flex items-center justify-center text-primary text-xs font-bold">1</span>
            Basic Information
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Full Name <span class="text-danger">*</span></label>
              <input v-model="form.name" type="text"
                     class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition-colors"
                     :class="form.errors.name ? 'border-danger bg-danger/5' : 'border-gray-200'" />
              <p v-if="form.errors.name" class="text-danger text-xs mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Email Address <span class="text-danger">*</span></label>
              <input v-model="form.email" type="email"
                     class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition-colors"
                     :class="form.errors.email ? 'border-danger bg-danger/5' : 'border-gray-200'" />
              <p v-if="form.errors.email" class="text-danger text-xs mt-1">{{ form.errors.email }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Phone Number</label>
              <input v-model="form.phone" type="tel"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20" />
            </div>

            <!-- Password section -->
            <div class="sm:col-span-2">
              <div class="border border-dashed border-gray-200 rounded-xl p-4 bg-gray-50/50">
                <p class="text-xs font-semibold text-text-muted mb-3">Change Password <span class="font-normal">(leave blank to keep current)</span></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div class="relative">
                    <input v-model="form.password" :type="showPwd ? 'text' : 'password'" placeholder="New password"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary pr-10"
                           :class="form.errors.password ? 'border-danger' : ''" />
                    <button type="button" @click="showPwd = !showPwd" class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                    </button>
                    <p v-if="form.errors.password" class="text-danger text-xs mt-1">{{ form.errors.password }}</p>
                  </div>
                  <input v-model="form.password_confirmation" :type="showPwd ? 'text' : 'password'" placeholder="Confirm new password"
                         class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary" />
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Academic Info -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h2 class="font-heading font-bold text-text-main text-sm mb-5 flex items-center gap-2">
            <span class="w-6 h-6 rounded-lg bg-primary/10 flex items-center justify-center text-primary text-xs font-bold">2</span>
            Academic Information
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Class / Grade</label>
              <select v-model="form.class_level_id"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main">
                <option value="">Select Class</option>
                <option v-for="cl in classLevels" :key="cl.id" :value="cl.id">{{ cl.label }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Date of Birth</label>
              <input v-model="form.dob" type="date"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20" />
              <p v-if="form.errors.dob" class="text-danger text-xs mt-1">{{ form.errors.dob }}</p>
            </div>

            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">School Name</label>
              <input v-model="form.school" type="text"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20" />
            </div>

          </div>
        </div>

        <!-- Location -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h2 class="font-heading font-bold text-text-main text-sm mb-5 flex items-center gap-2">
            <span class="w-6 h-6 rounded-lg bg-primary/10 flex items-center justify-center text-primary text-xs font-bold">3</span>
            Location
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">City</label>
              <input v-model="form.city" type="text"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">State</label>
              <select v-model="form.state"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main">
                <option value="">Select State</option>
                <option v-for="s in states" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Status -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="font-heading font-bold text-text-main text-sm">Account Status</h2>
              <p class="text-text-muted text-xs mt-0.5">Disabled accounts cannot log in</p>
            </div>
            <button type="button" @click="form.is_active = !form.is_active"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                    :class="form.is_active ? 'bg-success' : 'bg-gray-300'">
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                    :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"/>
            </button>
          </div>
        </div>

        <!-- Olympiad Assignment -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-5">
            <div>
              <h2 class="font-heading font-bold text-text-main text-sm flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-primary/10 flex items-center justify-center text-primary text-xs font-bold">4</span>
                Olympiad Assignments
              </h2>
              <p class="text-text-muted text-xs mt-1">Assign a published olympiad directly to this student without checkout.</p>
            </div>
            <span class="bg-gray-100 text-text-muted text-xs font-semibold px-2.5 py-1 rounded-lg font-number w-fit">
              {{ activeEnrollments.length }} active
            </span>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-3">
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Published Olympiad</label>
              <select
                v-model="assignForm.exam_id"
                class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main"
                :class="assignForm.errors.exam_id ? 'border-danger bg-danger/5' : 'border-gray-200'"
              >
                <option value="">Select olympiad to assign</option>
                <option v-for="exam in availableExams" :key="exam.id" :value="exam.id">
                  {{ exam.name }} — {{ exam.class_level?.label || 'No class' }} — {{ exam.subject?.name || 'No subject' }}
                </option>
              </select>
              <p v-if="assignForm.errors.exam_id" class="text-danger text-xs mt-1">{{ assignForm.errors.exam_id }}</p>
              <p v-else-if="!availableExams.length" class="text-text-muted text-xs mt-1">No published olympiads are available to assign.</p>
            </div>
            <div class="flex items-end">
              <button
                type="button"
                @click="assignExam"
                :disabled="assignForm.processing || !assignForm.exam_id"
                class="w-full lg:w-auto bg-accent text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-accent-dark transition-colors disabled:opacity-60 shadow-sm"
              >
                {{ assignForm.processing ? 'Assigning…' : 'Assign Olympiad' }}
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Payment Reference</label>
              <input
                v-model="assignForm.manual_reference"
                type="text"
                class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary"
                :class="assignForm.errors.manual_reference ? 'border-danger bg-danger/5' : 'border-gray-200'"
                placeholder="UTR / receipt / transaction id"
              />
              <p v-if="assignForm.errors.manual_reference" class="text-danger text-xs mt-1">{{ assignForm.errors.manual_reference }}</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Admin Note</label>
              <input
                v-model="assignForm.manual_note"
                type="text"
                class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary"
                :class="assignForm.errors.manual_note ? 'border-danger bg-danger/5' : 'border-gray-200'"
                placeholder="Optional note"
              />
              <p v-if="assignForm.errors.manual_note" class="text-danger text-xs mt-1">{{ assignForm.errors.manual_note }}</p>
            </div>
          </div>

          <div class="mt-5">
            <h3 class="font-heading font-bold text-text-main text-xs uppercase tracking-wider mb-3">Current Associations</h3>

            <div v-if="enrollments.length" class="space-y-3">
              <div
                v-for="enrollment in enrollments"
                :key="enrollment.id"
                class="rounded-xl border border-gray-100 bg-gray-50/70 p-4"
              >
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                      <h4 class="font-heading font-bold text-text-main text-sm">{{ enrollment.exam?.name || 'Deleted exam' }}</h4>
                      <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="statusClass(enrollment.status)">
                        {{ enrollment.status.replace('_', ' ') }}
                      </span>
                      <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary">
                        {{ sourceLabel(enrollment.enrollment_source) }}
                      </span>
                    </div>
                    <p class="text-text-muted text-xs">
                      {{ enrollment.exam?.subject?.name || 'Subject not set' }}
                      <span class="mx-1">•</span>
                      {{ enrollment.exam?.class_level?.label || 'Class not set' }}
                      <span class="mx-1">•</span>
                      {{ enrollment.enrolled_at ? formatDate(enrollment.enrolled_at) : 'No date' }}
                    </p>
                  </div>

                  <button
                    v-if="enrollment.status === 'enrolled'"
                    type="button"
                    @click="cancelEnrollment(enrollment)"
                    class="text-danger border border-danger/20 px-3 py-2 rounded-xl text-xs font-semibold hover:bg-danger/5 transition-colors"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            </div>

            <div v-else class="rounded-xl border border-dashed border-gray-200 py-8 text-center text-text-muted text-sm">
              No olympiads assigned yet.
            </div>
          </div>
        </div>

        <!-- Submit -->
        <div class="flex items-center gap-3 pb-6">
          <button type="submit" :disabled="form.processing"
                  class="bg-primary text-white px-6 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60 shadow-sm">
            {{ form.processing ? 'Saving…' : 'Save Changes' }}
          </button>
          <Link :href="route('admin.users.show', student.id)"
                class="bg-gray-100 text-text-main px-6 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">
            Cancel
          </Link>
        </div>

      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  student:     Object,
  classLevels: Array,
  states:      Array,
  enrollments: { type: Array, default: () => [] },
  assignableExams: { type: Array, default: () => [] },
});

const showPwd = ref(false);
const activeEnrollments = computed(() => props.enrollments.filter((enrollment) => enrollment.status === 'enrolled'));
const activeExamIds = computed(() => new Set(activeEnrollments.value.map((enrollment) => enrollment.exam?.id).filter(Boolean)));
const availableExams = computed(() => props.assignableExams.filter((exam) => !activeExamIds.value.has(exam.id)));

const form = useForm({
  name:                  props.student.name,
  email:                 props.student.email,
  phone:                 props.student.phone        || '',
  password:              '',
  password_confirmation: '',
  class_level_id:        props.student.class_level_id || '',
  dob:                   props.student.dob ? props.student.dob.substring(0, 10) : '',
  school:                props.student.school  || '',
  city:                  props.student.city    || '',
  state:                 props.student.state   || '',
  is_active:             props.student.is_active,
});

const assignForm = useForm({
  exam_id: '',
  manual_reference: '',
  manual_note: '',
});

const submit = () => {
  form.put(route('admin.users.update', props.student.id));
};

const assignExam = () => {
  assignForm.post(route('admin.users.enrollments.store', props.student.id), {
    preserveScroll: true,
    onSuccess: () => assignForm.reset(),
  });
};

const cancelEnrollment = (enrollment) => {
  if (!confirm(`Cancel assignment for "${enrollment.exam?.name || 'this olympiad'}"?`)) return;

  router.patch(route('admin.users.enrollments.cancel', [props.student.id, enrollment.id]), {}, {
    preserveScroll: true,
  });
};

const formatDate = (d) =>
  new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

const sourceLabel = (source) => ({
  admin: 'Admin assigned',
  payment: 'Paid checkout',
  free: 'Free checkout',
  checkout: 'Checkout',
}[source] || 'Enrollment');

const statusClass = (status) => ({
  enrolled: 'bg-success/10 text-success',
  pending_payment: 'bg-amber-100 text-amber-700',
  cancelled: 'bg-danger/10 text-danger',
}[status] || 'bg-gray-100 text-text-muted');
</script>
