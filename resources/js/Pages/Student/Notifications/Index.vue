<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    notifications: { type: Object, default: () => ({ data: [], links: [] }) },
});

const fmt = (d) => d ? new Date(d).toLocaleString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

const hasUnread = computed(() => props.notifications.data.some((n) => !n.is_read));

const open = (n) => {
    const done = () => { if (n.link) router.visit(n.link); };
    if (!n.is_read) {
        router.patch(`/student/notifications/${n.id}/read`, {}, { preserveScroll: true, preserveState: false, onFinish: done });
    } else {
        done();
    }
};

const markAll = () => {
    router.patch('/student/notifications/read-all', {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Notifications" />

    <StudentLayout title="Notifications">
        <div class="head">
            <div>
                <h2 class="h2">Notifications</h2>
                <p class="sub">Announcements from our team and replies to your support tickets.</p>
            </div>
            <button v-if="hasUnread" class="btn-ghost" @click="markAll">Mark all read</button>
        </div>

        <div v-if="notifications.data.length" class="list">
            <div
                v-for="n in notifications.data"
                :key="n.id"
                class="row"
                :class="{ unread: !n.is_read, clickable: !!n.link }"
                @click="n.link ? open(n) : null"
            >
                <span class="ic" :class="{ on: !n.is_read }">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/></svg>
                </span>
                <div class="body">
                    <strong>{{ n.title }}</strong>
                    <p>{{ n.message }}</p>
                    <small>{{ fmt(n.created_at) }}</small>
                </div>
                <span v-if="!n.is_read" class="new-dot"></span>
            </div>
        </div>

        <div v-else class="empty">
            <div class="empty-ic">🔔</div>
            <h3>No notifications</h3>
            <p>You'll see announcements and support replies here.</p>
        </div>

        <div v-if="notifications.links && notifications.links.length > 3" class="pager">
            <template v-for="(l, i) in notifications.links" :key="i">
                <Link v-if="l.url" :href="l.url" class="pg" :class="{ active: l.active }" v-html="l.label" preserve-scroll />
                <span v-else class="pg disabled" v-html="l.label" />
            </template>
        </div>
    </StudentLayout>
</template>

<style scoped>
.head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: 1.3rem; flex-wrap: wrap; }
.h2 { font-family: "Fraunces", serif; font-weight: 600; font-size: 1.6rem; color: #0A1024; margin: 0; }
.sub { color: #5B6373; font-size: .92rem; margin: .25rem 0 0; }
.btn-ghost { background: transparent; border: 1px solid #E7D9BE; color: #5B6373; border-radius: 12px; padding: .55rem 1rem; font-size: .85rem; font-weight: 600; cursor: pointer; font-family: inherit; }
.btn-ghost:hover { background: #F3E9D6; }

.list { display: grid; gap: .7rem; }
.row { display: flex; align-items: flex-start; gap: .9rem; background: #fff; border: 1px solid #E7D9BE; border-radius: 14px; padding: 1rem 1.1rem; box-shadow: 0 2px 8px rgba(10,16,36,.04); position: relative; }
.row.unread { background: rgba(238,106,44,.04); border-color: #f1cdb4; }
.row.clickable { cursor: pointer; transition: transform .15s, box-shadow .2s, border-color .2s; }
.row.clickable:hover { transform: translateY(-2px); border-color: #EE6A2C; box-shadow: 0 16px 34px -20px rgba(10,16,36,.3); }
.ic { width: 40px; height: 40px; flex-shrink: 0; border-radius: 11px; display: grid; place-items: center; background: #F3E9D6; color: #9aa0ad; }
.ic.on { background: rgba(238,106,44,.14); color: #C9501A; }
.ic svg { width: 18px; height: 18px; }
.body { min-width: 0; flex: 1; }
.body strong { display: block; color: #0A1024; font-size: .95rem; margin-bottom: .1rem; }
.body p { margin: 0 0 .3rem; font-size: .85rem; color: #5B6373; line-height: 1.45; }
.body small { font-size: .74rem; color: #9aa0ad; }
.new-dot { width: 8px; height: 8px; border-radius: 50%; background: #EE6A2C; flex-shrink: 0; margin-top: .4rem; }

.empty { text-align: center; padding: 3.5rem 1rem; color: #5B6373; }
.empty-ic { font-size: 2.4rem; margin-bottom: .5rem; }
.empty h3 { font-family: "Fraunces", serif; color: #0A1024; margin: 0 0 .3rem; }
.empty p { font-size: .9rem; margin: 0; }

.pager { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: 1.4rem; justify-content: center; }
.pg { min-width: 34px; text-align: center; padding: .4rem .6rem; border-radius: 9px; border: 1px solid #E7D9BE; background: #fff; color: #5B6373; font-size: .82rem; text-decoration: none; }
.pg.active { background: #0A1024; color: #fff; border-color: #0A1024; }
.pg.disabled { opacity: .45; }
</style>
