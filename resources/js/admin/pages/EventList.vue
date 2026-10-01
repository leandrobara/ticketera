<script setup>
import { onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import SeasonService from '@/admin/services/SeasonService';
import { eventPeriod, periodClass, publicationClass, publicationLabel } from '@/admin/helpers/eventDisplay';

const seasons = ref([]);
const pagination = ref(null);
const loading = ref(true);
const error = ref('');
const filters = reactive({ search: '', status: '', period: '' });
const failedImages = ref(new Set());
let searchTimer;
let searchPending = false;
let appliedSearch = '';
let requestId = 0;

function eventDate(season) {
  return season.next_presentation_at || season.latest_presentation_at || season.first_presentation_at;
}

function datePart(value, options) {
  return new Intl.DateTimeFormat('es-AR', options).format(new Date(value));
}

function eventInitial(season) {
  return (season.show?.title || 'Evento').trim().charAt(0).toLocaleUpperCase('es-AR');
}

function publicUrl(season) {
  return `/shows/${season.id}/${season.show?.slug || ''}`;
}

function imageFailed(season) {
  failedImages.value = new Set([...failedImages.value, season.id]);
}

async function load(page = 1) {
  const currentRequest = ++requestId;
  const search = filters.search.trim();
  appliedSearch = search.length >= 3 ? search : '';
  searchPending = false;
  loading.value = true;
  error.value = '';

  try {
    const response = await SeasonService.getInstance().getSeasons({
      page,
      search: appliedSearch || undefined,
      status: filters.status || undefined,
      period: filters.period || undefined,
    });
    if (currentRequest !== requestId) return;
    pagination.value = response.data.data;
    seasons.value = response.data.data.data ?? [];
  } catch (e) {
    if (currentRequest !== requestId) return;
    error.value = e.response?.data?.message ?? 'No se pudieron cargar los eventos.';
  } finally {
    if (currentRequest === requestId) loading.value = false;
  }
}

function applyFilters() {
  clearTimeout(searchTimer);
  load();
}

function clearSearch() {
  filters.search = '';
}

watch(() => filters.search, search => {
  clearTimeout(searchTimer);
  if (search.trim().length < 3) {
    if (appliedSearch || searchPending) load();
    return;
  }
  searchPending = true;
  ++requestId;
  loading.value = true;
  searchTimer = setTimeout(() => load(), 250);
});

onMounted(load);
onUnmounted(() => { clearTimeout(searchTimer); ++requestId; });
</script>

<template>
  <div class="events-page-header mb-4">
    <h1 class="page-title">Listado de eventos</h1>
    <span v-if="pagination" class="text-secondary">({{ pagination.total }})</span>
    <a class="btn btn-success" href="/admin/events/new">Crear evento</a>
  </div>

  <div class="card card-body mb-4">
    <div class="row g-3 align-items-center">
      <div class="col-12 col-lg-6">
        <label class="visually-hidden" for="event-search">Buscar evento por nombre</label>
        <div class="events-search">
          <svg class="events-search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input id="event-search" v-model="filters.search" class="form-control" type="text" placeholder="Buscar evento por nombre" autocomplete="off" @keydown.enter.prevent="applyFilters">
          <button v-if="filters.search" class="events-search-clear" type="button" aria-label="Quitar búsqueda" @click="clearSearch">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <label class="visually-hidden" for="event-publication">Publicación</label>
        <select id="event-publication" v-model="filters.status" class="form-select" @change="applyFilters">
          <option value="">Todas los eventos</option>
          <option value="draft">Eventos en estado borrador</option>
          <option value="published">Eventos publicados</option>
          <option value="finished">Eventos finalizados</option>
          <option value="cancelled">Eventos cancelados</option>
        </select>
      </div>
      <div class="col-6 col-lg-3">
        <label class="visually-hidden" for="event-period">Fechas</label>
        <select id="event-period" v-model="filters.period" class="form-select" @change="applyFilters">
          <option value="">Todos los momentos</option>
          <option value="upcoming">Próximos eventos</option>
          <option value="ongoing">Eventos en curso</option>
          <option value="past">Eventos pasados</option>
          <option value="undated">Eventos sin fechas</option>
        </select>
      </div>
    </div>
  </div>

  <div class="card">
    <div v-if="error" class="alert alert-danger m-3" role="alert">{{ error }}</div>
    <div v-if="loading" class="card-body text-secondary">Cargando eventos…</div>
    <div v-else-if="!seasons.length" class="card-body text-center py-5">
      <p class="text-secondary">No hay eventos para mostrar.</p>
      <a v-if="!pagination?.total && !filters.search && !filters.status && !filters.period" class="btn btn-success" href="/admin/events/new">Crear primer evento</a>
    </div>
    <div v-else class="table-responsive">
      <table class="table table-vcenter card-table events-table">
        <thead>
          <tr>
            <th>Próxima fecha</th>
            <th>Evento</th>
            <th>Publicación</th>
            <th>Momento</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="season in seasons" :key="season.id">
            <td class="events-date-cell">
              <time v-if="eventDate(season)" class="events-date" :datetime="eventDate(season)">
                <span class="events-date-month">{{ datePart(eventDate(season), { month: 'short' }) }}</span>
                <strong class="events-date-day">{{ datePart(eventDate(season), { day: '2-digit' }) }}</strong>
                <span class="events-date-time">{{ datePart(eventDate(season), { hour: '2-digit', minute: '2-digit' }) }}</span>
              </time>
              <span v-else class="text-secondary small">Sin fechas</span>
            </td>
            <td>
              <div class="events-identity">
                <img v-if="season.show?.main_image?.url && !failedImages.has(season.id)" class="events-banner" :src="season.show.main_image.url" :alt="'Banner de ' + (season.show?.title || 'evento')" @error="imageFailed(season)">
                <span v-else class="events-banner events-banner-initial" aria-hidden="true">{{ eventInitial(season) }}</span>
                <div class="events-identity-text">
                  {{ season.show?.title ?? 'Evento sin título' }}
                  <div class="text-secondary small">{{ season.venue?.name ?? 'Lugar sin definir' }}</div>
                  <div class="text-secondary small">Funciones: {{ season.past_presentations_count ?? 0 }} de {{ season.presentations_count ?? 0 }}</div>
                </div>
              </div>
            </td>
            <td><span class="badge" :class="publicationClass(season.status)">{{ publicationLabel(season.status) }}</span></td>
            <td><span class="badge" :class="periodClass(eventPeriod(season))">{{ eventPeriod(season) }}</span></td>
            <td class="text-end">
              <div class="events-actions">
                <a v-if="['published', 'finished'].includes(season.status)" class="btn btn-info btn-sm" :href="publicUrl(season)" target="_blank" rel="noopener noreferrer" :aria-label="'Ver ficha pública de ' + (season.show?.title || 'evento')">Ver evento</a>
                <button v-else class="btn btn-info btn-sm" type="button" disabled title="Este evento no tiene página pública disponible">Ver evento</button>
                <a v-if="!['finished', 'cancelled'].includes(season.status)" class="btn btn-sm btn-primary" :href="'/admin/events/new?season_id=' + season.id" :aria-label="'Editar ' + (season.show?.title || 'evento')">Editar</a>
                <button v-else class="btn btn-sm btn-primary" type="button" disabled title="Este evento ya no se puede editar">Editar</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="pagination && pagination.last_page > 1" class="card-footer d-flex justify-content-between align-items-center gap-3">
      <button type="button" class="btn btn-sm" :disabled="loading || pagination.current_page <= 1" @click="load(pagination.current_page - 1)">Anterior</button>
      <span class="text-secondary small">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
      <button type="button" class="btn btn-sm" :disabled="loading || pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Siguiente</button>
    </div>
  </div>
</template>
