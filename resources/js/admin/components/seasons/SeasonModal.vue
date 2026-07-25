<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import SeasonService from '@/admin/services/SeasonService';
  import ShowService from '@/admin/services/ShowService';
  import VenueService from '@/admin/services/VenueService';

  // emits
  const emit = defineEmits(['saved']);

  // data
  const mode = ref('create');
  const currentSeason = ref(null);
  const shows = ref([]);
  const venues = ref([]);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const form = reactive({
    show_id: '',
    venue_id: '',
    name: '',
    status: 'draft',
  });

  // computed
  const isEditing = computed(() => mode.value === 'update');
  const modalTitle = computed(() => isEditing.value ? 'Editar temporada' : 'Crear una nueva temporada');
  const submitLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Crear temporada');
  const availableStatuses = computed(() => {
    if (!isEditing.value) {
      return [
        { value: 'draft', label: 'Borrador' },
        { value: 'published', label: 'Publicada' },
      ];
    }

    const transitions = {
      draft: [
        { value: 'draft', label: 'Borrador' },
        { value: 'published', label: 'Publicada' },
        { value: 'cancelled', label: 'Cancelada' },
      ],
      published: [
        { value: 'published', label: 'Publicada' },
        { value: 'finished', label: 'Finalizada' },
        { value: 'cancelled', label: 'Cancelada' },
      ],
      finished: [{ value: 'finished', label: 'Finalizada' }],
      cancelled: [{ value: 'cancelled', label: 'Cancelada' }],
    };

    return transitions[currentSeason.value?.status] ?? [];
  });

  // methods
  const loadOptions = async () => {
    const [showsResponse, venuesResponse] = await Promise.all([
      ShowService.getInstance().getShows({ per_page: 100 }),
      VenueService.getInstance().getVenues({ per_page: 100 }),
    ]);

    shows.value = showsResponse.data.data.data ?? [];
    venues.value = venuesResponse.data.data.data ?? [];
  };

  const resetForm = (showId = null) => {
    form.show_id = showId ? Number(showId) : '';
    form.venue_id = '';
    form.name = '';
    form.status = 'draft';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const fillForm = (season) => {
    form.show_id = season.show_id;
    form.venue_id = season.venue_id;
    form.name = season.name ?? '';
    form.status = season.status;
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const showModal = async () => {
    await loadOptions();
    await nextTick();
    modalInstance.value.show();
  };

  const openForCreate = async (showId = null) => {
    mode.value = 'create';
    currentSeason.value = null;
    resetForm(showId);
    await showModal();
  };

  const openForUpdate = async (season) => {
    mode.value = 'update';
    currentSeason.value = season;
    fillForm(season);
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
        await SeasonService.getInstance().updateSeason(currentSeason.value.id, {
          name: form.name || null,
          status: form.status,
        });
      } else {
        await SeasonService.getInstance().createSeason({
          show_id: Number(form.show_id),
          venue_id: Number(form.venue_id),
          name: form.name || null,
          status: form.status,
        });
      }

      emit('saved');
      close();
    } catch (error) {
      fieldErrors.value = error.response?.data?.error?.fields
        ?? error.response?.data?.errors
        ?? {};
      errorMessage.value = error.response?.data?.error?.message
        ?? error.response?.data?.message
        ?? 'No se pudo guardar la temporada.';
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
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="season-show">Show</label>
                <select
                  id="season-show"
                  v-model="form.show_id"
                  class="form-select"
                  :class="{ 'is-invalid': getFieldError('show_id') }"
                  :disabled="isEditing"
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

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="season-venue">Espacio</label>
                <select
                  id="season-venue"
                  v-model="form.venue_id"
                  class="form-select"
                  :class="{ 'is-invalid': getFieldError('venue_id') }"
                  :disabled="isEditing"
                  required
                >
                  <option value="">Seleccionar espacio</option>
                  <option v-for="venue in venues" :key="venue.id" :value="venue.id">
                    {{ venue.name }}
                  </option>
                </select>
                <div v-if="getFieldError('venue_id')" class="invalid-feedback">
                  {{ getFieldError('venue_id') }}
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-8">
              <div class="mb-3">
                <label class="form-label" for="season-name">Nombre interno</label>
                <input
                  id="season-name"
                  v-model.trim="form.name"
                  class="form-control"
                  type="text"
                  maxlength="160"
                  placeholder="Ej: Temporada invierno 2026"
                >
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="season-status">Estado</label>
                <select id="season-status" v-model="form.status" class="form-select" required>
                  <option v-for="status in availableStatuses" :key="status.value" :value="status.value">
                    {{ status.label }}
                  </option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-link link-secondary" type="button" @click="close">
            Cancelar
          </button>
          <button class="btn btn-success ms-auto" type="submit" :disabled="isSubmitting">
            {{ submitLabel }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
