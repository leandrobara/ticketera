<script setup>
import { computed, inject, onBeforeUnmount, reactive, ref } from 'vue';
import ApiService from '@/admin/services/ApiService';

const { user } = inject('adminSession');
const api = ApiService.getInstance();
const organizationId = Number(window.location.pathname.match(/^\/admin\/organizations\/(\d+)\/edit$/)?.[1] ?? 0);
const editingMembership = computed(() => user.value.memberships?.find(m => Number(m.organization_id) === organizationId) ?? null);
const organization = computed(() => editingMembership.value?.organization ?? null);
const canEditOrganization = computed(() => organization.value && (
  Number(organization.value.creator_user_id) === Number(user.value.id)
  || editingMembership.value.roles.includes('administrator')
));
const isEditing = Boolean(organizationId);
const canDeleteOrganization = computed(() => isEditing && Number(organization.value?.creator_user_id) === Number(user.value.id));
const form = reactive({
  name: organization.value?.name ?? '',
});
const image = ref(null);
const previewUrl = ref('');
const imageInput = ref(null);
const removeCurrentImage = ref(false);
const error = ref('');
const deleteError = ref('');
const fieldErrors = ref({});
const busy = ref(false);
const deleting = ref(false);
const displayedImage = computed(() => previewUrl.value || (!removeCurrentImage.value ? organization.value?.image_url : null));
const initial = computed(() => form.name.trim().charAt(0).toUpperCase() || 'O');

function releasePreview() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = '';
}

function selectImage(event) {
  const file = event.target.files?.[0] ?? null;
  fieldErrors.value = {};
  error.value = '';
  if (!file) return;
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 3 * 1024 * 1024) {
    releasePreview();
    image.value = null;
    fieldErrors.value = { image: ['Elegí un archivo JPG, PNG o WebP de hasta 3 MB.'] };
    event.target.value = '';
    return;
  }
  releasePreview();
  image.value = file;
  removeCurrentImage.value = false;
  previewUrl.value = URL.createObjectURL(file);
}

function clearImage() {
  releasePreview();
  image.value = null;
  removeCurrentImage.value = true;
  fieldErrors.value = {};
  if (imageInput.value) imageInput.value.value = '';
}

async function save() {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  fieldErrors.value = {};
  const payload = new FormData();
  payload.append('name', form.name.trim());
  if (image.value) payload.append('image', image.value);
  if (isEditing && removeCurrentImage.value) payload.append('remove_image', '1');

  try {
    const response = isEditing
      ? await api.post('/api/admin/organizations/'+organizationId, payload)
      : await api.post('/api/admin/organizations', payload);
    if (isEditing) {
      window.location.assign('/admin/organizations');
    } else {
      localStorage.setItem('organization_'+user.value.id, response.data.data.id);
      sessionStorage.setItem('organization_created', '1');
      window.location.assign('/admin/events/new');
    }
  } catch (exception) {
    error.value = exception.response?.data?.message ?? 'No se pudo guardar la organización.';
    fieldErrors.value = exception.response?.data?.errors ?? {};
  } finally {
    busy.value = false;
  }
}

async function remove() {
  if (deleting.value || !canDeleteOrganization.value) return;
  if (!window.confirm('¿Eliminar la organización '+organization.value.name+'? Sus datos dejarán de estar disponibles en el panel.')) return;

  deleting.value = true;
  deleteError.value = '';
  try {
    await api.delete('/api/admin/organizations/'+organizationId);
    window.location.assign('/admin/organizations');
  } catch (exception) {
    deleteError.value = exception.response?.data?.message ?? 'No se pudo eliminar la organización.';
  } finally {
    deleting.value = false;
  }
}

onBeforeUnmount(releasePreview);
</script>

<template>
  <div class="organization-page admin-form-page">
    <a class="d-inline-block mb-3" href="/admin/organizations">← Mis organizaciones</a>
    <div class="page-header mb-4">
      <h1 class="page-title">{{ isEditing ? 'Editar organización' : 'Nueva organización' }}</h1>
      <p class="text-secondary mt-2 mb-0">{{ isEditing ? 'Actualizá los datos de tu organización.' : 'Creá el espacio donde vas a gestionar tus eventos y tu equipo.' }}</p>
    </div>

    <div class="card mb-4 organization-session-card">
      <div class="card-body">
        <div class="text-secondary small mb-1">{{ isEditing ? 'Sesión iniciada como' : 'Vas a crear esta organización con la cuenta' }}</div>
        <div class="fw-semibold organization-account-email">{{ user.email }}</div>
        <div v-if="!isEditing" class="text-secondary small mt-2">Vas a ser el líder de la organización y vas a poder invitar a otras personas.</div>
      </div>
    </div>

    <div v-if="isEditing && !canEditOrganization" class="alert alert-warning" role="alert">No tenés permiso para editar esta organización.</div>
    <form v-else class="card" @submit.prevent="save">
      <div class="card-body">
        <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>

        <div class="mb-4">
          <label class="form-label" for="organization-name">Nombre de la organización <span class="text-danger">*</span></label>
          <input id="organization-name" v-model="form.name" class="form-control" :class="{'is-invalid': fieldErrors.name}" required maxlength="160" autocomplete="organization" autofocus>
          <div v-if="fieldErrors.name" class="invalid-feedback">{{ fieldErrors.name[0] }}</div>
        </div>

        <div class="mb-4">
          <label class="form-label" for="organization-image">Imagen de la organización <span class="text-secondary fw-normal">(opcional)</span></label>
          <p class="text-secondary small mb-3">Podés agregarla ahora o más adelante. Formatos JPG, PNG o WebP; hasta 3 MB.</p>
          <div class="organization-image-picker d-flex align-items-center gap-3 flex-wrap">
            <img v-if="displayedImage" class="organization-list-avatar" :src="displayedImage" alt="Vista previa de la imagen de la organización">
            <span v-else class="organization-list-avatar organization-list-initial" aria-hidden="true">{{ initial }}</span>
            <div class="organization-image-input">
              <input id="organization-image" ref="imageInput" class="form-control" :class="{'is-invalid': fieldErrors.image}" type="file" accept="image/jpeg,image/png,image/webp" @change="selectImage">
              <div v-if="fieldErrors.image" class="invalid-feedback">{{ fieldErrors.image[0] }}</div>
              <button v-if="displayedImage" type="button" class="btn btn-link px-0 mt-1" @click="clearImage">Quitar imagen</button>
            </div>
          </div>
        </div>

      </div>
      <div class="card-footer d-flex gap-2 flex-wrap">
        <button class="btn btn-success" type="submit" :disabled="busy">{{ busy ? 'Guardando…' : (isEditing ? 'Guardar cambios' : 'Crear organización') }}</button>
      </div>
    </form>

    <section v-if="canDeleteOrganization" class="card mt-4" aria-labelledby="delete-organization-heading">
      <div class="card-body">
        <p class="small text-secondary mb-3">Si eliminás esta organización, sus datos dejarán de estar disponibles.</p>
        <button class="btn btn-outline-danger btn-sm" type="button" :disabled="deleting || busy" @click="remove">{{ deleting ? 'Eliminando…' : 'Eliminar organización' }}</button>
        <div v-if="deleteError" class="alert alert-danger" role="alert">{{ deleteError }}</div>
      </div>
    </section>
  </div>
</template>
