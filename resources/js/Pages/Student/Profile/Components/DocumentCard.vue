<script setup>
/**
 * One hall-ticket document (live photo, passport photo or school ID card): preview,
 * upload / capture, replace and remove. Uploads post to student.profile.documents.store,
 * where the server validates and re-encodes the file.
 */
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { confirmDialog } from '@/composables/useConfirm';
import LivePhotoCapture from './LivePhotoCapture.vue';

const props = defineProps({
    type: { type: String, required: true },
    doc: { type: Object, required: true },          // { label, uploaded, url, is_pdf }
    description: { type: String, default: '' },
    tips: { type: Array, default: () => [] },
    camera: { type: Boolean, default: false },       // live photo: camera only
    allowPdf: { type: Boolean, default: false },
    locked: { type: Boolean, default: false },
    maxMb: { type: Number, default: 5 },
});

const form = useForm({ file: null });
const input = ref(null);
const dragging = ref(false);
const localError = ref('');
const cameraOpen = ref(false);

const accept = computed(() => props.allowPdf ? 'image/jpeg,image/png,image/webp,application/pdf' : 'image/jpeg,image/png,image/webp');
const error = computed(() => localError.value || form.errors.file);

const upload = (file) => {
    localError.value = '';
    if (!file) return;
    const okType = accept.value.split(',').includes(file.type);
    if (!okType) { localError.value = props.allowPdf ? 'Upload a JPG, PNG, WebP or PDF file.' : 'Upload a JPG, PNG or WebP image.'; return; }
    if (file.size > props.maxMb * 1024 * 1024) { localError.value = `The file must be ${props.maxMb} MB or smaller.`; return; }
    form.file = file;
    form.post(route('student.profile.documents.store', props.type), {
        forceFormData: true,
        preserveScroll: true,
        errorBag: props.type,
        onFinish: () => { form.file = null; if (input.value) input.value.value = ''; },
    });
};

const onPick = (e) => upload(e.target.files?.[0]);
const onDrop = (e) => { dragging.value = false; if (!props.locked && !props.camera) upload(e.dataTransfer?.files?.[0]); };
const pick = () => (props.camera ? (cameraOpen.value = true) : input.value?.click());

const remove = async () => {
    if (!(await confirmDialog({
        title: `Remove your ${props.doc.label.toLowerCase()}?`,
        message: 'It is required for your hall ticket, so you will need to add it again before submitting.',
        confirmText: 'Remove',
        tone: 'danger',
    }))) return;
    router.delete(route('student.profile.documents.delete', props.type), { preserveScroll: true });
};
</script>

<template>
    <div class="doc" :class="{ done: doc.uploaded, drag: dragging, locked }"
         @dragover.prevent="dragging = !locked && !camera" @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
        <div class="doc-head">
            <span class="doc-ic"><slot name="icon" /></span>
            <strong class="doc-title">{{ doc.label }}</strong>
            <span class="badge" :class="doc.uploaded ? 'ok' : 'req'">{{ doc.uploaded ? 'Added' : 'Required' }}</span>
        </div>
        <p class="doc-desc">{{ description }}</p>

        <div class="doc-preview" :class="{ empty: !doc.uploaded }">
            <template v-if="doc.uploaded">
                <a v-if="doc.is_pdf" :href="doc.url" target="_blank" rel="noopener" class="pdf">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><text x="7" y="18" font-size="5.5" font-weight="700" fill="currentColor" stroke="none">PDF</text></svg>
                    Open PDF
                </a>
                <a v-else :href="doc.url" target="_blank" rel="noopener" class="thumb-link" :aria-label="`Open ${doc.label}`">
                    <img :src="doc.url" :alt="doc.label" class="thumb" />
                </a>
            </template>
            <button v-else type="button" class="drop" :disabled="locked || form.processing" @click="pick">
                <svg v-if="camera" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 7h3l2-2h8l2 2h3v12H3V7Z"/><circle cx="12" cy="13" r="3.5"/></svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                <span>{{ camera ? 'Open camera' : 'Click to upload or drag a file here' }}</span>
                <small>{{ camera ? 'Uses your device camera' : (allowPdf ? 'JPG, PNG, WebP or PDF' : 'JPG, PNG or WebP') + ` · up to ${maxMb} MB` }}</small>
            </button>
            <div v-if="form.processing" class="busy">
                <span class="spin"></span>
                Uploading{{ form.progress ? ` ${form.progress.percentage}%` : '…' }}
            </div>
        </div>

        <ul v-if="tips.length && !doc.uploaded" class="tips">
            <li v-for="t in tips" :key="t">{{ t }}</li>
        </ul>

        <p v-if="error" class="err" role="alert">{{ error }}</p>

        <div class="doc-actions">
            <template v-if="locked">
                <span class="lock-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    Locked for verification
                </span>
            </template>
            <template v-else-if="doc.uploaded">
                <button type="button" class="act" :disabled="form.processing" @click="pick">{{ camera ? 'Retake' : 'Replace' }}</button>
                <button type="button" class="act danger" :disabled="form.processing" @click="remove">Remove</button>
            </template>
            <button v-else type="button" class="act primary" :disabled="form.processing" @click="pick">{{ camera ? 'Take live photo' : 'Choose file' }}</button>
        </div>

        <input v-if="!camera" ref="input" type="file" class="hidden" :accept="accept" @change="onPick" />
        <LivePhotoCapture v-if="camera" :open="cameraOpen" @close="cameraOpen = false" @captured="upload" />
    </div>
</template>

<style scoped>
.doc { display: flex; flex-direction: column; gap: .8rem; padding: 1rem; border-radius: 18px; background: #fff; border: 1.5px solid #E7D9BE; transition: border-color .2s, box-shadow .2s; }
.doc.done { border-color: rgba(22,138,102,.35); }
.doc.drag { border-color: #EE6A2C; box-shadow: 0 0 0 4px rgba(238,106,44,.12); }
.doc-head { display: flex; align-items: center; gap: .7rem; }
.doc-ic { width: 40px; height: 40px; flex-shrink: 0; border-radius: 12px; display: grid; place-items: center; background: #FBF6EC; color: #C9501A; }
.doc-ic :deep(svg) { width: 21px; height: 21px; }
.doc.done .doc-ic { background: rgba(22,138,102,.1); color: #168A66; }
.doc-title { flex: 1; min-width: 0; font-size: .95rem; color: #0A1024; line-height: 1.25; }
.doc-desc { margin: -.35rem 0 0; font-size: .78rem; color: #5B6373; line-height: 1.4; min-height: 2.2em; }
.badge { font-size: .68rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; padding: .25rem .55rem; border-radius: 999px; white-space: nowrap; }
.badge.ok { color: #168A66; background: rgba(22,138,102,.12); }
.badge.req { color: #C9501A; background: rgba(238,106,44,.1); }

.doc-preview { position: relative; border-radius: 14px; overflow: hidden; background: #FBF9F3; aspect-ratio: 4 / 3; display: grid; place-items: center; }
.thumb-link { position: absolute; inset: 0; display: block; }
.thumb { width: 100%; height: 100%; object-fit: contain; display: block; background: #F3E9D6; }
.pdf { display: grid; justify-items: center; gap: .4rem; color: #131C3D; font-weight: 700; font-size: .85rem; text-decoration: none; }
.pdf svg { width: 54px; height: 54px; color: #C9501A; }
.drop { width: 100%; height: 100%; border: 2px dashed #E0CFAE; border-radius: 14px; background: transparent; cursor: pointer; display: grid; place-content: center; justify-items: center; gap: .3rem; color: #5B6373; padding: 1rem; transition: border-color .2s, background .2s; }
.drop:hover:not(:disabled) { border-color: #EE6A2C; background: rgba(238,106,44,.04); color: #C9501A; }
.drop:disabled { cursor: not-allowed; opacity: .6; }
.drop svg { width: 34px; height: 34px; }
.drop span { font-size: .86rem; font-weight: 700; }
.drop small { font-size: .74rem; color: #9aa0ad; }
.busy { position: absolute; inset: 0; display: grid; place-content: center; justify-items: center; gap: .5rem; background: rgba(255,255,255,.85); font-size: .85rem; font-weight: 700; color: #131C3D; }
.spin { width: 22px; height: 22px; border: 2.5px solid #E7D9BE; border-top-color: #EE6A2C; border-radius: 50%; animation: spin .7s linear infinite; }

.tips { margin: 0; padding-left: 1.1rem; font-size: .76rem; color: #5B6373; display: grid; gap: .15rem; }
.err { margin: 0; color: #DC2626; font-size: .8rem; font-weight: 600; }
.doc-actions { display: flex; gap: .5rem; margin-top: auto; }
.act { flex: 1; border: 1.5px solid #E7D9BE; background: #fff; color: #131C3D; font-weight: 700; font-size: .84rem; padding: .6rem .8rem; border-radius: 11px; cursor: pointer; transition: border-color .2s, color .2s; }
.act:hover:not(:disabled) { border-color: #131C3D; }
.act.primary { border: 0; color: #fff; background: linear-gradient(135deg, #1B2748, #0A1024); }
.act.danger { color: #DC2626; }
.act.danger:hover:not(:disabled) { border-color: #DC2626; }
.act:disabled { opacity: .55; cursor: progress; }
.lock-note { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; color: #5B6373; }
.lock-note svg { width: 15px; height: 15px; }
.hidden { display: none; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
