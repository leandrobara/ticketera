<script setup>
  import { computed, reactive, ref, watch } from 'vue';
  import { getStoredAttribution } from '@/site/helpers/AttributionHelper';
  import MercadoPagoNotice from '@/site/components/payment/MercadoPagoNotice.vue';
  import { formatDateTime } from '@/site/helpers/DateTimeFormatHelper';
  import CheckoutService from '@/site/services/CheckoutService';

  // props
  const props = defineProps({
    isOpen: {
      type: Boolean,
      default: false,
    },
    show: {
      type: Object,
      required: true,
    },
    presentation: {
      type: Object,
      default: null,
    },
    ticketType: {
      type: Object,
      default: null,
    },
    quantity: {
      type: Number,
      default: 0,
    },
    pricePreview: {
      type: Object,
      default: null,
    },
    promoCode: {
      type: String,
      default: null,
    },
  });

  // emits
  const emit = defineEmits(['close']);

  // data
  const form = reactive({
    name: '',
    last_name: '',
    phone: '',
    dni: '',
    email: '',
    email_repeat: '',
  });
  const errors = ref({});
  const errorMessage = ref('');
  const isSubmitting = ref(false);

  // computed
  const subtotalAmount = computed(() => Number(props.pricePreview?.subtotal_amount ?? 0));
  const discountAmount = computed(() => Number(props.pricePreview?.discount_amount ?? 0));
  const serviceFeeAmount = computed(() => Number(props.pricePreview?.service_fee_total_amount ?? 0));
  const paidQuantity = computed(() => Number(props.pricePreview?.paid_quantity ?? props.quantity));
  const serviceFeeUnitAmount = computed(() => {
    if (paidQuantity.value === 0) {
      return 0;
    }

    return serviceFeeAmount.value / paidQuantity.value;
  });
  const serviceFeeLabel = computed(() => {
    return props.pricePreview?.service_fee_minimum_applied
      ? 'Cargo mínimo por servicio'
      : 'Cargo por servicio';
  });
  const totalAmount = computed(() => Number(props.pricePreview?.total_amount ?? 0));
  const unitTicketAmount = computed(() => Number(props.ticketType?.price ?? 0));
  const discountLabel = computed(() => promotionLabel(props.ticketType));
  const requiredMessage = 'Este campo es obligatorio.';

  // methods
  const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency: 'ARS',
      maximumFractionDigits: 0,
    }).format(Number(amount ?? 0));
  };

  const formatNumber = (amount) => {
    return new Intl.NumberFormat('es-AR', {
      maximumFractionDigits: 2,
    }).format(Number(amount ?? 0));
  };

  const promotionLabel = (ticketType) => {
    if (!ticketType?.promotion?.type) {
      return 'Promoción';
    }

    if (ticketType.promotion.type === 'percent_discount') {
      return `Promoción ${formatNumber(ticketType.promotion.value)}%`;
    }

    if (ticketType.promotion.type === 'fixed_discount') {
      return `Promoción -$${formatNumber(ticketType.promotion.value)}`;
    }

    if (ticketType.promotion.type === 'buy_x_get_y') {
      return `Promoción ${ticketType.promotion.bundle_quantity}x${ticketType.promotion.pay_quantity}`;
    }

    return 'Promoción';
  };

  const validate = () => {
    const nextErrors = {};

    if (!form.name.trim()) {
      nextErrors.name = requiredMessage;
    }

    if (!form.last_name.trim()) {
      nextErrors.last_name = requiredMessage;
    }

    if (!form.phone.trim()) {
      nextErrors.phone = requiredMessage;
    }

    if (!form.dni.trim()) {
      nextErrors.dni = requiredMessage;
    }

    if (!form.email.trim()) {
      nextErrors.email = requiredMessage;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
      nextErrors.email = 'Ingresá un email válido.';
    }

    if (!form.email_repeat.trim()) {
      nextErrors.email_repeat = requiredMessage;
    } else if (form.email_repeat.trim() !== form.email.trim()) {
      nextErrors.email_repeat = 'Los emails tienen que coincidir.';
    }

    errors.value = nextErrors;
    return Object.keys(nextErrors).length === 0;
  };

  const getCheckoutUrl = (preference) => {
    return preference.init_point || preference.sandbox_init_point;
  };

  const submit = async () => {
    if (!validate()) {
      return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
      const payload = {
        presentation_ticket_type_id: props.ticketType.id,
        quantity: props.quantity,
        buyer: {
          name: form.name.trim(),
          last_name: form.last_name.trim(),
          email: form.email.trim(),
          phone: form.phone.trim(),
          dni: form.dni.trim(),
        },
      };

      if (props.promoCode) {
        payload.promo_code = props.promoCode;
      }

      const attribution = getStoredAttribution();

      if (attribution) {
        payload.attribution = attribution;
      }

      const response = await CheckoutService.getInstance().createOrder(payload);

      const checkoutUrl = getCheckoutUrl(response.data.data.preference);

      if (checkoutUrl) {
        window.location.href = checkoutUrl;
        return;
      }

      errorMessage.value = 'No se pudo iniciar el pago.';
    } catch (error) {
      const fields = error.response?.data?.errors ?? error.response?.data?.error?.fields;
      errors.value = fields ?? {};
      errorMessage.value = error.response?.data?.message
        ?? error.response?.data?.error?.message
        ?? 'No se pudo crear la orden.';
    } finally {
      isSubmitting.value = false;
    }
  };

  const fieldError = (field) => {
    if (Array.isArray(errors.value[field])) {
      return errors.value[field][0] || '';
    }

    return errors.value[field] || '';
  };

  // lifecycle
  watch(() => props.isOpen, (isOpen) => {
    document.body.classList.toggle('modal-open', isOpen);

    if (isOpen) {
      errors.value = {};
      errorMessage.value = '';
    }
  });
</script>

<template>
  <div class="checkout-modal" :class="{ 'is-open': isOpen }" :aria-hidden="!isOpen" @click.self="emit('close')">
    <div class="checkout-dialog" role="dialog" aria-modal="true" aria-labelledby="checkout-title">
      <header class="checkout-header">
        <div class="checkout-title">
          <h2 id="checkout-title">{{ show.title }}</h2>
          <p>Completá tus datos para continuar con la compra.</p>
        </div>
        <button class="checkout-close" type="button" aria-label="Cerrar" @click="emit('close')">×</button>
      </header>

      <div class="checkout-body">
        <form id="checkout-form" class="checkout-form" novalidate @submit.prevent="submit">
          <h3>Completá tus datos</h3>

          <div v-if="errorMessage" class="checkout-error">{{ errorMessage }}</div>

          <div class="checkout-fields">
            <div class="field">
              <label for="buyer-name">Nombre <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="buyer-name" v-model="form.name" autocomplete="given-name" :class="{ 'is-invalid': fieldError('name') }" required>
              <span v-if="fieldError('name')" class="field-error">{{ fieldError('name') }}</span>
            </div>

            <div class="field">
              <label for="buyer-last-name">Apellido <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="buyer-last-name" v-model="form.last_name" autocomplete="family-name" :class="{ 'is-invalid': fieldError('last_name') }" required>
              <span v-if="fieldError('last_name')" class="field-error">{{ fieldError('last_name') }}</span>
            </div>

            <div class="field">
              <label for="buyer-phone">Celular <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="buyer-phone" v-model="form.phone" autocomplete="tel" inputmode="tel" :class="{ 'is-invalid': fieldError('phone') }" required>
              <span v-if="fieldError('phone')" class="field-error">{{ fieldError('phone') }}</span>
            </div>

            <div class="field">
              <label for="buyer-dni">DNI <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="buyer-dni" v-model="form.dni" inputmode="numeric" :class="{ 'is-invalid': fieldError('dni') }" required>
              <span v-if="fieldError('dni')" class="field-error">{{ fieldError('dni') }}</span>
            </div>

            <div class="field full">
              <label for="buyer-email">Email <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="buyer-email" v-model="form.email" autocomplete="email" type="email" :class="{ 'is-invalid': fieldError('email') }" required>
              <span v-if="fieldError('email')" class="field-error">{{ fieldError('email') }}</span>
            </div>

            <div class="field full">
              <label for="buyer-email-repeat">Repetir email <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="buyer-email-repeat" v-model="form.email_repeat" autocomplete="email" type="email" :class="{ 'is-invalid': fieldError('email_repeat') }" required>
              <span v-if="fieldError('email_repeat')" class="field-error">{{ fieldError('email_repeat') }}</span>
            </div>
          </div>

          <footer class="checkout-actions checkout-actions-desktop">
            <button class="checkout-continue" type="submit" :disabled="isSubmitting">
              {{ isSubmitting ? 'Iniciando pago...' : 'Guardar y continuar el pago' }}
            </button>
            <p>Serás redirigido a Mercado Pago para completar el pago de forma segura.</p>
          </footer>
        </form>

        <aside class="checkout-modal-summary">
          <h3>Resumen de la compra</h3>
          <p class="modal-summary-performance">
            <strong>Fecha y hora</strong>
            <span>{{ formatDateTime(presentation?.starts_at) }}</span>
          </p>
          <div class="modal-summary-list">
            <div class="modal-summary-row">
              <div>
                <p class="modal-summary-name">{{ ticketType?.name }}</p>
                <p class="modal-summary-meta">{{ quantity }} × {{ formatMoney(unitTicketAmount) }}</p>
              </div>
              <span class="modal-summary-price">{{ formatMoney(subtotalAmount) }}</span>
            </div>
            <div v-if="discountAmount > 0" class="modal-summary-row">
              <div>
                <p class="modal-summary-name">{{ discountLabel }}</p>
              </div>
              <span class="modal-summary-price summary-discount">-{{ formatMoney(discountAmount) }}</span>
            </div>
            <div v-if="serviceFeeAmount > 0" class="modal-summary-row">
              <div>
                <p class="modal-summary-name">{{ serviceFeeLabel }}</p>
                <p class="modal-summary-meta">{{ paidQuantity }} × {{ formatMoney(serviceFeeUnitAmount) }}</p>
              </div>
              <span class="modal-summary-price">{{ formatMoney(serviceFeeAmount) }}</span>
            </div>
          </div>
          <div class="modal-summary-total">
            <span>Total</span>
            <strong>{{ formatMoney(totalAmount) }}</strong>
          </div>
          <MercadoPagoNotice />
        </aside>
      </div>

      <footer class="checkout-actions-mobile">
        <button class="checkout-continue" type="submit" form="checkout-form" :disabled="isSubmitting">
          {{ isSubmitting ? 'Iniciando pago...' : 'Guardar y continuar el pago' }}
        </button>
      </footer>
    </div>
  </div>
</template>
