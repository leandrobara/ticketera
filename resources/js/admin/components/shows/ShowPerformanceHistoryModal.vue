<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import ShowPerformanceHistoryService from '@/admin/services/ShowPerformanceHistoryService';

  // data
  const show = ref(null);
  const histories = ref([]);
  const currentHistory = ref(null);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isLoading = ref(false);
  const isSubmitting = ref(false);
  const form = reactive({
    year: '',
    venue_name: '',
    sort_order: '',
  });

  // computed
  const modalTitle = computed(() => show.value
    ? `Histórico de funciones de ${show.value.title}`
    : 'Histórico de funciones');
  const hasHistories = computed(() => histories.value.length > 0);
  const isEditing = computed(() => Boolean(currentHistory.value));
  const formButtonLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Agregar registro');
  const sortedHistories = computed(() => {
    return [...histories.value].sort((firstHistory, secondHistory) => {
      const orderDifference = Number(firstHistory.sort_order) - Number(secondHistory.sort_order);

      return orderDifference || Number(secondHistory.id) - Number(firstHistory.id);
    });
  });

  // methods
  const resetForm = () => {
    currentHistory.value = null;
    form.year = '';
    form.venue_name = '';
    form.sort_order = '';
    fieldErrors.value = {};
  };

  const getFieldError = (field) => fieldErrors.value[field]?.[0] ?? '';

  const setBackendErrors = (error) => {
    fieldErrors.value = error.response?.data?.errors
      ?? error.response?.data?.error?.fields
      ?? {};
    errorMessage.value = error.response?.data?.message
      ?? error.response?.data?.error?.message
      ?? 'No se pudo guardar el histórico.';
  };

  const validateForm = () => {
    fieldErrors.value = {};

    if (!form.year.trim()) {
      fieldErrors.value.year = ['Ingresá el año.'];
    }

    if (!form.venue_name.trim()) {
      fieldErrors.value.venue_name = ['Ingresá el lugar.'];
    }

    return Object.keys(fieldErrors.value).length === 0;
  };

  const getNextSortOrder = () => {
    if (!histories.value.length) {
      return 1;
    }

    return Math.max(...histories.value.map((history) => Number(history.sort_order || 1))) + 1;
  };

  const loadHistories = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await ShowPerformanceHistoryService.getInstance().getHistory({
        show_id: show.value.id,
        per_page: 100,
      });
      histories.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el histórico.';
    } finally {
      isLoading.value = false;
    }
  };

  const saveHistory = async () => {
    errorMessage.value = '';

    if (!validateForm()) {
      return;
    }

    isSubmitting.value = true;
    const payload = {
      show_id: show.value.id,
      year: form.year.trim(),
      venue_name: form.venue_name.trim(),
      sort_order: form.sort_order === '' ? getNextSortOrder() : Number(form.sort_order),
    };

    try {
      if (isEditing.value) {
        await ShowPerformanceHistoryService.getInstance().updateHistory(currentHistory.value.id, payload);
      } else {
        await ShowPerformanceHistoryService.getInstance().createHistory(payload);
      }

      resetForm();
      await loadHistories();
    } catch (error) {
      setBackendErrors(error);
    } finally {
      isSubmitting.value = false;
    }
  };

  const editHistory = (history) => {
    currentHistory.value = history;
    form.year = history.year ?? '';
    form.venue_name = history.venue_name ?? '';
    form.sort_order = history.sort_order ?? '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const deleteHistory = async (history) => {
    if (!window.confirm(`¿Eliminar ${history.venue_name} (${history.year}) del histórico?`)) {
      return;
    }

    errorMessage.value = '';

    try {
      await ShowPerformanceHistoryService.getInstance().deleteHistory(history.id);
      await loadHistories();

      if (currentHistory.value?.id === history.id) {
        resetForm();
      }
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar el registro.';
    }
  };

  const open = async (selectedShow) => {
    show.value = selectedShow;
    histories.value = [];
    resetForm();
    errorMessage.value = '';
    await nextTick();
    modalInstance.value.show();
    await loadHistories();
  };

  const close = () => modalInstance.value.hide();

  defineExpose({ open });

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
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title">{{ modalTitle }}</h5>
            <div class="text-secondary">Años y lugares donde se presentó la obra</div>
          </div>
          <button class="btn-close" type="button" aria-label="Cerrar" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div class="card mb-4">
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label" for="show-history-year">Año</label>
                  <input
                    id="show-history-year"
                    v-model.trim="form.year"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('year') }"
                    type="text"
                    placeholder="Ej: 2025"
                  >
                  <div v-if="getFieldError('year')" class="invalid-feedback">
                    {{ getFieldError('year') }}
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label" for="show-history-venue">Lugar</label>
                  <input
                    id="show-history-venue"
                    v-model.trim="form.venue_name"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('venue_name') }"
                    type="text"
                    placeholder="Ej: Teatro Metropolitan Sura"
                  >
                  <div v-if="getFieldError('venue_name')" class="invalid-feedback">
                    {{ getFieldError('venue_name') }}
                  </div>
                </div>

                <div class="col-md-3">
                  <label class="form-label" for="show-history-order">Orden</label>
                  <input
                    id="show-history-order"
                    v-model="form.sort_order"
                    class="form-control"
                    type="number"
                    min="1"
                    placeholder="Automático"
                  >
                </div>
              </div>

              <div class="btn-list justify-content-end mt-3">
                <button
                  v-if="isEditing"
                  class="btn btn-link link-secondary"
                  type="button"
                  :disabled="isSubmitting"
                  @click="resetForm"
                >
                  Cancelar edición
                </button>
                <button class="btn btn-success" type="button" :disabled="isSubmitting" @click="saveHistory">
                  <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                  {{ formButtonLabel }}
                </button>
              </div>
            </div>
          </div>

          <div v-if="isLoading" class="text-secondary">Cargando histórico...</div>
          <div v-else-if="!hasHistories" class="empty border rounded">
            <p class="empty-title">No hay funciones históricas cargadas</p>
          </div>
          <div v-else class="list-group">
            <div
              v-for="history in sortedHistories"
              :key="history.id"
              class="list-group-item d-flex align-items-center gap-3"
            >
              <span class="badge bg-secondary-lt">{{ history.sort_order }}</span>
              <div class="flex-fill">
                <div class="fw-semibold">{{ history.venue_name }}</div>
                <div class="text-secondary">{{ history.year }}</div>
              </div>
              <div class="btn-list flex-nowrap">
                <button class="btn btn-sm btn-outline-primary" type="button" @click="editHistory(history)">
                  Editar
                </button>
                <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteHistory(history)">
                  Eliminar
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-link link-secondary" type="button" @click="close">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</template>
