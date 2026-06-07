<template>
  <AdminLayout :title="exam.name" subtitle="Certificate Management">

    <!-- Back + actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <Link :href="route('admin.certificates')"
            class="inline-flex items-center gap-2 text-text-muted hover:text-text-main text-sm font-semibold transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        All Certificates
      </Link>
      <button
        v-if="exam.template && stats.released > 0"
        @click="confirmGenerate = true"
        class="inline-flex items-center gap-2 bg-success text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-green-700 transition-colors shadow-sm"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Issue to All Students
      </button>
    </div>

    <!-- Exam meta -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5 flex items-center gap-3 flex-wrap">
      <span v-if="exam.subject" class="text-sm font-semibold px-3 py-1 rounded-lg"
            :style="{ background: exam.subject.color + '20', color: exam.subject.color }">
        {{ exam.subject.icon }} {{ exam.subject.name }}
      </span>
      <span class="text-sm font-semibold text-text-muted bg-gray-100 px-3 py-1 rounded-lg">{{ exam.class_level?.label }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Left: Template upload -->
      <div class="lg:col-span-1 space-y-5">

        <!-- Current template -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h3 class="font-heading font-bold text-text-main text-sm mb-4">Certificate Template</h3>

          <!-- Has template -->
          <div v-if="exam.template" class="mb-4">
            <div class="flex items-start gap-3 p-3 bg-success/5 border border-success/20 rounded-xl mb-3">
              <div class="w-10 h-10 rounded-lg bg-success/10 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-text-main text-xs font-semibold truncate">{{ exam.template.original_name }}</p>
                <p class="text-text-muted text-[10px] mt-0.5">{{ formatSize(exam.template.file_size) }} · {{ exam.template.mime_type }}</p>
              </div>
            </div>
            <div class="flex gap-2">
              <a :href="route('admin.certificates.download', exam.id)"
                 class="flex-1 flex items-center justify-center gap-1.5 bg-primary/5 hover:bg-primary/10 text-primary border border-primary/20 py-2 rounded-xl text-xs font-semibold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download
              </a>
              <button @click="confirmDeleteTemplate = true"
                      class="flex-1 flex items-center justify-center gap-1.5 bg-danger/5 hover:bg-danger/10 text-danger border border-danger/20 py-2 rounded-xl text-xs font-semibold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Remove
              </button>
            </div>
          </div>

          <!-- Upload zone -->
          <div>
            <p class="text-text-muted text-xs font-semibold mb-2">
              {{ exam.template ? 'Replace Template' : 'Upload Template' }}
            </p>
            <label
              class="block border-2 border-dashed rounded-xl p-5 text-center cursor-pointer transition-colors"
              :class="dragOver ? 'border-primary bg-primary/5' : 'border-gray-200 hover:border-primary/50'"
              @dragover.prevent="dragOver = true"
              @dragleave="dragOver = false"
              @drop.prevent="handleDrop"
            >
              <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="handleFile" />
              <svg class="w-8 h-8 text-text-muted mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
              </svg>
              <p v-if="selectedFile" class="text-primary text-xs font-semibold truncate">{{ selectedFile.name }}</p>
              <p v-else class="text-text-muted text-xs">Drop PDF/image or <span class="text-primary font-semibold">browse</span></p>
              <p class="text-text-muted text-[10px] mt-1">Max 10 MB · PDF, JPG, PNG</p>
            </label>

            <button v-if="selectedFile"
                    @click="uploadTemplate"
                    :disabled="uploading"
                    class="w-full mt-3 bg-primary text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60">
              {{ uploading ? 'Uploading…' : 'Upload Template' }}
            </button>
          </div>
        </div>

        <!-- Stats card -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h3 class="font-heading font-bold text-text-main text-sm mb-4">Summary</h3>
          <div class="space-y-3">
            <div class="flex justify-between items-center text-sm">
              <span class="text-text-muted">Released Results</span>
              <span class="font-number font-bold text-text-main">{{ stats.released }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
              <span class="text-text-muted">Certificates Issued</span>
              <span class="font-number font-bold text-success">{{ stats.certs_issued }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
              <span class="text-text-muted">Pending Issue</span>
              <span class="font-number font-bold" :class="pending > 0 ? 'text-amber-600' : 'text-text-muted'">
                {{ pending }}
              </span>
            </div>
            <div class="border-t border-gray-50 pt-3 flex justify-between items-center text-sm">
              <span class="text-text-muted">Template</span>
              <span class="text-xs font-semibold" :class="stats.has_template ? 'text-success' : 'text-danger'">
                {{ stats.has_template ? 'Uploaded ✓' : 'Missing' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Checklist -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h3 class="font-heading font-bold text-text-main text-sm mb-3">Checklist</h3>
          <div class="space-y-2.5">
            <div v-for="step in checklist" :key="step.label" class="flex items-center gap-2.5 text-xs">
              <div class="w-5 h-5 rounded-full flex items-center justify-center shrink-0"
                   :class="step.done ? 'bg-success/10' : 'bg-gray-100'">
                <svg v-if="step.done" class="w-3 h-3 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span v-else class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
              </div>
              <span :class="step.done ? 'text-text-main font-semibold' : 'text-text-muted'">{{ step.label }}</span>
            </div>
          </div>
        </div>

      </div>

      <!-- Right: Issued certificates -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-heading font-bold text-text-main text-sm">Issued Certificates</h3>
            <span class="bg-gray-100 text-text-muted text-xs font-semibold px-2.5 py-1 rounded-lg font-number">
              {{ stats.certs_issued }}
            </span>
          </div>

          <div v-if="studentCerts.data.length === 0" class="py-16 text-center">
            <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-text-muted text-sm">
              {{ !stats.has_template
                ? 'Upload a template first.'
                : stats.released === 0
                  ? 'No released results yet.'
                  : 'Click "Issue to All Students" to generate certificates.' }}
            </p>
          </div>

          <table v-else class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="text-left px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">#</th>
                <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Student</th>
                <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">School</th>
                <th class="text-left px-4 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Issued On</th>
                <th class="text-right px-5 py-3 text-text-muted text-xs font-semibold uppercase tracking-wider">Download</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="(c, i) in studentCerts.data" :key="c.id" class="hover:bg-gray-50/40 transition-colors">
                <td class="px-5 py-3.5 text-text-muted font-number text-xs">{{ studentCerts.from + i }}</td>
                <td class="px-4 py-3.5">
                  <p class="font-semibold text-text-main text-xs">{{ c.user?.name }}</p>
                  <p class="text-text-muted text-[10px]">{{ c.user?.email }}</p>
                </td>
                <td class="px-4 py-3.5 text-text-muted text-xs">{{ c.user?.school || '—' }}</td>
                <td class="px-4 py-3.5 text-text-muted text-xs font-number">
                  {{ c.generated_at ? formatDate(c.generated_at) : '—' }}
                </td>
                <td class="px-5 py-3.5 text-right">
                  <a :href="c.download_url" target="_blank"
                     class="text-primary hover:text-primary-light text-xs font-semibold px-2.5 py-1 rounded-lg hover:bg-primary/5 transition-colors">
                    Download
                  </a>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div v-if="studentCerts.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
            <p class="text-text-muted text-xs">Showing {{ studentCerts.from }}–{{ studentCerts.to }} of {{ studentCerts.total }}</p>
            <div class="flex gap-1">
              <Link
                v-for="link in studentCerts.links"
                :key="link.label"
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

    <!-- Generate confirm modal -->
    <div v-if="confirmGenerate" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-success/10 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Issue Certificates?</h3>
        <p class="text-text-muted text-sm mb-5">
          This will make the certificate template available to all <strong>{{ stats.released }}</strong> students with released results. They can download it from their portal.
        </p>
        <div class="flex gap-3">
          <button @click="confirmGenerate = false" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
          <button @click="doGenerate" class="flex-1 bg-success text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-green-700 transition-colors">Issue Now</button>
        </div>
      </div>
    </div>

    <!-- Delete template confirm -->
    <div v-if="confirmDeleteTemplate" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-danger/10 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Remove Template?</h3>
        <p class="text-text-muted text-sm mb-5">The uploaded certificate template will be deleted. Students who already received certificates will still have access.</p>
        <div class="flex gap-3">
          <button @click="confirmDeleteTemplate = false" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
          <button @click="doDeleteTemplate" class="flex-1 bg-danger text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-red-700 transition-colors">Remove</button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  exam:         Object,
  studentCerts: Object,
  stats:        Object,
});

const pending = computed(() => Math.max(0, props.stats.released - props.stats.certs_issued));

const checklist = computed(() => [
  { label: 'Exam published',         done: props.exam.status !== 'draft' },
  { label: 'Results processed',      done: props.stats.total_results > 0 },
  { label: 'Results released',       done: props.stats.released > 0 },
  { label: 'Template uploaded',      done: props.stats.has_template },
  { label: 'Certificates issued',    done: props.stats.certs_issued > 0 },
]);

// Upload
const fileInput    = ref(null);
const selectedFile = ref(null);
const dragOver     = ref(false);
const uploading    = ref(false);

const handleFile = (e) => { selectedFile.value = e.target.files[0] || null; };
const handleDrop = (e) => {
  dragOver.value = false;
  selectedFile.value = e.dataTransfer.files[0] || null;
};

const uploadTemplate = () => {
  if (!selectedFile.value) return;
  uploading.value = true;
  const form = useForm({ template: selectedFile.value });
  form.post(route('admin.certificates.upload', props.exam.id), {
    forceFormData: true,
    onFinish: () => {
      uploading.value   = false;
      selectedFile.value = null;
    },
  });
};

// Generate
const confirmGenerate = ref(false);
const doGenerate = () => {
  router.post(route('admin.certificates.generate', props.exam.id), {}, {
    onFinish: () => { confirmGenerate.value = false; },
  });
};

// Delete template
const confirmDeleteTemplate = ref(false);
const doDeleteTemplate = () => {
  router.delete(route('admin.certificates.template.delete', props.exam.id), {
    onFinish: () => { confirmDeleteTemplate.value = false; },
  });
};

const formatDate = (d) =>
  new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

const formatSize = (bytes) => {
  if (!bytes) return '';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
};
</script>
