<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <AuthLayout
        eyebrow="Student Portal"
        heading="Welcome back"
        subheading="Log in to continue your olympiad journey."
    >
        <div v-if="status" class="status-pill">{{ status }}</div>

        <form class="auth-form" @submit.prevent="submit">
            <div class="field">
                <label for="email">Email address</label>
                <div class="control">
                    <span class="ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m3 7 9 6 9-6"/></svg>
                    </span>
                    <input id="email" type="email" v-model="form.email" required autofocus autocomplete="username" placeholder="you@example.com" />
                </div>
                <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
            </div>

            <div class="field">
                <div class="label-row">
                    <label for="password">Password</label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="link-mini">Forgot?</Link>
                </div>
                <div class="control">
                    <span class="ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    </span>
                    <input :type="showPassword ? 'text' : 'password'" id="password" v-model="form.password" required autocomplete="current-password" placeholder="••••••••" />
                    <button type="button" class="toggle" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                        <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c6.5 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.36M6.6 6.6A13.2 13.2 0 0 0 2 11s3.5 7 10 7a9.1 9.1 0 0 0 4.1-.94"/><path d="m2 2 20 20"/></svg>
                    </button>
                </div>
                <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
            </div>

            <label class="remember">
                <input type="checkbox" v-model="form.remember" />
                <span>Keep me signed in</span>
            </label>

            <button type="submit" class="cta" :class="{ busy: form.processing }" :disabled="form.processing">
                <span>{{ form.processing ? 'Signing in…' : 'Log in' }}</span>
            </button>
        </form>

        <p class="switch">
            New to National Olympiad Hunt?
            <Link :href="route('register')">Create an account</Link>
        </p>
    </AuthLayout>
</template>

<style scoped>
.status-pill {
    background: rgba(22,138,102,.1); color: #126b50;
    border: 1px solid rgba(22,138,102,.25);
    padding: .6rem .9rem; border-radius: 12px;
    font-size: .88rem; font-weight: 600; margin-bottom: 1.2rem;
}

.auth-form { display: grid; gap: 1.1rem; }
.field { display: grid; gap: .45rem; }
.label-row { display: flex; align-items: center; justify-content: space-between; }
.field label { font-size: .85rem; font-weight: 600; color: #0A1024; }

.control {
    position: relative; display: flex; align-items: center;
    background: #fff;
    border: 1.5px solid #E7D9BE;
    border-radius: 14px;
    transition: border-color .2s, box-shadow .2s, transform .2s;
}
.control:focus-within { border-color: #EE6A2C; box-shadow: 0 0 0 4px rgba(238,106,44,.12); }
.control .ic { display: grid; place-items: center; width: 44px; color: rgba(10,16,36,.4); flex-shrink: 0; }
.control .ic svg { width: 18px; height: 18px; }
.control input {
    flex: 1; border: 0; background: transparent; outline: none;
    padding: .8rem .9rem .8rem 0; font-size: .96rem; color: #0A1024;
    font-family: "Plus Jakarta Sans", sans-serif;
}
.control input::placeholder { color: rgba(10,16,36,.32); }
.toggle { border: 0; background: transparent; cursor: pointer; padding: 0 .9rem; color: rgba(10,16,36,.4); display: grid; place-items: center; }
.toggle:hover { color: #EE6A2C; }
.toggle svg { width: 18px; height: 18px; }

.err { color: #DC2626; font-size: .8rem; margin: 0; }
.link-mini { font-size: .82rem; color: #C9501A; font-weight: 600; text-decoration: none; }
.link-mini:hover { text-decoration: underline; }

.remember { display: flex; align-items: center; gap: .55rem; font-size: .9rem; color: rgba(10,16,36,.7); cursor: pointer; user-select: none; }
.remember input { width: 17px; height: 17px; accent-color: #EE6A2C; border-radius: 5px; }

.cta {
    position: relative; overflow: hidden;
    margin-top: .3rem;
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

.switch { text-align: center; margin: 1.6rem 0 0; font-size: .92rem; color: rgba(10,16,36,.6); }
.switch a { color: #C9501A; font-weight: 700; text-decoration: none; }
.switch a:hover { text-decoration: underline; }
</style>
