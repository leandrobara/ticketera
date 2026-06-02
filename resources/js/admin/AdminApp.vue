<script setup>
  import { computed, onMounted, ref } from 'vue';
  import LoginPage from './pages/LoginPage.vue';
  import ShowsIndex from './pages/ShowsIndex.vue';
  import AppLayout from './layouts/AppLayout.vue';

  // data
  const user = ref(null);
  const isLoadingSession = ref(true);
  const tokenStorageKey = 'token_tickets';
  const token = ref(localStorage.getItem(tokenStorageKey));

  // computed
  const isAuthenticated = computed(() => Boolean(token.value && user.value));

  // methods
  const setAuthorizationHeader = (newToken) => {
      if (newToken) {
          window.axios.defaults.headers.common.Authorization = `Bearer ${newToken}`;
          return;
      }

      delete window.axios.defaults.headers.common.Authorization;
  };

  const redirectToLogin = () => {
      if (window.location.pathname !== '/admin/login') {
          window.history.replaceState({}, '', '/admin/login');
      }
  };

  const redirectToAdmin = () => {
      if (window.location.pathname === '/admin/login' || window.location.pathname === '/admin') {
          window.history.replaceState({}, '', '/admin/shows');
      }
  };

  const clearSession = () => {
      token.value = null;
      user.value = null;
      localStorage.removeItem(tokenStorageKey);
      setAuthorizationHeader(null);
      redirectToLogin();
  };

  const loadSession = async () => {
      if (!token.value) {
          clearSession();
          isLoadingSession.value = false;
          return;
      }

      setAuthorizationHeader(token.value);

      try {
          const response = await window.axios.get('/api/admin/auth/me');
          user.value = response.data.data.user;
          redirectToAdmin();
      } catch (error) {
          clearSession();
      } finally {
          isLoadingSession.value = false;
      }
  };

  const handleLogin = ({ token: newToken, user: loggedUser }) => {
      token.value = newToken;
      user.value = loggedUser;
      localStorage.setItem(tokenStorageKey, newToken);
      setAuthorizationHeader(newToken);
      redirectToAdmin();
  };

  const handleLogout = async () => {
      try {
          await window.axios.post('/api/admin/auth/logout');
      } catch (error) {
          // Local logout still clears the admin session if the API token expired.
      } finally {
          clearSession();
      }
  };

  // lifecycle
  onMounted(loadSession);
</script>

<template>

  <div v-if="isLoadingSession" class="page page-center">
    <div class="container-tight py-4">
      <div class="text-center">
        <div class="spinner-border text-primary" role="status"></div>
      </div>
    </div>
  </div>
  <LoginPage v-else-if="!isAuthenticated" @login="handleLogin" />
  <AppLayout v-else :user="user" @logout="handleLogout">
    <ShowsIndex />
  </AppLayout>

</template>
