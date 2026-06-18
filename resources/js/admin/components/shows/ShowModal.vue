<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import ShowService from '@/admin/services/ShowService';

  // emits
  const emit = defineEmits(['saved']);

  // data
  const mode = ref('create');
  const currentShow = ref(null);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const form = reactive({
    title: '',
    slug: '',
    description: '',
    genre: '',
    format: '',
    duration_minutes: '',
    main_image_path: '',
    status: 'draft',
    age_rating: 'ATP',
  });

  // computed
  const isEditing = computed(() => mode.value === 'update');
  const modalTitle = computed(() => isEditing.value ? 'Editar show' : 'Crear un nuevo show');
  const submitLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Crear show');

  // methods
  const resetForm = () => {
    form.slug = '';
    form.title = '';
    form.genre = '';
    form.format = '';
    form.description = '';
    form.status = 'draft';
    fieldErrors.value = {};
    form.age_rating = 'ATP';
    errorMessage.value = '';
    form.main_image_path = '';
    form.duration_minutes = '';
  };

  const fillForm = (show) => {
    form.slug = show.slug ?? '';
    form.title = show.title ?? '';
    form.genre = show.genre ?? '';
    form.format = show.format ?? '';
    form.description = show.description ?? '';
    form.status = show.status ?? 'draft';
    form.age_rating = show.age_rating ?? 'ATP';
    form.main_image_path = show.main_image_path ?? '';
    form.duration_minutes = show.duration_minutes ?? '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const nullable = (value) => {
    return value === '' ? null : value;
  };

  const getPayload = () => {
    return {
      title: form.title,
      slug: nullable(form.slug),
      description: nullable(form.description),
      genre: nullable(form.genre),
      format: nullable(form.format),
      duration_minutes: form.duration_minutes === '' ? null : Number(form.duration_minutes),
      main_image_path: nullable(form.main_image_path),
      status: form.status,
      age_rating: form.age_rating,
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
    currentShow.value = null;
    resetForm();
    await showModal();
  };

  const openForUpdate = async (show) => {
    mode.value = 'update';
    currentShow.value = show;
    fillForm(show);
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
        await ShowService.getInstance().updateShow(currentShow.value.id, getPayload());
      } else {
        await ShowService.getInstance().createShow(getPayload());
      }

      emit('saved');
      close();
    } catch (error) {
      fieldErrors.value = error.response?.data?.errors ?? {};
      errorMessage.value = error.response?.data?.message ?? 'No se pudo crear el show.';
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
                <label class="form-label" for="show-title">Título</label>
                <input
                  id="show-title"
                  v-model.trim="form.title"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('title') }"
                  type="text"
                  maxlength="160"
                  required
                >
                <div v-if="getFieldError('title')" class="invalid-feedback">
                  {{ getFieldError('title') }}
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="show-status">Estado</label>
                <select
                  id="show-status"
                  v-model="form.status"
                  class="form-select"
                  :class="{ 'is-invalid': getFieldError('status') }"
                  required
                >
                  <option value="draft">Borrador</option>
                  <option value="published">Publicada</option>
                  <option value="archived">Archivada</option>
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
                <label class="form-label" for="show-slug">Slug</label>
                <input
                  id="show-slug"
                  v-model.trim="form.slug"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('slug') }"
                  type="text"
                  maxlength="180"
                >
                <div v-if="getFieldError('slug')" class="invalid-feedback">
                  {{ getFieldError('slug') }}
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="show-age-rating">Público</label>
                <select
                  id="show-age-rating"
                  v-model="form.age_rating"
                  class="form-select"
                  :class="{ 'is-invalid': getFieldError('age_rating') }"
                  required
                >
                  <option value="ATP">ATP</option>
                  <option value="+13">+13</option>
                  <option value="+16">+16</option>
                  <option value="+18">+18</option>
                </select>
                <div v-if="getFieldError('age_rating')" class="invalid-feedback">
                  {{ getFieldError('age_rating') }}
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="show-genre">Género</label>
                <input
                  id="show-genre"
                  v-model.trim="form.genre"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('genre') }"
                  type="text"
                  maxlength="100"
                >
                <div v-if="getFieldError('genre')" class="invalid-feedback">
                  {{ getFieldError('genre') }}
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="show-format">Formato</label>
                <input
                  id="show-format"
                  v-model.trim="form.format"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('format') }"
                  type="text"
                  maxlength="100"
                >
                <div v-if="getFieldError('format')" class="invalid-feedback">
                  {{ getFieldError('format') }}
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="show-duration">Duración</label>
                <input
                  id="show-duration"
                  v-model="form.duration_minutes"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('duration_minutes') }"
                  type="number"
                  min="0"
                >
                <div v-if="getFieldError('duration_minutes')" class="invalid-feedback">
                  {{ getFieldError('duration_minutes') }}
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="show-main-image-path">Imagen principal</label>
            <input
              id="show-main-image-path"
              v-model.trim="form.main_image_path"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('main_image_path') }"
              type="text"
              maxlength="255"
            >
            <div v-if="getFieldError('main_image_path')" class="invalid-feedback">
              {{ getFieldError('main_image_path') }}
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label" for="show-description">Descripción</label>
            <textarea
              id="show-description"
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
