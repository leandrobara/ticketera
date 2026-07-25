<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import ImageService from '@/admin/services/ImageService';

  const emit = defineEmits(['saved']);

  const show = ref(null);
  const images = ref([]);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const mainImageInput = ref(null);
  const galleryImageInput = ref(null);
  const isLoading = ref(false);
  const isSubmittingMain = ref(false);
  const isSubmittingGallery = ref(false);
  const mainPreviewUrl = ref('');
  const galleryPreviewUrl = ref('');
  const mainForm = reactive({
    image: null,
    alt_text: '',
    caption: '',
  });
  const galleryForm = reactive({
    image: null,
    alt_text: '',
    caption: '',
    sort_order: '',
  });

  const modalTitle = computed(() => show.value ? `Imágenes de ${show.value.title}` : 'Imágenes');
  const mainImage = computed(() => images.value.find((image) => image.is_main) ?? null);
  const galleryImages = computed(() => images.value.filter((image) => image.type === 'gallery'));
  const mainImageUrl = computed(() => mainPreviewUrl.value || mainImage.value?.url || '');
  const gallerySubmitLabel = computed(() => isSubmittingGallery.value ? 'Subiendo...' : 'Agregar a galería');
  const mainSubmitLabel = computed(() => {
    if (isSubmittingMain.value) {
      return 'Guardando...';
    }

    return mainImage.value ? 'Guardar imagen principal' : 'Subir imagen principal';
  });

  const resetFieldErrors = () => {
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const resetMainForm = () => {
    mainForm.image = null;
    mainForm.alt_text = mainImage.value?.alt_text ?? '';
    mainForm.caption = mainImage.value?.caption ?? '';

    if (mainImageInput.value) {
      mainImageInput.value.value = '';
    }
  };

  const resetGalleryForm = () => {
    galleryForm.image = null;
    galleryForm.alt_text = '';
    galleryForm.caption = '';
    galleryForm.sort_order = '';
    galleryPreviewUrl.value = '';

    if (galleryImageInput.value) {
      galleryImageInput.value.value = '';
    }
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const getImageFormData = (form, type, isMain) => {
    const payload = new FormData();

    payload.append('show_id', show.value.id);
    payload.append('type', type);
    payload.append('is_main', isMain ? '1' : '0');

    if (form.image) {
      payload.append('image', form.image);
    }

    payload.append('alt_text', form.alt_text);
    payload.append('caption', form.caption);

    if (!isMain) {
      payload.append('sort_order', form.sort_order === '' ? '1' : form.sort_order);
    }

    return payload;
  };

  const loadImages = async () => {
    const response = await ImageService.getInstance().getImages({
      show_id: show.value.id,
      per_page: 100,
    });
    images.value = response.data.data.data ?? [];
    resetMainForm();
  };

  const loadData = async () => {
    isLoading.value = true;
    resetFieldErrors();

    try {
      await loadImages();
    } catch (error) {
      errorMessage.value = 'No se pudieron cargar las imágenes del show.';
    } finally {
      isLoading.value = false;
    }
  };

  const open = async (selectedShow) => {
    show.value = selectedShow;
    images.value = [];
    resetFieldErrors();
    resetGalleryForm();
    mainPreviewUrl.value = '';
    await nextTick();
    modalInstance.value.show();
    await loadData();
  };

  const close = () => {
    modalInstance.value.hide();
  };

  const onMainFileChange = (event) => {
    mainForm.image = event.target.files?.[0] ?? null;
    mainPreviewUrl.value = mainForm.image ? URL.createObjectURL(mainForm.image) : '';
  };

  const onGalleryFileChange = (event) => {
    galleryForm.image = event.target.files?.[0] ?? null;
    galleryPreviewUrl.value = galleryForm.image ? URL.createObjectURL(galleryForm.image) : '';
  };

  const saveMainImage = async () => {
    resetFieldErrors();

    if (!mainImage.value && !mainForm.image) {
      fieldErrors.value = { image: ['Seleccioná una imagen JPG o JPEG.'] };
      return;
    }

    isSubmittingMain.value = true;

    try {
      const payload = getImageFormData(mainForm, 'grid', true);

      if (mainImage.value) {
        await ImageService.getInstance().updateImage(mainImage.value.id, payload);
      } else {
        await ImageService.getInstance().createImage(payload);
      }

      mainPreviewUrl.value = '';
      await loadImages();
      emit('saved');
    } catch (error) {
      fieldErrors.value = error.response?.data?.errors ?? {};
      errorMessage.value = error.response?.data?.message ?? 'No se pudo guardar la imagen principal.';
    } finally {
      isSubmittingMain.value = false;
    }
  };

  const saveGalleryImage = async () => {
    resetFieldErrors();

    if (!galleryForm.image) {
      fieldErrors.value = { gallery_image: ['Seleccioná una imagen JPG o JPEG.'] };
      return;
    }

    isSubmittingGallery.value = true;

    try {
      await ImageService.getInstance().createImage(getImageFormData(galleryForm, 'gallery', false));
      resetGalleryForm();
      await loadImages();
      emit('saved');
    } catch (error) {
      fieldErrors.value = error.response?.data?.errors ?? {};
      errorMessage.value = error.response?.data?.message ?? 'No se pudo agregar la imagen a la galería.';
    } finally {
      isSubmittingGallery.value = false;
    }
  };

  const deleteImage = async (image) => {
    if (!window.confirm('¿Eliminar esta imagen?')) {
      return;
    }

    isLoading.value = true;
    resetFieldErrors();

    try {
      await ImageService.getInstance().deleteImage(image.id);
      await loadImages();
      emit('saved');
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar la imagen.';
    } finally {
      isLoading.value = false;
    }
  };

  defineExpose({
    open,
  });

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
            <div class="text-secondary">Imagen principal y galería</div>
          </div>
          <button class="btn-close" type="button" aria-label="Close" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div v-if="isLoading" class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Cargando imágenes...</span>
          </div>

          <template v-else>
            <section class="mb-4">
              <h3 class="h4 mb-3">Imagen principal</h3>

              <div class="row g-3">
                <div class="col-md-5">
                  <div class="show-image-preview">
                    <img v-if="mainImageUrl" :src="mainImageUrl" :alt="mainForm.alt_text || show?.title">
                    <div v-else class="text-secondary">Sin imagen principal</div>
                  </div>
                </div>

                <div class="col-md-7">
                  <div class="mb-3">
                    <label class="form-label" for="show-main-image">Archivo</label>
                    <input
                      id="show-main-image"
                      ref="mainImageInput"
                      class="form-control"
                      :class="{ 'is-invalid': getFieldError('image') }"
                      type="file"
                      accept=".jpg,.jpeg,image/jpeg"
                      @change="onMainFileChange"
                    >
                    <div v-if="getFieldError('image')" class="invalid-feedback">
                      {{ getFieldError('image') }}
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label" for="show-main-alt-text">Texto alternativo</label>
                    <input
                      id="show-main-alt-text"
                      v-model.trim="mainForm.alt_text"
                      class="form-control"
                      :class="{ 'is-invalid': getFieldError('alt_text') }"
                      type="text"
                      maxlength="255"
                    >
                    <div v-if="getFieldError('alt_text')" class="invalid-feedback">
                      {{ getFieldError('alt_text') }}
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label" for="show-main-caption">Epígrafe</label>
                    <textarea
                      id="show-main-caption"
                      v-model.trim="mainForm.caption"
                      class="form-control"
                      :class="{ 'is-invalid': getFieldError('caption') }"
                      rows="2"
                    ></textarea>
                    <div v-if="getFieldError('caption')" class="invalid-feedback">
                      {{ getFieldError('caption') }}
                    </div>
                  </div>

                  <div class="btn-list">
                    <button class="btn btn-primary" type="button" :disabled="isSubmittingMain" @click="saveMainImage">
                      <span
                        v-if="isSubmittingMain"
                        aria-hidden="true"
                        class="spinner-border spinner-border-sm me-2"
                      ></span>
                      {{ mainSubmitLabel }}
                    </button>
                    <button
                      v-if="mainImage"
                      class="btn btn-outline-danger"
                      type="button"
                      :disabled="isSubmittingMain"
                      @click="deleteImage(mainImage)"
                    >
                      Eliminar principal
                    </button>
                  </div>
                </div>
              </div>
            </section>

            <section>
              <h3 class="h4 mb-3">Galería</h3>

              <div class="row g-3 mb-4">
                <div class="col-md-4">
                  <div class="show-image-preview show-image-preview-gallery">
                    <img v-if="galleryPreviewUrl" :src="galleryPreviewUrl" :alt="galleryForm.alt_text || 'Vista previa'">
                    <div v-else class="text-secondary">Vista previa</div>
                  </div>
                </div>

                <div class="col-md-8">
                  <div class="row g-3">
                    <div class="col-md-8">
                      <label class="form-label" for="show-gallery-image">Archivo</label>
                      <input
                        id="show-gallery-image"
                        ref="galleryImageInput"
                        class="form-control"
                        :class="{ 'is-invalid': getFieldError('gallery_image') || getFieldError('image') }"
                        type="file"
                        accept=".jpg,.jpeg,image/jpeg"
                        @change="onGalleryFileChange"
                      >
                      <div v-if="getFieldError('gallery_image') || getFieldError('image')" class="invalid-feedback">
                        {{ getFieldError('gallery_image') || getFieldError('image') }}
                      </div>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label" for="show-gallery-sort-order">Orden</label>
                      <input
                        id="show-gallery-sort-order"
                        v-model="galleryForm.sort_order"
                        class="form-control"
                        :class="{ 'is-invalid': getFieldError('sort_order') }"
                        type="number"
                        min="1"
                      >
                      <div v-if="getFieldError('sort_order')" class="invalid-feedback">
                        {{ getFieldError('sort_order') }}
                      </div>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label" for="show-gallery-alt-text">Texto alternativo</label>
                      <input
                        id="show-gallery-alt-text"
                        v-model.trim="galleryForm.alt_text"
                        class="form-control"
                        :class="{ 'is-invalid': getFieldError('alt_text') }"
                        type="text"
                        maxlength="255"
                      >
                    </div>

                    <div class="col-md-6">
                      <label class="form-label" for="show-gallery-caption">Epígrafe</label>
                      <input
                        id="show-gallery-caption"
                        v-model.trim="galleryForm.caption"
                        class="form-control"
                        :class="{ 'is-invalid': getFieldError('caption') }"
                        type="text"
                      >
                    </div>
                  </div>

                  <button
                    class="btn btn-primary mt-3"
                    type="button"
                    :disabled="isSubmittingGallery"
                    @click="saveGalleryImage"
                  >
                    <span
                      v-if="isSubmittingGallery"
                      aria-hidden="true"
                      class="spinner-border spinner-border-sm me-2"
                    ></span>
                    {{ gallerySubmitLabel }}
                  </button>
                </div>
              </div>

              <div v-if="galleryImages.length === 0" class="empty border rounded">
                <p class="empty-title">No hay imágenes de galería</p>
              </div>

              <div v-else class="row g-3">
                <div v-for="image in galleryImages" :key="image.id" class="col-md-4">
                  <div class="card">
                    <div class="show-gallery-thumb">
                      <img v-if="image.url" :src="image.url" :alt="image.alt_text || show?.title">
                    </div>
                    <div class="card-body">
                      <div class="fw-semibold">Orden {{ image.sort_order }}</div>
                      <div v-if="image.caption" class="text-secondary small">
                        {{ image.caption }}
                      </div>
                      <button class="btn btn-sm btn-outline-danger mt-3" type="button" @click="deleteImage(image)">
                        Eliminar
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </section>
          </template>
        </div>

        <div class="modal-footer">
          <button class="btn btn-link link-secondary" type="button" @click="close">
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
