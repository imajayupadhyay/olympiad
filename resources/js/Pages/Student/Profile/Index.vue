<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    profile: { type: Object, required: true },
    classLevels: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
});

const page = usePage();

/* ── tabs ── */
const tab = ref('details');

/* ── avatar ── */
const fileInput = ref(null);
const preview = ref(null);
const photoForm = useForm({ photo: null });

const avatarSrc = computed(() => preview.value || props.profile.photo_url);
const initials = computed(() => {
    const n = (props.profile.name ?? '').trim();
    if (!n) return 'S';
    const p = n.split(/\s+/);
    return (p[0][0] + (p[1]?.[0] ?? '')).toUpperCase();
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

const removePhoto = () => {
    if (!props.profile.photo_url) return;
    if (!confirm('Remove your profile photo?')) return;
    router.delete(route('student.profile.photo.delete'), { preserveScroll: true });
};

/* ── details form ── */
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
});

const saveDetails = () => {
    detailsForm.put(route('student.profile.update'), { preserveScroll: true });
};

/* ── password form ── */
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const savePassword = () => {
    passwordForm.put(route('student.profile.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => passwordForm.reset('current_password', 'password', 'password_confirmation'),
    });
};

/* ── profile completeness ── */
const completeness = computed(() => {
    const fields = ['name', 'email', 'phone', 'class_level_id', 'dob', 'school', 'school_address', 'city', 'pincode', 'state'];
    const filled = fields.filter((f) => {
        const v = props.profile[f];
        return v !== null && v !== '' && v !== undefined;
    }).length;
    const withPhoto = props.profile.photo_url ? 1 : 0;
    return Math.round(((filled + withPhoto) / (fields.length + 1)) * 100);
});
const ringDash = computed(() => `${(completeness.value / 100) * 326.7} 326.7`);

const memberSince = computed(() =>
    props.profile.created_at
        ? new Date(props.profile.created_at).toLocaleDateString('en-IN', { month: 'short', year: 'numeric' })
        : '—'
);

/* ── flash toast ── */
const toast = ref(null);
let toastTimer = null;
const showToast = (type, msg) => {
    toast.value = { type, msg };
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toast.value = null), 3200);
};
watch(
    () => page.props.flash,
    (f) => {
        if (f?.success) showToast('success', f.success);
        else if (f?.error) showToast('error', f.error);
    },
    { deep: true, immediate: true }
);
</script>

<template>
    <Head title="My Profile" />

    <StudentLayout title="My Profile">
        <!-- toast -->
        <Transition name="toast">
            <div v-if="toast" class="toast" :class="toast.type">
                <span class="toast-ic" v-html="toast.type === 'success' ? icons.check : icons.warn"></span>
                {{ toast.msg }}
            </div>
        </Transition>

        <!-- ───── header / identity hero ───── -->
        <section class="id-card">
            <div class="cover-blob a"></div>
            <div class="cover-blob b"></div>
            <span class="cover-shape">★</span>

            <div class="id-body">
                <div class="avatar-wrap" :class="{ busy: photoForm.processing }">
                    <img v-if="avatarSrc" :src="avatarSrc" alt="Profile photo" class="avatar-img" />
                    <span v-else class="avatar-fallback">{{ initials }}</span>

                    <button class="avatar-edit" type="button" @click="pickFile" :disabled="photoForm.processing" aria-label="Change photo">
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
                    </div>
                    <div class="photo-actions">
                        <button type="button" class="mini-link" @click="pickFile">Change photo</button>
                        <button v-if="profile.photo_url" type="button" class="mini-link danger" @click="removePhoto">Remove</button>
                    </div>
                    <p v-if="photoForm.errors.photo" class="err err-light">{{ photoForm.errors.photo }}</p>
                </div>

                <!-- completeness ring -->
                <div class="ring-wrap">
                    <svg viewBox="0 0 120 120" class="ring">
                        <circle cx="60" cy="60" r="52" class="ring-bg" />
                        <circle cx="60" cy="60" r="52" class="ring-fg" :stroke-dasharray="ringDash" />
                    </svg>
                    <div class="ring-label">
                        <strong>{{ completeness }}%</strong>
                        <small>complete</small>
                    </div>
                </div>
            </div>
        </section>

        <!-- ───── tabs ───── -->
        <div class="tabs">
            <button :class="{ on: tab === 'details' }" @click="tab = 'details'">
                <span v-html="icons.user"></span> Personal Details
            </button>
            <button :class="{ on: tab === 'security' }" @click="tab = 'security'">
                <span v-html="icons.lock"></span> Security
            </button>
        </div>

        <!-- ───── personal details ───── -->
        <section v-show="tab === 'details'" class="panel">
            <div class="panel-head">
                <h3>Personal Details</h3>
                <p>Keep your information up to date — it appears on your certificates and admit cards.</p>
            </div>

            <form @submit.prevent="saveDetails" class="form-grid">
                <div class="field col-6">
                    <label>Full name</label>
                    <input type="text" v-model="detailsForm.name" />
                    <p v-if="detailsForm.errors.name" class="err">{{ detailsForm.errors.name }}</p>
                </div>
                <div class="field col-6">
                    <label>Email address</label>
                    <input type="email" v-model="detailsForm.email" />
                    <p v-if="detailsForm.errors.email" class="err">{{ detailsForm.errors.email }}</p>
                </div>

                <div class="field col-6">
                    <label>Phone</label>
                    <input type="tel" v-model="detailsForm.phone" placeholder="Mobile number" />
                    <p v-if="detailsForm.errors.phone" class="err">{{ detailsForm.errors.phone }}</p>
                </div>
                <div class="field col-3">
                    <label>Class</label>
                    <select v-model="detailsForm.class_level_id">
                        <option value="" disabled>Select</option>
                        <option v-for="cl in classLevels" :key="cl.id" :value="cl.id">{{ cl.label }}</option>
                    </select>
                    <p v-if="detailsForm.errors.class_level_id" class="err">{{ detailsForm.errors.class_level_id }}</p>
                </div>
                <div class="field col-3">
                    <label>Date of birth</label>
                    <input type="date" v-model="detailsForm.dob" />
                    <p v-if="detailsForm.errors.dob" class="err">{{ detailsForm.errors.dob }}</p>
                </div>

                <div class="field col-12">
                    <label>School</label>
                    <input type="text" v-model="detailsForm.school" placeholder="School name" />
                    <p v-if="detailsForm.errors.school" class="err">{{ detailsForm.errors.school }}</p>
                </div>

                <div class="field col-12">
                    <label>School address</label>
                    <input type="text" v-model="detailsForm.school_address" placeholder="Street, area, landmark" />
                    <p v-if="detailsForm.errors.school_address" class="err">{{ detailsForm.errors.school_address }}</p>
                </div>

                <div class="field col-4">
                    <label>City</label>
                    <input type="text" v-model="detailsForm.city" placeholder="City" />
                    <p v-if="detailsForm.errors.city" class="err">{{ detailsForm.errors.city }}</p>
                </div>
                <div class="field col-4">
                    <label>PIN code</label>
                    <input type="text" inputmode="numeric" maxlength="6" v-model="detailsForm.pincode" placeholder="6-digit PIN" />
                    <p v-if="detailsForm.errors.pincode" class="err">{{ detailsForm.errors.pincode }}</p>
                </div>
                <div class="field col-4">
                    <label>State</label>
                    <select v-model="detailsForm.state">
                        <option value="" disabled>Select state</option>
                        <option v-for="st in states" :key="st" :value="st">{{ st }}</option>
                    </select>
                    <p v-if="detailsForm.errors.state" class="err">{{ detailsForm.errors.state }}</p>
                </div>

                <div class="actions col-12">
                    <Transition name="fade">
                        <span v-if="detailsForm.recentlySuccessful" class="saved">Saved ✓</span>
                    </Transition>
                    <button type="submit" class="cta" :class="{ busy: detailsForm.processing }" :disabled="detailsForm.processing">
                        {{ detailsForm.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </section>

        <!-- ───── security ───── -->
        <section v-show="tab === 'security'" class="panel">
            <div class="panel-head">
                <h3>Change Password</h3>
                <p>Use a strong password you don't use elsewhere.</p>
            </div>

            <form @submit.prevent="savePassword" class="form-grid">
                <div class="field col-12">
                    <label>Current password</label>
                    <input type="password" v-model="passwordForm.current_password" autocomplete="current-password" />
                    <p v-if="passwordForm.errors.current_password" class="err">{{ passwordForm.errors.current_password }}</p>
                </div>
                <div class="field col-6">
                    <label>New password</label>
                    <input type="password" v-model="passwordForm.password" autocomplete="new-password" />
                    <p v-if="passwordForm.errors.password" class="err">{{ passwordForm.errors.password }}</p>
                </div>
                <div class="field col-6">
                    <label>Confirm new password</label>
                    <input type="password" v-model="passwordForm.password_confirmation" autocomplete="new-password" />
                </div>

                <div class="actions col-12">
                    <Transition name="fade">
                        <span v-if="passwordForm.recentlySuccessful" class="saved">Updated ✓</span>
                    </Transition>
                    <button type="submit" class="cta" :class="{ busy: passwordForm.processing }" :disabled="passwordForm.processing">
                        {{ passwordForm.processing ? 'Updating…' : 'Update password' }}
                    </button>
                </div>
            </form>
        </section>
    </StudentLayout>
</template>

<script>
const icons = {
    camera: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h3l2-2h8l2 2h3v12H3V7Z"/><circle cx="12" cy="13" r="3.5"/></svg>',
    user:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
    lock:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
    check:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>',
    warn:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>',
};
export default { data: () => ({ icons }) };
</script>

<style scoped>
:root, .panel { --saffron: #EE6A2C; }
.err { color: #DC2626; font-size: .78rem; margin: .35rem 0 0; }

/* ── toast ── */
.toast {
    position: fixed; top: 1.1rem; right: 1.1rem; z-index: 60;
    display: flex; align-items: center; gap: .6rem;
    padding: .8rem 1.1rem; border-radius: 14px;
    font-size: .9rem; font-weight: 600; color: #fff;
    box-shadow: 0 18px 40px -14px rgba(10,16,36,.5);
}
.toast.success { background: linear-gradient(135deg, #1aa177, #168A66); }
.toast.error { background: linear-gradient(135deg, #ef4444, #DC2626); }
.toast-ic :deep(svg) { width: 18px; height: 18px; display: block; }
.toast-enter-active, .toast-leave-active { transition: all .35s cubic-bezier(.2,.7,.2,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(30px); }

/* ── identity hero (dark ink band — all content on one clear surface) ── */
.id-card {
    position: relative; overflow: hidden;
    background: linear-gradient(120deg, #1B2748, #131C3D 55%, #0A1024);
    border: 1px solid rgba(255,255,255,.08); border-radius: 24px;
    box-shadow: 0 24px 60px -28px rgba(10,16,36,.55);
    animation: fadeUp .5s cubic-bezier(.2,.7,.2,1) both;
}
.cover-blob { position: absolute; border-radius: 50%; filter: blur(55px); pointer-events: none; }
.cover-blob.a { width: 320px; height: 320px; top: -140px; right: -60px; background: radial-gradient(circle, rgba(238,106,44,.5), transparent 70%); }
.cover-blob.b { width: 240px; height: 240px; bottom: -140px; left: 12%; background: radial-gradient(circle, rgba(44,73,166,.45), transparent 70%); }
.cover-shape { position: absolute; top: 20%; right: 16%; color: rgba(242,200,75,.5); font-size: 1.3rem; animation: floaty 7s ease-in-out infinite; }

.id-body {
    position: relative; z-index: 1;
    display: flex; align-items: center; gap: 1.4rem;
    padding: 1.7rem 1.8rem; flex-wrap: wrap;
}

.avatar-wrap {
    position: relative; width: 108px; height: 108px; flex-shrink: 0;
    border-radius: 26px; border: 4px solid rgba(255,255,255,.9);
    box-shadow: 0 16px 34px -12px rgba(0,0,0,.55);
    background: linear-gradient(135deg, #37427a, #1B2748);
}
.avatar-wrap.busy { opacity: .7; }
.avatar-img { width: 100%; height: 100%; object-fit: cover; border-radius: 22px; display: block; }
.avatar-fallback {
    width: 100%; height: 100%; display: grid; place-items: center; border-radius: 22px;
    font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 2.1rem; color: #fff;
}
.avatar-edit {
    position: absolute; bottom: -8px; right: -8px;
    width: 36px; height: 36px; border-radius: 12px; border: 3px solid #131C3D;
    background: linear-gradient(135deg, #F2854E, #EE6A2C); color: #fff;
    display: grid; place-items: center; cursor: pointer;
    box-shadow: 0 8px 18px -6px rgba(238,106,44,.8); transition: transform .15s;
}
.avatar-edit:hover { transform: scale(1.08); }
.avatar-edit :deep(svg) { width: 17px; height: 17px; }

.id-meta { flex: 1; min-width: 220px; }
.id-name { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.65rem; line-height: 1.1; color: #fff; margin: 0; }
.id-email { color: rgba(251,246,236,.62); font-size: .9rem; margin: .2rem 0 .8rem; }
.id-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
.chip {
    font-size: .76rem; font-weight: 600; color: #FBF6EC;
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
    padding: .32rem .75rem; border-radius: 999px; backdrop-filter: blur(6px);
}
.chip-saffron { background: linear-gradient(135deg, #F2854E, #EE6A2C); border-color: transparent; color: #fff; box-shadow: 0 6px 16px -8px rgba(238,106,44,.9); }
.chip-ghost { background: transparent; border-color: rgba(255,255,255,.14); color: rgba(251,246,236,.55); }
.photo-actions { display: flex; gap: 1.2rem; margin-top: .8rem; }
.mini-link { border: 0; background: transparent; cursor: pointer; font-size: .82rem; font-weight: 600; color: #F9B98C; padding: 0; }
.mini-link:hover { color: #fff; text-decoration: underline; }
.mini-link.danger { color: #FCA5A5; }
.err-light { color: #FCA5A5; }

.ring-wrap {
    position: relative; width: 104px; height: 104px; flex-shrink: 0; margin-left: auto;
    background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12);
    border-radius: 20px; display: grid; place-items: center;
}
.ring { width: 88px; height: 88px; transform: rotate(-90deg); }
.ring-bg { fill: none; stroke: rgba(255,255,255,.14); stroke-width: 9; }
.ring-fg { fill: none; stroke: #F2C84B; stroke-width: 9; stroke-linecap: round; transition: stroke-dasharray .8s cubic-bezier(.2,.7,.2,1); }
.ring-label { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
.ring-label strong { font-family: "Space Grotesk", monospace; font-size: 1.15rem; color: #fff; display: block; }
.ring-label small { font-size: .64rem; color: rgba(251,246,236,.55); text-transform: uppercase; letter-spacing: .06em; }

@media (max-width: 560px) {
    .id-body { gap: 1rem; padding: 1.4rem; }
    .ring-wrap { margin-left: 0; }
}

/* ── tabs ── */
.tabs { display: flex; gap: .5rem; margin: 1.4rem 0 1rem; }
.tabs button {
    display: inline-flex; align-items: center; gap: .5rem;
    border: 1px solid #E7D9BE; background: #fff; color: rgba(10,16,36,.7);
    padding: .6rem 1.1rem; border-radius: 12px; font-size: .9rem; font-weight: 600; cursor: pointer;
    transition: all .2s;
}
.tabs button :deep(svg) { width: 17px; height: 17px; }
.tabs button:hover { border-color: #EE6A2C; color: #C9501A; }
.tabs button.on { background: #131C3D; color: #fff; border-color: #131C3D; }
.tabs button.on :deep(svg) { color: #F2C84B; }

/* ── panel + form ── */
.panel {
    background: #fff; border: 1px solid #E7D9BE; border-radius: 20px;
    padding: 1.6rem; box-shadow: 0 2px 10px rgba(10,16,36,.04);
    animation: fadeUp .4s cubic-bezier(.2,.7,.2,1) both;
}
.panel-head { margin-bottom: 1.3rem; }
.panel-head h3 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.25rem; color: #0A1024; margin: 0 0 .25rem; }
.panel-head p { font-size: .88rem; color: rgba(10,16,36,.55); margin: 0; }

.form-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.1rem; }
.col-12 { grid-column: span 12; }
.col-6 { grid-column: span 6; }
.col-4 { grid-column: span 4; }
.col-3 { grid-column: span 3; }
@media (max-width: 640px) { .col-6, .col-4, .col-3 { grid-column: span 12; } }

.field { display: grid; gap: .4rem; }
.field label { font-size: .82rem; font-weight: 600; color: #0A1024; }
.field input, .field select {
    width: 100%; background: #FBF9F3; border: 1.5px solid #E7D9BE; border-radius: 12px;
    padding: .7rem .85rem; font-size: .92rem; color: #0A1024; outline: none;
    font-family: "Plus Jakarta Sans", sans-serif; transition: border-color .2s, box-shadow .2s, background .2s;
}
.field input:focus, .field select:focus { border-color: #EE6A2C; background: #fff; box-shadow: 0 0 0 4px rgba(238,106,44,.1); }
.field select { cursor: pointer; }

.actions { display: flex; align-items: center; justify-content: flex-end; gap: 1rem; margin-top: .3rem; }
.saved { color: #168A66; font-size: .85rem; font-weight: 600; }
.cta {
    position: relative; overflow: hidden; border: 0; cursor: pointer;
    background: linear-gradient(135deg, #F2854E, #EE6A2C); color: #fff; font-weight: 700; font-size: .95rem;
    padding: .8rem 1.6rem; border-radius: 13px; box-shadow: 0 12px 26px -12px rgba(238,106,44,.7);
    transition: transform .15s, box-shadow .2s, opacity .2s;
}
.cta:hover { transform: translateY(-2px); box-shadow: 0 16px 32px -12px rgba(238,106,44,.8); }
.cta.busy { opacity: .7; cursor: progress; }

.spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
.hidden { display: none; }

.fade-enter-active, .fade-leave-active { transition: opacity .3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
@keyframes floaty { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-9px); } }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
