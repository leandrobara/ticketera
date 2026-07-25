<script setup>
  import { computed } from 'vue';
  import EntradatixLogo from '@/site/components/brand/EntradatixLogo.vue';

  // props
  const props = defineProps({
    variant: {
      type: String,
      default: 'transparent',
      validator: (value) => ['transparent', 'solid'].includes(value),
    },
    anchorPrefix: {
      type: String,
      default: '',
    },
  });

  // computed
  const anchor = computed(() => props.anchorPrefix);
</script>

<template>
  <header class="site-nav" :class="`site-nav--${variant}`">
    <a href="/" class="site-logo" aria-label="Entradatix">
      <EntradatixLogo />
    </a>
    <!-- <nav class="site-nav-links" aria-label="Navegación principal">
      <a :href="`${anchor}#about`">Obra</a>
      <a :href="`${anchor}#tickets`">Entradas</a>
      <a :href="`${anchor}#venue`">Espacio</a>
    </nav> -->
    <a class="publish-link" href="/publica-tu-obra">Publicá tu obra</a>
  </header>
</template>
