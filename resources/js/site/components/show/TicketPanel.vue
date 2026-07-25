<script setup>
  import { computed } from 'vue';
  import MercadoPagoNotice from '@/site/components/payment/MercadoPagoNotice.vue';
  import { formatDateTime } from '@/site/helpers/DateTimeFormatHelper';

  // props
  const props = defineProps({
    show: {
      type: Object,
      required: true,
    },
    presentations: {
      type: Array,
      default: () => [],
    },
    selectedPresentationId: {
      type: [Number, String, null],
      default: null,
    },
    selectedTicketTypeId: {
      type: [Number, String, null],
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
    isLoadingPreview: {
      type: Boolean,
      default: false,
    },
  });

  // emits
  const emit = defineEmits([
    'checkout',
    'clear',
    'select-presentation',
    'select-ticket-type',
    'update-quantity',
  ]);

  // computed
  const presentations = computed(() => props.presentations);
  const selectedPresentation = computed(() => presentations.value.find((presentation) => {
    return Number(presentation.id) === Number(props.selectedPresentationId);
  }) ?? null);
  const ticketTypes = computed(() => selectedPresentation.value?.tickets ?? []);
  const selectedTicketType = computed(() => ticketTypes.value.find((ticketType) => {
    return Number(ticketType.id) === Number(props.selectedTicketTypeId);
  }) ?? null);
  const hasSelection = computed(() => Boolean(selectedPresentation.value && selectedTicketType.value && props.quantity > 0));
  const totalQuantityLabel = computed(() => `${props.quantity} ${props.quantity === 1 ? 'entrada' : 'entradas'}`);
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
  const unitTicketAmount = computed(() => {
    if (!props.quantity) {
      return 0;
    }

    return Number(selectedTicketType.value?.price ?? 0);
  });
  const discountLabel = computed(() => {
    return promotionLabel(selectedTicketType.value);
  });

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

  const hasUnitPromotionPrice = (ticketType) => {
    return ['percent_discount', 'fixed_discount'].includes(ticketType?.promotion?.type)
      && ticketType?.promotion?.promotional_price !== null
      && ticketType?.promotion?.promotional_price !== undefined;
  };

  const selectTicketType = (ticketType) => {
    if (!ticketType.has_stock) {
      return;
    }

    emit('select-ticket-type', ticketType.id);
  };

  const updateQuantity = (ticketType, step) => {
    if (Number(ticketType.id) !== Number(props.selectedTicketTypeId)) {
      emit('select-ticket-type', ticketType.id);
      emit('update-quantity', 1);
      return;
    }

    const maxQuantity = Number(ticketType.max_purchase_quantity ?? 0);
    const nextQuantity = Math.max(0, Math.min(maxQuantity, props.quantity + step));
    emit('update-quantity', nextQuantity);
  };

  const isPresentationFinished = (presentation) => {
    return presentation.is_finished;
  };
</script>

<template>
  <aside id="tickets" class="ticket-card">
    <div class="ticket-panel ticket-availability">
      <h2>Comprar entradas</h2>

      <div class="performance-field">
        <label for="performance-date">Fecha y hora</label>
        <select
          id="performance-date"
          :value="selectedPresentationId"
          @change="emit('select-presentation', $event.target.value)"
        >
          <option value="">Seleccionar fecha...</option>
          <option
            v-for="presentation in presentations"
            :key="presentation.id"
            :value="presentation.id"
            :disabled="isPresentationFinished(presentation)"
          >
            {{ formatDateTime(presentation.starts_at) }}
          </option>
        </select>
      </div>

      <div class="ticket-options">
        <p class="ticket-section-label">Entradas disponibles</p>

        <article
          v-for="ticketType in ticketTypes"
          :key="ticketType.id"
          class="ticket-tier"
          :class="{
            'is-selected': Number(ticketType.id) === Number(selectedTicketTypeId) && quantity > 0,
            'is-sold-out': !ticketType.has_stock,
          }"
          @click="selectTicketType(ticketType)"
        >
          <div>
            <p class="ticket-tier-name">{{ ticketType.name }}</p>
            <p v-if="ticketType.promotion?.name" class="ticket-tier-promo-name">
              {{ ticketType.promotion.name }}
            </p>
            <p v-if="ticketType.promotion?.type" class="ticket-tier-promo-label">
              {{ promotionLabel(ticketType) }}
            </p>
          </div>
          <div class="ticket-tier-side">
            <div v-if="hasUnitPromotionPrice(ticketType)" class="ticket-tier-pricing">
              <del class="ticket-original-price">{{ formatMoney(ticketType.price) }}</del>
              <span class="ticket-price-pill is-promotional">{{ formatMoney(ticketType.promotion.promotional_price) }}</span>
            </div>
            <span v-else class="ticket-price-pill">{{ formatMoney(ticketType.price) }}</span>
            <span v-if="!ticketType.has_stock" class="sold-out-pill">Agotadas</span>
            <div v-else class="ticket-stepper" :aria-label="`Cantidad de entradas ${ticketType.name}`">
              <button type="button" :disabled="Number(ticketType.id) !== Number(selectedTicketTypeId) || quantity === 0" @click.stop="updateQuantity(ticketType, -1)">−</button>
              <span class="ticket-quantity">
                {{ Number(ticketType.id) === Number(selectedTicketTypeId) ? quantity : 0 }}
              </span>
              <button
                type="button"
                :disabled="Number(ticketType.id) === Number(selectedTicketTypeId) && quantity >= Number(ticketType.max_purchase_quantity ?? 0)"
                @click.stop="updateQuantity(ticketType, 1)"
              >+</button>
            </div>
          </div>
        </article>

        <p v-if="selectedPresentation && ticketTypes.length === 0" class="summary-empty">
          Esta función todavía no tiene entradas disponibles.
        </p>
      </div>
    </div>

    <div class="ticket-panel order-summary">
      <div class="summary-head">
        <strong>Resumen</strong>
        <span>{{ hasSelection ? totalQuantityLabel : 'Sin entradas' }}</span>
      </div>

      <p v-if="selectedPresentation" class="summary-performance">
        <strong>Fecha y hora</strong>
        <span>{{ formatDateTime(selectedPresentation.starts_at) }}</span>
      </p>

      <div class="summary-body">
        <div v-if="hasSelection" class="summary-row">
          <div>
            <p class="summary-item-title">{{ selectedTicketType.name }}</p>
            <p class="summary-item-meta">{{ quantity }} × {{ formatMoney(unitTicketAmount) }}</p>
          </div>
          <div class="summary-item-actions">
            <strong class="summary-price">{{ isLoadingPreview ? '-' : formatMoney(subtotalAmount) }}</strong>
            <button class="summary-remove" type="button" :aria-label="`Quitar ${selectedTicketType.name}`" @click="emit('clear')">×</button>
          </div>
        </div>
        <div v-if="hasSelection && discountAmount > 0" class="summary-row summary-adjustment">
          <div>
            <p class="summary-item-title">{{ discountLabel }}</p>
          </div>
          <strong class="summary-price summary-discount">-{{ formatMoney(discountAmount) }}</strong>
        </div>
        <div v-if="hasSelection && serviceFeeAmount > 0" class="summary-row summary-adjustment">
          <div>
            <p class="summary-item-title">{{ serviceFeeLabel }}</p>
            <p class="summary-item-meta">{{ paidQuantity }} × {{ formatMoney(serviceFeeUnitAmount) }}</p>
          </div>
          <strong class="summary-price">{{ isLoadingPreview ? '-' : formatMoney(serviceFeeAmount) }}</strong>
        </div>
        <p v-if="!hasSelection" class="summary-empty">Seleccioná una entrada para continuar.</p>
      </div>

      <div class="summary-total">
        <span>Total</span>
        <span class="summary-total-price">{{ hasSelection && !isLoadingPreview ? formatMoney(totalAmount) : '-' }}</span>
      </div>

      <MercadoPagoNotice />

      <button class="ticket-buy" type="button" :disabled="!hasSelection || isLoadingPreview" @click="emit('checkout')">
        {{ hasSelection ? 'Continuar con la compra' : 'Seleccioná una entrada' }}
      </button>
    </div>
  </aside>
</template>
