<script setup>
  import { computed, onMounted, ref } from 'vue';
  import ShowService from '@/admin/services/ShowService';

  // data
  const shows = ref([]);
  const pagination = ref(null);
  const errorMessage = ref('');
  const isLoading = ref(false);

  // computed
  const hasShows = computed(() => shows.value.length > 0);
  const totalShows = computed(() => pagination.value?.total ?? shows.value.length);

  // methods
  const formatDate = (date) => {
    if (!date) {
      return '-';
    }

    return new Intl.DateTimeFormat('es-AR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).format(new Date(date));
  };

  const loadShows = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await ShowService.getInstance().getShows();
      pagination.value = response.data.data;
      shows.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de shows.';
    } finally {
      isLoading.value = false;
    }
  };

  // lifecycle
  onMounted(loadShows);
</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Listado de shows</h1>
				<p class="card-subtitle">
					{{ totalShows }} registros
				</p>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button">
          Crear un nuevo show
        </button>
      </div>
    </div>
  </div>

  <div class="row row-cards mt-3">
    <div class="col-12">
      <div class="card">
        <!-- <div class="card-header">
          <div>
            <h2 class="card-title">Shows creados</h2>
            <p class="card-subtitle">
              {{ totalShows }} registros
            </p>
          </div>
        </div> -->

        <div v-if="errorMessage" class="alert alert-danger m-3 mb-0" role="alert">
          {{ errorMessage }}
        </div>

        <div v-if="isLoading" class="card-body">
          <div class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Cargando shows...</span>
          </div>
        </div>

        <div v-else-if="!hasShows" class="empty">
          <p class="empty-title">No hay shows cargados</p>
          <p class="empty-subtitle text-secondary">
            Cuando crees tu primer show, va a aparecer en este listado.
          </p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Slug</th>
                <th>Estado</th>
                <th>Publicado</th>
                <th>Creado</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="show in shows" :key="show.id">
                <td class="text-secondary">
                  #{{ show.id }}
                </td>
                <td>
                  <div class="fw-semibold">{{ show.title }}</div>
                  <div v-if="show.genre" class="text-secondary small">
                    {{ show.genre }}
                  </div>
                </td>
                <td class="text-secondary">
                  {{ show.slug || '-' }}
                </td>
                <td>
                  <span class="badge" :class="show.status === 'published' ? 'bg-success-lt' : 'bg-secondary-lt'">
                    {{ show.status }}
                  </span>
                </td>
                <td class="text-secondary">
                  {{ formatDate(show.published_at) }}
                </td>
                <td class="text-secondary">
                  {{ formatDate(show.created_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
