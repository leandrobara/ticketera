<script setup>
  import { computed, onMounted, ref } from 'vue';
  import PresentationService from '@/admin/services/PresentationService';
  import PresentationModal from '@/admin/components/presentations/PresentationModal.vue';
  import PresentationTicketsModal from '@/admin/components/presentations/PresentationTicketsModal.vue';
  import { formatDateTime } from '@/admin/helpers/DateTimeFormatHelper';

  // data
  const presentations = ref([]);
  const pagination = ref(null);
  const errorMessage = ref('');
  const isLoading = ref(false);
  const presentationModal = ref(null);
  const presentationTicketsModal = ref(null);
  const seasonId = new URLSearchParams(window.location.search).get('season_id');

  // computed
  const hasPresentations = computed(() => presentations.value.length > 0);
  const totalPresentations = computed(() => pagination.value?.total ?? presentations.value.length);

  // methods
  const getStatusLabel = (status) => {
    const labels = {
      draft: 'Borrador',
      published: 'Publicada',
      sold_out: 'Agotada',
      cancelled: 'Cancelada',
    };

    return labels[status] ?? status;
  };

  const getStatusClass = (status) => {
    const classes = {
      draft: 'bg-secondary-lt',
      published: 'bg-success-lt',
      sold_out: 'bg-warning-lt',
      cancelled: 'bg-danger-lt',
    };

    return classes[status] ?? 'bg-secondary-lt';
  };

  const getRemainingTickets = (presentation) => {
    return Math.max(0, presentation.capacity - presentation.sold_tickets_count);
  };

  const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency: 'ARS',
      maximumFractionDigits: 0,
    }).format(Number(amount ?? 0));
  };

  const loadPresentations = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await PresentationService.getInstance().getPresentations(
        seasonId ? { season_id: seasonId } : {}
      );
      pagination.value = response.data.data;
      presentations.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de funciones.';
    } finally {
      isLoading.value = false;
    }
  };

  const openPresentationModal = () => {
    presentationModal.value.openForCreate(seasonId);
  };

  const openUpdatePresentationModal = (presentation) => {
    presentationModal.value.openForUpdate(presentation);
  };

  const openPresentationTicketsModal = (presentation) => {
    presentationTicketsModal.value.open(presentation);
  };

  const deletePresentation = async (presentation) => {
    if (!window.confirm('¿Eliminar esta función?')) {
      return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
      await PresentationService.getInstance().deletePresentation(presentation.id);
      await loadPresentations();
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar la función.';
      isLoading.value = false;
    }
  };

  // lifecycle
  onMounted(loadPresentations);
</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Listado de funciones</h1>
        <p class="card-subtitle">
          {{ totalPresentations }} registros
        </p>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button" @click="openPresentationModal">
          Crear una nueva función
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
            <span>Cargando funciones...</span>
          </div>
        </div>

        <div v-else-if="!hasPresentations" class="empty">
          <p class="empty-title">No hay funciones cargadas</p>
          <p class="empty-subtitle text-secondary">
            Cuando crees tu primera función, va a aparecer en este listado.
          </p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Show</th>
                <th>Espacio</th>
                <th>Vendidas</th>
                <th>Recaudado</th>
                <th>Restantes</th>
                <th>Capacidad</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="presentation in presentations" :key="presentation.id">
                <td>
                  <div class="fw-semibold">
                    {{ presentation.season?.show?.title ?? '-' }}
                  </div>
                  <div class="text-secondary">
                    {{ formatDateTime(presentation.starts_at) }}
                  </div>
                </td>
                <td class="text-secondary">
                  {{ presentation.season?.venue?.name ?? '-' }}
                </td>
                <td class="text-secondary">
                  {{ presentation.sold_tickets_count }}
                </td>
                <td class="text-secondary">
                  {{ formatMoney(presentation.revenue_amount) }}
                </td>
                <td class="text-secondary">
                  {{ getRemainingTickets(presentation) }}
                </td>
                <td class="text-secondary">
                  {{ presentation.capacity }}
                </td>
                <td>
                  <span class="badge" :class="getStatusClass(presentation.status)">
                    {{ getStatusLabel(presentation.status) }}
                  </span>
                </td>
                <td class="text-end">
                  <div class="btn-list justify-content-end flex-nowrap">
                    <button
                      class="btn btn-sm btn-outline-secondary"
                      type="button"
                      @click="openPresentationTicketsModal(presentation)"
                    >
                      Ver entradas
                    </button>
                    <button
                      class="btn btn-sm btn-outline-primary"
                      type="button"
                      @click="openUpdatePresentationModal(presentation)"
                    >
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
                    <button class="btn btn-sm btn-outline-danger" type="button" @click="deletePresentation(presentation)">
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

  <PresentationModal ref="presentationModal" @saved="loadPresentations" />
  <PresentationTicketsModal ref="presentationTicketsModal" @changed="loadPresentations" />
</template>
