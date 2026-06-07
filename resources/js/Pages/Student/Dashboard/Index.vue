<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const firstName = computed(() => (user.value?.name ?? 'Student').split(/\s+/)[0]);

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
});

const today = new Date().toLocaleDateString('en-IN', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
});
</script>

<template>
    <Head title="Dashboard" />

    <StudentLayout title="Dashboard">
        <section class="hero">
            <!-- decorative art -->
            <div class="hero-blob a"></div>
            <div class="hero-blob b"></div>
            <span class="hero-shape s1">★</span>
            <span class="hero-shape s2">◆</span>

            <div class="hero-body">
                <p class="hero-date">{{ today }}</p>
                <h2 class="hero-title">
                    {{ greeting }},
                    <span class="ital">{{ firstName }}</span> 👋
                </h2>
                <p class="hero-sub">
                    Welcome to your olympiad dashboard. This is your home base —
                    upcoming exams, results, ranks and certificates will appear here soon.
                </p>
                <span class="hero-badge">
                    <span class="pulse"></span> Your portal is being set up
                </span>
            </div>

            <div class="hero-trophy">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/>
                    <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
                    <path d="M4 22h16"/>
                    <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
                    <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/>
                    <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
                </svg>
            </div>
        </section>

        <!-- placeholder grid (intentionally empty, to be configured later) -->
        <section class="soon-grid">
            <div class="soon-card" v-for="c in cards" :key="c.title" :style="{ animationDelay: c.delay }">
                <span class="soon-ic" :style="{ background: c.bg, color: c.fg }" v-html="c.icon"></span>
                <h3>{{ c.title }}</h3>
                <p>{{ c.text }}</p>
                <span class="soon-tag">Coming soon</span>
            </div>
        </section>
    </StudentLayout>
</template>

<script>
export default {
    data() {
        return {
            cards: [
                { title: 'Upcoming Exams', text: 'Browse and enrol in national olympiads for your class.', delay: '.05s', bg: 'rgba(238,106,44,.12)', fg: '#C9501A',
                  icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>' },
                { title: 'Your Results', text: 'Instant scorecards and section-wise performance.', delay: '.12s', bg: 'rgba(22,138,102,.12)', fg: '#168A66',
                  icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 4-5"/></svg>' },
                { title: 'Ranks & Medals', text: 'National, state and school-level leaderboards.', delay: '.19s', bg: 'rgba(214,153,31,.14)', fg: '#B45309',
                  icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="15" r="6"/><path d="M9 9 7 2m8 7 2-7"/></svg>' },
                { title: 'Certificates', text: 'Download auto-generated certificates of merit.', delay: '.26s', bg: 'rgba(44,73,166,.12)', fg: '#2C49A6',
                  icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16v12H4z"/><path d="M8 20l4-3 4 3"/></svg>' },
            ],
        };
    },
};
</script>

<style scoped>
.hero {
    position: relative; overflow: hidden;
    border-radius: 24px;
    padding: 2.4rem 2.2rem;
    background: linear-gradient(135deg, #1B2748 0%, #131C3D 50%, #0A1024 100%);
    color: #FBF6EC;
    display: flex; align-items: center; justify-content: space-between; gap: 1.5rem;
    box-shadow: 0 30px 70px -28px rgba(10,16,36,.5);
    animation: fadeUp .6s cubic-bezier(.2,.7,.2,1) both;
}
.hero-body { position: relative; z-index: 2; max-width: 38rem; }
.hero-date { font-family: "Space Grotesk", monospace; font-size: .82rem; letter-spacing: .04em; color: rgba(251,246,236,.55); margin: 0 0 .6rem; }
.hero-title { font-family: "Fraunces", serif; font-weight: 600; font-size: clamp(1.7rem, 3.5vw, 2.5rem); line-height: 1.1; margin: 0 0 .8rem; }
.hero-title .ital { font-style: italic; color: #F2C84B; }
.hero-sub { color: rgba(251,246,236,.66); font-size: 1rem; line-height: 1.6; margin: 0 0 1.3rem; }
.hero-badge {
    display: inline-flex; align-items: center; gap: .55rem;
    font-size: .82rem; font-weight: 600; color: #F2C84B;
    background: rgba(242,200,75,.1); border: 1px solid rgba(242,200,75,.25);
    padding: .45rem .9rem; border-radius: 999px;
}
.pulse { width: 8px; height: 8px; border-radius: 50%; background: #F2C84B; box-shadow: 0 0 0 0 rgba(242,200,75,.6); animation: pulse 1.8s infinite; }

.hero-trophy { position: relative; z-index: 2; color: #F2C84B; flex-shrink: 0; }
.hero-trophy svg { width: 120px; height: 120px; opacity: .9; animation: floaty 5s ease-in-out infinite; }
@media (max-width: 768px) { .hero-trophy { display: none; } }

.hero-blob { position: absolute; border-radius: 50%; filter: blur(50px); z-index: 1; }
.hero-blob.a { width: 360px; height: 360px; top: -120px; right: -80px; background: radial-gradient(circle, rgba(238,106,44,.5), transparent 70%); }
.hero-blob.b { width: 280px; height: 280px; bottom: -120px; left: 20%; background: radial-gradient(circle, rgba(44,73,166,.45), transparent 70%); }
.hero-shape { position: absolute; z-index: 1; color: rgba(242,200,75,.3); }
.hero-shape.s1 { top: 18%; right: 24%; font-size: 1.3rem; animation: floaty 7s ease-in-out infinite; }
.hero-shape.s2 { bottom: 20%; right: 40%; font-size: .9rem; color: rgba(238,106,44,.4); animation: floaty 9s ease-in-out infinite .5s; }

.soon-grid {
    margin-top: 1.4rem;
    display: grid; gap: 1.1rem;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
}
.soon-card {
    background: #fff; border: 1px solid #E7D9BE; border-radius: 18px;
    padding: 1.3rem; position: relative; overflow: hidden;
    box-shadow: 0 2px 8px rgba(10,16,36,.04);
    transition: transform .2s, box-shadow .2s;
    animation: fadeUp .6s cubic-bezier(.2,.7,.2,1) both;
}
.soon-card:hover { transform: translateY(-4px); box-shadow: 0 22px 44px -22px rgba(10,16,36,.3); }
.soon-ic { display: grid; place-items: center; width: 46px; height: 46px; border-radius: 13px; margin-bottom: .9rem; }
.soon-ic :deep(svg) { width: 22px; height: 22px; }
.soon-card h3 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.1rem; color: #0A1024; margin: 0 0 .35rem; }
.soon-card p { font-size: .88rem; color: rgba(10,16,36,.6); line-height: 1.5; margin: 0 0 .9rem; }
.soon-tag {
    display: inline-block; font-family: "Space Grotesk", monospace;
    font-size: .68rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
    color: rgba(10,16,36,.45); background: #F3E9D6; padding: .25rem .6rem; border-radius: 999px;
}

@keyframes fadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
@keyframes floaty { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(242,200,75,.6); }
    70% { box-shadow: 0 0 0 8px rgba(242,200,75,0); }
    100% { box-shadow: 0 0 0 0 rgba(242,200,75,0); }
}
</style>
