<script setup>
  import { computed } from 'vue';
  import {
    siFacebook,
    siInstagram,
    siPinterest,
    siTiktok,
    siWhatsapp,
    siX,
    siYoutube,
  } from 'simple-icons';

  // props
  const props = defineProps({
    name: {
      type: String,
      required: true,
    },
    title: {
      type: String,
      default: null,
    },
  });

  // data
  const icons = {
    facebook: siFacebook,
    instagram: siInstagram,
    pinterest: siPinterest,
    tiktok: siTiktok,
    whatsapp: siWhatsapp,
    x: siX,
    youtube: siYoutube,
  };

  // computed
  const icon = computed(() => icons[props.name] ?? null);
</script>

<template>
  <svg
    v-if="icon"
    class="brand-icon"
    viewBox="0 0 24 24"
    role="img"
    focusable="false"
    :aria-hidden="title ? undefined : true"
    :aria-label="title || undefined"
  >
    <title v-if="title">{{ title }}</title>
    <path :d="icon.path" />
  </svg>
</template>

<style scoped>
  .brand-icon {
    display: block;
    width: 1em;
    height: 1em;
    fill: currentColor;
  }
</style>
