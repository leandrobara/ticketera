<script setup>
  import { reactive, ref } from 'vue';
  import LoginService from '@/admin/services/LoginService';
  import ApiService from '@/admin/services/ApiService';

  // emits
  const emit = defineEmits(['login']);

  // data
  const errorMessage = ref('');
  const isSubmitting = ref(false);
  const isRegistering = ref(false);
  const hasInvitation = Boolean(sessionStorage.getItem('organization_invitation'));
  const form = reactive({
    email: '',
    password: '',
    name: '',
    password_confirmation: '',
  });

  // methods
  const submit = async () => {
    isSubmitting.value = true;
    errorMessage.value = '';

    try {
      if (isRegistering.value) await ApiService.getInstance().post('/api/admin/auth/register', {...form});
      const response = await LoginService.getInstance().login({
        email: form.email,
        password: form.password,
      });

      emit('login', response.data.data);
    } catch (error) {
      errorMessage.value = error.response?.data?.error?.message ?? error.response?.data?.message ?? 'Email o contraseña incorrectos.';
    } finally {
      isSubmitting.value = false;
    }
  };
</script>

<template>
  <div class="page page-center">
    <div class="container-tight py-4">
      <div class="text-center mb-4">
        <a class="navbar-brand navbar-brand-autodark justify-content-center" href="/admin/login" aria-label="Entradatix">
          <img class="admin-login-brand-logo" :src="'/brand/entradatix-logo.png'" alt="Entradatix">
        </a>
      </div>

      <form class="card card-md" @submit.prevent="submit">
        <div class="card-body">
          <h1 class="h2 text-center mb-4">{{ isRegistering ? 'Crear cuenta' : 'Ingresar al panel' }}</h1>
          <p v-if="hasInvitation" class="text-secondary">Ingresá o creá tu cuenta para aceptar la invitación.</p>

          <div v-if="errorMessage" class="alert alert-danger" role="alert">
              {{ errorMessage }}
          </div>

          <div v-if="isRegistering" class="mb-3"><label class="form-label" for="register-name">Nombre</label><input id="register-name" v-model="form.name" class="form-control" required autocomplete="name"></div>
          <div class="mb-3">
              <label class="form-label" for="email">Email</label>
              <input
                id="email"
                v-model.trim="form.email"
                class="form-control"
                type="email"
                autocomplete="email"
                required
                autofocus
              >
          </div>

          <div class="mb-2">
              <label class="form-label" for="password">Contraseña</label>
              <input
                id="password"
                v-model="form.password"
                class="form-control"
                type="password"
                autocomplete="current-password"
                :minlength="isRegistering ? 10 : undefined"
                required
              >
          </div>

          <div v-if="isRegistering" class="mb-3"><label class="form-label" for="password-confirmation">Repetir contraseña</label><input id="password-confirmation" v-model="form.password_confirmation" type="password" class="form-control" required autocomplete="new-password"></div>

          <div class="form-footer">
              <button class="btn btn-primary w-100" type="submit" :disabled="isSubmitting">
                <span
                  v-if="isSubmitting"
                  class="spinner-border spinner-border-sm me-2"
                  aria-hidden="true"
                ></span>
                {{ isRegistering ? 'Crear cuenta e ingresar' : 'Ingresar' }}
              </button>
          </div>
        </div>
      </form>
      <button type="button" class="btn btn-link w-100 mt-3" @click="isRegistering=!isRegistering;errorMessage=''">{{ isRegistering ? 'Ya tengo una cuenta' : 'Crear una cuenta' }}</button>
    </div>
  </div>
</template>
