<script setup>
import { inject } from 'vue';

const { user, membership: activeMembership } = inject('adminSession');
const emit = defineEmits(['organizations-changed']);
</script>

<template>
  <div class="organization-page">
    <div class="page-header mb-4">
      <h1 class="page-title">Tu usuario</h1>
      <p class="text-secondary mt-2 mb-0">Con esta cuenta accedés a las organizaciones que aparecen más abajo.</p>
    </div>

    <div class="card mb-3">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="organization-account-avatar organization-account-avatar--personal" aria-hidden="true">{{ user.name?.charAt(0)?.toUpperCase() || user.email.charAt(0).toUpperCase() }}</span>
        <div class="min-width-0">
          <div class="text-secondary small">Sesión iniciada como</div>
          <div class="fw-semibold organization-account-email">{{ user.email }}</div>
          <a class="small text-primary mt-2" href="/admin/account">Editar mis datos de usuario</a>
        </div>
      </div>
    </div>

    <section v-if="!user.memberships?.length" class="card organization-empty-state">
      <div class="card-body">
        <h2 class="h2 mb-2">Todavía no pertenecés a ninguna organización</h2>
        <p class="text-secondary mb-4">Tu cuenta ya está lista. Creá una organización para empezar a gestionar eventos, o pedile a tu equipo un enlace de invitación si ya tienen una.</p>
        <a class="btn btn-primary" href="/admin/organizations/new">Crear organización</a>
      </div>
    </section>

    <template v-else>
      <hr class="my-4">
      <div class="page-header mb-4 d-flex flex-column flex-sm-row align-items-start justify-content-between gap-3">
        <div>
          <h1 class="page-title">Mis organizaciones</h1>
          <p class="text-secondary mt-2 mb-0">Cada organización reúne los eventos y las personas de un equipo.</p>
        </div>
        <a class="btn btn-success flex-shrink-0" href="/admin/organizations/new">Crear una nueva organización</a>
      </div>

      <div v-for="membership in user.memberships" :key="membership.id" class="card mb-3">
        <div class="card-body d-flex align-items-center justify-content-between gap-3 flex-wrap">
          <div class="d-flex align-items-center gap-3 min-width-0">
            <img v-if="membership.organization.image_url" class="organization-list-avatar" :src="membership.organization.image_url" :alt="'Imagen de '+membership.organization.name">
            <span v-else class="organization-list-avatar organization-list-initial" aria-hidden="true">{{ membership.organization.name.charAt(0).toUpperCase() }}</span>
            <div class="min-width-0">
              <h2 class="card-title mb-0 text-truncate mb-2">{{ membership.organization.name }}</h2>
              <span v-if="Number(activeMembership?.organization_id) === Number(membership.organization_id)" class="badge bg-green-lt">Organización seleccionada</span>
              <button v-else class="organization-selection-status organization-select-action" type="button" :aria-label="'Seleccionar '+membership.organization.name" @click="emit('organizations-changed', membership.organization_id)">Seleccionar organización</button>
            </div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a v-if="membership.organization.creator_user_id === user.id || membership.roles.includes('administrator')" class="btn btn-primary btn-sm" :href="'/admin/organizations/'+membership.organization_id+'/edit'">Editar información</a>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
