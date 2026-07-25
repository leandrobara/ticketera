<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import ShowService from '@/admin/services/ShowService';

  const emit = defineEmits(['saved']);

  // data
  const show = ref(null);
  const faqs = ref([]);
  const currentIndex = ref(null);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const form = reactive({
    question: '',
    answer: '',
    sort_order: '',
  });

  // computed
  const modalTitle = computed(() => show.value ? `Preguntas frecuentes de ${show.value.title}` : 'Preguntas frecuentes');
  const hasFaqs = computed(() => faqs.value.length > 0);
  const isEditing = computed(() => currentIndex.value !== null);
  const formButtonLabel = computed(() => isSubmitting.value ? 'Guardando...' : 'Guardar pregunta');
  const sortedFaqs = computed(() => {
    return [...faqs.value].sort((firstFaq, secondFaq) => {
      const firstOrder = Number(firstFaq.sort_order || 1);
      const secondOrder = Number(secondFaq.sort_order || 1);

      if (firstOrder === secondOrder) {
        return firstFaq.local_id - secondFaq.local_id;
      }

      return firstOrder - secondOrder;
    });
  });

  // methods
  const getInitialFaqs = (selectedShow) => {
    return (selectedShow.faqs ?? []).map((faq, index) => ({
      local_id: index + 1,
      question: faq.question ?? '',
      answer: faq.answer ?? '',
      sort_order: faq.sort_order ?? index + 1,
    }));
  };

  const resetForm = () => {
    currentIndex.value = null;
    form.question = '';
    form.answer = '';
    form.sort_order = '';
  };

  const resetErrors = () => {
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const getBackendFieldError = (field, index) => {
    return getFieldError(`faqs.${index}.${field}`);
  };

  const getNextSortOrder = () => {
    if (!hasFaqs.value) {
      return 1;
    }

    return Math.max(...faqs.value.map((faq) => Number(faq.sort_order || 1))) + 1;
  };

  const validateForm = () => {
    fieldErrors.value = {};

    if (!form.question.trim()) {
      fieldErrors.value.question = ['Ingresá la pregunta.'];
    }

    if (!form.answer.trim()) {
      fieldErrors.value.answer = ['Ingresá la respuesta.'];
    }

    if (form.sort_order !== '' && Number(form.sort_order) < 1) {
      fieldErrors.value.sort_order = ['El orden debe ser mayor o igual a 1.'];
    }

    return Object.keys(fieldErrors.value).length === 0;
  };

  const sortFaqs = (items) => {
    return [...items].sort((firstFaq, secondFaq) => {
      const firstOrder = Number(firstFaq.sort_order || 1);
      const secondOrder = Number(secondFaq.sort_order || 1);

      if (firstOrder === secondOrder) {
        return firstFaq.local_id - secondFaq.local_id;
      }

      return firstOrder - secondOrder;
    });
  };

  const getPayload = (items) => {
    return {
      service_fee_type: show.value.service_fee_type,
      service_fee_fixed_amount: show.value.service_fee_fixed_amount,
      service_fee_percentage: show.value.service_fee_percentage,
      service_fee_minimum_unit_amount: show.value.service_fee_minimum_unit_amount,
      faqs: sortFaqs(items).map((faq, index) => ({
        question: faq.question,
        answer: faq.answer,
        sort_order: faq.sort_order || index + 1,
      })),
    };
  };

  const setBackendErrors = (error) => {
    fieldErrors.value = error.response?.data?.errors
      ?? error.response?.data?.error?.fields
      ?? {};
    errorMessage.value = error.response?.data?.message
      ?? error.response?.data?.error?.message
      ?? 'No se pudieron guardar las preguntas frecuentes.';
  };

  const persistFaqs = async (nextFaqs) => {
    isSubmitting.value = true;

    try {
      const response = await ShowService.getInstance().updateShow(show.value.id, getPayload(nextFaqs));
      show.value = response.data.data;
      faqs.value = getInitialFaqs(show.value);
      emit('saved');
      return true;
    } catch (error) {
      setBackendErrors(error);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  };

  const addOrUpdateFaq = async () => {
    resetErrors();

    if (!validateForm()) {
      return;
    }

    const faq = {
      local_id: isEditing.value ? faqs.value[currentIndex.value].local_id : Date.now(),
      question: form.question.trim(),
      answer: form.answer.trim(),
      sort_order: form.sort_order === '' ? getNextSortOrder() : Number(form.sort_order),
    };

    const nextFaqs = [...faqs.value];

    if (isEditing.value) {
      nextFaqs[currentIndex.value] = faq;
    } else {
      nextFaqs.push(faq);
    }

    const wasSaved = await persistFaqs(nextFaqs);

    if (wasSaved) {
      resetForm();
    }
  };

  const editFaq = (faq) => {
    const index = faqs.value.findIndex((item) => item.local_id === faq.local_id);

    if (index === -1) {
      return;
    }

    resetErrors();
    currentIndex.value = index;
    form.question = faq.question;
    form.answer = faq.answer;
    form.sort_order = faq.sort_order;
  };

  const deleteFaq = async (faq) => {
    if (!window.confirm('¿Eliminar esta pregunta frecuente?')) {
      return;
    }

    const isDeletingEditedFaq = isEditing.value && faqs.value[currentIndex.value]?.local_id === faq.local_id;
    const nextFaqs = faqs.value.filter((item) => item.local_id !== faq.local_id);

    const wasSaved = await persistFaqs(nextFaqs);

    if (wasSaved && isDeletingEditedFaq) {
      resetForm();
    }
  };

  const open = async (selectedShow) => {
    show.value = selectedShow;
    faqs.value = getInitialFaqs(selectedShow);
    resetForm();
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
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title">{{ modalTitle }}</h5>
            <div class="text-secondary">Preguntas visibles en la ficha pública de la obra</div>
          </div>
          <button class="btn-close" type="button" aria-label="Close" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div class="card mb-4">
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label" for="show-faq-question">Pregunta</label>
                  <input
                    id="show-faq-question"
                    v-model.trim="form.question"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('question') }"
                    type="text"
                    maxlength="255"
                    placeholder="Ej: ¿Con cuánta anticipación conviene llegar?"
                  >
                  <div v-if="getFieldError('question')" class="invalid-feedback">
                    {{ getFieldError('question') }}
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label" for="show-faq-sort-order">Orden</label>
                  <input
                    id="show-faq-sort-order"
                    v-model="form.sort_order"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('sort_order') }"
                    type="number"
                    min="1"
                    placeholder="Automático"
                  >
                  <div v-if="getFieldError('sort_order')" class="invalid-feedback">
                    {{ getFieldError('sort_order') }}
                  </div>
                </div>

                <div class="col-12">
                  <label class="form-label" for="show-faq-answer">Respuesta</label>
                  <textarea
                    id="show-faq-answer"
                    v-model.trim="form.answer"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('answer') }"
                    rows="4"
                    placeholder="Escribí la respuesta que va a ver el público."
                  ></textarea>
                  <div v-if="getFieldError('answer')" class="invalid-feedback">
                    {{ getFieldError('answer') }}
                  </div>
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
                <button class="btn btn-success" type="button" :disabled="isSubmitting" @click="addOrUpdateFaq">
                  <span v-if="isSubmitting" aria-hidden="true" class="spinner-border spinner-border-sm me-2"></span>
                  {{ formButtonLabel }}
                </button>
              </div>
            </div>
          </div>

          <section>
            <h3 class="h4 mb-3">Preguntas cargadas</h3>

            <div v-if="!hasFaqs" class="empty border rounded">
              <p class="empty-title">No hay preguntas frecuentes</p>
              <p class="empty-subtitle text-secondary">
                Agregá las preguntas que ayuden a comprar o asistir a la función.
              </p>
            </div>

            <div v-else class="list-group">
              <div v-for="(faq, index) in sortedFaqs" :key="faq.local_id" class="list-group-item">
                <div class="row align-items-start g-3">
                  <div class="col-auto">
                    <span class="badge bg-secondary-lt">{{ faq.sort_order || index + 1 }}</span>
                  </div>
                  <div class="col">
                    <div class="fw-semibold">{{ faq.question }}</div>
                    <div class="text-secondary mt-1" style="white-space: pre-line;">{{ faq.answer }}</div>
                    <div
                      v-if="getBackendFieldError('question', index) || getBackendFieldError('answer', index)"
                      class="text-danger small mt-2"
                    >
                      {{ getBackendFieldError('question', index) || getBackendFieldError('answer', index) }}
                    </div>
                  </div>
                  <div class="col-auto">
                    <div class="btn-list flex-nowrap">
                      <button
                        class="btn btn-sm btn-outline-primary"
                        type="button"
                        :disabled="isSubmitting"
                        @click="editFaq(faq)"
                      >
                        Editar
                      </button>
                      <button
                        class="btn btn-sm btn-outline-danger"
                        type="button"
                        :disabled="isSubmitting"
                        @click="deleteFaq(faq)"
                      >
                        Eliminar
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>
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
