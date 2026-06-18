<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import PresentationService from '@/admin/services/PresentationService';
  import ShowService from '@/admin/services/ShowService';
  import VenueService from '@/admin/services/VenueService';

  // emits
  const emit = defineEmits(['saved']);

  // data
  const shows = ref([]);
  const venues = ref([]);
  const mode = ref('create');
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const isLoadingOptions = ref(false);
  const currentPresentation = ref(null);
  const form = reactive({
    notes: '',
    show_id: '',
    capacity: '',
    venue_id: '',
    starts_at: '',
    status: 'published',
  });

  // computed
  const isEditing = computed(() => mode.value === 'update');
  const submitLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Crear función');
  const modalTitle = computed(() => isEditing.value ? 'Editar función' : 'Crear una nueva función');

  // methods
  const resetForm = () => {
    form.notes = '';
    form.show_id = '';
    form.capacity = '';
    form.venue_id = '';
    form.starts_at = '';
    form.status = 'published';

    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const toDateTimeLocal = (date) => {
    if (!date) {
      return '';
    }

    const value = new Date(date);
    const offset = value.getTimezoneOffset();
    const localValue = new Date(value.getTime() - offset * 60000);
  
    return localValue.toISOString().slice(0, 16);
  };

  const fillForm = (presentation) => {
    form.notes = presentation.notes ?? '';
    form.capacity = presentation.capacity ?? '';
    form.status = presentation.status ?? 'published';
    form.starts_at = toDateTimeLocal(presentation.starts_at);
    form.show_id = presentation.show_id ?? presentation.show?.id ?? '';
    form.venue_id = presentation.venue_id ?? presentation.venue?.id ?? '';
  
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const nullable = (value) => {
    return value === '' ? null : value;
  };

  const getPayload = () => {
    return {
      status: form.status,
      starts_at: form.starts_at,
      notes: nullable(form.notes),
      show_id: Number(form.show_id),
      capacity: Number(form.capacity),
      venue_id: form.venue_id === '' ? null : Number(form.venue_id),
    };
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const loadOptions = async () => {
    isLoadingOptions.value = true;

    try {
      const [showsResponse, venuesResponse] = await Promise.all([
        ShowService.getInstance().getShows(),
        VenueService.getInstance().getVenues(),
      ]);

      shows.value = showsResponse.data.data.data ?? [];
      venues.value = venuesResponse.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudieron cargar shows y espacios.';
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
    currentPresentation.value = null;
    resetForm();
    await showModal();
  };

  const openForUpdate = async (presentation) => {
    mode.value = 'update';
    currentPresentation.value = presentation;
    fillForm(presentation);
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
        await PresentationService.getInstance().updatePresentation(currentPresentation.value.id, getPayload());
      } else {
        await PresentationService.getInstance().createPresentation(getPayload());
      }

      emit('saved');
      close();
    } catch (error) {
      fieldErrors.value = error.response?.data?.errors ?? {};
      errorMessage.value = error.response?.data?.message ?? 'No se pudo guardar la función.';
    } finally {
      isSubmitting.value = false;
    }
  };

  const handleChangeVenue = () => {
    const selectedVenueId = Number(form.venue_id);
    const selectedVenue = venues.value.find(venue => venue.id === selectedVenueId);
    form.capacity = selectedVenue ? selectedVenue.capacity : null;
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
            <span>Cargando datos...</span>
          </div>

          <div class="row">
            <div class="col-md-8">
              <div class="mb-3">
                <label class="form-label" for="presentation-show-id">Show</label>
                <select
                  id="presentation-show-id"
                  v-model="form.show_id"
                  class="form-select"
                  :class="{ 'is-invalid': getFieldError('show_id') }"
                  required
                >
                  <option value="">Seleccionar show</option>
                  <option v-for="show in shows" :key="show.id" :value="show.id">
                    {{ show.title }}
                  </option>
                </select>
                <div v-if="getFieldError('show_id')" class="invalid-feedback">
                  {{ getFieldError('show_id') }}
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="presentation-status">Estado</label>
                <select
                  id="presentation-status"
                  v-model="form.status"
                  class="form-select"
                  :class="{ 'is-invalid': getFieldError('status') }"
                  required
                >
                  <option value="draft">Borrador</option>
                  <option value="published">Publicada</option>
                  <option value="sold_out">Agotada</option>
                  <option value="cancelled">Cancelada</option>
                </select>
                <div v-if="getFieldError('status')" class="invalid-feedback">
                  {{ getFieldError('status') }}
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-8">
              <div class="mb-3">
                <label class="form-label" for="presentation-venue-id">Espacio</label>
                <select
                  class="form-select"
                  v-model="form.venue_id"
                  id="presentation-venue-id"
                  @change="handleChangeVenue"
                  :class="{ 'is-invalid': getFieldError('venue_id') }"
                >
                  <option value="">Sin espacio asignado</option>
                  <option v-for="venue in venues" :key="venue.id" :value="venue.id">
                    {{ venue.name }}
                  </option>
                </select>
                <div v-if="getFieldError('venue_id')" class="invalid-feedback">
                  {{ getFieldError('venue_id') }}
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="presentation-capacity">Capacidad</label>
                <input
                  min="0"
                  required
                  type="number"
                  class="form-control"
                  v-model="form.capacity"
                  id="presentation-capacity"
                  :class="{ 'is-invalid': getFieldError('capacity') }"
                >
                <div v-if="getFieldError('capacity')" class="invalid-feedback">
                  {{ getFieldError('capacity') }}
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="presentation-starts-at">Fecha y hora</label>
            <input
              id="presentation-starts-at"
              v-model="form.starts_at"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('starts_at') }"
              type="datetime-local"
              required
            >
            <div v-if="getFieldError('starts_at')" class="invalid-feedback">
              {{ getFieldError('starts_at') }}
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label" for="presentation-notes">Notas</label>
            <textarea
              id="presentation-notes"
              v-model.trim="form.notes"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('notes') }"
              rows="4"
            ></textarea>
            <div v-if="getFieldError('notes')" class="invalid-feedback">
              {{ getFieldError('notes') }}
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
