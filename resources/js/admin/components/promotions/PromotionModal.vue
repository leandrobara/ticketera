<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import PromotionService from '@/admin/services/PromotionService';
  import PresentationTicketTypeService from '@/admin/services/PresentationTicketTypeService';

  // emits
  const emit = defineEmits(['saved']);

  // data
  const mode = ref('create');
  const currentPromotion = ref(null);
  const ticketTypes = ref([]);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const isLoadingTicketTypes = ref(false);
  const form = reactive({
    name: '',
    type: 'percent_discount',
    value: '',
    bundle_quantity: '',
    pay_quantity: '',
    access_code: '',
    starts_at: '',
    ends_at: '',
    is_active: true,
    presentation_ticket_type_id: '',
  });

  // computed
  const isEditing = computed(() => mode.value === 'update');
  const isBuyXGetY = computed(() => form.type === 'buy_x_get_y');
  const isPercentDiscount = computed(() => form.type === 'percent_discount');
  const modalTitle = computed(() => isEditing.value ? 'Editar promoción' : 'Crear una nueva promoción');
  const submitLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Crear promoción');

  // methods
  const resetForm = () => {
    form.name = '';
    form.type = 'percent_discount';
    form.value = '';
    form.bundle_quantity = '';
    form.pay_quantity = '';
    form.access_code = '';
    form.starts_at = '';
    form.ends_at = '';
    form.is_active = true;
    form.presentation_ticket_type_id = '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const toDateTimeLocal = (value) => {
    if (!value) {
      return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
  };

  const fillForm = (promotion) => {
    form.name = promotion.name ?? '';
    form.type = promotion.type ?? 'percent_discount';
    form.value = promotion.value ?? '';
    form.bundle_quantity = promotion.bundle_quantity ?? '';
    form.pay_quantity = promotion.pay_quantity ?? '';
    form.access_code = promotion.access_code ?? '';
    form.starts_at = toDateTimeLocal(promotion.starts_at);
    form.ends_at = toDateTimeLocal(promotion.ends_at);
    form.is_active = Boolean(promotion.is_active);
    form.presentation_ticket_type_id = promotion.presentation_ticket_type_id
      ?? promotion.presentation_ticket_type?.id
      ?? '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const nullable = (value) => {
    return value === '' ? null : value;
  };

  const getPayload = () => {
    return {
      name: form.name,
      type: form.type,
      value: isBuyXGetY.value ? null : form.value,
      bundle_quantity: isBuyXGetY.value ? Number(form.bundle_quantity) : null,
      pay_quantity: isBuyXGetY.value ? Number(form.pay_quantity) : null,
      access_code: nullable(form.access_code.trim().toLowerCase()),
      starts_at: nullable(form.starts_at),
      ends_at: nullable(form.ends_at),
      is_active: form.is_active,
      presentation_ticket_type_id: Number(form.presentation_ticket_type_id),
    };
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

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

  const getTicketTypeContext = (ticketType) => {
    const presentation = ticketType.presentation;
    const showTitle = presentation?.show?.title ?? `Show #${ticketType.show_id}`;
    return `${showTitle} - ${formatDateTime(presentation?.starts_at)}`;
  };

  const loadTicketTypes = async () => {
    isLoadingTicketTypes.value = true;

    try {
      const response = await PresentationTicketTypeService.getInstance().getPresentationTicketTypes({
        per_page: 1000,
      });
      ticketTypes.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudieron cargar los tipos de entrada.';
    } finally {
      isLoadingTicketTypes.value = false;
    }
  };

  const handleTypeChange = () => {
    fieldErrors.value = {};

    if (isBuyXGetY.value) {
      form.value = '';
      return;
    }

    form.bundle_quantity = '';
    form.pay_quantity = '';
  };

  const showModal = async () => {
    await loadTicketTypes();
    await nextTick();
    modalInstance.value.show();
  };

  const openForCreate = async () => {
    mode.value = 'create';
    currentPromotion.value = null;
    resetForm();
    await showModal();
  };

  const openForUpdate = async (promotion) => {
    mode.value = 'update';
    currentPromotion.value = promotion;
    fillForm(promotion);
    await showModal();
  };

  const close = () => {
    modalInstance.value.hide();
  };

  const submit = async () => {
    fieldErrors.value = {};
    errorMessage.value = '';
    isSubmitting.value = true;

    try {
      if (isEditing.value) {
        await PromotionService.getInstance().updatePromotion(currentPromotion.value.id, getPayload());
      } else {
        await PromotionService.getInstance().createPromotion(getPayload());
      }

      emit('saved');
      close();
    } catch (error) {
      fieldErrors.value = error.response?.data?.errors ?? error.response?.data?.error?.fields ?? {};
      errorMessage.value = error.response?.data?.message
        ?? error.response?.data?.error?.message
        ?? 'No se pudo guardar la promoción.';
    } finally {
      isSubmitting.value = false;
    }
  };

  defineExpose({
    openForCreate,
    openForUpdate,
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
      <form class="modal-content" @submit.prevent="submit">
        <div class="modal-header">
          <h5 class="modal-title">{{ modalTitle }}</h5>
          <button class="btn-close" type="button" aria-label="Close" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div class="row">
            <div class="col-md-8">
              <div class="mb-3">
                <label class="form-label" for="promotion-name">Nombre</label>
                <input
                  id="promotion-name"
                  v-model.trim="form.name"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('name') }"
                  type="text"
                  maxlength="160"
                  required
                >
                <div v-if="getFieldError('name')" class="invalid-feedback">
                  {{ getFieldError('name') }}
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="promotion-type">Tipo</label>
                <select
                  id="promotion-type"
                  v-model="form.type"
                  class="form-select"
                  :class="{ 'is-invalid': getFieldError('type') }"
                  required
                  @change="handleTypeChange"
                >
                  <option value="percent_discount">Descuento porcentual</option>
                  <option value="fixed_discount">Descuento fijo por entrada</option>
                  <option value="buy_x_get_y">Promoción por cantidad</option>
                </select>
                <div v-if="getFieldError('type')" class="invalid-feedback">
                  {{ getFieldError('type') }}
                </div>
              </div>
            </div>
          </div>

          <div v-if="!isBuyXGetY" class="mb-3">
            <label class="form-label" for="promotion-value">
              {{ isPercentDiscount ? 'Porcentaje de descuento' : 'Descuento por entrada' }}
            </label>
            <div class="input-group">
              <input
                id="promotion-value"
                v-model="form.value"
                class="form-control"
                :class="{ 'is-invalid': getFieldError('value') }"
                type="number"
                min="0.000001"
                :max="isPercentDiscount ? 100 : null"
                step="0.000001"
                required
              >
              <span class="input-group-text">{{ isPercentDiscount ? '%' : 'ARS' }}</span>
              <div v-if="getFieldError('value')" class="invalid-feedback">
                {{ getFieldError('value') }}
              </div>
            </div>
          </div>

          <div v-else class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="promotion-bundle-quantity">Entradas que recibe</label>
                <input
                  id="promotion-bundle-quantity"
                  v-model="form.bundle_quantity"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('bundle_quantity') }"
                  type="number"
                  min="2"
                  required
                >
                <div v-if="getFieldError('bundle_quantity')" class="invalid-feedback">
                  {{ getFieldError('bundle_quantity') }}
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="promotion-pay-quantity">Entradas que paga</label>
                <input
                  id="promotion-pay-quantity"
                  v-model="form.pay_quantity"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('pay_quantity') }"
                  type="number"
                  min="1"
                  required
                >
                <div v-if="getFieldError('pay_quantity')" class="invalid-feedback">
                  {{ getFieldError('pay_quantity') }}
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="promotion-access-code">Código de acceso</label>
                <input
                  id="promotion-access-code"
                  v-model.trim="form.access_code"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('access_code') }"
                  type="text"
                  maxlength="80"
                  pattern="[a-z0-9-]+"
                  placeholder="instagram30"
                >
                <div v-if="getFieldError('access_code')" class="invalid-feedback">
                  {{ getFieldError('access_code') }}
                </div>
                <div v-else class="form-hint">
                  Vacío para promoción pública.
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="promotion-starts-at">Inicio</label>
                <input
                  id="promotion-starts-at"
                  v-model="form.starts_at"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('starts_at') }"
                  type="datetime-local"
                >
                <div v-if="getFieldError('starts_at')" class="invalid-feedback">
                  {{ getFieldError('starts_at') }}
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="promotion-ends-at">Fin</label>
                <input
                  id="promotion-ends-at"
                  v-model="form.ends_at"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('ends_at') }"
                  type="datetime-local"
                >
                <div v-if="getFieldError('ends_at')" class="invalid-feedback">
                  {{ getFieldError('ends_at') }}
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-check form-switch">
              <input v-model="form.is_active" class="form-check-input" type="checkbox">
              <span class="form-check-label">Promoción activa</span>
            </label>
          </div>

          <div class="hr-text">Tipo de entrada</div>

          <div v-if="isLoadingTicketTypes" class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Cargando tipos de entrada...</span>
          </div>

          <div v-else-if="ticketTypes.length === 0" class="alert alert-warning">
            No hay tipos de entrada disponibles.
          </div>

          <div v-else class="mb-3">
            <label class="form-label" for="promotion-ticket-type-id">Tipo de entrada</label>
            <select
              id="promotion-ticket-type-id"
              v-model="form.presentation_ticket_type_id"
              class="form-select"
              :class="{ 'is-invalid': getFieldError('presentation_ticket_type_id') }"
              required
            >
              <option value="">Seleccionar tipo de entrada</option>
              <option v-for="ticketType in ticketTypes" :key="ticketType.id" :value="ticketType.id">
                {{ ticketType.name }} - {{ getTicketTypeContext(ticketType) }}
              </option>
            </select>
            <div v-if="getFieldError('presentation_ticket_type_id')" class="invalid-feedback">
              {{ getFieldError('presentation_ticket_type_id') }}
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
            :disabled="isSubmitting || isLoadingTicketTypes"
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
