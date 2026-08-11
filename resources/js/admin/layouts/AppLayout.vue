<script setup>
  import AppFooter from '@/admin/layouts/AppFooter.vue';
  import AppHeader from '@/admin/layouts/AppHeader.vue';

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

  defineEmits(['logout']);
</script>

<template>
  <div class="page">
    <AppHeader :user="user" :is-logging-out="isLoggingOut" @logout="$emit('logout')" />

    <main class="page-wrapper">
      <div class="page-body">
        <div class="container-xl">
          <slot />
        </div>
      </div>
    </main>

    <AppFooter />
  </div>
</template>
