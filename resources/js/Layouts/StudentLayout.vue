<template>
  <div class="min-h-screen flex bg-bg">
    <!-- Sidebar -->
    <aside class="w-64 bg-primary text-white flex flex-col shadow-xl shrink-0">
      <div class="px-6 py-5 border-b border-blue-800">
        <Link href="/" class="font-heading font-bold text-lg">NOH</Link>
        <p class="text-blue-300 text-xs mt-1">Student Portal</p>
      </div>

      <nav class="flex-1 px-4 py-6 space-y-1 text-sm">
        <SidebarLink href="/student/dashboard" :active="$page.url.startsWith('/student/dashboard')">
          Dashboard
        </SidebarLink>
        <SidebarLink href="/student/exams" :active="$page.url.startsWith('/student/exams')">
          Exams
        </SidebarLink>
        <SidebarLink href="/student/results" :active="$page.url.startsWith('/student/results')">
          Results
        </SidebarLink>
        <SidebarLink href="/student/leaderboard" :active="$page.url.startsWith('/student/leaderboard')">
          Leaderboard
        </SidebarLink>
        <SidebarLink href="/student/certificates" :active="$page.url.startsWith('/student/certificates')">
          Certificates
        </SidebarLink>
        <SidebarLink href="/student/practice" :active="$page.url.startsWith('/student/practice')">
          Practice
        </SidebarLink>
        <SidebarLink href="/student/payments" :active="$page.url.startsWith('/student/payments')">
          Payments
        </SidebarLink>
      </nav>

      <div class="px-4 py-4 border-t border-blue-800">
        <SidebarLink href="/student/profile" :active="$page.url.startsWith('/student/profile')">
          Profile
        </SidebarLink>
        <Link
          href="/logout"
          method="post"
          as="button"
          class="w-full text-left mt-2 px-3 py-2 rounded-lg text-blue-200 hover:bg-blue-800 hover:text-white transition-colors text-sm"
        >
          Logout
        </Link>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Top Bar -->
      <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
        <h1 class="font-heading font-bold text-text-main text-lg">{{ title }}</h1>
        <div class="text-sm text-text-muted">
          Welcome, <span class="font-semibold text-primary">{{ $page.props.auth.user?.name }}</span>
        </div>
      </header>

      <main class="flex-1 overflow-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SidebarLink from '@/Components/Shared/SidebarLink.vue';

defineProps({
  title: {
    type: String,
    default: '',
  },
});
</script>
