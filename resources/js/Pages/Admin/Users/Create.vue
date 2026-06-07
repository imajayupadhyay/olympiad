<template>
  <AdminLayout title="Add Student" subtitle="Manually create a student account">

    <div class="max-w-3xl">

      <!-- Back -->
      <Link :href="route('admin.users.index')"
            class="inline-flex items-center gap-2 text-text-muted hover:text-text-main text-sm font-semibold mb-6 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to Students
      </Link>

      <form @submit.prevent="submit" class="space-y-5">

        <!-- Basic Info -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h2 class="font-heading font-bold text-text-main text-sm mb-5 flex items-center gap-2">
            <span class="w-6 h-6 rounded-lg bg-primary/10 flex items-center justify-center text-primary text-xs font-bold">1</span>
            Basic Information
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Full Name <span class="text-danger">*</span></label>
              <input v-model="form.name" type="text" placeholder="e.g. Arjun Sharma"
                     class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition-colors"
                     :class="form.errors.name ? 'border-danger bg-danger/5' : 'border-gray-200'" />
              <p v-if="form.errors.name" class="text-danger text-xs mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Email Address <span class="text-danger">*</span></label>
              <input v-model="form.email" type="email" placeholder="student@email.com"
                     class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition-colors"
                     :class="form.errors.email ? 'border-danger bg-danger/5' : 'border-gray-200'" />
              <p v-if="form.errors.email" class="text-danger text-xs mt-1">{{ form.errors.email }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Phone Number</label>
              <input v-model="form.phone" type="tel" placeholder="+91 98765 43210"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20" />
              <p v-if="form.errors.phone" class="text-danger text-xs mt-1">{{ form.errors.phone }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Password <span class="text-danger">*</span></label>
              <div class="relative">
                <input v-model="form.password" :type="showPwd ? 'text' : 'password'" placeholder="Min. 8 characters"
                       class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 pr-10 transition-colors"
                       :class="form.errors.password ? 'border-danger bg-danger/5' : 'border-gray-200'" />
                <button type="button" @click="showPwd = !showPwd" class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted hover:text-text-main">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path v-if="!showPwd" stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                  </svg>
                </button>
              </div>
              <p v-if="form.errors.password" class="text-danger text-xs mt-1">{{ form.errors.password }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Confirm Password <span class="text-danger">*</span></label>
              <input v-model="form.password_confirmation" :type="showPwd ? 'text' : 'password'" placeholder="Re-enter password"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20" />
            </div>

          </div>
        </div>

        <!-- Academic Info -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h2 class="font-heading font-bold text-text-main text-sm mb-5 flex items-center gap-2">
            <span class="w-6 h-6 rounded-lg bg-primary/10 flex items-center justify-center text-primary text-xs font-bold">2</span>
            Academic Information
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Class / Grade</label>
              <select v-model="form.class_level_id"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main">
                <option value="">Select Class</option>
                <option v-for="cl in classLevels" :key="cl.id" :value="cl.id">{{ cl.label }}</option>
              </select>
              <p v-if="form.errors.class_level_id" class="text-danger text-xs mt-1">{{ form.errors.class_level_id }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">Date of Birth</label>
              <input v-model="form.dob" type="date"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20" />
              <p v-if="form.errors.dob" class="text-danger text-xs mt-1">{{ form.errors.dob }}</p>
            </div>

            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">School Name</label>
              <input v-model="form.school" type="text" placeholder="e.g. Delhi Public School, Vasant Kunj"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20" />
            </div>

          </div>
        </div>

        <!-- Location -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h2 class="font-heading font-bold text-text-main text-sm mb-5 flex items-center gap-2">
            <span class="w-6 h-6 rounded-lg bg-primary/10 flex items-center justify-center text-primary text-xs font-bold">3</span>
            Location
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">City</label>
              <input v-model="form.city" type="text" placeholder="e.g. New Delhi"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-muted uppercase tracking-wider mb-1.5">State</label>
              <select v-model="form.state"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main">
                <option value="">Select State</option>
                <option v-for="s in states" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>

          </div>
        </div>

        <!-- Status -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="font-heading font-bold text-text-main text-sm">Account Status</h2>
              <p class="text-text-muted text-xs mt-0.5">Active accounts can log in and enroll in exams</p>
            </div>
            <button type="button" @click="form.is_active = !form.is_active"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                    :class="form.is_active ? 'bg-success' : 'bg-gray-300'">
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                    :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"/>
            </button>
          </div>
        </div>

        <!-- Submit -->
        <div class="flex items-center gap-3 pb-6">
          <button type="submit" :disabled="form.processing"
                  class="bg-primary text-white px-6 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60 shadow-sm">
            {{ form.processing ? 'Creating…' : 'Create Student' }}
          </button>
          <Link :href="route('admin.users.index')"
                class="bg-gray-100 text-text-main px-6 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">
            Cancel
          </Link>
        </div>

      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  classLevels: Array,
  states:      Array,
});

const showPwd = ref(false);

const form = useForm({
  name:                  '',
  email:                 '',
  phone:                 '',
  password:              '',
  password_confirmation: '',
  class_level_id:        '',
  dob:                   '',
  school:                '',
  city:                  '',
  state:                 '',
  is_active:             true,
});

const submit = () => {
  form.post(route('admin.users.store'));
};
</script>
