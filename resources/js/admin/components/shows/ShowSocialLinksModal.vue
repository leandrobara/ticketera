<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import ShowService from '@/admin/services/ShowService';

  // emits
  const emit = defineEmits(['saved']);

  // data
  const show = ref(null);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const form = reactive({
    instagram_url: '',
    facebook_url: '',
    x_url: '',
    tiktok_url: '',
    youtube_url: '',
    pinterest_url: '',
    website_url: '',
  });

  // computed
  const modalTitle = computed(() => {
    return show.value ? `Redes de ${show.value.title}` : 'Redes del show';
  });

  // methods
  const nullable = (value) => value === '' ? null : value;

  const resetErrors = () => {
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const fillForm = (selectedShow) => {
    form.instagram_url = selectedShow.instagram_url ?? '';
    form.facebook_url = selectedShow.facebook_url ?? '';
    form.x_url = selectedShow.x_url ?? '';
    form.tiktok_url = selectedShow.tiktok_url ?? '';
    form.youtube_url = selectedShow.youtube_url ?? '';
    form.pinterest_url = selectedShow.pinterest_url ?? '';
    form.website_url = selectedShow.website_url ?? '';
  };

  const getPayload = () => {
    return {
      service_fee_type: show.value.service_fee_type,
      service_fee_fixed_amount: show.value.service_fee_fixed_amount,
      service_fee_percentage: show.value.service_fee_percentage,
      service_fee_minimum_unit_amount: show.value.service_fee_minimum_unit_amount,
      instagram_url: nullable(form.instagram_url),
      facebook_url: nullable(form.facebook_url),
      x_url: nullable(form.x_url),
      tiktok_url: nullable(form.tiktok_url),
      youtube_url: nullable(form.youtube_url),
      pinterest_url: nullable(form.pinterest_url),
      website_url: nullable(form.website_url),
    };
  };

  const save = async () => {
    resetErrors();
    isSubmitting.value = true;

    try {
      const response = await ShowService.getInstance().updateShow(show.value.id, getPayload());
      show.value = response.data.data;
      fillForm(show.value);
      emit('saved');
      modalInstance.value.hide();
    } catch (error) {
      fieldErrors.value = error.response?.data?.errors
        ?? error.response?.data?.error?.fields
        ?? {};
      errorMessage.value = error.response?.data?.message
        ?? error.response?.data?.error?.message
        ?? 'No se pudieron guardar las redes del show.';
    } finally {
      isSubmitting.value = false;
    }
  };

  const open = async (selectedShow) => {
    show.value = selectedShow;
    fillForm(selectedShow);
    resetErrors();
    await nextTick();
    modalInstance.value.show();
  };

  const close = () => {
    modalInstance.value.hide();
  };

  defineExpose({
    open,
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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
      <form class="modal-content" @submit.prevent="save">
        <div class="modal-header">
          <div>
            <h5 class="modal-title">{{ modalTitle }}</h5>
            <div class="text-secondary">Enlaces que podrán mostrarse en la ficha pública</div>
          </div>
          <button class="btn-close" type="button" aria-label="Cerrar" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="show-instagram-url">Instagram</label>
              <input id="show-instagram-url" v-model.trim="form.instagram_url" class="form-control" :class="{ 'is-invalid': getFieldError('instagram_url') }" type="url" placeholder="https://instagram.com/...">
              <div v-if="getFieldError('instagram_url')" class="invalid-feedback">{{ getFieldError('instagram_url') }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="show-facebook-url">Facebook</label>
              <input id="show-facebook-url" v-model.trim="form.facebook_url" class="form-control" :class="{ 'is-invalid': getFieldError('facebook_url') }" type="url" placeholder="https://facebook.com/...">
              <div v-if="getFieldError('facebook_url')" class="invalid-feedback">{{ getFieldError('facebook_url') }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="show-x-url">X</label>
              <input id="show-x-url" v-model.trim="form.x_url" class="form-control" :class="{ 'is-invalid': getFieldError('x_url') }" type="url" placeholder="https://x.com/...">
              <div v-if="getFieldError('x_url')" class="invalid-feedback">{{ getFieldError('x_url') }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="show-tiktok-url">TikTok</label>
              <input id="show-tiktok-url" v-model.trim="form.tiktok_url" class="form-control" :class="{ 'is-invalid': getFieldError('tiktok_url') }" type="url" placeholder="https://tiktok.com/@...">
              <div v-if="getFieldError('tiktok_url')" class="invalid-feedback">{{ getFieldError('tiktok_url') }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="show-youtube-url">YouTube</label>
              <input id="show-youtube-url" v-model.trim="form.youtube_url" class="form-control" :class="{ 'is-invalid': getFieldError('youtube_url') }" type="url" placeholder="https://youtube.com/...">
              <div v-if="getFieldError('youtube_url')" class="invalid-feedback">{{ getFieldError('youtube_url') }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="show-pinterest-url">Pinterest</label>
              <input id="show-pinterest-url" v-model.trim="form.pinterest_url" class="form-control" :class="{ 'is-invalid': getFieldError('pinterest_url') }" type="url" placeholder="https://pinterest.com/...">
              <div v-if="getFieldError('pinterest_url')" class="invalid-feedback">{{ getFieldError('pinterest_url') }}</div>
            </div>

            <div class="col-12">
              <label class="form-label" for="show-website-url">Sitio web oficial</label>
              <input id="show-website-url" v-model.trim="form.website_url" class="form-control" :class="{ 'is-invalid': getFieldError('website_url') }" type="url" placeholder="https://...">
              <div v-if="getFieldError('website_url')" class="invalid-feedback">{{ getFieldError('website_url') }}</div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-link link-secondary" type="button" @click="close">Cancelar</button>
          <button class="btn btn-success ms-auto" type="submit" :disabled="isSubmitting">
            <span v-if="isSubmitting" aria-hidden="true" class="spinner-border spinner-border-sm me-2"></span>
            {{ isSubmitting ? 'Guardando...' : 'Guardar redes' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
