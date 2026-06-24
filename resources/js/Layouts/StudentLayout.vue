<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SeoHead from '@/Components/Shared/SeoHead.vue';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const initials = computed(() => {
    const n = (user.value?.name ?? '').trim();
    if (!n) return 'S';
    const parts = n.split(/\s+/);
    return (parts[0][0] + (parts[1]?.[0] ?? '')).toUpperCase();
});

const sidebarOpen = ref(false);

const nav = [
    { label: 'Dashboard',    href: '/student/dashboard',    match: '/student/dashboard',    icon: 'grid' },
    { label: 'Exams',        href: '/student/exams',        match: '/student/exams',        icon: 'doc'  },
    { label: 'Results',      href: '/student/results',      match: '/student/results',      icon: 'chart'},
    { label: 'Leaderboard',  href: '/student/leaderboard',  match: '/student/leaderboard',  icon: 'crown'},
    { label: 'Certificates', href: '/student/certificates', match: '/student/certificates', icon: 'medal'},
    { label: 'Practice',     href: '/student/practice',     match: '/student/practice',     icon: 'bolt' },
    { label: 'Payments',     href: '/student/payments',     match: '/student/payments',     icon: 'card' },
    { label: 'Refer & Earn', href: '/student/refer',        match: '/student/refer',        icon: 'gift' },
];

const isActive = (m) => page.url.startsWith(m);
</script>

<template>
    <SeoHead noindex
        description="Your National Olympiad Hunt student area — enrolled exams, results, certificates and payments." />
    <div class="noh-student">
        <!-- mobile overlay -->
        <div v-show="sidebarOpen" class="overlay" @click="sidebarOpen = false"></div>

        <!-- ───── Sidebar ───── -->
        <aside class="sidebar" :class="{ open: sidebarOpen }">
            <div class="blob"></div>

            <div class="side-head">
                <Link href="/student/dashboard" class="logo">
                    <span class="logo-mark">NOH</span>
                    <span class="logo-text">
                        <strong>Olympiad Hunt</strong>
                        <small>Student Portal</small>
                    </span>
                </Link>
            </div>

            <nav class="side-nav">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    class="nav-link"
                    :class="{ active: isActive(item.match) }"
                    @click="sidebarOpen = false"
                >
                    <span class="nav-ic" v-html="icons[item.icon]"></span>
                    <span>{{ item.label }}</span>
                    <span class="nav-glow"></span>
                </Link>
            </nav>

            <div class="side-foot">
                <Link href="/student/profile" class="nav-link" :class="{ active: isActive('/student/profile') }" @click="sidebarOpen = false">
                    <span class="nav-ic" v-html="icons.user"></span>
                    <span>Profile</span>
                </Link>
                <Link href="/logout" method="post" as="button" class="logout">
                    <span class="nav-ic" v-html="icons.logout"></span>
                    <span>Logout</span>
                </Link>
            </div>
        </aside>

        <!-- ───── Main ───── -->
        <div class="main">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="hamburger" @click="sidebarOpen = true" aria-label="Open menu">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                    </button>
                    <h1 class="page-title">{{ title }}</h1>
                </div>

                <div class="topbar-right">
                    <button class="bell" aria-label="Notifications">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/></svg>
                        <span class="bell-dot"></span>
                    </button>
                    <div class="who">
                        <div class="who-text">
                            <strong>{{ user?.name }}</strong>
                            <small>Student</small>
                        </div>
                        <div class="avatar">
                            <img v-if="user?.photo_url" :src="user.photo_url" alt="" />
                            <template v-else>{{ initials }}</template>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content">
                <slot />
            </main>
        </div>
    </div>
</template>

<script>
// inline SVG icon set (kept outside setup for v-html use)
const icons = {
    grid:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
    doc:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg>',
    chart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 4-5"/></svg>',
    crown: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7l4 4 5-7 5 7 4-4v11H3V7Z"/></svg>',
    medal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="15" r="6"/><path d="M9 9 7 2m8 7 2-7M10.5 13.5 12 12l1.5 1.5"/></svg>',
    bolt:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg>',
    card:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
    gift:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9"/><path d="M12 8S10 3 7.5 4.5 9 8 12 8Zm0 0s2-5 4.5-3.5S15 8 12 8Z"/></svg>',
    user:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
    logout:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>',
};
export default { data: () => ({ icons }) };
</script>

<style scoped>
.noh-student {
    --ink: #0A1024; --ink-2: #131C3D;
    --paper: #FBF6EC; --paper-2: #F3E9D6; --paper-line: #E7D9BE;
    --saffron: #EE6A2C; --gold-lt: #F2C84B;
    min-height: 100vh;
    display: flex;
    background:
        radial-gradient(70% 50% at 100% 0%, rgba(238,106,44,.05), transparent 55%),
        var(--paper);
    font-family: "Plus Jakarta Sans", system-ui, sans-serif;
}

/* ── sidebar ── */
.sidebar {
    position: relative;
    width: 264px; flex-shrink: 0;
    background: linear-gradient(180deg, #1B2748 0%, #131C3D 45%, #0A1024 100%);
    color: rgba(251,246,236,.85);
    display: flex; flex-direction: column;
    overflow: hidden;
    z-index: 40;
}
.sidebar .blob {
    position: absolute; top: -100px; left: -80px;
    width: 280px; height: 280px; border-radius: 50%;
    background: radial-gradient(circle, rgba(238,106,44,.28), transparent 70%);
    filter: blur(40px); pointer-events: none;
}

.side-head { padding: 1.4rem 1.3rem; position: relative; z-index: 1; }
.logo { display: flex; align-items: center; gap: .7rem; text-decoration: none; }
.logo-mark {
    font-family: "Fraunces", serif; font-weight: 700; color: var(--ink);
    background: linear-gradient(135deg, var(--gold-lt), #D6991F);
    padding: .35rem .55rem; border-radius: 11px; font-size: .9rem;
    box-shadow: 0 8px 20px -8px rgba(214,153,31,.7);
}
.logo-text { display: flex; flex-direction: column; line-height: 1.15; }
.logo-text strong { color: #fff; font-size: .98rem; font-weight: 700; }
.logo-text small { color: rgba(251,246,236,.5); font-size: .72rem; }

.side-nav { flex: 1; padding: .5rem .8rem; display: grid; gap: .25rem; align-content: start; position: relative; z-index: 1; }
.side-foot { padding: .8rem; border-top: 1px solid rgba(251,246,236,.1); display: grid; gap: .25rem; position: relative; z-index: 1; }

.nav-link, .logout {
    position: relative; display: flex; align-items: center; gap: .8rem;
    padding: .7rem .85rem; border-radius: 12px;
    color: rgba(251,246,236,.72); font-size: .92rem; font-weight: 500;
    text-decoration: none; border: 0; background: transparent; cursor: pointer;
    width: 100%; text-align: left;
    transition: color .2s, background .2s, transform .15s;
}
.nav-link:hover, .logout:hover { color: #fff; background: rgba(251,246,236,.06); transform: translateX(2px); }
.nav-link.active {
    color: #fff;
    background: linear-gradient(135deg, rgba(238,106,44,.95), rgba(201,80,26,.95));
    box-shadow: 0 10px 24px -10px rgba(238,106,44,.8);
}
.nav-link.active .nav-ic { color: #fff; }
.nav-ic { display: grid; place-items: center; width: 20px; flex-shrink: 0; color: rgba(251,246,236,.6); }
.nav-ic :deep(svg) { width: 19px; height: 19px; }
.nav-link:hover .nav-ic { color: var(--gold-lt); }
.logout { color: rgba(251,246,236,.6); }
.logout:hover { color: #FCA5A5; background: rgba(220,38,38,.12); }

/* ── main ── */
.main { flex: 1; min-width: 0; display: flex; flex-direction: column; }

.topbar {
    position: sticky; top: 0; z-index: 30;
    display: flex; align-items: center; justify-content: space-between;
    padding: .9rem 1.5rem;
    background: rgba(251,246,236,.78);
    backdrop-filter: blur(14px) saturate(150%);
    -webkit-backdrop-filter: blur(14px) saturate(150%);
    border-bottom: 1px solid var(--paper-line);
}
.topbar-left { display: flex; align-items: center; gap: .8rem; }
.page-title { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.3rem; color: var(--ink); margin: 0; }

.hamburger { display: none; border: 0; background: transparent; color: var(--ink); cursor: pointer; padding: .2rem; }
.hamburger svg { width: 24px; height: 24px; }

.topbar-right { display: flex; align-items: center; gap: 1rem; }
.bell { position: relative; border: 0; background: #fff; border: 1px solid var(--paper-line); border-radius: 11px; padding: .5rem; cursor: pointer; color: rgba(10,16,36,.65); transition: color .2s, transform .15s; }
.bell:hover { color: var(--saffron); transform: translateY(-1px); }
.bell svg { width: 19px; height: 19px; display: block; }
.bell-dot { position: absolute; top: 7px; right: 8px; width: 7px; height: 7px; border-radius: 50%; background: var(--saffron); box-shadow: 0 0 0 2px #fff; }

.who { display: flex; align-items: center; gap: .65rem; }
.who-text { display: none; flex-direction: column; line-height: 1.2; text-align: right; }
.who-text strong { font-size: .88rem; color: var(--ink); font-weight: 700; }
.who-text small { font-size: .74rem; color: rgba(10,16,36,.5); }
.avatar {
    width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
    display: grid; place-items: center;
    font-family: "Space Grotesk", monospace; font-weight: 700; font-size: .95rem;
    color: #fff; background: linear-gradient(135deg, #2A335A, #131C3D);
    box-shadow: 0 8px 20px -8px rgba(10,16,36,.5);
    overflow: hidden;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
@media (min-width: 640px) { .who-text { display: flex; } }

.content { flex: 1; padding: 1.6rem; }

/* ── responsive ── */
.overlay { display: none; }
@media (max-width: 1023px) {
    .sidebar {
        position: fixed; inset: 0 auto 0 0; height: 100%;
        transform: translateX(-100%); transition: transform .3s cubic-bezier(.2,.7,.2,1);
        box-shadow: 0 30px 80px -20px rgba(10,16,36,.6);
    }
    .sidebar.open { transform: translateX(0); }
    .hamburger { display: grid; place-items: center; }
    .overlay { display: block; position: fixed; inset: 0; z-index: 35; background: rgba(10,16,36,.5); backdrop-filter: blur(2px); }
}
</style>
