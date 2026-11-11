<script setup>
/**
 * The hall ticket. Renders HallTicketLayoutService::render() output, so the student
 * page, admin review, both print pages and the settings live preview all draw the same
 * admin-configured layout. No business rules here: only packing and presentation.
 */
import { computed } from 'vue';

const props = defineProps({
    ticket: { type: Object, required: true },
});

const t = computed(() => props.ticket);
const cols = computed(() => Math.min(3, Math.max(1, t.value.detail_columns || 2)));

/*
 * Pack details into rows of `cols`: wide fields take a full row and the last cell of
 * any incomplete row stretches to fill it, so the grid never shows holes whatever the
 * admin switches on or off.
 */
const packed = computed(() => {
    const out = [];
    let col = 0;
    const fillRow = () => {
        if (col > 0 && out.length) out[out.length - 1].span += cols.value - col;
        col = 0;
    };
    for (const d of t.value.details ?? []) {
        if (d.wide || cols.value === 1) {
            fillRow();
            out.push({ ...d, span: cols.value });
            continue;
        }
        out.push({ ...d, span: 1 });
        col += 1;
        if (col === cols.value) col = 0;
    }
    fillRow();
    return out;
});

const photo = computed(() => t.value.photo);
const signatures = computed(() => t.value.signatures ?? []);
</script>

<template>
    <article class="ht" :class="[`theme-${t.theme || 'brand'}`, { draft: t.status !== 'approved' }]">
        <div v-if="t.watermark" class="ht-watermark" aria-hidden="true">{{ t.watermark }}</div>

        <!-- header -->
        <header class="ht-head" :class="{ 'no-logo': !t.header.logo, 'no-roll': !t.header.roll_box }">
            <img v-if="t.header.logo" src="/NEO_logo_horizontal_transparent.svg" alt="National Excellence Olympiad" class="ht-logo" />
            <div class="ht-title">
                <p v-if="t.header.session" class="ht-kicker">{{ t.header.session }}</p>
                <h2>{{ t.header.title }}</h2>
                <p v-if="t.header.subtitle" class="ht-sub">{{ t.header.subtitle }}</p>
            </div>
            <div v-if="t.header.roll_box" class="ht-roll">
                <span>Roll number</span>
                <strong>{{ t.header.roll_number || 'Issued on approval' }}</strong>
            </div>
        </header>

        <!-- candidate -->
        <section v-if="packed.length || photo" class="ht-cand" :class="photo ? `photo-${photo.position}` : 'no-photo'">
            <dl v-if="packed.length" class="ht-grid" :style="{ gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }">
                <div v-for="d in packed" :key="d.key" class="ht-cell" :style="{ gridColumn: `span ${d.span}` }">
                    <dt>{{ d.label }}</dt>
                    <dd :class="{ mono: d.mono, strong: d.strong }">{{ d.value ?? '—' }}</dd>
                </div>
            </dl>
            <div v-if="photo" class="ht-side">
                <div class="ht-photo">
                    <img v-if="photo.url" :src="photo.url" alt="Candidate photograph" />
                    <span v-else>Affix passport-size photograph</span>
                </div>
                <div v-if="photo.signature_box" class="ht-sign"><span>Candidate's signature</span></div>
            </div>
        </section>

        <!-- schedule -->
        <section v-if="t.schedule" class="ht-sec">
            <h3>{{ t.schedule.title }}</h3>
            <div v-if="t.schedule.rows.length" class="ht-table-wrap">
                <table class="ht-table">
                    <thead>
                        <tr><th v-for="c in t.schedule.columns" :key="c.key" :class="`col-${c.key}`">{{ c.label }}</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in t.schedule.rows" :key="i">
                            <td v-for="c in t.schedule.columns" :key="c.key" :data-label="c.label" :class="[`col-${c.key}`, { mono: c.mono }]">
                                <span class="ht-cellv">
                                    <component :is="c.key === 'exam' ? 'strong' : 'span'">{{ row[c.key]?.text }}</component>
                                    <small v-if="row[c.key]?.sub">{{ row[c.key].sub }}</small>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="ht-empty">No olympiad enrolments yet. Enrolled olympiads appear here automatically.</p>
            <p v-if="t.venue" class="ht-venue"><b>Venue:</b> {{ t.venue }}</p>
        </section>
        <p v-else-if="t.venue" class="ht-venue solo"><b>Venue:</b> {{ t.venue }}</p>

        <!-- instructions -->
        <section v-if="t.instructions" class="ht-sec">
            <h3>{{ t.instructions.title }}</h3>
            <ol class="ht-rules" :class="{ two: t.instructions.columns === 2 }">
                <li v-for="(rule, i) in t.instructions.items" :key="i">{{ rule }}</li>
            </ol>
        </section>

        <!-- signatures + stamp -->
        <footer v-if="signatures.length || t.stamp" class="ht-foot" :style="{ gridTemplateColumns: `repeat(${Math.max(signatures.length, 1)}, minmax(0, 1fr))` }">
            <div v-for="(label, i) in signatures" :key="i" class="ht-sig">
                <span v-if="t.stamp && i === signatures.length - 1" class="ht-stamp"><b>{{ t.stamp.text }}</b><small>{{ t.stamp.date }}</small></span>
                <span v-else class="ht-line"></span>
                {{ label }}
            </div>
            <div v-if="!signatures.length && t.stamp" class="ht-sig solo">
                <span class="ht-stamp"><b>{{ t.stamp.text }}</b><small>{{ t.stamp.date }}</small></span>
            </div>
        </footer>
        <p v-if="t.footer_note" class="ht-note">{{ t.footer_note }}</p>
    </article>
</template>

<style scoped>
.ht {
    --ht-ink: #0A1024; --ht-head: #131C3D; --ht-accent: #C9501A; --ht-line: #c9cdd6; --ht-soft: #e3e5ea; --ht-zebra: #FBF9F3; --ht-muted: #5B6373;
    --ht-bar: linear-gradient(90deg, #131C3D 0 70%, #EE6A2C 70% 88%, #D6991F 88%);
    position: relative; overflow: hidden; background: #fff; color: var(--ht-ink);
    border: 1.5px solid var(--ht-ink); border-radius: 6px; padding: 26px 28px 18px;
    font-family: "Plus Jakarta Sans", system-ui, sans-serif; font-size: 13px; line-height: 1.45;
    max-width: 860px; margin: 0 auto;
}
.ht.theme-classic { --ht-head: #000; --ht-ink: #000; --ht-accent: #000; --ht-zebra: #f6f6f6; --ht-muted: #444; --ht-bar: #000; }
.ht::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 6px; background: var(--ht-bar); }
.mono { font-family: "Space Grotesk", monospace; font-variant-numeric: tabular-nums; }

.ht-watermark {
    position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; z-index: 2;
    font-family: "Space Grotesk", monospace; font-weight: 700; font-size: 58px; letter-spacing: .08em;
    color: rgba(201,80,26,.11); transform: rotate(-24deg); text-align: center;
}
.theme-classic .ht-watermark { color: rgba(0,0,0,.08); }

.ht-head { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 18px; padding: 6px 0 16px; border-bottom: 1.5px solid var(--ht-ink); }
.ht-head.no-logo { grid-template-columns: 1fr auto; }
.ht-head.no-roll { grid-template-columns: auto 1fr; }
.ht-head.no-logo.no-roll { grid-template-columns: 1fr; }
.ht-logo { height: 52px; width: auto; display: block; }
.ht-title { text-align: center; }
.ht-kicker { margin: 0; font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--ht-accent); }
.ht-title h2 { margin: 2px 0 0; font-family: "Fraunces", Georgia, serif; font-weight: 700; font-size: 30px; letter-spacing: .02em; text-transform: uppercase; }
.ht-sub { margin: 0; font-size: 12px; color: var(--ht-muted); }
.ht-roll { border: 1.5px solid var(--ht-ink); border-radius: 6px; padding: 8px 16px; text-align: center; min-width: 150px; white-space: nowrap; }
.ht-roll span { display: block; font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--ht-muted); }
.ht-roll strong { display: block; font-family: "Space Grotesk", monospace; font-size: 18px; letter-spacing: .04em; margin-top: 2px; }

.ht-cand { display: grid; gap: 18px; padding: 16px 0; border-bottom: 1px solid var(--ht-line); grid-template-columns: 1fr 150px; }
.ht-cand.photo-left { grid-template-columns: 150px 1fr; }
.ht-cand.photo-left .ht-side { order: -1; }
.ht-cand.no-photo { grid-template-columns: 1fr; }
/* 1px gap over a line-coloured background = clean borders for any column count. */
.ht-grid { display: grid; gap: 1px; margin: 0; background: var(--ht-soft); border: 1px solid var(--ht-line); border-radius: 4px; overflow: hidden; align-content: start; }
.ht-cell { padding: 7px 10px; min-width: 0; background: #fff; }
.ht-cell dt { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ht-muted); }
.ht-cell dd { margin: 1px 0 0; font-size: 13px; word-break: break-word; }
.ht-cell dd.strong { font-size: 16px; font-weight: 800; text-transform: uppercase; letter-spacing: .02em; }
.ht-side { display: grid; gap: 10px; align-content: start; }
.ht-photo { width: 150px; height: 180px; border: 1.5px solid var(--ht-ink); border-radius: 4px; overflow: hidden; display: grid; place-items: center; text-align: center; background: var(--ht-zebra); }
.ht-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ht-photo span { font-size: 11px; color: var(--ht-muted); padding: 10px; }
.ht-sign { height: 54px; border: 1px dashed #8b92a1; border-radius: 4px; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 4px; }
.ht-sign span { font-size: 10px; color: var(--ht-muted); }

.ht-sec { padding: 14px 0 4px; }
.ht-sec h3 { margin: 0 0 8px; font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: var(--ht-head); display: flex; align-items: center; gap: 8px; }
.ht-sec h3::after { content: ""; flex: 1; height: 1px; background: var(--ht-line); }
.ht-table-wrap { overflow-x: auto; }
.ht-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.ht-table th { background: var(--ht-head); color: #fff; text-align: left; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 7px 8px; white-space: nowrap; }
.ht-table td { border: 1px solid var(--ht-line); padding: 7px 8px; vertical-align: top; }
.ht-table td.mono { white-space: nowrap; }
.ht-cellv { display: block; min-width: 0; }
.ht-table td strong { display: block; font-size: 12.5px; }
.ht-table td small { display: block; color: var(--ht-muted); font-size: 11px; }
.ht-table tbody tr:nth-child(even) td { background: var(--ht-zebra); }
.ht-empty { margin: 0; padding: 12px; border: 1px dashed var(--ht-line); border-radius: 4px; color: var(--ht-muted); text-align: center; }
.ht-venue { margin: 8px 0 0; font-size: 12px; color: #2A335A; }
.ht-venue.solo { padding-top: 10px; }

.ht-rules { list-style: decimal; margin: 0; padding-left: 18px; font-size: 11.5px; color: #2A335A; }
.ht-rules.two { columns: 2; column-gap: 26px; }
.ht-rules li { break-inside: avoid; margin-bottom: 4px; }

.ht-foot { display: grid; gap: 22px; padding: 26px 0 6px; }
.ht-sig { display: grid; gap: 6px; text-align: center; font-size: 11px; font-weight: 600; color: #2A335A; align-content: end; }
.ht-sig.solo { justify-items: end; }
.ht-line { height: 40px; border-bottom: 1px solid var(--ht-ink); display: block; }
.ht-stamp { display: grid; place-content: center; min-height: 40px; padding: 4px 10px; color: #168A66; border: 2px solid #168A66; border-radius: 6px; transform: rotate(-3deg); margin: 0 12px; }
.theme-classic .ht-stamp { color: #000; border-color: #000; }
.ht-stamp b { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; }
.ht-stamp small { font-size: 10px; font-family: "Space Grotesk", monospace; }
.ht-note { margin: 6px 0 0; text-align: center; font-size: 10px; color: #8b92a1; }

/* phones: stack the header, one detail column, schedule rows become cards */
@media screen and (max-width: 680px) {
    .ht { padding: 22px 14px 14px; font-size: 12.5px; }
    .ht-head, .ht-head.no-logo, .ht-head.no-roll { grid-template-columns: 1fr; justify-items: center; text-align: center; gap: 10px; }
    .ht-logo { height: 42px; }
    .ht-title h2 { font-size: 24px; }
    .ht-cand, .ht-cand.photo-left { grid-template-columns: 1fr; }
    .ht-side { grid-template-columns: 120px 1fr; align-items: end; order: -1; }
    .ht-photo { width: 120px; height: 144px; }
    .ht-grid { grid-template-columns: 1fr !important; }
    .ht-cell { grid-column: auto !important; }
    .ht-table thead { display: none; }
    .ht-table, .ht-table tbody, .ht-table tr, .ht-table td { display: block; width: 100%; }
    .ht-table tr { border: 1px solid var(--ht-line); border-radius: 6px; margin-bottom: 10px; overflow: hidden; }
    .ht-table td { border: 0; border-bottom: 1px solid #eef0f3; display: flex; justify-content: space-between; gap: 12px; text-align: right; background: #fff !important; }
    .ht-table td::before { content: attr(data-label); font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--ht-muted); text-align: left; flex-shrink: 0; }
    .ht-table td.col-index { display: none; }
    .ht-rules.two { columns: 1; }
    .ht-foot { grid-template-columns: 1fr !important; gap: 14px; }
    .ht-watermark { font-size: 22px; }
}

@media print {
    /* Compact so a ticket with several olympiads still fits one A4 page. */
    .ht { border-radius: 0; max-width: none; font-size: 10.5px; line-height: 1.35; padding: 14px 16px 8px; }
    .ht::before, .ht-table th, .ht-table tbody tr:nth-child(even) td, .ht-grid { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .ht-sec, .ht-cand, .ht-foot { break-inside: avoid; }
    .ht-head { padding: 4px 0 10px; gap: 12px; }
    .ht-logo { height: 40px; }
    .ht-title h2 { font-size: 24px; }
    .ht-sub, .ht-kicker { font-size: 10px; }
    .ht-roll { padding: 6px 12px; min-width: 130px; }
    .ht-roll strong { font-size: 15px; }
    .ht-cand { grid-template-columns: 1fr 118px; gap: 12px; padding: 10px 0; }
    .ht-cand.photo-left { grid-template-columns: 118px 1fr; }
    .ht-cand.no-photo { grid-template-columns: 1fr; }
    .ht-cell { padding: 4px 8px; }
    .ht-cell dt { font-size: 8.5px; }
    .ht-cell dd { font-size: 11px; }
    .ht-cell dd.strong { font-size: 13.5px; }
    .ht-photo { width: 118px; height: 142px; }
    .ht-sign { height: 40px; }
    .ht-sec { padding: 8px 0 2px; }
    .ht-sec h3 { margin-bottom: 5px; font-size: 9.5px; }
    .ht-table { font-size: 10px; }
    .ht-table th { font-size: 8.5px; padding: 5px 6px; }
    .ht-table td { padding: 4px 6px; }
    .ht-table td strong { font-size: 10.5px; }
    .ht-table td small { font-size: 9px; }
    .ht-venue { font-size: 10px; margin-top: 5px; }
    .ht-rules { font-size: 9.5px; }
    .ht-rules.two { column-gap: 18px; }
    .ht-rules li { margin-bottom: 2px; }
    .ht-foot { padding: 16px 0 4px; gap: 16px; }
    .ht-line { height: 30px; }
    .ht-note { font-size: 8.5px; margin-top: 3px; }
}
</style>
