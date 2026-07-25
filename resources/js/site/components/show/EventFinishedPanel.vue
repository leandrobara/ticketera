<script setup>
  import { CalendarCheck } from '@lucide/vue';

  // props
  defineProps({
    hasSocialLinks: {
      type: Boolean,
      default: false,
    },
  });
</script>

<template>
  <aside id="tickets" class="ticket-card event-finished-panel">
    <div class="event-finished-icon" aria-hidden="true">
      <CalendarCheck />
    </div>
    <h2>Evento finalizado</h2>
    <p>Esta obra ya no tiene funciones disponibles.</p>

    <template v-if="hasSocialLinks">
      <p class="event-finished-social-copy">
        Seguinos en redes para enterarte de nuevas fechas.
      </p>
      <a class="event-finished-social-link" href="#social-links">
        Ver redes de la obra
      </a>
    </template>
  </aside>
</template>
