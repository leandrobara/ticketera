<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
  import TicketService from '@/admin/services/TicketService';

  // emits
  const emit = defineEmits(['changed']);

  // data
  const presentation = ref(null);
  const tickets = ref([]);
  const errorMessage = ref('');
  const isLoading = ref(false);
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const cancellingTicketId = ref(null);
  const markingTicketUsedId = ref(null);

  // computed
  const showTitle = computed(() => presentation.value?.show?.title ?? '-');
  const venueName = computed(() => presentation.value?.venue?.name ?? '-');
  const presentationDate = computed(() => formatDateTime(presentation.value?.starts_at));
  const modalTitle = computed(() => `Entradas de ${showTitle.value}`);
  const totalTicketsCount = computed(() => tickets.value.length);
  const validTicketsCount = computed(() => tickets.value.filter((ticket) => ticket.status === 'VALID').length);
  const usedTicketsCount = computed(() => tickets.value.filter((ticket) => ticket.status === 'USED').length);
  const canceledTicketsCount = computed(() => tickets.value.filter((ticket) => ticket.status === 'CANCELED').length);

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

  const getBuyerName = (ticket) => {
    const buyer = ticket.order?.buyer;

    if (!buyer) {
      return '-';
    }

    return [buyer.name, buyer.last_name].filter(Boolean).join(' ');
  };

  const getTicketTypeName = (ticket) => {
    return ticket.presentation_ticket_type?.name
      ?? ticket.order_item?.name
      ?? '-';
  };

  const getTicketPrice = (ticket) => {
    return ticket.order_item?.unit_price ?? 0;
  };

  const canCancelTicket = (ticket) => {
    return ticket.status === 'VALID';
  };

  const canMarkTicketUsed = (ticket) => {
    return ticket.status === 'VALID';
  };

  const loadTickets = async () => {
    if (!presentation.value?.id) {
      return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await TicketService.getInstance().getTickets({
        presentation_id: presentation.value.id,
        per_page: 500,
      });
      tickets.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudieron cargar las entradas de la función.';
    } finally {
      isLoading.value = false;
    }
  };

  const open = async (selectedPresentation) => {
    presentation.value = selectedPresentation;
    tickets.value = [];
    errorMessage.value = '';
    cancellingTicketId.value = null;
    markingTicketUsedId.value = null;
    await nextTick();
    modalInstance.value.show();
    await loadTickets();
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
      const response = await TicketService.getInstance().cancelTicket(ticket.id);
      Object.assign(ticket, response.data.data);
      emit('changed');
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
      Object.assign(ticket, response.data.data);
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
            <h5 class="modal-title">{{ modalTitle }}</h5>
            <div class="text-secondary">{{ presentationDate }} · {{ venueName }}</div>
          </div>
          <button class="btn-close" type="button" aria-label="Close" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div class="tickets-summary">
            <div class="tickets-summary-item">
              <div class="tickets-summary-label">Total</div>
              <div class="tickets-summary-value">{{ totalTicketsCount }}</div>
            </div>
            <div class="tickets-summary-item">
              <div class="tickets-summary-label">Válidas</div>
              <div class="tickets-summary-value">{{ validTicketsCount }}</div>
            </div>
            <div class="tickets-summary-item">
              <div class="tickets-summary-label">Usadas</div>
              <div class="tickets-summary-value">{{ usedTicketsCount }}</div>
            </div>
            <div class="tickets-summary-item">
              <div class="tickets-summary-label">Canceladas</div>
              <div class="tickets-summary-value">{{ canceledTicketsCount }}</div>
            </div>
          </div>

          <div v-if="isLoading" class="d-flex align-items-center py-4">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Cargando entradas...</span>
          </div>

          <div v-else-if="tickets.length === 0" class="empty">
            <p class="empty-title">Esta función no tiene entradas</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-vcenter card-table presentation-tickets-table">
              <thead>
                <tr>
                  <th>Entrada</th>
                  <!-- <th>Comprador</th> -->
                  <th>Estado</th>
                  <th>Usada</th>
                  <th>Cancelación</th>
                  <th class="ticket-actions-header"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ticket in tickets" :key="ticket.id">
                  <td class="buyer-cell">
                    <div class="fw-semibold">{{ getBuyerName(ticket) }}</div>
                    <div class="text-secondary">{{ ticket.order?.buyer?.email ?? '-' }}</div>
                    <div class="text-secondary">{{ ticket.order?.buyer?.phone ?? '-' }}</div>
                    <div class="text-secondary">{{ ticket.code }}</div>
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
  .tickets-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--tblr-border-color);
  }

  .tickets-summary-item {
    padding: 0.875rem 1rem;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
    background: var(--tblr-bg-surface-secondary);
  }

  .tickets-summary-label {
    color: var(--tblr-secondary);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .tickets-summary-value {
    color: var(--tblr-body-color);
    font-size: 1.35rem;
    font-weight: 600;
  }

  .presentation-tickets-table {
    table-layout: fixed;
    width: 100%;
  }

  .ticket-info-cell {
    width: 22%;
    word-break: break-word;
  }

  .buyer-cell {
    width: 24%;
    word-break: break-word;
  }

  .ticket-actions-header,
  .ticket-actions-cell {
    width: 260px;
  }

  .presentation-tickets-table.card-table tr td.ticket-actions-cell {
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
    .tickets-summary {
      grid-template-columns: 1fr;
    }
  }
</style>
