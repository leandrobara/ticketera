<script setup>
import AppFooter from './AppFooter.vue';
import AppHeader from './AppHeader.vue';
</script>

<template>
    <div class="min-h-screen bg-zinc-50 text-zinc-950">
        <AppHeader />

        <main class="mx-auto w-full max-w-7xl px-6 py-8">
            <slot />
        </main>

        <AppFooter />
    </div>
</template>
