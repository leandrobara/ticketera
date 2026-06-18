<script setup>
  import { computed, onMounted, ref } from 'vue';
  import BuyerService from '@/admin/services/BuyerService';
  import BuyerModal from '@/admin/components/buyers/BuyerModal.vue';

  // data
  const buyers = ref([]);
  const pagination = ref(null);
  const errorMessage = ref('');
  const isLoading = ref(false);
  const buyerModal = ref(null);

  // computed
  const hasBuyers = computed(() => buyers.value.length > 0);
  const totalBuyers = computed(() => pagination.value?.total ?? buyers.value.length);

  // methods
  const getFullName = (buyer) => {
    return [buyer.name, buyer.last_name].filter(Boolean).join(' ');
  };

  const loadBuyers = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await BuyerService.getInstance().getBuyers();
      pagination.value = response.data.data;
      buyers.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de compradores.';
    } finally {
      isLoading.value = false;
    }
  };

  const openBuyerModal = () => {
    buyerModal.value.openForCreate();
  };

  const openUpdateBuyerModal = (buyer) => {
    buyerModal.value.openForUpdate(buyer);
  };

  const deleteBuyer = async (buyer) => {
    if (!window.confirm(`¿Eliminar el comprador "${getFullName(buyer)}"?`)) {
      return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
      await BuyerService.getInstance().deleteBuyer(buyer.id);
      await loadBuyers();
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar el comprador.';
      isLoading.value = false;
    }
  };

  // lifecycle
  onMounted(loadBuyers);
</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Listado de compradores</h1>
        <p class="card-subtitle">
          {{ totalBuyers }} registros
        </p>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button" @click="openBuyerModal">
          Crear un nuevo comprador
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
            <span>Cargando compradores...</span>
          </div>
        </div>

        <div v-else-if="!hasBuyers" class="empty">
          <p class="empty-title">No hay compradores cargados</p>
          <p class="empty-subtitle text-secondary">
            Cuando crees tu primer comprador, va a aparecer en este listado.
          </p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>DNI</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="buyer in buyers" :key="buyer.id">
                <td>
                  <div class="fw-semibold">{{ getFullName(buyer) }}</div>
                </td>
                <td class="text-secondary">
                  {{ buyer.email }}
                </td>
                <td class="text-secondary">
                  {{ buyer.phone || '-' }}
                </td>
                <td class="text-secondary">
                  {{ buyer.dni || '-' }}
                </td>
                <td class="text-end">
                  <div class="btn-list justify-content-end flex-nowrap">
                    <button class="btn btn-sm btn-outline-primary" type="button" @click="openUpdateBuyerModal(buyer)">
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
                    <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteBuyer(buyer)">
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

  <BuyerModal ref="buyerModal" @saved="loadBuyers" />
</template>
