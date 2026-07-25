<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
  import { formatDateTime } from '@/admin/helpers/DateTimeFormatHelper';
  import OrderItemService from '@/admin/services/OrderItemService';
  import OrderService from '@/admin/services/OrderService';
  import PresentationService from '@/admin/services/PresentationService';
  import PresentationTicketTypeService from '@/admin/services/PresentationTicketTypeService';

  // emits
  const emit = defineEmits(['saved']);

  // data
  const presentations = ref([]);
  const ticketTypes = ref([]);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const isLoadingOptions = ref(false);
  const isLoadingTicketTypes = ref(false);
  const isCalculatingAmounts = ref(false);
  const pricing = ref({
    unit_price: '0.000000',
    unit_service_fee: '0.000000',
    service_fee_type: null,
    service_fee_fixed_amount: null,
    service_fee_percentage: null,
    service_fee_base_amount: '0.000000',
    subtotal_amount: '0.000000',
    discount_amount: '0.000000',
    service_fee_total_amount: '0.000000',
    total_amount: '0.000000',
    promotion: null,
  });
  let calculateAmountsTimer = null;
  let calculateAmountsRequestId = 0;
  const form = reactive({
    buyer: {
      dni: '',
      name: '',
      email: '',
      phone: '',
      last_name: '',
    },
    presentation_id: '',
    presentation_ticket_type_id: '',
    promo_code: '',
    quantity: 1,
    payment_method: 'CASH',
    notes: '',
  });

  // computed
  const selectedTicketType = computed(() => {
    return ticketTypes.value.find((ticketType) => Number(ticketType.id) === Number(form.presentation_ticket_type_id));
  });

  const selectedPresentation = computed(() => {
    return presentations.value.find((presentation) => Number(presentation.id) === Number(form.presentation_id));
  });

  const ticketTypeAvailableTickets = computed(() => {
    if (!selectedTicketType.value || selectedTicketType.value.stock === null) {
      return null;
    }

    return Math.max(
      0,
      selectedTicketType.value.stock - Number(selectedTicketType.value.sold_tickets_count ?? 0)
    );
  });

  const presentationAvailableTickets = computed(() => {
    if (!selectedPresentation.value) {
      return null;
    }

    return Math.max(
      0,
      selectedPresentation.value.capacity - Number(selectedPresentation.value.sold_tickets_count ?? 0)
    );
  });

  const maxAssignableTickets = computed(() => {
    const limits = [
      ticketTypeAvailableTickets.value,
      presentationAvailableTickets.value,
    ].filter((value) => value !== null);

    return limits.length ? Math.min(...limits) : null;
  });

  const unitPrice = computed(() => {
    return pricing.value.unit_price;
  });

  const appliedPromotion = computed(() => pricing.value.promotion ?? null);
  const selectedTicketTypeRequiresPromoCode = computed(() => {
    return Boolean(
      selectedTicketType.value?.promotion_is_active
      && selectedTicketType.value?.promotion_type
      && selectedTicketType.value?.promotion_access_code
      && form.payment_method !== 'FREE'
    );
  });
  const subtotalAmount = computed(() => pricing.value.subtotal_amount);
  const discountAmount = computed(() => pricing.value.discount_amount);
  const serviceFeeAmount = computed(() => pricing.value.service_fee_total_amount);
  const totalAmount = computed(() => pricing.value.total_amount);

  const submitLabel = computed(() => isSubmitting.value ? 'Creando entrada...' : 'Crear entrada');

  // methods
  const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency: 'ARS',
      maximumFractionDigits: 6,
    }).format(amount ?? 0);
  };

  const resetPricing = () => {
    pricing.value = {
      unit_price: '0.000000',
      unit_service_fee: '0.000000',
      service_fee_type: null,
      service_fee_fixed_amount: null,
      service_fee_percentage: null,
      service_fee_base_amount: '0.000000',
      subtotal_amount: '0.000000',
      discount_amount: '0.000000',
      service_fee_total_amount: '0.000000',
      total_amount: '0.000000',
      promotion: null,
    };
  };

  const getPromotionLabel = (promotion) => {
    const promotionName = promotion.promotion_name ?? promotion.name ?? 'Promoción';
    const promotionType = promotion.promotion_type ?? promotion.type;

    if (promotionType === 'percent_discount') {
      return `${promotionName} - ${Number(promotion.promotion_value ?? promotion.value)}%`;
    }

    if (promotionType === 'fixed_discount') {
      return `${promotionName} - ${formatMoney(promotion.promotion_value ?? promotion.value)} por entrada`;
    }

    return `${promotionName} - ${promotion.promotion_bundle_quantity ?? promotion.bundle_quantity}x${promotion.promotion_pay_quantity ?? promotion.pay_quantity}`;
  };

  const calculateAmounts = async () => {
    if (!form.presentation_ticket_type_id || Number(form.quantity) < 1) {
      resetPricing();
      return;
    }

    const requestId = ++calculateAmountsRequestId;
    isCalculatingAmounts.value = true;

    try {
      const payload = {
        presentation_ticket_type_id: Number(form.presentation_ticket_type_id),
        quantity: Number(form.quantity),
        payment_method: form.payment_method,
      };

      if (selectedTicketTypeRequiresPromoCode.value) {
        payload.promo_code = nullable(form.promo_code.trim().toLowerCase());
      }

      const response = await OrderItemService.getInstance().calculateAmounts(payload);

      if (requestId === calculateAmountsRequestId) {
        pricing.value = response.data.data;
        delete fieldErrors.value.promo_code;
      }
    } catch (error) {
      if (requestId === calculateAmountsRequestId) {
        resetPricing();
        fieldErrors.value = error.response?.data?.errors
          ?? error.response?.data?.error?.fields
          ?? {};
        errorMessage.value = 'No se pudo calcular el importe de la entrada.';
      }
    } finally {
      if (requestId === calculateAmountsRequestId) {
        isCalculatingAmounts.value = false;
      }
    }
  };

  const scheduleAmountsCalculation = () => {
    window.clearTimeout(calculateAmountsTimer);
    calculateAmountsTimer = window.setTimeout(calculateAmounts, 200);
  };

  const resetForm = () => {
    form.buyer.dni = '';
    form.buyer.name = '';
    form.buyer.email = '';
    form.buyer.phone = '';
    form.buyer.last_name = '';
    form.presentation_id = '';
    form.presentation_ticket_type_id = '';
    form.promo_code = '';
    form.quantity = 1;
    form.payment_method = 'CASH';
    form.notes = '';
    ticketTypes.value = [];
    resetPricing();
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const nullable = (value) => {
    return value === '' ? null : value;
  };

  const getPayload = () => {
    return {
      buyer: {
        dni: nullable(form.buyer.dni),
        name: form.buyer.name,
        email: form.buyer.email,
        phone: nullable(form.buyer.phone),
        last_name: nullable(form.buyer.last_name),
      },
      presentation_ticket_type_id: Number(form.presentation_ticket_type_id),
      promo_code: selectedTicketTypeRequiresPromoCode.value
        ? nullable(form.promo_code.trim().toLowerCase())
        : null,
      quantity: Number(form.quantity),
      payment_method: form.payment_method,
      notes: nullable(form.notes),
    };
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const getPresentationLabel = (presentation) => {
    const showTitle = presentation.season?.show?.title ?? `Temporada #${presentation.season_id}`;
    return `${showTitle} - ${formatDateTime(presentation.starts_at)}`;
  };

  const getTicketTypeLabel = (ticketType) => {
    if (ticketType.stock === null) {
      return `${ticketType.name} - ${formatMoney(ticketType.price)} - según capacidad`;
    }

    const available = Math.max(0, ticketType.stock - Number(ticketType.sold_tickets_count ?? 0));
    return `${ticketType.name} - ${formatMoney(ticketType.price)} - ${available} disponibles`;
  };

  const loadOptions = async () => {
    isLoadingOptions.value = true;

    try {
      const presentationsResponse = await PresentationService.getInstance().getPresentations();
      presentations.value = presentationsResponse.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudieron cargar las funciones.';
    } finally {
      isLoadingOptions.value = false;
    }
  };

  const loadTicketTypes = async () => {
    form.presentation_ticket_type_id = '';
    form.promo_code = '';
    ticketTypes.value = [];
    resetPricing();

    if (!form.presentation_id) {
      return;
    }

    isLoadingTicketTypes.value = true;

    try {
      const response = await PresentationTicketTypeService.getInstance().getPresentationTicketTypes({
        presentation_id: form.presentation_id,
        is_active: 1,
      });

      ticketTypes.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudieron cargar los tipos de entrada.';
    } finally {
      isLoadingTicketTypes.value = false;
    }
  };

  const showModal = async () => {
    await loadOptions();
    await nextTick();
    modalInstance.value.show();
  };

  const open = async () => {
    resetForm();
    await showModal();
  };

  const close = () => {
    modalInstance.value.hide();
  };

  const submit = async () => {
    fieldErrors.value = {};
    errorMessage.value = '';

    if (selectedTicketTypeRequiresPromoCode.value && !form.promo_code.trim()) {
      fieldErrors.value = {
        promo_code: ['El código de promoción es obligatorio.'],
      };
      return;
    }

    if (maxAssignableTickets.value !== null && Number(form.quantity) > maxAssignableTickets.value) {
      fieldErrors.value = {
        quantity: [`Solo hay ${maxAssignableTickets.value} entradas disponibles.`],
      };
      return;
    }

    isSubmitting.value = true;

    try {
      await OrderService.getInstance().createOrder(getPayload());
      emit('saved');
      close();
    } catch (error) {
      fieldErrors.value = error.response?.data?.errors
        ?? error.response?.data?.error?.fields
        ?? {};
      errorMessage.value = error.response?.data?.message
        ?? error.response?.data?.error?.message
        ?? 'No se pudo crear la entrada.';
    } finally {
      isSubmitting.value = false;
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
    window.clearTimeout(calculateAmountsTimer);
    modalInstance.value?.dispose();
  });

  watch(
    () => [
      form.presentation_ticket_type_id,
      form.promo_code,
      form.quantity,
      form.payment_method,
    ],
    () => {
      if (!selectedTicketTypeRequiresPromoCode.value) {
        form.promo_code = '';
      }

      scheduleAmountsCalculation();
    }
  );
</script>

<template>
  <div ref="modalElement" class="modal modal-blur fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <form class="modal-content" @submit.prevent="submit">
        <div class="modal-header">
          <h5 class="modal-title">Nueva entrada</h5>
          <button class="btn-close" type="button" aria-label="Close" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div v-if="isLoadingOptions" class="d-flex align-items-center mb-3">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Cargando datos...</span>
          </div>

          <div class="hr-text">Comprador</div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="order-buyer-name">Nombre</label>
                <input
                  id="order-buyer-name"
                  v-model.trim="form.buyer.name"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('buyer.name') }"
                  type="text"
                  maxlength="160"
                  required
                >
                <div v-if="getFieldError('buyer.name')" class="invalid-feedback">
                  {{ getFieldError('buyer.name') }}
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="order-buyer-last-name">Apellido</label>
                <input
                  id="order-buyer-last-name"
                  v-model.trim="form.buyer.last_name"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('buyer.last_name') }"
                  type="text"
                  maxlength="160"
                >
                <div v-if="getFieldError('buyer.last_name')" class="invalid-feedback">
                  {{ getFieldError('buyer.last_name') }}
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="order-buyer-email">Email</label>
                <input
                  id="order-buyer-email"
                  v-model.trim="form.buyer.email"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('buyer.email') }"
                  type="email"
                  maxlength="255"
                  required
                >
                <div v-if="getFieldError('buyer.email')" class="invalid-feedback">
                  {{ getFieldError('buyer.email') }}
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="order-buyer-phone">Teléfono</label>
                <input
                  id="order-buyer-phone"
                  v-model.trim="form.buyer.phone"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('buyer.phone') }"
                  type="text"
                  maxlength="40"
                >
                <div v-if="getFieldError('buyer.phone')" class="invalid-feedback">
                  {{ getFieldError('buyer.phone') }}
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="order-buyer-dni">DNI</label>
            <input
              id="order-buyer-dni"
              v-model.trim="form.buyer.dni"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('buyer.dni') }"
              type="text"
              maxlength="20"
            >
            <div v-if="getFieldError('buyer.dni')" class="invalid-feedback">
              {{ getFieldError('buyer.dni') }}
            </div>
          </div>

          <div class="hr-text">Entrada</div>

          <div class="mb-3">
            <label class="form-label" for="order-presentation-id">Función</label>
            <select
              id="order-presentation-id"
              v-model="form.presentation_id"
              class="form-select"
              required
              @change="loadTicketTypes"
            >
              <option value="">Seleccionar función</option>
              <option v-for="presentation in presentations" :key="presentation.id" :value="presentation.id">
                {{ getPresentationLabel(presentation) }}
              </option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="order-ticket-type-id">Tipo de entrada</label>
            <select
              id="order-ticket-type-id"
              v-model="form.presentation_ticket_type_id"
              class="form-select"
              :class="{ 'is-invalid': getFieldError('presentation_ticket_type_id') }"
              :disabled="!form.presentation_id || isLoadingTicketTypes"
              required
            >
              <option value="">
                {{ isLoadingTicketTypes ? 'Cargando tipos...' : 'Seleccionar tipo de entrada' }}
              </option>
              <option v-for="ticketType in ticketTypes" :key="ticketType.id" :value="ticketType.id">
                {{ getTicketTypeLabel(ticketType) }}
              </option>
            </select>
            <div v-if="getFieldError('presentation_ticket_type_id')" class="invalid-feedback">
              {{ getFieldError('presentation_ticket_type_id') }}
            </div>
          </div>

          <div v-if="selectedTicketTypeRequiresPromoCode" class="mb-3">
            <label class="form-label" for="order-promo-code">Código de promoción</label>
            <input
              id="order-promo-code"
              v-model.trim="form.promo_code"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('promo_code') }"
              type="text"
              maxlength="80"
              autocomplete="off"
            >
            <div v-if="getFieldError('promo_code')" class="invalid-feedback">
              {{ getFieldError('promo_code') }}
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="order-quantity">Cantidad</label>
                <input
                  id="order-quantity"
                  v-model.number="form.quantity"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('quantity') }"
                  type="number"
                  min="1"
                  :max="maxAssignableTickets"
                  :disabled="maxAssignableTickets === 0"
                  required
                >
                <div v-if="getFieldError('quantity')" class="invalid-feedback">
                  {{ getFieldError('quantity') }}
                </div>
                <div v-else-if="maxAssignableTickets === 0" class="form-hint text-danger">
                  No hay entradas disponibles.
                </div>
                <div v-else-if="maxAssignableTickets !== null" class="form-hint">
                  Máximo disponible: {{ maxAssignableTickets }}
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="order-payment-method">Forma de pago</label>
                <select
                  id="order-payment-method"
                  v-model="form.payment_method"
                  class="form-select"
                  :class="{ 'is-invalid': getFieldError('payment_method') }"
                  required
                >
                  <option value="CASH">Efectivo</option>
                  <option value="BANK_TRANSFER">Transferencia</option>
                  <option value="FREE">Gratis / cortesía</option>
                </select>
                <div v-if="getFieldError('payment_method')" class="invalid-feedback">
                  {{ getFieldError('payment_method') }}
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="order-notes">Notas</label>
            <textarea
              id="order-notes"
              v-model.trim="form.notes"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('notes') }"
              rows="3"
            ></textarea>
            <div v-if="getFieldError('notes')" class="invalid-feedback">
              {{ getFieldError('notes') }}
            </div>
          </div>

          <div class="hr-text">Resumen</div>
          <div v-if="isCalculatingAmounts" class="d-flex align-items-center mb-3">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Calculando importe...</span>
          </div>
          <div v-if="appliedPromotion" class="alert alert-info py-2" role="alert">
            Promoción aplicada: {{ getPromotionLabel(appliedPromotion) }}
          </div>
          <div class="row g-2">
            <div class="col-sm-3">
              <div class="text-secondary">Precio unitario</div>
              <div class="fw-semibold">{{ formatMoney(unitPrice) }}</div>
            </div>
            <div class="col-sm-3">
              <div class="text-secondary">Subtotal</div>
              <div class="fw-semibold">{{ formatMoney(subtotalAmount) }}</div>
            </div>
            <div class="col-sm-3">
              <div class="text-secondary">Descuento</div>
              <div class="fw-semibold">{{ formatMoney(discountAmount) }}</div>
            </div>
            <div class="col-sm-3">
              <div class="text-secondary">Fee plataforma</div>
              <div class="fw-semibold">{{ formatMoney(serviceFeeAmount) }}</div>
            </div>
            <div class="col-sm-3">
              <div class="text-secondary">Total</div>
              <div class="fw-semibold">{{ formatMoney(totalAmount) }}</div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-link link-secondary" type="button" @click="close">
            Cancelar
          </button>
          <button
            class="btn btn-success ms-auto"
            type="submit"
            :disabled="isSubmitting || isLoadingOptions || isCalculatingAmounts || maxAssignableTickets === 0"
          >
            <span
              v-if="isSubmitting"
              aria-hidden="true"
              class="spinner-border spinner-border-sm me-2"
            ></span>
            {{ submitLabel }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
