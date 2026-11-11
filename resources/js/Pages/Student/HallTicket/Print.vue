<script setup>
import HallTicketPrintShell from '@/Components/Shared/HallTicketPrintShell.vue';

defineProps({ ticket: { type: Object, required: true } });
</script>

<template>
    <HallTicketPrintShell :ticket="ticket" :back-href="route('student.hall-ticket')" back-label="Back to my hall ticket" />
</template>
