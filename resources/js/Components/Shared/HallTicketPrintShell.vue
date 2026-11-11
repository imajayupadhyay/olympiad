<script setup>
/** Bare print page around HallTicketCard (student + admin print routes). */
import { Head, Link } from '@inertiajs/vue3';
import SeoHead from '@/Components/Shared/SeoHead.vue';
import HallTicketCard from '@/Components/Shared/HallTicketCard.vue';

const props = defineProps({
    ticket: { type: Object, required: true },
    backHref: { type: String, required: true },
    backLabel: { type: String, default: 'Back' },
});

const print = () => window.print();
</script>

<template>
    <Head :title="`Hall Ticket ${ticket.header?.roll_number || ''}`.trim()" />
    <SeoHead title="Hall Ticket" noindex description="Printable hall ticket." />

    <div class="print-page">
        <div class="toolbar">
            <Link :href="backHref" class="tb-back">← {{ backLabel }}</Link>
            <p class="tb-hint">Tip: choose <b>Save as PDF</b> in the print dialog to download a copy. Use A4, portrait.</p>
            <button type="button" class="tb-print" @click="print">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/></svg>
                Print hall ticket
            </button>
        </div>
        <HallTicketCard :ticket="props.ticket" />
    </div>
</template>

<style scoped>
.print-page { min-height: 100vh; background: #F3E9D6; padding: 20px 16px 40px; }
.toolbar { max-width: 860px; margin: 0 auto 16px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.tb-back { font-weight: 700; font-size: 14px; color: #131C3D; text-decoration: none; }
.tb-back:hover { color: #C9501A; }
.tb-hint { flex: 1; min-width: 200px; margin: 0; font-size: 12.5px; color: #5B6373; }
.tb-print { display: inline-flex; align-items: center; gap: 8px; border: 0; cursor: pointer; color: #fff; font-weight: 700; font-size: 14px;
    padding: 10px 18px; border-radius: 12px; background: linear-gradient(135deg, #F2854E, #EE6A2C); box-shadow: 0 12px 26px -12px rgba(238,106,44,.8); }
.tb-print svg { width: 18px; height: 18px; }
@media print {
    .toolbar { display: none !important; }
    .print-page { background: #fff; padding: 0; min-height: 0; }
}
</style>

<style>
@media print {
    @page { size: A4 portrait; margin: 10mm; }
    html, body { background: #fff !important; }
}
</style>
