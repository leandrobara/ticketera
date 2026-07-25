<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import PresentationService from '@/admin/services/PresentationService';
  import SeasonService from '@/admin/services/SeasonService';

  // emits
  const emit = defineEmits(['saved']);

  // data
  const seasons = ref([]);
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
    season_id: '',
    capacity: '',
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
    form.season_id = '';
    form.capacity = '';
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
    form.season_id = presentation.season_id ?? presentation.season?.id ?? '';
  
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
      season_id: Number(form.season_id),
      capacity: Number(form.capacity),
    };
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const loadOptions = async () => {
    isLoadingOptions.value = true;

    try {
      const response = await SeasonService.getInstance().getSeasons({ per_page: 1000 });
      seasons.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudieron cargar las temporadas.';
    } finally {
      isLoadingOptions.value = false;
    }
  };

  const showModal = async () => {
    await loadOptions();
    await nextTick();
    modalInstance.value.show();
  };

  const openForCreate = async (seasonId = null) => {
    mode.value = 'create';
    currentPresentation.value = null;
    resetForm();
    form.season_id = seasonId ? Number(seasonId) : '';
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

  const handleChangeSeason = () => {
    const selectedSeason = seasons.value.find(
      (season) => Number(season.id) === Number(form.season_id)
    );
    form.capacity = selectedSeason?.venue?.capacity ?? '';
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
                <label class="form-label" for="presentation-season-id">Temporada</label>
                <select
                  id="presentation-season-id"
                  v-model="form.season_id"
                  class="form-select"
                  :class="{ 'is-invalid': getFieldError('season_id') }"
                  @change="handleChangeSeason"
                  required
                >
                  <option value="">Seleccionar temporada</option>
                  <option
                    v-for="season in seasons"
                    :key="season.id"
                    :value="season.id"
                    :disabled="['finished', 'cancelled'].includes(season.status)"
                  >
                    {{ season.show?.title }} - {{ season.venue?.name }}
                    <template v-if="season.name"> - {{ season.name }}</template>
                  </option>
                </select>
                <div v-if="getFieldError('season_id')" class="invalid-feedback">
                  {{ getFieldError('season_id') }}
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
