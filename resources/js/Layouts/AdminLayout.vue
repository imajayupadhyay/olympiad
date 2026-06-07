<template>
  <div class="min-h-screen flex" style="background:#FBF6EC;">

    <!-- Sidebar -->
    <aside
      class="w-64 flex flex-col shadow-2xl shrink-0 fixed inset-y-0 left-0 z-30"
      style="background:linear-gradient(180deg,#0A1024 0%,#131C3D 60%,#1B2748 100%);"
    >
      <!-- Brand -->
      <div class="px-5 py-5 border-b border-blue-800/60 flex items-center gap-3">
        <AppLogo :size="36" :show-text="false" />
        <div>
          <p class="font-heading font-bold text-white text-base leading-tight">NOH Admin</p>
          <p class="text-blue-300 text-xs">National Olympiad Hunt</p>
        </div>
      </div>

      <!-- Nav -->
      <nav class="flex-1 px-3 py-5 space-y-0.5 overflow-y-auto text-sm">
        <p class="text-blue-400 text-[10px] font-semibold uppercase tracking-widest px-3 pb-2 pt-1">Main</p>

        <SidebarLink :href="route('admin.dashboard')" :active="isActive('/admin/dashboard')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
          </template>
          Dashboard
        </SidebarLink>

        <SidebarLink :href="route('admin.users.index')" :active="isActive('/admin/users')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
          </template>
          Students
        </SidebarLink>

        <p class="text-blue-400 text-[10px] font-semibold uppercase tracking-widest px-3 pb-2 pt-4">Exams</p>

        <SidebarLink :href="route('admin.questions.index')" :active="isActive('/admin/questions')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </template>
          Question Bank
        </SidebarLink>

        <SidebarLink :href="route('admin.exams.index')" :active="isActive('/admin/exams')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
          </template>
          Exams
        </SidebarLink>

        <SidebarLink :href="route('admin.results')" :active="isActive('/admin/results')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
          </template>
          Results
        </SidebarLink>

        <SidebarLink :href="route('admin.certificates')" :active="isActive('/admin/certificates')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
          </template>
          Certificates
        </SidebarLink>

        <p class="text-blue-400 text-[10px] font-semibold uppercase tracking-widest px-3 pb-2 pt-4">Finance & Comms</p>

        <SidebarLink :href="route('admin.payments')" :active="isActive('/admin/payments')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
          </template>
          Payments
        </SidebarLink>

        <SidebarLink :href="route('admin.notifications')" :active="isActive('/admin/notifications')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
          </template>
          Notifications
        </SidebarLink>

        <SidebarLink :href="route('admin.content')" :active="isActive('/admin/content')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
          </template>
          Content
        </SidebarLink>

        <p class="text-blue-400 text-[10px] font-semibold uppercase tracking-widest px-3 pb-2 pt-4">Settings</p>

        <SidebarLink :href="route('admin.settings.subjects')" :active="isActive('/admin/settings/subjects')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
            </svg>
          </template>
          Subjects
        </SidebarLink>

        <SidebarLink :href="route('admin.settings.categories')" :active="isActive('/admin/settings/categories')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
            </svg>
          </template>
          Categories
        </SidebarLink>

        <SidebarLink :href="route('admin.settings.classes')" :active="isActive('/admin/settings/classes')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
          </template>
          Class Levels
        </SidebarLink>
      </nav>

      <!-- User + Logout -->
      <div class="px-4 py-4 border-t border-blue-800/60">
        <div class="flex items-center gap-3 mb-3 px-1">
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
            style="background:#EE6A2C;"
          >
            {{ $page.props.auth.user?.name?.charAt(0)?.toUpperCase() }}
          </div>
          <div class="min-w-0">
            <p class="text-white text-sm font-semibold truncate">{{ $page.props.auth.user?.name }}</p>
            <p class="text-blue-300 text-xs">Administrator</p>
          </div>
        </div>
        <Link
          :href="route('admin.logout')"
          method="post"
          as="button"
          class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-blue-200 hover:bg-red-500/20 hover:text-red-300 transition-colors text-sm"
        >
          <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          Sign Out
        </Link>
      </div>
    </aside>

    <!-- Page content (offset by sidebar width) -->
    <div class="flex-1 flex flex-col ml-64 min-h-screen">

      <!-- Top bar -->
      <header class="bg-white border-b border-gray-200 px-6 py-3.5 flex items-center justify-between sticky top-0 z-20 shadow-sm">
        <div>
          <h1 class="font-heading font-bold text-text-main text-lg leading-tight">{{ title }}</h1>
          <p v-if="subtitle" class="text-text-muted text-xs mt-0.5">{{ subtitle }}</p>
        </div>
        <div class="flex items-center gap-3">
          <a href="/" target="_blank" class="text-text-muted hover:text-primary text-xs flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
            View Site
          </a>
          <span class="bg-accent/10 text-accent text-xs font-semibold px-2.5 py-1 rounded-full border border-accent/20">Admin</span>
        </div>
      </header>

      <!-- Main slot -->
      <main class="flex-1 overflow-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SidebarLink from '@/Components/Shared/SidebarLink.vue';
import AppLogo from '@/Components/Shared/AppLogo.vue';

defineProps({
  title:    { type: String, default: 'Dashboard' },
  subtitle: { type: String, default: '' },
});

const isActive = (path) => usePage().url.startsWith(path);
</script>
