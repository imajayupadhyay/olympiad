<script setup>
/**
 * Rotating "someone just registered" social-proof card.
 *
 * The names, cities and timings are illustrative sample data, not real
 * registrations — the page is a campaign landing page and this is a momentum
 * cue, deliberately kept generic so it never resembles a specific person.
 * Swap `NAMES`/`CITIES` for a real feed later if you ever want live proof.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    // Hidden while the register sheet is open so it never competes with the form.
    paused: { type: Boolean, default: false },
});

const DISMISS_KEY = 'noh_marketing_proof_dismissed';

const NAMES = [
    'Aarav S.', 'Priya M.', 'Rohan K.', 'Ananya D.', 'Vihaan R.', 'Ishita B.',
    'Arjun P.', 'Saanvi T.', 'Kabir N.', 'Diya G.', 'Aditya V.', 'Meera J.',
    'Krishna L.', 'Navya C.', 'Reyansh A.', 'Aisha F.', 'Dhruv H.', 'Tanvi W.',
    'Yash M.', 'Riya S.', 'Advik B.', 'Kavya R.', 'Om P.', 'Sara Q.',
    'Ayaan Z.', 'Myra K.', 'Ved D.', 'Anika T.', 'Ishaan G.', 'Pari N.',
];

const CITIES = [
    'Pune', 'Jaipur', 'Lucknow', 'Indore', 'Nagpur', 'Bhopal', 'Patna', 'Surat',
    'Kochi', 'Coimbatore', 'Chandigarh', 'Guwahati', 'Ranchi', 'Raipur', 'Nashik',
    'Vadodara', 'Varanasi', 'Ludhiana', 'Mysuru', 'Visakhapatnam',
];

const ACTIONS = [
    'registered for the Maths Olympiad',
    'registered for the Science Olympiad',
    'joined the English Olympiad',
    'enrolled in 2 olympiads',
    'registered for the GK Olympiad',
    'joined the Reasoning Olympiad',
];

const pick = (list) => list[Math.floor(Math.random() * list.length)];
const rand = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;

const item = ref(null);
const visible = ref(false);
const dismissed = ref(false);

let showTimer = null;
let hideTimer = null;

function schedule(delay) {
    clearTimeout(showTimer);
    showTimer = setTimeout(() => {
        if (dismissed.value || props.paused) return schedule(rand(12000, 20000));

        item.value = {
            name: pick(NAMES),
            city: pick(CITIES),
            action: pick(ACTIONS),
            ago: rand(1, 14),
        };
        visible.value = true;

        hideTimer = setTimeout(() => {
            visible.value = false;
            schedule(rand(18000, 30000));
        }, 6000);
    }, delay);
}

const dismiss = () => {
    dismissed.value = true;
    visible.value = false;
    clearTimeout(showTimer);
    clearTimeout(hideTimer);
    try { sessionStorage.setItem(DISMISS_KEY, '1'); } catch { /* private mode */ }
};

// Hide instantly while the register sheet is up, then resume the rotation.
watch(() => props.paused, (paused) => {
    if (paused) visible.value = false;
});

onMounted(() => {
    try { dismissed.value = sessionStorage.getItem(DISMISS_KEY) === '1'; } catch { /* private mode */ }
    if (dismissed.value) return;
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;

    schedule(8000);
});

onBeforeUnmount(() => {
    clearTimeout(showTimer);
    clearTimeout(hideTimer);
});
</script>

<template>
    <Transition name="proof">
        <aside v-if="visible && item && !paused" class="proof glass" aria-live="polite">
            <span class="proof__av">{{ item.name.charAt(0) }}</span>
            <div class="proof__body">
                <b>{{ item.name }}</b> from {{ item.city }}
                <small>{{ item.action }} · {{ item.ago }} min ago</small>
            </div>
            <button class="proof__x" type="button" aria-label="Dismiss" @click="dismiss">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </aside>
    </Transition>
</template>

<style scoped>
.proof{
  --ink:#0A1024; --saffron:#EE6A2C; --emerald:#168A66;
  --ink-55:rgba(10,16,36,.55); --ink-12:rgba(10,16,36,.12);
  --body:"Plus Jakarta Sans",system-ui,sans-serif;

  position:fixed; left:20px; bottom:20px; z-index:70;
  display:flex; align-items:center; gap:13px;
  max-width:330px; padding:12px 14px; border-radius:16px;
  font-family:var(--body); color:var(--ink);
  background:rgba(251,246,236,.86); backdrop-filter:blur(14px) saturate(150%); -webkit-backdrop-filter:blur(14px) saturate(150%);
  border:1px solid rgba(255,255,255,.6);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.6), 0 18px 44px -16px rgba(10,16,36,.4);
}
.proof__av{
  width:40px; height:40px; flex:none; border-radius:50%;
  background:linear-gradient(140deg,var(--saffron),#C9501A);
  color:#fff; font:700 16px/40px var(--body); text-align:center;
}
.proof__body{ flex:1; min-width:0; font-size:13.5px; line-height:1.35; }
.proof__body b{ font-weight:700; }
.proof__body small{ display:block; font-size:11.5px; color:var(--ink-55); margin-top:3px; }
.proof__x{
  width:24px; height:24px; flex:none; border:none; background:none; cursor:pointer;
  color:var(--ink-55); padding:0; display:grid; place-items:center; border-radius:50%; transition:.2s;
}
.proof__x svg{ width:13px; height:13px; }
.proof__x:hover{ background:var(--ink-12); color:var(--ink); }

.proof-enter-active, .proof-leave-active{ transition:opacity .4s cubic-bezier(.2,.8,.2,1), transform .4s cubic-bezier(.2,.8,.2,1); }
.proof-enter-from, .proof-leave-to{ opacity:0; transform:translateY(16px); }

@media (max-width:600px){
  .proof{ left:10px; right:10px; bottom:calc(10px + env(safe-area-inset-bottom)); max-width:none; gap:9px; padding:10px 11px; border-radius:13px; }
  .proof__av{ width:34px; height:34px; font-size:13px; line-height:34px; }
  .proof__body{ font-size:11.5px; }
  .proof__body small{ font-size:9.5px; }
  .proof__x{ width:28px; height:28px; }
}
@media (prefers-reduced-motion: reduce){
  .proof-enter-active, .proof-leave-active{ transition:none; }
}
</style>
