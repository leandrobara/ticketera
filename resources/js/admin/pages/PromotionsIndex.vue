<script setup>
  import { computed, onMounted, ref } from 'vue';
  import PromotionService from '@/admin/services/PromotionService';
  import PromotionModal from '@/admin/components/promotions/PromotionModal.vue';

  // data
  const promotions = ref([]);
  const pagination = ref(null);
  const errorMessage = ref('');
  const isLoading = ref(false);
  const promotionModal = ref(null);

  // computed
  const hasPromotions = computed(() => promotions.value.length > 0);
  const totalPromotions = computed(() => pagination.value?.total ?? promotions.value.length);

  // methods
  const getTypeLabel = (type) => {
    const labels = {
      percent_discount: 'Porcentaje',
      fixed_discount: 'Monto fijo',
      buy_x_get_y: 'Por cantidad',
    };

    return labels[type] ?? type;
  };

  const getBenefitLabel = (promotion) => {
    if (promotion.type === 'percent_discount') {
      return `${Number(promotion.value)}%`;
    }

    if (promotion.type === 'fixed_discount') {
      return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        maximumFractionDigits: 6,
      }).format(promotion.value);
    }

    return `${promotion.bundle_quantity}x${promotion.pay_quantity}`;
  };

  const getTicketTypeLabel = (promotion) => {
    const ticketType = promotion.presentation_ticket_type;

    if (!ticketType) {
      return '-';
    }

    const showTitle = ticketType.presentation?.show?.title ?? `Show #${ticketType.show_id}`;
    return `${ticketType.name} - ${showTitle}`;
  };

  const formatDateTime = (date) => {
    if (!date) {
      return 'Sin límite';
    }

    return new Intl.DateTimeFormat('es-AR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(date));
  };

  const loadPromotions = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await PromotionService.getInstance().getPromotions();
      pagination.value = response.data.data;
      promotions.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de promociones.';
    } finally {
      isLoading.value = false;
    }
  };

  const openPromotionModal = () => {
    promotionModal.value.openForCreate();
  };

  const openUpdatePromotionModal = (promotion) => {
    promotionModal.value.openForUpdate(promotion);
  };

  const deletePromotion = async (promotion) => {
    if (!window.confirm(`¿Eliminar la promoción "${promotion.name}"?`)) {
      return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
      await PromotionService.getInstance().deletePromotion(promotion.id);
      await loadPromotions();
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar la promoción.';
      isLoading.value = false;
    }
  };

  // lifecycle
  onMounted(loadPromotions);
</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Listado de promociones</h1>
        <p class="card-subtitle">
          {{ totalPromotions }} registros
        </p>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button" @click="openPromotionModal">
          Crear una nueva promoción
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
            <span>Cargando promociones...</span>
          </div>
        </div>

        <div v-else-if="!hasPromotions" class="empty">
          <p class="empty-title">No hay promociones cargadas</p>
          <p class="empty-subtitle text-secondary">
            Cuando crees tu primera promoción, va a aparecer en este listado.
          </p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Beneficio</th>
                <th>Acceso</th>
                <th>Tipo de entrada</th>
                <th>Vigencia</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="promotion in promotions" :key="promotion.id">
                <td>
                  <div class="fw-semibold">{{ promotion.name }}</div>
                </td>
                <td class="text-secondary">
                  {{ getTypeLabel(promotion.type) }}
                </td>
                <td>
                  <span class="badge bg-blue-lt">{{ getBenefitLabel(promotion) }}</span>
                </td>
                <td>
                  <span v-if="promotion.access_code" class="badge bg-purple-lt">
                    {{ promotion.access_code }}
                  </span>
                  <span v-else class="badge bg-green-lt">Pública</span>
                </td>
                <td class="text-secondary">
                  {{ getTicketTypeLabel(promotion) }}
                </td>
                <td class="text-secondary small">
                  <div>Desde: {{ formatDateTime(promotion.starts_at) }}</div>
                  <div>Hasta: {{ formatDateTime(promotion.ends_at) }}</div>
                </td>
                <td>
                  <span class="badge" :class="promotion.is_active ? 'bg-success-lt' : 'bg-secondary-lt'">
                    {{ promotion.is_active ? 'Activa' : 'Inactiva' }}
                  </span>
                </td>
                <td class="text-end">
                  <div class="btn-list justify-content-end flex-nowrap">
                    <button
                      class="btn btn-sm btn-outline-primary"
                      type="button"
                      @click="openUpdatePromotionModal(promotion)"
                    >
                      Editar
                    </button>
                    <button
                      class="btn btn-sm btn-outline-danger"
                      type="button"
                      @click="deletePromotion(promotion)"
                    >
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

  <PromotionModal ref="promotionModal" @saved="loadPromotions" />
</template>
