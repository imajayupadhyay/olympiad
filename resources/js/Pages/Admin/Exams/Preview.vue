<script setup>
// Admin exam simulator. Renders the REAL student exam room (Student/ExamRoom/Index) in
// preview mode, then scores the run in memory on the server. Nothing is recorded.
import ExamRoom from '@/Pages/Student/ExamRoom/Index.vue';
import PreviewResult from './Components/PreviewResult.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    attempt: { type: Object, required: true },
    exam: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
    questions: { type: Array, default: () => [] },
    remaining_seconds: { type: Number, default: 0 },
    back_url: { type: String, required: true },
});

const phase = ref('room');          // room | scoring | result | error
const run = ref(0);                 // bump to remount the room for a fresh retake
const lastRun = ref(null);
const result = ref(null);
const errorText = ref('');

// Review follows the order the admin actually saw (questions may be shuffled).
const seenOrder = computed(() => new Map(props.questions.map((question, index) => [question.id, index])));

async function finish(payload) {
    lastRun.value = payload;
    phase.value = 'scoring';
    try {
        const { data } = await window.axios.post(route('admin.exams.preview.score', props.exam.id), {
            answers: payload.answers,
            time_taken_seconds: payload.time_taken_seconds,
        });
        data.review.sort((a, b) => (seenOrder.value.get(a.id) ?? 0) - (seenOrder.value.get(b.id) ?? 0));
        result.value = { ...data, reason: payload.reason };
        phase.value = 'result';
    } catch (error) {
        errorText.value = error?.response?.status === 403
            ? 'You no longer have permission to preview exams.'
            : 'Could not score this preview. Check your connection and try again.';
        phase.value = 'error';
    }
}

function retake() {
    result.value = null;
    run.value++;
    phase.value = 'room';
}

const exit = () => router.visit(props.back_url);
</script>

<template>
    <ExamRoom
        v-if="phase === 'room'"
        :key="run"
        preview
        :attempt="attempt"
        :exam="exam"
        :sections="sections"
        :questions="questions"
        :remaining_seconds="remaining_seconds"
        @finish="finish"
        @exit="exit"
    />

    <template v-else>
        <Head :title="`Preview · ${exam.name}`" />

        <div v-if="phase === 'scoring' || phase === 'error'" class="state">
            <div class="state-card">
                <template v-if="phase === 'scoring'">
                    <span class="spinner" aria-hidden="true"></span>
                    <p>Scoring your preview…</p>
                </template>
                <template v-else>
                    <p class="err">{{ errorText }}</p>
                    <div class="state-actions">
                        <button type="button" class="ghost" @click="exit">Exit preview</button>
                        <button type="button" class="solid" @click="finish(lastRun)">Try again</button>
                    </div>
                </template>
            </div>
        </div>

        <PreviewResult
            v-else
            :exam="exam"
            :result="result"
            @retake="retake"
            @exit="exit"
        />
    </template>
</template>

<style scoped>
.state { position: fixed; inset: 0; display: grid; place-items: center; padding: 1.5rem; background: #FBF6EC; font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
.state-card { background: #fff; border: 1px solid #E7D9BE; border-radius: 20px; padding: 2rem; max-width: 400px; width: 100%; text-align: center; box-shadow: 0 30px 70px -28px rgba(10,16,36,.4); }
.state-card p { margin: .8rem 0 0; color: #41485a; font-size: .95rem; }
.state-card .err { margin: 0 0 1.2rem; color: #DC2626; font-weight: 600; }
.spinner { display: inline-block; width: 34px; height: 34px; border-radius: 50%; border: 3px solid #F3E9D6; border-top-color: #EE6A2C; animation: spin .8s linear infinite; }
.state-actions { display: flex; gap: .6rem; }
.ghost, .solid { flex: 1; font-weight: 700; font-size: .9rem; padding: .75rem; border-radius: 12px; cursor: pointer; }
.ghost { border: 1px solid #E7D9BE; background: #fff; color: #41485a; }
.solid { border: 0; color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); }
@keyframes spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .spinner { animation-duration: 2.4s; } }
</style>
