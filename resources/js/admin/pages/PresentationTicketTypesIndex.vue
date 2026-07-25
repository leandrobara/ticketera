<script setup>
  import { computed, onMounted, ref } from 'vue';
  import PresentationTicketTypeService from '@/admin/services/PresentationTicketTypeService';
  import ShowService from '@/admin/services/ShowService';
  import PresentationTicketTypeModal from '@/admin/components/presentation-ticket-types/PresentationTicketTypeModal.vue';
  import { formatDateTime } from '@/admin/helpers/DateTimeFormatHelper';

  // data
  const ticketTypes = ref([]);
  const shows = ref([]);
  const selectedShowId = ref('');
  const pagination = ref(null);
  const errorMessage = ref('');
  const isLoading = ref(false);
  const ticketTypeModal = ref(null);

  // computed
  const hasTicketTypes = computed(() => ticketTypes.value.length > 0);
  const totalTicketTypes = computed(() => pagination.value?.total ?? ticketTypes.value.length);

  // methods
  const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency: 'ARS',
      maximumFractionDigits: 0,
    }).format(amount ?? 0);
  };

  const getPromotionLabel = (ticketType) => {
    if (!ticketType.promotion_type) {
      return '-';
    }

    if (ticketType.promotion_type === 'percent_discount') {
      return `${ticketType.promotion_name ?? 'Promoción'} - ${Number(ticketType.promotion_value)}%`;
    }

    if (ticketType.promotion_type === 'fixed_discount') {
      return `${ticketType.promotion_name ?? 'Promoción'} - ${formatMoney(ticketType.promotion_value)}`;
    }

    return `${ticketType.promotion_name ?? 'Promoción'} - ${ticketType.promotion_bundle_quantity}x${ticketType.promotion_pay_quantity}`;
  };

  const loadTicketTypes = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await PresentationTicketTypeService.getInstance().getPresentationTicketTypes({
        show_id: selectedShowId.value || undefined,
      });
      pagination.value = response.data.data;
      ticketTypes.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de tipos de entrada.';
    } finally {
      isLoading.value = false;
    }
  };

  const loadShows = async () => {
    try {
      const response = await ShowService.getInstance().getShows({ per_page: 1000 });
      shows.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de shows.';
    }
  };

  const filterByShow = () => {
    loadTicketTypes();
  };

  const openTicketTypeModal = () => {
    ticketTypeModal.value.openForCreate();
  };

  const openUpdateTicketTypeModal = (ticketType) => {
    ticketTypeModal.value.openForUpdate(ticketType);
  };

  const deleteTicketType = async (ticketType) => {
    if (!window.confirm(`¿Eliminar el tipo de entrada "${ticketType.name}"?`)) {
      return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
      await PresentationTicketTypeService.getInstance().deletePresentationTicketType(ticketType.id);
      await loadTicketTypes();
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar el tipo de entrada.';
      isLoading.value = false;
    }
  };

  // lifecycle
  onMounted(async () => {
    await Promise.all([
      loadShows(),
      loadTicketTypes(),
    ]);
  });
</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Listado de tipos de entrada</h1>
        <p class="card-subtitle">
          {{ totalTicketTypes }} registros
        </p>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button" @click="openTicketTypeModal">
          Crear un nuevo tipo de entrada
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

        <div class="card-body border-bottom">
          <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
              <label class="form-label" for="ticket-types-show-filter">Show</label>
              <select
                id="ticket-types-show-filter"
                v-model="selectedShowId"
                class="form-select"
                @change="filterByShow"
              >
                <option value="">Todos los shows</option>
                <option v-for="show in shows" :key="show.id" :value="show.id">
                  {{ show.title }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <div v-if="isLoading" class="card-body">
          <div class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Cargando tipos de entrada...</span>
          </div>
        </div>

        <div v-else-if="!hasTicketTypes" class="empty">
          <p class="empty-title">No hay tipos de entrada cargados</p>
          <p class="empty-subtitle text-secondary">
            Cuando crees tu primer tipo de entrada, va a aparecer en este listado.
          </p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Función</th>
                <th>Tipo de entrada</th>
                <th>Precio</th>
                <th>Promoción</th>
                <th>Stock</th>
                <th>Orden</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="ticketType in ticketTypes" :key="ticketType.id">
                <td>
                  <div class="fw-semibold">
                    {{ ticketType.presentation?.season?.show?.title ?? '-' }}
                  </div>
                  <div class="text-secondary">
                    {{ formatDateTime(ticketType.presentation?.starts_at) }}
                  </div>
                </td>
                <td>
                  <div class="fw-semibold">{{ ticketType.name }}</div>
                </td>
                <td class="text-secondary">
                  {{ formatMoney(ticketType.price) }}
                </td>
                <td>
                  <span
                    v-if="ticketType.promotion_type"
                    class="badge"
                    :class="ticketType.promotion_is_active ? 'bg-blue-lt' : 'bg-secondary-lt'"
                  >
                    {{ getPromotionLabel(ticketType) }}
                  </span>
                  <span v-else class="text-secondary">-</span>
                  <div v-if="ticketType.promotion_access_code" class="text-secondary small mt-1">
                    Código: {{ ticketType.promotion_access_code }}
                  </div>
                </td>
                <td class="text-secondary">
                  {{ ticketType.stock ?? '-' }}
                </td>
                <td class="text-secondary">
                  {{ ticketType.sort_order ?? '-' }}
                </td>
                <td>
                  <span class="badge" :class="ticketType.is_active ? 'bg-success-lt' : 'bg-secondary-lt'">
                    {{ ticketType.is_active ? 'Activa' : 'Inactiva' }}
                  </span>
                </td>
                <td class="text-end">
                  <div class="btn-list justify-content-end flex-nowrap">
                    <button class="btn btn-sm btn-outline-primary" type="button" @click="openUpdateTicketTypeModal(ticketType)">
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
                    <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteTicketType(ticketType)">
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

  <PresentationTicketTypeModal ref="ticketTypeModal" @saved="loadTicketTypes" />
</template>
