<script setup>
  import { computed } from 'vue';

  // props
  const props = defineProps({
    credits: {
      type: Array,
      default: () => [],
    },
  });

  // computed
  const cast = computed(() => props.credits.filter((credit) => credit.section === 'cast'));
  const creative = computed(() => props.credits.filter((credit) => credit.section !== 'cast'));
</script>

<template>
  <section v-if="cast.length" class="cast-section">
    <h2>Elenco</h2>
    <ul class="cast-list" aria-label="Listado de integrantes del elenco">
      <li v-for="credit in cast" :key="credit.id" class="cast-item">
        <div class="cast-photo-placeholder">
          <img v-if="credit.photo_url" :src="credit.photo_url" :alt="credit.name">
          <span v-else>{{ credit.name?.slice(0, 1) || '?' }}</span>
        </div>
        <div>
          <h3 class="cast-name">{{ credit.name }}</h3>
          <p class="cast-role">Como: {{ credit.character_name || credit.role }}</p>
        </div>
      </li>
    </ul>
  </section>

  <section v-if="creative.length" class="creative-section">
    <h2>Equipo técnico</h2>
    <ul class="creative-list" aria-label="Listado de ficha técnica">
      <li v-for="credit in creative" :key="credit.id" class="creative-item">
        <div class="creative-photo-placeholder">
          <img v-if="credit.photo_url" :src="credit.photo_url" :alt="credit.name">
          <span v-else>{{ credit.name?.slice(0, 1) || '?' }}</span>
        </div>
        <div>
          <p class="creative-name">{{ credit.name }}</p>
          <p class="creative-role">{{ credit.role }}</p>
        </div>
      </li>
    </ul>
  </section>
</template>
