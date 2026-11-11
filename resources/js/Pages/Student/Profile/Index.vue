<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import DocumentCard from './Components/DocumentCard.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({
    profile: { type: Object, required: true },
    classLevels: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    hallTicket: { type: Object, required: true },
    documents: { type: Object, required: true },
    lockedFields: { type: Array, default: () => [] },
    customFields: { type: Array, default: () => [] },
});

const page = usePage();
const ht = computed(() => props.hallTicket);
const el = computed(() => props.hallTicket.eligibility);
const locked = computed(() => props.lockedFields.length > 0);
const isLocked = (field) => props.lockedFields.includes(field);

/* ── tabs (deep-linkable with ?tab=) ── */
const TABS = ['personal', 'school', 'documents', 'security'];
const initialTab = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '').get('tab');
const tab = ref(TABS.includes(initialTab) ? initialTab : 'personal');

/* ── checklist: the server's completion items, grouped by tab ── */
const BASE_GROUPS = {
    personal: ['name', 'email', 'class_level_id', 'phone', 'dob'],
    school: ['school', 'school_address', 'city', 'pincode', 'state', 'coordinator_name', 'coordinator_phone'],
    documents: ['live_photo', 'passport_photo', 'school_id_card'],
};
const LABELS = {
    name: 'Full name', email: 'Email', class_level_id: 'Class', phone: 'Phone', dob: 'Date of birth',
    school: 'School', school_address: 'School address', city: 'City', pincode: 'PIN code', state: 'State',
    coordinator_name: 'Coordinator name', coordinator_phone: 'Coordinator phone',
    live_photo: 'Live photo', passport_photo: 'Passport photo', school_id_card: 'School ID card',
};
// Admin-defined fields marked required join the Personal checklist (server decides; keys custom:{key}).
const requiredCustom = computed(() => props.customFields.filter((f) => f.required).map((f) => `custom:${f.key}`));
const GROUPS = computed(() => ({ ...BASE_GROUPS, personal: [...BASE_GROUPS.personal, ...requiredCustom.value] }));
const REQUIRED = computed(() => ['live_photo', 'passport_photo', 'school_id_card', 'coordinator_name', 'coordinator_phone', ...requiredCustom.value]);
const labelFor = (f) => el.value.labels?.[f] ?? LABELS[f] ?? f;
const groupDone = (g) => GROUPS.value[g].filter((f) => el.value.fields?.[f]).length;

const tabs = computed(() => [
    { key: 'personal', label: 'Personal', icon: icons.user, count: `${groupDone('personal')}/${GROUPS.value.personal.length}` },
    { key: 'school', label: 'School & Coordinator', icon: icons.school, count: `${groupDone('school')}/${GROUPS.value.school.length}` },
    { key: 'documents', label: 'Photos & Documents', icon: icons.idcard, count: `${groupDone('documents')}/${GROUPS.value.documents.length}` },
    { key: 'security', label: 'Security', icon: icons.lock, count: null },
]);

/* ── avatar (optional; not counted) ── */
const fileInput = ref(null);
const preview = ref(null);
const photoForm = useForm({ photo: null });
const avatarSrc = computed(() => preview.value || props.profile.photo_url || props.documents.passport_photo?.url);
const initials = computed(() => {
    const p = (props.profile.name ?? '').trim().split(/\s+/).filter(Boolean);
    return p.length ? (p[0][0] + (p[1]?.[0] ?? '')).toUpperCase() : 'S';
});
const pickFile = () => fileInput.value?.click();
const onFile = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    preview.value = URL.createObjectURL(file);
    photoForm.photo = file;
    photoForm.post(route('student.profile.photo'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { preview.value = null; photoForm.reset(); },
        onError: () => { preview.value = null; },
    });
};
const removePhoto = async () => {
    if (!props.profile.photo_url) return;
    if (!(await confirmDialog({
        title: 'Remove your profile picture?',
        message: 'Your profile will show your initials (or passport photo) instead.',
        confirmText: 'Remove picture',
        tone: 'danger',
    }))) return;
    router.delete(route('student.profile.photo.delete'), { preserveScroll: true });
};

/* ── details form (personal + school tabs share it) ── */
const detailsForm = useForm({
    name: props.profile.name ?? '',
    email: props.profile.email ?? '',
    phone: props.profile.phone ?? '',
    class_level_id: props.profile.class_level_id ?? '',
    dob: props.profile.dob ? String(props.profile.dob).slice(0, 10) : '',
    school: props.profile.school ?? '',
    school_address: props.profile.school_address ?? '',
    city: props.profile.city ?? '',
    pincode: props.profile.pincode ?? '',
    state: props.profile.state ?? '',
    coordinator_name: props.profile.coordinator_name ?? '',
    coordinator_phone: props.profile.coordinator_phone ?? '',
    custom: Object.fromEntries(props.customFields.map((f) => [f.key, f.value ?? ''])),
});
const saveDetails = () => {
    detailsForm.put(route('student.profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Pick up server-normalised values (e.g. +91 phone numbers).
            detailsForm.defaults({ ...detailsForm.data(), phone: props.profile.phone ?? '', coordinator_phone: props.profile.coordinator_phone ?? '' });
            detailsForm.reset('phone', 'coordinator_phone');
        },
    });
};

/* ── password ── */
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const savePassword = () => {
    passwordForm.put(route('student.profile.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => passwordForm.reset('current_password', 'password', 'password_confirmation'),
    });
};

/* ── completion ring (server-computed) ── */
const RING = 2 * Math.PI * 52;
const ringDash = computed(() => `${((el.value.percent ?? 0) / 100) * RING} ${RING}`);
const targetAngle = computed(() => ((el.value.min_percent ?? 90) / 100) * 360);

/* ── submit for verification ── */
const submitting = ref(false);
const submitForVerification = () => {
    router.post(route('student.hall-ticket.store'), {}, {
        preserveScroll: true,
        onStart: () => (submitting.value = true),
        onFinish: () => (submitting.value = false),
    });
};
const canSubmit = computed(() => el.value.eligible && ['none', 'rejected'].includes(ht.value.status));

const memberSince = computed(() => props.profile.created_at
    ? new Date(props.profile.created_at).toLocaleDateString('en-IN', { month: 'short', year: 'numeric' })
    : '—');

/* ── toast ── */
const toast = ref(null);
let toastTimer = null;
watch(() => page.props.flash, (f) => {
    const msg = f?.success || f?.error;
    if (!msg) return;
    toast.value = { type: f.success ? 'success' : 'error', msg };
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toast.value = null), 4200);
}, { deep: true, immediate: true });
</script>

<template>
    <Head title="My Profile" />

    <StudentLayout title="My Profile">
        <Transition name="toast">
            <div v-if="toast" class="toast" :class="toast.type" role="status">
                <span class="toast-ic" v-html="toast.type === 'success' ? icons.check : icons.warn"></span>
                {{ toast.msg }}
            </div>
        </Transition>

        <!-- ───── identity hero ───── -->
        <section class="id-card">
            <div class="cover-blob a"></div>
            <div class="cover-blob b"></div>
            <span class="cover-shape">★</span>

            <div class="id-body">
                <div class="avatar-wrap" :class="{ busy: photoForm.processing }">
                    <img v-if="avatarSrc" :src="avatarSrc" alt="Profile photo" class="avatar-img" />
                    <span v-else class="avatar-fallback">{{ initials }}</span>
                    <button class="avatar-edit" type="button" @click="pickFile" :disabled="photoForm.processing" aria-label="Change profile picture">
                        <span v-if="photoForm.processing" class="spin"></span>
                        <span v-else v-html="icons.camera"></span>
                    </button>
                    <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onFile" />
                </div>

                <div class="id-meta">
                    <h2 class="id-name">{{ profile.name }}</h2>
                    <p class="id-email">{{ profile.email }}</p>
                    <div class="id-chips">
                        <span v-if="profile.class_level" class="chip chip-saffron">{{ profile.class_level.label }}</span>
                        <span v-if="profile.city || profile.state" class="chip">{{ [profile.city, profile.state].filter(Boolean).join(', ') }}</span>
                        <span class="chip chip-ghost">Member since {{ memberSince }}</span>
                        <span class="chip chip-status" :class="ht.status">
                            <span v-html="icons.ticket"></span> Hall ticket: {{ ht.label }}
                        </span>
                    </div>
                    <div class="photo-actions">
                        <button type="button" class="mini-link" @click="pickFile">Change picture</button>
                        <button v-if="profile.photo_url" type="button" class="mini-link danger" @click="removePhoto">Remove</button>
                    </div>
                    <p v-if="photoForm.errors.photo" class="err err-light">{{ photoForm.errors.photo }}</p>
                </div>

                <div class="ring-wrap" :class="{ ok: el.eligible }">
                    <svg viewBox="0 0 120 120" class="ring-svg">
                        <circle cx="60" cy="60" r="52" class="ring-bg" />
                        <circle cx="60" cy="60" r="52" class="ring-fg" :stroke-dasharray="ringDash" />
                    </svg>
                    <span class="ring-target" :style="{ transform: `rotate(${targetAngle}deg)` }" aria-hidden="true"><i></i></span>
                    <div class="ring-label">
                        <strong>{{ el.percent }}%</strong>
                        <small>complete</small>
                    </div>
                </div>
            </div>
        </section>

        <!-- ───── hall ticket status banners ───── -->
        <div v-if="ht.status === 'pending'" class="banner info">
            <span class="b-ic" v-html="icons.hourglass"></span>
            <div>
                <strong>Profile submitted successfully</strong>
                <p>Your profile is now awaiting admin verification. Your details are locked until the examination team reviews them. We'll notify you once your hall ticket is ready.</p>
            </div>
            <Link :href="route('student.hall-ticket')" class="b-cta ghost">View status</Link>
        </div>
        <div v-else-if="ht.status === 'approved'" class="banner good">
            <span class="b-ic" v-html="icons.check"></span>
            <div>
                <strong>Verified · Roll No. <span class="mono">{{ ht.roll_number }}</span></strong>
                <p>Your hall ticket is approved. Verified details are locked; contact support if anything needs correcting.</p>
            </div>
            <Link :href="route('student.hall-ticket')" class="b-cta">Open hall ticket</Link>
        </div>
        <div v-else-if="ht.status === 'rejected'" class="banner bad">
            <span class="b-ic" v-html="icons.warn"></span>
            <div>
                <strong>Your hall ticket needs changes</strong>
                <p>“{{ ht.rejection_reason }}” Update the details below, then submit again.</p>
            </div>
            <button v-if="canSubmit" type="button" class="b-cta" :disabled="submitting" @click="submitForVerification">{{ submitting ? 'Submitting…' : 'Resubmit' }}</button>
        </div>
        <div v-else-if="canSubmit" class="banner ready">
            <span class="b-ic" v-html="icons.spark"></span>
            <div>
                <strong>Your profile is {{ el.percent }}% complete</strong>
                <p>You can now submit your details for verification and generate your hall ticket.</p>
            </div>
            <button type="button" class="b-cta" :disabled="submitting" @click="submitForVerification">{{ submitting ? 'Submitting…' : 'Submit for verification' }}</button>
        </div>

        <div class="layout">
            <!-- ───── main ───── -->
            <div class="main">
                <div class="tabs" role="tablist" aria-label="Profile sections">
                    <button v-for="t in tabs" :key="t.key" type="button" role="tab" :aria-selected="tab === t.key" :class="{ on: tab === t.key }" @click="tab = t.key">
                        <span class="t-ic" v-html="t.icon"></span>
                        <span class="t-l">{{ t.label }}</span>
                        <span v-if="t.count" class="t-count">{{ t.count }}</span>
                    </button>
                </div>

                <p v-if="locked && tab !== 'security'" class="lock-line">
                    <span v-html="icons.lock"></span>
                    Fields with a lock are frozen while your hall ticket is {{ ht.status === 'approved' ? 'approved' : 'under review' }}. You can still update your email and phone.
                </p>

                <!-- personal -->
                <section v-show="tab === 'personal'" class="panel">
                    <div class="panel-head">
                        <span class="ph-ic" v-html="icons.user"></span>
                        <div>
                            <h3>Personal details</h3>
                            <p>These appear on your hall ticket and certificates exactly as written. Use your name as in school records.</p>
                        </div>
                    </div>
                    <form @submit.prevent="saveDetails" class="form-grid">
                        <div class="field col-12" :class="{ locked: isLocked('name') }">
                            <label for="f-name"><span v-html="icons.user"></span> Full name</label>
                            <input id="f-name" type="text" v-model="detailsForm.name" :disabled="isLocked('name')" autocomplete="name" />
                            <p v-if="detailsForm.errors.name" class="err">{{ detailsForm.errors.name }}</p>
                        </div>
                        <div class="field col-6">
                            <label for="f-email"><span v-html="icons.mail"></span> Email address</label>
                            <input id="f-email" type="email" v-model="detailsForm.email" autocomplete="email" />
                            <p v-if="detailsForm.errors.email" class="err">{{ detailsForm.errors.email }}</p>
                        </div>
                        <div class="field col-6">
                            <label for="f-phone"><span v-html="icons.phone"></span> Mobile number</label>
                            <input id="f-phone" type="tel" v-model="detailsForm.phone" placeholder="10-digit mobile number" autocomplete="tel" />
                            <p v-if="detailsForm.errors.phone" class="err">{{ detailsForm.errors.phone }}</p>
                        </div>
                        <div class="field col-6" :class="{ locked: isLocked('class_level_id') }">
                            <label for="f-class"><span v-html="icons.cap"></span> Class</label>
                            <select id="f-class" v-model="detailsForm.class_level_id" :disabled="isLocked('class_level_id')">
                                <option value="" disabled>Select</option>
                                <option v-for="cl in classLevels" :key="cl.id" :value="cl.id">{{ cl.label }}</option>
                            </select>
                            <p v-if="detailsForm.errors.class_level_id" class="err">{{ detailsForm.errors.class_level_id }}</p>
                        </div>
                        <div class="field col-6" :class="{ locked: isLocked('dob') }">
                            <label for="f-dob"><span v-html="icons.cake"></span> Date of birth</label>
                            <input id="f-dob" type="date" v-model="detailsForm.dob" :disabled="isLocked('dob')" />
                            <p v-if="detailsForm.errors.dob" class="err">{{ detailsForm.errors.dob }}</p>
                        </div>
                        <template v-if="customFields.length">
                            <div class="sub-head col-12">
                                <span v-html="icons.users"></span> Additional details
                            </div>
                            <div v-for="f in customFields" :key="f.key" class="field" :class="['col-6', { locked }]">
                                <label :for="`f-c-${f.key}`">
                                    {{ f.label }}
                                    <em v-if="f.required" class="req-tag">Required</em>
                                </label>
                                <select v-if="f.type === 'select'" :id="`f-c-${f.key}`" v-model="detailsForm.custom[f.key]" :disabled="locked">
                                    <option value="">Select</option>
                                    <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
                                </select>
                                <input v-else :id="`f-c-${f.key}`" :type="f.type === 'date' ? 'date' : 'text'" v-model="detailsForm.custom[f.key]"
                                       :placeholder="f.placeholder || ''" :disabled="locked" maxlength="150" />
                                <small v-if="f.help_text" class="hint">{{ f.help_text }}</small>
                                <p v-if="detailsForm.errors[`custom.${f.key}`]" class="err">{{ detailsForm.errors[`custom.${f.key}`] }}</p>
                            </div>
                        </template>
                        <div class="actions col-12">
                            <Transition name="fade"><span v-if="detailsForm.recentlySuccessful" class="saved">Saved ✓</span></Transition>
                            <button type="button" class="link-btn" @click="tab = 'school'">Next: School →</button>
                            <button type="submit" class="cta" :class="{ busy: detailsForm.processing }" :disabled="detailsForm.processing">
                                {{ detailsForm.processing ? 'Saving…' : 'Save details' }}
                            </button>
                        </div>
                    </form>
                </section>

                <!-- school + coordinator -->
                <section v-show="tab === 'school'" class="panel">
                    <div class="panel-head">
                        <span class="ph-ic" v-html="icons.school"></span>
                        <div>
                            <h3>School &amp; coordinator</h3>
                            <p>Your school coordinator is the teacher who helps with olympiad registrations at your school.</p>
                        </div>
                    </div>
                    <form @submit.prevent="saveDetails" class="form-grid">
                        <div class="field col-12" :class="{ locked: isLocked('school') }">
                            <label for="f-school"><span v-html="icons.school"></span> School name</label>
                            <input id="f-school" type="text" v-model="detailsForm.school" :disabled="isLocked('school')" placeholder="Full school name" />
                            <p v-if="detailsForm.errors.school" class="err">{{ detailsForm.errors.school }}</p>
                        </div>
                        <div class="field col-12" :class="{ locked: isLocked('school_address') }">
                            <label for="f-addr"><span v-html="icons.pin"></span> School address</label>
                            <input id="f-addr" type="text" v-model="detailsForm.school_address" :disabled="isLocked('school_address')" placeholder="Street, area, landmark" />
                            <p v-if="detailsForm.errors.school_address" class="err">{{ detailsForm.errors.school_address }}</p>
                        </div>
                        <div class="field col-4" :class="{ locked: isLocked('city') }">
                            <label for="f-city">City</label>
                            <input id="f-city" type="text" v-model="detailsForm.city" :disabled="isLocked('city')" placeholder="City" />
                            <p v-if="detailsForm.errors.city" class="err">{{ detailsForm.errors.city }}</p>
                        </div>
                        <div class="field col-4" :class="{ locked: isLocked('pincode') }">
                            <label for="f-pin">PIN code</label>
                            <input id="f-pin" type="text" inputmode="numeric" maxlength="6" v-model="detailsForm.pincode" :disabled="isLocked('pincode')" placeholder="6-digit PIN" />
                            <p v-if="detailsForm.errors.pincode" class="err">{{ detailsForm.errors.pincode }}</p>
                        </div>
                        <div class="field col-4" :class="{ locked: isLocked('state') }">
                            <label for="f-state">State</label>
                            <select id="f-state" v-model="detailsForm.state" :disabled="isLocked('state')">
                                <option value="" disabled>Select state</option>
                                <option v-for="st in states" :key="st" :value="st">{{ st }}</option>
                            </select>
                            <p v-if="detailsForm.errors.state" class="err">{{ detailsForm.errors.state }}</p>
                        </div>

                        <div class="sub-head col-12">
                            <span v-html="icons.users"></span> School coordinator <em>Required</em>
                        </div>
                        <div class="field col-6" :class="{ locked: isLocked('coordinator_name') }">
                            <label for="f-cname"><span v-html="icons.user"></span> Coordinator name</label>
                            <input id="f-cname" type="text" v-model="detailsForm.coordinator_name" :disabled="isLocked('coordinator_name')" placeholder="e.g. Mrs. Sunita Sharma" />
                            <p v-if="detailsForm.errors.coordinator_name" class="err">{{ detailsForm.errors.coordinator_name }}</p>
                        </div>
                        <div class="field col-6" :class="{ locked: isLocked('coordinator_phone') }">
                            <label for="f-cphone"><span v-html="icons.phone"></span> Coordinator phone</label>
                            <input id="f-cphone" type="tel" v-model="detailsForm.coordinator_phone" :disabled="isLocked('coordinator_phone')" placeholder="10-digit mobile number" />
                            <p v-if="detailsForm.errors.coordinator_phone" class="err">{{ detailsForm.errors.coordinator_phone }}</p>
                        </div>
                        <div class="actions col-12">
                            <Transition name="fade"><span v-if="detailsForm.recentlySuccessful" class="saved">Saved ✓</span></Transition>
                            <button type="button" class="link-btn" @click="tab = 'documents'">Next: Photos →</button>
                            <button type="submit" class="cta" :class="{ busy: detailsForm.processing }" :disabled="detailsForm.processing">
                                {{ detailsForm.processing ? 'Saving…' : 'Save details' }}
                            </button>
                        </div>
                    </form>
                </section>

                <!-- documents -->
                <section v-show="tab === 'documents'" class="panel">
                    <div class="panel-head">
                        <span class="ph-ic" v-html="icons.idcard"></span>
                        <div>
                            <h3>Photos &amp; documents</h3>
                            <p>All three are required for your hall ticket. Files are stored privately and only you and the examination team can see them.</p>
                        </div>
                    </div>
                    <div class="docs">
                        <DocumentCard type="live_photo" :doc="documents.live_photo" camera :locked="locked"
                                      description="Taken now with your device camera, to confirm it's really you."
                                      :tips="['Good light, face the camera', 'No cap, mask or sunglasses']">
                            <template #icon><span v-html="icons.camera"></span></template>
                        </DocumentCard>
                        <DocumentCard type="passport_photo" :doc="documents.passport_photo" :locked="locked"
                                      description="A recent passport-size photo. This is printed on your hall ticket."
                                      :tips="['Plain light background', 'Face clearly visible, front-facing', 'At least 200 × 200 pixels']">
                            <template #icon><span v-html="icons.portrait"></span></template>
                        </DocumentCard>
                        <DocumentCard type="school_id_card" :doc="documents.school_id_card" allow-pdf :locked="locked"
                                      description="Front side of your current school ID card (photo or PDF)."
                                      :tips="['Name, photo and school name readable', 'Whole card in frame, no glare']">
                            <template #icon><span v-html="icons.idcard"></span></template>
                        </DocumentCard>
                    </div>
                </section>

                <!-- security -->
                <section v-show="tab === 'security'" class="panel">
                    <div class="panel-head">
                        <span class="ph-ic" v-html="icons.lock"></span>
                        <div>
                            <h3>Change password</h3>
                            <p>Use a strong password you don't use elsewhere.</p>
                        </div>
                    </div>
                    <form @submit.prevent="savePassword" class="form-grid">
                        <div class="field col-12">
                            <label for="f-cur">Current password</label>
                            <input id="f-cur" type="password" v-model="passwordForm.current_password" autocomplete="current-password" />
                            <p v-if="passwordForm.errors.current_password" class="err">{{ passwordForm.errors.current_password }}</p>
                        </div>
                        <div class="field col-6">
                            <label for="f-new">New password</label>
                            <input id="f-new" type="password" v-model="passwordForm.password" autocomplete="new-password" />
                            <p v-if="passwordForm.errors.password" class="err">{{ passwordForm.errors.password }}</p>
                        </div>
                        <div class="field col-6">
                            <label for="f-conf">Confirm new password</label>
                            <input id="f-conf" type="password" v-model="passwordForm.password_confirmation" autocomplete="new-password" />
                        </div>
                        <div class="actions col-12">
                            <Transition name="fade"><span v-if="passwordForm.recentlySuccessful" class="saved">Updated ✓</span></Transition>
                            <button type="submit" class="cta" :class="{ busy: passwordForm.processing }" :disabled="passwordForm.processing">
                                {{ passwordForm.processing ? 'Updating…' : 'Update password' }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <!-- ───── checklist rail ───── -->
            <aside class="rail">
                <div class="rail-card">
                    <div class="rail-top">
                        <div>
                            <p class="rail-k">Hall ticket readiness</p>
                            <strong class="rail-pct" :class="{ ok: el.eligible }">{{ el.percent }}%</strong>
                        </div>
                        <span class="rail-need">Need {{ el.min_percent }}%<br />+ all required</span>
                    </div>
                    <div class="meter">
                        <span :style="{ width: el.percent + '%' }" :class="{ ok: el.eligible }"></span>
                        <i :style="{ left: el.min_percent + '%' }"></i>
                    </div>

                    <div v-for="(fields, g) in GROUPS" :key="g" class="cl-group">
                        <button type="button" class="cl-title" @click="tab = g">
                            {{ { personal: 'Personal', school: 'School & coordinator', documents: 'Photos & documents' }[g] }}
                            <span>{{ groupDone(g) }}/{{ fields.length }}</span>
                        </button>
                        <ul>
                            <li v-for="f in fields" :key="f" :class="{ done: el.fields?.[f] }" @click="tab = g">
                                <span class="cl-dot" v-html="el.fields?.[f] ? icons.check : ''"></span>
                                {{ labelFor(f) }}
                                <em v-if="REQUIRED.includes(f) && !el.fields?.[f]">required</em>
                            </li>
                        </ul>
                    </div>

                    <button v-if="canSubmit" type="button" class="rail-cta" :disabled="submitting" @click="submitForVerification">
                        {{ submitting ? 'Submitting…' : (ht.status === 'rejected' ? 'Resubmit for verification' : 'Submit for verification') }}
                    </button>
                    <Link v-else-if="['pending', 'approved'].includes(ht.status)" :href="route('student.hall-ticket')" class="rail-cta alt">
                        {{ ht.status === 'approved' ? 'Open hall ticket' : 'Track verification' }}
                    </Link>
                    <p v-else class="rail-hint">Complete the items above to unlock hall ticket generation.</p>
                </div>
            </aside>
        </div>
    </StudentLayout>
</template>

<script>
const icons = {
    camera: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h3l2-2h8l2 2h3v12H3V7Z"/><circle cx="12" cy="13" r="3.5"/></svg>',
    user: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
    users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/></svg>',
    lock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5"/></svg>',
    warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>',
    school: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 10 12 5l9 5-9 5-9-5Z"/><path d="M7 12v5c3 2 7 2 10 0v-5M21 10v6"/></svg>',
    idcard: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16.2a3.4 3.4 0 0 1 6.4 0M14 10h4M14 13.5h3"/></svg>',
    portrait: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M8 17a4 4 0 0 1 8 0"/></svg>',
    mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
    phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>',
    cap: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V6a2 2 0 0 1 2-2h12v13H6a2 2 0 0 0-2 2Zm0 0a2 2 0 0 0 2 2h12"/></svg>',
    cake: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>',
    pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>',
    ticket: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4Z"/></svg>',
    hourglass: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M6 2h12M6 22h12M7 2v4l5 6-5 6v4M17 2v4l-5 6 5 6v4"/></svg>',
    spark: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/></svg>',
};
export default { data: () => ({ icons }) };
</script>

<style scoped>
.mono { font-family: "Space Grotesk", monospace; }
.err { color: #DC2626; font-size: .78rem; margin: .35rem 0 0; }

/* toast */
.toast { position: fixed; top: 1.1rem; right: 1.1rem; z-index: 60; max-width: min(440px, calc(100vw - 2.2rem)); display: flex; align-items: center; gap: .6rem;
    padding: .8rem 1.1rem; border-radius: 14px; font-size: .9rem; font-weight: 600; color: #fff; box-shadow: 0 18px 40px -14px rgba(10,16,36,.5); }
.toast.success { background: linear-gradient(135deg, #1aa177, #168A66); }
.toast.error { background: linear-gradient(135deg, #ef4444, #DC2626); }
.toast-ic { flex-shrink: 0; }
.toast-ic :deep(svg) { width: 18px; height: 18px; display: block; }
.toast-enter-active, .toast-leave-active { transition: all .35s cubic-bezier(.2,.7,.2,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(30px); }

/* hero */
.id-card { position: relative; overflow: hidden; background: linear-gradient(120deg, #1B2748, #131C3D 55%, #0A1024);
    border: 1px solid rgba(255,255,255,.08); border-radius: 24px; box-shadow: 0 24px 60px -28px rgba(10,16,36,.55); animation: fadeUp .5s cubic-bezier(.2,.7,.2,1) both; }
.cover-blob { position: absolute; border-radius: 50%; filter: blur(55px); pointer-events: none; }
.cover-blob.a { width: 320px; height: 320px; top: -140px; right: -60px; background: radial-gradient(circle, rgba(238,106,44,.5), transparent 70%); }
.cover-blob.b { width: 240px; height: 240px; bottom: -140px; left: 12%; background: radial-gradient(circle, rgba(44,73,166,.45), transparent 70%); }
.cover-shape { position: absolute; top: 20%; right: 22%; color: rgba(242,200,75,.5); font-size: 1.3rem; animation: floaty 7s ease-in-out infinite; }
.id-body { position: relative; z-index: 1; display: flex; align-items: center; gap: 1.4rem; padding: 1.7rem 1.8rem; flex-wrap: wrap; }
.avatar-wrap { position: relative; width: 108px; height: 108px; flex-shrink: 0; border-radius: 26px; border: 4px solid rgba(255,255,255,.9);
    box-shadow: 0 16px 34px -12px rgba(0,0,0,.55); background: linear-gradient(135deg, #37427a, #1B2748); }
.avatar-wrap.busy { opacity: .7; }
.avatar-img { width: 100%; height: 100%; object-fit: cover; border-radius: 22px; display: block; }
.avatar-fallback { width: 100%; height: 100%; display: grid; place-items: center; border-radius: 22px; font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 2.1rem; color: #fff; }
.avatar-edit { position: absolute; bottom: -8px; right: -8px; width: 36px; height: 36px; border-radius: 12px; border: 3px solid #131C3D;
    background: linear-gradient(135deg, #F2854E, #EE6A2C); color: #fff; display: grid; place-items: center; cursor: pointer; box-shadow: 0 8px 18px -6px rgba(238,106,44,.8); transition: transform .15s; }
.avatar-edit:hover { transform: scale(1.08); }
.avatar-edit :deep(svg) { width: 17px; height: 17px; }
.id-meta { flex: 1; min-width: 220px; }
.id-name { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.65rem; line-height: 1.1; color: #fff; margin: 0; }
.id-email { color: rgba(251,246,236,.62); font-size: .9rem; margin: .2rem 0 .8rem; word-break: break-all; }
.id-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
.chip { display: inline-flex; align-items: center; gap: .35rem; font-size: .76rem; font-weight: 600; color: #FBF6EC; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); padding: .32rem .75rem; border-radius: 999px; }
.chip :deep(svg) { width: 14px; height: 14px; }
.chip-saffron { background: linear-gradient(135deg, #F2854E, #EE6A2C); border-color: transparent; color: #fff; }
.chip-ghost { background: transparent; border-color: rgba(255,255,255,.14); color: rgba(251,246,236,.55); }
.chip-status.pending { color: #F2C84B; border-color: rgba(242,200,75,.45); background: rgba(242,200,75,.12); }
.chip-status.approved { color: #8ff0c9; border-color: rgba(22,138,102,.55); background: rgba(22,138,102,.22); }
.chip-status.rejected { color: #fecaca; border-color: rgba(220,38,38,.5); background: rgba(220,38,38,.18); }
.photo-actions { display: flex; gap: 1.2rem; margin-top: .8rem; }
.mini-link { border: 0; background: transparent; cursor: pointer; font-size: .82rem; font-weight: 600; color: #F9B98C; padding: 0; }
.mini-link:hover { color: #fff; text-decoration: underline; }
.mini-link.danger { color: #FCA5A5; }
.err-light { color: #FCA5A5; }

.ring-wrap { position: relative; width: 112px; height: 112px; flex-shrink: 0; margin-left: auto; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 22px; display: grid; place-items: center; }
.ring-svg { width: 92px; height: 92px; transform: rotate(-90deg); }
.ring-bg { fill: none; stroke: rgba(255,255,255,.14); stroke-width: 9; }
.ring-fg { fill: none; stroke: #F2C84B; stroke-width: 9; stroke-linecap: round; transition: stroke-dasharray .8s cubic-bezier(.2,.7,.2,1); }
.ring-wrap.ok .ring-fg { stroke: #34d399; }
.ring-target { position: absolute; width: 92px; height: 92px; left: 50%; top: 50%; margin: -46px 0 0 -46px; pointer-events: none; }
.ring-target i { position: absolute; left: 50%; top: -3px; width: 3px; height: 14px; margin-left: -1.5px; background: #fff; border-radius: 2px; }
.ring-label { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
.ring-label strong { font-family: "Space Grotesk", monospace; font-size: 1.2rem; color: #fff; display: block; }
.ring-label small { font-size: .64rem; color: rgba(251,246,236,.55); text-transform: uppercase; letter-spacing: .06em; }
@media (max-width: 560px) { .id-body { gap: 1rem; padding: 1.4rem; } .ring-wrap { margin-left: 0; } }

/* banners */
.banner { margin-top: 1.1rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; padding: 1rem 1.2rem; border-radius: 18px; border: 1px solid; animation: fadeUp .45s cubic-bezier(.2,.7,.2,1) both; }
.banner > div { flex: 1; min-width: 220px; }
.banner strong { display: block; color: #0A1024; font-size: .98rem; }
.banner p { margin: .15rem 0 0; font-size: .86rem; color: rgba(10,16,36,.66); line-height: 1.45; }
.b-ic { width: 44px; height: 44px; flex-shrink: 0; border-radius: 14px; display: grid; place-items: center; color: #fff; }
.b-ic :deep(svg) { width: 22px; height: 22px; }
.banner.info { background: rgba(44,73,166,.06); border-color: rgba(44,73,166,.25); }
.banner.info .b-ic { background: #2C49A6; }
.banner.good { background: rgba(22,138,102,.07); border-color: rgba(22,138,102,.3); }
.banner.good .b-ic { background: #168A66; }
.banner.bad { background: rgba(220,38,38,.05); border-color: rgba(220,38,38,.25); }
.banner.bad .b-ic { background: #DC2626; }
.banner.ready { background: linear-gradient(135deg, rgba(238,106,44,.08), rgba(242,200,75,.1)); border-color: rgba(238,106,44,.35); }
.banner.ready .b-ic { background: linear-gradient(135deg, #F2854E, #EE6A2C); }
.b-cta { border: 0; cursor: pointer; text-decoration: none; font-weight: 700; font-size: .86rem; padding: .65rem 1.1rem; border-radius: 12px; color: #fff; white-space: nowrap;
    background: linear-gradient(135deg, #F2854E, #EE6A2C); box-shadow: 0 10px 22px -12px rgba(238,106,44,.7); }
.b-cta.ghost { background: #fff; color: #131C3D; border: 1.5px solid #E7D9BE; box-shadow: none; }
.b-cta:disabled { opacity: .6; }

/* layout */
.layout { display: grid; grid-template-columns: minmax(0, 1fr) 290px; gap: 1.2rem; align-items: start; margin-top: 1.2rem; }
@media (max-width: 1080px) { .layout { grid-template-columns: 1fr; } .rail { order: -1; } }

.tabs { display: flex; gap: .5rem; margin-bottom: 1rem; overflow-x: auto; padding-bottom: 2px; scrollbar-width: none; }
.tabs::-webkit-scrollbar { display: none; }
.tabs button { display: inline-flex; align-items: center; gap: .5rem; flex-shrink: 0; border: 1px solid #E7D9BE; background: #fff; color: rgba(10,16,36,.7);
    padding: .6rem 1rem; border-radius: 12px; font-size: .88rem; font-weight: 600; cursor: pointer; transition: all .2s; }
.t-ic :deep(svg) { width: 17px; height: 17px; display: block; }
.t-count { font-family: "Space Grotesk", monospace; font-size: .72rem; padding: .1rem .4rem; border-radius: 6px; background: #F3E9D6; color: #5B6373; }
.tabs button:hover { border-color: #EE6A2C; color: #C9501A; }
.tabs button.on { background: #131C3D; color: #fff; border-color: #131C3D; }
.tabs button.on .t-ic { color: #F2C84B; }
.tabs button.on .t-count { background: rgba(255,255,255,.14); color: #fff; }
@media (max-width: 520px) { .t-l { display: none; } .tabs button.on .t-l { display: inline; } }

.lock-line { display: flex; align-items: center; gap: .45rem; margin: 0 0 .9rem; font-size: .82rem; color: #5B6373; }
.lock-line :deep(svg) { width: 15px; height: 15px; flex-shrink: 0; }

.panel { background: #fff; border: 1px solid #E7D9BE; border-radius: 20px; padding: 1.5rem; box-shadow: 0 2px 10px rgba(10,16,36,.04); animation: fadeUp .4s cubic-bezier(.2,.7,.2,1) both; }
.panel-head { display: flex; gap: .9rem; align-items: flex-start; margin-bottom: 1.3rem; }
.ph-ic { width: 44px; height: 44px; flex-shrink: 0; border-radius: 14px; display: grid; place-items: center; background: linear-gradient(135deg, #1B2748, #0A1024); color: #F2C84B; }
.ph-ic :deep(svg) { width: 22px; height: 22px; }
.panel-head h3 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.25rem; color: #0A1024; margin: 0 0 .2rem; }
.panel-head p { font-size: .87rem; color: rgba(10,16,36,.55); margin: 0; }

.form-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.1rem; }
.col-12 { grid-column: span 12; } .col-6 { grid-column: span 6; } .col-4 { grid-column: span 4; }
@media (max-width: 640px) { .col-6, .col-4 { grid-column: span 12; } .panel { padding: 1.15rem; } }
.field { display: grid; gap: .4rem; position: relative; }
.field label { display: inline-flex; align-items: center; gap: .4rem; font-size: .82rem; font-weight: 600; color: #0A1024; }
.field label :deep(svg) { width: 15px; height: 15px; color: #C9501A; }
.field input, .field select { width: 100%; background: #FBF9F3; border: 1.5px solid #E7D9BE; border-radius: 12px; padding: .7rem .85rem; font-size: .92rem; color: #0A1024; outline: none;
    font-family: "Plus Jakarta Sans", sans-serif; transition: border-color .2s, box-shadow .2s, background .2s; }
.field input:focus, .field select:focus { border-color: #EE6A2C; background: #fff; box-shadow: 0 0 0 4px rgba(238,106,44,.1); }
.field.locked input, .field.locked select { background: #F3EFE6; color: #5B6373; cursor: not-allowed; padding-right: 2.3rem; }
.field.locked::after { content: ""; position: absolute; right: .85rem; bottom: .8rem; width: 15px; height: 15px; opacity: .55;
    background: no-repeat center/contain url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230A1024' stroke-width='2'%3E%3Crect x='4' y='11' width='16' height='10' rx='2'/%3E%3Cpath d='M8 11V7a4 4 0 0 1 8 0v4'/%3E%3C/svg%3E"); }
.sub-head { display: flex; align-items: center; gap: .5rem; margin-top: .4rem; padding-top: 1rem; border-top: 1px dashed #E7D9BE; font-family: "Fraunces", serif; font-weight: 600; font-size: 1.02rem; color: #0A1024; }
.sub-head :deep(svg) { width: 19px; height: 19px; color: #C9501A; }
.req-tag { font-style: normal; font-size: .62rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #C9501A; background: rgba(238,106,44,.1); padding: .15rem .45rem; border-radius: 999px; }
.hint { font-size: .74rem; color: #9aa0ad; }
.sub-head em { font-style: normal; font-family: "Plus Jakarta Sans", sans-serif; font-size: .66rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #C9501A; background: rgba(238,106,44,.1); padding: .2rem .5rem; border-radius: 999px; }

.actions { display: flex; align-items: center; justify-content: flex-end; gap: 1rem; flex-wrap: wrap; margin-top: .3rem; }
.saved { color: #168A66; font-size: .85rem; font-weight: 600; }
.link-btn { border: 0; background: transparent; cursor: pointer; font-weight: 700; font-size: .86rem; color: #C9501A; }
.link-btn:hover { text-decoration: underline; }
.cta { position: relative; overflow: hidden; border: 0; cursor: pointer; background: linear-gradient(135deg, #F2854E, #EE6A2C); color: #fff; font-weight: 700; font-size: .95rem;
    padding: .8rem 1.6rem; border-radius: 13px; box-shadow: 0 12px 26px -12px rgba(238,106,44,.7); transition: transform .15s, box-shadow .2s, opacity .2s; }
.cta:hover { transform: translateY(-2px); }
.cta.busy { opacity: .7; cursor: progress; }

.docs { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
@media (max-width: 900px) { .docs { grid-template-columns: 1fr; } }

/* rail */
.rail { position: sticky; top: 1rem; }
.rail-card { background: #fff; border: 1px solid #E7D9BE; border-radius: 20px; padding: 1.2rem; box-shadow: 0 14px 40px -26px rgba(10,16,36,.35); }
.rail-top { display: flex; align-items: flex-end; justify-content: space-between; gap: .5rem; }
.rail-k { margin: 0; font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #5B6373; }
.rail-pct { font-family: "Space Grotesk", monospace; font-size: 2rem; line-height: 1.1; color: #C9501A; }
.rail-pct.ok { color: #168A66; }
.rail-need { text-align: right; font-size: .72rem; color: #9aa0ad; line-height: 1.3; }
.meter { position: relative; height: 9px; border-radius: 999px; background: #EADFC8; margin: .6rem 0 1rem; }
.meter span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #F2C84B, #D6991F); transition: width .8s cubic-bezier(.2,.7,.2,1); }
.meter span.ok { background: linear-gradient(90deg, #1aa177, #168A66); }
.meter i { position: absolute; top: -4px; bottom: -4px; width: 2px; background: #0A1024; border-radius: 2px; }
.cl-group + .cl-group { margin-top: .8rem; }
.cl-title { width: 100%; display: flex; justify-content: space-between; border: 0; background: transparent; padding: 0 0 .35rem; cursor: pointer; font-size: .78rem; font-weight: 800; color: #0A1024; text-transform: uppercase; letter-spacing: .05em; }
.cl-title span { font-family: "Space Grotesk", monospace; color: #9aa0ad; }
.cl-group ul { list-style: none; margin: 0; padding: 0; display: grid; gap: .25rem; }
.cl-group li { display: flex; align-items: center; gap: .5rem; font-size: .84rem; color: #5B6373; cursor: pointer; padding: .2rem .3rem; border-radius: 8px; }
.cl-group li:hover { background: #FBF6EC; }
.cl-group li.done { color: #0A1024; }
.cl-group li em { margin-left: auto; font-style: normal; font-size: .64rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #C9501A; }
.cl-dot { width: 18px; height: 18px; flex-shrink: 0; border-radius: 6px; border: 1.5px solid #E0CFAE; display: grid; place-items: center; }
.cl-group li.done .cl-dot { background: #168A66; border-color: #168A66; color: #fff; }
.cl-dot :deep(svg) { width: 12px; height: 12px; }
.rail-cta { display: block; width: 100%; margin-top: 1.1rem; text-align: center; text-decoration: none; border: 0; cursor: pointer; color: #fff; font-weight: 700; font-size: .9rem;
    padding: .8rem 1rem; border-radius: 13px; background: linear-gradient(135deg, #F2854E, #EE6A2C); box-shadow: 0 12px 26px -12px rgba(238,106,44,.7); }
.rail-cta.alt { background: linear-gradient(135deg, #1B2748, #0A1024); box-shadow: 0 12px 26px -12px rgba(10,16,36,.6); }
.rail-cta:disabled { opacity: .6; }
.rail-hint { margin: 1rem 0 0; font-size: .78rem; color: #9aa0ad; text-align: center; }
@media (max-width: 1080px) {
    .rail { position: static; }
    .cl-group { display: none; }
}

.spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
.hidden { display: none; }
.fade-enter-active, .fade-leave-active { transition: opacity .3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
@keyframes floaty { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-9px); } }
@keyframes spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .id-card, .panel, .banner, .cover-shape { animation: none; } }
</style>
