<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    exam: { type: Object, required: true },
    attempt: { type: Object, default: () => ({}) },
});

const timeText = computed(() => {
    const s = props.attempt.time_taken_seconds ?? 0;
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${m}m ${String(sec).padStart(2, '0')}s`;
});
</script>

<template>
    <Head title="Test submitted" />

    <div class="wrap">
        <div class="blob a"></div>
        <div class="blob b"></div>

        <div class="card">
            <div class="tick">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
            <h1>Test submitted!</h1>
            <p class="lead">Your responses for <strong>{{ exam.name }}</strong> have been recorded.</p>

            <div class="stats">
                <div><strong>{{ attempt.answered ?? 0 }}</strong><span>Answered</span></div>
                <div><strong>{{ attempt.total_questions ?? 0 }}</strong><span>Questions</span></div>
                <div><strong>{{ timeText }}</strong><span>Time taken</span></div>
            </div>

            <div class="note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                Results will be <strong>declared by the admin</strong>. You'll be able to view your
                rank, score and certificate from the Results section once they're released.
            </div>

            <div class="actions">
                <Link :href="route('student.results')" class="ghost">View Results</Link>
                <Link :href="route('student.dashboard')" class="solid">Back to Dashboard</Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wrap { position: fixed; inset: 0; display: grid; place-items: center; padding: 1.5rem; overflow: auto;
    background: radial-gradient(80% 60% at 100% 0%, rgba(238,106,44,.06), transparent 55%), #FBF6EC;
    font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
.blob { position: absolute; border-radius: 50%; filter: blur(60px); z-index: 0; }
.blob.a { width: 360px; height: 360px; top: -120px; right: -80px; background: radial-gradient(circle, rgba(22,138,102,.4), transparent 70%); }
.blob.b { width: 300px; height: 300px; bottom: -120px; left: -60px; background: radial-gradient(circle, rgba(44,73,166,.35), transparent 70%); }

.card { position: relative; z-index: 1; background: #fff; border: 1px solid #E7D9BE; border-radius: 24px; padding: 2.4rem; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 40px 80px -30px rgba(10,16,36,.4); animation: pop .5s cubic-bezier(.2,.8,.2,1) both; }
.tick { width: 72px; height: 72px; margin: 0 auto 1.2rem; border-radius: 50%; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #1aa177, #168A66); box-shadow: 0 16px 34px -12px rgba(22,138,102,.6); animation: bounce .6s .15s both; }
.tick svg { width: 34px; height: 34px; }
.card h1 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.7rem; color: #0A1024; margin: 0 0 .4rem; }
.lead { color: #5B6373; font-size: .95rem; margin: 0 0 1.5rem; }

.stats { display: flex; gap: .7rem; margin-bottom: 1.4rem; }
.stats div { flex: 1; background: #F3E9D6; border-radius: 13px; padding: .8rem; }
.stats strong { display: block; font-family: "Space Grotesk", monospace; font-size: 1.2rem; color: #0A1024; }
.stats span { font-size: .72rem; color: #5B6373; }

.note { display: flex; gap: .6rem; text-align: left; background: rgba(44,73,166,.07); border: 1px solid rgba(44,73,166,.2); border-radius: 13px; padding: .9rem; font-size: .84rem; color: #41485a; line-height: 1.5; margin-bottom: 1.5rem; }
.note svg { width: 20px; height: 20px; color: #2C49A6; flex-shrink: 0; }

.actions { display: flex; gap: .7rem; }
.ghost, .solid { flex: 1; text-decoration: none; text-align: center; font-weight: 700; font-size: .9rem; padding: .8rem; border-radius: 12px; }
.ghost { border: 1px solid #E7D9BE; color: #41485a; }
.ghost:hover { border-color: #c9bda0; }
.solid { border: 0; color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); box-shadow: 0 12px 26px -12px rgba(238,106,44,.8); }
.solid:hover { transform: translateY(-2px); }

@keyframes pop { from { opacity: 0; transform: translateY(20px) scale(.97); } to { opacity: 1; transform: none; } }
@keyframes bounce { 0% { transform: scale(0); } 60% { transform: scale(1.15); } 100% { transform: scale(1); } }
</style>
