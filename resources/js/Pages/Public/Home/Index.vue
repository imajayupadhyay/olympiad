<template>
  <div class="noh" ref="rootEl">

    <!-- ============ MARQUEE ============ -->
    <div class="marquee">
      <div class="marquee__track">
        <template v-for="n in 2" :key="n">
          <span v-for="(m, i) in marquee" :key="n + '-' + i"><i class="dot"></i> {{ m.icon }} {{ m.text }} <b>{{ m.hl }}</b></span>
        </template>
      </div>
    </div>

    <!-- ============ NAVBAR ============ -->
    <header class="nav" :class="{ scrolled }">
      <div class="wrap nav__inner">
        <Link href="/" class="brand">
          <span class="brand__mark">
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 2l2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 19.3 7.2 17l.9-5.4L4.2 7.7l5.4-.8L12 2z" fill="#F2C84B"/><path d="M8 19h8v2.5a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5V19z" fill="#EE6A2C"/></svg>
          </span>
          <span>National Olympiad Hunt<small>Est. 2026 · India</small></span>
        </Link>
        <nav class="nav__links">
          <a v-for="l in navLinks" :key="l.id" :href="'#' + l.id" :class="{ active: activeSection === l.id }">{{ l.label }}</a>
        </nav>
        <div class="nav__cta">
          <Link href="/login" class="btn btn-ghost nav-login">Log In</Link>
          <Link href="/register" class="btn btn-primary">Register Free
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </Link>
          <button class="burger" :class="{ open: mobileOpen }" @click="mobileOpen = !mobileOpen" aria-label="Menu"><span></span><span></span><span></span></button>
        </div>
      </div>
    </header>
    <div class="mobile-menu" :class="{ open: mobileOpen }">
      <a v-for="l in navLinks" :key="l.id" :href="'#' + l.id" @click="mobileOpen = false">{{ l.label }}</a>
      <Link href="/register" class="btn btn-primary">Register Free</Link>
    </div>

    <!-- ============ HERO ============ -->
    <section class="hero" id="home">
      <div class="hero__blob a"></div>
      <div class="hero__blob b"></div>
      <!-- decorative floating shapes -->
      <svg class="float-shape s1" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="40" r="36" stroke="#EE6A2C" stroke-width="2" stroke-dasharray="6 8" opacity=".5"/></svg>
      <svg class="float-shape s2" viewBox="0 0 60 60" fill="none"><path d="M30 4l26 48H4L30 4z" stroke="#2C49A6" stroke-width="2" opacity=".4"/></svg>
      <svg class="float-shape s3" viewBox="0 0 40 40" fill="none"><path d="M20 2v36M2 20h36" stroke="#D6991F" stroke-width="3" stroke-linecap="round" opacity=".5"/></svg>
      <svg class="float-shape s4" viewBox="0 0 50 50" fill="none"><rect x="6" y="6" width="38" height="38" rx="10" stroke="#168A66" stroke-width="2" opacity=".4"/></svg>

      <div class="wrap hero__grid">
        <div class="hero__copy" data-reveal>
          <div class="hero__badge glass">India's largest school olympiad <b>NEW · 2026</b></div>
          <h1>
            Where India's<br>
            <span class="underline">young minds
              <svg viewBox="0 0 300 16" preserveAspectRatio="none" fill="none"><path d="M2 12C60 4 140 3 298 8" stroke="#EE6A2C" stroke-width="5" stroke-linecap="round"/></svg>
            </span><br>
            rise to the <span class="ital">top.</span>
          </h1>
          <p class="lede">Compete in national olympiads across 8 subjects, from Class 1 to 12. Earn ranks, medals and scholarships — judged on one fair, transparent national stage.</p>
          <div class="hero__cta">
            <Link href="/register" class="btn btn-primary btn-shine">Start Your Journey
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </Link>
            <a href="#exams" class="btn btn-ghost">Explore Exams</a>
          </div>
          <div class="hero__trust">
            <div class="avatars">
              <span style="background:#2C49A6">AR</span>
              <span style="background:#168A66">SK</span>
              <span style="background:#EE6A2C">PM</span>
              <span style="background:#6C3FA0">NV</span>
              <span style="background:#D6991F">+</span>
            </div>
            <small><b>1,20,000+ students</b> from 5,000+ schools<br><span class="stars">★★★★★</span> rated 4.9/5 by parents</small>
          </div>
        </div>

        <div class="hero__visual" data-reveal>
          <div class="medal-card">
            <div class="medal-card__shine"></div>
            <div class="medal-card__top">
              <svg class="medal-card__crest" viewBox="0 0 64 64" fill="none">
                <circle cx="32" cy="26" r="20" fill="url(#g1)"/>
                <circle cx="32" cy="26" r="15" fill="none" stroke="#3a2a05" stroke-width="1.5" opacity=".3"/>
                <path d="M32 16l2.8 5.7 6.3.9-4.5 4.4 1 6.3L32 34.5 26.4 37.3l1-6.3-4.5-4.4 6.3-.9L32 16z" fill="#3a2a05"/>
                <path d="M22 44l-6 16 8-4 4 6 5-14M42 44l6 16-8-4-4 6-5-14" fill="#EE6A2C"/>
                <defs><linearGradient id="g1" x1="12" y1="6" x2="52" y2="46"><stop stop-color="#FBE08A"/><stop offset="1" stop-color="#D6991F"/></linearGradient></defs>
              </svg>
              <div class="medal-card__rank">
                <div class="num">#01</div>
                <small>National Rank</small>
              </div>
            </div>
            <h3>Aarav Mehta</h3>
            <div class="sub">Class 8 · Maths Olympiad · Delhi</div>
            <div class="medal-card__bars">
              <div class="bar-row" v-for="b in heroBars" :key="b.label">
                <div class="lbl"><span>{{ b.label }}</span><b>{{ b.val }}</b></div>
                <div class="bar"><i :style="{ width: barsIn ? b.w : '0%' }"></i></div>
              </div>
            </div>
          </div>

          <div class="chip glass c1">
            <span class="chip__ic" style="background:rgba(22,138,102,.16)">🎯</span>
            <div><b>Live Ranking</b><small>Updated in real-time</small></div>
          </div>
          <div class="chip glass c2">
            <span class="chip__ic" style="background:rgba(214,153,31,.18)">🏅</span>
            <div><b>Gold Medal</b><small>Certificate + ₹50K</small></div>
          </div>
          <div class="chip glass c3">
            <span class="chip__ic" style="background:rgba(44,73,166,.16)">📜</span>
            <div><b>Verified</b><small>Govt-recognised cert</small></div>
          </div>
        </div>
      </div>

      <!-- wave divider -->
      <svg class="wave" viewBox="0 0 1440 80" preserveAspectRatio="none"><path d="M0 40C240 80 480 0 720 24C960 48 1200 80 1440 48V80H0V40Z" fill="#0A1024"/></svg>
    </section>

    <!-- ============ STATS ============ -->
    <section class="stats">
      <div class="wrap stats__grid">
        <div class="stat" v-for="s in stats" :key="s.label">
          <div class="num">{{ s.display }}</div>
          <small>{{ s.label }}</small>
        </div>
      </div>
    </section>

    <!-- ============ SUBJECTS / TABS ============ -->
    <section class="section" id="subjects">
      <svg class="bg-dots" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"></svg>
      <div class="wrap">
        <div class="shead" data-reveal>
          <span class="eyebrow">Choose your arena</span>
          <h2>Eight subjects. One <span class="ital">national</span> stage.</h2>
          <p>Every olympiad is crafted by India's top educators and mapped to your class syllabus — challenging enough to stretch, fair enough to enjoy.</p>
        </div>

        <div class="tabs" data-reveal>
          <button v-for="t in subjectTabs" :key="t.key" class="tab" :class="{ active: activeTab === t.key }" @click="activeTab = t.key">
            <span class="em">{{ t.icon }}</span> {{ t.label }}
          </button>
        </div>

        <div class="tab-panels" data-reveal>
          <div class="subj-grid">
            <div v-for="(s, i) in subjectGroups[activeTab]" :key="activeTab + s.name" class="subj"
                 :style="{ '--accent': s.color, transitionDelay: (i * 60) + 'ms' }">
              <div class="subj__ic">{{ s.icon }}</div>
              <h3>{{ s.name }}</h3>
              <p>{{ s.desc }}</p>
              <div class="subj__meta">
                <span class="subj__tag">{{ s.range }}</span>
                <span class="subj__arrow">→</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ HOW IT WORKS ============ -->
    <section class="section section--ink" id="how">
      <svg class="bg-grid" aria-hidden="true"></svg>
      <div class="wrap">
        <div class="shead center" data-reveal>
          <span class="eyebrow light" style="justify-content:center">Simple by design</span>
          <h2>From sign-up to <span class="ital">stardom</span> in three steps</h2>
        </div>
        <div class="steps">
          <div class="step" v-for="(s, i) in steps" :key="s.title" data-reveal :style="{ transitionDelay: (i * 120) + 'ms' }">
            <div class="step__n glass-dark">{{ s.icon }}<span>{{ i + 1 }}</span></div>
            <h3>{{ s.title }}</h3>
            <p>{{ s.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ EXAMS / CAROUSEL ============ -->
    <section class="section" id="exams">
      <div class="wrap">
        <div class="shead row" data-reveal>
          <div>
            <span class="eyebrow">Upcoming olympiads</span>
            <h2>Live exams open for <span class="ital">registration</span></h2>
          </div>
          <Link href="/exams" class="btn btn-ghost">View all exams →</Link>
        </div>

        <div class="carousel" data-reveal>
          <div class="carousel__viewport">
            <div class="carousel__track" ref="examTrack" :style="{ transform: examTX }"
                 @touchstart.passive="onTouchStart($event, 'exam')" @touchend.passive="onTouchEnd($event, 'exam')">
              <article class="ecard" v-for="(e, i) in exams" :key="i">
                <div class="ecard__head">
                  <div class="ecard__ribbon" v-if="e.ribbon">{{ e.ribbon }}</div>
                  <div class="ecard__date">📅 {{ e.date }}</div>
                  <h3>{{ e.name }}</h3>
                  <div class="ecard__sub">{{ e.sub }}</div>
                </div>
                <div class="ecard__body">
                  <div class="ecard__row">
                    <span class="ecard__pill" v-for="p in e.pills" :key="p">{{ p }}</span>
                  </div>
                  <div class="ecard__foot">
                    <div class="ecard__fee"><b>{{ e.fee }}</b><small>per attempt</small></div>
                    <Link href="/register" class="btn btn-primary">Enroll</Link>
                  </div>
                </div>
              </article>
            </div>
          </div>
          <div class="carousel__nav">
            <div class="carousel__dots">
              <button v-for="(d, i) in examPages" :key="i" class="cdot" :class="{ active: examIndex === i }" @click="goExam(i)"></button>
            </div>
            <div class="carousel__arrows">
              <button class="carr" :disabled="examIndex === 0" @click="examPrev" aria-label="Previous"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
              <button class="carr" :disabled="examIndex >= examMaxIndex" @click="examNext" aria-label="Next"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ REWARDS / MEDALS ============ -->
    <section class="section section--paper2" id="rewards">
      <div class="wrap">
        <div class="shead center" data-reveal>
          <span class="eyebrow" style="justify-content:center">Recognition that matters</span>
          <h2>Compete for more than a <span class="ital">score</span></h2>
          <p>Every winner earns a verified certificate, a national rank badge and real rewards worth celebrating.</p>
        </div>
        <div class="medals">
          <div class="mcard silver" data-reveal>
            <svg class="mcard__medal" viewBox="0 0 64 64" fill="none"><circle cx="32" cy="40" r="20" fill="#D9DEE8" stroke="#fff" stroke-width="2"/><path d="M22 8h20l-7 18H29L22 8z" fill="#9aa3b5"/><text x="32" y="47" font-size="18" font-family="Space Grotesk" font-weight="700" fill="#5a6273" text-anchor="middle">2</text></svg>
            <div class="tier">Silver Tier</div>
            <h3>Top 10%</h3>
            <ul>
              <li v-for="p in silverPerks" :key="p"><svg viewBox="0 0 24 24" fill="none" stroke="#168A66" stroke-width="3"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg> {{ p }}</li>
            </ul>
          </div>
          <div class="mcard gold" data-reveal style="transition-delay:.1s">
            <svg class="mcard__medal" viewBox="0 0 64 64" fill="none"><circle cx="32" cy="40" r="20" fill="#F2C84B" stroke="#fff" stroke-width="2"/><path d="M22 8h20l-7 18H29L22 8z" fill="#EE6A2C"/><text x="32" y="47" font-size="18" font-family="Space Grotesk" font-weight="700" fill="#7a5a10" text-anchor="middle">1</text></svg>
            <div class="tier">Gold Tier</div>
            <h3>Top 1%</h3>
            <ul>
              <li v-for="p in goldPerks" :key="p"><svg viewBox="0 0 24 24" fill="none" stroke="#168A66" stroke-width="3"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg> {{ p }}</li>
            </ul>
          </div>
          <div class="mcard bronze" data-reveal style="transition-delay:.2s">
            <svg class="mcard__medal" viewBox="0 0 64 64" fill="none"><circle cx="32" cy="40" r="20" fill="#D99868" stroke="#fff" stroke-width="2"/><path d="M22 8h20l-7 18H29L22 8z" fill="#a05c2c"/><text x="32" y="47" font-size="18" font-family="Space Grotesk" font-weight="700" fill="#5a3416" text-anchor="middle">3</text></svg>
            <div class="tier">Bronze Tier</div>
            <h3>Top 25%</h3>
            <ul>
              <li v-for="p in bronzePerks" :key="p"><svg viewBox="0 0 24 24" fill="none" stroke="#168A66" stroke-width="3"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg> {{ p }}</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ TESTIMONIALS / CAROUSEL ============ -->
    <section class="section tsection">
      <svg class="bg-grid" aria-hidden="true"></svg>
      <div class="wrap">
        <div class="shead center" data-reveal style="margin-inline:auto">
          <span class="eyebrow light" style="justify-content:center">Loved across India</span>
          <h2>Students and parents on <span class="ital">the Hunt</span></h2>
        </div>
        <div class="tcarousel" data-reveal @mouseenter="pauseTest" @mouseleave="resumeTest">
          <div class="ttrack" ref="testTrack" :style="{ transform: testTX }"
               @touchstart.passive="onTouchStart($event, 'test')" @touchend.passive="onTouchEnd($event, 'test')">
            <div class="tcard glass-dark" v-for="(t, i) in testimonials" :key="i">
              <div class="tcard__q">"</div>
              <div class="stars">★★★★★</div>
              <p>{{ t.quote }}</p>
              <div class="tcard__who">
                <span class="tcard__av" :style="{ background: t.color }">{{ t.initials }}</span>
                <div><b>{{ t.name }}</b><small>{{ t.role }}</small></div>
              </div>
            </div>
          </div>
          <div class="carousel__nav" style="margin-top:30px">
            <div class="carousel__dots">
              <button v-for="(d, i) in testPages" :key="i" class="cdot" :class="{ active: testIndex === i }" @click="goTest(i)"></button>
            </div>
            <div class="carousel__arrows">
              <button class="carr light" :disabled="testIndex === 0" @click="testPrev"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
              <button class="carr light" :disabled="testIndex >= testMaxIndex" @click="testNext"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ FAQ / ACCORDION ============ -->
    <section class="section" id="faq">
      <div class="wrap faq-wrap">
        <div data-reveal>
          <span class="eyebrow">Good to know</span>
          <h2 class="h-serif faq-h">Questions,<br><span class="ital">answered.</span></h2>
          <div class="faq-aside" style="margin-top:30px">
            <h3>Still curious?</h3>
            <p>Our support team replies within a few hours, every day of the week.</p>
            <a href="#register" class="btn btn-gold">Talk to us</a>
            <div class="faq-aside__help">
              <span class="chip__ic" style="width:44px;height:44px;background:rgba(214,153,31,.18);font-size:20px">💬</span>
              <div><b>care@olympiadhunt.in</b><small>+91 98765 43210 · Mon–Sun</small></div>
            </div>
          </div>
        </div>

        <div class="acc" data-reveal>
          <div class="acc__item" v-for="(f, i) in faqs" :key="i" :class="{ open: openFaq === i }">
            <button class="acc__q" @click="openFaq = openFaq === i ? -1 : i">
              {{ f.q }}<span class="acc__ic"></span>
            </button>
            <div class="acc__a" :style="{ maxHeight: openFaq === i ? '240px' : '0' }">
              <p>{{ f.a }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ REGISTER FORM ============ -->
    <section class="section register" id="register">
      <div class="wrap reg-grid">
        <div class="reg-copy" data-reveal>
          <span class="eyebrow">Your seat awaits</span>
          <h2>Create your free <span class="ital">student account</span> today</h2>
          <p>It takes under two minutes. Register now and get instant access to free practice tests while you choose your first olympiad.</p>
          <div class="reg-feats">
            <div class="reg-feat" v-for="f in regFeats" :key="f.title">
              <div class="reg-feat__ic">{{ f.icon }}</div>
              <div><b>{{ f.title }}</b><small>{{ f.sub }}</small></div>
            </div>
          </div>
        </div>

        <div class="form-card glass" data-reveal>
          <form v-if="!submitted" @submit.prevent="submitForm" novalidate>
            <h3>Register in 2 minutes</h3>
            <p>Join 1,20,000+ students already on the Hunt.</p>

            <div class="field" :class="{ err: errors.name }">
              <label>Student Full Name</label>
              <input type="text" v-model="form.name" placeholder="e.g. Aarav Mehta" @input="clearErr('name')" />
              <span class="msg">Please enter the student's name.</span>
            </div>

            <div class="field-row">
              <div class="field" :class="{ err: errors.klass }">
                <label>Class</label>
                <select v-model="form.klass" @change="clearErr('klass')">
                  <option value="">Select…</option>
                  <option v-for="c in 12" :key="c">Class {{ c }}</option>
                </select>
                <span class="msg">Choose a class.</span>
              </div>
              <div class="field" :class="{ err: errors.subject }">
                <label>First Subject</label>
                <select v-model="form.subject" @change="clearErr('subject')">
                  <option value="">Select…</option>
                  <option v-for="s in subjectOptions" :key="s">{{ s }}</option>
                </select>
                <span class="msg">Choose a subject.</span>
              </div>
            </div>

            <div class="field" :class="{ err: errors.email }">
              <label>Parent Email</label>
              <input type="email" v-model="form.email" placeholder="parent@email.com" @input="clearErr('email')" />
              <span class="msg">Enter a valid email address.</span>
            </div>

            <div class="field" :class="{ err: errors.phone }">
              <label>Mobile Number</label>
              <input type="tel" v-model="form.phone" placeholder="10-digit mobile" maxlength="10" @input="clearErr('phone')" />
              <span class="msg">Enter a valid 10-digit number.</span>
            </div>

            <button type="submit" class="btn btn-primary btn-shine">Create My Free Account
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <p class="form-note">By registering you agree to our Terms & Privacy Policy.</p>
          </form>

          <div v-else class="form-success show">
            <svg viewBox="0 0 64 64" fill="none"><circle cx="32" cy="32" r="30" fill="#168A66" opacity=".12"/><circle cx="32" cy="32" r="22" fill="#168A66"/><path d="M22 32l7 7 13-14" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <h3 class="h-serif">Welcome aboard! 🎉</h3>
            <p>Your free account is ready. Check your email to verify and start practising right away.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ CTA BANNER ============ -->
    <section class="cta">
      <div class="wrap">
        <div class="cta__inner" data-reveal>
          <h2>Ready to prove your <span class="ital">brilliance</span> on the national stage?</h2>
          <p>Join India's fastest-growing olympiad community and turn your hard work into recognition.</p>
          <div class="cta__btns">
            <Link href="/register" class="btn btn-gold">Register Free Now</Link>
            <a href="#exams" class="btn btn-ghost light">Browse Exams</a>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ FOOTER ============ -->
    <footer>
      <div class="wrap">
        <div class="foot-grid">
          <div class="foot-brand">
            <Link href="/" class="brand">
              <span class="brand__mark"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2l2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 19.3 7.2 17l.9-5.4L4.2 7.7l5.4-.8L12 2z" fill="#F2C84B"/><path d="M8 19h8v2.5a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5V19z" fill="#EE6A2C"/></svg></span>
              <span>National Olympiad Hunt</span>
            </Link>
            <p>India's premier olympiad platform for school students, building confidence one competition at a time.</p>
            <div class="foot-social">
              <a href="#" aria-label="Instagram"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></a>
              <a href="#" aria-label="YouTube"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="4"/><path d="M10 9l5 3-5 3V9z" fill="currentColor" stroke="none"/></svg></a>
              <a href="#" aria-label="Twitter"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h3l-7 8 8 12h-6l-5-7-6 7H2l8-9L2 2h6l4 6 6-6z"/></svg></a>
              <a href="#" aria-label="Facebook"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v8h4v-8h3l1-4h-4V8a1 1 0 0 1 1-1z"/></svg></a>
            </div>
          </div>
          <div class="foot-col">
            <h4>Platform</h4>
            <Link href="/exams">Subjects</Link><Link href="/exams">Exams</Link><a href="#rewards">Rewards</a><Link href="/results">Leaderboard</Link><Link href="/exams">Practice Tests</Link>
          </div>
          <div class="foot-col">
            <h4>Company</h4>
            <Link href="/about">About Us</Link><a href="#how">How it Works</a><Link href="/blog">Blog</Link><Link href="/contact">Careers</Link><Link href="/contact">Contact</Link>
          </div>
          <div class="foot-col">
            <h4>Support</h4>
            <a href="#faq">FAQ</a><Link href="/contact">Help Center</Link><Link href="/terms">Terms of Use</Link><Link href="/privacy">Privacy Policy</Link><Link href="/refund">Refund Policy</Link>
          </div>
        </div>
        <div class="foot-bottom">
          <span>© {{ year }} National Olympiad Hunt. All rights reserved. Made in India 🇮🇳</span>
          <div class="foot-bottom__links"><Link href="/terms">Terms</Link><Link href="/privacy">Privacy</Link><a href="#">Cookies</a></div>
        </div>
      </div>
    </footer>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';

/* ---------- static content ---------- */
const year = new Date().getFullYear();
const navLinks = [
  { id: 'subjects', label: 'Subjects' },
  { id: 'how',      label: 'How it Works' },
  { id: 'exams',    label: 'Exams' },
  { id: 'rewards',  label: 'Rewards' },
  { id: 'faq',      label: 'FAQ' },
];
const marquee = [
  { icon: '🏆', text: 'National Maths Olympiad 2026 —', hl: 'Registrations Open' },
  { icon: '🎓', text: 'Over 1,20,000+ students competing nationwide', hl: '' },
  { icon: '🥇', text: 'Win scholarships up to', hl: '₹1,00,000' },
  { icon: '📅', text: 'Early-bird closes', hl: '30 June' },
];
const heroBars = [
  { label: 'Accuracy',   val: '98%',  w: '98%' },
  { label: 'Percentile', val: '99.7', w: '99%' },
  { label: 'Speed Score',val: '92%',  w: '92%' },
];

const subjectTabs = [
  { key: 'core',   icon: '🧠', label: 'Core Academics' },
  { key: 'lang',   icon: '🗣️', label: 'Languages' },
  { key: 'future', icon: '🚀', label: 'Future Skills' },
];
const subjectGroups = {
  core: [
    { name: 'Mathematics',       icon: '📐', color: '#2C49A6', range: 'Class 1–12', desc: 'Logic, numbers and problem-solving from arithmetic to advanced reasoning.' },
    { name: 'Science',           icon: '🔬', color: '#168A66', range: 'Class 1–12', desc: 'Physics, chemistry and biology brought alive through concept-first questions.' },
    { name: 'General Knowledge', icon: '🌍', color: '#EE6A2C', range: 'Class 1–10', desc: 'Current affairs, history, geography and the world around us.' },
  ],
  lang: [
    { name: 'English',        icon: '📖', color: '#6C3FA0', range: 'Class 1–12', desc: 'Grammar, comprehension and vocabulary tested the smart way.' },
    { name: 'Hindi',          icon: '🅰️', color: '#C9501A', range: 'Class 1–10', desc: 'व्याकरण, गद्य और काव्य — strengthen your mother tongue.' },
    { name: 'Social Science', icon: '🏛️', color: '#168A66', range: 'Class 6–12', desc: 'History, civics and geography for the curious citizen.' },
  ],
  future: [
    { name: 'Computers',        icon: '💻', color: '#2C49A6', range: 'Class 3–12', desc: 'Logic, coding fundamentals and digital literacy for the AI age.' },
    { name: 'Logical Reasoning',icon: '🧩', color: '#D6991F', range: 'Class 1–12', desc: 'Patterns, puzzles and analytical thinking that sharpen the mind.' },
    { name: 'Creativity & Art', icon: '🎨', color: '#EE6A2C', range: 'Class 1–8',  desc: 'Visual thinking, design sense and imaginative problem-solving.' },
  ],
};
const activeTab = ref('core');

const steps = [
  { icon: '📝', title: 'Register & Pick',   desc: 'Create a free account, choose your class and the subjects you want to conquer.' },
  { icon: '⚡', title: 'Compete Online',    desc: 'Take the timed MCQ exam from home in a secure, full-screen proctored room.' },
  { icon: '🏆', title: 'Win & Celebrate',   desc: 'Get instant scores, national ranks, downloadable certificates and rewards.' },
];

const exams = [
  { name: 'National Maths Olympiad', sub: 'Open · Class 1 to 12', date: '12 Jul 2026', ribbon: 'HOT', fee: '₹299', pills: ['⏱️ 60 min', '📋 50 MCQ', '🏅 Gold tier'] },
  { name: 'Science Champions Cup',   sub: 'Open · Class 4 to 10', date: '19 Jul 2026', ribbon: '',    fee: '₹249', pills: ['⏱️ 45 min', '📋 40 MCQ', '🥈 Silver tier'] },
  { name: 'Coding & Logic Quest',    sub: 'Open · Class 6 to 12', date: '26 Jul 2026', ribbon: 'NEW', fee: '₹349', pills: ['⏱️ 60 min', '📋 45 MCQ', '🏅 Gold tier'] },
  { name: 'English Mastery Open',    sub: 'Open · Class 1 to 12', date: '02 Aug 2026', ribbon: '',    fee: '₹199', pills: ['⏱️ 45 min', '📋 40 MCQ', '🥉 Bronze tier'] },
  { name: 'GK & Current Affairs',    sub: 'Open · Class 3 to 10', date: '09 Aug 2026', ribbon: '',    fee: '₹149', pills: ['⏱️ 30 min', '📋 30 MCQ', '🥈 Silver tier'] },
];

const silverPerks = ['Digital + printed certificate', 'State-level rank badge', '₹10,000 scholarship'];
const goldPerks    = ['Govt-recognised certificate', 'National rank + trophy', '₹50,000 scholarship', 'Felicitation ceremony'];
const bronzePerks  = ['Digital merit certificate', 'School-level rank badge', 'Practice vouchers'];

const testimonials = [
  { quote: 'The exam room felt exactly like a real competition. My son cleared the Maths olympiad with All-India Rank 14 — the certificate is now framed on our wall!', name: 'Rohit Malhotra', role: 'Parent · Bengaluru, Karnataka', initials: 'RM', color: '#2C49A6' },
  { quote: 'I loved how fast the results came. Within minutes I saw my score, my rank and where I went wrong. It pushed me to practice harder for the next round.', name: 'Ananya Sharma', role: 'Class 9 · Jaipur, Rajasthan', initials: 'AS', color: '#168A66' },
  { quote: 'As a school we registered 200 students. The dashboard, the ranks, the certificates — everything was seamless and genuinely motivating for the kids.', name: 'Veena Pillai', role: 'Principal · Kochi, Kerala', initials: 'VP', color: '#EE6A2C' },
  { quote: 'The questions were challenging but fair. Winning a Gold tier scholarship genuinely changed how my daughter sees her own potential. Thank you!', name: 'Deepak Kumar', role: 'Parent · Patna, Bihar', initials: 'DK', color: '#6C3FA0' },
  { quote: 'From a small town in Assam to an All-India Rank — National Olympiad Hunt gave me a stage I never thought I would reach. Forever grateful.', name: 'Manish Bora', role: 'Class 11 · Guwahati, Assam', initials: 'MB', color: '#D6991F' },
];

const faqs = [
  { q: 'Who can participate in the olympiads?', a: 'Any student from Class 1 to Class 12 in India can register. You only need to pick your current class and the subjects you would like to compete in — no school nomination required.' },
  { q: 'How are the exams conducted?', a: 'All exams are online, timed MCQ tests taken in a secure full-screen room from any laptop or tablet. The system auto-submits when time ends and flags tab-switching to keep it fair for everyone.' },
  { q: 'When will I get my results and rank?', a: 'You will see your score and a detailed breakdown instantly after submitting. National, state and school ranks are published once the exam window closes — usually within 48 hours.' },
  { q: 'Are the certificates recognised?', a: 'Yes. Every participant receives a verified digital certificate, and Gold-tier winners receive a government-recognised certificate plus a printed copy and trophy.' },
  { q: 'What does it cost to participate?', a: 'Registration is free. You only pay a small per-exam fee (starting at ₹149) when you enroll for a specific olympiad. Practice tests are completely free for all registered students.' },
];
const openFaq = ref(0);

const regFeats = [
  { icon: '🎁', title: 'Free to join',        sub: 'No payment until you enroll for an exam' },
  { icon: '📚', title: 'Free practice tests',  sub: 'Sharpen up before the real competition' },
  { icon: '🔒', title: 'Safe & private',       sub: 'Your data is encrypted and never shared' },
];
const subjectOptions = ['Mathematics', 'Science', 'English', 'Computers', 'General Knowledge', 'Reasoning'];

/* ---------- nav / scroll state ---------- */
const rootEl       = ref(null);
const scrolled     = ref(false);
const mobileOpen   = ref(false);
const activeSection= ref('home');
const barsIn       = ref(false);

/* ---------- animated counters ---------- */
const stats = reactive([
  { label: 'Students Enrolled', target: 120000, suffix: '+', display: '0' },
  { label: 'Partner Schools',   target: 5000,   suffix: '+', display: '0' },
  { label: 'Subjects Offered',  target: 8,      suffix: '',  display: '0' },
  { label: 'States & UTs',      target: 28,     suffix: '',  display: '0' },
]);
let countersDone = false;
function runCounters() {
  if (countersDone) return; countersDone = true;
  stats.forEach((s) => {
    const dur = 1600, t0 = performance.now();
    const tick = (t) => {
      const p = Math.min((t - t0) / dur, 1);
      const eased = 1 - Math.pow(1 - p, 3);
      s.display = Math.floor(eased * s.target).toLocaleString('en-IN') + (p === 1 ? s.suffix : '');
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  });
}

/* ---------- responsive perView ---------- */
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1280);
const perView = computed(() => (windowWidth.value <= 600 ? 1 : windowWidth.value <= 1024 ? 2 : 3));

/* ---------- exams carousel ---------- */
const examTrack    = ref(null);
const examIndex    = ref(0);
const examTX       = ref('translateX(0px)');
const examMaxIndex = computed(() => Math.max(0, exams.length - perView.value));
const examPages    = computed(() => examMaxIndex.value + 1);
function recalcExam() {
  const tr = examTrack.value; if (!tr || !tr.children[0]) return;
  const gap = parseFloat(getComputedStyle(tr).gap) || 24;
  const step = tr.children[0].getBoundingClientRect().width + gap;
  examTX.value = `translateX(${-examIndex.value * step}px)`;
}
const goExam   = (i) => { examIndex.value = i; };
const examPrev = () => { if (examIndex.value > 0) examIndex.value--; };
const examNext = () => { if (examIndex.value < examMaxIndex.value) examIndex.value++; };
watch(examIndex, recalcExam);

/* ---------- testimonials carousel ---------- */
const testTrack    = ref(null);
const testIndex    = ref(0);
const testTX       = ref('translateX(0px)');
const testMaxIndex = computed(() => Math.max(0, testimonials.length - perView.value));
const testPages    = computed(() => testMaxIndex.value + 1);
function recalcTest() {
  const tr = testTrack.value; if (!tr || !tr.children[0]) return;
  const gap = parseFloat(getComputedStyle(tr).gap) || 26;
  const step = tr.children[0].getBoundingClientRect().width + gap;
  testTX.value = `translateX(${-testIndex.value * step}px)`;
}
const goTest   = (i) => { testIndex.value = i; };
const testPrev = () => { if (testIndex.value > 0) testIndex.value--; };
const testNext = () => { testIndex.value = testIndex.value >= testMaxIndex.value ? 0 : testIndex.value + 1; };
watch(testIndex, recalcTest);

/* autoplay */
let testTimer = null;
const startTest = () => { testTimer = setInterval(testNext, 5000); };
const pauseTest = () => clearInterval(testTimer);
const resumeTest = () => { pauseTest(); startTest(); };

/* clamp indices when viewport changes */
watch(perView, () => {
  if (examIndex.value > examMaxIndex.value) examIndex.value = examMaxIndex.value;
  if (testIndex.value > testMaxIndex.value) testIndex.value = testMaxIndex.value;
  nextTick(() => { recalcExam(); recalcTest(); });
});

/* ---------- touch swipe ---------- */
let touchX = 0;
const onTouchStart = (e) => { touchX = e.touches[0].clientX; };
const onTouchEnd = (e, which) => {
  const dx = e.changedTouches[0].clientX - touchX;
  if (which === 'exam') { if (dx < -40) examNext(); else if (dx > 40) examPrev(); }
  else { if (dx < -40) testNext(); else if (dx > 40) testPrev(); }
};

/* ---------- register form ---------- */
const form = reactive({ name: '', klass: '', subject: '', email: '', phone: '' });
const errors = reactive({ name: false, klass: false, subject: false, email: false, phone: false });
const submitted = ref(false);
const clearErr = (k) => { errors[k] = false; };
function submitForm() {
  errors.name    = form.name.trim().length < 2;
  errors.klass   = form.klass === '';
  errors.subject = form.subject === '';
  errors.email   = !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email);
  errors.phone   = !/^\d{10}$/.test(form.phone.trim());
  if (!Object.values(errors).some(Boolean)) submitted.value = true;
}

/* ---------- lifecycle ---------- */
let scrollHandler, resizeHandler;
const observers = [];

onMounted(() => {
  document.documentElement.style.scrollBehavior = 'smooth';

  scrollHandler = () => { scrolled.value = window.scrollY > 12; };
  window.addEventListener('scroll', scrollHandler, { passive: true });
  scrollHandler();

  resizeHandler = () => { windowWidth.value = window.innerWidth; };
  window.addEventListener('resize', resizeHandler, { passive: true });

  const root = rootEl.value;

  /* reveal on scroll */
  const revealIO = new IntersectionObserver((entries) => {
    entries.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('in'); revealIO.unobserve(e.target); } });
  }, { threshold: 0.12 });
  root.querySelectorAll('[data-reveal]').forEach((el) => revealIO.observe(el));
  observers.push(revealIO);

  /* counters + hero bars */
  const statsEl = root.querySelector('.stats');
  if (statsEl) {
    const statIO = new IntersectionObserver((entries) => {
      entries.forEach((e) => { if (e.isIntersecting) { runCounters(); statIO.unobserve(e.target); } });
    }, { threshold: 0.4 });
    statIO.observe(statsEl); observers.push(statIO);
  }
  const medalEl = root.querySelector('.medal-card');
  if (medalEl) {
    const barIO = new IntersectionObserver((entries) => {
      entries.forEach((e) => { if (e.isIntersecting) { barsIn.value = true; barIO.unobserve(e.target); } });
    }, { threshold: 0.3 });
    barIO.observe(medalEl); observers.push(barIO);
  }

  /* scrollspy — active nav */
  const sections = ['home', 'subjects', 'how', 'exams', 'rewards', 'faq', 'register']
    .map((id) => document.getElementById(id)).filter(Boolean);
  const spyIO = new IntersectionObserver((entries) => {
    entries.forEach((e) => { if (e.isIntersecting) activeSection.value = e.target.id; });
  }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });
  sections.forEach((s) => spyIO.observe(s));
  observers.push(spyIO);

  nextTick(() => { recalcExam(); recalcTest(); });
  startTest();
});

onUnmounted(() => {
  window.removeEventListener('scroll', scrollHandler);
  window.removeEventListener('resize', resizeHandler);
  observers.forEach((o) => o.disconnect());
  clearInterval(testTimer);
  document.documentElement.style.scrollBehavior = '';
});
</script>

<style scoped>
/* ============================================================
   NATIONAL OLYMPIAD HUNT — Editorial / Prestige homepage
   Scoped to .noh — fonts loaded globally in app.blade.php
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
  overflow-x:hidden; -webkit-font-smoothing:antialiased;
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
.h-serif{ font-family:var(--display); font-weight:600; line-height:1.02; letter-spacing:-.02em; }
.num{ font-family:var(--mono); font-variant-numeric:tabular-nums; }
.ital{ font-style:italic; color:var(--saffron-dk); font-weight:500; }

/* glass utilities */
.glass{ background:rgba(251,246,236,.55); backdrop-filter:blur(14px) saturate(150%); -webkit-backdrop-filter:blur(14px) saturate(150%); border:1px solid rgba(255,255,255,.55); box-shadow:inset 0 1px 0 rgba(255,255,255,.6), var(--shadow-sm); }
.glass-dark{ background:rgba(255,255,255,.06); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.10); }

/* buttons */
.btn{ display:inline-flex; align-items:center; justify-content:center; gap:9px; font:700 15px/1 var(--body); padding:15px 26px; border-radius:100px; cursor:pointer; border:1.5px solid transparent; transition:.28s cubic-bezier(.2,.8,.2,1); white-space:nowrap; position:relative; overflow:hidden; }
.btn svg{ width:18px; height:18px; }
.btn-primary{ background:var(--saffron); color:#fff; box-shadow:0 12px 26px -10px var(--saffron); }
.btn-primary:hover{ background:var(--saffron-dk); transform:translateY(-2px); box-shadow:0 18px 34px -10px var(--saffron); }
.btn-ghost{ background:transparent; color:var(--ink); border-color:var(--ink-12); }
.btn-ghost:hover{ border-color:var(--ink); background:var(--ink); color:var(--paper); transform:translateY(-2px); }
.btn-ghost.light{ color:#fff; border-color:rgba(255,255,255,.5); }
.btn-ghost.light:hover{ background:#fff; color:var(--saffron-dk); }
.btn-gold{ background:linear-gradient(135deg,var(--gold-lt),var(--gold)); color:#3a2a05; box-shadow:0 12px 26px -10px var(--gold); }
.btn-gold:hover{ transform:translateY(-2px); }
/* shine sweep */
.btn-shine::after{ content:""; position:absolute; top:0; left:-120%; width:60%; height:100%; background:linear-gradient(120deg,transparent,rgba(255,255,255,.45),transparent); transform:skewX(-20deg); transition:left .7s; }
.btn-shine:hover::after{ left:140%; }

/* marquee */
.marquee{ background:var(--ink); color:var(--paper-70); overflow:hidden; white-space:nowrap; border-bottom:1px solid rgba(255,255,255,.06); }
.marquee__track{ display:inline-flex; gap:60px; padding:9px 0; animation:scroll 32s linear infinite; }
.marquee:hover .marquee__track{ animation-play-state:paused; }
.marquee span{ font:600 12.5px/1 var(--body); letter-spacing:.04em; display:inline-flex; align-items:center; gap:10px; }
.marquee b{ color:var(--gold-lt); }
.marquee .dot{ width:5px; height:5px; border-radius:50%; background:var(--saffron); }
@keyframes scroll{ to{ transform:translateX(-50%);} }

/* navbar */
.nav{ position:sticky; top:0; z-index:80; transition:.3s; background:transparent; border-bottom:1px solid transparent; }
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
.burger{ display:none; flex-direction:column; gap:5px; background:none; border:none; cursor:pointer; padding:8px; }
.burger span{ width:24px; height:2px; background:var(--ink); border-radius:2px; transition:.3s; }
.burger.open span:nth-child(1){ transform:translateY(7px) rotate(45deg); }
.burger.open span:nth-child(2){ opacity:0; }
.burger.open span:nth-child(3){ transform:translateY(-7px) rotate(-45deg); }
.mobile-menu{ position:fixed; inset:74px 0 auto 0; background:var(--paper); z-index:79; padding:20px 24px 30px; transform:translateY(-130%); transition:.4s cubic-bezier(.2,.8,.2,1); border-bottom:1px solid var(--paper-line); box-shadow:var(--shadow-md); }
.mobile-menu.open{ transform:translateY(0); }
.mobile-menu a{ display:block; padding:14px 4px; font:600 17px/1 var(--body); border-bottom:1px solid var(--paper-line); }
.mobile-menu .btn{ width:100%; margin-top:18px; }

/* hero */
.hero{ position:relative; padding:60px 0 0; overflow:hidden; }
.hero__blob{ position:absolute; border-radius:50%; filter:blur(70px); opacity:.5; z-index:0; pointer-events:none; }
.hero__blob.a{ width:520px; height:520px; background:radial-gradient(circle,#F6C45A,transparent 65%); top:-160px; right:-120px; animation:drift 14s ease-in-out infinite; }
.hero__blob.b{ width:440px; height:440px; background:radial-gradient(circle,#7C97F0,transparent 65%); bottom:60px; left:-140px; opacity:.4; animation:drift 18s ease-in-out infinite reverse; }
@keyframes drift{ 0%,100%{ transform:translate(0,0);} 50%{ transform:translate(30px,-30px);} }
.float-shape{ position:absolute; z-index:1; pointer-events:none; animation:floaty 7s ease-in-out infinite; }
.float-shape.s1{ width:80px; height:80px; top:90px; left:8%; animation-delay:0s; }
.float-shape.s2{ width:54px; height:54px; top:240px; right:12%; animation-delay:1.2s; }
.float-shape.s3{ width:40px; height:40px; bottom:160px; left:18%; animation-delay:.6s; }
.float-shape.s4{ width:50px; height:50px; top:120px; right:32%; animation-delay:2s; }
@keyframes floaty{ 0%,100%{ transform:translateY(0) rotate(0);} 50%{ transform:translateY(-18px) rotate(8deg);} }
.hero__grid{ position:relative; z-index:2; display:grid; grid-template-columns:1.05fr .95fr; gap:50px; align-items:center; padding-bottom:120px; }
.hero__badge{ display:inline-flex; align-items:center; gap:10px; padding:7px 7px 7px 14px; border-radius:100px; font:600 13px/1 var(--body); color:var(--ink-70); margin-bottom:26px; }
.hero__badge b{ background:var(--ink); color:var(--gold-lt); padding:5px 11px; border-radius:100px; font-size:11.5px; letter-spacing:.04em; }
.hero h1{ font-family:var(--display); font-weight:600; font-size:clamp(40px,6vw,76px); line-height:.98; letter-spacing:-.025em; }
.hero h1 .ital{ font-weight:500; }
.hero h1 .underline{ position:relative; white-space:nowrap; }
.hero h1 .underline svg{ position:absolute; left:0; bottom:-12px; width:100%; height:16px; }
.hero p.lede{ font-size:19px; color:var(--ink-70); max-width:520px; margin:28px 0 36px; }
.hero__cta{ display:flex; gap:14px; flex-wrap:wrap; align-items:center; }
.hero__trust{ display:flex; align-items:center; gap:16px; margin-top:34px; }
.avatars{ display:flex; }
.avatars span{ width:38px; height:38px; border-radius:50%; border:2.5px solid var(--paper); margin-left:-12px; display:grid; place-items:center; font:700 13px/1 var(--body); color:#fff; }
.avatars span:first-child{ margin-left:0; }
.hero__trust small{ font-size:13px; color:var(--ink-55); line-height:1.4; }
.hero__trust b{ color:var(--ink); }
.stars{ color:var(--gold); letter-spacing:2px; font-size:13px; }

.hero__visual{ position:relative; }
.medal-card{ position:relative; background:linear-gradient(165deg,var(--ink-2),var(--ink)); border-radius:var(--r-xl); padding:38px 34px; color:var(--paper); overflow:hidden; box-shadow:var(--shadow-lg); border:1px solid rgba(255,255,255,.08); }
.medal-card::before{ content:""; position:absolute; inset:0; background:radial-gradient(600px 200px at 80% -10%, rgba(214,153,31,.35), transparent 60%); }
.medal-card__shine{ position:absolute; top:-60%; left:-30%; width:60%; height:220%; background:linear-gradient(120deg,transparent,rgba(255,255,255,.10),transparent); transform:skewX(-20deg); animation:sheen 6s ease-in-out infinite; }
@keyframes sheen{ 0%,100%{ left:-40%;} 50%{ left:120%;} }
.medal-card__top{ position:relative; display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:30px; }
.medal-card__crest{ width:60px; height:60px; }
.medal-card__rank{ text-align:right; }
.medal-card__rank .num{ font-size:42px; font-weight:700; color:var(--gold-lt); line-height:1; }
.medal-card__rank small{ display:block; font-size:11px; letter-spacing:.18em; text-transform:uppercase; color:var(--paper-45); margin-top:6px; }
.medal-card h3{ position:relative; font-family:var(--display); font-size:25px; font-weight:600; margin-bottom:6px; }
.medal-card .sub{ position:relative; color:var(--paper-45); font-size:13.5px; margin-bottom:26px; }
.medal-card__bars{ position:relative; display:flex; flex-direction:column; gap:14px; }
.bar-row .lbl{ display:flex; justify-content:space-between; font-size:12.5px; color:var(--paper-70); margin-bottom:7px; }
.bar-row .lbl b{ color:var(--gold-lt); }
.bar{ height:7px; border-radius:100px; background:rgba(255,255,255,.1); overflow:hidden; }
.bar i{ display:block; height:100%; border-radius:100px; background:linear-gradient(90deg,var(--saffron),var(--gold-lt)); width:0; transition:width 1.4s cubic-bezier(.2,.8,.2,1); }
.chip{ position:absolute; border-radius:16px; padding:13px 16px; display:flex; align-items:center; gap:11px; animation:float 5s ease-in-out infinite; }
.chip__ic{ width:38px; height:38px; border-radius:11px; display:grid; place-items:center; flex:none; }
.chip b{ font:700 15px/1 var(--body); }
.chip small{ display:block; font-size:11px; color:var(--ink-55); margin-top:3px; }
.chip.c1{ top:-26px; left:-34px; animation-delay:0s; }
.chip.c2{ bottom:46px; left:-50px; animation-delay:1.4s; }
.chip.c3{ bottom:-24px; right:-22px; animation-delay:.7s; }
@keyframes float{ 0%,100%{ transform:translateY(0);} 50%{ transform:translateY(-12px);} }
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
.bg-dots{ position:absolute; inset:0; width:100%; height:100%; opacity:.5; pointer-events:none; }
.bg-grid{ position:absolute; inset:0; width:100%; height:100%; pointer-events:none; opacity:.5;
  background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);
  background-size:48px 48px; mask-image:radial-gradient(ellipse 70% 60% at 50% 40%,#000,transparent); -webkit-mask-image:radial-gradient(ellipse 70% 60% at 50% 40%,#000,transparent); }
.shead{ max-width:680px; margin-bottom:54px; position:relative; }
.shead.center{ margin-inline:auto; text-align:center; }
.shead.row{ max-width:none; display:flex; justify-content:space-between; align-items:flex-end; gap:30px; flex-wrap:wrap; }
.shead.row > div{ max-width:560px; }
.shead h2{ font-family:var(--display); font-weight:600; font-size:clamp(32px,4.4vw,52px); line-height:1.04; letter-spacing:-.02em; margin:18px 0 0; }
.shead p{ font-size:17px; color:var(--ink-70); margin-top:18px; }
.section--ink .shead p{ color:var(--paper-45); }

/* subjects / tabs */
.tabs{ display:flex; gap:10px; flex-wrap:wrap; margin-bottom:40px; position:relative; }
.tab{ font:600 14px/1 var(--body); padding:12px 20px; border-radius:100px; cursor:pointer; background:var(--paper); border:1.5px solid var(--paper-line); color:var(--ink-70); transition:.22s; display:inline-flex; align-items:center; gap:9px; }
.tab .em{ font-size:16px; }
.tab:hover{ border-color:var(--ink-35); color:var(--ink); transform:translateY(-1px); }
.tab.active{ background:var(--ink); color:var(--paper); border-color:var(--ink); }
.tab-panels{ position:relative; }
.subj-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:22px; }
.subj{ background:var(--paper); border:1px solid var(--paper-line); border-radius:var(--r-lg); padding:30px; transition:.45s cubic-bezier(.2,.8,.2,1); position:relative; overflow:hidden; cursor:pointer; opacity:0; transform:translateY(20px); animation:cardIn .6s cubic-bezier(.2,.8,.2,1) forwards; }
@keyframes cardIn{ to{ opacity:1; transform:none; } }
.subj::after{ content:""; position:absolute; inset:auto -40% -60% auto; width:180px; height:180px; border-radius:50%; background:var(--accent,var(--saffron)); opacity:.08; transition:.5s; }
.subj:hover{ transform:translateY(-6px); box-shadow:var(--shadow-md); border-color:transparent; }
.subj:hover::after{ transform:scale(1.6); opacity:.16; }
.subj__ic{ width:58px; height:58px; border-radius:16px; display:grid; place-items:center; font-size:28px; margin-bottom:20px; background:color-mix(in srgb,var(--accent,var(--saffron)) 14%, transparent); transition:.4s; }
.subj:hover .subj__ic{ transform:scale(1.08) rotate(-6deg); }
.subj h3{ font-family:var(--display); font-size:22px; font-weight:600; margin-bottom:8px; }
.subj p{ font-size:14px; color:var(--ink-70); margin-bottom:18px; min-height:42px; }
.subj__meta{ display:flex; align-items:center; justify-content:space-between; padding-top:16px; border-top:1px solid var(--paper-line); }
.subj__tag{ font:600 11.5px/1 var(--body); letter-spacing:.04em; padding:6px 11px; border-radius:100px; background:color-mix(in srgb,var(--accent,var(--saffron)) 14%, transparent); color:var(--accent,var(--saffron-dk)); }
.subj__arrow{ width:34px; height:34px; border-radius:50%; display:grid; place-items:center; background:var(--ink); color:var(--paper); transition:.3s; }
.subj:hover .subj__arrow{ transform:rotate(-45deg); background:var(--accent,var(--saffron)); }

/* steps */
.steps{ display:grid; grid-template-columns:repeat(3,1fr); gap:26px; position:relative; }
.steps::before{ content:""; position:absolute; top:46px; left:16%; right:16%; height:2px; background:repeating-linear-gradient(90deg,rgba(255,255,255,.25) 0 8px,transparent 8px 16px); z-index:0; }
.step{ position:relative; z-index:1; text-align:center; }
.step__n{ width:92px; height:92px; margin:0 auto 24px; border-radius:50%; display:grid; place-items:center; position:relative; font-family:var(--display); font-size:34px; font-weight:700; color:var(--paper); }
.step__n span{ position:absolute; top:-8px; right:-8px; width:30px; height:30px; border-radius:50%; background:var(--saffron); color:#fff; display:grid; place-items:center; font:700 13px/1 var(--body); box-shadow:var(--shadow-sm); }
.step h3{ font-family:var(--display); font-size:22px; font-weight:600; margin-bottom:10px; color:var(--paper); }
.step p{ font-size:14.5px; color:var(--paper-45); max-width:280px; margin-inline:auto; }

/* carousel */
.carousel{ position:relative; }
.carousel__viewport{ overflow:hidden; padding:8px 4px 24px; }
.carousel__track{ display:flex; gap:24px; transition:transform .6s cubic-bezier(.2,.8,.2,1); }
.ecard{ flex:0 0 calc((100% - 48px)/3); background:var(--ink-2); color:var(--paper); border-radius:var(--r-lg); overflow:hidden; border:1px solid rgba(255,255,255,.07); position:relative; transition:.35s; }
.ecard:hover{ transform:translateY(-6px); box-shadow:var(--shadow-lg); }
.ecard__head{ padding:26px 26px 0; position:relative; }
.ecard__ribbon{ position:absolute; top:18px; right:-34px; transform:rotate(40deg); background:var(--saffron); color:#fff; font:700 11px/1 var(--body); letter-spacing:.06em; padding:6px 40px; }
.ecard__date{ display:inline-flex; align-items:center; gap:8px; font:600 12px/1 var(--body); color:var(--gold-lt); background:rgba(214,153,31,.14); padding:8px 12px; border-radius:100px; margin-bottom:18px; }
.ecard h3{ font-family:var(--display); font-size:24px; font-weight:600; line-height:1.1; margin-bottom:8px; }
.ecard__sub{ color:var(--paper-45); font-size:13.5px; }
.ecard__body{ padding:22px 26px 26px; }
.ecard__row{ display:flex; gap:10px; flex-wrap:wrap; margin-bottom:22px; }
.ecard__pill{ font:600 12px/1 var(--body); padding:8px 12px; border-radius:100px; background:rgba(255,255,255,.07); color:var(--paper-70); }
.ecard__foot{ display:flex; align-items:center; justify-content:space-between; padding-top:20px; border-top:1px solid rgba(255,255,255,.08); }
.ecard__fee b{ font-family:var(--mono); font-size:24px; font-weight:700; color:var(--gold-lt); }
.ecard__fee small{ display:block; font-size:11px; color:var(--paper-45); letter-spacing:.04em; }
.ecard .btn{ padding:11px 18px; font-size:13.5px; }
.carousel__nav{ display:flex; align-items:center; justify-content:space-between; margin-top:8px; }
.carousel__dots{ display:flex; gap:8px; }
.cdot{ width:9px; height:9px; border-radius:100px; background:var(--ink-12); cursor:pointer; transition:.3s; border:none; padding:0; }
.section--ink .cdot, .tsection .cdot{ background:rgba(255,255,255,.2); }
.cdot.active{ width:28px; background:var(--saffron); }
.carousel__arrows{ display:flex; gap:10px; }
.carr{ width:48px; height:48px; border-radius:50%; border:1.5px solid var(--ink-12); background:var(--paper); display:grid; place-items:center; cursor:pointer; transition:.25s; color:var(--ink); }
.carr:hover{ background:var(--ink); color:var(--paper); border-color:var(--ink); }
.carr:disabled{ opacity:.3; cursor:not-allowed; }
.carr.light{ background:transparent; color:var(--paper); border-color:rgba(255,255,255,.2); }
.carr.light:hover{ background:var(--paper); color:var(--ink); }

/* medals */
.medals{ display:grid; grid-template-columns:repeat(3,1fr); gap:24px; align-items:end; }
.mcard{ border-radius:var(--r-lg); padding:34px 28px; text-align:center; position:relative; overflow:hidden; color:#3a2a05; transition:.4s; }
.mcard:hover{ transform:translateY(-8px); box-shadow:var(--shadow-lg); }
.mcard.gold{ background:linear-gradient(165deg,#FBE08A,#E8B53C); transform:translateY(-18px); box-shadow:var(--shadow-md); }
.mcard.gold:hover{ transform:translateY(-26px); }
.mcard.silver{ background:linear-gradient(165deg,#EDEFF3,#C7CDD9); color:#33384a; }
.mcard.bronze{ background:linear-gradient(165deg,#F2C9A0,#D08B53); color:#4a2c12; }
.mcard__medal{ width:74px; height:74px; margin:0 auto 18px; }
.mcard h3{ font-family:var(--display); font-size:24px; font-weight:700; margin-bottom:6px; }
.mcard .tier{ font:600 12px/1 var(--body); letter-spacing:.16em; text-transform:uppercase; opacity:.7; margin-bottom:20px; }
.mcard ul{ list-style:none; text-align:left; display:flex; flex-direction:column; gap:11px; padding:0; }
.mcard li{ font-size:14px; display:flex; align-items:flex-start; gap:10px; font-weight:500; }
.mcard li svg{ width:18px; height:18px; flex:none; margin-top:1px; }
.mcard.gold::after{ content:"★ TOP RANK"; position:absolute; top:16px; right:-30px; transform:rotate(40deg); background:#3a2a05; color:var(--gold-lt); font:700 10px/1 var(--body); letter-spacing:.1em; padding:6px 38px; }

/* testimonials */
.tsection{ background:var(--ink); color:var(--paper); }
.tsection .shead h2{ color:var(--paper); }
.tcarousel{ overflow:hidden; }
.ttrack{ display:flex; gap:26px; transition:transform .6s cubic-bezier(.2,.8,.2,1); }
.tcard{ flex:0 0 calc((100% - 52px)/3); border-radius:var(--r-lg); padding:34px 30px; position:relative; }
.tcard__q{ font-family:var(--display); font-size:60px; line-height:.6; color:var(--gold); opacity:.4; position:absolute; top:24px; right:26px; }
.tcard .stars{ margin-bottom:16px; }
.tcard p{ font-size:15.5px; color:var(--paper-70); line-height:1.65; margin-bottom:26px; font-weight:500; position:relative; z-index:1; }
.tcard__who{ display:flex; align-items:center; gap:14px; padding-top:20px; border-top:1px solid rgba(255,255,255,.08); }
.tcard__av{ width:48px; height:48px; border-radius:50%; display:grid; place-items:center; font:700 16px/1 var(--body); color:#fff; flex:none; }
.tcard__who b{ font-size:15px; display:block; }
.tcard__who small{ font-size:12.5px; color:var(--paper-45); }

/* faq */
.faq-wrap{ display:grid; grid-template-columns:.9fr 1.1fr; gap:60px; align-items:start; }
.faq-h{ font-size:clamp(30px,4vw,46px); margin:16px 0 0; }
.acc{ border-top:1px solid var(--paper-line); }
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
.faq-aside{ background:var(--ink); color:var(--paper); border-radius:var(--r-lg); padding:40px 34px; position:sticky; top:96px; }
.faq-aside h3{ font-family:var(--display); font-size:26px; font-weight:600; margin-bottom:12px; }
.faq-aside p{ color:var(--paper-45); font-size:14.5px; margin-bottom:26px; }
.faq-aside .btn{ width:100%; }
.faq-aside__help{ margin-top:24px; padding-top:24px; border-top:1px solid rgba(255,255,255,.1); display:flex; align-items:center; gap:14px; }
.faq-aside__help b{ display:block; font-size:14px; }
.faq-aside__help small{ color:var(--paper-45); font-size:12.5px; }

/* register */
.register{ background:var(--paper-2); }
.reg-grid{ display:grid; grid-template-columns:1fr 1fr; gap:60px; align-items:center; }
.reg-copy h2{ font-family:var(--display); font-weight:600; font-size:clamp(30px,4vw,46px); line-height:1.05; letter-spacing:-.02em; margin-top:18px; }
.reg-copy p{ font-size:17px; color:var(--ink-70); margin:20px 0 30px; }
.reg-feats{ display:flex; flex-direction:column; gap:16px; }
.reg-feat{ display:flex; gap:14px; align-items:flex-start; }
.reg-feat__ic{ width:42px; height:42px; border-radius:12px; flex:none; display:grid; place-items:center; background:var(--paper); border:1px solid var(--paper-line); font-size:20px; }
.reg-feat b{ font-size:15px; }
.reg-feat small{ display:block; color:var(--ink-55); font-size:13px; margin-top:2px; }
.form-card{ border-radius:var(--r-lg); padding:38px 34px; box-shadow:var(--shadow-md); }
.form-card h3{ font-family:var(--display); font-size:24px; font-weight:600; margin-bottom:6px; }
.form-card > form > p{ font-size:14px; color:var(--ink-55); margin-bottom:26px; }
.field{ margin-bottom:18px; }
.field label{ display:block; font:600 12.5px/1 var(--body); letter-spacing:.04em; color:var(--ink-70); margin-bottom:8px; text-transform:uppercase; }
.field input,.field select{ width:100%; font:500 15px/1 var(--body); padding:14px 16px; border-radius:12px; border:1.5px solid var(--paper-line); background:rgba(255,255,255,.6); color:var(--ink); transition:.2s; }
.field input::placeholder{ color:var(--ink-35); }
.field input:focus,.field select:focus{ outline:none; border-color:var(--saffron); background:#fff; box-shadow:0 0 0 4px rgba(238,106,44,.12); }
.field.err input,.field.err select{ border-color:var(--saffron-dk); background:rgba(201,80,26,.05); }
.field .msg{ font-size:12px; color:var(--saffron-dk); margin-top:6px; display:none; }
.field.err .msg{ display:block; }
.field-row{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-card .btn{ width:100%; margin-top:8px; }
.form-note{ text-align:center; font-size:12px; color:var(--ink-55); margin-top:16px; }
.form-success{ text-align:center; padding:30px 10px; animation:fadeUp .5s; }
.form-success svg{ width:64px; height:64px; margin:0 auto 18px; }
.form-success h3{ margin-bottom:8px; }
.form-success p{ color:var(--ink-70); font-size:14.5px; }
@keyframes fadeUp{ from{ opacity:0; transform:translateY(14px);} to{ opacity:1; transform:none;} }

/* cta */
.cta{ padding:104px 0; }
.cta__inner{ position:relative; overflow:hidden; border-radius:var(--r-xl); background:linear-gradient(135deg,var(--saffron),var(--saffron-dk) 70%,#9c3d12); padding:70px 60px; text-align:center; color:#fff; box-shadow:var(--shadow-lg); }
.cta__inner::before{ content:""; position:absolute; inset:0; background-image:radial-gradient(rgba(255,255,255,.13) 1.5px,transparent 1.5px); background-size:28px 28px; }
.cta__inner::after{ content:""; position:absolute; width:380px; height:380px; border-radius:50%; background:radial-gradient(circle,var(--gold-lt),transparent 65%); opacity:.5; top:-180px; right:-80px; }
.cta h2{ position:relative; font-family:var(--display); font-weight:600; font-size:clamp(30px,4.5vw,54px); line-height:1.04; letter-spacing:-.02em; max-width:760px; margin:0 auto 18px; color:#fff; }
.cta h2 .ital{ color:#fff; }
.cta p{ position:relative; font-size:18px; opacity:.92; max-width:520px; margin:0 auto 34px; }
.cta__btns{ position:relative; display:flex; gap:14px; justify-content:center; flex-wrap:wrap; }

/* footer */
footer{ background:var(--ink); color:var(--paper-70); padding:74px 0 0; }
.foot-grid{ display:grid; grid-template-columns:1.6fr 1fr 1fr 1fr; gap:40px; padding-bottom:54px; }
.foot-brand .brand{ color:var(--paper); margin-bottom:18px; }
.foot-brand p{ font-size:14px; max-width:300px; margin-bottom:22px; }
.foot-social{ display:flex; gap:10px; }
.foot-social a{ width:40px; height:40px; border-radius:11px; border:1px solid rgba(255,255,255,.12); display:grid; place-items:center; transition:.25s; color:var(--paper-70); }
.foot-social a:hover{ background:var(--saffron); border-color:var(--saffron); color:#fff; transform:translateY(-2px); }
.foot-col h4{ font:700 13px/1 var(--body); letter-spacing:.08em; text-transform:uppercase; color:var(--paper); margin-bottom:20px; }
.foot-col a{ display:block; font-size:14px; padding:7px 0; transition:.2s; }
.foot-col a:hover{ color:var(--gold-lt); padding-left:5px; }
.foot-bottom{ border-top:1px solid rgba(255,255,255,.1); padding:24px 0; display:flex; align-items:center; justify-content:space-between; font-size:13px; color:var(--paper-45); }
.foot-bottom__links{ display:flex; gap:22px; }

/* reveal */
[data-reveal]{ opacity:0; transform:translateY(28px); transition:opacity .7s cubic-bezier(.2,.8,.2,1), transform .7s cubic-bezier(.2,.8,.2,1); }
[data-reveal].in{ opacity:1; transform:none; }

/* responsive */
@media (max-width:1024px){
  .hero__grid{ grid-template-columns:1fr; gap:64px; padding-bottom:140px; }
  .hero__visual{ max-width:460px; margin-inline:auto; }
  .faq-wrap{ grid-template-columns:1fr; gap:34px; }
  .faq-aside{ position:static; }
  .reg-grid{ grid-template-columns:1fr; gap:40px; }
  .ecard{ flex-basis:calc((100% - 24px)/2); }
  .tcard{ flex-basis:calc((100% - 26px)/2); }
}
@media (max-width:860px){
  .nav__links, .nav-login{ display:none; }
  .burger{ display:flex; }
  .stats__grid{ grid-template-columns:repeat(2,1fr); gap:40px 20px; }
  .stat:nth-child(2)::after{ display:none; }
  .subj-grid{ grid-template-columns:1fr 1fr; }
  .steps{ grid-template-columns:1fr; gap:44px; }
  .steps::before{ display:none; }
  .medals{ grid-template-columns:1fr; }
  .mcard.gold{ transform:none; }
  .mcard.gold:hover{ transform:translateY(-8px); }
  .float-shape{ display:none; }
}
@media (max-width:600px){
  .wrap{ padding-inline:18px; }
  .section{ padding:74px 0; }
  .subj-grid{ grid-template-columns:1fr; }
  .ecard{ flex-basis:100%; }
  .tcard{ flex-basis:100%; }
  .field-row{ grid-template-columns:1fr; }
  .cta{ padding:74px 0; }
  .cta__inner{ padding:50px 26px; }
  .foot-grid{ grid-template-columns:1fr 1fr; gap:32px; }
  .foot-bottom{ flex-direction:column; gap:14px; text-align:center; }
  .hero__trust{ flex-wrap:wrap; }
  .chip.c1,.chip.c2{ left:-12px; }
}
</style>
