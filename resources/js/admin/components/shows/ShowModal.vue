<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import { normalizeDecimalInput } from '@/admin/helpers/number';
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
    subtitle: '',
    slug: '',
    synopsis: '',
    additional_information: '',
    production_note: '',
    genre: '',
    format: '',
    service_fee_type: 'fixed_amount',
    service_fee_fixed_amount: '0',
    service_fee_percentage: '',
    service_fee_minimum_unit_amount: '2000',
    duration_minutes: '',
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
    form.subtitle = '';
    form.genre = '';
    form.format = '';
    form.service_fee_type = 'fixed_amount';
    form.service_fee_fixed_amount = '0';
    form.service_fee_percentage = '';
    form.service_fee_minimum_unit_amount = '2000';
    form.synopsis = '';
    form.additional_information = '';
    form.production_note = '';
    fieldErrors.value = {};
    form.age_rating = 'ATP';
    errorMessage.value = '';
    form.duration_minutes = '';
  };

  const fillForm = (show) => {
    form.slug = show.slug ?? '';
    form.title = show.title ?? '';
    form.subtitle = show.subtitle ?? '';
    form.genre = show.genre ?? '';
    form.format = show.format ?? '';
    form.service_fee_type = show.service_fee_type ?? 'fixed_amount';
    form.service_fee_fixed_amount = normalizeDecimalInput(show.service_fee_fixed_amount);
    form.service_fee_percentage = normalizeDecimalInput(show.service_fee_percentage);
    form.service_fee_minimum_unit_amount = normalizeDecimalInput(show.service_fee_minimum_unit_amount);
    form.synopsis = show.synopsis ?? '';
    form.additional_information = show.additional_information ?? '';
    form.production_note = show.production_note ?? '';
    form.age_rating = show.age_rating ?? 'ATP';
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
      subtitle: nullable(form.subtitle),
      slug: nullable(form.slug),
      synopsis: nullable(form.synopsis),
      additional_information: nullable(form.additional_information),
      production_note: nullable(form.production_note),
      genre: nullable(form.genre),
      format: nullable(form.format),
      service_fee_type: form.service_fee_type,
      service_fee_fixed_amount: form.service_fee_type === 'fixed_amount'
        ? (form.service_fee_fixed_amount === '' ? 0 : form.service_fee_fixed_amount)
        : null,
      service_fee_percentage: form.service_fee_type === 'percentage'
        ? form.service_fee_percentage
        : null,
      service_fee_minimum_unit_amount: nullable(form.service_fee_minimum_unit_amount),
      duration_minutes: form.duration_minutes === '' ? null : Number(form.duration_minutes),
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
            <div class="col-12">
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

          </div>

          <div class="mb-3">
            <label class="form-label" for="show-subtitle">Subtítulo</label>
            <input
              id="show-subtitle"
              v-model.trim="form.subtitle"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('subtitle') }"
              type="text"
              maxlength="255"
              placeholder="Texto breve que acompaña el título en la ficha pública"
            >
            <div v-if="getFieldError('subtitle')" class="invalid-feedback">
              {{ getFieldError('subtitle') }}
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="show-synopsis">Sinopsis</label>
            <textarea
              id="show-synopsis"
              v-model.trim="form.synopsis"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('synopsis') }"
              rows="4"
            ></textarea>
            <div v-if="getFieldError('synopsis')" class="invalid-feedback">
              {{ getFieldError('synopsis') }}
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="show-additional-information">Información adicional</label>
            <textarea
              id="show-additional-information"
              v-model.trim="form.additional_information"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('additional_information') }"
              rows="4"
              placeholder="Premios, reconocimientos u otra información destacada de la obra"
            ></textarea>
            <div v-if="getFieldError('additional_information')" class="invalid-feedback">
              {{ getFieldError('additional_information') }}
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

          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="show-service-fee-type">Tipo de fee</label>
                <select
                  id="show-service-fee-type"
                  v-model="form.service_fee_type"
                  class="form-select"
                  :class="{ 'is-invalid': getFieldError('service_fee_type') }"
                  required
                >
                  <option value="fixed_amount">Monto fijo</option>
                  <option value="percentage">Porcentaje</option>
                </select>
                <div v-if="getFieldError('service_fee_type')" class="invalid-feedback">
                  {{ getFieldError('service_fee_type') }}
                </div>
              </div>
            </div>

            <div v-if="form.service_fee_type === 'fixed_amount'" class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="show-service-fee-fixed-amount">Monto fijo</label>
                <input
                  id="show-service-fee-fixed-amount"
                  v-model="form.service_fee_fixed_amount"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('service_fee_fixed_amount') }"
                  type="number"
                  min="0"
                  step="0.000001"
                  required
                >
                <div v-if="getFieldError('service_fee_fixed_amount')" class="invalid-feedback">
                  {{ getFieldError('service_fee_fixed_amount') }}
                </div>
              </div>
            </div>

            <div v-else class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="show-service-fee-percentage">Porcentaje</label>
                <div class="input-group">
                  <input
                    id="show-service-fee-percentage"
                    v-model="form.service_fee_percentage"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('service_fee_percentage') }"
                    type="number"
                    min="0"
                    max="100"
                    step="0.000001"
                    required
                  >
                  <span class="input-group-text">%</span>
                  <div v-if="getFieldError('service_fee_percentage')" class="invalid-feedback">
                    {{ getFieldError('service_fee_percentage') }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="show-service-fee-minimum-unit-amount">
                  Fee mínimo por entrada pagada (opcional)
                </label>
                <input
                  id="show-service-fee-minimum-unit-amount"
                  v-model="form.service_fee_minimum_unit_amount"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('service_fee_minimum_unit_amount') }"
                  type="number"
                  min="0"
                  step="0.000001"
                >
                <div v-if="getFieldError('service_fee_minimum_unit_amount')" class="invalid-feedback">
                  {{ getFieldError('service_fee_minimum_unit_amount') }}
                </div>
              </div>
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label" for="show-production-note">Nota de la producción</label>
            <textarea
              id="show-production-note"
              v-model.trim="form.production_note"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('production_note') }"
              rows="3"
            ></textarea>
            <div v-if="getFieldError('production_note')" class="invalid-feedback">
              {{ getFieldError('production_note') }}
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
