<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import BuyerService from '@/admin/services/BuyerService';

  // emits
  const emit = defineEmits(['saved']);

  // data
  const mode = ref('create');
  const currentBuyer = ref(null);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const form = reactive({
    dni: '',
    name: '',
    email: '',
    phone: '',
    last_name: '',
  });

  // computed
  const isEditing = computed(() => mode.value === 'update');
  const modalTitle = computed(() => isEditing.value ? 'Editar comprador' : 'Crear un nuevo comprador');
  const submitLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Crear comprador');

  // methods
  const resetForm = () => {
    form.dni = '';
    form.name = '';
    form.email = '';
    form.phone = '';
    form.last_name = '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const fillForm = (buyer) => {
    form.dni = buyer.dni ?? '';
    form.name = buyer.name ?? '';
    form.email = buyer.email ?? '';
    form.phone = buyer.phone ?? '';
    form.last_name = buyer.last_name ?? '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const nullable = (value) => {
    return value === '' ? null : value;
  };

  const getPayload = () => {
    if (isEditing.value) {
      return {
        name: nullable(form.name),
        email: nullable(form.email),
        phone: nullable(form.phone),
      };
    }

    return {
      dni: nullable(form.dni),
      name: form.name,
      email: form.email,
      phone: nullable(form.phone),
      last_name: nullable(form.last_name),
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
    currentBuyer.value = null;
    resetForm();
    await showModal();
  };

  const openForUpdate = async (buyer) => {
    mode.value = 'update';
    currentBuyer.value = buyer;
    fillForm(buyer);
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
        await BuyerService.getInstance().updateBuyer(currentBuyer.value.id, getPayload());
      } else {
        await BuyerService.getInstance().createBuyer(getPayload());
      }

      emit('saved');
      close();
    } catch (error) {
      fieldErrors.value = error.response?.data?.errors ?? {};
      errorMessage.value = error.response?.data?.message ?? 'No se pudo guardar el comprador.';
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
                <label class="form-label" for="buyer-name">Nombre</label>
                <input
                  id="buyer-name"
                  v-model.trim="form.name"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('name') }"
                  type="text"
                  maxlength="160"
                  required
                >
                <div v-if="getFieldError('name')" class="invalid-feedback">
                  {{ getFieldError('name') }}
                </div>
              </div>
            </div>

            <div v-if="!isEditing" class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="buyer-last-name">Apellido</label>
                <input
                  id="buyer-last-name"
                  v-model.trim="form.last_name"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('last_name') }"
                  type="text"
                  maxlength="160"
                >
                <div v-if="getFieldError('last_name')" class="invalid-feedback">
                  {{ getFieldError('last_name') }}
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="buyer-email">Email</label>
                <input
                  id="buyer-email"
                  v-model.trim="form.email"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('email') }"
                  type="email"
                  maxlength="255"
                  required
                >
                <div v-if="getFieldError('email')" class="invalid-feedback">
                  {{ getFieldError('email') }}
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="buyer-phone">Teléfono</label>
                <input
                  id="buyer-phone"
                  v-model.trim="form.phone"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('phone') }"
                  type="text"
                  maxlength="40"
                >
                <div v-if="getFieldError('phone')" class="invalid-feedback">
                  {{ getFieldError('phone') }}
                </div>
              </div>
            </div>
          </div>

          <div v-if="!isEditing" class="mb-0">
            <label class="form-label" for="buyer-dni">DNI</label>
            <input
              id="buyer-dni"
              v-model.trim="form.dni"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('dni') }"
              type="text"
              maxlength="20"
            >
            <div v-if="getFieldError('dni')" class="invalid-feedback">
              {{ getFieldError('dni') }}
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
