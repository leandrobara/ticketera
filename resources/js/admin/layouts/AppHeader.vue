<script setup>
  import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

  // emits
  const emit = defineEmits(['logout']);

  // props
  defineProps({
    user: {
      type: Object,
      required: true,
    },
    isLoggingOut: {
      type: Boolean,
      default: false,
    },
  });

  // data
  const currentPath = window.location.pathname;
  const isUserMenuOpen = ref(false);
  const userMenuElement = ref(null);

  // computed
  const isShowsActive = computed(() => currentPath === '/admin/shows');
  const isSeasonsActive = computed(() => currentPath === '/admin/seasons');
  const isOrdersActive = computed(() => currentPath === '/admin/orders');
  const isUsersActive = computed(() => currentPath === '/admin/users');
  const isPeopleActive = computed(() => currentPath === '/admin/people');
  const isVenuesActive = computed(() => currentPath === '/admin/venues');
  const isPresentationsActive = computed(() => currentPath === '/admin/presentations');
  const isPresentationTicketTypesActive = computed(() => currentPath === '/admin/presentation-ticket-types');
  const isBuyersActive = computed(() => currentPath === '/admin/buyers');
  const isCommentsActive = computed(() => currentPath === '/admin/comments');
  const isNewsletterSubscribersActive = computed(() => currentPath === '/admin/newsletter-subscribers');

  // methods
  const closeUserMenu = () => {
    isUserMenuOpen.value = false;
  };

  const toggleUserMenu = () => {
    isUserMenuOpen.value = !isUserMenuOpen.value;
  };

  const handleDocumentClick = (event) => {
    if (!userMenuElement.value?.contains(event.target)) {
      closeUserMenu();
    }
  };

  const handleDocumentKeydown = (event) => {
    if (event.key === 'Escape') {
      closeUserMenu();
    }
  };

  const handleLogout = () => {
    closeUserMenu();
    emit('logout');
  };

  // lifecycle
  onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleDocumentKeydown);
  });

  onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleDocumentKeydown);
  });
</script>

<template>
  <header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
      <button
        class="navbar-toggler"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#admin-navbar"
        aria-controls="admin-navbar"
        aria-expanded="false"
        aria-label="Toggle navigation"
      >
        <span class="navbar-toggler-icon"></span>
      </button>

      <h1 class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
        <a href="/admin/shows" aria-label="Entradatix">
          <img class="admin-brand-logo" :src="'/brand/entradatix-logo.png'" alt="Entradatix">
        </a>
      </h1>

      <div class="navbar-nav flex-row order-md-last">
        <div ref="userMenuElement" class="nav-item dropdown admin-user-menu" :class="{ show: isUserMenuOpen }">
          <button
            class="nav-link d-flex lh-1 text-reset p-0 border-0 bg-transparent"
            type="button"
            :aria-expanded="isUserMenuOpen"
            aria-label="Open user menu"
            @click.stop="toggleUserMenu"
          >
            <span class="avatar avatar-sm">
              {{ user.name?.charAt(0) ?? 'A' }}
            </span>
            <div class="d-none d-xl-block ps-2">
              <div>{{ user.name }}</div>
              <div class="mt-1 small text-secondary">{{ user.email }}</div>
            </div>
          </button>
          <div
            class="dropdown-menu dropdown-menu-end dropdown-menu-arrow admin-user-menu-dropdown"
            :class="{ show: isUserMenuOpen }"
            @click.stop
          >
            <button class="dropdown-item" type="button" :disabled="isLoggingOut" @click="handleLogout">
              <span v-if="isLoggingOut" class="spinner-border spinner-border-sm me-2" role="status"></span>
              Cerrar sesión
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <header class="navbar-expand-md">
    <div id="admin-navbar" class="collapse navbar-collapse">
      <div class="navbar">
        <div class="container-xl">
          <ul class="navbar-nav">
            <li class="nav-item" :class="{ active: isShowsActive }">
              <a
                class="nav-link"
                href="/admin/shows"
              >
                <span class="nav-link-title">Shows</span>
              </a>
            </li>
            <li class="nav-item" :class="{ active: isSeasonsActive }">
              <a
                class="nav-link"
                href="/admin/seasons"
              >
                <span class="nav-link-title">Temporadas</span>
              </a>
            </li>
            <li class="nav-item" :class="{ active: isOrdersActive }">
              <a
                class="nav-link"
                href="/admin/orders"
              >
                <span class="nav-link-title">Entradas</span>
              </a>
            </li>
            <li class="nav-item" :class="{ active: isPresentationsActive }">
              <a
                class="nav-link"
                href="/admin/presentations"
              >
                <span class="nav-link-title">Funciones</span>
              </a>
            </li>
            <li class="nav-item" :class="{ active: isPresentationTicketTypesActive }">
              <a
                class="nav-link"
                href="/admin/presentation-ticket-types"
              >
                <span class="nav-link-title">Tipos de entrada</span>
              </a>
            </li>
            <li class="nav-item" :class="{ active: isVenuesActive }">
              <a
                class="nav-link"
                href="/admin/venues"
              >
                <span class="nav-link-title">Espacios</span>
              </a>
            </li>
            <li class="nav-item" :class="{ active: isPeopleActive }">
              <a
                class="nav-link"
                href="/admin/people"
              >
                <span class="nav-link-title">Personas</span>
              </a>
            </li>
            <li class="nav-item" :class="{ active: isBuyersActive }">
              <a
                class="nav-link"
                href="/admin/buyers"
              >
                <span class="nav-link-title">Compradores</span>
              </a>
            </li>
            <li class="nav-item" :class="{ active: isUsersActive }">
              <a
                class="nav-link"
                href="/admin/users"
              >
                <span class="nav-link-title">Usuarios</span>
              </a>
            </li>
            <li class="nav-item" :class="{ active: isNewsletterSubscribersActive }">
              <a
                class="nav-link"
                href="/admin/newsletter-subscribers"
              >
                <span class="nav-link-title">Suscriptores</span>
              </a>
            </li>
            <li class="nav-item" :class="{ active: isCommentsActive }">
              <a class="nav-link" href="/admin/comments">
                <span class="nav-link-title">Comentarios</span>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </header>
</template>
