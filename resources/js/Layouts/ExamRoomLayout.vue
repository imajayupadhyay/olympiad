<script setup>
// Minimal, locked, full-screen shell for the exam room — no app nav, no escape routes.
</script>

<template>
    <div class="exam-shell">
        <slot />
    </div>
</template>

<style scoped>
.exam-shell {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    flex-direction: column;
    background:
        radial-gradient(80% 60% at 100% 0%, rgba(238,106,44,.05), transparent 55%),
        #FBF6EC;
    font-family: "Plus Jakarta Sans", system-ui, sans-serif;
    color: #0A1024;
    overflow: hidden;
}
</style>
