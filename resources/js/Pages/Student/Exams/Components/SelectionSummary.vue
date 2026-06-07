<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },     // [{id,name,fee_amount,is_free}]
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['checkout', 'clear']);

const total = computed(() => props.items.reduce((s, e) => s + (e.fee_amount || 0), 0));
const hasPaid = computed(() => props.items.some((e) => !e.is_free));
</script>

<template>
    <Transition name="bar">
        <div v-if="items.length" class="bar">
            <div class="left">
                <span class="count">{{ items.length }}</span>
                <div class="txt">
                    <strong>{{ items.length }} exam{{ items.length > 1 ? 's' : '' }} selected</strong>
                    <small>{{ items.map(e => e.name).join(' · ') }}</small>
                </div>
            </div>

            <div class="right">
                <div class="total">
                    <small>Total</small>
                    <strong :class="{ free: total === 0 }">{{ total === 0 ? 'FREE' : '₹' + total.toLocaleString('en-IN') }}</strong>
                </div>
                <button class="clear" type="button" @click="emit('clear')">Clear</button>
                <button class="cta" type="button" :disabled="processing" @click="emit('checkout')">
                    {{ processing ? 'Please wait…' : (hasPaid ? 'Proceed to Pay' : 'Enroll now') }}
                </button>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.bar {
    position: sticky; bottom: 1rem; z-index: 20; margin-top: 1.4rem;
    display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
    background: linear-gradient(135deg, #1B2748, #0A1024);
    color: #FBF6EC; border-radius: 18px; padding: .9rem 1.2rem;
    box-shadow: 0 26px 50px -18px rgba(10,16,36,.6);
}
.left { display: flex; align-items: center; gap: .8rem; min-width: 0; }
.count {
    width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0; display: grid; place-items: center;
    font-family: "Space Grotesk", monospace; font-weight: 700; color: #0A1024;
    background: linear-gradient(135deg, #F2C84B, #D6991F);
}
.txt { display: flex; flex-direction: column; min-width: 0; }
.txt strong { font-size: .92rem; }
.txt small { font-size: .76rem; color: rgba(251,246,236,.55); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 42ch; }

.right { display: flex; align-items: center; gap: 1rem; }
.total { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.1; }
.total small { font-size: .68rem; color: rgba(251,246,236,.5); }
.total strong { font-family: "Space Grotesk", monospace; font-size: 1.2rem; color: #F2C84B; }
.total strong.free { color: #34d399; }

.clear { border: 0; background: transparent; color: rgba(251,246,236,.6); cursor: pointer; font-size: .85rem; }
.clear:hover { color: #fff; }

.cta {
    border: 0; cursor: pointer; font-weight: 700; font-size: .92rem; color: #fff;
    background: linear-gradient(135deg, #F2854E, #EE6A2C); padding: .7rem 1.4rem; border-radius: 12px;
    box-shadow: 0 12px 26px -12px rgba(238,106,44,.8); transition: transform .15s;
}
.cta:hover { transform: translateY(-2px); }
.cta:disabled { opacity: .6; cursor: progress; }

.bar-enter-active, .bar-leave-active { transition: all .3s cubic-bezier(.2,.7,.2,1); }
.bar-enter-from, .bar-leave-to { opacity: 0; transform: translateY(20px); }
</style>
