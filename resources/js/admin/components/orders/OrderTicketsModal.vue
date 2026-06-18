<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
  import TicketService from '@/admin/services/TicketService';

  // emits
  const emit = defineEmits(['changed']);

  // data
  const order = ref(null);
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const cancellingTicketId = ref(null);
  const markingTicketUsedId = ref(null);

  // computed
  const tickets = computed(() => order.value?.tickets ?? []);
  const orderCode = computed(() => order.value?.code ?? '-');
  const showTitle = computed(() => order.value?.presentation?.show?.title ?? '-');
  const presentationDate = computed(() => formatDateTime(order.value?.presentation?.starts_at));
  const buyerName = computed(() => {
    const buyer = order.value?.buyer;

    if (!buyer) {
      return '-';
    }

    return [buyer.name, buyer.last_name].filter(Boolean).join(' ');
  });

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

  const getTicketStatusLabel = (status) => {
    const labels = {
      VALID: 'Válida',
      USED: 'Usada',
      CANCELED: 'Cancelada',
    };

    return labels[status] ?? status;
  };

  const getTicketStatusClass = (status) => {
    if (status === 'VALID') {
      return 'bg-success-lt';
    }

    if (status === 'USED') {
      return 'bg-blue-lt';
    }

    return 'bg-danger-lt';
  };

  const canCancelTicket = (ticket) => {
    return ticket.status === 'VALID';
  };

  const canMarkTicketUsed = (ticket) => {
    return ticket.status === 'VALID';
  };

  const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency: 'ARS',
      maximumFractionDigits: 0,
    }).format(amount ?? 0);
  };

  const getTicketTypeName = (ticket) => {
    return ticket.presentation_ticket_type?.name
      ?? ticket.order_item?.name
      ?? '-';
  };

  const getTicketPrice = (ticket) => {
    return ticket.order_item?.unit_price ?? 0;
  };

  const open = async (selectedOrder) => {
    order.value = selectedOrder;
    errorMessage.value = '';
    cancellingTicketId.value = null;
    markingTicketUsedId.value = null;
    await nextTick();
    modalInstance.value.show();
  };

  const close = () => {
    modalInstance.value.hide();
  };

  const cancelTicket = async (ticket) => {
    if (!window.confirm(`¿Cancelar la entrada "${ticket.code}"?`)) {
      return;
    }

    cancellingTicketId.value = ticket.id;
    errorMessage.value = '';

    try {
      await TicketService.getInstance().cancelTicket(ticket.id);
      emit('changed');
      close();
    } catch (error) {
      errorMessage.value = error.response?.data?.message
        ?? error.response?.data?.error?.message
        ?? 'No se pudo cancelar la entrada.';
    } finally {
      cancellingTicketId.value = null;
    }
  };

  const markTicketUsed = async (ticket) => {
    if (!window.confirm(`¿Marcar la entrada "${ticket.code}" como usada?`)) {
      return;
    }

    markingTicketUsedId.value = ticket.id;
    errorMessage.value = '';

    try {
      const response = await TicketService.getInstance().markTicketUsed(ticket.id);
      const updatedTicket = response.data.data;
      Object.assign(ticket, updatedTicket);
      emit('changed');
    } catch (error) {
      errorMessage.value = error.response?.data?.message
        ?? error.response?.data?.error?.message
        ?? 'No se pudo marcar la entrada como usada.';
    } finally {
      markingTicketUsedId.value = null;
    }
  };

  defineExpose({
    open,
  });

  // lifecycle
  onMounted(() => {
    modalInstance.value = new Modal(modalElement.value);
  });

  onBeforeUnmount(() => {
    modalInstance.value?.dispose();
  });
</script>

<template>
  <div ref="modalElement" class="modal modal-blur fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title">Detalle de entradas</h5>
            <div class="text-secondary">Orden {{ orderCode }}</div>
          </div>
          <button class="btn-close" type="button" aria-label="Close" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div class="order-summary">
            <div class="order-summary-item">
              <div class="order-summary-label">Función</div>
              <div class="order-summary-title">{{ showTitle }}</div>
              <div class="order-summary-meta">{{ presentationDate }}</div>
            </div>

            <div class="order-summary-item">
              <div class="order-summary-label">Comprador</div>
              <div class="order-summary-title">{{ buyerName }}</div>
              <div class="order-summary-meta">{{ order?.buyer?.email ?? '-' }}</div>
              <div class="order-summary-meta">{{ order?.buyer?.phone ?? '-' }}</div>
            </div>
          </div>

          <div v-if="tickets.length === 0" class="empty">
            <p class="empty-title">Esta orden no tiene tickets</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-vcenter card-table order-tickets-table">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Estado</th>
                  <th>Usada</th>
                  <th>Cancelación</th>
                  <th class="ticket-actions-header"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ticket in tickets" :key="ticket.id">
                  <td class="ticket-info-cell">
                    <div class="fw-semibold">{{ ticket.code }}</div>
                    <div class="text-secondary">{{ getTicketTypeName(ticket) }}</div>
                    <div class="text-secondary">{{ formatMoney(getTicketPrice(ticket)) }}</div>
                  </td>
                  <td>
                    <span class="badge" :class="getTicketStatusClass(ticket.status)">
                      {{ getTicketStatusLabel(ticket.status) }}
                    </span>
                  </td>
                  <td class="text-secondary">{{ formatDateTime(ticket.checked_in_at) }}</td>
                  <td class="text-secondary">{{ formatDateTime(ticket.canceled_at) }}</td>
                  <td class="text-end ticket-actions-cell">
                    <div class="ticket-actions">
                      <button
                        v-if="canMarkTicketUsed(ticket)"
                        class="btn btn-sm btn-outline-success"
                        type="button"
                        :disabled="markingTicketUsedId === ticket.id || cancellingTicketId === ticket.id"
                        @click="markTicketUsed(ticket)"
                      >
                        {{ markingTicketUsedId === ticket.id ? 'Marcando...' : 'Marcar usada' }}
                      </button>
                      <button
                        v-if="canCancelTicket(ticket)"
                        class="btn btn-sm btn-outline-danger"
                        type="button"
                        :disabled="cancellingTicketId === ticket.id || markingTicketUsedId === ticket.id"
                        @click="cancelTicket(ticket)"
                      >
                        {{ cancellingTicketId === ticket.id ? 'Cancelando...' : 'Cancelar' }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-link link-secondary" type="button" @click="close">
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
  .order-summary {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 1rem;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--tblr-border-color);
  }

  .order-summary-item {
    min-width: 0;
    padding: 0.875rem 1rem;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
    background: var(--tblr-bg-surface-secondary);
  }

  .order-summary-label {
    margin-bottom: 0.35rem;
    color: var(--tblr-secondary);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .order-summary-title {
    overflow: hidden;
    color: var(--tblr-body-color);
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .order-summary-meta {
    overflow: hidden;
    color: var(--tblr-secondary);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .order-tickets-table {
    table-layout: fixed;
    width: 100%;
  }

  .ticket-info-cell {
    width: 34%;
    word-break: break-word;
  }

  .ticket-actions-header,
  .ticket-actions-cell {
    width: 260px;
  }

  .order-tickets-table.card-table tr td.ticket-actions-cell {
    padding-right: 1rem;
  }

  .ticket-actions {
    display: flex;
    flex-wrap: nowrap;
    gap: 0.5rem;
    justify-content: flex-end;
  }

  .ticket-actions .btn {
    white-space: nowrap;
  }

  @media (max-width: 767.98px) {
    .order-summary {
      grid-template-columns: 1fr;
    }
  }
</style>
