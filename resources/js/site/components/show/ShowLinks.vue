<script setup>
  import { computed } from 'vue';
  import { ExternalLink } from '@lucide/vue';

  // props
  const props = defineProps({
    links: {
      type: Array,
      default: () => [],
    },
  });

  // computed
  const sortedLinks = computed(() => {
    return [...props.links].sort((firstLink, secondLink) => {
      const orderDifference = Number(firstLink.sort_order) - Number(secondLink.sort_order);

      return orderDifference || Number(firstLink.id) - Number(secondLink.id);
    });
  });
</script>

<template>
  <section v-if="sortedLinks.length" class="show-links-section">
    <div class="comments-heading">
      <div>
        <h2>Links de interés</h2>
        <p>Notas, entrevistas, videos y contenidos relacionados con la obra.</p>
      </div>
    </div>

    <div class="show-links-card">
      <ul class="show-links-list">
        <li v-for="link in sortedLinks" :key="link.id">
          <a :href="link.url" target="_blank" rel="nofollow noreferrer">
            <span>{{ link.text }}</span>
            <ExternalLink aria-hidden="true" />
          </a>
        </li>
      </ul>
    </div>
  </section>
</template>
