<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import ExamSelectCard from './Components/ExamSelectCard.vue';
import EnrolledExamCard from './Components/EnrolledExamCard.vue';
import SelectionSummary from './Components/SelectionSummary.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    enrolledExams: { type: Array, default: () => [] },
    available: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    classLevels: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();

/* ── filters ── */
const filters = reactive({
    search: props.filters.search ?? '',
    subject_id: props.filters.subject_id ?? '',
    class_level_id: props.filters.class_level_id ?? '',
});
let searchTimer = null;
const applyFilters = () => router.get(route('student.exams'), { ...filters }, { preserveState: true, replace: true, preserveScroll: true });
watch(() => [filters.subject_id, filters.class_level_id], applyFilters);
watch(() => filters.search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 350); });

const hasActiveFilters = computed(() => filters.search || filters.subject_id || (filters.class_level_id && filters.class_level_id !== 'all'));
const clearFilters = () => { filters.search = ''; filters.subject_id = ''; filters.class_level_id = 'all'; };

/* ── selection (available only) ── */
const selectedIds = ref([]);
const toggle = (id) => {
    const i = selectedIds.value.indexOf(id);
    i === -1 ? selectedIds.value.push(id) : selectedIds.value.splice(i, 1);
};
const selectedExams = computed(() => props.available.filter((e) => selectedIds.value.includes(e.id)));

const enrollForm = useForm({ exam_ids: [] });
const checkout = () => {
    enrollForm.exam_ids = [...selectedIds.value];
    enrollForm.post(route('student.exams.enroll'), { preserveScroll: true, onSuccess: () => { selectedIds.value = []; } });
};

/* ── toast ── */
const toast = ref(null);
let toastTimer = null;
watch(() => page.props.flash, (f) => {
    const msg = f?.success || f?.info || f?.error; if (!msg) return;
    toast.value = { type: f?.error ? 'error' : (f?.success ? 'success' : 'info'), msg };
    clearTimeout(toastTimer); toastTimer = setTimeout(() => (toast.value = null), 3600);
}, { deep: true, immediate: true });
</script>

<template>
    <Head title="Exams" />

    <StudentLayout title="Exams">
        <Transition name="toast"><div v-if="toast" class="toast" :class="toast.type">{{ toast.msg }}</div></Transition>

        <!-- ═══ My enrolled exams ═══ -->
        <section v-if="enrolledExams.length" class="block">
            <div class="block-head">
                <div class="bh-l">
                    <span class="bh-ic ic-green">🎯</span>
                    <div>
                        <h2>My Exams</h2>
                        <p>Exams you're enrolled in — start, continue or view results.</p>
                    </div>
                </div>
                <span class="count-pill">{{ enrolledExams.length }}</span>
            </div>
            <div class="grid">
                <EnrolledExamCard v-for="ex in enrolledExams" :key="ex.id" :exam="ex" />
            </div>
        </section>

        <!-- ═══ Browse / available ═══ -->
        <section class="block">
            <div class="block-head">
                <div class="bh-l">
                    <span class="bh-ic ic-saffron">🧭</span>
                    <div>
                        <h2>Browse Olympiads</h2>
                        <p>Discover new exams and enrol in one step.</p>
                    </div>
                </div>
            </div>

            <!-- modern filter bar -->
            <div class="filterbar">
                <div class="search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input v-model="filters.search" type="text" placeholder="Search exams…" />
                </div>
                <div class="selset">
                    <label class="sel">
                        <span class="sel-ic">📚</span>
                        <select v-model="filters.subject_id">
                            <option value="">All subjects</option>
                            <option v-for="s in subjects" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                        </select>
                        <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </label>
                    <label class="sel">
                        <span class="sel-ic">🎓</span>
                        <select v-model="filters.class_level_id">
                            <option value="all">All classes</option>
                            <option v-for="c in classLevels" :key="c.id" :value="String(c.id)">{{ c.label }}</option>
                        </select>
                        <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </label>
                    <button v-if="hasActiveFilters" class="clear" @click="clearFilters">Clear</button>
                </div>
            </div>

            <div v-if="available.length" class="grid">
                <ExamSelectCard
                    v-for="exam in available"
                    :key="exam.id"
                    :exam="exam"
                    :selected="selectedIds.includes(exam.id)"
                    @toggle="toggle"
                />
            </div>
            <div v-else class="empty">
                <div class="empty-ic">📭</div>
                <h3>No exams found</h3>
                <p>{{ hasActiveFilters ? 'Try a different filter.' : 'You\'re enrolled in everything available for now — check back soon!' }}</p>
            </div>
        </section>

        <SelectionSummary :items="selectedExams" :processing="enrollForm.processing" @checkout="checkout" @clear="selectedIds = []" />
    </StudentLayout>
</template>

<style scoped>
.toast { position: fixed; top: 1.1rem; right: 1.1rem; z-index: 60; padding: .8rem 1.1rem; border-radius: 13px; font-size: .9rem; font-weight: 600; color: #fff; box-shadow: 0 18px 40px -14px rgba(10,16,36,.5); }
.toast.success { background: linear-gradient(135deg, #1aa177, #168A66); }
.toast.info { background: linear-gradient(135deg, #3a5bd0, #2C49A6); }
.toast.error { background: linear-gradient(135deg, #ef4444, #DC2626); }
.toast-enter-active, .toast-leave-active { transition: all .35s cubic-bezier(.2,.7,.2,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(30px); }

.block { margin-bottom: 2rem; }
.block-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.1rem; }
.bh-l { display: flex; align-items: center; gap: .8rem; }
.bh-ic { width: 44px; height: 44px; flex-shrink: 0; border-radius: 13px; display: grid; place-items: center; font-size: 1.3rem; }
.ic-green { background: rgba(22,138,102,.12); }
.ic-saffron { background: rgba(238,106,44,.12); }
.block-head h2 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.4rem; color: #0A1024; margin: 0; line-height: 1.1; }
.block-head p { color: #5B6373; font-size: .86rem; margin: .15rem 0 0; }
.count-pill { font-family: "Space Grotesk", monospace; font-weight: 700; font-size: .95rem; color: #168A66; background: rgba(22,138,102,.12); padding: .35rem .8rem; border-radius: 999px; }

/* modern filter bar */
.filterbar { display: flex; flex-wrap: wrap; align-items: center; gap: .7rem; background: #fff; border: 1px solid #E7D9BE; border-radius: 16px; padding: .7rem; margin-bottom: 1.3rem; box-shadow: 0 2px 8px rgba(10,16,36,.04); }
.search { position: relative; flex: 1; min-width: 200px; display: flex; align-items: center; background: #FBF9F3; border: 1.5px solid transparent; border-radius: 11px; transition: border-color .2s, background .2s; }
.search:focus-within { border-color: #EE6A2C; background: #fff; }
.search svg { width: 18px; height: 18px; margin-left: .8rem; color: #9aa0ad; }
.search input { flex: 1; border: 0; background: transparent; outline: none; padding: .65rem .8rem; font-size: .92rem; font-family: "Plus Jakarta Sans", sans-serif; color: #0A1024; }
.selset { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.sel { position: relative; display: inline-flex; align-items: center; background: #FBF9F3; border: 1.5px solid transparent; border-radius: 11px; padding-left: .7rem; transition: border-color .2s, background .2s; }
.sel:focus-within { border-color: #EE6A2C; background: #fff; }
.sel-ic { font-size: .95rem; }
.sel select { appearance: none; -webkit-appearance: none; border: 0; background: transparent; outline: none; padding: .65rem 2rem .65rem .5rem; font-size: .9rem; font-family: "Plus Jakarta Sans", sans-serif; color: #0A1024; cursor: pointer; }
.sel .chev { position: absolute; right: .6rem; width: 16px; height: 16px; color: #9aa0ad; pointer-events: none; }
.clear { border: 0; background: transparent; color: #C9501A; font-weight: 700; font-size: .85rem; cursor: pointer; padding: .4rem .7rem; }
.clear:hover { text-decoration: underline; }

.grid { display: grid; gap: 1.1rem; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }

.empty { text-align: center; padding: 3rem 1rem; color: #5B6373; background: #fff; border: 1px dashed #E7D9BE; border-radius: 16px; }
.empty-ic { font-size: 2.2rem; margin-bottom: .4rem; }
.empty h3 { font-family: "Fraunces", serif; color: #0A1024; margin: 0 0 .3rem; }
.empty p { font-size: .9rem; margin: 0; }
</style>
