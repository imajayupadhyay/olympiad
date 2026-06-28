<template>
  <SeoHead description="National Olympiad Hunt — India's online olympiad exam platform for Class 1–12. Register, compete across subjects and earn national ranks, medals and certificates." />
  <div class="min-h-screen flex flex-col bg-bg">

    <!-- ── Navbar ── -->
    <header
      ref="navRef"
      class="sticky top-0 z-50 transition-all duration-300"
      :class="scrolled
        ? 'bg-primary/95 backdrop-blur-md shadow-lg shadow-primary/20'
        : 'bg-primary'"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">

        <!-- Logo -->
        <Link href="/" class="inline-flex items-center">
          <AppLogo :size="54" variant="light" />
        </Link>

        <!-- Desktop Nav -->
        <nav class="hidden md:flex items-center gap-1 text-sm font-medium">
          <NavLink href="/exams">Exams</NavLink>
          <NavLink href="/results">Results</NavLink>
          <NavLink href="/blog">Blog</NavLink>
          <NavLink href="/about">About</NavLink>
          <NavLink href="/contact">Contact</NavLink>
        </nav>

        <!-- Auth buttons -->
        <div class="flex items-center gap-3">
          <Link
            href="/login"
            class="text-sm text-blue-100 hover:text-white transition-colors font-medium hidden sm:block"
          >Login</Link>
          <Link
            href="/register"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-white px-4 py-2 rounded-lg transition-all duration-200 hover:scale-105"
            style="background: linear-gradient(135deg,#EA580C,#C2410C); box-shadow: 0 4px 12px rgba(234,88,12,0.35);"
          >
            Register Free
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
            </svg>
          </Link>

          <!-- Mobile menu button -->
          <button
            @click="mobileOpen = !mobileOpen"
            class="md:hidden text-white p-1 rounded-lg hover:bg-white/10 transition-colors"
            aria-label="Toggle menu"
          >
            <svg v-if="!mobileOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Mobile menu -->
      <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
      >
        <div v-if="mobileOpen" class="md:hidden bg-primary border-t border-white/10 px-4 py-3 space-y-1">
          <MobileNavLink href="/exams"   @click="mobileOpen=false">Exams</MobileNavLink>
          <MobileNavLink href="/results" @click="mobileOpen=false">Results</MobileNavLink>
          <MobileNavLink href="/blog"    @click="mobileOpen=false">Blog</MobileNavLink>
          <MobileNavLink href="/about"   @click="mobileOpen=false">About</MobileNavLink>
          <MobileNavLink href="/contact" @click="mobileOpen=false">Contact</MobileNavLink>
          <MobileNavLink href="/login"   @click="mobileOpen=false">Login</MobileNavLink>
        </div>
      </Transition>
    </header>

    <!-- Page Content -->
    <main class="flex-1">
      <slot />
    </main>

    <!-- ── Footer ── -->
    <footer style="background: linear-gradient(135deg, #1E2F6B 0%, #1E3A8A 100%);">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-10">

          <!-- Brand column -->
          <div class="md:col-span-2">
            <Link href="/" class="inline-block mb-4">
              <AppLogo :size="54" variant="light" />
            </Link>
            <p class="text-blue-200 text-sm leading-relaxed max-w-xs mt-3" style="font-family:'Inter',sans-serif;">
              India's premier online olympiad platform. Empowering school students to compete, excel, and earn recognition nationwide.
            </p>
            <div class="flex items-center gap-3 mt-5">
              <a href="#" aria-label="Facebook"  class="w-8 h-8 rounded-full bg-white/10 hover:bg-accent flex items-center justify-center transition-colors">
                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
              </a>
              <a href="#" aria-label="Twitter/X" class="w-8 h-8 rounded-full bg-white/10 hover:bg-accent flex items-center justify-center transition-colors">
                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
              </a>
              <a href="#" aria-label="Instagram"  class="w-8 h-8 rounded-full bg-white/10 hover:bg-accent flex items-center justify-center transition-colors">
                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5" fill="none" stroke="currentColor" stroke-width="2"/><path fill="none" stroke="currentColor" stroke-width="2" d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke="currentColor" stroke-width="2"/></svg>
              </a>
            </div>
          </div>

          <!-- Quick Links -->
          <div>
            <h4 class="font-heading font-semibold text-white mb-4 text-sm uppercase tracking-wider">Quick Links</h4>
            <ul class="space-y-2.5 text-sm" style="font-family:'Inter',sans-serif;">
              <li v-for="link in quickLinks" :key="link.href">
                <Link :href="link.href" class="text-blue-200 hover:text-white hover:translate-x-1 inline-block transition-all duration-200">
                  {{ link.label }}
                </Link>
              </li>
            </ul>
          </div>

          <!-- Contact -->
          <div>
            <h4 class="font-heading font-semibold text-white mb-4 text-sm uppercase tracking-wider">Contact</h4>
            <ul class="space-y-3 text-sm text-blue-200" style="font-family:'Inter',sans-serif;">
              <li class="flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 shrink-0 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                info@nationalolypmiadhunt.in
              </li>
              <li class="flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 shrink-0 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                New Delhi, India
              </li>
            </ul>
          </div>
        </div>

        <!-- Bottom bar -->
        <div class="border-t border-white/10 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-blue-300" style="font-family:'Inter',sans-serif;">
          <span>© {{ new Date().getFullYear() }} National Olympiad Hunt. All rights reserved.</span>
          <div class="flex gap-4">
            <Link href="/privacy"  class="hover:text-white transition-colors">Privacy Policy</Link>
            <Link href="/terms"    class="hover:text-white transition-colors">Terms of Use</Link>
            <Link href="/refund"   class="hover:text-white transition-colors">Refund Policy</Link>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/Components/Shared/AppLogo.vue';
import SeoHead from '@/Components/Shared/SeoHead.vue';

// ── Scroll-aware navbar ──
const scrolled    = ref(false);
const mobileOpen  = ref(false);

const onScroll = () => { scrolled.value = window.scrollY > 40; };

onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }));
onUnmounted(() => window.removeEventListener('scroll', onScroll));

// ── Footer data ──
const quickLinks = [
  { href: '/exams',   label: 'Exams' },
  { href: '/results', label: 'Results & Toppers' },
  { href: '/pricing', label: 'Pricing' },
  { href: '/blog',    label: 'Blog' },
  { href: '/faq',     label: 'FAQs' },
  { href: '/about',   label: 'About Us' },
  { href: '/contact', label: 'Contact' },
];
</script>

<!-- ── Inline nav helper components ── -->
<script>
import { defineComponent, h } from 'vue';
import { Link } from '@inertiajs/vue3';

const NavLink = defineComponent({
  props: { href: String },
  setup(props, { slots }) {
    return () => h(Link, {
      href: props.href,
      class: 'px-3 py-1.5 rounded-lg text-blue-100 hover:text-white hover:bg-white/10 transition-all duration-200',
    }, slots.default);
  },
});

const MobileNavLink = defineComponent({
  props: { href: String },
  emits: ['click'],
  setup(props, { slots, emit }) {
    return () => h(Link, {
      href: props.href,
      onClick: () => emit('click'),
      class: 'block px-3 py-2 rounded-lg text-blue-100 hover:text-white hover:bg-white/10 transition-colors text-sm',
    }, slots.default);
  },
});
</script>
