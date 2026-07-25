<script setup>
  import { computed, onMounted, ref } from 'vue';
  import SeasonModal from '@/admin/components/seasons/SeasonModal.vue';
  import SeasonService from '@/admin/services/SeasonService';

  // data
  const seasons = ref([]);
  const pagination = ref(null);
  const errorMessage = ref('');
  const isLoading = ref(false);
  const seasonModal = ref(null);
  const showId = new URLSearchParams(window.location.search).get('show_id');

  // computed
  const hasSeasons = computed(() => seasons.value.length > 0);
  const totalSeasons = computed(() => pagination.value?.total ?? seasons.value.length);

  // methods
  const statusLabel = (status) => ({
    draft: 'Borrador',
    published: 'Publicada',
    finished: 'Finalizada',
    cancelled: 'Cancelada',
  })[status] ?? status;

  const statusClass = (status) => ({
    published: 'bg-success-lt',
    finished: 'bg-blue-lt',
    cancelled: 'bg-danger-lt',
  })[status] ?? 'bg-secondary-lt';

  const publicUrl = (season) => {
    return `/shows/${season.id}/${season.show?.slug ?? ''}`;
  };

  const loadSeasons = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await SeasonService.getInstance().getSeasons(
        showId ? { show_id: showId } : {}
      );
      pagination.value = response.data.data;
      seasons.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de temporadas.';
    } finally {
      isLoading.value = false;
    }
  };

  const openSeasonModal = () => {
    seasonModal.value.openForCreate(showId);
  };

  const openUpdateSeasonModal = (season) => {
    seasonModal.value.openForUpdate(season);
  };

  const deleteSeason = async (season) => {
    if (!window.confirm(`¿Eliminar la temporada de "${season.show?.title}" en "${season.venue?.name}"?`)) {
      return;
    }

    try {
      await SeasonService.getInstance().deleteSeason(season.id);
      await loadSeasons();
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar la temporada.';
    }
  };

  // lifecycle
  onMounted(loadSeasons);
</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Listado de temporadas</h1>
        <p class="card-subtitle">{{ totalSeasons }} registros</p>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button" @click="openSeasonModal">
          Crear una nueva temporada
        </button>
      </div>
    </div>
  </div>

  <div class="row row-cards mt-3">
    <div class="col-12">
      <div class="card">
        <div v-if="errorMessage" class="alert alert-danger m-3 mb-0" role="alert">
          {{ errorMessage }}
        </div>
        <div v-if="isLoading" class="card-body">Cargando temporadas...</div>
        <div v-else-if="!hasSeasons" class="empty">
          <p class="empty-title">No hay temporadas cargadas</p>
        </div>
        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Show</th>
                <th>Espacio</th>
                <th>Nombre interno</th>
                <th>Funciones</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="season in seasons" :key="season.id">
                <td class="fw-semibold">{{ season.show?.title }}</td>
                <td>{{ season.venue?.name }}</td>
                <td class="text-secondary">{{ season.name || '-' }}</td>
                <td>{{ season.presentations_count }}</td>
                <td>
                  <span class="badge" :class="statusClass(season.status)">
                    {{ statusLabel(season.status) }}
                  </span>
                </td>
                <td class="text-end">
                  <div class="btn-list justify-content-end flex-nowrap">
                    <a
                      v-if="['published', 'finished'].includes(season.status)"
                      class="btn btn-sm btn-outline-secondary"
                      :href="publicUrl(season)"
                      target="_blank"
                      rel="noreferrer"
                    >
                      Ver obra
                    </a>
                    <a
                      class="btn btn-sm btn-outline-secondary"
                      :href="`/admin/presentations?season_id=${season.id}`"
                    >
                      Funciones
                    </a>
                    <button class="btn btn-sm btn-outline-primary" type="button" @click="openUpdateSeasonModal(season)">
                      Editar
                    </button>
                    <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteSeason(season)">
                      Eliminar
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <SeasonModal ref="seasonModal" @saved="loadSeasons" />
</template>
