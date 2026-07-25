<script setup>
  import { Modal } from 'bootstrap';
  import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
  import PersonService from '@/admin/services/PersonService';
  import ShowCreditService from '@/admin/services/ShowCreditService';

  const show = ref(null);
  const credits = ref([]);
  const personSearch = ref('');
  const personResults = ref([]);
  const hasSearchedPeople = ref(false);
  const selectedPerson = ref(null);
  const currentCredit = ref(null);
  const fieldErrors = ref({});
  const errorMessage = ref('');
  const selectedPhoto = ref(null);
  const photoPreviewUrl = ref('');
  const photoInput = ref(null);
  const modalElement = ref(null);
  const modalInstance = ref(null);
  const isLoading = ref(false);
  const isSearchingPeople = ref(false);
  const isSubmitting = ref(false);
  const form = reactive({
    credit_type: 'person',
    person_id: '',
    display_name_override: '',
    role_label: '',
    section: '',
    character_name: '',
    sort_order: '',
  });

  const isEditing = computed(() => Boolean(currentCredit.value));
  const modalTitle = computed(() => show.value ? `Créditos de ${show.value.title}` : 'Créditos');
  const submitLabel = computed(() => isEditing.value ? 'Guardar crédito' : 'Agregar crédito');
  const castCredits = computed(() => credits.value.filter((credit) => credit.section === 'cast'));
  const technicalCredits = computed(() => credits.value.filter((credit) => credit.section === 'technical'));
  const isFreeCredit = computed(() => form.credit_type === 'free');

  const resetPhoto = () => {
    if (photoPreviewUrl.value && selectedPhoto.value) {
      URL.revokeObjectURL(photoPreviewUrl.value);
    }

    selectedPhoto.value = null;
    photoPreviewUrl.value = '';

    if (photoInput.value) {
      photoInput.value.value = '';
    }
  };

  const resetForm = (creditType = 'person') => {
    currentCredit.value = null;
    personSearch.value = '';
    personResults.value = [];
    hasSearchedPeople.value = false;
    selectedPerson.value = null;
    form.credit_type = creditType;
    form.person_id = '';
    form.display_name_override = '';
    form.role_label = '';
    form.section = '';
    form.character_name = '';
    form.sort_order = '';
    resetPhoto();
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const fillForm = (credit) => {
    currentCredit.value = credit;
    form.credit_type = credit.person_id ? 'person' : 'free';
    form.person_id = credit.person_id ?? '';
    form.display_name_override = credit.display_name_override ?? '';
    selectedPerson.value = credit.person ?? null;
    personSearch.value = credit.person?.display_name ?? '';
    personResults.value = [];
    hasSearchedPeople.value = false;
    form.role_label = credit.role_label ?? '';
    form.section = credit.section;
    form.character_name = credit.character_name ?? '';
    form.sort_order = credit.sort_order ?? '';
    resetPhoto();
    photoPreviewUrl.value = credit.photo_url ?? '';
    fieldErrors.value = {};
    errorMessage.value = '';
  };

  const getPayload = () => {
    const isFree = form.credit_type === 'free';

    const payload = {
      show_id: show.value.id,
      person_id: isFree || form.person_id === '' ? null : Number(form.person_id),
      display_name_override: isFree ? form.display_name_override.trim() : null,
      role_label: form.role_label.trim(),
      section: form.section,
      character_name: form.section === 'cast' && form.character_name !== '' ? form.character_name : null,
      sort_order: form.sort_order === '' ? 1 : Number(form.sort_order),
    };

    if (!selectedPhoto.value) {
      return payload;
    }

    const formData = new FormData();

    Object.entries(payload).forEach(([key, value]) => {
      if (value !== null && value !== undefined) {
        formData.append(key, value);
      }
    });

    formData.append('photo', selectedPhoto.value);

    return formData;
  };

  const getFieldError = (field) => {
    return fieldErrors.value[field]?.[0] ?? '';
  };

  const getCreditPersonName = (credit) => {
    return credit.display_name_override
      ?? credit.person?.display_name
      ?? '-';
  };

  const getRoleLabel = (credit) => {
    return credit.role_label ?? '-';
  };

  const isFreeNameCredit = (credit) => {
    return !credit.person_id && Boolean(credit.display_name_override);
  };

  const getPersonMeta = (person) => {
    return [
      person.email,
      [person.document_type, person.document_number].filter(Boolean).join(' '),
    ].filter(Boolean).join(' · ');
  };

  const searchPeople = async () => {
    fieldErrors.value = {};

    if (personSearch.value.length < 3) {
      personResults.value = [];
      hasSearchedPeople.value = false;
      return;
    }

    isSearchingPeople.value = true;
    hasSearchedPeople.value = false;

    try {
      const response = await PersonService.getInstance().getPeople({
        search: personSearch.value,
        per_page: 10,
      });
      personResults.value = response.data.data.data ?? [];
      hasSearchedPeople.value = true;
    } catch (error) {
      errorMessage.value = 'No se pudo buscar personas.';
    } finally {
      isSearchingPeople.value = false;
    }
  };

  const selectPerson = (person) => {
    selectedPerson.value = person;
    form.person_id = person.id;
    personSearch.value = person.display_name;
    personResults.value = [];
    hasSearchedPeople.value = false;
  };

  const clearSelectedPerson = () => {
    selectedPerson.value = null;
    form.person_id = '';
    personResults.value = [];
    hasSearchedPeople.value = false;
  };

  const setCreditType = (creditType) => {
    form.credit_type = creditType;
    fieldErrors.value = {};

    if (creditType === 'free') {
      clearSelectedPerson();
      return;
    }

    form.display_name_override = '';
    resetPhoto();
  };

  const handleSectionChange = () => {
    if (form.section === 'technical') {
      form.character_name = '';
    }
  };

  const handlePhotoChange = (event) => {
    const [file] = event.target.files ?? [];

    resetPhoto();

    if (!file) {
      return;
    }

    selectedPhoto.value = file;
    photoPreviewUrl.value = URL.createObjectURL(file);
  };

  const loadCredits = async () => {
    const response = await ShowCreditService.getInstance().getShowCredits({
      show_id: show.value.id,
      per_page: 100,
    });
    credits.value = response.data.data.data ?? [];
  };

  const loadData = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      await loadCredits();
    } catch (error) {
      errorMessage.value = 'No se pudieron cargar los créditos del show.';
    } finally {
      isLoading.value = false;
    }
  };

  const open = async (selectedShow) => {
    show.value = selectedShow;
    resetForm();
    await nextTick();
    modalInstance.value.show();
    await loadData();
  };

  const close = () => {
    modalInstance.value.hide();
  };

  const submit = async () => {
    fieldErrors.value = {};
    errorMessage.value = '';
    isSubmitting.value = true;
    const lastCreditType = form.credit_type;

    try {
      if (isEditing.value) {
        await ShowCreditService.getInstance().updateShowCredit(currentCredit.value.id, getPayload());
      } else {
        await ShowCreditService.getInstance().createShowCredit(getPayload());
      }

      resetForm(lastCreditType);
      await loadCredits();
    } catch (error) {
      fieldErrors.value = error.response?.data?.errors ?? {};
      errorMessage.value = error.response?.data?.message ?? 'No se pudo guardar el crédito.';
    } finally {
      isSubmitting.value = false;
    }
  };

  const editCredit = (credit) => {
    fillForm(credit);
  };

  const deleteCredit = async (credit) => {
    if (!window.confirm(`¿Eliminar el crédito de "${getCreditPersonName(credit)}"?`)) {
      return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
      await ShowCreditService.getInstance().deleteShowCredit(credit.id);
      await loadCredits();

      if (currentCredit.value?.id === credit.id) {
        resetForm();
      }
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar el crédito.';
    } finally {
      isLoading.value = false;
    }
  };

  defineExpose({
    open,
  });

  onMounted(() => {
    modalInstance.value = new Modal(modalElement.value);
  });

  onBeforeUnmount(() => {
    resetPhoto();
    modalInstance.value?.dispose();
  });
</script>

<template>
  <div ref="modalElement" class="modal modal-blur fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title">{{ modalTitle }}</h5>
            <div class="text-secondary">Elenco y ficha técnica</div>
          </div>
          <button class="btn-close" type="button" aria-label="Close" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div v-if="isLoading" class="d-flex align-items-center mb-3">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Cargando créditos...</span>
          </div>

          <form class="border rounded p-3 mb-4" @submit.prevent="submit">
            <div class="mb-3">
              <label class="form-label">Tipo de crédito</label>
              <div class="btn-group" role="group" aria-label="Tipo de crédito">
                <button
                  class="btn"
                  :class="form.credit_type === 'person' ? 'btn-primary' : 'btn-outline-secondary'"
                  type="button"
                  @click="setCreditType('person')"
                >
                  Persona
                </button>
                <button
                  class="btn"
                  :class="form.credit_type === 'free' ? 'btn-primary' : 'btn-outline-secondary'"
                  type="button"
                  @click="setCreditType('free')"
                >
                  Nombre libre
                </button>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div v-if="!isFreeCredit" class="mb-3">
                  <label class="form-label" for="show-credit-person">Persona</label>
                  <div v-if="selectedPerson" class="border rounded p-2">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                      <div>
                        <div class="fw-semibold">{{ selectedPerson.display_name }}</div>
                        <div class="text-secondary small">{{ getPersonMeta(selectedPerson) || '-' }}</div>
                      </div>
                      <button class="btn btn-sm btn-outline-secondary" type="button" @click="clearSelectedPerson">
                        Cambiar
                      </button>
                    </div>
                  </div>
                  <div v-else>
                    <div class="input-group">
                      <input
                        id="show-credit-person"
                        v-model.trim="personSearch"
                        class="form-control"
                        :class="{ 'is-invalid': getFieldError('person_id') }"
                        type="search"
                        minlength="3"
                        placeholder="Buscar por nombre, email o documento"
                      >
                      <button
                        class="btn btn-outline-primary"
                        type="button"
                        :disabled="personSearch.length < 3 || isSearchingPeople"
                        @click="searchPeople"
                      >
                        Buscar
                      </button>
                    </div>
                    <div class="form-hint">
                      Escribí al menos 3 caracteres.
                    </div>
                    <div v-if="personResults.length > 0" class="list-group mt-2">
                      <button
                        v-for="person in personResults"
                        :key="person.id"
                        class="list-group-item list-group-item-action"
                        type="button"
                        @click="selectPerson(person)"
                      >
                        <div class="fw-semibold">{{ person.display_name }}</div>
                        <div class="text-secondary small">{{ getPersonMeta(person) || '-' }}</div>
                      </button>
                    </div>
                    <div v-else-if="hasSearchedPeople && !isSearchingPeople" class="form-hint">
                      No hay resultados para esa búsqueda.
                    </div>
                  </div>
                  <div v-if="getFieldError('person_id')" class="invalid-feedback">
                    {{ getFieldError('person_id') }}
                  </div>
                </div>
                <div v-else class="mb-3">
                  <label class="form-label" for="show-credit-display-name">Nombre a mostrar</label>
                  <input
                    id="show-credit-display-name"
                    v-model.trim="form.display_name_override"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('display_name_override') }"
                    type="text"
                    maxlength="160"
                    placeholder="Ej: RGB Entertainment - Preludio producciones"
                    required
                  >
                  <div v-if="getFieldError('display_name_override')" class="invalid-feedback">
                    {{ getFieldError('display_name_override') }}
                  </div>
                </div>
              </div>

              <div class="col-md-3">
                <div class="mb-3">
                  <label class="form-label" for="show-credit-character">Nombre del personaje</label>
                  <input
                    id="show-credit-character"
                    placeholder="Ej: John Doe el casero"
                    v-model.trim="form.character_name"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('character_name') }"
                    type="text"
                    maxlength="160"
                    :disabled="form.section === 'technical'"
                  >
                  <div v-if="getFieldError('character_name')" class="invalid-feedback">
                    {{ getFieldError('character_name') }}
                  </div>
                </div>
              </div>

              <div class="col-md-3">
                <div class="mb-3">
                  <label class="form-label" for="show-credit-role">Rol de la persona</label>
                  <input
                    id="show-credit-role"
                    v-model.trim="form.role_label"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('role_label') }"
                    type="text"
                    maxlength="160"
                    placeholder="Ej: Actriz, Dirección, Producción general"
                    required
                  >
                  <div v-if="getFieldError('role_label')" class="invalid-feedback">
                    {{ getFieldError('role_label') }}
                  </div>
                </div>
              </div>

              <div class="col-md-2">
                <div class="mb-3">
                  <label class="form-label" for="show-credit-section">Sección</label>
                  <select
                    id="show-credit-section"
                    v-model="form.section"
                    class="form-select"
                    :class="{ 'is-invalid': getFieldError('section') }"
                    required
                    @change="handleSectionChange"
                  >
                    <option value="">Seleccionar...</option>
                    <option value="cast">Elenco</option>
                    <option value="technical">Ficha técnica</option>
                  </select>
                  <div v-if="getFieldError('section')" class="invalid-feedback">
                    {{ getFieldError('section') }}
                  </div>
                </div>
              </div>

            </div>

            <div v-if="isFreeCredit" class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label" for="show-credit-photo">Foto</label>
                  <input
                    id="show-credit-photo"
                    ref="photoInput"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('photo') }"
                    type="file"
                    accept="image/jpeg,.jpg,.jpeg"
                    @change="handlePhotoChange"
                  >
                  <div class="form-hint">
                    Formatos aceptados: jpg/jpeg. Tamaño máximo 500 KB.
                  </div>
                  <div v-if="getFieldError('photo')" class="invalid-feedback">
                    {{ getFieldError('photo') }}
                  </div>
                </div>
              </div>

              <div v-if="photoPreviewUrl" class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Vista previa</label>
                  <div class="d-flex align-items-center gap-3">
                    <span
                      class="avatar avatar-xl"
                      :style="{ backgroundImage: `url(${photoPreviewUrl})` }"
                    ></span>
                    <button v-if="selectedPhoto" class="btn btn-outline-secondary" type="button" @click="resetPhoto">
                      Quitar foto seleccionada
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="row align-items-end">
              <div class="col-md-2">
                <div class="mb-0">
                  <label class="form-label" for="show-credit-sort-order">Orden</label>
                  <input
                    id="show-credit-sort-order"
                    v-model="form.sort_order"
                    class="form-control"
                    :class="{ 'is-invalid': getFieldError('sort_order') }"
                    type="number"
                    min="1"
                  >
                  <div v-if="getFieldError('sort_order')" class="invalid-feedback">
                    {{ getFieldError('sort_order') }}
                  </div>
                </div>
              </div>

              <div class="col-md-10 text-end">
                <button
                  v-if="isEditing"
                  class="btn btn-link"
                  type="button"
                  :disabled="isSubmitting"
                  @click="resetForm"
                >
                  Cancelar edición
                </button>
                <button class="btn btn-success" type="submit" :disabled="isSubmitting || isLoading">
                  <span
                    v-if="isSubmitting"
                    aria-hidden="true"
                    class="spinner-border spinner-border-sm me-2"
                  ></span>
                  {{ submitLabel }}
                </button>
              </div>
            </div>
          </form>

          <div class="row">
            <div class="col-md-6">
              <h3 class="card-title mb-3">Elenco</h3>
              <div v-if="castCredits.length === 0" class="empty border rounded">
                <p class="empty-title">Sin elenco cargado</p>
              </div>
              <div v-else class="list-group">
                <div v-for="credit in castCredits" :key="credit.id" class="list-group-item">
                  <div class="row align-items-center">
                    <div class="col-auto" v-if="credit.photo_url">
                      <span
                        class="avatar"
                        :style="{ backgroundImage: `url(${credit.photo_url})` }"
                      ></span>
                    </div>
                    <div class="col">
                      <div class="fw-semibold">
                        {{ getCreditPersonName(credit) }}
                        <span v-if="isFreeNameCredit(credit)" class="badge bg-secondary-lt ms-2">
                          Nombre libre
                        </span>
                      </div>
                      <div v-if="credit.character_name" class="text-secondary small">
                        {{ credit.character_name }}
                      </div>
                      <div class="text-secondary small">{{ getRoleLabel(credit) }}</div>
                    </div>
                    <div class="col-auto">
                      <div class="btn-list">
                        <button class="btn btn-sm btn-outline-primary" type="button" @click="editCredit(credit)">
                          Editar
                        </button>
                        <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteCredit(credit)">
                          Eliminar
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <h3 class="card-title mb-3">Ficha técnica</h3>
              <div v-if="technicalCredits.length === 0" class="empty border rounded">
                <p class="empty-title">Sin ficha técnica cargada</p>
              </div>
              <div v-else class="list-group">
                <div v-for="credit in technicalCredits" :key="credit.id" class="list-group-item">
                  <div class="row align-items-center">
                    <div class="col-auto" v-if="credit.photo_url">
                      <span
                        class="avatar"
                        :style="{ backgroundImage: `url(${credit.photo_url})` }"
                      ></span>
                    </div>
                    <div class="col">
                      <div class="fw-semibold">
                        {{ getCreditPersonName(credit) }}
                        <span v-if="isFreeNameCredit(credit)" class="badge bg-secondary-lt ms-2">
                          Nombre libre
                        </span>
                      </div>
                      <div class="text-secondary small">{{ getRoleLabel(credit) }}</div>
                    </div>
                    <div class="col-auto">
                      <div class="btn-list">
                        <button class="btn btn-sm btn-outline-primary" type="button" @click="editCredit(credit)">
                          Editar
                        </button>
                        <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteCredit(credit)">
                          Eliminar
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <a class="btn btn-outline-secondary" href="/admin/people">
            Administrar personas
          </a>
          <button class="btn btn-link link-secondary ms-auto" type="button" @click="close">
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
