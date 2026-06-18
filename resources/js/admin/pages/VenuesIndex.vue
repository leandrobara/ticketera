<script setup>
  import { computed, onMounted, ref } from 'vue';
  import VenueService from '@/admin/services/VenueService';
  import VenueModal from '@/admin/components/venues/VenueModal.vue';

  // data
  const venues = ref([]);
  const pagination = ref(null);
  const errorMessage = ref('');
  const isLoading = ref(false);
  const venueModal = ref(null);

  // computed
  const hasVenues = computed(() => venues.value.length > 0);
  const totalVenues = computed(() => pagination.value?.total ?? venues.value.length);

  // methods
  const loadVenues = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await VenueService.getInstance().getVenues();
      pagination.value = response.data.data;
      venues.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de espacios.';
    } finally {
      isLoading.value = false;
    }
  };

  const openVenueModal = () => {
    venueModal.value.openForCreate();
  };

  const openUpdateVenueModal = (venue) => {
    venueModal.value.openForUpdate(venue);
  };

  const deleteVenue = async (venue) => {
    if (!window.confirm(`¿Eliminar el espacio "${venue.name}"?`)) {
      return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
      await VenueService.getInstance().deleteVenue(venue.id);
      await loadVenues();
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar el espacio.';
      isLoading.value = false;
    }
  };

  // lifecycle
  onMounted(loadVenues);
</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Listado de espacios</h1>
        <p class="card-subtitle">
          {{ totalVenues }} registros
        </p>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button" @click="openVenueModal">
          Crear un nuevo espacio
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

        <div v-if="isLoading" class="card-body">
          <div class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Cargando espacios...</span>
          </div>
        </div>

        <div v-else-if="!hasVenues" class="empty">
          <p class="empty-title">No hay espacios cargados</p>
          <p class="empty-subtitle text-secondary">
            Cuando crees tu primer espacio, va a aparecer en este listado.
          </p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Dirección</th>
                <th>Capacidad</th>
                <th>Características</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="venue in venues" :key="venue.id">
                <td>
                  <div class="fw-semibold">
                    {{ venue.name }}
                  </div>
                </td>
                <td class="text-secondary">
                  <div>
                    {{ venue.address || '-' }}
                  </div>
                  <div v-if="venue.neighborhood || venue.city" class="text-secondary small">
                    {{ [venue.neighborhood, venue.city].filter(Boolean).join(', ') }}
                  </div>
                </td>
                <td class="text-secondary">
                  {{ venue.capacity ?? '-' }}
                </td>
                <td>
                  <div class="badges-list">
                    <span v-if="venue.has_bar" class="badge bg-blue-lt">Bar</span>
                    <span v-if="venue.has_parking" class="badge bg-green-lt">Parking</span>
                    <span v-if="venue.is_accessible" class="badge bg-purple-lt">Accesible</span>
                    <span v-if="!venue.has_bar && !venue.has_parking && !venue.is_accessible" class="text-secondary">
                      -
                    </span>
                  </div>
                </td>
                <td class="text-end">
                  <div class="btn-list justify-content-end flex-nowrap">
                    <button class="btn btn-sm btn-outline-primary" type="button" @click="openUpdateVenueModal(venue)">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="icon"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                        fill="none"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                      >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                        <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                        <path d="M16 5l3 3" />
                      </svg>
                      Editar
                    </button>
                    <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteVenue(venue)">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="icon"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                        fill="none"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                      >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 7h16" />
                        <path d="M10 11v6" />
                        <path d="M14 11v6" />
                        <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                        <path d="M9 7v-3h6v3" />
                      </svg>
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

  <VenueModal ref="venueModal" @saved="loadVenues" />
</template>
