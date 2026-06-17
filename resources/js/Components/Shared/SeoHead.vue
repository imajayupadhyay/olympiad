<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Centralised <head> meta for every page.
 * - `title`       → document title (run through the global "… - National Olympiad Hunt" template).
 * - `description` → meta description + Open Graph / Twitter description (falls back to the site blurb).
 * - `noindex`     → emit robots noindex,nofollow (use on private/auth pages).
 * Tags use head-key so Inertia replaces (not duplicates) them across navigation.
 */
const props = defineProps({
    title:       { type: String, default: null },
    description: { type: String, default: '' },
    noindex:     { type: Boolean, default: false },
    image:       { type: String, default: null },
});

const SITE = 'National Olympiad Hunt';
const FALLBACK = "National Olympiad Hunt is India's online olympiad platform for Class 1–12 students — register, sit timed olympiad exams, climb national ranks and earn certificates.";

const page = usePage();
const desc = computed(() => props.description || FALLBACK);
const ogTitle = computed(() => (props.title ? `${props.title} - ${SITE}` : SITE));
const url = computed(() => (typeof window !== 'undefined' ? window.location.origin : '') + page.url);
</script>

<template>
    <Head :title="title ?? undefined">
        <meta head-key="description" name="description" :content="desc" />
        <meta head-key="ogtitle" property="og:title" :content="ogTitle" />
        <meta head-key="ogdesc" property="og:description" :content="desc" />
        <meta head-key="ogurl" property="og:url" :content="url" />
        <meta head-key="twtitle" name="twitter:title" :content="ogTitle" />
        <meta head-key="twdesc" name="twitter:description" :content="desc" />
        <meta v-if="image" head-key="ogimage" property="og:image" :content="image" />
        <meta v-if="noindex" head-key="robots" name="robots" content="noindex, nofollow" />
    </Head>
</template>
