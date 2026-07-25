<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import ShowLinkService from '@/admin/services/ShowLinkService';

  // data
  const show = ref(null);
  const links = ref([]);
  const currentLink = ref(null);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isLoading = ref(false);
  const isSubmitting = ref(false);
  const form = reactive({
    text: '',
    url: '',
    sort_order: '',
  });

  // computed
  const modalTitle = computed(() => show.value ? `Links de interés de ${show.value.title}` : 'Links de interés');
  const hasLinks = computed(() => links.value.length > 0);
  const isEditing = computed(() => Boolean(currentLink.value));
  const formButtonLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Agregar link');
  const sortedLinks = computed(() => {
    return [...links.value].sort((firstLink, secondLink) => {
      const orderDifference = Number(firstLink.sort_order) - Number(secondLink.sort_order);

      return orderDifference || Number(firstLink.id) - Number(secondLink.id);
    });
  });

  // methods
  const resetForm = () => {
    currentLink.value = null;
    form.text = '';
    form.url = '';
    form.sort_order = '';
    fieldErrors.value = {};
  };

  const getFieldError = (field) => fieldErrors.value[field]?.[0] ?? '';

  const validateForm = () => {
    fieldErrors.value = {};

    if (!form.text.trim()) {
      fieldErrors.value.text = ['Ingresá el texto del enlace.'];
    }

    if (!form.url.trim()) {
      fieldErrors.value.url = ['Ingresá el enlace.'];
    }

    return Object.keys(fieldErrors.value).length === 0;
  };

  const getNextSortOrder = () => {
    if (!links.value.length) {
      return 1;
    }

    return Math.max(...links.value.map((link) => Number(link.sort_order || 1))) + 1;
  };

  const setBackendErrors = (error) => {
    fieldErrors.value = error.response?.data?.errors
      ?? error.response?.data?.error?.fields
      ?? {};
    errorMessage.value = error.response?.data?.message
      ?? error.response?.data?.error?.message
      ?? 'No se pudo guardar el link.';
  };

  const loadLinks = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await ShowLinkService.getInstance().getLinks({
        show_id: show.value.id,
        per_page: 100,
      });
      links.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudieron cargar los links.';
    } finally {
      isLoading.value = false;
    }
  };

  const saveLink = async () => {
    errorMessage.value = '';

    if (!validateForm()) {
      return;
    }

    isSubmitting.value = true;
    const payload = {
      show_id: show.value.id,
      text: form.text.trim(),
      url: form.url.trim(),
      sort_order: form.sort_order === '' ? getNextSortOrder() : Number(form.sort_order),
    };

    try {
      if (isEditing.value) {
        await ShowLinkService.getInstance().updateLink(currentLink.value.id, payload);
      } else {
        await ShowLinkService.getInstance().createLink(payload);
      }

      resetForm();
      await loadLinks();
    } catch (error) {
      setBackendErrors(error);
    } finally {
      isSubmitting.value = false;
    }
  };

  const editLink = (link) => {
    currentLink.value = link;
    form.text = link.text ?? '';
    form.url = link.url ?? '';
    form.sort_order = link.sort_order ?? '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const deleteLink = async (link) => {
    if (!window.confirm(`¿Eliminar el link "${link.text}"?`)) {
      return;
    }

    errorMessage.value = '';

    try {
      await ShowLinkService.getInstance().deleteLink(link.id);
      await loadLinks();

      if (currentLink.value?.id === link.id) {
        resetForm();
      }
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar el link.';
    }
  };

  const open = async (selectedShow) => {
    show.value = selectedShow;
    links.value = [];
    resetForm();
    errorMessage.value = '';
    await nextTick();
    modalInstance.value.show();
    await loadLinks();
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
            <div class="text-secondary">Notas, artículos, videos y sitios relacionados con la obra</div>
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
                <div class="col-md-5">
                  <label class="form-label" for="show-link-text">Texto</label>
                  <input
                    id="show-link-text"
                    v-model.trim="form.text"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('text') }"
                    type="text"
                    placeholder="Ej: Entrevista con el elenco en Página 12"
                  >
                  <div v-if="getFieldError('text')" class="invalid-feedback">
                    {{ getFieldError('text') }}
                  </div>
                </div>

                <div class="col-md-5">
                  <label class="form-label" for="show-link-url">Enlace</label>
                  <input
                    id="show-link-url"
                    v-model.trim="form.url"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('url') }"
                    type="url"
                    placeholder="https://..."
                  >
                  <div v-if="getFieldError('url')" class="invalid-feedback">
                    {{ getFieldError('url') }}
                  </div>
                </div>

                <div class="col-md-2">
                  <label class="form-label" for="show-link-order">Orden</label>
                  <input
                    id="show-link-order"
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
                <button class="btn btn-success" type="button" :disabled="isSubmitting" @click="saveLink">
                  <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                  {{ formButtonLabel }}
                </button>
              </div>
            </div>
          </div>

          <div v-if="isLoading" class="text-secondary">Cargando links...</div>
          <div v-else-if="!hasLinks" class="empty border rounded">
            <p class="empty-title">No hay links cargados</p>
          </div>
          <div v-else class="list-group">
            <div
              v-for="link in sortedLinks"
              :key="link.id"
              class="list-group-item d-flex align-items-center gap-3"
            >
              <span class="badge bg-secondary-lt">{{ link.sort_order }}</span>
              <div class="flex-fill min-w-0">
                <div class="fw-semibold">{{ link.text }}</div>
                <a :href="link.url" target="_blank" rel="nofollow noreferrer" class="text-secondary text-break">
                  {{ link.url }}
                </a>
              </div>
              <div class="btn-list flex-nowrap">
                <button class="btn btn-sm btn-outline-primary" type="button" @click="editLink(link)">
                  Editar
                </button>
                <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteLink(link)">
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
