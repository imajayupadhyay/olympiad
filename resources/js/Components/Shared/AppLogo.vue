<template>
  <div class="flex items-center gap-3 select-none">
    <!-- SVG Badge Icon -->
    <svg
      :width="size"
      :height="size"
      viewBox="0 0 64 64"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      :aria-label="'National Olympiad Hunt logo'"
    >
      <defs>
        <!-- Gold ring gradient -->
        <linearGradient id="noh-gold-ring" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%"   stop-color="#FEF3C7"/>
          <stop offset="40%"  stop-color="#F2C84B"/>
          <stop offset="100%" stop-color="#D6991F"/>
        </linearGradient>
        <!-- Navy disc gradient -->
        <linearGradient id="noh-navy" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%"   stop-color="#131C3D"/>
          <stop offset="100%" stop-color="#14244f"/>
        </linearGradient>
        <!-- Flame gradient (bottom=orange, top=pale yellow) -->
        <linearGradient id="noh-flame" x1="0%" y1="100%" x2="0%" y2="0%">
          <stop offset="0%"   stop-color="#EE6A2C"/>
          <stop offset="55%"  stop-color="#F2854E"/>
          <stop offset="100%" stop-color="#FEF9C3"/>
        </linearGradient>
        <!-- Torch metal gradient -->
        <linearGradient id="noh-torch" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%"   stop-color="#92400E"/>
          <stop offset="45%"  stop-color="#F2C84B"/>
          <stop offset="100%" stop-color="#92400E"/>
        </linearGradient>
        <!-- Drop shadow filter -->
        <filter id="noh-glow" x="-20%" y="-20%" width="140%" height="140%">
          <feGaussianBlur in="SourceGraphic" stdDeviation="1.5" result="blur"/>
          <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
        </filter>
      </defs>

      <!-- Outer gold ring -->
      <circle cx="32" cy="32" r="31.5" fill="url(#noh-gold-ring)"/>
      <!-- Inner navy disc -->
      <circle cx="32" cy="32" r="28"   fill="url(#noh-navy)"/>

      <!-- ── Stars (top arc) ── -->
      <!-- Centre star -->
      <polygon
        points="32,8.5 33.8,13.5 39,13.5 34.8,16.6 36.6,21.5 32,18.3 27.4,21.5 29.2,16.6 25,13.5 30.2,13.5"
        fill="#F2C84B"
        filter="url(#noh-glow)"
      />
      <!-- Left small star -->
      <polygon
        points="17,20 17.9,22.7 20.7,22.7 18.5,24.4 19.4,27 17,25.4 14.6,27 15.5,24.4 13.3,22.7 16.1,22.7"
        fill="#F2C84B" opacity="0.72"
      />
      <!-- Right small star -->
      <polygon
        points="47,20 47.9,22.7 50.7,22.7 48.5,24.4 49.4,27 47,25.4 44.6,27 45.5,24.4 43.3,22.7 46.1,22.7"
        fill="#F2C84B" opacity="0.72"
      />

      <!-- ── Torch body ── -->
      <!-- Handle -->
      <rect x="29.5" y="46" width="5" height="11.5" rx="2.5" fill="url(#noh-torch)"/>
      <!-- Base plate -->
      <rect x="26"   y="56" width="12" height="3"    rx="1.5" fill="url(#noh-gold-ring)"/>

      <!-- Cup (trapezoid) -->
      <path d="M25.5 42 L38.5 42 L36.5 48 L27.5 48 Z" fill="url(#noh-torch)"/>
      <!-- Cup top rim -->
      <rect x="24" y="39.5" width="16" height="4" rx="2" fill="url(#noh-gold-ring)"/>

      <!-- ── Flame ── -->
      <!-- Main flame body -->
      <path
        d="M32 41
           C27 35 24 28 26 20
           C27 24 29 22 28 17
           C29 20 31 18 30 14
           C31 17 33 14 32 11
           C34 15 35 18 33 23
           C35 18 35 15 33 12
           C37 18 37 27 33 34
           C35 31 35 37 32 41 Z"
        fill="url(#noh-flame)"
        filter="url(#noh-glow)"
      />
      <!-- Inner bright core (pale flicker) -->
      <path
        d="M32 39
           C30 34 28 28 30 23
           C31 26 32 24 31 20
           C32 23 33 21 32 17
           C33 20 34 24 32 37 Z"
        fill="white" opacity="0.30"
      />

      <!-- Tiny sparkle dots in corners -->
      <circle cx="11" cy="32" r="1.5" fill="#F2C84B" opacity="0.55"/>
      <circle cx="53" cy="32" r="1.5" fill="#F2C84B" opacity="0.55"/>
    </svg>

    <!-- Wordmark -->
    <div v-if="showText" class="leading-none">
      <div
        class="font-heading font-bold tracking-tight leading-tight"
        :class="textClass"
        :style="{ color: primaryColor }"
      >National Olympiad</div>
      <div
        class="font-heading font-bold tracking-tight leading-none"
        :class="textClass"
        style="color: #EE6A2C;"
      >Hunt</div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  size:         { type: Number,  default: 48 },
  showText:     { type: Boolean, default: true },
  primaryColor: { type: String,  default: '#ffffff' },
  textClass:    { type: String,  default: 'text-xl' },
});
</script>
