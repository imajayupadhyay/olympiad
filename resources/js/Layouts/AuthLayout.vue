<script setup>
defineProps({
    eyebrow: { type: String, default: 'Student Portal' },
    heading: { type: String, default: '' },
    subheading: { type: String, default: '' },
});
</script>

<template>
    <div class="noh-auth min-h-screen w-full flex">
        <!-- ───────────── Brand showcase (left) ───────────── -->
        <aside class="brand-panel">
            <!-- decorative background art -->
            <div class="blob blob-a"></div>
            <div class="blob blob-b"></div>
            <div class="grid-mask"></div>
            <span class="float-shape shape-1">◆</span>
            <span class="float-shape shape-2">★</span>
            <span class="float-shape shape-3">●</span>

            <div class="brand-inner">
                <a href="/" class="brand-logo">
                    <span class="brand-mark">NOH</span>
                    <span class="brand-name">National Olympiad Hunt</span>
                </a>

                <div class="brand-hero">
                    <div class="trophy">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6" />
                            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18" />
                            <path d="M4 22h16" />
                            <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22" />
                            <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22" />
                            <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z" />
                        </svg>
                    </div>

                    <h1 class="brand-title">
                        Where India's <span class="ital">brightest</span> compete.
                    </h1>
                    <p class="brand-sub">
                        Join lakhs of students across Class 1–12 in national olympiads
                        for Maths, Science, English & more — earn ranks, medals and
                        certificates that matter.
                    </p>

                    <ul class="brand-feats">
                        <li><span class="dot dot-saffron"></span> Timed online exams from anywhere</li>
                        <li><span class="dot dot-gold"></span> National, state &amp; school-level ranks</li>
                        <li><span class="dot dot-emerald"></span> Instant scorecards &amp; certificates</li>
                    </ul>
                </div>

                <div class="brand-stats">
                    <div><strong>50K+</strong><span>Students</span></div>
                    <div><strong>8</strong><span>Subjects</span></div>
                    <div><strong>12</strong><span>Class levels</span></div>
                </div>
            </div>
        </aside>

        <!-- ───────────── Form side (right) ───────────── -->
        <main class="form-panel">
            <div class="form-shell">
                <a href="/" class="form-logo-mobile">
                    <span class="brand-mark">NOH</span>
                    <span>National Olympiad Hunt</span>
                </a>

                <header class="form-head">
                    <p class="eyebrow">{{ eyebrow }}</p>
                    <h2 class="form-title">{{ heading }}</h2>
                    <p v-if="subheading" class="form-subtitle">{{ subheading }}</p>
                </header>

                <slot />
            </div>
        </main>
    </div>
</template>

<style scoped>
.noh-auth {
    --ink:        #0A1024;
    --ink-2:      #131C3D;
    --ink-soft:   #2A335A;
    --paper:      #FBF6EC;
    --paper-2:    #F3E9D6;
    --paper-line: #E7D9BE;
    --saffron:    #EE6A2C;
    --gold:       #D6991F;
    --gold-lt:    #F2C84B;
    --emerald:    #168A66;
    --display: "Fraunces", Georgia, serif;
    --body: "Plus Jakarta Sans", system-ui, sans-serif;
    --mono: "Space Grotesk", monospace;
    font-family: var(--body);
    background: var(--paper);
}

/* ── brand panel ── */
.brand-panel {
    position: relative;
    display: none;
    width: 46%;
    overflow: hidden;
    background:
        radial-gradient(120% 120% at 0% 0%, #1B2748 0%, #131C3D 38%, #0A1024 100%);
    color: var(--paper);
}
@media (min-width: 1024px) { .brand-panel { display: block; } }

.brand-inner {
    position: relative;
    z-index: 2;
    height: 100%;
    padding: 3rem 3.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.brand-logo { display: inline-flex; align-items: center; gap: .7rem; text-decoration: none; }
.brand-mark {
    font-family: var(--display);
    font-weight: 700;
    letter-spacing: .04em;
    color: var(--ink);
    background: linear-gradient(135deg, var(--gold-lt), var(--gold));
    padding: .35rem .6rem;
    border-radius: 12px;
    font-size: .95rem;
    box-shadow: 0 8px 22px -8px rgba(214,153,31,.7);
}
.brand-name { font-weight: 700; font-size: .95rem; color: rgba(251,246,236,.92); }

.brand-hero { max-width: 30rem; }
.trophy {
    width: 64px; height: 64px;
    display: grid; place-items: center;
    border-radius: 18px;
    color: var(--gold-lt);
    background: rgba(242,200,75,.10);
    border: 1px solid rgba(242,200,75,.25);
    margin-bottom: 1.6rem;
    animation: floaty 5s ease-in-out infinite;
}
.trophy svg { width: 32px; height: 32px; }

.brand-title {
    font-family: var(--display);
    font-weight: 600;
    font-size: clamp(2rem, 3.2vw, 2.9rem);
    line-height: 1.08;
    margin: 0 0 1rem;
}
.brand-title .ital { font-style: italic; color: var(--gold-lt); }
.brand-sub { color: rgba(251,246,236,.66); font-size: 1.02rem; line-height: 1.6; margin: 0 0 1.8rem; }

.brand-feats { list-style: none; margin: 0; padding: 0; display: grid; gap: .8rem; }
.brand-feats li { display: flex; align-items: center; gap: .7rem; font-size: .96rem; color: rgba(251,246,236,.85); }
.dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.dot-saffron { background: var(--saffron); box-shadow: 0 0 0 4px rgba(238,106,44,.18); }
.dot-gold { background: var(--gold-lt); box-shadow: 0 0 0 4px rgba(242,200,75,.18); }
.dot-emerald { background: var(--emerald); box-shadow: 0 0 0 4px rgba(22,138,102,.18); }

.brand-stats { display: flex; gap: 2.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(251,246,236,.12); }
.brand-stats div { display: flex; flex-direction: column; }
.brand-stats strong { font-family: var(--mono); font-size: 1.6rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--gold-lt); }
.brand-stats span { font-size: .8rem; color: rgba(251,246,236,.55); }

/* decorative art */
.blob { position: absolute; border-radius: 50%; filter: blur(60px); opacity: .5; z-index: 1; }
.blob-a { width: 460px; height: 460px; top: -140px; right: -120px; background: radial-gradient(circle, rgba(238,106,44,.55), transparent 70%); animation: drift 16s ease-in-out infinite; }
.blob-b { width: 380px; height: 380px; bottom: -130px; left: -90px; background: radial-gradient(circle, rgba(44,73,166,.55), transparent 70%); animation: drift 20s ease-in-out infinite reverse; }
.grid-mask {
    position: absolute; inset: 0; z-index: 1; opacity: .5;
    background-image:
        linear-gradient(rgba(251,246,236,.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(251,246,236,.06) 1px, transparent 1px);
    background-size: 44px 44px;
    -webkit-mask-image: radial-gradient(120% 90% at 70% 10%, #000 30%, transparent 75%);
            mask-image: radial-gradient(120% 90% at 70% 10%, #000 30%, transparent 75%);
}
.float-shape { position: absolute; z-index: 1; color: rgba(242,200,75,.35); font-size: 1.5rem; }
.shape-1 { top: 22%; right: 18%; animation: floaty 7s ease-in-out infinite; }
.shape-2 { top: 55%; right: 30%; font-size: 1.1rem; color: rgba(238,106,44,.4); animation: floaty 9s ease-in-out infinite .6s; }
.shape-3 { bottom: 22%; right: 14%; font-size: .8rem; color: rgba(251,246,236,.3); animation: floaty 6s ease-in-out infinite .3s; }

/* ── form panel ── */
.form-panel {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1.5rem;
    background:
        radial-gradient(80% 60% at 100% 0%, rgba(238,106,44,.06), transparent 60%),
        var(--paper);
}
.form-shell { width: 100%; max-width: 30rem; animation: fadeUp .6s cubic-bezier(.2,.7,.2,1) both; }

.form-logo-mobile { display: flex; align-items: center; gap: .6rem; font-weight: 700; color: var(--ink); text-decoration: none; margin-bottom: 2rem; }
@media (min-width: 1024px) { .form-logo-mobile { display: none; } }

.form-head { margin-bottom: 1.8rem; }
.eyebrow {
    display: inline-block;
    font-family: var(--mono); font-weight: 600; font-size: .72rem;
    letter-spacing: .14em; text-transform: uppercase;
    color: var(--saffron);
    background: rgba(238,106,44,.10);
    padding: .3rem .7rem; border-radius: 999px;
    margin: 0 0 .9rem;
}
.form-title { font-family: var(--display); font-weight: 600; font-size: 2rem; line-height: 1.1; color: var(--ink); margin: 0; }
.form-subtitle { color: rgba(10,16,36,.6); margin: .6rem 0 0; font-size: .98rem; }

@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
@keyframes floaty { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
@keyframes drift { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(20px,30px) scale(1.08); } }
</style>
