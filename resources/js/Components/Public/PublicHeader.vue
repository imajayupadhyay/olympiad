<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    // Homepage passes its scrollspy section so the matching link highlights.
    activeSection: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const currentPath = computed(() => (page.url || '/').split('?')[0]);

const navLinks = [
    { id: 'subjects', label: 'Subjects' },
    { id: 'how', label: 'How it Works' },
    { id: 'exams', label: 'Exams', to: '/exams' },
    { id: 'rewards', label: 'Rewards' },
    { id: 'faq', label: 'FAQ' },
];

const scrolled = ref(false);
const mobileOpen = ref(false);
let onScroll = null;

onMounted(() => {
    onScroll = () => { scrolled.value = window.scrollY > 12; };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});
onUnmounted(() => onScroll && window.removeEventListener('scroll', onScroll));
</script>

<template>
    <div class="ph">
        <header class="nav" :class="{ scrolled }">
            <div class="wrap nav__inner">
                <Link href="/" class="brand">
                    <span class="brand__mark">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 2l2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 19.3 7.2 17l.9-5.4L4.2 7.7l5.4-.8L12 2z" fill="#F2C84B"/><path d="M8 19h8v2.5a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5V19z" fill="#EE6A2C"/></svg>
                    </span>
                    <span>National Olympiad Hunt<small>Est. 2026 · India</small></span>
                </Link>

                <nav class="nav__links">
                    <template v-for="l in navLinks" :key="l.id">
                        <Link v-if="l.to" :href="l.to" :class="{ active: currentPath === l.to }">{{ l.label }}</Link>
                        <a v-else :href="'/#' + l.id" :class="{ active: currentPath === '/' && activeSection === l.id }">{{ l.label }}</a>
                    </template>
                </nav>

                <div class="nav__cta">
                    <Link v-if="user" href="/student/dashboard" class="btn btn-primary">Dashboard
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </Link>
                    <template v-else>
                        <Link href="/login" class="btn btn-ghost nav-login">Log In</Link>
                        <Link href="/register" class="btn btn-primary">Register Free
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </Link>
                    </template>
                    <button class="burger" :class="{ open: mobileOpen }" @click="mobileOpen = !mobileOpen" aria-label="Menu"><span></span><span></span><span></span></button>
                </div>
            </div>
        </header>

        <div class="mobile-menu" :class="{ open: mobileOpen }">
            <template v-for="l in navLinks" :key="l.id">
                <Link v-if="l.to" :href="l.to" @click="mobileOpen = false">{{ l.label }}</Link>
                <a v-else :href="'/#' + l.id" @click="mobileOpen = false">{{ l.label }}</a>
            </template>
            <Link v-if="user" href="/student/dashboard" class="btn btn-primary" @click="mobileOpen = false">Dashboard</Link>
            <template v-else>
                <Link href="/login" class="btn btn-ghost" @click="mobileOpen = false">Log In</Link>
                <Link href="/register" class="btn btn-primary" @click="mobileOpen = false">Register Free</Link>
            </template>
        </div>
    </div>
</template>

<style scoped>
.ph {
    --ink:#0A1024; --ink-2:#131C3D; --paper:#FBF6EC; --paper-2:#F3E9D6; --paper-line:#E7D9BE;
    --saffron:#EE6A2C; --saffron-dk:#C9501A; --gold:#D6991F; --gold-lt:#F2C84B; --royal:#2C49A6;
    --ink-70:rgba(10,16,36,.70); --ink-55:rgba(10,16,36,.55); --ink-12:rgba(10,16,36,.12);
    --shadow-sm:0 2px 8px rgba(10,16,36,.06); --shadow-md:0 14px 40px -12px rgba(10,16,36,.22);
    --display:"Fraunces",Georgia,serif; --body:"Plus Jakarta Sans",system-ui,sans-serif;
}
.ph a { text-decoration:none; color:inherit; }
.ph svg { display:block; }
.wrap { max-width:1240px; margin:0 auto; padding:0 24px; }

.nav{ position:fixed; top:0; left:0; right:0; z-index:80; transition:.3s; background:transparent; border-bottom:1px solid transparent; }
.nav.scrolled{ background:rgba(251,246,236,.72); backdrop-filter:blur(14px) saturate(140%); -webkit-backdrop-filter:blur(14px) saturate(140%); border-bottom-color:var(--paper-line); box-shadow:var(--shadow-sm); }
.nav__inner{ display:flex; align-items:center; justify-content:space-between; height:74px; }

.brand{ display:flex; align-items:center; gap:12px; font-family:var(--display); font-weight:700; font-size:20px; letter-spacing:-.01em; color:var(--ink); }
.brand__mark{ width:42px; height:42px; border-radius:13px; flex:none; background:linear-gradient(150deg,var(--royal),var(--ink)); display:grid; place-items:center; box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.12), var(--shadow-sm); }
.brand__mark svg{ width:24px; height:24px; }
.brand small{ display:block; font:600 10px/1 var(--body); letter-spacing:.22em; text-transform:uppercase; color:var(--saffron-dk); margin-top:3px; }

.nav__links{ display:flex; align-items:center; gap:4px; }
.nav__links a{ font:600 14.5px/1 var(--body); padding:10px 15px; border-radius:10px; color:var(--ink-70); transition:.2s; position:relative; }
.nav__links a::after{ content:""; position:absolute; left:15px; right:15px; bottom:5px; height:2px; background:var(--saffron); border-radius:2px; transform:scaleX(0); transform-origin:left; transition:transform .28s; }
.nav__links a:hover{ color:var(--ink); }
.nav__links a.active{ color:var(--ink); }
.nav__links a.active::after{ transform:scaleX(1); }

.nav__cta{ display:flex; align-items:center; gap:12px; }
.nav__cta .btn{ padding:11px 20px; font-size:14px; }

.btn{ display:inline-flex; align-items:center; justify-content:center; gap:9px; font:700 15px/1 var(--body); padding:15px 26px; border-radius:100px; cursor:pointer; border:1.5px solid transparent; transition:.28s cubic-bezier(.2,.8,.2,1); white-space:nowrap; position:relative; overflow:hidden; }
.btn svg{ width:18px; height:18px; }
.btn-primary{ background:var(--saffron); color:#fff; box-shadow:0 12px 26px -10px var(--saffron); }
.btn-primary:hover{ background:var(--saffron-dk); transform:translateY(-2px); box-shadow:0 18px 34px -10px var(--saffron); }
.btn-ghost{ background:transparent; color:var(--ink); border-color:var(--ink-12); }
.btn-ghost:hover{ border-color:var(--ink); background:var(--ink); color:var(--paper); transform:translateY(-2px); }

.burger{ display:none; flex-direction:column; gap:5px; background:none; border:none; cursor:pointer; padding:8px; }
.burger span{ width:24px; height:2px; background:var(--ink); border-radius:2px; transition:.3s; }
.burger.open span:nth-child(1){ transform:translateY(7px) rotate(45deg); }
.burger.open span:nth-child(2){ opacity:0; }
.burger.open span:nth-child(3){ transform:translateY(-7px) rotate(-45deg); }

.mobile-menu{ position:fixed; inset:74px 0 auto 0; background:var(--paper); z-index:79; padding:20px 24px 30px; transform:translateY(-130%); transition:.4s cubic-bezier(.2,.8,.2,1); border-bottom:1px solid var(--paper-line); box-shadow:var(--shadow-md); }
.mobile-menu.open{ transform:translateY(0); }
.mobile-menu a{ display:block; padding:14px 4px; font:600 17px/1 var(--body); border-bottom:1px solid var(--paper-line); }
.mobile-menu .btn{ width:100%; margin-top:18px; }

@media (max-width:860px){
    .nav__links, .nav-login{ display:none; }
    .burger{ display:flex; }
}
</style>
