<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import { formatDateTime } from '@/admin/helpers/DateTimeFormatHelper';
  import { normalizeDecimalInput } from '@/admin/helpers/number';
  import PresentationService from '@/admin/services/PresentationService';
  import PresentationTicketTypeService from '@/admin/services/PresentationTicketTypeService';

  // emits
  const emit = defineEmits(['saved']);

  // data
  const mode = ref('create');
  const currentTicketType = ref(null);
  const presentations = ref([]);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const isLoadingOptions = ref(false);
  const form = reactive({
    presentation_id: '',
    name: '',
    price: '',
    stock: '',
    sort_order: 1,
    is_active: true,
    promotion_enabled: false,
    promotion_name: '',
    promotion_type: '',
    promotion_value: '',
    promotion_bundle_quantity: '',
    promotion_pay_quantity: '',
    promotion_access_code: '',
  });

  // computed
  const isEditing = computed(() => mode.value === 'update');
  const submitLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Crear tipo de entrada');
  const modalTitle = computed(() => isEditing.value ? 'Editar tipo de entrada' : 'Crear un nuevo tipo de entrada');
  const hasPromotion = computed(() => form.promotion_enabled);
  const promotionNeedsValue = computed(() => ['percent_discount', 'fixed_discount'].includes(form.promotion_type));
  const promotionIsBundle = computed(() => form.promotion_type === 'buy_x_get_y');

  // methods
  const resetForm = () => {
    form.presentation_id = '';
    form.name = '';
    form.price = '';
    form.stock = '';
    form.sort_order = 1;
    form.is_active = true;
    form.promotion_enabled = false;
    form.promotion_name = '';
    form.promotion_type = '';
    form.promotion_value = '';
    form.promotion_bundle_quantity = '';
    form.promotion_pay_quantity = '';
    form.promotion_access_code = '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const fillForm = (ticketType) => {
    form.presentation_id = ticketType.presentation_id ?? ticketType.presentation?.id ?? '';
    form.name = ticketType.name ?? '';
    form.price = normalizeDecimalInput(ticketType.price);
    form.stock = ticketType.stock ?? '';
    form.sort_order = ticketType.sort_order ?? 1;
    form.is_active = ticketType.is_active ?? true;
    form.promotion_enabled = Boolean(ticketType.promotion_type && ticketType.promotion_is_active);
    form.promotion_name = ticketType.promotion_name ?? '';
    form.promotion_type = ticketType.promotion_type ?? '';
    form.promotion_value = normalizeDecimalInput(ticketType.promotion_value);
    form.promotion_bundle_quantity = ticketType.promotion_bundle_quantity ?? '';
    form.promotion_pay_quantity = ticketType.promotion_pay_quantity ?? '';
    form.promotion_access_code = ticketType.promotion_access_code ?? '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const nullable = (value) => {
    return value === '' ? null : value;
  };

  const selectedPresentation = () => {
    return presentations.value.find((presentation) => Number(presentation.id) === Number(form.presentation_id));
  };

  const getPayload = () => {
    return {
      presentation_id: Number(form.presentation_id),
      name: form.name,
      price: form.price,
      is_active: form.is_active,
      sort_order: form.sort_order === '' ? 1 : Number(form.sort_order),
      stock: form.stock === '' ? null : Number(form.stock),
      promotion_name: hasPromotion.value ? nullable(form.promotion_name) : null,
      promotion_type: hasPromotion.value ? nullable(form.promotion_type) : null,
      promotion_value: hasPromotion.value && promotionNeedsValue.value ? form.promotion_value : null,
      promotion_bundle_quantity: hasPromotion.value && promotionIsBundle.value
        ? Number(form.promotion_bundle_quantity)
        : null,
      promotion_pay_quantity: hasPromotion.value && promotionIsBundle.value
        ? Number(form.promotion_pay_quantity)
        : null,
      promotion_access_code: hasPromotion.value ? nullable(form.promotion_access_code.trim().toLowerCase()) : null,
      promotion_is_active: hasPromotion.value,
    };
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const getPresentationLabel = (presentation) => {
    const showTitle = presentation.season?.show?.title ?? `Temporada #${presentation.season_id}`;
    return `${showTitle} - ${formatDateTime(presentation.starts_at)}`;
  };

  const loadOptions = async () => {
    isLoadingOptions.value = true;

    try {
      const response = await PresentationService.getInstance().getPresentations();
      presentations.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudieron cargar las funciones.';
    } finally {
      isLoadingOptions.value = false;
    }
  };

  const resetPromotionFields = () => {
    if (form.promotion_enabled) {
      if (!form.promotion_type) {
        form.promotion_type = 'percent_discount';
      }

      return;
    }

    form.promotion_name = '';
    form.promotion_type = '';
    form.promotion_value = '';
    form.promotion_bundle_quantity = '';
    form.promotion_pay_quantity = '';
    form.promotion_access_code = '';
  };

  const showModal = async () => {
    await loadOptions();
    await nextTick();
    modalInstance.value.show();
  };

  const openForCreate = async () => {
    mode.value = 'create';
    currentTicketType.value = null;
    resetForm();
    await showModal();
  };

  const openForUpdate = async (ticketType) => {
    mode.value = 'update';
    currentTicketType.value = ticketType;
    fillForm(ticketType);
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
        await PresentationTicketTypeService.getInstance().updatePresentationTicketType(
          currentTicketType.value.id,
          getPayload()
        );
      } else {
        await PresentationTicketTypeService.getInstance().createPresentationTicketType(getPayload());
      }

      emit('saved');
      close();
    } catch (error) {
      fieldErrors.value = error.response?.data?.errors ?? {};
      errorMessage.value = error.response?.data?.message ?? 'No se pudo guardar el tipo de entrada.';
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
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <form class="modal-content" @submit.prevent="submit">
        <div class="modal-header">
          <h5 class="modal-title">{{ modalTitle }}</h5>
          <button class="btn-close" type="button" aria-label="Close" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div v-if="isLoadingOptions" class="d-flex align-items-center mb-3">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Cargando funciones...</span>
          </div>

          <div class="mb-3">
            <label class="form-label" for="ticket-type-presentation-id">Función</label>
            <select
              id="ticket-type-presentation-id"
              v-model="form.presentation_id"
              class="form-select"
              :class="{ 'is-invalid': getFieldError('presentation_id') }"
              required
            >
              <option value="">Seleccionar función</option>
              <option v-for="presentation in presentations" :key="presentation.id" :value="presentation.id">
                {{ getPresentationLabel(presentation) }}
              </option>
            </select>
            <div v-if="getFieldError('presentation_id')" class="invalid-feedback">
              {{ getFieldError('presentation_id') }}
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="ticket-type-name">Nombre</label>
                <input
                  id="ticket-type-name"
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
                <label class="form-label" for="ticket-type-price">Precio</label>
                <input
                  id="ticket-type-price"
                  v-model="form.price"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('price') }"
                  type="number"
                  min="0"
                  required
                >
                <div v-if="getFieldError('price')" class="invalid-feedback">
                  {{ getFieldError('price') }}
                </div>
              </div>
            </div>

          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="ticket-type-stock">Stock</label>
                <input
                  id="ticket-type-stock"
                  v-model="form.stock"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('stock') }"
                  type="number"
                  min="0"
                >
                <div v-if="getFieldError('stock')" class="invalid-feedback">
                  {{ getFieldError('stock') }}
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="ticket-type-sort-order">Orden</label>
                <input
                  id="ticket-type-sort-order"
                  v-model.number="form.sort_order"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('sort_order') }"
                  type="number"
                  min="1"
                >
                <div v-if="getFieldError('sort_order')" class="invalid-feedback">
                  {{ getFieldError('sort_order') }}
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Estado</label>
                <label class="form-check form-switch mt-2">
                  <input v-model="form.is_active" class="form-check-input" type="checkbox">
                  <span class="form-check-label">Activa</span>
                </label>
                <div v-if="getFieldError('is_active')" class="invalid-feedback d-block">
                  {{ getFieldError('is_active') }}
                </div>
              </div>
            </div>
          </div>

          <div class="hr-text">Promoción</div>

          <div class="mb-3">
            <label class="form-check form-switch">
              <input
                v-model="form.promotion_enabled"
                class="form-check-input"
                type="checkbox"
                @change="resetPromotionFields"
              >
              <span class="form-check-label">Esta entrada tiene promoción</span>
            </label>
          </div>

          <div v-if="hasPromotion">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label" for="ticket-type-promotion-name">Nombre de la promoción</label>
                  <input
                    id="ticket-type-promotion-name"
                    v-model.trim="form.promotion_name"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('promotion_name') }"
                    type="text"
                    maxlength="160"
                  >
                  <div v-if="getFieldError('promotion_name')" class="invalid-feedback">
                    {{ getFieldError('promotion_name') }}
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label" for="ticket-type-promotion-type">Tipo de promoción</label>
                  <select
                    id="ticket-type-promotion-type"
                    v-model="form.promotion_type"
                    class="form-select"
                    :class="{ 'is-invalid': getFieldError('promotion_type') }"
                    required
                  >
                    <option value="">Seleccionar promoción</option>
                    <option value="percent_discount">Descuento porcentual</option>
                    <option value="fixed_discount">Descuento fijo</option>
                    <option value="buy_x_get_y">2x1 / 3x1</option>
                  </select>
                  <div v-if="getFieldError('promotion_type')" class="invalid-feedback">
                    {{ getFieldError('promotion_type') }}
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div v-if="promotionNeedsValue" class="col-md-4">
                <div class="mb-3">
                  <label class="form-label" for="ticket-type-promotion-value">
                    {{ form.promotion_type === 'percent_discount' ? 'Porcentaje' : 'Monto fijo' }}
                  </label>
                  <input
                    id="ticket-type-promotion-value"
                    v-model="form.promotion_value"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('promotion_value') }"
                    type="number"
                    min="0"
                    required
                  >
                  <div v-if="getFieldError('promotion_value')" class="invalid-feedback">
                    {{ getFieldError('promotion_value') }}
                  </div>
                </div>
              </div>

              <template v-if="promotionIsBundle">
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label" for="ticket-type-promotion-bundle">Entradas que recibe</label>
                    <input
                      id="ticket-type-promotion-bundle"
                      v-model="form.promotion_bundle_quantity"
                      class="form-control"
                      :class="{ 'is-invalid': getFieldError('promotion_bundle_quantity') }"
                      type="number"
                      min="2"
                      required
                    >
                    <div v-if="getFieldError('promotion_bundle_quantity')" class="invalid-feedback">
                      {{ getFieldError('promotion_bundle_quantity') }}
                    </div>
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label" for="ticket-type-promotion-pay">Entradas que paga</label>
                    <input
                      id="ticket-type-promotion-pay"
                      v-model="form.promotion_pay_quantity"
                      class="form-control"
                      :class="{ 'is-invalid': getFieldError('promotion_pay_quantity') }"
                      type="number"
                      min="1"
                      required
                    >
                    <div v-if="getFieldError('promotion_pay_quantity')" class="invalid-feedback">
                      {{ getFieldError('promotion_pay_quantity') }}
                    </div>
                  </div>
                </div>
              </template>

              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label" for="ticket-type-promotion-access-code">Código</label>
                  <input
                    id="ticket-type-promotion-access-code"
                    v-model.trim="form.promotion_access_code"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('promotion_access_code') }"
                    type="text"
                    maxlength="80"
                  >
                  <div v-if="getFieldError('promotion_access_code')" class="invalid-feedback">
                    {{ getFieldError('promotion_access_code') }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-link link-secondary" type="button" @click="close">
            Cancelar
          </button>
          <button class="btn btn-success ms-auto" type="submit" :disabled="isSubmitting || isLoadingOptions">
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
