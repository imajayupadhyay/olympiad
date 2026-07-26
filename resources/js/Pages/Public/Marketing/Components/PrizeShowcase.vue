<script setup>
/**
 * "Win 60+ exciting prizes" — the pull of the campaign page.
 * Content is intentionally hardcoded here: this is the one place to edit when
 * the prize pool for a campaign changes.
 */
defineEmits(['register']);

const headline = {
    eyebrow: 'The prize pool',
    title: 'Win 60+ exciting prizes',
    lede: 'Every national olympiad season, the top performers across India walk away with rewards worth over ₹5,00,000 — and every single participant takes something home.',
};

// The showcase piece — one prize gets the spotlight tile.
const hero = {
    rank: 'National Rank 1',
    name: 'Amazon Alexa Echo Dot',
    note: 'Plus a ₹10,000 scholarship cheque and the Gold Medal of Merit.',
    emoji: '🔊',
};

const prizes = [
    { rank: 'Rank 2',        name: 'Android Tablet',        emoji: '📱', tone: 'gold' },
    { rank: 'Rank 3',        name: 'Smart Watch',           emoji: '⌚', tone: 'royal' },
    { rank: 'Rank 4 – 10',   name: 'Wireless Headphones',   emoji: '🎧', tone: 'plum' },
    { rank: 'Rank 11 – 25',  name: 'Digital Drawing Pad',   emoji: '🖊️', tone: 'emerald' },
    { rank: 'Rank 26 – 50',  name: '₹2,000 Gift Voucher',   emoji: '🎁', tone: 'saffron' },
    { rank: 'State Toppers', name: 'Premium Book Hamper',   emoji: '📚', tone: 'royal' },
    { rank: 'City Toppers',  name: 'Scientific Calculator', emoji: '🧮', tone: 'plum' },
    { rank: 'School Toppers', name: 'Trophy + Merit Medal', emoji: '🏆', tone: 'gold' },
];

// The "nobody leaves empty-handed" promise — the real conversion lever.
const everyone = [
    { icon: '📜', label: 'Digital participation certificate' },
    { icon: '📊', label: 'Detailed performance report' },
    { icon: '🇮🇳', label: 'Your official national rank' },
];
</script>

<template>
    <section id="prizes" class="section section--ink prizes">
        <div class="bg-grid"></div>

        <div class="wrap">
            <div class="shead center" data-reveal>
                <span class="eyebrow light" style="justify-content:center">{{ headline.eyebrow }}</span>
                <h2>Win <span class="gold-ital">60+ exciting</span> prizes</h2>
                <p>{{ headline.lede }}</p>
            </div>

            <div class="pz-grid">
                <!-- spotlight -->
                <article class="pz-hero" data-reveal>
                    <div class="pz-hero__glow"></div>
                    <span class="pz-hero__rank">{{ hero.rank }}</span>
                    <div class="pz-hero__emoji">{{ hero.emoji }}</div>
                    <h3>{{ hero.name }}</h3>
                    <p>{{ hero.note }}</p>
                    <button class="btn btn-gold btn-shine" type="button" @click="$emit('register')">
                        Enter the hunt
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </button>
                </article>

                <!-- the rest of the pool -->
                <div class="pz-list">
                    <article v-for="(p, i) in prizes" :key="p.name" class="pz-card glass-dark"
                             :class="p.tone" data-reveal :style="{ transitionDelay: (i % 4) * 90 + 'ms' }">
                        <span class="pz-card__emoji">{{ p.emoji }}</span>
                        <div>
                            <small>{{ p.rank }}</small>
                            <b>{{ p.name }}</b>
                        </div>
                    </article>
                </div>
            </div>

            <!-- every participant -->
            <div class="pz-all" data-reveal>
                <p class="pz-all__lead">And every single participant gets</p>
                <ul>
                    <li v-for="e in everyone" :key="e.label">
                        <span>{{ e.icon }}</span>{{ e.label }}
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Scoped CSS doesn't reach into a child component, so this section carries its
   own copy of the v2 scaffolding it needs (same tokens as the page root). */
.prizes{
  --ink:#0A1024; --paper:#FBF6EC;
  --gold:#D6991F; --gold-lt:#F2C84B; --saffron:#EE6A2C;
  --paper-45:rgba(251,246,236,.45); --paper-70:rgba(251,246,236,.72);
  --display:"Fraunces",Georgia,serif; --body:"Plus Jakarta Sans",system-ui,sans-serif; --mono:"Space Grotesk",monospace;
  --maxw:1240px;

  padding:104px 0; position:relative; overflow:hidden;
  background:var(--ink); color:var(--paper);
  font-family:var(--body); line-height:1.6;
}
.prizes *{ box-sizing:border-box; }
.prizes svg{ display:block; }

.wrap{ width:100%; max-width:var(--maxw); margin-inline:auto; padding-inline:24px; position:relative; }
.bg-grid{ position:absolute; inset:0; width:100%; height:100%; pointer-events:none; opacity:.5;
  background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);
  background-size:48px 48px; mask-image:radial-gradient(ellipse 70% 60% at 50% 40%,#000,transparent); -webkit-mask-image:radial-gradient(ellipse 70% 60% at 50% 40%,#000,transparent); }
.eyebrow{ font:600 12px/1 var(--body); letter-spacing:.22em; text-transform:uppercase; display:inline-flex; align-items:center; gap:10px; color:var(--gold-lt); }
.eyebrow::before{ content:""; width:26px; height:2px; background:var(--gold); border-radius:2px; }
.shead{ max-width:680px; margin-bottom:54px; position:relative; }
.shead.center{ margin-inline:auto; text-align:center; }
.shead h2{ font-family:var(--display); font-weight:600; font-size:clamp(32px,4.4vw,52px); line-height:1.04; letter-spacing:-.02em; margin:18px 0 0; color:var(--paper); }
.shead p{ font-size:17px; color:var(--paper-45); margin-top:18px; }
.glass-dark{ background:rgba(255,255,255,.06); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.10); }
.btn{ display:inline-flex; align-items:center; justify-content:center; gap:9px; font:700 15px/1 var(--body); padding:15px 26px; border-radius:100px; cursor:pointer; border:1.5px solid transparent; transition:.28s cubic-bezier(.2,.8,.2,1); white-space:nowrap; position:relative; overflow:hidden; }
.btn svg{ width:18px; height:18px; }
.btn-gold{ background:linear-gradient(135deg,var(--gold-lt),var(--gold)); color:#3a2a05; box-shadow:0 12px 26px -10px var(--gold); }
.btn-gold:hover{ transform:translateY(-2px); }
.btn-shine::after{ content:""; position:absolute; top:0; left:-120%; width:60%; height:100%; background:linear-gradient(120deg,transparent,rgba(255,255,255,.45),transparent); transform:skewX(-20deg); transition:left .7s; }
.btn-shine:hover::after{ left:140%; }
[data-reveal]{ opacity:0; transform:translateY(28px); transition:opacity .7s cubic-bezier(.2,.8,.2,1), transform .7s cubic-bezier(.2,.8,.2,1); }
[data-reveal].in{ opacity:1; transform:none; }

.gold-ital{ font-style:italic; font-weight:500; color:var(--gold-lt); }

.pz-grid{ display:grid; grid-template-columns:.85fr 1.15fr; gap:26px; align-items:stretch; }

/* spotlight tile */
.pz-hero{
  position:relative; overflow:hidden; border-radius:28px; padding:38px 34px;
  background:linear-gradient(160deg,rgba(242,200,75,.16),rgba(214,153,31,.05) 60%,transparent);
  border:1px solid rgba(242,200,75,.3);
  display:flex; flex-direction:column; align-items:flex-start;
}
.pz-hero__glow{ position:absolute; width:340px; height:340px; border-radius:50%; top:-160px; right:-120px; background:radial-gradient(circle,var(--gold-lt),transparent 65%); opacity:.28; pointer-events:none; }
.pz-hero__rank{ position:relative; font:700 11px/1 var(--body); letter-spacing:.18em; text-transform:uppercase; color:#3a2a05; background:linear-gradient(135deg,var(--gold-lt),var(--gold)); padding:7px 13px; border-radius:100px; }
.pz-hero__emoji{ position:relative; font-size:64px; line-height:1; margin:28px 0 18px; filter:drop-shadow(0 12px 24px rgba(0,0,0,.45)); }
.pz-hero h3{ position:relative; font-family:var(--display); font-weight:600; font-size:30px; line-height:1.1; letter-spacing:-.02em; }
.pz-hero p{ position:relative; font-size:14.5px; color:rgba(251,246,236,.55); margin:12px 0 28px; }
.pz-hero .btn{ position:relative; margin-top:auto; }

/* the pool */
.pz-list{ display:grid; grid-template-columns:1fr 1fr; gap:14px; align-content:start; }
.pz-card{
  display:flex; align-items:center; gap:15px; padding:18px 20px; border-radius:18px;
  transition:transform .3s cubic-bezier(.2,.8,.2,1), border-color .3s, background .3s;
}
.pz-card:hover{ transform:translateY(-4px); border-color:rgba(255,255,255,.22); background:rgba(255,255,255,.09); }
.pz-card__emoji{ width:48px; height:48px; flex:none; border-radius:14px; display:grid; place-items:center; font-size:24px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1); }
.pz-card small{ display:block; font:700 10.5px/1 var(--body); letter-spacing:.16em; text-transform:uppercase; color:rgba(251,246,236,.45); margin-bottom:6px; }
.pz-card b{ font-size:15px; font-weight:700; line-height:1.25; }

/* a whisper of colour per tone, so the grid doesn't read as eight grey boxes */
.pz-card.gold .pz-card__emoji{ background:rgba(214,153,31,.16); }
.pz-card.royal .pz-card__emoji{ background:rgba(44,73,166,.24); }
.pz-card.plum .pz-card__emoji{ background:rgba(108,63,160,.24); }
.pz-card.emerald .pz-card__emoji{ background:rgba(22,138,102,.22); }
.pz-card.saffron .pz-card__emoji{ background:rgba(238,106,44,.2); }

/* everyone wins */
.pz-all{ margin-top:44px; padding:28px 32px; border-radius:24px; border:1px dashed rgba(255,255,255,.16); display:flex; align-items:center; gap:34px; flex-wrap:wrap; justify-content:center; text-align:center; }
.pz-all__lead{ font-family:var(--display); font-size:20px; font-weight:600; font-style:italic; color:var(--gold-lt); }
.pz-all ul{ display:flex; gap:30px; flex-wrap:wrap; justify-content:center; list-style:none; margin:0; padding:0; }
.pz-all li{ display:flex; align-items:center; gap:10px; font-size:14.5px; color:rgba(251,246,236,.72); }
.pz-all li span{ font-size:19px; }

@media (max-width:1024px){
  .pz-grid{ grid-template-columns:1fr; }
}
@media (max-width:600px){
  .prizes{ padding:60px 0; line-height:1.55; }
  .wrap{ padding-inline:16px; }
  .eyebrow{ font-size:10px; letter-spacing:.18em; gap:8px; }
  .eyebrow::before{ width:20px; }
  .shead{ margin-bottom:34px; }
  .shead h2{ margin-top:13px; font-size:clamp(28px,9vw,34px); line-height:1.08; }
  .shead p{ margin-top:13px; font-size:13.5px; line-height:1.58; }
  .pz-grid{ gap:14px; }
  .pz-hero{ padding:25px 20px; border-radius:22px; }
  .pz-hero__rank{ padding:6px 10px; font-size:9px; letter-spacing:.14em; }
  .pz-hero__emoji{ margin:20px 0 13px; font-size:46px; }
  .pz-hero h3{ font-size:23px; }
  .pz-hero p{ margin:9px 0 21px; font-size:12.5px; line-height:1.55; }
  .pz-hero .btn{ min-height:44px; padding:13px 19px; font-size:13px; }
  .pz-list{ grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; }
  .pz-card{ min-width:0; align-items:flex-start; gap:9px; padding:13px 10px; border-radius:14px; }
  .pz-card__emoji{ width:35px; height:35px; border-radius:10px; font-size:17px; }
  .pz-card > div{ min-width:0; }
  .pz-card small{ margin-bottom:4px; font-size:8px; line-height:1.25; letter-spacing:.1em; }
  .pz-card b{ display:block; overflow-wrap:anywhere; font-size:11px; line-height:1.3; }
  .pz-all{ margin-top:24px; padding:20px 17px; gap:15px; border-radius:18px; justify-content:flex-start; text-align:left; }
  .pz-all__lead{ font-size:16px; }
  .pz-all ul{ flex-direction:column; gap:10px; align-items:flex-start; text-align:left; }
  .pz-all li{ gap:8px; font-size:11.5px; }
  .pz-all li span{ font-size:16px; }
}
@media (max-width:360px){
  .wrap{ padding-inline:14px; }
  .pz-list{ grid-template-columns:1fr; }
  .pz-card{ align-items:center; padding:13px; }
}
</style>
