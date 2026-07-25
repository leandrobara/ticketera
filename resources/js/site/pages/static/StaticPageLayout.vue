<script setup>
  import SiteHeader from '@/site/components/layout/SiteHeader.vue';
  import SiteFooter from '@/site/components/show/SiteFooter.vue';

  // props
  defineProps({
    title: {
      type: String,
      required: true,
    },
    eyebrow: {
      type: String,
      default: 'Ticketera',
    },
  });
</script>

<template>
  <div class="static-page">
    <SiteHeader variant="solid" anchor-prefix="/" />

    <main class="static-page-shell">
      <section class="static-page-card">
        <p class="static-page-eyebrow">{{ eyebrow }}</p>
        <h1>{{ title }}</h1>
        <div class="static-page-content">
          <slot></slot>
        </div>
      </section>
    </main>

    <SiteFooter />
  </div>
</template>
