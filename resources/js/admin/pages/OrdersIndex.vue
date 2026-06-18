<script setup>
  import { computed, onMounted, ref } from 'vue';
  import OrderService from '@/admin/services/OrderService';
  import OrderModal from '@/admin/components/orders/OrderModal.vue';
  import OrderTicketsModal from '@/admin/components/orders/OrderTicketsModal.vue';

  // data
  const orders = ref([]);
  const pagination = ref(null);
  const errorMessage = ref('');
  const isLoading = ref(false);
  const cancellingOrderId = ref(null);
  const orderModal = ref(null);
  const orderTicketsModal = ref(null);

  // computed
  const hasOrders = computed(() => orders.value.length > 0);
  const totalOrders = computed(() => pagination.value?.total ?? orders.value.length);

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

  const getBuyerName = (order) => {
    if (!order.buyer) {
      return '-';
    }

    return [order.buyer.name, order.buyer.last_name].filter(Boolean).join(' ');
  };

  const getPresentationLabel = (order) => {
    if (!order.presentation) {
      return '-';
    }

    return formatDateTime(order.presentation.starts_at);
  };

  const getShowTitle = (order) => {
    return order.presentation?.show?.title ?? `Show #${order.show_id}`;
  };

  const getPaymentMethodLabel = (paymentMethod) => {
    const labels = {
      CASH: 'Efectivo',
      BANK_TRANSFER: 'Transferencia',
      FREE: 'Gratis / cortesía',
      MERCADO_PAGO: 'MercadoPago',
      OTHER: 'Otro',
    };

    return labels[paymentMethod] ?? paymentMethod;
  };

  const getStatusLabel = (status) => {
    const labels = {
      PENDING: 'Pendiente',
      APPROVED: 'Aprobada',
      REJECTED: 'Rechazada',
      IN_PROCESS: 'En proceso',
      CANCELED: 'Cancelada',
      EXPIRED: 'Vencida',
      REFUNDED: 'Devuelta',
    };

    return labels[status] ?? status;
  };

  const getStatusClass = (status) => {
    if (status === 'APPROVED') {
      return 'bg-success-lt';
    }

    if (['REJECTED', 'CANCELED', 'EXPIRED'].includes(status)) {
      return 'bg-danger-lt';
    }

    return 'bg-secondary-lt';
  };

  const hasValidTickets = (order) => {
    return (order.tickets ?? []).some((ticket) => ticket.status === 'VALID');
  };

  const hasUsedTickets = (order) => {
    return (order.tickets ?? []).some((ticket) => ticket.status === 'USED');
  };

  const canCancelOrder = (order) => {
    return order.status !== 'CANCELED' && hasValidTickets(order) && !hasUsedTickets(order);
  };

  const getTicketsSummary = (order) => {
    const tickets = order.tickets ?? [];
    const valid = tickets.filter((ticket) => ticket.status === 'VALID').length;
    const used = tickets.filter((ticket) => ticket.status === 'USED').length;
    const canceled = tickets.filter((ticket) => ticket.status === 'CANCELED').length;

    return `${valid} válidas <br>
      ${used} usadas <br>
      ${canceled} canceladas
    `;
  };

  const loadOrders = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await OrderService.getInstance().getOrders();
      pagination.value = response.data.data;
      orders.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de entradas.';
    } finally {
      isLoading.value = false;
    }
  };

  const openOrderModal = () => {
    orderModal.value.open();
  };

  const openOrderTicketsModal = (order) => {
    orderTicketsModal.value.open(order);
  };

  const cancelOrder = async (order) => {
    if (!window.confirm(`¿Cancelar la orden "${order.code}" y todas sus entradas válidas?`)) {
      return;
    }

    cancellingOrderId.value = order.id;
    errorMessage.value = '';

    try {
      await OrderService.getInstance().cancelOrder(order.id);
      await loadOrders();
    } catch (error) {
      errorMessage.value = error.response?.data?.message
        ?? error.response?.data?.error?.message
        ?? 'No se pudo cancelar la orden.';
    } finally {
      cancellingOrderId.value = null;
    }
  };

  // lifecycle
  onMounted(loadOrders);
</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Listado de entradas</h1>
        <p class="card-subtitle">
          {{ totalOrders }} registros
        </p>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button" @click="openOrderModal">
          Nueva entrada
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
            <span>Cargando entradas...</span>
          </div>
        </div>

        <div v-else-if="!hasOrders" class="empty">
          <p class="empty-title">No hay entradas creadas</p>
          <p class="empty-subtitle text-secondary">
            Cuando asignes tu primera entrada, va a aparecer en este listado.
          </p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Entrada</th>
                <th>Funcion</th>
                <th>Comprador</th>
                <th>Total</th>
                <th>Estado</th>
                <th>Creada</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in orders" :key="order.id">
                <td>
                  <div class="fw-semibold">{{ order.code }}</div>
                  <div class="text-secondary">Cantidad: {{ order.total_quantity }}</div>
                  <div class="text-secondary">Total: {{ formatMoney(order.total_amount) }}</div>
                  <div class="text-secondary">Pago: {{ getPaymentMethodLabel(order.payment_method) }}</div>
                </td>
                <td>
                  <div class="fw-semibold">{{ getShowTitle(order) }}</div>
                  <div class="text-secondary">{{ getPresentationLabel(order) }}</div>
                </td>
                <td>
                  <div class="fw-semibold">{{ getBuyerName(order) }}</div>
                  <div class="text-secondary">{{ order.buyer?.email ?? '-' }}</div>
                </td>
                <td class="text-secondary">
                  <div v-html="getTicketsSummary(order)" />
                </td>
                <td>
                  <span class="badge" :class="getStatusClass(order.status)">
                    {{ getStatusLabel(order.status) }}
                  </span>
                </td>
                <td class="text-secondary">
                  {{ formatDateTime(order.created_at) }}
                </td>
                <td class="text-end">
                  <div class="btn-list justify-content-end flex-nowrap">
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="openOrderTicketsModal(order)">
                      Ver tickets
                    </button>
                    <button
                      v-if="canCancelOrder(order)"
                      class="btn btn-sm btn-outline-danger"
                      type="button"
                      :disabled="cancellingOrderId === order.id"
                      @click="cancelOrder(order)"
                    >
                      {{ cancellingOrderId === order.id ? 'Cancelando...' : 'Cancelar orden' }}
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

  <OrderModal ref="orderModal" @saved="loadOrders" />
  <OrderTicketsModal ref="orderTicketsModal" @changed="loadOrders" />
</template>
