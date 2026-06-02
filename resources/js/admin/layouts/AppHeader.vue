<script setup>
  // emits
  defineEmits(['logout']);

  // props
  defineProps({
    user: {
      type: Object,
      required: true,
    },
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
            <li class="nav-item active dropdown">
              <a
                class="nav-link dropdown-toggle"
                href="#navbar-shows"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                role="button"
                aria-expanded="false"
              >
                <span class="nav-link-title">Shows</span>
              </a>
              <div id="navbar-shows" class="dropdown-menu">
                <a class="dropdown-item active" href="/admin/shows">
                  Listado
                </a>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </header>
</template>
