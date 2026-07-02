<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { usePoll } from '@/composables/usePoll';

const props = defineProps({
    active:    { type: Boolean, default: false },
    link:      { type: String, default: '' },
    code:      { type: String, default: '' },
    program:   { type: Object, default: () => ({}) },
    stats:     { type: Object, default: () => ({}) },
    rewards:   { type: Array, default: () => [] },
    referrals: { type: Array, default: () => [] },
});

// Keep the live counters fresh (≤5s) without a manual page refresh.
usePoll(['stats', 'referrals', 'rewards']);

const copied = ref(false);
const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.link);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch { /* clipboard unavailable */ }
};

const shareText = computed(() =>
    `Join me on National Olympiad Hunt! Sign up with my link${props.program.referee_discount && props.program.referee_discount !== '—' ? ` and get ${props.program.referee_discount} on your first olympiad` : ''}: ${props.link}`
);
const waLink = computed(() => `https://wa.me/?text=${encodeURIComponent(shareText.value)}`);
const mailLink = computed(() => `mailto:?subject=${encodeURIComponent('Join National Olympiad Hunt')}&body=${encodeURIComponent(shareText.value)}`);

const nativeShare = async () => {
    if (navigator.share) {
        try { await navigator.share({ title: 'National Olympiad Hunt', text: shareText.value, url: props.link }); } catch { /* cancelled */ }
    } else {
        copyLink();
    }
};

// At a threshold boundary, show the meter completed rather than reset-to-empty.
const justEarned = computed(() => (props.stats.rewarded ?? 0) > 0 && props.stats.toward === 0);
const displayPercent = computed(() => justEarned.value ? 100 : (props.stats.percent ?? 0));
const displayToward = computed(() => justEarned.value ? props.stats.threshold : props.stats.toward);

// Mode-aware progress wording (admin setting).
const progressLabel = computed(() => ({
    link_click: 'Link opens',
    registration: 'Joined',
    first_paid_enrollment: 'Enrolled',
}[props.stats.mode] || 'Joined'));
const progressNoun = computed(() => ({
    link_click: 'link open',
    registration: 'qualified referral',
    first_paid_enrollment: 'paid enrollment',
}[props.stats.mode] || 'referral'));

const rewardBadge = (s) => ({
    available: { label: 'Available', cls: 'rb-ok' },
    used:      { label: 'Used',      cls: 'rb-used' },
    expired:   { label: 'Expired',   cls: 'rb-exp' },
}[s] || { label: s, cls: 'rb-used' });

const statusBadge = (s) => ({
    pending:   'Pending',
    qualified: 'Qualified',
    rewarded:  'Rewarded',
}[s] || s);
</script>

<template>
    <Head title="Refer & Earn" />

    <StudentLayout title="Refer & Earn">
        <div class="wrap">
            <!-- Program off -->
            <div v-if="!active" class="empty">
                <div class="empty-icon">🎁</div>
                <h2>Referrals are currently paused</h2>
                <p>The referral program isn’t active right now. Check back soon to start earning rewards for inviting friends.</p>
            </div>

            <template v-else>
                <!-- Hero / share card -->
                <section class="hero">
                    <div class="hero-bg"></div>
                    <div class="hero-in">
                        <span class="kick">Invite friends · Earn rewards</span>
                        <h2 class="head">Share your link, <em>earn discounts</em></h2>
                        <p class="lead">
                            Friends who join with your link get
                            <strong>{{ program.referee_discount && program.referee_discount !== '—' ? program.referee_discount : 'a warm welcome' }}</strong>.
                            Refer <strong>{{ program.threshold }}</strong> and you unlock
                            <strong>{{ program.referrer_reward && program.referrer_reward !== '—' ? program.referrer_reward : 'a reward' }}</strong>,
                            auto-applied at your next checkout.
                        </p>

                        <div class="link-row">
                            <div class="link-box">
                                <span class="link-text">{{ link }}</span>
                            </div>
                            <button class="copy" :class="{ done: copied }" @click="copyLink">
                                {{ copied ? 'Copied ✓' : 'Copy link' }}
                            </button>
                        </div>

                        <div class="share">
                            <a class="sh wa" :href="waLink" target="_blank" rel="noopener">WhatsApp</a>
                            <a class="sh mail" :href="mailLink">Email</a>
                            <button class="sh more" @click="nativeShare">More…</button>
                            <span class="code-chip">Your code: <strong>{{ code }}</strong></span>
                        </div>
                    </div>
                </section>

                <!-- Stats -->
                <section class="stats">
                    <div class="stat"><strong>{{ stats.referred }}</strong><span>Friends referred</span></div>
                    <div class="stat"><strong>{{ stats.progress }}</strong><span>{{ progressLabel }}</span></div>
                    <div class="stat"><strong>{{ stats.rewarded }}</strong><span>Rewards earned</span></div>
                </section>

                <!-- Progress to next reward -->
                <section class="block">
                    <div class="block-head">
                        <h3>Progress to your next reward</h3>
                        <span class="muted">{{ displayToward }} / {{ program.threshold }}</span>
                    </div>
                    <div class="bar"><div class="bar-fill" :style="{ width: displayPercent + '%' }"></div></div>
                    <p class="hint">
                        <template v-if="justEarned">🎉 Reward earned! </template>{{ stats.remaining }} more {{ progressNoun }}{{ stats.remaining === 1 ? '' : 's' }} to unlock
                        {{ program.referrer_reward && program.referrer_reward !== '—' ? program.referrer_reward : 'your reward' }}.
                    </p>
                </section>

                <!-- Rewards -->
                <section class="block">
                    <div class="block-head"><h3>Your rewards</h3></div>
                    <div v-if="rewards.length" class="rewards">
                        <div v-for="r in rewards" :key="r.code" class="reward">
                            <div class="reward-l">
                                <span class="reward-code">{{ r.code }}</span>
                                <span class="reward-label">{{ r.label }}</span>
                                <span v-if="r.expires_at" class="reward-exp">Expires {{ r.expires_at }}</span>
                            </div>
                            <span class="rb" :class="rewardBadge(r.status).cls">{{ rewardBadge(r.status).label }}</span>
                        </div>
                    </div>
                    <p v-else class="empty-line">No rewards yet — invite friends to earn your first one.</p>
                </section>

                <!-- Referral history -->
                <section class="block">
                    <div class="block-head"><h3>Your referrals</h3></div>
                    <div v-if="referrals.length" class="rows">
                        <div v-for="(r, i) in referrals" :key="i" class="row">
                            <span class="row-name">{{ r.name }}</span>
                            <span class="row-meta">
                                <span class="pill" :class="'pill-' + r.status">{{ statusBadge(r.status) }}</span>
                                <span class="row-date">{{ r.created_at }}</span>
                            </span>
                        </div>
                    </div>
                    <p v-else class="empty-line">No referrals yet. Share your link to get started!</p>
                </section>
            </template>
        </div>
    </StudentLayout>
</template>

<style scoped>
.wrap {
    --ink: #0A1024; --ink-2: #131C3D; --paper: #FBF6EC; --paper-2: #F3E9D6;
    --line: #E7D9BE; --saffron: #EE6A2C; --saffron-dk: #C9501A; --gold: #D6991F;
    --gold-lt: #F2C84B; --emerald: #168A66; --muted: #5B6373;
    display: grid; gap: 1.3rem; max-width: 900px;
}

/* Empty / paused */
.empty { background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 3rem 2rem; text-align: center; }
.empty-icon { font-size: 2.6rem; }
.empty h2 { font-family: "Fraunces", serif; font-weight: 600; color: var(--ink); margin: .8rem 0 .4rem; }
.empty p { color: var(--muted); max-width: 44ch; margin: 0 auto; font-size: .92rem; }

/* Hero */
.hero { position: relative; border-radius: 24px; overflow: hidden; color: var(--paper);
    background: linear-gradient(155deg, var(--ink) 0%, var(--ink-2) 60%, #1B2748 100%); }
.hero-bg { position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(60% 60% at 90% 0%, rgba(242,200,75,.22), transparent 60%),
                radial-gradient(50% 50% at 0% 100%, rgba(238,106,44,.18), transparent 55%); }
.hero-in { position: relative; padding: 2rem 1.9rem; }
.kick { font-size: .72rem; letter-spacing: .16em; text-transform: uppercase; color: var(--gold-lt); font-weight: 700; }
.head { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.85rem; line-height: 1.1; margin: .5rem 0 .6rem; }
.head em { font-style: italic; color: var(--gold-lt); }
.lead { font-size: .92rem; line-height: 1.6; color: rgba(251,246,236,.78); max-width: 56ch; }
.lead strong { color: #fff; }

.link-row { display: flex; gap: .6rem; margin-top: 1.3rem; flex-wrap: wrap; }
.link-box { flex: 1; min-width: 220px; display: flex; align-items: center; background: rgba(251,246,236,.08);
    border: 1px solid rgba(251,246,236,.16); border-radius: 12px; padding: .7rem .9rem; overflow: hidden; }
.link-text { font-family: "Space Grotesk", monospace; font-size: .86rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.copy { border: 0; cursor: pointer; font-weight: 700; font-size: .86rem; padding: 0 1.3rem; border-radius: 12px;
    background: linear-gradient(135deg, var(--saffron), var(--saffron-dk)); color: #fff; transition: transform .15s, background .2s; }
.copy:hover { transform: translateY(-1px); }
.copy.done { background: linear-gradient(135deg, #1aa177, var(--emerald)); }

.share { display: flex; align-items: center; gap: .55rem; margin-top: 1rem; flex-wrap: wrap; }
.sh { border: 0; cursor: pointer; text-decoration: none; font-weight: 600; font-size: .82rem; padding: .5rem .9rem; border-radius: 10px;
    background: rgba(251,246,236,.1); color: #fff; border: 1px solid rgba(251,246,236,.16); transition: background .2s; }
.sh:hover { background: rgba(251,246,236,.18); }
.sh.wa { background: rgba(37,211,102,.18); border-color: rgba(37,211,102,.4); }
.code-chip { margin-left: auto; font-size: .78rem; color: rgba(251,246,236,.6); }
.code-chip strong { font-family: "Space Grotesk", monospace; color: var(--gold-lt); letter-spacing: .05em; }

/* Stats */
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
.stat { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 1.1rem 1rem; text-align: center; }
.stat strong { display: block; font-family: "Space Grotesk", monospace; font-size: 1.8rem; color: var(--ink); }
.stat span { font-size: .8rem; color: var(--muted); }

/* Blocks */
.block { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 1.3rem 1.4rem; }
.block-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .9rem; }
.block-head h3 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.05rem; color: var(--ink); margin: 0; }
.muted { font-size: .82rem; color: var(--muted); font-family: "Space Grotesk", monospace; }

.bar { height: 10px; border-radius: 999px; background: var(--paper-2); overflow: hidden; }
.bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--gold-lt), var(--gold)); transition: width .5s ease; }
.hint { font-size: .82rem; color: var(--muted); margin: .7rem 0 0; }

.rewards { display: grid; gap: .6rem; }
.reward { display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    border: 1.5px dashed var(--line); border-radius: 12px; padding: .7rem .9rem; }
.reward-l { display: flex; flex-direction: column; gap: .15rem; }
.reward-code { font-family: "Space Grotesk", monospace; font-weight: 700; color: var(--ink); letter-spacing: .05em; }
.reward-label { font-size: .82rem; color: var(--emerald); font-weight: 600; }
.reward-exp { font-size: .72rem; color: var(--muted); }
.rb { font-size: .72rem; font-weight: 700; padding: .25rem .6rem; border-radius: 999px; }
.rb-ok { background: rgba(22,138,102,.12); color: var(--emerald); }
.rb-used { background: rgba(10,16,36,.08); color: var(--muted); }
.rb-exp { background: rgba(220,38,38,.1); color: #b02a1f; }

.rows { display: grid; gap: .1rem; }
.row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .65rem 0; border-bottom: 1px dashed var(--line); }
.row:last-child { border-bottom: 0; }
.row-name { font-size: .9rem; color: var(--ink); }
.row-meta { display: flex; align-items: center; gap: .8rem; }
.pill { font-size: .72rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px; }
.pill-pending { background: rgba(10,16,36,.08); color: var(--muted); }
.pill-qualified { background: rgba(22,138,102,.12); color: var(--emerald); }
.pill-rewarded { background: rgba(214,153,31,.14); color: #97751e; }
.row-date { font-size: .76rem; color: var(--muted); font-family: "Space Grotesk", monospace; }

.empty-line { font-size: .88rem; color: var(--muted); margin: 0; }

@media (max-width: 560px) {
    .stats { grid-template-columns: 1fr; }
    .head { font-size: 1.55rem; }
    .code-chip { margin-left: 0; width: 100%; }
}
</style>
