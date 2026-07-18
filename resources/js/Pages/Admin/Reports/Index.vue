<template>
  <AdminLayout title="Student Reports" subtitle="Filter, review and export student enrollment and payment data">
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 mb-6">
      <div>
        <p class="text-sm text-text-muted">
          <span class="font-number font-bold text-primary">{{ summary.matched.toLocaleString() }}</span>
          matching {{ summary.matched === 1 ? 'student' : 'students' }}
        </p>
        <p v-if="hasFilters" class="text-xs text-text-muted mt-1">Exports use the same filters and include every matching row.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a
          :href="excelUrl"
          :aria-disabled="!canExportExcel"
          :title="canExportExcel ? 'Export filtered students as an Excel workbook' : `Excel exports support up to ${exportLimits.excel.toLocaleString()} rows`"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold border transition-colors"
          :class="canExportExcel ? 'bg-white border-success/30 text-success hover:bg-success/5' : 'bg-gray-100 border-gray-200 text-gray-400 pointer-events-none'"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 19h14"/>
          </svg>
          Export Excel
        </a>
        <a
          :href="pdfUrl"
          :aria-disabled="!canExportPdf"
          :title="canExportPdf ? 'Export filtered students as a PDF report' : `PDF exports support up to ${exportLimits.pdf.toLocaleString()} rows; narrow the filters first`"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold border transition-colors"
          :class="canExportPdf ? 'bg-white border-danger/30 text-danger hover:bg-danger/5' : 'bg-gray-100 border-gray-200 text-gray-400 pointer-events-none'"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v14H7a2 2 0 01-2-2V5a2 2 0 012-2zm7 0v5h5M9 13h6m-6 4h4"/>
          </svg>
          Export PDF
        </a>
      </div>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-5 gap-3 mb-6">
      <div v-for="stat in statCards" :key="stat.label" class="bg-white border border-gray-100 shadow-sm rounded-lg p-4 min-w-0">
        <div class="flex items-center gap-2 mb-2">
          <span class="w-2 h-2 rounded-full shrink-0" :class="stat.dot"></span>
          <p class="text-[11px] font-semibold uppercase text-text-muted">{{ stat.label }}</p>
        </div>
        <p class="font-number text-2xl font-bold truncate" :class="stat.color">{{ stat.value }}</p>
      </div>
    </div>

    <section class="bg-white border border-gray-100 shadow-sm rounded-lg mb-5" aria-labelledby="report-filters-heading">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
        <div>
          <h2 id="report-filters-heading" class="font-heading font-bold text-text-main text-sm">Report filters</h2>
          <p class="text-xs text-text-muted mt-0.5">Course-scoped enrollment and payment filters combine with student details.</p>
        </div>
        <button v-if="hasFilters" type="button" @click="clearFilters" class="text-xs font-semibold text-danger hover:text-red-700">Clear all</button>
      </div>

      <form class="p-5" @submit.prevent="applyFilters">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
          <label class="xl:col-span-2 block">
            <span class="filter-label">Search student</span>
            <div class="relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <input v-model="form.search" type="search" placeholder="Name, email, phone, school or city" class="filter-control pl-9" />
            </div>
          </label>

          <label class="block">
            <span class="filter-label">Student class</span>
            <select v-model="form.class_level_id" class="filter-control">
              <option value="">All classes</option>
              <option v-for="level in classLevels" :key="level.id" :value="String(level.id)">{{ level.label }}</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">State</span>
            <select v-model="form.state" class="filter-control">
              <option value="">All states</option>
              <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Subject</span>
            <select v-model="form.subject_id" class="filter-control" @change="syncExamToSubject">
              <option value="">All subjects</option>
              <option v-for="subject in subjects" :key="subject.id" :value="String(subject.id)">{{ subject.name }}</option>
            </select>
          </label>

          <label class="block xl:col-span-2">
            <span class="filter-label">Olympiad / course</span>
            <select v-model="form.exam_id" class="filter-control">
              <option value="">{{ form.subject_id ? 'All olympiads in subject' : 'All olympiads' }}</option>
              <option v-for="exam in availableExams" :key="exam.id" :value="String(exam.id)">
                {{ exam.name }} ({{ exam.exam_code }}) · {{ exam.class_level?.label || 'No class' }}
              </option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Enrollment</span>
            <select v-model="form.enrollment_status" class="filter-control">
              <option value="">Any enrollment</option>
              <option value="enrolled">Enrolled</option>
              <option value="not_enrolled">Not enrolled</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Payment</span>
            <select v-model="form.payment_status" class="filter-control">
              <option value="">Any payment status</option>
              <option value="paid">Paid</option>
              <option value="unpaid">Unpaid (no successful payment)</option>
              <option value="pending">Pending payment</option>
              <option value="failed">Failed payment</option>
              <option value="refunded">Refunded payment</option>
              <option value="no_payments">No payment records</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Account</span>
            <select v-model="form.account_status" class="filter-control">
              <option value="">Any account status</option>
              <option value="active">Active</option>
              <option value="inactive">Disabled</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Registered from</span>
            <input v-model="form.registered_from" type="date" class="filter-control" />
          </label>

          <label class="block">
            <span class="filter-label">Registered to</span>
            <input v-model="form.registered_to" type="date" :min="form.registered_from || undefined" class="filter-control" />
          </label>

          <label class="block">
            <span class="filter-label">Paid from</span>
            <input v-model="form.paid_from" type="date" class="filter-control" />
          </label>

          <label class="block">
            <span class="filter-label">Paid to</span>
            <input v-model="form.paid_to" type="date" :min="form.paid_from || undefined" class="filter-control" />
          </label>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mt-5 pt-4 border-t border-gray-100">
          <div class="flex flex-wrap gap-3">
            <label class="block">
              <span class="filter-label">Sort by</span>
              <select v-model="form.sort" class="filter-control min-w-44">
                <option value="registered_at">Registration date</option>
                <option value="name">Student name</option>
                <option value="paid_total">Lifetime paid</option>
                <option value="enrollments">Enrollment count</option>
              </select>
            </label>
            <label class="block">
              <span class="filter-label">Order</span>
              <select v-model="form.direction" class="filter-control min-w-32">
                <option value="desc">Descending</option>
                <option value="asc">Ascending</option>
              </select>
            </label>
            <label class="block">
              <span class="filter-label">Rows</span>
              <select v-model="form.per_page" class="filter-control min-w-24">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </label>
          </div>
          <button type="submit" class="inline-flex items-center justify-center gap-2 bg-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-primary-light transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10m-7 7h4"/>
            </svg>
            Apply filters
          </button>
        </div>
      </form>
    </section>

    <div class="bg-white border border-gray-100 shadow-sm rounded-lg overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-heading font-bold text-text-main text-sm">Student data</h2>
        <span class="text-xs text-text-muted">{{ students.total.toLocaleString() }} records</span>
      </div>

      <div v-if="students.data.length === 0" class="py-20 px-5 text-center">
        <div class="w-12 h-12 bg-primary/10 text-primary rounded-lg flex items-center justify-center mx-auto mb-4">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18v4l-7 7v5l-4-2v-3L3 8V4z"/>
          </svg>
        </div>
        <p class="font-heading font-bold text-text-main">No students match this report</p>
        <p class="text-sm text-text-muted mt-1">Adjust or clear the filters to broaden the result.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[1180px] text-sm">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="table-head w-12">#</th>
              <th class="table-head">Student</th>
              <th class="table-head">Contact</th>
              <th class="table-head">Class / Location</th>
              <th class="table-head">Active olympiads</th>
              <th class="table-head">Payment</th>
              <th class="table-head">Account</th>
              <th class="table-head">Joined</th>
              <th class="table-head text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(student, index) in students.data" :key="student.id" class="hover:bg-gray-50/70 transition-colors align-top">
              <td class="table-cell text-xs text-text-muted font-number">{{ students.from + index }}</td>
              <td class="table-cell">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-heading font-bold shrink-0">{{ initials(student.name) }}</div>
                  <div class="min-w-0">
                    <p class="font-semibold text-text-main max-w-48 truncate">{{ student.name }}</p>
                    <p class="text-[11px] text-text-muted font-number">ID #{{ student.id }}</p>
                  </div>
                </div>
              </td>
              <td class="table-cell">
                <p class="text-xs text-text-main max-w-52 truncate">{{ student.email }}</p>
                <p class="text-xs text-text-muted mt-1">{{ student.phone || 'No phone' }}</p>
              </td>
              <td class="table-cell">
                <p class="text-xs font-semibold text-text-main">{{ student.class || 'No class' }}</p>
                <p class="text-xs text-text-muted mt-1 max-w-44 truncate">{{ location(student) }}</p>
                <p v-if="student.school" class="text-[11px] text-text-muted mt-0.5 max-w-44 truncate">{{ student.school }}</p>
              </td>
              <td class="table-cell max-w-72">
                <div v-if="student.olympiads.length" class="flex flex-wrap gap-1.5">
                  <span v-for="exam in student.olympiads.slice(0, 2)" :key="exam.id" class="inline-flex max-w-52 px-2 py-1 rounded bg-royal/10 text-royal text-[11px] font-semibold truncate" :title="`${exam.name}${exam.subject ? ` · ${exam.subject}` : ''}`">
                    {{ exam.name }}
                  </span>
                  <span v-if="student.olympiads.length > 2" class="inline-flex px-2 py-1 rounded bg-gray-100 text-text-muted text-[11px] font-semibold">+{{ student.olympiads.length - 2 }}</span>
                </div>
                <span v-else class="text-xs text-text-muted">Not enrolled</span>
              </td>
              <td class="table-cell">
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[11px] font-semibold" :class="paymentClass(student.payment_label)">
                  <span class="w-1.5 h-1.5 rounded-full bg-current"></span>{{ student.payment_label }}
                </span>
                <p class="font-number text-xs font-bold text-text-main mt-1.5">{{ money(student.paid_total) }}</p>
                <p class="text-[10px] text-text-muted">lifetime paid</p>
              </td>
              <td class="table-cell">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold" :class="student.is_active ? 'text-success' : 'text-danger'">
                  <span class="w-1.5 h-1.5 rounded-full bg-current"></span>{{ student.is_active ? 'Active' : 'Disabled' }}
                </span>
              </td>
              <td class="table-cell text-xs text-text-muted font-number whitespace-nowrap">{{ date(student.registered_at) }}</td>
              <td class="table-cell text-right">
                <Link :href="route('admin.users.show', student.id)" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-accent transition-colors">
                  View
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="students.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <p class="text-xs text-text-muted">Showing {{ students.from }}-{{ students.to }} of {{ students.total.toLocaleString() }}</p>
        <div class="flex flex-wrap gap-1">
          <Link v-for="link in students.links" :key="link.label" :href="link.url || '#'" preserve-scroll
            :class="['px-3 py-1.5 rounded text-xs font-semibold transition-colors', link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100', !link.url ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  students: Object,
  classLevels: Array,
  subjects: Array,
  exams: Array,
  states: Array,
  filters: Object,
  summary: Object,
  exportLimits: Object,
});

const defaults = {
  search: '', class_level_id: '', subject_id: '', exam_id: '', state: '',
  account_status: '', enrollment_status: '', payment_status: '',
  registered_from: '', registered_to: '', paid_from: '', paid_to: '',
  sort: 'registered_at', direction: 'desc', per_page: '25',
};

const form = reactive(Object.fromEntries(
  Object.entries(defaults).map(([key, fallback]) => [key, props.filters[key] != null ? String(props.filters[key]) : fallback]),
));

const filterKeys = ['search', 'class_level_id', 'subject_id', 'exam_id', 'state', 'account_status', 'enrollment_status', 'payment_status', 'registered_from', 'registered_to', 'paid_from', 'paid_to'];
const hasFilters = computed(() => filterKeys.some(key => form[key]));
const query = computed(() => Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '')));
const availableExams = computed(() => form.subject_id ? props.exams.filter(exam => String(exam.subject_id) === form.subject_id) : props.exams);
const exportQuery = computed(() => new URLSearchParams(query.value).toString());
const excelUrl = computed(() => `${route('admin.reports.excel')}?${exportQuery.value}`);
const pdfUrl = computed(() => `${route('admin.reports.pdf')}?${exportQuery.value}`);
const canExportExcel = computed(() => props.summary.matched <= props.exportLimits.excel);
const canExportPdf = computed(() => props.summary.matched <= props.exportLimits.pdf);

const statCards = computed(() => [
  { label: 'Matched', value: props.summary.matched.toLocaleString(), color: 'text-primary', dot: 'bg-primary' },
  { label: 'Paid', value: props.summary.paid.toLocaleString(), color: 'text-success', dot: 'bg-success' },
  { label: 'Unpaid', value: props.summary.unpaid.toLocaleString(), color: 'text-accent', dot: 'bg-accent' },
  { label: 'Enrolled', value: props.summary.enrolled.toLocaleString(), color: 'text-royal', dot: 'bg-royal' },
  { label: 'Collected', value: money(props.summary.collected), color: 'text-gold-dark', dot: 'bg-gold' },
]);

const applyFilters = () => router.get(route('admin.reports.index'), query.value, { preserveState: true, preserveScroll: true, replace: true });
const clearFilters = () => {
  Object.assign(form, defaults);
  applyFilters();
};
const syncExamToSubject = () => {
  if (form.exam_id && !availableExams.value.some(exam => String(exam.id) === form.exam_id)) form.exam_id = '';
};

const initials = name => name?.split(/\s+/).map(word => word[0]).slice(0, 2).join('').toUpperCase() || '?';
const location = student => [student.city, student.state].filter(Boolean).join(', ') || 'No location';
const date = value => value ? new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
const money = value => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(Number(value || 0));
const paymentClass = label => ({
  Paid: 'bg-success/10 text-success',
  Pending: 'bg-gold/10 text-gold-dark',
  Unpaid: 'bg-danger/10 text-danger',
}[label] || 'bg-gray-100 text-text-muted');
</script>

<style scoped>
.filter-label { display: block; margin-bottom: 0.375rem; color: #5B6373; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; }
.filter-control { width: 100%; min-height: 2.625rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; background: #f9fafb; padding: 0.625rem 0.75rem; color: #0A1024; font-size: 0.8125rem; }
.filter-control:focus { border-color: #131C3D; outline: none; box-shadow: 0 0 0 2px rgba(19, 28, 61, 0.1); }
.table-head { padding: 0.75rem 1rem; color: #5B6373; font-size: 0.6875rem; font-weight: 600; text-align: left; text-transform: uppercase; white-space: nowrap; }
.table-cell { padding: 1rem; }
</style>
