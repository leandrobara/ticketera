<script setup>
defineProps({user:Object,isLoggingOut:Boolean});
defineEmits(['logout']);
</script>
<template>
  <header class="navbar d-print-none">
    <div class="container-xl">
      <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#admin-sidebar-nav" aria-controls="admin-sidebar-nav" aria-label="Abrir navegación"><span class="navbar-toggler-icon"></span></button>
      <span class="text-secondary d-none d-md-inline">Panel de gestión</span>
      <div class="ms-auto d-flex align-items-center gap-3">
        <div class="text-end"><div>{{ user.name }}</div><small class="text-secondary">{{ user.email }}</small></div>
        <button class="btn btn-outline-secondary btn-sm" :disabled="isLoggingOut" @click="$emit('logout')">{{ isLoggingOut ? 'Cerrando…' : 'Cerrar sesión' }}</button>
      </div>
    </div>
  </header>
</template>
