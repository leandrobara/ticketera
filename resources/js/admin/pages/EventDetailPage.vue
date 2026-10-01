<script setup>
import { computed, onMounted, ref } from 'vue';
import SeasonService from '@/admin/services/SeasonService';
import { eventPeriod, formatEventDate, periodClass, publicationClass, publicationLabel } from '@/admin/helpers/eventDisplay';

const season = ref(null);
const loading = ref(true);
const error = ref('');
const id = Number(window.location.pathname.match(/\/admin\/events\/(\d+)$/)?.[1]);

const dates = computed(() => season.value?.presentations ?? []);
const period = computed(() => eventPeriod({
  first_presentation_at: dates.value[0]?.starts_at,
  last_presentation_at: dates.value.reduce((latest, presentation) => {
    return !latest || new Date(presentation.ends_at) > new Date(latest) ? presentation.ends_at : latest;
  }, null),
}));

onMounted(async () => {
  try {
    const response = await SeasonService.getInstance().getSeason(id);
    season.value = response.data.data;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo cargar el evento.';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="mb-4"><a href="/admin/events">← Eventos</a></div>
  <div v-if="loading" class="text-secondary">Cargando evento…</div>
  <div v-else-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
  <template v-else-if="season">
    <div class="page-header mb-4 d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <div class="page-pretitle">Detalle del evento</div>
        <h1 class="page-title">{{ season.show?.title ?? 'Evento' }}</h1>
        <p v-if="season.name" class="text-secondary mt-2 mb-0">{{ season.name }}</p>
        <div class="d-flex gap-2 mt-3">
          <span class="badge" :class="publicationClass(season.status)">{{ publicationLabel(season.status) }}</span>
          <span class="badge" :class="periodClass(period)">{{ period }}</span>
        </div>
      </div>
      <a v-if="!['finished', 'cancelled'].includes(season.status)" class="btn btn-primary" :href="'/admin/events/new?season_id=' + season.id">Editar evento</a>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <section class="card mb-4">
          <div class="card-header"><h2 class="card-title">Información del evento</h2></div>
          <div class="card-body">
            <img v-if="season.show?.main_image?.url" class="wizard-image mb-3" :src="season.show.main_image.url" :alt="season.show.title">
            <p v-if="season.show?.subtitle" class="fw-semibold">{{ season.show.subtitle }}</p>
            <p v-if="season.show?.synopsis" class="mb-3">{{ season.show.synopsis }}</p>
            <dl class="row mb-0">
              <dt class="col-sm-4">Show</dt><dd class="col-sm-8">{{ season.show?.title ?? '—' }}</dd>
              <dt class="col-sm-4">Edición</dt><dd class="col-sm-8">{{ season.name || 'Sin nombre' }}</dd>
              <dt class="col-sm-4">Lugar</dt><dd class="col-sm-8">{{ season.venue?.name ?? 'Sin lugar' }}<span v-if="season.venue?.address" class="text-secondary"> · {{ season.venue.address }}</span></dd>
              <dt class="col-sm-4">Género</dt><dd class="col-sm-8">{{ season.show?.genre || '—' }}</dd>
            </dl>
            <a v-if="['published', 'finished'].includes(season.status)" :href="'/shows/' + season.id + '/' + season.show?.slug" target="_blank" rel="noopener noreferrer">Ver página pública ↗</a>
          </div>
        </section>

        <section class="card mb-4">
          <div class="card-header"><h2 class="card-title">Funciones · {{ dates.length }}</h2></div>
          <div v-if="!dates.length" class="card-body text-secondary">Todavía no hay funciones cargadas.</div>
          <div v-else class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th>Inicio</th><th>Fin</th><th>Estado</th></tr></thead>
              <tbody><tr v-for="presentation in dates" :key="presentation.id">
                <td>{{ formatEventDate(presentation.starts_at) }}</td>
                <td>{{ formatEventDate(presentation.ends_at) }}</td>
                <td>{{ presentation.status ?? '—' }}</td>
              </tr></tbody>
            </table>
          </div>
        </section>

        <section v-if="season.ticket_types?.length" class="card">
          <div class="card-header"><h2 class="card-title">Tipos de entrada</h2></div>
          <div class="table-responsive"><table class="table table-vcenter card-table">
            <thead><tr><th>Nombre</th><th>Precio</th><th>Estado</th></tr></thead>
            <tbody><tr v-for="ticket in season.ticket_types" :key="ticket.id">
              <td>{{ ticket.name }}</td><td>{{ new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(Number(ticket.price)) }}</td>
              <td>{{ ticket.is_active ? 'Activa' : 'Inactiva' }}</td>
            </tr></tbody>
          </table></div>
        </section>
      </div>

      <aside class="col-lg-4">
        <div class="card">
          <div class="card-header"><h2 class="card-title">Nueva edición de este show</h2></div>
          <div class="card-body">
            <p>¿Querés volver a presentar <strong>{{ season.show?.title }}</strong>?</p>
            <p class="text-secondary">Reutilizá sus datos e imagen y configurá nuevas fechas, lugar y entradas para la próxima edición.</p>
            <p class="small text-secondary">El show se comparte entre ediciones: los cambios en sus datos generales también se reflejan en las anteriores.</p>
            <p v-if="!['finished', 'cancelled'].includes(season.status)" class="small text-secondary">Para usar el mismo lugar, primero tenés que finalizar esta temporada desde <a href="/admin/seasons">Temporadas</a>.</p>
            <a class="btn btn-success w-100" :href="'/admin/events/new?show_id=' + season.show_id">Crear nueva edición a partir de este show</a>
          </div>
        </div>
      </aside>
    </div>
  </template>
</template>
