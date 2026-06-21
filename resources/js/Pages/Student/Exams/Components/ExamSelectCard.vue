<script setup>
import { computed } from 'vue';

const props = defineProps({
    exam: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle']);

const selectable = computed(() => !props.exam.is_enrolled && props.exam.availability !== 'closed');

const availMeta = computed(() => ({
    upcoming: { label: 'Upcoming', cls: 'av-upcoming' },
    live:     { label: 'Live now', cls: 'av-live' },
    closed:   { label: 'Closed',   cls: 'av-closed' },
}[props.exam.availability] ?? { label: '', cls: '' }));

const toggle = () => { if (selectable.value) emit('toggle', props.exam.id); };
</script>

<template>
    <div class="card" :class="{ on: selected, enrolled: exam.is_enrolled }" @click="toggle">
        <!-- top row -->
        <div class="top">
            <span class="avail" :class="availMeta.cls">
                <span class="dot"></span>{{ availMeta.label }}
            </span>
        </div>

        <h3 class="name">{{ exam.subject?.name ?? 'General' }}</h3>

        <p v-if="exam.description" class="desc">{{ exam.description }}</p>

        <!-- footer -->
        <div class="foot">
            <span class="fee" :class="{ free: exam.is_free }">
                {{ exam.is_free ? 'FREE' : '₹' + exam.fee_amount.toLocaleString('en-IN') }}
            </span>

            <span v-if="exam.is_enrolled" class="enrolled-tag">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6 9 17l-5-5"/></svg> Enrolled
            </span>
            <span v-else-if="exam.availability === 'closed'" class="closed-tag">Window closed</span>
            <span v-else class="check" :class="{ on: selected }">
                <svg v-if="selected" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
            </span>
        </div>
    </div>
</template>

<style scoped>
.card {
    position: relative; background: #fff; border: 1.5px solid #E7D9BE; border-radius: 18px;
    padding: 1.2rem; cursor: pointer; transition: transform .18s, box-shadow .2s, border-color .2s;
    box-shadow: 0 2px 8px rgba(10,16,36,.04); display: flex; flex-direction: column; gap: .7rem;
}
.card:hover { transform: translateY(-3px); box-shadow: 0 20px 40px -22px rgba(10,16,36,.3); }
.card.on { border-color: #EE6A2C; box-shadow: 0 0 0 3px rgba(238,106,44,.14), 0 20px 40px -22px rgba(238,106,44,.4); }
.card.enrolled { cursor: default; }
.card.enrolled:hover { transform: none; }

.top { display: flex; align-items: center; justify-content: flex-end; gap: .5rem; }

.avail { display: inline-flex; align-items: center; gap: .35rem; font-size: .72rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px; }
.avail .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.av-upcoming { background: rgba(44,73,166,.12); color: #2C49A6; }
.av-live { background: rgba(22,138,102,.14); color: #168A66; }
.av-closed { background: rgba(91,99,115,.14); color: #5B6373; }

.name { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.12rem; color: #0A1024; margin: 0; line-height: 1.25; }

.desc { font-size: .86rem; color: #5B6373; line-height: 1.45; margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

.foot { display: flex; align-items: center; justify-content: space-between; margin-top: .2rem; padding-top: .7rem; border-top: 1px solid #F0E6D2; }
.fee { font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 1.05rem; color: #0A1024; }
.fee.free { color: #168A66; }

.enrolled-tag { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; font-weight: 700; color: #168A66; }
.enrolled-tag svg { width: 15px; height: 15px; }
.closed-tag { font-size: .78rem; color: #9aa0ad; }
.check { width: 24px; height: 24px; border-radius: 8px; border: 2px solid #D9C9A6; display: grid; place-items: center; color: #fff; transition: all .15s; }
.check.on { background: #EE6A2C; border-color: #EE6A2C; }
.check svg { width: 14px; height: 14px; }
</style>
