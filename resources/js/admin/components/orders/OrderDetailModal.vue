<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
  import { formatDateTime } from '@/admin/helpers/DateTimeFormatHelper';

  // data
  const order = ref(null);
  const modalElement = ref(null);
  const modalInstance = ref(null);

  // computed
  const orderCode = computed(() => order.value?.code ?? '-');
  const orderItems = computed(() => order.value?.items ?? []);
  const payments = computed(() => order.value?.payments ?? []);
  const buyerName = computed(() => {
    const buyer = order.value?.buyer;

    if (!buyer) {
      return '-';
    }

    return [buyer.name, buyer.last_name].filter(Boolean).join(' ');
  });
  const showTitle = computed(() => {
    return order.value?.presentation?.season?.show?.title
      ?? order.value?.show?.title
      ?? '-';
  });
  const presentationDate = computed(() => formatDateTime(order.value?.presentation?.starts_at));

  // methods
  const formatMoney = (amount, currency = 'ARS') => {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency,
      maximumFractionDigits: 0,
    }).format(amount ?? 0);
  };

  const getOrderStatusLabel = (status) => {
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

  const getPaymentStatusLabel = (status) => {
    const labels = {
      PENDING: 'Pendiente',
      APPROVED: 'Aprobado',
      REJECTED: 'Rechazado',
      IN_PROCESS: 'En proceso',
      CANCELED: 'Cancelado',
      REFUNDED: 'Devuelto',
    };

    return labels[status] ?? status ?? '-';
  };

  const getStatusClass = (status) => {
    if (status === 'APPROVED') {
      return 'bg-success-lt';
    }

    if (status === 'IN_PROCESS') {
      return 'bg-primary-lt';
    }

    if (['REJECTED', 'CANCELED', 'EXPIRED'].includes(status)) {
      return 'bg-danger-lt';
    }

    return 'bg-warning-lt';
  };

  const getServiceFeeRuleLabel = (item) => {
    if (item.service_fee_type === 'percentage') {
      return `${Number(item.service_fee_percentage ?? 0).toLocaleString('es-AR')}% sobre el importe efectivo`;
    }

    if (item.service_fee_type === 'fixed_amount') {
      return `${formatMoney(item.service_fee_fixed_amount)} por unidad pagada`;
    }

    return 'Sin configuración';
  };

  const getPaymentTechnicalRows = (payment) => {
    const rawResponse = payment.raw_response ?? {};
    const additionalInfo = rawResponse.additional_info ?? {};
    const payer = additionalInfo.payer ?? {};
    const items = additionalInfo.items ?? [];

    const rows = [
      ['status', rawResponse.status],
      ['status_detail', rawResponse.status_detail],
      ['external_reference', rawResponse.external_reference],
      ['collector_id', rawResponse.collector_id],
      ['payer.id', rawResponse.payer?.id],
      ['additional_info.payer.first_name', payer.first_name],
      ['additional_info.payer.last_name', payer.last_name],
      ['payment_method_id', rawResponse.payment_method_id],
      ['payment_type_id', rawResponse.payment_type_id],
      ['transaction_amount', rawResponse.transaction_amount],
      ['date_created', rawResponse.date_created],
      ['date_approved', rawResponse.date_approved],
      ['live_mode', rawResponse.live_mode],
    ];

    items.forEach((item, index) => {
      const itemNumber = index + 1;

      rows.push(
        [`additional_info.items.${itemNumber}.id`, item.id],
        [`additional_info.items.${itemNumber}.title`, item.title],
        [`additional_info.items.${itemNumber}.description`, item.description],
        [`additional_info.items.${itemNumber}.quantity`, item.quantity],
        [`additional_info.items.${itemNumber}.unit_price`, item.unit_price],
      );
    });

    return rows.filter((row) => row[1] !== undefined && row[1] !== null && row[1] !== '');
  };

  const open = async (selectedOrder) => {
    order.value = selectedOrder;
    await nextTick();
    modalInstance.value.show();
  };

  const close = () => {
    modalInstance.value.hide();
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
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title">Detalle de orden</h5>
            <div class="text-secondary">{{ orderCode }}</div>
          </div>
          <button class="btn-close" type="button" aria-label="Close" @click="close"></button>
        </div>

        <div class="modal-body">
          <div class="order-summary">
            <div class="order-summary-item">
              <div class="order-summary-label">Orden</div>
              <div class="order-summary-title">{{ orderCode }}</div>
              <div class="order-summary-meta">
                {{ getOrderStatusLabel(order?.status) }}
              </div>
              <div class="order-summary-meta">
                {{ formatMoney(order?.total_amount, order?.currency ?? 'ARS') }} · {{ order?.total_quantity ?? 0 }} entradas
              </div>
            </div>

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

          <h3 class="card-title mb-3">Detalle comercial</h3>

          <div class="payment-list mb-4">
            <div v-for="item in orderItems" :key="item.id" class="payment-item">
              <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div>
                  <div class="fw-semibold">{{ item.name }}</div>
                  <div class="text-secondary">
                    {{ item.quantity }} emitidas · {{ item.paid_quantity }} pagadas
                  </div>
                </div>
                <div class="fw-semibold">{{ formatMoney(item.total_amount, order?.currency ?? 'ARS') }}</div>
              </div>

              <div class="payment-grid">
                <div>
                  <div class="payment-label">Subtotal</div>
                  <div class="payment-value">{{ formatMoney(item.subtotal_amount, order?.currency ?? 'ARS') }}</div>
                </div>
                <div>
                  <div class="payment-label">Descuento</div>
                  <div class="payment-value">{{ formatMoney(item.discount_amount, order?.currency ?? 'ARS') }}</div>
                </div>
                <div>
                  <div class="payment-label">Base efectiva del fee</div>
                  <div class="payment-value">{{ formatMoney(item.service_fee_base_amount, order?.currency ?? 'ARS') }}</div>
                </div>
                <div>
                  <div class="payment-label">Regla de fee</div>
                  <div class="payment-value">{{ getServiceFeeRuleLabel(item) }}</div>
                </div>
                <div>
                  <div class="payment-label">Fee cobrado</div>
                  <div class="payment-value">{{ formatMoney(item.service_fee_total_amount, order?.currency ?? 'ARS') }}</div>
                </div>
                <div>
                  <div class="payment-label">Fee unitario efectivo</div>
                  <div class="payment-value">{{ formatMoney(item.unit_service_fee, order?.currency ?? 'ARS') }}</div>
                </div>
                <div>
                  <div class="payment-label">Fee mínimo por entrada pagada</div>
                  <div class="payment-value">
                    {{ item.service_fee_minimum_unit_amount === null
                      ? 'Sin mínimo'
                      : formatMoney(item.service_fee_minimum_unit_amount, order?.currency ?? 'ARS') }}
                  </div>
                </div>
                <div>
                  <div class="payment-label">Fee mínimo aplicado</div>
                  <div class="payment-value">{{ item.service_fee_minimum_applied ? 'Sí' : 'No' }}</div>
                </div>
              </div>
            </div>
          </div>

          <h3 class="card-title mb-3">Pago</h3>

          <div v-if="payments.length === 0" class="empty">
            <p class="empty-title">Esta orden no tiene pagos registrados</p>
          </div>

          <div v-else class="payment-list">
            <div v-for="payment in payments" :key="payment.id" class="payment-item">
              <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div>
                  <div class="fw-semibold">{{ payment.provider }}</div>
                  <div class="text-secondary">
                    {{ formatMoney(payment.amount, payment.currency ?? 'ARS') }}
                  </div>
                </div>
                <span class="badge" :class="getStatusClass(payment.provider_status)">
                  {{ getPaymentStatusLabel(payment.provider_status) }}
                </span>
              </div>

              <div class="payment-grid">
                <div>
                  <div class="payment-label">ID de pago</div>
                  <div class="payment-value">{{ payment.provider_payment_id ?? '-' }}</div>
                </div>
                <div>
                  <div class="payment-label">ID de preferencia</div>
                  <div class="payment-value">{{ payment.provider_preference_id ?? '-' }}</div>
                </div>
                <div>
                  <div class="payment-label">Fecha de pago</div>
                  <div class="payment-value">{{ formatDateTime(payment.paid_at) }}</div>
                </div>
                <div>
                  <div class="payment-label">Actualizado</div>
                  <div class="payment-value">{{ formatDateTime(payment.updated_at) }}</div>
                </div>
              </div>

              <details v-if="payment.raw_response" class="technical-detail mt-3">
                <summary>Detalle técnico</summary>
                <div class="technical-list mt-3">
                  <div v-for="row in getPaymentTechnicalRows(payment)" :key="row[0]" class="technical-row">
                    <div class="payment-label technical-label">{{ row[0] }}</div>
                    <div class="payment-value technical-value">{{ row[1] }}</div>
                  </div>
                </div>
              </details>
            </div>
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
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--tblr-border-color);
  }

  .order-summary-item,
  .payment-item {
    min-width: 0;
    padding: 0.875rem 1rem;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
    background: var(--tblr-bg-surface-secondary);
  }

  .order-summary-label,
  .payment-label {
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

  .order-summary-meta,
  .payment-value {
    overflow-wrap: anywhere;
    color: var(--tblr-secondary);
  }

  .payment-list {
    display: grid;
    gap: 1rem;
  }

  .payment-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
  }

  .technical-detail summary {
    cursor: pointer;
    color: var(--tblr-primary);
    font-weight: 600;
  }

  .technical-list {
    display: grid;
    gap: 0.75rem;
  }

  .technical-row {
    display: grid;
    grid-template-columns: minmax(0, 260px) minmax(0, 1fr);
    gap: 1rem;
    align-items: start;
  }

  .technical-label {
    overflow-wrap: anywhere;
    line-height: 1.35;
  }

  .technical-value {
    min-width: 0;
    overflow-wrap: anywhere;
    line-height: 1.35;
  }

  @media (max-width: 767.98px) {
    .order-summary,
    .payment-grid,
    .technical-row {
      grid-template-columns: 1fr;
    }
  }
</style>
