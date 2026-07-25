<script setup>
  import { computed } from 'vue';

  // props
  const props = defineProps({
    histories: {
      type: Array,
      default: () => [],
    },
  });

  // computed
  const sortedHistories = computed(() => {
    return [...props.histories].sort((firstHistory, secondHistory) => {
      const orderDifference = Number(firstHistory.sort_order) - Number(secondHistory.sort_order);

      return orderDifference || Number(secondHistory.id) - Number(firstHistory.id);
    });
  });
</script>

<template>
  <section v-if="sortedHistories.length" class="performance-history-section">
    <div class="comments-heading">
      <div>
        <h2>Histórico de funciones</h2>
        <p>Espacios y temporadas anteriores de la obra.</p>
      </div>
    </div>

    <div class="performance-history-card">
      <ul class="performance-history-list">
        <li v-for="history in sortedHistories" :key="history.id">
          <strong>{{ history.venue_name }}</strong>
          <span>{{ history.year }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>
