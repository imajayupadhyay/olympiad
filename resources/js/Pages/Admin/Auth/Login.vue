<template>
  <div class="min-h-screen flex items-center justify-center relative overflow-hidden" style="background:linear-gradient(135deg,#0f1f54 0%,#131C3D 50%,#14244f 100%);">

    <!-- Background dot grid -->
    <div class="absolute inset-0" style="background-image:radial-gradient(circle,rgba(255,255,255,.07) 1px,transparent 1px);background-size:30px 30px;"></div>
    <!-- Glow orbs -->
    <div class="absolute top-0 right-0 w-[500px] h-[500px] rounded-full pointer-events-none" style="background:radial-gradient(circle,rgba(238,106,44,.18),transparent 70%);"></div>
    <div class="absolute bottom-0 left-0 w-80 h-80 rounded-full pointer-events-none" style="background:radial-gradient(circle,rgba(214,153,31,.14),transparent 70%);"></div>

    <div class="relative z-10 w-full max-w-md px-4">

      <!-- Card -->
      <div class="bg-white/[0.06] backdrop-blur-xl border border-white/10 rounded-3xl p-8 shadow-2xl">

        <!-- Logo & heading -->
        <div class="text-center mb-8">
          <div class="flex justify-center mb-5">
            <AppLogo :size="54" variant="light" />
          </div>
          <h1 class="font-heading font-bold text-white text-2xl mb-1">Admin Access</h1>
          <p class="text-blue-300 text-sm" style="font-family:'Inter',sans-serif;">
            Secure Panel
          </p>
          <!-- Security badge -->
          <div class="inline-flex items-center gap-2 mt-3 bg-green-500/10 border border-green-500/20 text-green-300 text-xs font-medium px-3 py-1.5 rounded-full">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            Encrypted secure connection
          </div>
        </div>

        <!-- Error alert -->
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 -translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
        >
          <div v-if="form.errors.email" class="mb-5 flex items-start gap-3 bg-red-500/10 border border-red-500/25 text-red-300 text-sm px-4 py-3 rounded-xl" style="font-family:'Inter',sans-serif;">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ form.errors.email }}
          </div>
        </Transition>

        <!-- Login form -->
        <form @submit.prevent="submit" class="space-y-5">

          <!-- Email -->
          <div>
            <label class="block text-blue-200 text-xs font-semibold uppercase tracking-widest mb-2" style="font-family:'Inter',sans-serif;">
              Administrator Email
            </label>
            <div class="relative">
              <div class="absolute left-4 top-1/2 -translate-y-1/2 text-blue-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
              </div>
              <input
                v-model="form.email"
                type="email"
                autocomplete="email"
                placeholder="admin@example.com"
                required
                class="w-full bg-white/[0.07] border border-white/15 text-white placeholder-blue-400/60 rounded-xl pl-11 pr-4 py-3.5 text-sm focus:outline-none focus:border-accent focus:bg-white/10 transition-all duration-200"
                :class="{ 'border-red-500/60': form.errors.email }"
                style="font-family:'Inter',sans-serif;"
              />
            </div>
          </div>

          <!-- Password -->
          <div>
            <label class="block text-blue-200 text-xs font-semibold uppercase tracking-widest mb-2" style="font-family:'Inter',sans-serif;">
              Password
            </label>
            <div class="relative">
              <div class="absolute left-4 top-1/2 -translate-y-1/2 text-blue-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
              </div>
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                placeholder="••••••••••"
                required
                class="w-full bg-white/[0.07] border border-white/15 text-white placeholder-blue-400/60 rounded-xl pl-11 pr-12 py-3.5 text-sm focus:outline-none focus:border-accent focus:bg-white/10 transition-all duration-200"
                :class="{ 'border-red-500/60': form.errors.email }"
                style="font-family:'Inter',sans-serif;"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-4 top-1/2 -translate-y-1/2 text-blue-400 hover:text-white transition-colors"
                tabindex="-1"
              >
                <svg v-if="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Remember me -->
          <div class="flex items-center gap-3">
            <input
              v-model="form.remember"
              id="remember"
              type="checkbox"
              class="w-4 h-4 rounded border-white/20 bg-white/10 text-accent focus:ring-accent focus:ring-offset-0 cursor-pointer"
            />
            <label for="remember" class="text-blue-200 text-sm cursor-pointer select-none" style="font-family:'Inter',sans-serif;">
              Keep me signed in for 30 days
            </label>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="form.processing"
            class="w-full font-heading font-bold text-white py-4 rounded-xl text-base transition-all duration-200 hover:scale-[1.02] hover:shadow-xl disabled:opacity-60 disabled:cursor-not-allowed disabled:scale-100 flex items-center justify-center gap-2.5"
            style="background:linear-gradient(135deg,#EE6A2C,#C9501A);box-shadow:0 8px 24px rgba(238,106,44,.4);"
          >
            <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            {{ form.processing ? 'Authenticating…' : 'Access Admin Panel' }}
          </button>
        </form>

        <!-- Footer note -->
        <p class="text-center text-blue-400/60 text-xs mt-6" style="font-family:'Inter',sans-serif;">
          Unauthorised access is strictly prohibited and monitored.
        </p>
      </div>

      <!-- Back to site link -->
      <div class="text-center mt-5">
        <Link href="/" class="text-blue-300/60 hover:text-blue-200 text-xs transition-colors" style="font-family:'Inter',sans-serif;">
          ← Back to National Olympiad Hunt
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLogo from '@/Components/Shared/AppLogo.vue';

const showPassword = ref(false);

const form = useForm({
  email:    '',
  password: '',
  remember: false,
});

const submit = () => {
  form.post(route('admin.login.submit'), {
    onFinish: () => form.reset('password'),
  });
};
</script>

<style scoped>
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus {
  -webkit-text-fill-color: #fff;
  -webkit-box-shadow: 0 0 0 1000px rgba(255,255,255,0.07) inset;
  transition: background-color 5000s ease-in-out 0s;
}
</style>
