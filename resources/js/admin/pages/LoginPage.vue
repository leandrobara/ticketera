<script setup>
  import { reactive, ref } from 'vue';
  import LoginService from '@/admin/services/LoginService';

  // emits
  const emit = defineEmits(['login']);

  // data
  const errorMessage = ref('');
  const isSubmitting = ref(false);
  const form = reactive({
    email: '',
    password: '',
  });

  // methods
  const submit = async () => {
    isSubmitting.value = true;
    errorMessage.value = '';

    try {
      const response = await LoginService.getInstance().login({
        email: form.email,
        password: form.password,
      });

      emit('login', response.data.data);
    } catch (error) {
      errorMessage.value = 'Invalid email or password.';
    } finally {
      isSubmitting.value = false;
    }
  };
</script>

<template>
  <div class="page page-center">
    <div class="container-tight py-4">
      <div class="text-center mb-4">
        <a class="navbar-brand navbar-brand-autodark" href="/admin/login">
          Ticketera
        </a>
      </div>

      <form class="card card-md" @submit.prevent="submit">
        <div class="card-body">
          <h1 class="h2 text-center mb-4">Admin login</h1>

          <div v-if="errorMessage" class="alert alert-danger" role="alert">
              {{ errorMessage }}
          </div>

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
              <label class="form-label" for="password">Password</label>
              <input
                id="password"
                v-model="form.password"
                class="form-control"
                type="password"
                autocomplete="current-password"
                required
              >
          </div>

          <div class="form-footer">
              <button class="btn btn-primary w-100" type="submit" :disabled="isSubmitting">
                <span
                  v-if="isSubmitting"
                  class="spinner-border spinner-border-sm me-2"
                  aria-hidden="true"
                ></span>
                Login
              </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>
