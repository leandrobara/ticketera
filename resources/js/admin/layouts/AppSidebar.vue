<script setup>
import { computed } from 'vue';
const props = defineProps({user:Object,membership:Object,canManage:Boolean,canEditEvents:Boolean});
defineEmits(['select-organization']);
const currentPath = window.location.pathname;
const links = computed(() => [
  ...(props.canEditEvents ? [['/admin/events','Eventos'],['/admin/events/new','Crear evento'],['/admin/shows','Shows'],['/admin/seasons','Temporadas'],['/admin/presentations','Funciones'],['/admin/season-ticket-types','Tipos de entrada'],['/admin/venues','Lugares'],['/admin/people','Personas'],['/admin/orders','Ventas'],['/admin/buyers','Compradores'],['/admin/comments','Comentarios']] : []),
  ...(props.membership ? [['/admin/door','Puerta · Validar entradas']] : []),
  ...(props.canManage ? [['/admin/team','Equipo e invitaciones'],['/admin/newsletter-subscribers','Suscriptores']] : []),
  ['/admin/organizations','Mis organizaciones'],
]);
</script>
<template>
  <aside class="navbar navbar-vertical navbar-expand-lg d-print-none">
    <div class="container-fluid">
      <a class="navbar-brand navbar-brand-autodark" href="/admin/events"><img class="admin-brand-logo" :src="'/brand/entradatix-logo.png'" alt="Entradatix"></a>
      <div id="admin-sidebar-nav" class="collapse navbar-collapse">
        <div v-if="user.memberships?.length" class="w-100 px-3 py-3">
          <label class="form-label small text-secondary" for="active-organization">Organización</label>
          <select id="active-organization" class="form-select" :value="membership?.organization_id ?? ''" @change="$emit('select-organization',$event.target.value)">
            <option v-if="!membership" value="">Seleccionar organización</option>
            <option v-for="m in user.memberships" :key="m.id" :value="m.organization_id">{{ m.organization.name }}</option>
          </select>
        </div>
        <ul class="navbar-nav w-100">
          <li v-for="[href,label] in links" :key="href" class="nav-item" :class="{active:currentPath === href || (href === '/admin/events' && /^\/admin\/events\/\d+$/.test(currentPath)) || (href === '/admin/organizations' && currentPath.startsWith('/admin/organizations/')) || (href === '/admin/team' && (currentPath.startsWith('/admin/team/') || currentPath.startsWith('/admin/invitations/')))}"><a class="nav-link" :href="href"><span class="nav-link-title">{{ label }}</span></a></li>
        </ul>
      </div>
    </div>
  </aside>
</template>
