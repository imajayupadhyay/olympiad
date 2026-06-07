<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    certificates: { type: Array, default: () => [] },
});

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
</script>

<template>
    <Head title="Certificates" />

    <StudentLayout title="Certificates">
        <div class="head">
            <h2 class="h2">My Certificates</h2>
            <p class="sub">Download certificates for your released results.</p>
        </div>

        <div v-if="certificates.length" class="grid">
            <div v-for="c in certificates" :key="c.id" class="cert">
                <div class="ribbon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="9" r="6"/><path d="M9 14.5 7 22l5-3 5 3-2-7.5"/></svg>
                </div>
                <span class="cert-subj" :style="c.subject?.color ? { color: c.subject.color } : {}">{{ c.subject?.icon || '🏅' }} {{ c.subject?.name }}</span>
                <h3>{{ c.exam?.name }}</h3>
                <p class="issued">Issued {{ fmt(c.generated_at) }}</p>
                <a :href="route('student.certificates.download', c.id)" class="dl">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 21h16"/></svg>
                    Download
                </a>
            </div>
        </div>

        <div v-else class="empty">
            <div class="empty-ic">🏅</div>
            <h3>No certificates yet</h3>
            <p>Certificates appear here after your results are released and issued by the admin.</p>
            <Link :href="route('student.results')" class="cta">View results →</Link>
        </div>
    </StudentLayout>
</template>

<style scoped>
.head { margin-bottom: 1.3rem; }
.h2 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.6rem; color: #0A1024; margin: 0; }
.sub { color: #5B6373; font-size: .92rem; margin: .25rem 0 0; }

.grid { display: grid; gap: 1.1rem; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
.cert { position: relative; overflow: hidden; background: #fff; border: 1px solid #E7D9BE; border-radius: 18px; padding: 1.3rem; box-shadow: 0 2px 8px rgba(10,16,36,.04); transition: transform .2s, box-shadow .2s; }
.cert:hover { transform: translateY(-3px); box-shadow: 0 20px 40px -22px rgba(10,16,36,.3); }
.ribbon { width: 46px; height: 46px; border-radius: 13px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #F2C84B, #D6991F); margin-bottom: .9rem; box-shadow: 0 10px 22px -10px rgba(214,153,31,.7); }
.ribbon svg { width: 24px; height: 24px; }
.cert-subj { font-size: .76rem; font-weight: 700; color: #5B6373; }
.cert h3 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.1rem; color: #0A1024; margin: .3rem 0 .3rem; }
.issued { font-size: .8rem; color: #9aa0ad; margin: 0 0 1rem; }
.dl { display: inline-flex; align-items: center; gap: .5rem; text-decoration: none; font-weight: 700; font-size: .85rem; color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); padding: .55rem 1rem; border-radius: 11px; box-shadow: 0 10px 22px -12px rgba(238,106,44,.8); }
.dl svg { width: 16px; height: 16px; }

.empty { text-align: center; padding: 3.5rem 1rem; color: #5B6373; }
.empty-ic { font-size: 2.4rem; margin-bottom: .5rem; }
.empty h3 { font-family: "Fraunces", serif; color: #0A1024; margin: 0 0 .3rem; }
.empty p { font-size: .9rem; margin: 0 0 1rem; max-width: 36ch; margin-inline: auto; }
.cta { text-decoration: none; font-weight: 700; color: #C9501A; }
.cta:hover { text-decoration: underline; }
</style>
