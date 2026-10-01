<script setup>
import AppHeader from './AppHeader.vue';
import AppSidebar from './AppSidebar.vue';
defineProps({user:Object, membership:Object, canManage:Boolean, canEditEvents:Boolean, isLoggingOut:Boolean});
defineEmits(['logout','select-organization']);
</script>
<template>
  <div class="page">
    <AppSidebar :user="user" :membership="membership" :can-manage="canManage" :can-edit-events="canEditEvents" @select-organization="$emit('select-organization',$event)" />
    <div class="page-wrapper">
      <AppHeader :user="user" :is-logging-out="isLoggingOut" @logout="$emit('logout')" />
      <main class="page-body"><div class="container-xl"><slot /></div></main>
    </div>
  </div>
</template>
