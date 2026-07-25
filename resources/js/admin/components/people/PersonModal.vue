<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import PersonService from '@/admin/services/PersonService';

  const emit = defineEmits(['saved']);

  const mode = ref('create');
  const currentPerson = ref(null);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const duplicateCandidates = ref([]);
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isSubmitting = ref(false);
  const isLoadingCandidates = ref(false);
  const form = reactive({
    bio: '',
    email: '',
    phone: '',
    last_name: '',
    photo_path: '',
    first_name: '',
    website_url: '',
    display_name: '',
    document_type: '',
    instagram_url: '',
    document_number: '',
  });

  const isEditing = computed(() => mode.value === 'update');
  const hasDuplicateCandidates = computed(() => duplicateCandidates.value.length > 0);
  const modalTitle = computed(() => isEditing.value ? 'Editar persona' : 'Crear una nueva persona');
  const submitLabel = computed(() => isEditing.value ? 'Guardar cambios' : 'Crear persona');

  const nullable = (value) => value === '' ? null : value;

  const resetForm = () => {
    form.bio = '';
    form.email = '';
    form.phone = '';
    form.last_name = '';
    form.photo_path = '';
    form.first_name = '';
    form.website_url = '';
    form.display_name = '';
    form.document_type = '';
    form.instagram_url = '';
    form.document_number = '';
    fieldErrors.value = {};
    errorMessage.value = '';
    duplicateCandidates.value = [];
  };

  const fillForm = (person) => {
    form.bio = person.bio ?? '';
    form.email = person.email ?? '';
    form.phone = person.phone ?? '';
    form.last_name = person.last_name ?? '';
    form.photo_path = person.photo_path ?? '';
    form.first_name = person.first_name ?? '';
    form.website_url = person.website_url ?? '';
    form.display_name = person.display_name ?? '';
    form.document_type = person.document_type ?? '';
    form.instagram_url = person.instagram_url ?? '';
    form.document_number = person.document_number ?? '';
    fieldErrors.value = {};
    errorMessage.value = '';
    duplicateCandidates.value = [];
  };

  const getPayload = (allowDuplicateName = false) => {
    return {
      bio: nullable(form.bio),
      email: nullable(form.email),
      phone: nullable(form.phone),
      last_name: nullable(form.last_name),
      photo_path: nullable(form.photo_path),
      first_name: nullable(form.first_name),
      website_url: nullable(form.website_url),
      display_name: form.display_name,
      document_type: nullable(form.document_type),
      instagram_url: nullable(form.instagram_url),
      document_number: nullable(form.document_number),
      allow_duplicate_name: allowDuplicateName,
    };
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const hasPossibleDuplicateError = (errors) => {
    return errors?.display_name?.includes('possible_duplicate_person');
  };

  const loadDuplicateCandidates = async () => {
    isLoadingCandidates.value = true;

    try {
      const response = await PersonService.getInstance().getPersonCandidates({
        display_name: form.display_name,
        email: nullable(form.email),
        document_type: nullable(form.document_type),
        document_number: nullable(form.document_number),
      });
      duplicateCandidates.value = response.data.data ?? [];
    } catch (error) {
      duplicateCandidates.value = [];
    } finally {
      isLoadingCandidates.value = false;
    }
  };

  const showModal = async () => {
    await nextTick();
    modalInstance.value.show();
  };

  const openForCreate = async () => {
    mode.value = 'create';
    currentPerson.value = null;
    resetForm();
    await showModal();
  };

  const openForUpdate = async (person) => {
    mode.value = 'update';
    currentPerson.value = person;
    fillForm(person);
    await showModal();
  };

  const close = () => {
    modalInstance.value.hide();
  };

  const submit = async (allowDuplicateName = false) => {
    fieldErrors.value = {};
    errorMessage.value = '';
    isSubmitting.value = true;

    try {
      if (isEditing.value) {
        await PersonService.getInstance().updatePerson(currentPerson.value.id, getPayload(allowDuplicateName));
      } else {
        await PersonService.getInstance().createPerson(getPayload(allowDuplicateName));
      }

      emit('saved');
      close();
    } catch (error) {
      const errors = error.response?.data?.errors ?? {};
      fieldErrors.value = errors;

      if (hasPossibleDuplicateError(errors)) {
        errorMessage.value = 'Ya existe una persona con un nombre parecido. Revisá si corresponde usar una existente o crear una nueva.';
        await loadDuplicateCandidates();
      } else {
        errorMessage.value = error.response?.data?.message ?? 'No se pudo guardar la persona.';
      }
    } finally {
      isSubmitting.value = false;
    }
  };

  const submitAllowingDuplicate = async () => {
    await submit(true);
  };

  const editCandidate = async (person) => {
    mode.value = 'update';
    currentPerson.value = person;
    fillForm(person);
  };

  defineExpose({
    openForCreate,
    openForUpdate,
  });

  onMounted(() => {
    modalInstance.value = new Modal(modalElement.value);
  });

  onBeforeUnmount(() => {
    modalInstance.value?.dispose();
  });
</script>

<template>
  <div ref="modalElement" class="modal modal-blur fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
      <form class="modal-content" @submit.prevent="submit(false)">
        <div class="modal-header">
          <h5 class="modal-title">{{ modalTitle }}</h5>
          <button class="btn-close" type="button" aria-label="Close" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-warning" role="alert">
            {{ errorMessage }}
          </div>

          <div v-if="hasDuplicateCandidates" class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">Posibles coincidencias</h3>
            </div>
            <div class="list-group list-group-flush">
              <div
                v-for="candidate in duplicateCandidates"
                :key="candidate.id"
                class="list-group-item"
              >
                <div class="row align-items-center">
                  <div class="col-auto">
                    <span
                      v-if="candidate.photo_path"
                      class="avatar"
                      :style="{ backgroundImage: `url(${candidate.photo_path})` }"
                    ></span>
                    <span v-else class="avatar">{{ candidate.display_name?.charAt(0) ?? 'P' }}</span>
                  </div>
                  <div class="col">
                    <div class="fw-semibold">{{ candidate.display_name }}</div>
                    <div class="text-secondary small">
                      {{ candidate.email || '-' }}
                      <span v-if="candidate.document_number"> · {{ candidate.document_type }} {{ candidate.document_number }}</span>
                    </div>
                  </div>
                  <div class="col-auto">
                    <button class="btn btn-sm btn-outline-primary" type="button" @click="editCandidate(candidate)">
                      Editar esta persona
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div class="card-footer text-end">
              <button
                class="btn btn-outline-warning"
                type="button"
                :disabled="isSubmitting || isLoadingCandidates"
                @click="submitAllowingDuplicate"
              >
                Crear como persona nueva
              </button>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="person-display-name">Nombre público</label>
                <input
                  id="person-display-name"
                  v-model.trim="form.display_name"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('display_name') }"
                  type="text"
                  maxlength="160"
                  required
                >
                <div v-if="getFieldError('display_name')" class="invalid-feedback">
                  {{ getFieldError('display_name') }}
                </div>
              </div>
            </div>

            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label" for="person-first-name">Nombre</label>
                <input
                  id="person-first-name"
                  v-model.trim="form.first_name"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('first_name') }"
                  type="text"
                  maxlength="120"
                >
                <div v-if="getFieldError('first_name')" class="invalid-feedback">
                  {{ getFieldError('first_name') }}
                </div>
              </div>
            </div>

            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label" for="person-last-name">Apellido</label>
                <input
                  id="person-last-name"
                  v-model.trim="form.last_name"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('last_name') }"
                  type="text"
                  maxlength="120"
                >
                <div v-if="getFieldError('last_name')" class="invalid-feedback">
                  {{ getFieldError('last_name') }}
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="person-email">Email</label>
                <input
                  id="person-email"
                  v-model.trim="form.email"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('email') }"
                  type="email"
                  maxlength="255"
                >
                <div v-if="getFieldError('email')" class="invalid-feedback">
                  {{ getFieldError('email') }}
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="person-phone">Teléfono</label>
                <input
                  id="person-phone"
                  v-model.trim="form.phone"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('phone') }"
                  type="text"
                  maxlength="80"
                >
                <div v-if="getFieldError('phone')" class="invalid-feedback">
                  {{ getFieldError('phone') }}
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="person-document-type">Tipo de documento</label>
                <input
                  id="person-document-type"
                  v-model.trim="form.document_type"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('document_type') }"
                  type="text"
                  maxlength="30"
                >
                <div v-if="getFieldError('document_type')" class="invalid-feedback">
                  {{ getFieldError('document_type') }}
                </div>
              </div>
            </div>

            <div class="col-md-8">
              <div class="mb-3">
                <label class="form-label" for="person-document-number">Número de documento</label>
                <input
                  id="person-document-number"
                  v-model.trim="form.document_number"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('document_number') }"
                  type="text"
                  maxlength="80"
                >
                <div v-if="getFieldError('document_number')" class="invalid-feedback">
                  {{ getFieldError('document_number') }}
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="person-photo-path">Foto</label>
            <input
              id="person-photo-path"
              v-model.trim="form.photo_path"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('photo_path') }"
              type="text"
              maxlength="255"
            >
            <div v-if="getFieldError('photo_path')" class="invalid-feedback">
              {{ getFieldError('photo_path') }}
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="person-instagram-url">Instagram URL</label>
                <input
                  id="person-instagram-url"
                  v-model.trim="form.instagram_url"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('instagram_url') }"
                  type="url"
                  maxlength="255"
                >
                <div v-if="getFieldError('instagram_url')" class="invalid-feedback">
                  {{ getFieldError('instagram_url') }}
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label" for="person-website-url">Website URL</label>
                <input
                  id="person-website-url"
                  v-model.trim="form.website_url"
                  class="form-control"
                  :class="{ 'is-invalid': getFieldError('website_url') }"
                  type="url"
                  maxlength="255"
                >
                <div v-if="getFieldError('website_url')" class="invalid-feedback">
                  {{ getFieldError('website_url') }}
                </div>
              </div>
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label" for="person-bio">Bio</label>
            <textarea
              id="person-bio"
              v-model.trim="form.bio"
              class="form-control"
              :class="{ 'is-invalid': getFieldError('bio') }"
              rows="4"
            ></textarea>
            <div v-if="getFieldError('bio')" class="invalid-feedback">
              {{ getFieldError('bio') }}
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-link link-secondary" type="button" @click="close">
            Cancelar
          </button>
          <button class="btn btn-success ms-auto" type="submit" :disabled="isSubmitting">
            <span
              v-if="isSubmitting"
              aria-hidden="true"
              class="spinner-border spinner-border-sm me-2"
            ></span>
            {{ submitLabel }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
