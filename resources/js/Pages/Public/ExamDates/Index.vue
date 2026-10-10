<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicHeader from '@/Components/Public/PublicHeader.vue';
import AppLogo from '@/Components/Shared/AppLogo.vue';
import SeoHead from '@/Components/Shared/SeoHead.vue';

/**
 * Display-only exam calendar. Every value — date, weekday, times — arrives
 * pre-formatted from ExamCatalogueService::upcomingSchedule(), so the page shows
 * exactly the schedule the admin saved, whatever the viewer's timezone.
 */
const props = defineProps({
    sessions: { type: Array, default: () => [] },
});

const year = new Date().getFullYear();

/* ── countdown, from the viewer's own calendar day ── */
const todayUtc = (() => {
    const t = new Date();
    return Date.UTC(t.getFullYear(), t.getMonth(), t.getDate());
})();

const daysUntil = (ymd) => {
    const [y, m, d] = ymd.split('-').map(Number);
    return Math.round((Date.UTC(y, m - 1, d) - todayUtc) / 86400000);
};

const countdown = (s) => {
    if (s.status === 'live') return { label: 'Live now', tone: 'live' };
    const n = daysUntil(s.date);
    if (n <= 0) return { label: 'Today', tone: 'soon' };
    if (n === 1) return { label: 'Tomorrow', tone: 'soon' };
    return { label: `In ${n} days`, tone: n <= 7 ? 'soon' : 'later' };
};

/* ── month sections, in date order (the server already sorts) ── */
const months = computed(() => {
    const byMonth = new Map();
    props.sessions.forEach((s) => {
        if (!byMonth.has(s.month_key)) byMonth.set(s.month_key, { key: s.month_key, label: s.month_label, sessions: [] });
        byMonth.get(s.month_key).sessions.push(s);
    });
    return [...byMonth.values()];
});

const next = computed(() => props.sessions[0] ?? null);
const subjectCount = computed(() => new Set(props.sessions.map((s) => s.subject?.name)).size);

/* ── subject colour helpers ── */
const FALLBACK = '#2C49A6';
const colorOf = (s) => (/^#[0-9a-f]{6}$/i.test(s.subject?.color ?? '') ? s.subject.color : FALLBACK);
const tint = (hex, a) => hex + a; // 6-digit hex + alpha byte
const monogram = (s) => (s.subject?.icon || s.subject?.name || '?').trim().slice(0, 2);

const timeLine = (s) => (s.end_time ? `${s.start_time} – ${s.end_time}` : s.start_time);
const durationLabel = (m) => {
    if (!m) return null;
    const h = Math.floor(m / 60);
    const r = m % 60;
    if (!h) return `${m} min`;
    return r ? `${h} hr ${r} min` : `${h} hr${h > 1 ? 's' : ''}`;
};
</script>

<template>
    <SeoHead
        title="Upcoming Exam Dates"
        description="Upcoming National Olympiad Hunt exam dates and timings — every olympiad by subject, with the classes it covers, the exam date and start time. Check the schedule and prepare in time." />

    <div class="noh">
        <PublicHeader />

        <!-- ═══════════ HERO ═══════════ -->
        <section class="hero">
            <span class="hero__glow" aria-hidden="true"></span>
            <svg class="hero__cal" viewBox="0 0 64 64" fill="none" stroke="#D6991F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="8" y="12" width="48" height="44" rx="6"/><path d="M8 24h48M20 6v12M44 6v12"/><path d="M18 34h6M30 34h6M42 34h4M18 44h6M30 44h6"/>
            </svg>

            <div class="wrap hero__inner">
                <div class="hero__copy">
                    <span class="eyebrow">Exam Calendar</span>
                    <h1>Mark your <span class="ital">exam dates</span>.</h1>
                    <p class="lede">
                        Every upcoming olympiad in one place — the subject, the classes it is for, the date and
                        the start time. Find your class, note the day, and start preparing.
                    </p>

                    <div v-if="sessions.length" class="facts">
                        <div class="fact"><span class="fact__n">{{ sessions.length }}</span><span class="fact__l">Upcoming exam{{ sessions.length > 1 ? 's' : '' }}</span></div>
                        <div class="fact"><span class="fact__n">{{ subjectCount }}</span><span class="fact__l">Subject{{ subjectCount > 1 ? 's' : '' }}</span></div>
                        <div class="fact"><span class="fact__n">{{ months.length }}</span><span class="fact__l">Month{{ months.length > 1 ? 's' : '' }}</span></div>
                    </div>
                </div>

                <!-- next exam spotlight -->
                <aside v-if="next" class="spot" :aria-label="'Next exam: ' + next.subject?.name">
                    <span class="spot__eyebrow">Next exam</span>
                    <span class="badge" :class="'b-' + countdown(next).tone"><span class="dot"></span>{{ countdown(next).label }}</span>
                    <h2>{{ next.subject?.name }}</h2>
                    <span class="spot__cls">{{ next.class_range }}</span>
                    <div class="spot__rule"></div>
                    <div class="spot__when">
                        <div>
                            <small>Date</small>
                            <time :datetime="next.date">{{ next.date_label }}</time>
                        </div>
                        <div>
                            <small>Starts</small>
                            <strong>{{ next.start_time }}</strong>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <!-- ═══════════ CALENDAR ═══════════ -->
        <main class="wrap cal">
            <template v-if="months.length">
                <section v-for="m in months" :key="m.key" class="month" :aria-labelledby="'m-' + m.key">
                    <header class="month__head">
                        <h2 :id="'m-' + m.key">{{ m.label }}</h2>
                        <span class="month__count">{{ m.sessions.length }} exam{{ m.sessions.length > 1 ? 's' : '' }}</span>
                        <span class="month__rule" aria-hidden="true"></span>
                    </header>

                    <div class="grid">
                        <article
                            v-for="s in m.sessions"
                            :key="s.key"
                            class="ticket"
                            :class="{ live: s.status === 'live' }"
                            :style="{ '--c': colorOf(s), '--c-08': tint(colorOf(s), '14'), '--c-18': tint(colorOf(s), '2E') }"
                            :aria-label="`${s.subject?.name}, ${s.class_range}, ${s.date_label}, ${timeLine(s)}`"
                        >
                            <!-- date stub -->
                            <div class="stub">
                                <span class="stub__wd">{{ s.weekday.slice(0, 3) }}</span>
                                <time class="stub__day" :datetime="s.date">{{ s.day }}</time>
                                <span class="stub__mo">{{ s.month }}</span>
                            </div>

                            <!-- details -->
                            <div class="body">
                                <div class="body__top">
                                    <span class="crest" aria-hidden="true">{{ monogram(s) }}</span>
                                    <span class="badge" :class="'b-' + countdown(s).tone"><span class="dot"></span>{{ countdown(s).label }}</span>
                                </div>

                                <h3>{{ s.subject?.name }}</h3>

                                <span class="cls">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5 10-5Z" stroke-linejoin="round"/><path d="M6 12v5c3 2 9 2 12 0v-5" stroke-linecap="round"/></svg>
                                    {{ s.class_range }}
                                </span>

                                <div class="when">
                                    <span class="when__date">{{ s.date_label }}</span>
                                    <span class="when__time">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                        {{ timeLine(s) }}
                                    </span>
                                    <span v-if="s.closes_label" class="when__closes">Open till {{ s.closes_label }}</span>
                                </div>

                                <span v-if="durationLabel(s.duration_minutes)" class="chip">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 3h10M7 21h10M8 3c0 5 8 6 8 9s-8 4-8 9M16 3c0 5-8 6-8 9" stroke-linecap="round"/></svg>
                                    {{ durationLabel(s.duration_minutes) }} paper
                                </span>
                            </div>
                        </article>
                    </div>
                </section>
            </template>

            <div v-else class="empty">
                <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="8" y="12" width="48" height="44" rx="6"/><path d="M8 24h48M20 6v12M44 6v12"/>
                </svg>
                <h2>No exams scheduled yet</h2>
                <p>New olympiad dates are announced here as soon as they are set. Check back soon.</p>
            </div>
        </main>

        <footer class="foot-band">
            <div class="wrap foot-band__inner">
                <Link href="/" class="brand"><AppLogo :size="54" variant="light" /></Link>
                <p>© {{ year }} National Olympiad Hunt. All rights reserved.</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.noh {
    --ink:#0A1024; --ink-2:#131C3D; --paper:#FBF6EC; --paper-2:#F3E9D6; --paper-line:#E7D9BE;
    --saffron:#EE6A2C; --saffron-dk:#C9501A; --gold:#D6991F; --gold-lt:#F2C84B; --royal:#2C49A6; --emerald:#168A66;
    --ink-70:rgba(10,16,36,.7); --ink-55:rgba(10,16,36,.55); --paper-70:rgba(251,246,236,.72); --paper-45:rgba(251,246,236,.45);
    --display:"Fraunces",Georgia,serif; --body:"Plus Jakarta Sans",system-ui,sans-serif; --mono:"Space Grotesk",monospace;
    font-family:var(--body); background:var(--paper); color:var(--ink); min-height:100vh; padding-top:74px;
    display:flex; flex-direction:column; /* footer pinned to the bottom on short pages */
}
.wrap { max-width:1180px; margin:0 auto; padding:0 24px; }
.brand { display:flex; align-items:center; text-decoration:none; }

/* ═══════════ hero ═══════════ */
.hero { position:relative; overflow-x:clip; padding:3.2rem 0 3rem; } /* clip only sideways so the spotlight shadow isn't cut off */
.hero__glow { position:absolute; width:560px; height:560px; right:-160px; top:-330px; border-radius:50%; background:radial-gradient(circle, rgba(238,106,44,.16), rgba(214,153,31,.08) 45%, transparent 70%); pointer-events:none; }
.hero__cal { position:absolute; width:66px; left:3%; bottom:12%; opacity:.45; transform:rotate(-8deg); pointer-events:none; }
.hero__inner { position:relative; z-index:1; display:grid; grid-template-columns:1.25fr 1fr; gap:3rem; align-items:center; }

.eyebrow { display:inline-block; font-family:var(--mono); font-weight:600; font-size:.74rem; letter-spacing:.14em; text-transform:uppercase; color:var(--saffron); background:rgba(238,106,44,.1); padding:.3rem .7rem; border-radius:999px; }
.hero h1 { font-family:var(--display); font-weight:600; font-size:clamp(2.1rem,4.4vw,3.3rem); line-height:1.06; letter-spacing:-.01em; margin:.9rem 0 .7rem; }
.hero h1 .ital { font-style:italic; color:var(--saffron); }
.lede { color:var(--ink-55); font-size:1.06rem; line-height:1.6; max-width:50ch; margin:0; }

.facts { display:flex; flex-wrap:wrap; gap:.8rem; margin-top:1.7rem; }
.fact { display:flex; flex-direction:column; min-width:108px; padding:.75rem 1rem; background:rgba(255,255,255,.6); border:1px solid var(--paper-line); border-radius:14px; backdrop-filter:blur(14px) saturate(150%); -webkit-backdrop-filter:blur(14px) saturate(150%); box-shadow:inset 0 1px 0 rgba(255,255,255,.7); }
.fact__n { font-family:var(--mono); font-weight:700; font-size:1.55rem; line-height:1.1; color:var(--ink); font-variant-numeric:tabular-nums; }
.fact__l { font-size:.78rem; font-weight:600; color:var(--ink-55); }

/* next-exam spotlight — ink card */
.spot { position:relative; overflow:hidden; display:flex; flex-direction:column; align-items:flex-start; gap:.55rem; padding:1.6rem 1.6rem 1.5rem; border-radius:28px; color:var(--paper); background:linear-gradient(150deg,#1B2748,var(--ink-2) 45%,var(--ink)); box-shadow:0 40px 80px -28px rgba(10,16,36,.55); }
.spot::before { content:""; position:absolute; inset:0; background-image:radial-gradient(rgba(242,200,75,.16) 1px, transparent 1.2px); background-size:16px 16px; -webkit-mask-image:linear-gradient(200deg,#000,transparent 60%); mask-image:linear-gradient(200deg,#000,transparent 60%); pointer-events:none; }
.spot > * { position:relative; }
.spot__eyebrow { font-family:var(--mono); font-size:.72rem; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:var(--gold-lt); }
.spot h2 { font-family:var(--display); font-weight:600; font-size:clamp(1.6rem,2.6vw,2.05rem); line-height:1.1; margin:.2rem 0 0; }
.spot__cls { font-family:var(--mono); font-weight:600; font-size:.92rem; color:var(--paper-70); }
.spot__rule { align-self:stretch; height:1px; margin:.5rem 0 .35rem; background:linear-gradient(90deg,rgba(242,200,75,.45),rgba(251,246,236,.06)); }
.spot__when { display:flex; flex-wrap:wrap; gap:.6rem 1.8rem; }
.spot__when small { display:block; font-size:.7rem; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:var(--paper-45); margin-bottom:.15rem; }
.spot__when time, .spot__when strong { font-weight:700; font-size:1rem; color:var(--paper); }
.spot__when strong { font-family:var(--mono); color:var(--gold-lt); font-size:1.1rem; }
.spot .badge.b-later { background:rgba(242,200,75,.14); color:var(--gold-lt); }

/* ═══════════ badges ═══════════ */
.badge { display:inline-flex; align-items:center; gap:.35rem; font-size:.74rem; font-weight:700; padding:.28rem .65rem; border-radius:999px; white-space:nowrap; }
.badge .dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
.b-live { background:rgba(22,138,102,.14); color:var(--emerald); }
.b-live .dot { animation:pulse 1.6s ease-in-out infinite; }
.b-soon { background:rgba(238,106,44,.13); color:var(--saffron-dk); }
.b-later { background:rgba(44,73,166,.11); color:var(--royal); }
.spot .b-live { background:rgba(52,211,153,.16); color:#6EE7B7; }
.spot .b-soon { background:rgba(238,106,44,.2); color:#F7A779; }
@keyframes pulse { 0%,100% { box-shadow:0 0 0 0 currentColor; } 50% { box-shadow:0 0 0 4px transparent; } }

/* ═══════════ calendar ═══════════ */
.cal { flex:1; width:100%; padding-top:.5rem; padding-bottom:4rem; }
.month + .month { margin-top:2.8rem; }
.month__head { display:flex; align-items:center; gap:.8rem; margin-bottom:1.2rem; }
.month__head h2 { font-family:var(--display); font-weight:600; font-size:1.55rem; margin:0; white-space:nowrap; }
.month__count { font-family:var(--mono); font-weight:600; font-size:.76rem; color:var(--saffron-dk); background:rgba(238,106,44,.1); padding:.22rem .6rem; border-radius:999px; white-space:nowrap; }
.month__rule { flex:1; height:1px; background:var(--paper-line); }

.grid { display:grid; gap:1.15rem; grid-template-columns:repeat(auto-fill,minmax(min(100%,350px),1fr)); }

/* ticket card: date stub + perforation + details */
.ticket { position:relative; display:flex; background:#fff; border:1.5px solid var(--paper-line); border-radius:20px; box-shadow:0 2px 8px rgba(10,16,36,.05); transition:transform .2s, box-shadow .25s, border-color .2s; }
.ticket:hover { transform:translateY(-3px); border-color:var(--c-18); box-shadow:0 24px 46px -26px rgba(10,16,36,.34); }
.ticket.live { border-color:rgba(22,138,102,.45); }

.stub { position:relative; flex:none; width:92px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.1rem; padding:1rem .4rem; border-radius:18px 0 0 18px; background:linear-gradient(170deg,var(--c-18),var(--c-08)); color:var(--c); border-right:2px dashed var(--paper-line); }
/* punched notches where the stub tears off */
.stub::before, .stub::after { content:""; position:absolute; right:-10px; width:18px; height:18px; border-radius:50%; background:var(--paper); border:1.5px solid var(--paper-line); }
.stub::before { top:-10px; clip-path:inset(50% 0 0 0); }
.stub::after { bottom:-10px; clip-path:inset(0 0 50% 0); }
.stub__wd { font-family:var(--mono); font-weight:700; font-size:.74rem; letter-spacing:.14em; text-transform:uppercase; }
.stub__day { font-family:var(--mono); font-weight:700; font-size:2.6rem; line-height:1; color:var(--ink); font-variant-numeric:tabular-nums; }
.stub__mo { font-family:var(--mono); font-weight:700; font-size:.82rem; letter-spacing:.16em; text-transform:uppercase; }

.body { flex:1; min-width:0; display:flex; flex-direction:column; align-items:flex-start; gap:.55rem; padding:1.1rem 1.15rem 1.15rem 1.3rem; }
.body__top { align-self:stretch; display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
.crest { width:38px; height:38px; flex:none; display:grid; place-items:center; border-radius:12px; border:1.5px solid var(--c-18); background:var(--c-08); color:var(--c); font-family:var(--mono); font-weight:700; font-size:1rem; }
.body h3 { font-family:var(--display); font-weight:600; font-size:1.32rem; line-height:1.15; margin:.1rem 0 0; color:var(--ink); overflow-wrap:anywhere; }

.cls { display:inline-flex; align-items:center; gap:.4rem; font-weight:700; font-size:.84rem; color:#8A5A07; background:linear-gradient(135deg,rgba(242,200,75,.28),rgba(214,153,31,.16)); border:1px solid rgba(214,153,31,.35); padding:.3rem .7rem; border-radius:999px; }
.cls svg { width:15px; height:15px; flex:none; }

.when { align-self:stretch; display:flex; flex-direction:column; gap:.2rem; padding-top:.65rem; margin-top:.15rem; border-top:1px solid #F0E6D2; }
.when__date { font-size:.86rem; font-weight:600; color:var(--ink-70); }
.when__time { display:inline-flex; align-items:center; gap:.4rem; font-family:var(--mono); font-weight:700; font-size:1.12rem; color:var(--ink); font-variant-numeric:tabular-nums; }
.when__time svg { width:17px; height:17px; color:var(--saffron); flex:none; }
.when__closes { font-size:.8rem; font-weight:600; color:var(--saffron-dk); padding-left:calc(17px + .4rem); }

.chip { display:inline-flex; align-items:center; gap:.3rem; font-size:.74rem; font-weight:600; color:#5B6373; background:var(--paper); border:1px solid var(--paper-line); padding:.24rem .6rem; border-radius:999px; }
.chip svg { width:13px; height:13px; flex:none; }

/* empty */
.empty { text-align:center; padding:4.5rem 1rem; color:#5B6373; }
.empty svg { display:block; width:56px; height:56px; margin:0 auto; color:var(--gold); }
.empty h2 { font-family:var(--display); font-weight:600; color:var(--ink); font-size:1.5rem; margin:.8rem 0 .35rem; }
.empty p { margin:0 auto; max-width:42ch; }

/* footer */
.foot-band { background:var(--ink); color:rgba(251,246,236,.7); }
.foot-band__inner { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding-top:1.6rem; padding-bottom:1.6rem; flex-wrap:wrap; }
.foot-band p { font-size:.84rem; margin:0; }

/* ═══════════ responsive ═══════════ */
@media (max-width:900px) {
    .hero__inner { grid-template-columns:1fr; gap:2rem; }
    .hero__cal { display:none; }
}
@media (max-width:520px) {
    .wrap { padding:0 16px; }
    .hero { padding:2.2rem 0 2.2rem; }
    .fact { min-width:0; flex:1; padding:.65rem .75rem; }
    .fact__n { font-size:1.3rem; }
    .spot { padding:1.3rem 1.25rem; border-radius:22px; }
    .stub { width:78px; }
    .stub__day { font-size:2.2rem; }
    .body { padding:1rem .95rem 1rem 1.1rem; }
    .month__head h2 { font-size:1.3rem; }
}

@media (prefers-reduced-motion: reduce) {
    .ticket { transition:none; }
    .ticket:hover { transform:none; }
    .b-live .dot { animation:none; }
}
</style>
