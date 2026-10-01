<script setup>
import { inject, reactive, ref } from 'vue';
import ApiService from '@/admin/services/ApiService';

const { user } = inject('adminSession');
const form = reactive({
  name: user.value.name ?? '',
  email: user.value.email ?? '',
  current_password: '',
  password: '',
  password_confirmation: '',
});
const busy = ref(false);
const saved = ref(false);
const error = ref('');
const fieldErrors = ref({});

async function save() {
  if (busy.value) return;
  busy.value = true;
  saved.value = false;
  error.value = '';
  fieldErrors.value = {};
  try {
    const response = await ApiService.getInstance().put('/api/admin/auth/me', form);
    user.value = response.data.data.user;
    form.current_password = '';
    form.password = '';
    form.password_confirmation = '';
    saved.value = true;
  } catch (exception) {
    error.value = exception.response?.data?.message ?? 'No se pudieron guardar tus datos.';
    fieldErrors.value = exception.response?.data?.errors ?? {};
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="admin-form-page">
    <a class="d-inline-block mb-3" href="/admin/organizations">← Mis organizaciones</a>
    <div class="page-header mb-4">
      <h1 class="page-title">Mi cuenta</h1>
      <p class="text-secondary mt-2 mb-0">Estos datos son tuyos y se usan en todas las organizaciones a las que pertenecés.</p>
    </div>

    <form class="card" @submit.prevent="save">
      <div class="card-body">
        <div v-if="saved" class="alert alert-success" role="status">Tus datos se guardaron correctamente.</div>
        <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>

        <div class="mb-3">
          <label class="form-label" for="account-name">Nombre</label>
          <input id="account-name" v-model="form.name" class="form-control" :class="{'is-invalid': fieldErrors.name}" autocomplete="name" maxlength="255" required>
          <div v-if="fieldErrors.name" class="invalid-feedback">{{ fieldErrors.name[0] }}</div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="account-email">Email de acceso</label>
          <input id="account-email" v-model="form.email" class="form-control" :class="{'is-invalid': fieldErrors.email}" type="email" autocomplete="email" maxlength="255" required>
          <div v-if="fieldErrors.email" class="invalid-feedback">{{ fieldErrors.email[0] }}</div>
        </div>

        <h2 class="h3 mb-3">Cambiar contraseña</h2>
        <p class="text-secondary small">Dejá estos campos vacíos si no querés cambiarla.</p>
        <div class="mb-3">
          <label class="form-label" for="account-password">Nueva contraseña</label>
          <input id="account-password" v-model="form.password" class="form-control" :class="{'is-invalid': fieldErrors.password}" type="password" autocomplete="new-password" minlength="8">
          <div v-if="fieldErrors.password" class="invalid-feedback">{{ fieldErrors.password[0] }}</div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="account-password-confirmation">Confirmar nueva contraseña</label>
          <input id="account-password-confirmation" v-model="form.password_confirmation" class="form-control" type="password" autocomplete="new-password">
        </div>
        <div class="mb-0">
          <label class="form-label" for="account-current-password">Contraseña actual <span class="text-danger">*</span></label>
          <input id="account-current-password" v-model="form.current_password" class="form-control" :class="{'is-invalid': fieldErrors.current_password}" type="password" autocomplete="current-password" required>
          <div v-if="fieldErrors.current_password" class="invalid-feedback">{{ fieldErrors.current_password[0] }}</div>
          <div class="form-hint">Necesaria para confirmar cualquier cambio en tu cuenta.</div>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-success" type="submit" :disabled="busy">{{ busy ? 'Guardando…' : 'Guardar cambios' }}</button>
      </div>
    </form>
  </div>
</template>
