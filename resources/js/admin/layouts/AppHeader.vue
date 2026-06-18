<script setup>
  import { computed } from 'vue';

  // emits
  defineEmits(['logout']);

  // props
  defineProps({
    user: {
      type: Object,
      required: true,
    },
  });

  // data
  const currentPath = window.location.pathname;

  // computed
  const isShowsActive = computed(() => currentPath === '/admin/shows');
  const isOrdersActive = computed(() => currentPath === '/admin/orders');
  const isPromotionsActive = computed(() => currentPath === '/admin/promotions');
  const isVenuesActive = computed(() => currentPath === '/admin/venues');
  const isPresentationsActive = computed(() => currentPath === '/admin/presentations');
  const isPresentationTicketTypesActive = computed(() => currentPath === '/admin/presentation-ticket-types');
  const isBuyersActive = computed(() => currentPath === '/admin/buyers');
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
        <a href="/admin/shows">Ticketera</a>
      </h1>

      <div class="navbar-nav flex-row order-md-last">
        <div class="nav-item dropdown">
          <button
            class="nav-link d-flex lh-1 text-reset p-0 border-0 bg-transparent"
            type="button"
            data-bs-toggle="dropdown"
            aria-label="Open user menu"
          >
            <span class="avatar avatar-sm">
              {{ user.name?.charAt(0) ?? 'A' }}
            </span>
            <div class="d-none d-xl-block ps-2">
              <div>{{ user.name }}</div>
              <div class="mt-1 small text-secondary">{{ user.email }}</div>
            </div>
          </button>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <button class="dropdown-item" type="button" @click="$emit('logout')">
              Logout
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
            <li class="nav-item" :class="{ active: isPromotionsActive }">
              <a
                class="nav-link"
                href="/admin/promotions"
              >
                <span class="nav-link-title">Promociones</span>
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
            <li class="nav-item" :class="{ active: isBuyersActive }">
              <a
                class="nav-link"
                href="/admin/buyers"
              >
                <span class="nav-link-title">Compradores</span>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </header>
</template>
