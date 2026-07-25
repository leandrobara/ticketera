<script setup>
  import { computed, onMounted, ref } from 'vue';
  import ShowService from '@/admin/services/ShowService';
  import ShowCreditsModal from '@/admin/components/shows/ShowCreditsModal.vue';
  import ShowFaqsModal from '@/admin/components/shows/ShowFaqsModal.vue';
  import ShowImagesModal from '@/admin/components/shows/ShowImagesModal.vue';
  import ShowLinksModal from '@/admin/components/shows/ShowLinksModal.vue';
  import ShowModal from '@/admin/components/shows/ShowModal.vue';
  import ShowPerformanceHistoryModal from '@/admin/components/shows/ShowPerformanceHistoryModal.vue';
  import ShowSocialLinksModal from '@/admin/components/shows/ShowSocialLinksModal.vue';

  // data
  const shows = ref([]);
  const pagination = ref(null);
  const errorMessage = ref('');
  const isLoading = ref(false);
  const showModal = ref(null);
  const showPerformanceHistoryModal = ref(null);
  const showCreditsModal = ref(null);
  const showFaqsModal = ref(null);
  const showImagesModal = ref(null);
  const showLinksModal = ref(null);
  const showSocialLinksModal = ref(null);

  // computed
  const hasShows = computed(() => shows.value.length > 0);
  const totalShows = computed(() => pagination.value?.total ?? shows.value.length);

  // methods
  const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency: 'ARS',
      maximumFractionDigits: 0,
    }).format(amount ?? 0);
  };

  const formatServiceFee = (show) => {
    if (show.service_fee_type === 'percentage') {
      return Number(show.service_fee_percentage ?? 0).toLocaleString('es-AR') + '%';
    }

    return formatMoney(show.service_fee_fixed_amount);
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

  const openShowModal = () => {
    showModal.value.openForCreate();
  };

  const openUpdateShowModal = (show) => {
    showModal.value.openForUpdate(show);
  };

  const openShowCreditsModal = (show) => {
    showCreditsModal.value.open(show);
  };

  const openShowPerformanceHistoryModal = (show) => {
    showPerformanceHistoryModal.value.open(show);
  };

  const openShowImagesModal = (show) => {
    showImagesModal.value.open(show);
  };

  const openShowLinksModal = (show) => {
    showLinksModal.value.open(show);
  };

  const openShowFaqsModal = (show) => {
    showFaqsModal.value.open(show);
  };

  const openShowSocialLinksModal = (show) => {
    showSocialLinksModal.value.open(show);
  };

  const deleteShow = async (show) => {
    if (!window.confirm(`¿Eliminar el show "${show.title}"?`)) {
      return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
      await ShowService.getInstance().deleteShow(show.id);
      await loadShows();
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar el show.';
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
        <button class="btn btn-success" type="button" @click="openShowModal">
          Crear un nuevo show
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
                <th>Título</th>
                <th>Slug</th>
                <th>Fee</th>
                <th>Temporadas</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="show in shows" :key="show.id">
                <td>
                  <div class="fw-semibold">{{ show.title }}</div>
                  <div v-if="show.genre" class="text-secondary small">
                    {{ show.genre }}
                  </div>
                </td>
                <td class="text-secondary">
                  {{ show.slug || '-' }}
                </td>
                <td class="text-secondary">
                  {{ formatServiceFee(show) }}
                </td>
                <td class="text-secondary">{{ show.seasons_count ?? 0 }}</td>
                <td class="text-end">
                  <div class="btn-list justify-content-end flex-nowrap">
                    <a
                      class="btn btn-sm btn-outline-secondary"
                      :href="`/admin/seasons?show_id=${show.id}`"
                    >
                      Temporadas
                    </a>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="openShowCreditsModal(show)">
                      Créditos
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="openShowPerformanceHistoryModal(show)">
                      Histórico
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="openShowLinksModal(show)">
                      Links
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="openShowImagesModal(show)">
                      Imágenes
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="openShowSocialLinksModal(show)">
                      Redes
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="openShowFaqsModal(show)">
                      FAQs
                    </button>
                    <button class="btn btn-sm btn-outline-primary" type="button" @click="openUpdateShowModal(show)">
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
                    <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteShow(show)">
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

  <ShowModal ref="showModal" @saved="loadShows" />
  <ShowPerformanceHistoryModal ref="showPerformanceHistoryModal" />
  <ShowCreditsModal ref="showCreditsModal" />
  <ShowFaqsModal ref="showFaqsModal" @saved="loadShows" />
  <ShowImagesModal ref="showImagesModal" @saved="loadShows" />
  <ShowLinksModal ref="showLinksModal" />
  <ShowSocialLinksModal ref="showSocialLinksModal" @saved="loadShows" />
</template>
