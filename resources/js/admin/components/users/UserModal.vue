<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import UserService from '@/admin/services/UserService';

  // emits
  const emit = defineEmits(['saved']);

  // data
  const roles = [
    { value: 'admin', label: 'Admin' },
    { value: 'operador', label: 'Operador' },
    { value: 'puerta', label: 'Puerta' },
  ];
  const mode = ref('create');
  const currentUser = ref(null);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const form = reactive({
    name: '',
    email: '',
    role: 'operador',
    password: '',
  });

  // computed
  const isEditing = computed(() => mode.value === 'update');
  const modalTitle = computed(() => isEditing.value ? 'Editar usuario' : 'Crear usuario');
  const submitLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Crear usuario');
  const passwordHelp = computed(() => {
    return isEditing.value
      ? 'Dejalo vacío para conservar la contraseña actual.'
      : 'Mínimo 8 caracteres.';
  });

  // methods
  const resetForm = () => {
    form.name = '';
    form.email = '';
    form.role = 'operador';
    form.password = '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const fillForm = (user) => {
    form.name = user.name ?? '';
    form.email = user.email ?? '';
    form.role = user.role ?? 'operador';
    form.password = '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const getPayload = () => {
    const payload = {
      name: form.name,
      email: form.email,
      role: form.role,
    };

    if (!isEditing.value || form.password !== '') {
      payload.password = form.password;
    }

    return payload;
  };

  const getResponseFields = (error) => {
    return error.response?.data?.error?.fields ?? error.response?.data?.errors ?? {};
  };

  const getResponseMessage = (error) => {
    return error.response?.data?.error?.message
      ?? error.response?.data?.message
      ?? 'No se pudo guardar el usuario.';
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
    currentUser.value = null;
    resetForm();
    await showModal();
  };

  const openForUpdate = async (user) => {
    mode.value = 'update';
    currentUser.value = user;
    fillForm(user);
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
        await UserService.getInstance().updateUser(currentUser.value.id, getPayload());
      } else {
        await UserService.getInstance().createUser(getPayload());
      }

      emit('saved');
      close();
    } catch (error) {
      fieldErrors.value = getResponseFields(error);
      errorMessage.value = getResponseMessage(error);
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
    <div class="modal-dialog modal-dialog-centered" role="document">
      <form class="modal-content" @submit.prevent="submit">
        <div class="modal-header">
          <h5 class="modal-title">{{ modalTitle }}</h5>
          <button class="btn-close" type="button" aria-label="Close" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div class="mb-3">
            <label class="form-label" for="user-name">Nombre</label>
            <input
              id="user-name"
              v-model.trim="form.name"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('name') }"
              type="text"
              maxlength="255"
              required
              autocomplete="name"
            >
            <div v-if="getFieldError('name')" class="invalid-feedback">
              {{ getFieldError('name') }}
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="user-email">Email</label>
            <input
              id="user-email"
              v-model.trim="form.email"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('email') }"
              type="email"
              maxlength="255"
              required
              autocomplete="email"
            >
            <div v-if="getFieldError('email')" class="invalid-feedback">
              {{ getFieldError('email') }}
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="user-role">Rol</label>
            <select
              id="user-role"
              v-model="form.role"
              class="form-select"
              :class="{ 'is-invalid': getFieldError('role') }"
              required
            >
              <option v-for="role in roles" :key="role.value" :value="role.value">
                {{ role.label }}
              </option>
            </select>
            <div v-if="getFieldError('role')" class="invalid-feedback">
              {{ getFieldError('role') }}
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label" for="user-password">Contraseña</label>
            <input
              id="user-password"
              v-model="form.password"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('password') }"
              type="password"
              minlength="8"
              maxlength="255"
              :required="!isEditing"
              autocomplete="new-password"
            >
            <div v-if="getFieldError('password')" class="invalid-feedback">
              {{ getFieldError('password') }}
            </div>
            <div v-else class="form-hint">
              {{ passwordHelp }}
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-link link-secondary" type="button" :disabled="isSubmitting" @click="close">
            Cancelar
          </button>
          <button class="btn btn-primary" type="submit" :disabled="isSubmitting">
            <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
            {{ submitLabel }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
