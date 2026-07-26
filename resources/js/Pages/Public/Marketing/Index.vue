<script setup>
/**
 * /marketing — the campaign landing page.
 *
 * One CTA, everywhere: "Register Now" opens RegisterModal, which runs the whole
 * funnel (details → olympiads → referral → Razorpay) without leaving the page.
 * Deliberately self-contained — no PublicHeader, no nav links out. Built on the
 * v2 "Editorial / Prestige" brand (see instructions.md).
 */
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import SeoHead from '@/Components/Shared/SeoHead.vue';
import AppLogo from '@/Components/Shared/AppLogo.vue';
import PrizeShowcase from './Components/PrizeShowcase.vue';
import RegisterModal from './Components/RegisterModal.vue';
import SocialProofToast from './Components/SocialProofToast.vue';

const props = defineProps({
    classLevels: { type: Array, default: () => [] },
    exams: { type: Array, default: () => [] },
    referral: { type: Object, default: null },
    referredBy: { type: Object, default: null },
    program: { type: Object, default: null },
});

/* ── the single call to action ──────────────────────────────── */
const modalOpen = ref(false);
const openRegister = () => { modalOpen.value = true; };

/* ── content ────────────────────────────────────────────────── */
const stats = reactive([
    { label: 'Students registered', target: 48000, suffix: '+', display: '0' },
    { label: 'Schools reached', target: 1200, suffix: '+', display: '0' },
    { label: 'Prizes to be won', target: 60, suffix: '+', display: '0' },
    { label: 'Cities across India', target: 320, suffix: '+', display: '0' },
]);

const benefits = [
    {
        icon: '🏅',
        title: 'A rank that counts',
        body: 'Every participant gets an official national rank, plus state and city standings you can put on a school application.',
    },
    {
        icon: '📊',
        title: 'Know exactly where you stand',
        body: 'A detailed performance report breaks your score down question by question, so you know what to work on next.',
    },
    {
        icon: '💻',
        title: 'Sit it from home',
        body: 'A timed, full-screen online exam. Open any time inside the exam window — no travel, no exam centre, no queues.',
    },
    {
        icon: '📜',
        title: 'Certificates for everyone',
        body: 'Nobody leaves empty-handed. Every student who sits the paper earns a digital certificate of participation.',
    },
];

const steps = [
    { n: '01', title: 'Register in 60 seconds', body: 'One form — your details, the olympiads you want, and a referral code if a friend sent you.' },
    { n: '02', title: 'Prepare with the syllabus', body: 'We email your login and the full syllabus straight away, so you can start preparing the same day.' },
    { n: '03', title: 'Sit the exam & win', body: 'Take the paper online inside your exam window, then watch for your rank, certificate and prizes.' },
];

const faqs = [
    { q: 'Who can participate?', a: 'Any student from Class 1 to Class 12 studying in India. You pick your class when you register, and only the olympiads open to that class are shown to you.' },
    { q: 'How much does it cost?', a: 'The fee is set per olympiad and shown next to each one when you register — there are no hidden charges, and some olympiads are free. Whatever you pick, you pay once, at registration.' },
    { q: 'Do I need a password to register?', a: 'No. Register with just your name, email, phone and class, and we email your login details immediately. You can set your own password from your dashboard whenever you like.' },
    { q: 'When and where is the exam?', a: 'It is fully online. Each olympiad has an exam window, and you can sit the paper at any time inside that window — once — from any laptop or desktop with a stable connection.' },
    { q: 'How are the prizes awarded?', a: 'Prizes are awarded on national, state, city and school ranks once results are declared. Every participant also receives a digital certificate and a full performance report.' },
    { q: 'What is the referral code for?', a: "If a friend shared their link with you, entering their code gets you a welcome discount on your registration — and earns them a reward too. It's optional." },
];

const testimonials = [
    { quote: 'The performance report was the real prize. It showed exactly which topics my son was losing marks on — we fixed them before his school exams.', name: 'Sunita R.', role: 'Parent · Class 7 · Nagpur', tone: '#2C49A6' },
    { quote: 'I sat the maths paper from home on a Sunday morning. No stress, no travelling. My All India rank came through three weeks later.', name: 'Aarav M.', role: 'Student · Class 9 · Jaipur', tone: '#EE6A2C' },
    { quote: 'Twenty-two of my students registered together. The school-topper trophies gave the whole class something to chase.', name: 'Mr. Prakash D.', role: 'Teacher · Kochi', tone: '#168A66' },
];

/* Group the catalogue by subject so the page can show what's on offer without
   turning into a second exams listing — the CTA is still Register. */
const subjects = computed(() => {
    const map = new Map();
    props.exams.forEach((e) => {
        const name = e.subject?.name || 'Olympiad';
        if (!map.has(name)) {
            map.set(name, { name, color: e.subject?.color || '#2C49A6', icon: e.subject?.icon || '', count: 0, from: null });
        }
        const entry = map.get(name);
        entry.count += 1;
        if (entry.from === null || e.fee_amount < entry.from) entry.from = e.fee_amount;
    });
    return [...map.values()].slice(0, 8);
});

const inr = (n) => '₹' + Number(n).toLocaleString('en-IN');

/* ── FAQ accordion ──────────────────────────────────────────── */
const openFaq = ref(0);
const toggleFaq = (i) => { openFaq.value = openFaq.value === i ? -1 : i; };

/* ── scroll behaviours ──────────────────────────────────────── */
const rootEl = ref(null);
const scrolled = ref(false);
const observers = [];

const onScroll = () => { scrolled.value = window.scrollY > 20; };

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    const root = rootEl.value;
    if (!root) return;

    // Reveal-on-scroll — the animation backbone shared with the homepage.
    const revealIO = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) {
                e.target.classList.add('in');
                revealIO.unobserve(e.target);
            }
        });
    }, { threshold: 0.12 });
    root.querySelectorAll('[data-reveal]').forEach((el) => revealIO.observe(el));
    observers.push(revealIO);

    // Counters run once, when the dark stats strip comes into view.
    const statsEl = root.querySelector('.stats');
    if (statsEl) {
        const countIO = new IntersectionObserver((entries) => {
            if (!entries[0].isIntersecting) return;
            countIO.disconnect();
            runCounters();
        }, { threshold: 0.4 });
        countIO.observe(statsEl);
        observers.push(countIO);
    }

});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
    observers.forEach((o) => o.disconnect());
});

function runCounters() {
    stats.forEach((s) => {
        const dur = 1600;
        const t0 = performance.now();
        const tick = (t) => {
            const p = Math.min((t - t0) / dur, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            s.display = Math.floor(eased * s.target).toLocaleString('en-IN') + (p === 1 ? s.suffix : '');
            if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    });
}

const year = new Date().getFullYear();
</script>

<template>
    <SeoHead
        title="Register for the National Olympiad Hunt"
        description="Register for India's national olympiad for Class 1–12. One form, one payment — sit timed online exams, earn a national rank and certificate, and compete for 60+ prizes including tablets, smart watches and gift vouchers."
        keywords="olympiad registration India, national olympiad hunt, online olympiad class 1 to 12, olympiad prizes, maths olympiad registration, science olympiad registration"
        canonical="https://neoexam.org/marketing"
    />

    <div ref="rootEl" class="noh">
        <!-- ══ header: logo + the one CTA ══ -->
        <header class="mhead" :class="{ scrolled }">
            <div class="wrap mhead__inner">
                <a href="/" class="mhead__brand" aria-label="National Olympiad Hunt">
                    <AppLogo :size="38" variant="dark" />
                </a>
                <button class="btn btn-primary btn-shine mhead__cta" type="button" @click="openRegister">
                    Register Now
                </button>
            </div>
        </header>

        <!-- ══ hero ══ -->
        <section class="hero">
            <div class="hero__blob a"></div>
            <div class="hero__blob b"></div>

            <svg class="float-shape s1" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="40" r="36" stroke="#EE6A2C" stroke-width="2" stroke-dasharray="6 8" opacity=".5" /></svg>
            <svg class="float-shape s2" viewBox="0 0 60 60" fill="none"><path d="M30 4l26 48H4L30 4z" stroke="#2C49A6" stroke-width="2" opacity=".4" /></svg>
            <svg class="float-shape s3" viewBox="0 0 40 40" fill="none"><path d="M20 2v36M2 20h36" stroke="#D6991F" stroke-width="3" stroke-linecap="round" opacity=".5" /></svg>
            <svg class="float-shape s4" viewBox="0 0 50 50" fill="none"><rect x="6" y="6" width="38" height="38" rx="10" stroke="#168A66" stroke-width="2" opacity=".4" /></svg>

            <div class="wrap hero__grid">
                <div>
                    <span class="hero__badge glass">
                        <i aria-hidden="true"></i>
                        2026 registrations are open
                    </span>

                    <h1>
                        Compete.<br>
                        Rank nationally.<br>
                        <span class="ital">Win big.</span>
                    </h1>

                    <p class="lede">
                        India’s online olympiad for Classes 1–12 — with an official rank and certificate
                        for every participant, plus 60+ prizes worth over ₹5 lakh.
                    </p>

                    <div class="hero__cta">
                        <button class="btn btn-primary btn-shine" type="button" @click="openRegister">
                            Enter the Olympiad
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </button>
                        <span class="hero__cta-note">60-second registration · No password needed</span>
                    </div>

                    <div class="hero__proof" aria-label="Campaign highlights">
                        <span><b class="num">48,000+</b> students</span>
                        <span><b>Class 1–12</b></span>
                        <span><b>100% online</b></span>
                    </div>
                </div>

                <div class="hero__visual">
                    <div class="campaign-card">
                        <div class="campaign-card__glow"></div>
                        <div class="campaign-card__confetti" aria-hidden="true">
                            <i class="cf1"></i><i class="cf2"></i><i class="cf3"></i><i class="cf4"></i><i class="cf5"></i>
                        </div>
                        <div class="campaign-card__top">
                            <span>National prize season</span>
                            <b><i></i> LIVE</b>
                        </div>

                        <div class="campaign-card__main">
                            <div class="trophy" aria-hidden="true">
                                <span>🏆</span>
                            </div>
                            <div class="prize-count">
                                <strong class="num">60<span>+</span></strong>
                                <b>Exciting prizes</b>
                                <small>Rewards worth ₹5 lakh+</small>
                            </div>
                        </div>

                        <div class="campaign-prizes">
                            <span><i>🔊</i><b>Alexa</b></span>
                            <span><i>📱</i><b>Tablet</b></span>
                            <span><i>⌚</i><b>Smart watch</b></span>
                        </div>

                        <div class="campaign-card__foot">
                            <span>✓</span>
                            Every student earns a rank + certificate
                        </div>
                    </div>
                </div>
            </div>

            <svg class="wave" viewBox="0 0 1440 80" preserveAspectRatio="none">
                <path d="M0 40C240 80 480 0 720 24C960 48 1200 80 1440 48V80H0V40Z" fill="#0A1024" />
            </svg>
        </section>

        <!-- ══ stats ══ -->
        <section class="stats">
            <div class="wrap stats__grid">
                <div v-for="s in stats" :key="s.label" class="stat">
                    <div class="num">{{ s.display }}</div>
                    <small>{{ s.label }}</small>
                </div>
            </div>
        </section>

        <!-- ══ prizes ══ -->
        <PrizeShowcase @register="openRegister" />

        <!-- ══ why participate ══ -->
        <section class="section">
            <div class="wrap">
                <div class="shead center" data-reveal>
                    <span class="eyebrow" style="justify-content:center">Why take part</span>
                    <h2>More than a <span class="ital">medal</span></h2>
                    <p>The prizes get you in the door. What students actually keep is the rank, the report and the habit of competing nationally.</p>
                </div>

                <div class="ben-grid">
                    <article v-for="(b, i) in benefits" :key="b.title" class="ben" data-reveal :style="{ transitionDelay: i * 100 + 'ms' }">
                        <span class="ben__ic">{{ b.icon }}</span>
                        <h3>{{ b.title }}</h3>
                        <p>{{ b.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- ══ how it works ══ -->
        <section class="section section--ink">
            <div class="bg-grid"></div>
            <div class="wrap">
                <div class="shead center" data-reveal>
                    <span class="eyebrow light" style="justify-content:center">How it works</span>
                    <h2>Three steps, start to <span class="gold-ital">rank</span></h2>
                </div>

                <div class="steps">
                    <article v-for="(s, i) in steps" :key="s.n" class="step" data-reveal :style="{ transitionDelay: i * 130 + 'ms' }">
                        <span class="step__n glass-dark num">{{ s.n }}</span>
                        <h3>{{ s.title }}</h3>
                        <p>{{ s.body }}</p>
                    </article>
                </div>

                <div class="steps__cta" data-reveal>
                    <button class="btn btn-gold btn-shine" type="button" @click="openRegister">Register Now</button>
                </div>
            </div>
        </section>

        <!-- ══ olympiads on offer ══ -->
        <section v-if="subjects.length" class="section section--paper2">
            <div class="wrap">
                <div class="shead center" data-reveal>
                    <span class="eyebrow" style="justify-content:center">On offer this season</span>
                    <h2>Pick as many as you <span class="ital">like</span></h2>
                    <p>Choose one subject or all of them — you select your olympiads and pay for the whole lot in a single step when you register.</p>
                </div>

                <div class="subj-grid">
                    <button v-for="(s, i) in subjects" :key="s.name" type="button" class="subj" data-reveal
                            :style="{ transitionDelay: (i % 4) * 90 + 'ms' }" @click="openRegister">
                        <span class="subj__bar" :style="{ background: s.color }"></span>
                        <b>{{ s.name }}</b>
                        <small>{{ s.count }} olympiad<span v-if="s.count !== 1">s</span></small>
                        <span class="subj__fee num">{{ s.from > 0 ? `from ${inr(s.from)}` : 'Free' }}</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- ══ testimonials ══ -->
        <section class="section tsection">
            <div class="bg-grid"></div>
            <div class="wrap">
                <div class="shead center" data-reveal>
                    <span class="eyebrow light" style="justify-content:center">What families say</span>
                    <h2>Worth the <span class="gold-ital">hour</span></h2>
                </div>

                <div class="tgrid">
                    <article v-for="(t, i) in testimonials" :key="t.name" class="tcard glass-dark" data-reveal
                             :style="{ transitionDelay: i * 120 + 'ms' }">
                        <p class="tcard__q">“{{ t.quote }}”</p>
                        <div class="tcard__who">
                            <span class="tcard__av" :style="{ background: t.tone }">{{ t.name.charAt(0) }}</span>
                            <div>
                                <b>{{ t.name }}</b>
                                <small>{{ t.role }}</small>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- ══ faq ══ -->
        <section class="section">
            <div class="wrap">
                <div class="shead center" data-reveal>
                    <span class="eyebrow" style="justify-content:center">Questions</span>
                    <h2>Everything you need to <span class="ital">know</span></h2>
                </div>

                <div class="acc" data-reveal>
                    <div v-for="(f, i) in faqs" :key="f.q" class="acc__item" :class="{ open: openFaq === i }">
                        <button class="acc__q" type="button" :aria-expanded="openFaq === i" @click="toggleFaq(i)">
                            {{ f.q }}
                            <span class="acc__ic" aria-hidden="true"></span>
                        </button>
                        <div class="acc__a" :style="{ maxHeight: openFaq === i ? '340px' : '0' }">
                            <p>{{ f.a }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ final CTA ══ -->
        <section class="cta">
            <div class="wrap">
                <div class="cta__inner" data-reveal>
                    <h2>Registrations close soon. <span class="ital">Claim your seat.</span></h2>
                    <p>One form. One payment. A national rank, a certificate and a shot at 60+ prizes.</p>
                    <div class="cta__btns">
                        <button class="btn btn-ghost light" type="button" @click="openRegister">Register Now</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ footer ══ -->
        <footer>
            <div class="wrap foot-inner">
                <AppLogo :size="36" variant="light" />
                <p>National Olympiad Hunt · Online olympiad exams for Class 1–12 students across India.</p>
                <div class="foot-meta">
                    <a href="mailto:support@neoexam.org">support@neoexam.org</a>
                    <span>© {{ year }} National Olympiad Hunt</span>
                </div>
            </div>
        </footer>

        <SocialProofToast :paused="modalOpen" />

        <RegisterModal
            :open="modalOpen"
            :class-levels="classLevels"
            :exams="exams"
            :referral="referral"
            :referred-by="referredBy"
            :program="program"
            @close="modalOpen = false"
        />
    </div>
</template>

<style scoped>
/* ============================================================
   NATIONAL OLYMPIAD HUNT — /marketing campaign landing page
   v2 "Editorial / Prestige" · scoped to .noh
   ============================================================ */
.noh{
  --ink:#0A1024; --ink-2:#131C3D; --ink-soft:#2A335A;
  --paper:#FBF6EC; --paper-2:#F3E9D6; --paper-line:#E7D9BE;
  --saffron:#EE6A2C; --saffron-dk:#C9501A; --gold:#D6991F; --gold-lt:#F2C84B;
  --royal:#2C49A6; --emerald:#168A66; --plum:#6C3FA0;
  --ink-70:rgba(10,16,36,.70); --ink-55:rgba(10,16,36,.55); --ink-35:rgba(10,16,36,.35); --ink-12:rgba(10,16,36,.12);
  --paper-45:rgba(251,246,236,.45); --paper-70:rgba(251,246,236,.72);
  --shadow-sm:0 2px 8px rgba(10,16,36,.06); --shadow-md:0 14px 40px -12px rgba(10,16,36,.22); --shadow-lg:0 40px 80px -28px rgba(10,16,36,.40);
  --r-sm:12px; --r-md:18px; --r-lg:28px; --r-xl:40px;
  --display:"Fraunces",Georgia,serif; --body:"Plus Jakarta Sans",system-ui,sans-serif; --mono:"Space Grotesk",monospace;
  --maxw:1240px;

  font-family:var(--body); background:var(--paper); color:var(--ink); line-height:1.6;
  overflow-x:hidden; -webkit-font-smoothing:antialiased; padding-top:74px;
  background-image:radial-gradient(var(--ink-12) .5px, transparent .5px);
  background-size:22px 22px;
}
.noh *{ box-sizing:border-box; }
.noh ::selection{ background:var(--saffron); color:#fff; }
.noh a{ color:inherit; text-decoration:none; }
.noh svg{ display:block; }

.wrap{ width:100%; max-width:var(--maxw); margin-inline:auto; padding-inline:24px; }
.eyebrow{ font:600 12px/1 var(--body); letter-spacing:.22em; text-transform:uppercase; display:inline-flex; align-items:center; gap:10px; color:var(--saffron-dk); }
.eyebrow::before{ content:""; width:26px; height:2px; background:var(--saffron); border-radius:2px; }
.eyebrow.light{ color:var(--gold-lt); }
.eyebrow.light::before{ background:var(--gold); }
.num{ font-family:var(--mono); font-variant-numeric:tabular-nums; }
.ital{ font-style:italic; color:var(--saffron-dk); font-weight:500; }
.gold-ital{ font-style:italic; color:var(--gold-lt); font-weight:500; }

/* glass utilities */
.glass{ background:rgba(251,246,236,.55); backdrop-filter:blur(14px) saturate(150%); -webkit-backdrop-filter:blur(14px) saturate(150%); border:1px solid rgba(255,255,255,.55); box-shadow:inset 0 1px 0 rgba(255,255,255,.6), var(--shadow-sm); }
.glass-dark{ background:rgba(255,255,255,.06); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.10); }

/* buttons */
.btn{ display:inline-flex; align-items:center; justify-content:center; gap:9px; font:700 15px/1 var(--body); padding:15px 26px; border-radius:100px; cursor:pointer; border:1.5px solid transparent; transition:.28s cubic-bezier(.2,.8,.2,1); white-space:nowrap; position:relative; overflow:hidden; font-family:var(--body); }
.btn svg{ width:18px; height:18px; }
.btn-primary{ background:var(--saffron); color:#fff; box-shadow:0 12px 26px -10px var(--saffron); }
.btn-primary:hover{ background:var(--saffron-dk); transform:translateY(-2px); box-shadow:0 18px 34px -10px var(--saffron); }
.btn-gold{ background:linear-gradient(135deg,var(--gold-lt),var(--gold)); color:#3a2a05; box-shadow:0 12px 26px -10px var(--gold); }
.btn-gold:hover{ transform:translateY(-2px); }
.btn-ghost.light{ background:transparent; color:#fff; border-color:rgba(255,255,255,.5); }
.btn-ghost.light:hover{ background:#fff; color:var(--saffron-dk); transform:translateY(-2px); }
.btn-shine::after{ content:""; position:absolute; top:0; left:-120%; width:60%; height:100%; background:linear-gradient(120deg,transparent,rgba(255,255,255,.45),transparent); transform:skewX(-20deg); transition:left .7s; }
.btn-shine:hover::after{ left:140%; }

/* header — logo + the single CTA, no nav links out of the funnel */
.mhead{ position:fixed; top:0; left:0; right:0; z-index:80; transition:.3s; background:transparent; border-bottom:1px solid transparent; }
.mhead.scrolled{ background:rgba(251,246,236,.72); backdrop-filter:blur(14px) saturate(140%); -webkit-backdrop-filter:blur(14px) saturate(140%); border-bottom-color:var(--paper-line); box-shadow:var(--shadow-sm); }
.mhead__inner{ display:flex; align-items:center; justify-content:space-between; height:74px; gap:20px; }
.mhead__brand{ display:flex; align-items:center; }
.mhead__cta{ padding:11px 22px; font-size:14px; }

/* hero */
.hero{ position:relative; padding:60px 0 0; overflow:hidden; }
.hero__blob{ position:absolute; border-radius:50%; filter:blur(70px); opacity:.5; z-index:0; pointer-events:none; }
.hero__blob.a{ width:520px; height:520px; background:radial-gradient(circle,#F6C45A,transparent 65%); top:-160px; right:-120px; animation:drift 14s ease-in-out infinite; }
.hero__blob.b{ width:440px; height:440px; background:radial-gradient(circle,#7C97F0,transparent 65%); bottom:60px; left:-140px; opacity:.4; animation:drift 18s ease-in-out infinite reverse; }
@keyframes drift{ 0%,100%{ transform:translate(0,0);} 50%{ transform:translate(30px,-30px);} }
.float-shape{ position:absolute; z-index:1; pointer-events:none; animation:floaty 7s ease-in-out infinite; }
.float-shape.s1{ width:80px; height:80px; top:90px; left:8%; }
.float-shape.s2{ width:54px; height:54px; top:240px; right:12%; animation-delay:1.2s; }
.float-shape.s3{ width:40px; height:40px; bottom:160px; left:18%; animation-delay:.6s; }
.float-shape.s4{ width:50px; height:50px; top:120px; right:32%; animation-delay:2s; }
@keyframes floaty{ 0%,100%{ transform:translateY(0) rotate(0);} 50%{ transform:translateY(-18px) rotate(8deg);} }

.hero__grid{ position:relative; z-index:2; display:grid; grid-template-columns:1.05fr .95fr; gap:50px; align-items:center; padding-bottom:120px; }
.hero__badge{ display:inline-flex; align-items:center; gap:9px; padding:9px 14px; border-radius:100px; font:700 12px/1 var(--body); letter-spacing:.05em; text-transform:uppercase; color:var(--ink-70); margin-bottom:26px; }
.hero__badge i{ width:8px; height:8px; flex:none; border-radius:50%; background:var(--emerald); box-shadow:0 0 0 5px rgba(22,138,102,.12); animation:pulse-dot 1.8s ease-out infinite; }
@keyframes pulse-dot{ 0%,100%{ box-shadow:0 0 0 4px rgba(22,138,102,.12); } 50%{ box-shadow:0 0 0 8px rgba(22,138,102,0); } }
.hero h1{ font-family:var(--display); font-weight:600; font-size:clamp(38px,5.6vw,70px); line-height:.98; letter-spacing:-.025em; }
.hero h1 .ital{ font-weight:500; }
.hero p.lede{ font-size:18px; color:var(--ink-70); max-width:570px; margin:26px 0 34px; }
.hero__cta{ display:flex; gap:18px; flex-wrap:wrap; align-items:center; }
.hero__cta-note{ font-size:13px; color:var(--ink-55); }
.hero__proof{ display:flex; align-items:center; gap:0; margin-top:30px; color:var(--ink-55); }
.hero__proof span{ display:flex; align-items:baseline; gap:4px; padding:0 15px; font-size:12px; line-height:1.2; border-left:1px solid var(--ink-12); }
.hero__proof span:first-child{ padding-left:0; border-left:0; }
.hero__proof b{ color:var(--ink); font-size:13px; }

.hero__visual{ position:relative; }
.campaign-card{ position:relative; isolation:isolate; overflow:hidden; padding:25px; border-radius:var(--r-xl); color:var(--paper); background:linear-gradient(150deg,#1b2748 0%,var(--ink-2) 45%,var(--ink) 100%); border:1px solid rgba(255,255,255,.1); box-shadow:var(--shadow-lg); transform:rotate(1.2deg); transition:transform .4s cubic-bezier(.2,.8,.2,1); }
.campaign-card:hover{ transform:rotate(0) translateY(-5px); }
.campaign-card::before{ content:""; position:absolute; inset:0; z-index:-1; background-image:radial-gradient(rgba(255,255,255,.055) 1px,transparent 1px); background-size:18px 18px; mask-image:linear-gradient(to bottom,#000,transparent 75%); }
.campaign-card__glow{ position:absolute; z-index:-1; width:440px; height:440px; border-radius:50%; top:-240px; right:-170px; background:radial-gradient(circle,var(--gold-lt),transparent 66%); opacity:.34; }
.campaign-card__top{ display:flex; align-items:center; justify-content:space-between; gap:16px; font:700 10px/1 var(--body); letter-spacing:.18em; text-transform:uppercase; color:var(--paper-45); }
.campaign-card__top b{ display:flex; align-items:center; gap:6px; padding:6px 9px; border-radius:100px; font-size:9px; letter-spacing:.12em; color:#9FF4D8; background:rgba(22,138,102,.16); border:1px solid rgba(22,138,102,.26); }
.campaign-card__top b i{ width:6px; height:6px; border-radius:50%; background:#5de4b8; box-shadow:0 0 10px #5de4b8; }
.campaign-card__main{ display:grid; grid-template-columns:.88fr 1.12fr; align-items:center; gap:10px; min-height:280px; }
.trophy{ position:relative; display:grid; place-items:center; }
.trophy::before,.trophy::after{ content:""; position:absolute; border-radius:50%; border:1px solid rgba(242,200,75,.18); }
.trophy::before{ width:180px; height:180px; }
.trophy::after{ width:130px; height:130px; border-style:dashed; animation:spin 18s linear infinite; }
@keyframes spin{ to{ transform:rotate(360deg); } }
.trophy span{ position:relative; z-index:1; font-size:98px; line-height:1; filter:drop-shadow(0 24px 26px rgba(0,0,0,.48)); animation:trophy-float 4.5s ease-in-out infinite; }
@keyframes trophy-float{ 0%,100%{ transform:translateY(3px) rotate(-3deg); } 50%{ transform:translateY(-8px) rotate(3deg); } }
.prize-count{ position:relative; display:flex; flex-direction:column; align-items:flex-start; }
.prize-count strong{ font-size:82px; font-weight:700; line-height:.82; letter-spacing:-.06em; color:var(--gold-lt); text-shadow:0 10px 35px rgba(214,153,31,.24); }
.prize-count strong span{ font-size:.52em; vertical-align:top; margin-left:2px; }
.prize-count > b{ margin-top:14px; font-family:var(--display); font-size:27px; line-height:1; font-weight:600; }
.prize-count small{ margin-top:9px; font-size:12px; color:var(--paper-45); }
.campaign-prizes{ display:grid; grid-template-columns:repeat(3,1fr); gap:9px; margin-top:2px; }
.campaign-prizes span{ display:flex; align-items:center; gap:9px; min-width:0; padding:11px; border-radius:13px; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.09); }
.campaign-prizes i{ width:31px; height:31px; flex:none; display:grid; place-items:center; border-radius:9px; font-size:17px; font-style:normal; background:rgba(242,200,75,.1); }
.campaign-prizes b{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11px; }
.campaign-card__foot{ display:flex; align-items:center; justify-content:center; gap:8px; margin-top:14px; padding:12px; border-radius:13px; font-size:11.5px; font-weight:600; color:var(--paper-70); background:rgba(22,138,102,.11); border:1px solid rgba(22,138,102,.2); }
.campaign-card__foot span{ display:grid; place-items:center; width:19px; height:19px; flex:none; border-radius:50%; color:var(--ink); background:#72e1bd; font-size:11px; font-weight:800; }
.campaign-card__confetti i{ position:absolute; display:block; width:7px; height:18px; border-radius:4px; opacity:.75; }
.campaign-card__confetti .cf1{ top:20%; left:7%; background:var(--saffron); transform:rotate(28deg); }
.campaign-card__confetti .cf2{ top:12%; left:44%; width:9px; height:9px; border-radius:50%; background:var(--royal); }
.campaign-card__confetti .cf3{ top:35%; right:7%; background:var(--gold-lt); transform:rotate(-38deg); }
.campaign-card__confetti .cf4{ bottom:26%; left:5%; width:10px; height:10px; background:var(--emerald); transform:rotate(18deg); }
.campaign-card__confetti .cf5{ bottom:12%; right:4%; width:9px; height:9px; border:2px solid var(--saffron); background:transparent; transform:rotate(35deg); }
.wave{ position:absolute; bottom:0; left:0; width:100%; height:80px; z-index:1; }

/* stats */
.stats{ background:var(--ink); color:var(--paper); position:relative; overflow:hidden; }
.stats::before{ content:""; position:absolute; inset:0; background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px); background-size:26px 26px; }
.stats__grid{ position:relative; display:grid; grid-template-columns:repeat(4,1fr); gap:30px; padding:54px 0; }
.stat{ text-align:center; position:relative; }
.stat:not(:last-child)::after{ content:""; position:absolute; right:-15px; top:14%; height:72%; width:1px; background:rgba(255,255,255,.1); }
.stat .num{ font-size:clamp(34px,4vw,52px); font-weight:700; line-height:1; background:linear-gradient(180deg,#fff,var(--gold-lt)); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; }
.stat small{ display:block; margin-top:10px; font-size:13px; letter-spacing:.05em; color:var(--paper-45); text-transform:uppercase; }

/* sections */
.section{ padding:104px 0; position:relative; overflow:hidden; }
.section--paper2{ background:var(--paper-2); }
.section--ink{ background:var(--ink); color:var(--paper); }
.section--ink .shead h2{ color:var(--paper); }
.bg-grid{ position:absolute; inset:0; width:100%; height:100%; pointer-events:none; opacity:.5;
  background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);
  background-size:48px 48px; mask-image:radial-gradient(ellipse 70% 60% at 50% 40%,#000,transparent); -webkit-mask-image:radial-gradient(ellipse 70% 60% at 50% 40%,#000,transparent); }
.shead{ max-width:680px; margin-bottom:54px; position:relative; }
.shead.center{ margin-inline:auto; text-align:center; }
.shead h2{ font-family:var(--display); font-weight:600; font-size:clamp(32px,4.4vw,52px); line-height:1.04; letter-spacing:-.02em; margin:18px 0 0; }
.shead p{ font-size:17px; color:var(--ink-70); margin-top:18px; }
.section--ink .shead p, .tsection .shead p{ color:var(--paper-45); }

/* benefits */
.ben-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:22px; }
.ben{ background:var(--paper-2); border:1px solid var(--paper-line); border-radius:var(--r-lg); padding:30px 26px; transition:transform .3s cubic-bezier(.2,.8,.2,1), box-shadow .3s; }
.ben:hover{ transform:translateY(-6px); box-shadow:var(--shadow-md); }
.ben__ic{ display:grid; place-items:center; width:52px; height:52px; border-radius:15px; background:var(--paper); border:1px solid var(--paper-line); font-size:24px; margin-bottom:20px; }
.ben h3{ font-family:var(--display); font-size:20px; font-weight:600; line-height:1.2; margin-bottom:10px; }
.ben p{ font-size:14px; color:var(--ink-70); }

/* steps */
.steps{ position:relative; display:grid; grid-template-columns:repeat(3,1fr); gap:34px; }
.steps::before{ content:""; position:absolute; top:28px; left:12%; right:12%; height:2px; background-image:linear-gradient(90deg,rgba(255,255,255,.22) 50%,transparent 50%); background-size:12px 2px; }
.step{ position:relative; text-align:center; }
.step__n{ display:inline-grid; place-items:center; width:56px; height:56px; border-radius:50%; font-size:17px; font-weight:700; color:var(--gold-lt); margin-bottom:22px; position:relative; z-index:1; background:var(--ink-2); }
.step h3{ font-family:var(--display); font-size:22px; font-weight:600; margin-bottom:10px; }
.step p{ font-size:14.5px; color:var(--paper-45); max-width:300px; margin-inline:auto; }
.steps__cta{ text-align:center; margin-top:52px; }

/* subjects */
.subj-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:18px; }
.subj{ position:relative; overflow:hidden; text-align:left; cursor:pointer; font-family:var(--body); color:var(--ink);
  background:var(--paper); border:1px solid var(--paper-line); border-radius:var(--r-md); padding:26px 24px 22px;
  transition:transform .3s cubic-bezier(.2,.8,.2,1), box-shadow .3s, border-color .3s; }
.subj:hover{ transform:translateY(-5px); box-shadow:var(--shadow-md); border-color:var(--ink-12); }
.subj__bar{ position:absolute; top:0; left:0; width:100%; height:4px; }
.subj b{ display:block; font-family:var(--display); font-size:20px; font-weight:600; line-height:1.2; }
.subj small{ display:block; font-size:13px; color:var(--ink-55); margin-top:6px; }
.subj__fee{ display:inline-block; margin-top:16px; font:700 13px/1 var(--mono); color:var(--saffron-dk); }

/* testimonials */
.tsection{ background:var(--ink); color:var(--paper); }
.tsection .shead h2{ color:var(--paper); }
.tgrid{ display:grid; grid-template-columns:repeat(3,1fr); gap:22px; }
.tcard{ border-radius:var(--r-lg); padding:32px 28px; display:flex; flex-direction:column; gap:24px; }
.tcard__q{ font-family:var(--display); font-size:18px; font-weight:500; line-height:1.45; flex:1; }
.tcard__who{ display:flex; align-items:center; gap:14px; padding-top:20px; border-top:1px solid rgba(255,255,255,.08); }
.tcard__av{ width:48px; height:48px; border-radius:50%; display:grid; place-items:center; font:700 16px/1 var(--body); color:#fff; flex:none; }
.tcard__who b{ font-size:15px; display:block; }
.tcard__who small{ font-size:12.5px; color:var(--paper-45); }

/* faq */
.acc{ border-top:1px solid var(--paper-line); max-width:820px; margin-inline:auto; }
.acc__item{ border-bottom:1px solid var(--paper-line); }
.acc__q{ width:100%; background:none; border:none; cursor:pointer; text-align:left; display:flex; align-items:center; justify-content:space-between; gap:20px; padding:24px 4px; font:600 17px/1.4 var(--body); color:var(--ink); }
.acc__ic{ width:32px; height:32px; border-radius:50%; flex:none; border:1.5px solid var(--ink-12); display:grid; place-items:center; transition:.3s; position:relative; }
.acc__ic::before,.acc__ic::after{ content:""; position:absolute; background:var(--ink); border-radius:2px; transition:.3s; }
.acc__ic::before{ width:13px; height:2px; }
.acc__ic::after{ width:2px; height:13px; }
.acc__item.open .acc__ic{ background:var(--saffron); border-color:var(--saffron); transform:rotate(90deg); }
.acc__item.open .acc__ic::before,.acc__item.open .acc__ic::after{ background:#fff; }
.acc__item.open .acc__ic::after{ transform:scaleY(0); }
.acc__a{ max-height:0; overflow:hidden; transition:max-height .4s cubic-bezier(.2,.8,.2,1); }
.acc__a p{ padding:0 4px 26px; font-size:15px; color:var(--ink-70); }

/* cta */
.cta{ padding:104px 0; }
.cta__inner{ position:relative; overflow:hidden; border-radius:var(--r-xl); background:linear-gradient(135deg,var(--saffron),var(--saffron-dk) 70%,#9c3d12); padding:70px 60px; text-align:center; color:#fff; box-shadow:var(--shadow-lg); }
.cta__inner::before{ content:""; position:absolute; inset:0; background-image:radial-gradient(rgba(255,255,255,.13) 1.5px,transparent 1.5px); background-size:28px 28px; }
.cta__inner::after{ content:""; position:absolute; width:380px; height:380px; border-radius:50%; background:radial-gradient(circle,var(--gold-lt),transparent 65%); opacity:.5; top:-180px; right:-80px; }
.cta h2{ position:relative; font-family:var(--display); font-weight:600; font-size:clamp(30px,4.5vw,52px); line-height:1.04; letter-spacing:-.02em; max-width:760px; margin:0 auto 18px; color:#fff; }
.cta h2 .ital{ color:#fff; }
.cta p{ position:relative; font-size:18px; opacity:.92; max-width:520px; margin:0 auto 34px; }
.cta__btns{ position:relative; display:flex; gap:14px; justify-content:center; flex-wrap:wrap; }

/* footer — intentionally link-free so the funnel has no exits */
footer{ background:var(--ink); color:var(--paper-70); padding:52px 0; }
.foot-inner{ display:flex; flex-direction:column; align-items:center; text-align:center; gap:16px; }
.foot-inner p{ font-size:14px; max-width:460px; color:var(--paper-45); }
.foot-meta{ display:flex; align-items:center; gap:22px; flex-wrap:wrap; justify-content:center; font-size:13px; color:var(--paper-45); padding-top:18px; border-top:1px solid rgba(255,255,255,.1); width:100%; }
.foot-meta a:hover{ color:var(--gold-lt); }

/* reveal */
[data-reveal]{ opacity:0; transform:translateY(28px); transition:opacity .7s cubic-bezier(.2,.8,.2,1), transform .7s cubic-bezier(.2,.8,.2,1); }
[data-reveal].in{ opacity:1; transform:none; }

/* responsive */
@media (max-width:1024px){
  .hero__grid{ grid-template-columns:1fr; gap:64px; padding-bottom:140px; }
  .hero__visual{ max-width:460px; margin-inline:auto; }
  .ben-grid{ grid-template-columns:1fr 1fr; }
  .subj-grid{ grid-template-columns:1fr 1fr; }
  .tgrid{ grid-template-columns:1fr; }
}
@media (max-width:860px){
  .stats__grid{ grid-template-columns:repeat(2,1fr); gap:40px 20px; }
  .stat:nth-child(2)::after{ display:none; }
  .steps{ grid-template-columns:1fr; gap:44px; }
  .steps::before{ display:none; }
  .float-shape{ display:none; }
}
@media (max-width:600px){
  .noh{ padding-top:62px; line-height:1.55; background-size:18px 18px; }
  .wrap{ padding-inline:16px; }
  .mhead__inner{ height:62px; gap:12px; }
  .mhead__brand :deep(img){ height:30px !important; max-width:190px; }
  .mhead__cta{ padding:10px 15px; font-size:12.5px; }

  .btn{ min-height:44px; padding:13px 20px; font-size:13.5px; }
  .btn svg{ width:16px; height:16px; }
  .eyebrow{ font-size:10px; letter-spacing:.18em; gap:8px; }
  .eyebrow::before{ width:20px; }

  .hero{ padding-top:34px; }
  .hero__blob.a{ width:320px; height:320px; top:-100px; right:-150px; filter:blur(54px); }
  .hero__blob.b{ width:280px; height:280px; left:-170px; bottom:20px; filter:blur(54px); }
  .hero__grid{ gap:48px; padding-bottom:88px; }
  .hero__badge{ gap:8px; padding:8px 11px; margin-bottom:20px; font-size:9.5px; letter-spacing:.04em; }
  .hero__badge i{ width:7px; height:7px; }
  .hero h1{ font-size:clamp(32px,10.7vw,42px); line-height:1.01; letter-spacing:-.02em; }
  .hero p.lede{ max-width:none; margin:20px 0 26px; font-size:14.5px; line-height:1.58; }
  .hero__cta{ gap:10px; }
  .hero__cta .btn{ width:100%; }
  .hero__cta-note{ width:100%; text-align:center; font-size:11px; }
  .hero__proof{ width:100%; justify-content:space-between; margin-top:22px; }
  .hero__proof span{ gap:3px; padding:0 9px; font-size:9px; }
  .hero__proof b{ font-size:9.5px; }

  .hero__visual{ width:100%; max-width:410px; }
  .campaign-card{ padding:18px; border-radius:24px; transform:none; }
  .campaign-card:hover{ transform:none; }
  .campaign-card__top{ font-size:8px; letter-spacing:.13em; }
  .campaign-card__top b{ padding:5px 7px; font-size:7.5px; }
  .campaign-card__main{ grid-template-columns:.86fr 1.14fr; min-height:190px; gap:6px; }
  .trophy::before{ width:116px; height:116px; }
  .trophy::after{ width:84px; height:84px; }
  .trophy span{ font-size:66px; }
  .prize-count strong{ font-size:54px; }
  .prize-count > b{ margin-top:9px; font-size:18px; }
  .prize-count small{ margin-top:6px; font-size:9.5px; }
  .campaign-prizes{ gap:6px; }
  .campaign-prizes span{ flex-direction:column; gap:5px; justify-content:center; padding:8px 5px; }
  .campaign-prizes i{ width:29px; height:29px; font-size:15px; }
  .campaign-prizes b{ max-width:100%; font-size:8.5px; }
  .campaign-card__foot{ gap:6px; margin-top:9px; padding:9px 7px; font-size:9.5px; }
  .campaign-card__foot span{ width:17px; height:17px; font-size:9px; }
  .wave{ height:48px; }

  .stats__grid{ gap:0; padding:30px 16px 34px; }
  .stat{ padding:14px 6px; }
  .stat:not(:last-child)::after{ right:0; top:18%; height:64%; }
  .stat:nth-child(2)::after{ display:none; }
  .stat:nth-child(-n+2){ border-bottom:1px solid rgba(255,255,255,.1); }
  .stat .num{ font-size:27px; }
  .stat small{ margin-top:7px; font-size:9px; line-height:1.35; letter-spacing:.06em; }

  .section{ padding:60px 0; }
  .shead{ margin-bottom:34px; }
  .shead h2{ margin-top:13px; font-size:clamp(28px,9vw,34px); line-height:1.08; }
  .shead p{ margin-top:13px; font-size:13.5px; line-height:1.58; }
  .ben-grid{ grid-template-columns:1fr; }
  .ben-grid{ gap:12px; }
  .ben{ border-radius:20px; padding:21px 19px; }
  .ben__ic{ width:43px; height:43px; margin-bottom:14px; border-radius:12px; font-size:20px; }
  .ben h3{ margin-bottom:7px; font-size:17px; }
  .ben p{ font-size:12.5px; line-height:1.55; }

  .steps{ gap:32px; }
  .step__n{ width:46px; height:46px; margin-bottom:14px; font-size:14px; }
  .step h3{ margin-bottom:7px; font-size:18px; }
  .step p{ max-width:320px; font-size:12.5px; line-height:1.55; }
  .steps__cta{ margin-top:34px; }

  .subj-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
  .subj{ min-width:0; padding:19px 14px 16px; border-radius:15px; }
  .subj b{ overflow-wrap:anywhere; font-size:16px; }
  .subj small{ font-size:10.5px; }
  .subj__fee{ margin-top:12px; font-size:11px; }

  .tgrid{ gap:12px; }
  .tcard{ gap:18px; padding:22px 19px; border-radius:20px; }
  .tcard__q{ font-size:15.5px; line-height:1.48; }
  .tcard__who{ gap:11px; padding-top:15px; }
  .tcard__av{ width:40px; height:40px; font-size:13px; }
  .tcard__who b{ font-size:12.5px; }
  .tcard__who small{ font-size:10.5px; }

  .acc__q{ gap:14px; padding:19px 2px; font-size:14px; line-height:1.45; }
  .acc__ic{ width:28px; height:28px; }
  .acc__a p{ padding:0 2px 21px; font-size:12.5px; line-height:1.6; }

  .cta{ padding:60px 0; }
  .cta__inner{ padding:40px 20px; border-radius:24px; }
  .cta__inner::after{ width:250px; height:250px; top:-130px; right:-110px; }
  .cta h2{ font-size:clamp(27px,8.8vw,33px); line-height:1.08; margin-bottom:13px; }
  .cta p{ margin-bottom:25px; font-size:13.5px; line-height:1.55; }
  .cta__btns .btn{ width:100%; }

  footer{ padding:42px 0 calc(38px + env(safe-area-inset-bottom)); }
  .foot-inner{ gap:13px; }
  .foot-inner :deep(img){ height:30px !important; max-width:210px; }
  .foot-inner p{ font-size:11.5px; line-height:1.55; }
  .foot-meta{ gap:10px 18px; padding-top:14px; font-size:10.5px; }
}
@media (max-width:360px){
  .wrap{ padding-inline:14px; }
  .mhead__brand :deep(img){ max-width:166px; }
  .mhead__cta{ padding-inline:12px; }
  .hero h1{ font-size:31px; }
  .hero__badge{ font-size:8.8px; }
  .hero__proof span{ padding-inline:6px; font-size:8.3px; }
  .hero__proof b{ font-size:8.8px; }
  .campaign-card{ padding-inline:14px; }
  .trophy span{ font-size:58px; }
  .trophy::before{ width:102px; height:102px; }
  .trophy::after{ width:74px; height:74px; }
  .prize-count strong{ font-size:49px; }
  .prize-count > b{ font-size:16px; }
  .subj-grid{ grid-template-columns:1fr; }
}
@media (prefers-reduced-motion: reduce){
  .noh *{ animation:none !important; }
  [data-reveal]{ opacity:1; transform:none; transition:none; }
}
</style>
