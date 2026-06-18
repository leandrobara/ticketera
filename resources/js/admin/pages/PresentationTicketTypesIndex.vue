<script setup>
  import { computed, onMounted, ref } from 'vue';
  import PresentationTicketTypeService from '@/admin/services/PresentationTicketTypeService';
  import PresentationTicketTypeModal from '@/admin/components/presentation-ticket-types/PresentationTicketTypeModal.vue';

  // data
  const ticketTypes = ref([]);
  const pagination = ref(null);
  const errorMessage = ref('');
  const isLoading = ref(false);
  const ticketTypeModal = ref(null);

  // computed
  const hasTicketTypes = computed(() => ticketTypes.value.length > 0);
  const totalTicketTypes = computed(() => pagination.value?.total ?? ticketTypes.value.length);

  // methods
  const formatDateTime = (date) => {
    if (!date) {
      return '-';
    }

    return new Intl.DateTimeFormat('es-AR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(date));
  };

  const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency: 'ARS',
      maximumFractionDigits: 0,
    }).format(amount ?? 0);
  };

  const loadTicketTypes = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await PresentationTicketTypeService.getInstance().getPresentationTicketTypes();
      pagination.value = response.data.data;
      ticketTypes.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de tipos de entrada.';
    } finally {
      isLoading.value = false;
    }
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
  onMounted(loadTicketTypes);
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
                <th>Nombre</th>
                <th>Función</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Orden</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="ticketType in ticketTypes" :key="ticketType.id">
                <td>
                  <div class="fw-semibold">{{ ticketType.name }}</div>
                </td>
                <td class="text-secondary">
                  {{ formatDateTime(ticketType.presentation?.starts_at) }}
                </td>
                <td class="text-secondary">
                  {{ formatMoney(ticketType.price) }}
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
