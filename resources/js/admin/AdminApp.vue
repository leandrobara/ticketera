<script setup>
  import { computed, onMounted, ref } from 'vue';
  import BuyersIndex from './pages/BuyersIndex.vue';
  import CommentsIndex from './pages/CommentsIndex.vue';
  import LoginPage from './pages/LoginPage.vue';
  import NewsletterSubscribersIndex from './pages/NewsletterSubscribersIndex.vue';
  import OrdersIndex from './pages/OrdersIndex.vue';
  import PeopleIndex from './pages/PeopleIndex.vue';
  import PresentationsIndex from './pages/PresentationsIndex.vue';
  import PresentationTicketTypesIndex from './pages/PresentationTicketTypesIndex.vue';
  import SeasonsIndex from './pages/SeasonsIndex.vue';
  import ShowsIndex from './pages/ShowsIndex.vue';
  import VenuesIndex from './pages/VenuesIndex.vue';
  import AppLayout from './layouts/AppLayout.vue';
  import ApiService from '@/admin/services/ApiService';
  import LoginService from '@/admin/services/LoginService';

  // data
  const user = ref(null);
  const isLoadingSession = ref(true);
  const tokenStorageKey = 'token_tickets';
  const token = ref(localStorage.getItem(tokenStorageKey));
  const currentPath = ref(window.location.pathname);

  // computed
  const isAuthenticated = computed(() => Boolean(token.value && user.value));
  const currentPage = computed(() => {
    if (currentPath.value === '/admin/buyers') {
      return BuyersIndex;
    }

    if (currentPath.value === '/admin/comments') {
      return CommentsIndex;
    }

    if (currentPath.value === '/admin/orders') {
      return OrdersIndex;
    }

    if (currentPath.value === '/admin/newsletter-subscribers') {
      return NewsletterSubscribersIndex;
    }

    if (currentPath.value === '/admin/people') {
      return PeopleIndex;
    }

    if (currentPath.value === '/admin/presentation-ticket-types') {
      return PresentationTicketTypesIndex;
    }

    if (currentPath.value === '/admin/presentations') {
      return PresentationsIndex;
    }

    if (currentPath.value === '/admin/seasons') {
      return SeasonsIndex;
    }

    if (currentPath.value === '/admin/venues') {
      return VenuesIndex;
    }

    return ShowsIndex;
  });

  // methods
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
    ApiService.getInstance().clearAccessToken();
    redirectToLogin();
  };

  const loadSession = async () => {
    if (!token.value) {
      clearSession();
      isLoadingSession.value = false;
      return;
    }

    ApiService.getInstance().setAccessToken(token.value);

    try {
      const response = await LoginService.getInstance().me();
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
    ApiService.getInstance().setAccessToken(newToken);
    redirectToAdmin();
  };

  const handleLogout = async () => {
    try {
      await LoginService.getInstance().logout();
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
    <component :is="currentPage" />
  </AppLayout>

</template>
