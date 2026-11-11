<script setup>
import HallTicketPrintShell from '@/Components/Shared/HallTicketPrintShell.vue';

defineProps({ ticket: { type: Object, required: true }, ticketId: { type: Number, default: null } });
</script>

<template>
    <HallTicketPrintShell :ticket="ticket" :back-href="ticketId ? route('admin.hall-tickets.show', ticketId) : route('admin.hall-tickets.index')" back-label="Back to review" />
</template>
