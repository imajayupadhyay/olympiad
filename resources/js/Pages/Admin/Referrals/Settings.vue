<template>
  <AdminLayout title="Referrals" subtitle="Configure the two-sided referral program">

    <!-- Tab nav -->
    <div class="flex items-center gap-1 mb-6 bg-gray-100 p-1 rounded-xl w-fit">
      <Link :href="route('admin.referrals')" class="px-4 py-2 rounded-lg text-sm font-semibold text-text-muted hover:text-text-main transition-colors">Overview</Link>
      <Link :href="route('admin.referrals.settings')" class="px-4 py-2 rounded-lg text-sm font-semibold bg-white text-primary shadow-sm">Settings</Link>
    </div>

    <form @submit.prevent="submit" class="max-w-2xl space-y-5">

      <!-- Master toggle -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <label class="flex items-center justify-between gap-4 cursor-pointer">
          <div>
            <p class="font-heading font-bold text-text-main">Referral program</p>
            <p class="text-text-muted text-sm">When off, referral links stop attributing and no discounts are minted.</p>
          </div>
          <input v-model="form.is_active" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-accent focus:ring-accent" />
        </label>
      </div>

      <!-- Live preview (updates as you type) -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-text-muted mb-3">Live preview</p>
        <div class="grid sm:grid-cols-2 gap-3">
          <div class="rounded-xl bg-bg p-4 border border-gray-100">
            <p class="text-text-muted text-xs mb-1">New student (referee) gets</p>
            <p class="font-number text-lg font-bold text-success">{{ refereePreview }}</p>
          </div>
          <div class="rounded-xl bg-bg p-4 border border-gray-100">
            <p class="text-text-muted text-xs mb-1">Referrer earns</p>
            <p class="font-number text-lg font-bold text-gold">{{ referrerPreview }}</p>
          </div>
        </div>
        <p class="text-text-muted text-xs mt-3">
          Unlocks after <strong class="text-text-main">{{ form.unlock_threshold || 1 }}</strong>
          {{ qualifyNoun }}{{ Number(form.unlock_threshold) === 1 ? '' : 's' }}.
        </p>
      </div>

      <!-- Referee welcome discount -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
        <div>
          <p class="font-heading font-bold text-text-main">New student (referee) welcome discount</p>
          <p class="text-text-muted text-sm">Auto-applied at checkout for a student who signs up through a referral link.</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-text-muted mb-1.5">Type</label>
            <select v-model="form.referee_discount_type" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
              <option value="percentage">Percentage (%)</option>
              <option value="fixed">Fixed (₹)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-text-muted mb-1.5">{{ form.referee_discount_type === 'percentage' ? 'Discount %' : 'Discount ₹' }}</label>
            <input v-model="form.referee_discount_value" type="number" step="0.01" min="0" :max="form.referee_discount_type === 'percentage' ? 100 : null"
              class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
            <p v-if="form.errors.referee_discount_value" class="text-danger text-xs mt-1">{{ form.errors.referee_discount_value }}</p>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div v-if="form.referee_discount_type === 'percentage'">
            <label class="block text-xs font-semibold text-text-muted mb-1.5">Max discount cap ₹ <span class="font-normal">(optional)</span></label>
            <input v-model="form.referee_max_discount" type="number" step="0.01" min="1" placeholder="e.g. 200"
              class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
          </div>
          <div :class="form.referee_discount_type === 'percentage' ? '' : 'col-span-2'">
            <label class="block text-xs font-semibold text-text-muted mb-1.5">Min order ₹ <span class="font-normal">(optional)</span></label>
            <input v-model="form.referee_min_order_amount" type="number" step="0.01" min="0" placeholder="0"
              class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
          </div>
        </div>
      </div>

      <!-- Referrer reward -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
        <div>
          <p class="font-heading font-bold text-text-main">Referrer reward</p>
          <p class="text-text-muted text-sm">Earned by the sharer once they hit the unlock threshold; auto-applied at their next checkout.</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-text-muted mb-1.5">Type</label>
            <select v-model="form.referrer_reward_type" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
              <option value="percentage">Percentage (%)</option>
              <option value="fixed">Fixed (₹)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-text-muted mb-1.5">{{ form.referrer_reward_type === 'percentage' ? 'Reward %' : 'Reward ₹' }}</label>
            <input v-model="form.referrer_reward_value" type="number" step="0.01" min="0" :max="form.referrer_reward_type === 'percentage' ? 100 : null"
              class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
            <p v-if="form.errors.referrer_reward_value" class="text-danger text-xs mt-1">{{ form.errors.referrer_reward_value }}</p>
          </div>
        </div>
        <div v-if="form.referrer_reward_type === 'percentage'" class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-text-muted mb-1.5">Max reward cap ₹ <span class="font-normal">(optional)</span></label>
            <input v-model="form.referrer_max_discount" type="number" step="0.01" min="1" placeholder="e.g. 300"
              class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
          </div>
        </div>
      </div>

      <!-- Program rules -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
        <div>
          <p class="font-heading font-bold text-text-main">Program rules</p>
          <p class="text-text-muted text-sm">How many referrals unlock a reward, and when a referral counts.</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-text-muted mb-1.5">Unlock threshold</label>
            <input v-model="form.unlock_threshold" type="number" min="1" placeholder="1"
              class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
            <p class="text-text-muted text-[11px] mt-1">Successful referrals needed before the sharer unlocks a reward.</p>
            <p v-if="form.errors.unlock_threshold" class="text-danger text-xs mt-1">{{ form.errors.unlock_threshold }}</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-text-muted mb-1.5">Reward validity (days)</label>
            <input v-model="form.reward_validity_days" type="number" min="1" placeholder="never expires"
              class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-number focus:outline-none focus:border-primary bg-gray-50" />
            <p class="text-text-muted text-[11px] mt-1">Blank = minted discounts never expire.</p>
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-text-muted mb-1.5">A referral counts as successful when…</label>
          <select v-model="form.qualify_on" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main">
            <option value="link_share">When the user copies or shares their link (no open needed)</option>
            <option value="link_click">As soon as someone opens the shared link</option>
            <option value="registration">The referred student finishes registration via the link</option>
            <option value="first_paid_enrollment">The referred student completes their first paid enrollment</option>
          </select>
          <p v-if="form.qualify_on === 'link_share'" class="text-text-muted text-[11px] mt-1.5">
            Loosest option — the referrer earns every time they copy or share their own link (no de-dupe, no open or
            sign-up required). Raise the unlock threshold to temper how often a reward is granted.
          </p>
          <p v-else-if="form.qualify_on === 'link_click'" class="text-text-muted text-[11px] mt-1.5">
            The referrer earns from link opens alone (de-duped per device/IP), no sign-up required.
          </p>
        </div>
        <p class="text-text-muted text-[11px] bg-gray-50 rounded-lg p-3">Note: the sharer earns one reward at the first time they cross the unlock threshold.</p>
      </div>

      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing" class="bg-primary text-white px-6 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60">
          {{ form.processing ? 'Saving…' : 'Save Settings' }}
        </button>
        <span v-if="saved" class="text-success text-sm font-semibold">Saved ✓</span>
      </div>

    </form>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  settings: Object,
});

const s = props.settings;
const form = useForm({
  is_active:                !!s.is_active,
  referee_discount_type:    s.referee_discount_type || 'percentage',
  referee_discount_value:   s.referee_discount_value ?? 0,
  referee_max_discount:     s.referee_max_discount ?? '',
  referee_min_order_amount: s.referee_min_order_amount ?? 0,
  referrer_reward_type:     s.referrer_reward_type || 'percentage',
  referrer_reward_value:    s.referrer_reward_value ?? 0,
  referrer_max_discount:    s.referrer_max_discount ?? '',
  unlock_threshold:         s.unlock_threshold ?? 1,
  qualify_on:               s.qualify_on || 'registration',
  reward_validity_days:     s.reward_validity_days ?? '',
});

// ── Live, fully-dynamic discount labels (mirror the server-side formatter) ──
const fmtMoney = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
const labelFor = (type, value, cap) => {
    const v = Number(value);
    if (!v || v <= 0) return '—';
    if (type === 'percentage') {
        const base = `${v}% off`;
        return Number(cap) > 0 ? `${base} (up to ${fmtMoney(cap)})` : base;
    }
    return `${fmtMoney(v)} off`;
};
const refereePreview = computed(() => labelFor(form.referee_discount_type, form.referee_discount_value, form.referee_max_discount));
const referrerPreview = computed(() => labelFor(form.referrer_reward_type, form.referrer_reward_value, form.referrer_max_discount));
const qualifyNoun = computed(() => ({
    registration: 'sign-up via the link',
    first_paid_enrollment: 'paid enrollment',
    link_click: 'link open',
    link_share: 'link share',
}[form.qualify_on] || 'referral'));

const saved = ref(false);
const submit = () => form.put(route('admin.referrals.settings.update'), {
  preserveScroll: true,
  onSuccess: () => { saved.value = true; setTimeout(() => (saved.value = false), 2500); },
});
</script>
