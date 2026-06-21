<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Stepper from './Onboarding/Components/Stepper.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

defineProps({
    classLevels: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    class_level_id: '',
    dob: '',
    school: '',
    city: '',
    state: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);

// Date of birth as three dropdowns (no calendar). Composed into form.dob (YYYY-MM-DD).
const dobDay = ref('');
const dobMonth = ref('');
const dobYear = ref('');

const months = [
    { v: '01', l: 'Jan' }, { v: '02', l: 'Feb' }, { v: '03', l: 'Mar' },
    { v: '04', l: 'Apr' }, { v: '05', l: 'May' }, { v: '06', l: 'Jun' },
    { v: '07', l: 'Jul' }, { v: '08', l: 'Aug' }, { v: '09', l: 'Sep' },
    { v: '10', l: 'Oct' }, { v: '11', l: 'Nov' }, { v: '12', l: 'Dec' },
];

const thisYear = new Date().getFullYear();
const years = Array.from({ length: 28 }, (_, i) => thisYear - 3 - i); // e.g. 2023 → 1996

// Days available depend on the chosen month/year (28–31), so Feb never offers 30/31.
const maxDay = computed(() => {
    if (!dobMonth.value) return 31;
    const y = dobYear.value ? Number(dobYear.value) : 2000; // leap-safe fallback
    return new Date(y, Number(dobMonth.value), 0).getDate();
});
const days = computed(() => Array.from({ length: maxDay.value }, (_, i) => i + 1));

// Drop an out-of-range day if the month/year change shrinks the month.
watch(maxDay, (max) => {
    if (dobDay.value && Number(dobDay.value) > max) dobDay.value = '';
});

// Keep form.dob in sync; empty until all three are picked (field is optional).
watch([dobDay, dobMonth, dobYear], () => {
    form.dob = (dobDay.value && dobMonth.value && dobYear.value)
        ? `${dobYear.value}-${dobMonth.value}-${String(dobDay.value).padStart(2, '0')}`
        : '';
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Create account" />

    <AuthLayout
        eyebrow="Join the Hunt"
        heading="Create your account"
        subheading="A few details and you're ready for your first olympiad."
    >
        <Stepper :current="1" />

        <form class="auth-form" @submit.prevent="submit">
            <!-- Identity -->
            <div class="field">
                <label for="name">Full name</label>
                <div class="control">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
                    <input id="name" type="text" v-model="form.name" required autofocus autocomplete="name" placeholder="Student's full name" />
                </div>
                <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label for="email">Email</label>
                    <div class="control">
                        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m3 7 9 6 9-6"/></svg></span>
                        <input id="email" type="email" v-model="form.email" required autocomplete="username" placeholder="you@example.com" />
                    </div>
                    <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
                </div>

                <div class="field">
                    <label for="phone">Phone</label>
                    <div class="control">
                        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg></span>
                        <input id="phone" type="tel" v-model="form.phone" autocomplete="tel" placeholder="Parent / student mobile" />
                    </div>
                    <p v-if="form.errors.phone" class="err">{{ form.errors.phone }}</p>
                </div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label for="class_level_id">Class</label>
                    <div class="control">
                        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1 2.7 2.5 6 2.5s6-1.5 6-2.5v-5"/></svg></span>
                        <select id="class_level_id" v-model="form.class_level_id" required>
                            <option value="" disabled>Select class</option>
                            <option v-for="cl in classLevels" :key="cl.id" :value="cl.id">{{ cl.label }}</option>
                        </select>
                    </div>
                    <p v-if="form.errors.class_level_id" class="err">{{ form.errors.class_level_id }}</p>
                </div>

                <div class="field">
                    <label for="dob-day">Date of birth</label>
                    <div class="control dob">
                        <select id="dob-day" class="dob-part" v-model="dobDay" aria-label="Day of birth">
                            <option value="" disabled>Day</option>
                            <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
                        </select>
                        <select class="dob-part" v-model="dobMonth" aria-label="Month of birth">
                            <option value="" disabled>Month</option>
                            <option v-for="m in months" :key="m.v" :value="m.v">{{ m.l }}</option>
                        </select>
                        <select class="dob-part" v-model="dobYear" aria-label="Year of birth">
                            <option value="" disabled>Year</option>
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                    <p v-if="form.errors.dob" class="err">{{ form.errors.dob }}</p>
                </div>
            </div>

            <div class="field">
                <label for="school">School</label>
                <div class="control">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/></svg></span>
                    <input id="school" type="text" v-model="form.school" placeholder="School name" />
                </div>
                <p v-if="form.errors.school" class="err">{{ form.errors.school }}</p>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label for="city">City</label>
                    <div class="control">
                        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 21s-7-5.3-7-11a7 7 0 0 1 14 0c0 5.7-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
                        <input id="city" type="text" v-model="form.city" placeholder="City" />
                    </div>
                    <p v-if="form.errors.city" class="err">{{ form.errors.city }}</p>
                </div>

                <div class="field">
                    <label for="state">State</label>
                    <div class="control">
                        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6Z"/><path d="M9 4v14M15 6v14"/></svg></span>
                        <select id="state" v-model="form.state">
                            <option value="" disabled>Select state</option>
                            <option v-for="st in states" :key="st" :value="st">{{ st }}</option>
                        </select>
                    </div>
                    <p v-if="form.errors.state" class="err">{{ form.errors.state }}</p>
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="control">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span>
                    <input :type="showPassword ? 'text' : 'password'" id="password" v-model="form.password" required autocomplete="new-password" placeholder="Min. 8 characters" />
                    <button type="button" class="toggle" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                        <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c6.5 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.36M6.6 6.6A13.2 13.2 0 0 0 2 11s3.5 7 10 7a9.1 9.1 0 0 0 4.1-.94"/><path d="m2 2 20 20"/></svg>
                    </button>
                </div>
                <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <div class="control">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><path d="m9 16 2 2 4-4"/></svg></span>
                    <input :type="showPassword ? 'text' : 'password'" id="password_confirmation" v-model="form.password_confirmation" required autocomplete="new-password" placeholder="Re-enter password" />
                </div>
                <p v-if="form.errors.password_confirmation" class="err">{{ form.errors.password_confirmation }}</p>
            </div>

            <button type="submit" class="cta" :class="{ busy: form.processing }" :disabled="form.processing">
                <span>{{ form.processing ? 'Saving…' : 'Save & Next →' }}</span>
            </button>
        </form>

        <p class="switch">
            Already have an account?
            <Link :href="route('login')">Log in</Link>
        </p>
    </AuthLayout>
</template>

<style scoped>
.auth-form { display: grid; gap: 1rem; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } }

.field { display: grid; gap: .4rem; }
.field label { font-size: .85rem; font-weight: 600; color: #0A1024; }

.control {
    position: relative; display: flex; align-items: center;
    background: #fff;
    border: 1.5px solid #E7D9BE;
    border-radius: 14px;
    transition: border-color .2s, box-shadow .2s;
}
.control:focus-within { border-color: #EE6A2C; box-shadow: 0 0 0 4px rgba(238,106,44,.12); }
.control .ic { display: grid; place-items: center; width: 42px; color: rgba(10,16,36,.4); flex-shrink: 0; }
.control .ic svg { width: 17px; height: 17px; }
.control input, .control select {
    flex: 1; min-width: 0; border: 0; background: transparent; outline: none;
    padding: .75rem .8rem .75rem 0; font-size: .94rem; color: #0A1024;
    font-family: "Plus Jakarta Sans", sans-serif;
}
.control select { cursor: pointer; appearance: none; -webkit-appearance: none; }
.control input::placeholder { color: rgba(10,16,36,.32); }

/* Date of birth: three equal dropdowns, no calendar */
.control.dob { padding-left: .3rem; }
.control.dob .dob-part { flex: 1; min-width: 0; padding: .75rem .5rem; text-align: center; }
.control.dob .dob-part:not(:last-child) { border-right: 1px solid #F0E6D2; }
.toggle { border: 0; background: transparent; cursor: pointer; padding: 0 .8rem; color: rgba(10,16,36,.4); display: grid; place-items: center; }
.toggle:hover { color: #EE6A2C; }
.toggle svg { width: 17px; height: 17px; }

.err { color: #DC2626; font-size: .78rem; margin: 0; }

.cta {
    position: relative; overflow: hidden;
    margin-top: .4rem;
    border: 0; cursor: pointer;
    background: linear-gradient(135deg, #F2854E, #EE6A2C);
    color: #fff; font-weight: 700; font-size: 1rem;
    padding: .9rem 1rem; border-radius: 14px;
    box-shadow: 0 14px 30px -12px rgba(238,106,44,.7);
    transition: transform .15s, box-shadow .2s, opacity .2s;
}
.cta:hover { transform: translateY(-2px); box-shadow: 0 18px 36px -12px rgba(238,106,44,.8); }
.cta:active { transform: translateY(0); }
.cta.busy { opacity: .7; cursor: progress; }
.cta::after {
    content: ""; position: absolute; top: 0; left: -120%; width: 60%; height: 100%;
    background: linear-gradient(120deg, transparent, rgba(255,255,255,.35), transparent);
    transition: left .6s;
}
.cta:hover::after { left: 130%; }

.switch { text-align: center; margin: 1.4rem 0 0; font-size: .92rem; color: rgba(10,16,36,.6); }
.switch a { color: #C9501A; font-weight: 700; text-decoration: none; }
.switch a:hover { text-decoration: underline; }
</style>
