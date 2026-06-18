<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import VenueService from '@/admin/services/VenueService';

  // emits
  const emit = defineEmits(['saved']);

  // data
  const mode = ref('create');
  const currentVenue = ref(null);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const form = reactive({
    name: '',
    city: '',
    address: '',
    capacity: '',
    description: '',
    neighborhood: '',
    google_maps_url: '',
    has_bar: false,
    has_parking: false,
    is_accessible: false,
  });

  // computed
  const isEditing = computed(() => mode.value === 'update');
  const modalTitle = computed(() => isEditing.value ? 'Editar espacio' : 'Crear un nuevo espacio');
  const submitLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Crear espacio');

  // methods
  const resetForm = () => {
    form.name = '';
    form.city = '';
    form.address = '';
    form.capacity = '';
    form.description = '';
    form.neighborhood = '';
    form.google_maps_url = '';
    form.has_bar = false;
    form.has_parking = false;
    form.is_accessible = false;
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const fillForm = (venue) => {
    form.name = venue.name ?? '';
    form.city = venue.city ?? '';
    form.address = venue.address ?? '';
    form.capacity = venue.capacity ?? '';
    form.description = venue.description ?? '';
    form.neighborhood = venue.neighborhood ?? '';
    form.google_maps_url = venue.google_maps_url ?? '';
    form.has_bar = Boolean(venue.has_bar);
    form.has_parking = Boolean(venue.has_parking);
    form.is_accessible = Boolean(venue.is_accessible);
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const nullable = (value) => {
    return value === '' ? null : value;
  };

  const getPayload = () => {
    return {
      name: form.name,
      city: nullable(form.city),
      address: nullable(form.address),
      capacity: form.capacity === '' ? null : Number(form.capacity),
      description: nullable(form.description),
      neighborhood: nullable(form.neighborhood),
      google_maps_url: nullable(form.google_maps_url),
      has_bar: form.has_bar,
      has_parking: form.has_parking,
      is_accessible: form.is_accessible,
    };
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const showModal = async () => {
    await nextTick();
    modalInstance.value.show();
  };

  const openForCreate = async () => {
    mode.value = 'create';
    currentVenue.value = null;
    resetForm();
    await showModal();
  };

  const openForUpdate = async (venue) => {
    mode.value = 'update';
    currentVenue.value = venue;
    fillForm(venue);
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
        await VenueService.getInstance().updateVenue(currentVenue.value.id, getPayload());
      } else {
        await VenueService.getInstance().createVenue(getPayload());
      }

      emit('saved');
      close();
    } catch (error) {
      fieldErrors.value = error.response?.data?.errors ?? {};
      errorMessage.value = error.response?.data?.message ?? 'No se pudo guardar el espacio.';
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

          <div class="row">
            <div class="col-md-8">
              <div class="mb-3">
                <label class="form-label" for="venue-name">Nombre</label>
                <input
                  id="venue-name"
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
                <label class="form-label" for="venue-capacity">Capacidad</label>
                <input
                  id="venue-capacity"
                  v-model="form.capacity"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('capacity') }"
                  type="number"
                  min="0"
                >
                <div v-if="getFieldError('capacity')" class="invalid-feedback">
                  {{ getFieldError('capacity') }}
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-8">
              <div class="mb-3">
                <label class="form-label" for="venue-address">Dirección</label>
                <input
                  id="venue-address"
                  v-model.trim="form.address"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('address') }"
                  type="text"
                  maxlength="255"
                >
                <div v-if="getFieldError('address')" class="invalid-feedback">
                  {{ getFieldError('address') }}
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="venue-city">Ciudad</label>
                <input
                  id="venue-city"
                  v-model.trim="form.city"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('city') }"
                  type="text"
                  maxlength="120"
                >
                <div v-if="getFieldError('city')" class="invalid-feedback">
                  {{ getFieldError('city') }}
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="venue-neighborhood">Barrio</label>
            <input
              id="venue-neighborhood"
              v-model.trim="form.neighborhood"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('neighborhood') }"
              type="text"
              maxlength="120"
            >
            <div v-if="getFieldError('neighborhood')" class="invalid-feedback">
              {{ getFieldError('neighborhood') }}
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="venue-google-maps-url">Google Maps URL</label>
            <input
              id="venue-google-maps-url"
              v-model.trim="form.google_maps_url"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('google_maps_url') }"
              type="url"
              maxlength="255"
            >
            <div v-if="getFieldError('google_maps_url')" class="invalid-feedback">
              {{ getFieldError('google_maps_url') }}
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Características</label>
            <div class="form-selectgroup">
              <label class="form-selectgroup-item">
                <input v-model="form.has_bar" class="form-selectgroup-input" type="checkbox">
                <span class="form-selectgroup-label">Tiene bar</span>
              </label>
              <label class="form-selectgroup-item">
                <input v-model="form.has_parking" class="form-selectgroup-input" type="checkbox">
                <span class="form-selectgroup-label">Tiene parking</span>
              </label>
              <label class="form-selectgroup-item">
                <input v-model="form.is_accessible" class="form-selectgroup-input" type="checkbox">
                <span class="form-selectgroup-label">Es accesible</span>
              </label>
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label" for="venue-description">Descripción</label>
            <textarea
              id="venue-description"
              v-model.trim="form.description"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('description') }"
              rows="4"
            ></textarea>
            <div v-if="getFieldError('description')" class="invalid-feedback">
              {{ getFieldError('description') }}
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-link link-secondary" type="button" @click="close">
            Cancelar
          </button>
          <button class="btn btn-success ms-auto" type="submit" :disabled="isSubmitting">
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
