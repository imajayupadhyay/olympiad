<template>
  <SeoHead :title="title" noindex
    description="National Olympiad Hunt admin panel — manage exams, questions, students, results, payments, coupons and content." />
  <div class="min-h-screen flex" style="background:#FBF6EC;">

    <!-- Mobile backdrop (only when the drawer is open on small screens) -->
    <div
      v-if="sidebarOpen"
      @click="sidebarOpen = false"
      class="fixed inset-0 bg-black/50 z-30 lg:hidden"
      aria-hidden="true"
    ></div>

    <!-- Sidebar -->
    <aside
      class="w-64 flex flex-col shadow-2xl shrink-0 fixed inset-y-0 left-0 z-40 transition-transform duration-300 ease-in-out motion-reduce:transition-none"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
      style="background:linear-gradient(180deg,#0A1024 0%,#131C3D 60%,#1B2748 100%);"
    >
      <!-- Brand -->
      <div class="px-5 py-5 border-b border-blue-800/60 flex items-center">
        <AppLogo :size="54" variant="light" />
      </div>

      <!-- Nav: rendered from AdminPermissions::MODULES (shared as admin_modules), grouped by section -->
      <div class="px-3 pt-4 pb-2">
        <label class="relative block">
          <span class="sr-only">Find a page</span>
          <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-blue-300/70 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
          <input v-model="menuSearch" type="search" placeholder="Find a page…" autocomplete="off"
                 class="w-full bg-white/5 border border-white/10 rounded-lg pl-8 pr-2 py-2 text-xs text-white placeholder-blue-300/60 focus:outline-none focus:border-accent/60 focus:bg-white/10"
                 @keydown.esc="menuSearch = ''" />
        </label>
      </div>

      <nav class="flex-1 px-3 pb-5 overflow-y-auto text-sm" aria-label="Admin navigation" @click="closeOnMobile">
        <p v-if="menuSearch && !visibleGroups.length" class="px-3 py-4 text-xs text-blue-300/80">No page matches “{{ menuSearch }}”.</p>

        <div v-for="group in visibleGroups" :key="group.name" class="mb-1">
          <button type="button"
                  class="w-full flex items-center gap-2 px-3 pt-3 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-blue-400 hover:text-blue-200 transition-colors"
                  :aria-expanded="isOpen(group)" :aria-controls="`nav-${group.slug}`" @click.stop="toggleGroup(group)">
            <span class="flex-1 text-left">{{ group.name }}</span>
            <span v-if="!isOpen(group) && group.badge" class="min-w-[16px] h-4 px-1 rounded-full bg-accent text-white text-[9px] font-bold font-number inline-flex items-center justify-center tracking-normal">{{ group.badge > 99 ? '99+' : group.badge }}</span>
            <svg class="w-3 h-3 transition-transform motion-reduce:transition-none" :class="isOpen(group) ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
          </button>
          <div v-show="isOpen(group)" :id="`nav-${group.slug}`" class="space-y-0.5">
            <SidebarLink v-for="item in group.items" :key="item.key" :href="item.href" :active="item.active">
              <template #icon>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="ICONS[item.key] || ICONS.default"/>
                </svg>
              </template>
              <span class="flex items-center justify-between flex-1 min-w-0">
                <span class="truncate">{{ item.label }}</span>
                <span v-if="item.badge" class="ml-2 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-accent text-white text-[10px] font-bold font-number"
                      :aria-label="`${item.badge} need attention`">{{ item.badge > 99 ? '99+' : item.badge }}</span>
              </span>
            </SidebarLink>
          </div>
        </div>
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
            <p class="text-blue-300 text-xs truncate">{{ roleLabel }}</p>
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

    <!-- Page content (offset by sidebar width when open on desktop, full width otherwise) -->
    <div
      class="flex-1 min-w-0 flex flex-col min-h-screen transition-[margin] duration-300 ease-in-out motion-reduce:transition-none"
      :class="sidebarOpen ? 'lg:ml-64' : 'ml-0'"
    >

      <!-- Top bar -->
      <header class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3.5 flex items-center justify-between sticky top-0 z-20 shadow-sm">
        <div class="flex items-center gap-3 min-w-0">
          <button
            type="button"
            @click="toggleSidebar"
            class="p-2 -ml-1 rounded-lg text-text-muted hover:text-primary hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-primary/30 shrink-0"
            :aria-label="sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'"
            :aria-expanded="sidebarOpen"
          >
            <svg v-if="sidebarOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
            </svg>
            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>
          <div class="min-w-0">
            <h1 class="font-heading font-bold text-text-main text-lg leading-tight truncate">{{ title }}</h1>
            <p v-if="subtitle" class="text-text-muted text-xs mt-0.5 truncate">{{ subtitle }}</p>
          </div>
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
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SidebarLink from '@/Components/Shared/SidebarLink.vue';
import AppLogo from '@/Components/Shared/AppLogo.vue';
import SeoHead from '@/Components/Shared/SeoHead.vue';

defineProps({
  title:    { type: String, default: 'Dashboard' },
  subtitle: { type: String, default: '' },
});

const STORAGE_KEY = 'noh-admin-sidebar-open';
const page = usePage();

// Open by default; the real value is resolved on mount (desktop reads the saved
// preference, mobile always starts closed). The layout remounts on every Inertia
// visit, so persistence lives in localStorage rather than component state.
const sidebarOpen = ref(true);

const isDesktop = () => window.matchMedia('(min-width: 1024px)').matches;

const applyResponsiveState = () => {
  if (isDesktop()) {
    const stored = localStorage.getItem(STORAGE_KEY);
    sidebarOpen.value = stored === null ? true : stored === 'true';
  } else {
    sidebarOpen.value = false;
  }
};

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value;
  // Only the desktop preference is remembered; mobile is always a transient drawer.
  if (isDesktop()) localStorage.setItem(STORAGE_KEY, String(sidebarOpen.value));
};

const closeOnMobile = () => {
  if (!isDesktop()) sidebarOpen.value = false;
};

const onKeydown = (e) => {
  if (e.key === 'Escape' && !isDesktop() && sidebarOpen.value) sidebarOpen.value = false;
};

let resizeTimer;
const onResize = () => {
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(applyResponsiveState, 150);
};

onMounted(() => {
  applyResponsiveState();
  window.addEventListener('resize', onResize);
  window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize);
  window.removeEventListener('keydown', onKeydown);
  clearTimeout(resizeTimer);
});

const permissions = computed(() => page.props.admin_permissions || {});
const roleLabel = computed(() => page.props.admin_is_super ? 'Super Admin' : (page.props.admin_role?.name || 'Administrator'));
const can = (module, action = 'read') => !!permissions.value?.[module]?.[action];
// ── Sidebar menu (data-driven) ───────────────────────────────────────────────
// Icons per module key; labels, order and categories come from AdminPermissions::MODULES.
const ICONS = {
  certificates: 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z',
  content: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
  coupons: 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z',
  dashboard: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  data_entry: 'M3 5h18M3 12h18M3 19h18M8 5v14M16 5v14',
  default: 'M4 6h16M4 12h16M4 18h16',
  emails: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8m-18 8h18a2 2 0 002-2V8a2 2 0 00-2-2H3a2 2 0 00-2 2v6a2 2 0 002 2z',
  exam_sessions: 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z',
  exams: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
  forms: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  hall_tickets: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2',
  notifications: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
  payments: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
  questions: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  receipts: 'M9 14h6m-6 4h6M7 3h7l5 5v13H7a2 2 0 01-2-2V5a2 2 0 012-2z',
  referrals: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a3 3 0 10-2.83-4M5 12a3 3 0 102.83-4',
  reports: 'M9 17v-5m3 5V7m3 10v-3M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z',
  results: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
  roles: 'M12 3l7 4v5c0 4.5-2.9 7.8-7 9-4.1-1.2-7-4.5-7-9V7l7-4zm-2 9l1.5 1.5L15 10',
  school_designations: 'M7 7h10M7 12h10M7 17h6M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z',
  schools: 'M4 21V7l8-4 8 4v14M8 21v-7h8v7M7 10h.01M12 10h.01M17 10h.01',
  settings_categories: 'M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z',
  settings_classes: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
  settings_hall_ticket: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z',
  settings_receipts: 'M9 14h6m-6 4h6M8 3h8l3 3v15H5V6l3-3z',
  settings_subjects: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
  staff_users: 'M16 14a4 4 0 10-8 0m8 0a6 6 0 012 4v1H6v-1a6 6 0 012-4m8 0H8m9-7h4m-2-2v4',
  students: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
  support: 'M8 10h.01M12 10h.01M16 10h.01M21 12a8 8 0 01-11.6 7.15L3 21l1.85-6.4A8 8 0 1121 12z',
};

const GROUPS_KEY = 'noh-admin-nav-groups-v2';
const menuSearch = ref('');
const savedGroups = ref({});

const pathOf = (routeName) => {
  try { return new URL(route(routeName), window.location.origin).pathname; } catch { return null; }
};
const isActivePath = (path) => {
  if (!path) return false;
  const url = page.url.split('?')[0];
  return url === path || url.startsWith(`${path}/`);
};
const badgeFor = (key) => ({
  hall_tickets: page.props.admin_hall_tickets_pending,
  support: page.props.admin_support_unread,
}[key] || 0);

const groups = computed(() => {
  const list = [];
  for (const m of page.props.admin_modules || []) {
    if (!can(m.key)) continue;
    let group = list.find((g) => g.name === m.section);
    if (!group) {
      group = { name: m.section, slug: m.section.toLowerCase().replace(/[^a-z0-9]+/g, '-'), items: [] };
      list.push(group);
    }
    const href = route(m.route);
    group.items.push({ key: m.key, label: m.label, href, active: isActivePath(pathOf(m.route)), badge: badgeFor(m.key) });
  }
  return list.map((g) => ({ ...g, active: g.items.some((i) => i.active), badge: g.items.reduce((n, i) => n + i.badge, 0) }));
});

const visibleGroups = computed(() => {
  const q = menuSearch.value.trim().toLowerCase();
  if (!q) return groups.value;
  return groups.value
    .map((g) => ({ ...g, items: g.items.filter((i) => `${i.label} ${g.name}`.toLowerCase().includes(q)) }))
    .filter((g) => g.items.length);
});

// Open: while searching, the group holding the current page, or what the admin chose last.
// First visit: only Overview + the current page's group are open, so the list stays short.
const isOpen = (group) => {
  if (menuSearch.value.trim()) return true;
  if (group.active) return true;
  if (group.name in savedGroups.value) return savedGroups.value[group.name];
  return group.name === 'Overview';
};
const toggleGroup = (group) => {
  if (menuSearch.value.trim() || group.active) return; // the current page's group always stays open
  savedGroups.value = { ...savedGroups.value, [group.name]: !isOpen(group) };
  try { localStorage.setItem(GROUPS_KEY, JSON.stringify(savedGroups.value)); } catch { /* private mode */ }
};
onMounted(() => {
  try { savedGroups.value = JSON.parse(localStorage.getItem(GROUPS_KEY) || '{}') || {}; } catch { savedGroups.value = {}; }
});
</script>
