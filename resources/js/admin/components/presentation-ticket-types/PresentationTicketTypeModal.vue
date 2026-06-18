<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
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
  });

  // computed
  const isEditing = computed(() => mode.value === 'update');
  const submitLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Crear tipo de entrada');
  const modalTitle = computed(() => isEditing.value ? 'Editar tipo de entrada' : 'Crear un nuevo tipo de entrada');

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

  const resetForm = () => {
    form.presentation_id = '';
    form.name = '';
    form.price = '';
    form.stock = '';
    form.sort_order = 1;
    form.is_active = true;
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const fillForm = (ticketType) => {
    form.presentation_id = ticketType.presentation_id ?? ticketType.presentation?.id ?? '';
    form.name = ticketType.name ?? '';
    form.price = ticketType.price ?? '';
    form.stock = ticketType.stock ?? '';
    form.sort_order = ticketType.sort_order ?? 1;
    form.is_active = ticketType.is_active ?? true;
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
    const presentation = selectedPresentation();

    return {
      presentation_id: Number(form.presentation_id),
      show_id: presentation?.show_id ?? presentation?.show?.id ?? currentTicketType.value?.show_id,
      name: form.name,
      price: form.price,
      is_active: form.is_active,
      sort_order: form.sort_order === '' ? 1 : Number(form.sort_order),
      stock: form.stock === '' ? null : Number(form.stock),
    };
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const getPresentationLabel = (presentation) => {
    const showTitle = presentation.show?.title ?? `Show #${presentation.show_id}`;
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
              :class="{ 'is-invalid': getFieldError('presentation_id') || getFieldError('show_id') }"
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
            <div v-else-if="getFieldError('show_id')" class="invalid-feedback">
              {{ getFieldError('show_id') }}
            </div>
          </div>

          <div class="row">
            <div class="col-md-8">
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
