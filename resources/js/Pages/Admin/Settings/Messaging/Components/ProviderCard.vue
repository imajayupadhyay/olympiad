<template>
  <div class="relative rounded-2xl border-2 p-4 transition-colors cursor-pointer"
       :class="[active ? 'border-success bg-success/[.04]' : selected ? 'border-primary/40 bg-white' : 'border-gray-100 bg-white hover:border-gray-200']"
       role="radio" :aria-checked="active" tabindex="0"
       @click="$emit('select')" @keydown.enter.prevent="$emit('select')" @keydown.space.prevent="$emit('select')">
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <span class="w-11 h-11 shrink-0 rounded-xl grid place-items-center font-heading font-bold text-sm"
              :class="active ? 'bg-success text-white' : 'bg-primary/5 text-primary'">{{ initials }}</span>
        <div class="min-w-0">
          <p class="font-heading font-bold text-text-main">{{ provider.label }}</p>
          <p class="text-[11px] text-text-muted truncate">
            {{ provider.template_source === 'live' ? 'Templates read live from ' + provider.label : 'Templates managed on this page' }}
          </p>
        </div>
      </div>
      <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-lg" :class="state.cls">{{ state.label }}</span>
    </div>

    <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
      <div class="rounded-xl bg-gray-50 py-2">
        <dt class="text-[10px] uppercase tracking-wider text-text-muted">Sender</dt>
        <dd class="font-number font-semibold text-sm text-text-main truncate px-1">{{ provider.sender || '—' }}</dd>
      </div>
      <div class="rounded-xl bg-gray-50 py-2">
        <dt class="text-[10px] uppercase tracking-wider text-text-muted">Sent · 30d</dt>
        <dd class="font-number font-semibold text-sm text-text-main">{{ provider.usage.sent_30d.toLocaleString() }}</dd>
      </div>
      <div class="rounded-xl bg-gray-50 py-2">
        <dt class="text-[10px] uppercase tracking-wider text-text-muted">Broadcasts</dt>
        <dd class="font-number font-semibold text-sm text-text-main">{{ provider.usage.broadcasts.toLocaleString() }}</dd>
      </div>
    </dl>

    <p v-if="!provider.can_send" class="mt-3 text-[11px] leading-relaxed text-gold-dark">{{ provider.unavailable_reason }}</p>

    <div class="mt-4 flex items-center justify-between gap-2">
      <p v-if="active" class="flex items-center gap-1.5 text-xs font-semibold text-success">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        Sending WhatsApp broadcasts
      </p>
      <p v-else class="text-xs text-text-muted">Not in use for new broadcasts</p>

      <button v-if="!active && canSwitch" type="button" class="btn-primary" :disabled="switching" @click.stop="$emit('activate')">
        {{ switching ? 'Switching…' : `Use ${provider.label}` }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  provider: { type: Object, required: true },
  active: { type: Boolean, default: false },
  selected: { type: Boolean, default: false },
  canSwitch: { type: Boolean, default: false },
  switching: { type: Boolean, default: false },
});
defineEmits(['select', 'activate']);

const initials = computed(() => props.provider.label.replace(/[^A-Za-z0-9]/g, '').slice(0, 2).toUpperCase());

const state = computed(() => {
  const p = props.provider;
  if (p.can_send) return { label: 'Ready', cls: 'bg-success/10 text-success' };
  if (p.configured) return { label: 'Unavailable', cls: 'bg-gold/15 text-gold-dark' };
  return { label: 'Not configured', cls: 'bg-danger/10 text-danger' };
});
</script>

<style scoped>
.btn-primary { @apply px-3.5 py-2 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-light transition-colors disabled:opacity-50; }
</style>
