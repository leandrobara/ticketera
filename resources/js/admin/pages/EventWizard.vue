<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import ApiService from '@/admin/services/ApiService';
import WizardField from '@/admin/components/WizardField.vue';
import DateTimeField from '@/admin/components/DateTimeField.vue';
import PersonModal from '@/admin/components/people/PersonModal.vue';
import VenueModal from '@/admin/components/venues/VenueModal.vue';
const api = ApiService.getInstance();
const step = ref(1), busy = ref(false), loading = ref(true), error = ref(''), errors = ref({}), notice = ref('');
const venues = ref([]), people = ref([]), dates = ref([]), presentations = ref([]), status = ref('draft');
const personModal = ref(null), venueModal = ref(null), choosingVenue = ref(false), imageFile = ref(null), imageInput = ref(null), imageId = ref(null), imageRemoved = ref(false), imageUrl = ref('');
const feeChoice = ref('');
const localImageUrl = ref('');
const imagePreviewWidth = ref(null);
const displayedImageUrl = computed(() => localImageUrl.value || (imageRemoved.value ? '' : imageUrl.value));
let savedState = '';
let leavingAfterSave = false;
const sourceShowTitle = ref('');
const steps = ['Datos del evento', 'Fechas y funciones', 'Entradas', 'Cargos extras', 'Completar la ficha', 'Resumen'];
const socialFields = [['instagram_url', 'Instagram'], ['facebook_url', 'Facebook'], ['x_url', 'X'], ['tiktok_url', 'TikTok'], ['youtube_url', 'YouTube'], ['pinterest_url', 'Pinterest'], ['website_url', 'Sitio web oficial']];
const optionalShowFields = new Set(['additional_information', 'production_note', 'faqs', 'comments_enabled', ...socialFields.map(([key]) => key)]);
const form = reactive({
  show_id: null, season_id: null, venue_id: '', venue: { name: '', address: '', capacity: '' }, capacity: '',
  show: { title: '', subtitle: '', synopsis: '', genre: '', age_rating: 'ATP', additional_information: '', production_note: '', slug: '', comments_enabled: true, format: 'theater', faqs: [], service_fee_type: 'fixed_amount', service_fee_fixed_amount: 0, service_fee_percentage: null, service_fee_minimum_unit_amount: 0, ...Object.fromEntries(socialFields.map(([key]) => [key, ''])) },
  credits: [], links: [], ticket_types: [], configuration: { groups: [], individual_dates: [], excluded_starts_at: [] }
});
const pageTitle = computed(() => form.season_id ? `Editar ${form.show.title || 'evento'}` : 'Crear evento');
const editingTicketIndex = ref(null);
const ticketDraft = reactive(emptyTicket());
const ticketEditorError = ref('');
let ticketDraftBaseline = JSON.stringify(ticketDraft);
const creditSections = [{ key: 'cast', title: 'Elenco' }, { key: 'technical', title: 'Equipo técnico' }];
const creditDrafts = reactive({ cast: emptyCredit('cast'), technical: emptyCredit('technical') });
const editingCredits = reactive({ cast: null, technical: null });
const creditEditorErrors = reactive({ cast: '', technical: '' });
const creditDraftBaselines = { cast: JSON.stringify(creditDrafts.cast), technical: JSON.stringify(creditDrafts.technical) };
function emptyCredit(section) {
  return { person_id: '', character_name: '', role_label: '', section, sort_order: 1 };
}
function creditsIn(section) { return form.credits.filter(credit => credit.section === section).sort((a, b) => Number(a.sort_order) - Number(b.sort_order)); }
function personForCredit(id) { return people.value.find(person => Number(person.id) === Number(id)); }
function personName(id) { return personForCredit(id)?.display_name ?? 'Persona sin nombre'; }
function personPhoto(id) { const person = personForCredit(id); return person?.photo_url || person?.photo_path || ''; }
function personInitial(id) { return personName(id).trim().charAt(0).toLocaleUpperCase('es-AR') || 'P'; }
function setCreditDraft(section, credit) {
  for (const key of Object.keys(creditDrafts[section])) delete creditDrafts[section][key];
  Object.assign(creditDrafts[section], emptyCredit(section), credit);
  creditDrafts[section].section = section;
  creditDraftBaselines[section] = JSON.stringify(creditDrafts[section]);
}
function creditDraftHasChanges(section) { return JSON.stringify(creditDrafts[section]) !== creditDraftBaselines[section]; }
function resetCreditDraft(section) {
  editingCredits[section] = null;
  creditEditorErrors[section] = '';
  setCreditDraft(section, { sort_order: Math.max(0, ...creditsIn(section).map(credit => Number(credit.sort_order) || 0)) + 1 });
}
function editCredit(section, credit) {
  if (creditDraftHasChanges(section)) {
    creditEditorErrors[section] = 'Guardá o descartá los cambios del formulario antes de editar otro crédito.';
    document.getElementById('credit-editor-' + section)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return;
  }
  editingCredits[section] = credit;
  creditEditorErrors[section] = '';
  setCreditDraft(section, credit);
  nextTick(() => document.getElementById('credit-editor-' + section)?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
}
function removeCredit(section, credit) {
  const index = form.credits.indexOf(credit);
  if (index !== -1) form.credits.splice(index, 1);
  if (editingCredits[section] === credit) resetCreditDraft(section);
  else if (editingCredits[section] === null && !creditDraftHasChanges(section)) resetCreditDraft(section);
}
function saveCreditDraft(section) {
  const editor = document.getElementById('credit-editor-' + section);
  const invalid = [...editor.querySelectorAll('input, select, textarea')].find(input => !input.disabled && !input.checkValidity());
  if (invalid) { invalid.reportValidity(); invalid.focus(); return; }
  const credit = { ...creditDrafts[section], section, character_name: section === 'cast' ? creditDrafts[section].character_name : null };
  if (editingCredits[section] === null) form.credits.push(credit);
  else {
    const index = form.credits.indexOf(editingCredits[section]);
    if (index !== -1) form.credits.splice(index, 1, credit);
  }
  resetCreditDraft(section);
  nextTick(() => document.getElementById('credit-summaries-' + section)?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
}
function pendingCreditSection() { return creditSections.find(({ key }) => editingCredits[key] !== null || creditDraftHasChanges(key))?.key; }
const itemDrafts = reactive({ link: emptyItem('link'), faq: emptyItem('faq') });
const editingItems = reactive({ link: null, faq: null });
const itemEditorErrors = reactive({ link: '', faq: '' });
const itemDraftBaselines = { link: JSON.stringify(itemDrafts.link), faq: JSON.stringify(itemDrafts.faq) };
function emptyItem(kind) { return kind === 'link' ? { text: '', url: '', sort_order: 1 } : { question: '', answer: '', sort_order: 1 }; }
function itemsFor(kind) { return kind === 'link' ? form.links : form.show.faqs; }
function sortedItems(kind) { return [...itemsFor(kind)].sort((a, b) => Number(a.sort_order) - Number(b.sort_order)); }
function setItemDraft(kind, item) {
  for (const key of Object.keys(itemDrafts[kind])) delete itemDrafts[kind][key];
  Object.assign(itemDrafts[kind], emptyItem(kind), item);
  itemDraftBaselines[kind] = JSON.stringify(itemDrafts[kind]);
}
function itemDraftHasChanges(kind) { return JSON.stringify(itemDrafts[kind]) !== itemDraftBaselines[kind]; }
function resetItemDraft(kind) {
  editingItems[kind] = null;
  itemEditorErrors[kind] = '';
  setItemDraft(kind, { sort_order: Math.max(0, ...itemsFor(kind).map(item => Number(item.sort_order) || 0)) + 1 });
}
function editItem(kind, item) {
  if (itemDraftHasChanges(kind)) {
    itemEditorErrors[kind] = 'Guardá o descartá los cambios del formulario antes de editar otro elemento.';
    document.getElementById(kind + '-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return;
  }
  editingItems[kind] = item;
  itemEditorErrors[kind] = '';
  setItemDraft(kind, item);
  nextTick(() => document.getElementById(kind + '-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
}
function removeItem(kind, item) {
  const index = itemsFor(kind).indexOf(item);
  if (index !== -1) itemsFor(kind).splice(index, 1);
  if (editingItems[kind] === item) resetItemDraft(kind);
  else if (editingItems[kind] === null && !itemDraftHasChanges(kind)) resetItemDraft(kind);
}
function saveItemDraft(kind) {
  const editor = document.getElementById(kind + '-editor');
  const invalid = [...editor.querySelectorAll('input, select, textarea')].find(input => !input.disabled && !input.checkValidity());
  if (invalid) { invalid.reportValidity(); invalid.focus(); return; }
  const item = { ...itemDrafts[kind] };
  if (editingItems[kind] === null) itemsFor(kind).push(item);
  else {
    const index = itemsFor(kind).indexOf(editingItems[kind]);
    if (index !== -1) itemsFor(kind).splice(index, 1, item);
  }
  resetItemDraft(kind);
  nextTick(() => document.getElementById(kind + '-summaries')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
}
function pendingItemKind() { return ['link', 'faq'].find(kind => editingItems[kind] !== null || itemDraftHasChanges(kind)); }
function emptyTicket() {
  return { name: '', price: '', stock: null, is_active: true, sort_order: 1, promotion_type: null,
    promotion_name: '', promotion_value: null, promotion_bundle_quantity: null, promotion_pay_quantity: null,
    promotion_access_code: '', promotion_is_active: false };
}
watch(() => form.venue.capacity, (value, previous) => {
  if (!form.venue_id && !form.season_id && (form.capacity === '' || String(form.capacity) === String(previous))) form.capacity = value;
});
const recurring = reactive({ frequency: 'weekly', weekdays: [], starts_on: '', ends_on: '', start_time: '', end_time: '' });
watch(() => recurring.starts_on, first => {
  if (first && recurring.ends_on && recurring.ends_on < first) recurring.ends_on = '';
});
const individual = reactive({ starts_at: '', ends_at: '' });
watch(() => individual.starts_at, start => {
  if (start && individual.ends_at && individual.ends_at <= start) individual.ends_at = '';
});
const weekdays = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
const venueOptions = computed(() => [{ value: '', label: 'Elegir ubicación' }, ...venues.value.map(v => ({ value: v.id, label: v.name }))]);
const selectedVenue = computed(() => venues.value.find(v => Number(v.id) === Number(form.venue_id)) ?? (form.venue_id ? form.venue : null));
const venueCapacity = computed(() => {
  const value = selectedVenue.value?.capacity ?? form.venue.capacity;
  return value === null || value === undefined || value === '' ? null : value;
});
const capacityMatchesVenue = computed(() => form.capacity !== '' && venueCapacity.value !== null && Number(form.capacity) === Number(venueCapacity.value));
const personOptions = computed(() => [{ value: '', label: 'Elegir persona' }, ...people.value.map(p => ({ value: p.id, label: p.display_name }))]);
const showFields = Object.keys(form.show);
const protectedDates = computed(() => presentations.value.filter(p => p.is_protected));
const displayDates = computed(() => {
  const all = new Map(dates.value.map(d => [d.starts_at, { ...d, is_protected: false }]));
  for (const p of protectedDates.value) all.set(p.local_starts_at, { starts_at: p.local_starts_at, ends_at: p.local_ends_at, is_protected: true });
  return [...all.values()].sort((a, b) => a.starts_at.localeCompare(b.starts_at));
});
function nullable(value) { return value === '' ? null : value; }
function showError(e) { error.value = e.response?.data?.error?.message ?? e.response?.data?.message ?? 'No se pudo completar la operación.'; errors.value = e.response?.data?.error?.fields ?? e.response?.data?.errors ?? {}; }
function currentState() { return JSON.stringify({ form, ticketDraft, creditDrafts, itemDrafts, individual, recurring, feeChoice: feeChoice.value, imageRemoved: imageRemoved.value, image: imageFile.value ? [imageFile.value.name, imageFile.value.size, imageFile.value.lastModified] : null }); }
function beforeUnload(event) { if (!leavingAfterSave && savedState && currentState() !== savedState) { event.preventDefault(); event.returnValue = ''; } }
watch(imageFile, file => {
  if (localImageUrl.value) URL.revokeObjectURL(localImageUrl.value);
  localImageUrl.value = file ? URL.createObjectURL(file) : '';
});
watch(displayedImageUrl, () => { imagePreviewWidth.value = null; });
function sizeImagePreview(event) {
  requestAnimationFrame(() => {
    if (event.target.isConnected) imagePreviewWidth.value = event.target.getBoundingClientRect().width;
  });
}
function removeImage() {
  if (imageFile.value) {
    imageFile.value = null;
    if (imageInput.value) imageInput.value.value = '';
  } else if (imageId.value) {
    imageRemoved.value = true;
  }
}
function selectVenue() {
  const venue = venues.value.find(v => Number(v.id) === Number(form.venue_id));
  if (venue) {
    form.venue = { name: venue.name, address: venue.address ?? '', capacity: venue.capacity ?? '' };
    form.capacity = venue.capacity ?? '';
    choosingVenue.value = false;
  } else {
    form.venue = { name: '', address: '', capacity: '' };
  }
}
function venueSaved(venue) {
  if (!venue?.id) return;
  venues.value = [...venues.value.filter(v => Number(v.id) !== Number(venue.id)), venue].sort((a, b) => a.name.localeCompare(b.name, 'es'));
  form.venue_id = venue.id;
  selectVenue();
}
async function loadOptions() {
  const [v, p] = await Promise.all([api.get('/api/admin/venues', { params: { per_page: 1000 } }), api.get('/api/admin/people', { params: { per_page: 1000 } })]);
  venues.value = v.data.data.data; people.value = p.data.data.data;
}
async function personSaved() { await loadOptions(); }
function feeChoiceFor(show) {
  if (show.service_fee_type === 'percentage') return 'percentage';
  return Number(show.service_fee_fixed_amount ?? 0) === 0 && Number(show.service_fee_minimum_unit_amount ?? 0) === 0 ? 'none' : 'fixed_amount';
}
function chooseFee(choice) {
  feeChoice.value = choice;
  if (choice === 'none') {
    Object.assign(form.show, { service_fee_type: 'fixed_amount', service_fee_fixed_amount: 0, service_fee_percentage: null, service_fee_minimum_unit_amount: 0 });
  } else if (choice === 'fixed_amount') {
    form.show.service_fee_type = 'fixed_amount';
    form.show.service_fee_percentage = null;
  } else if (choice === 'percentage') {
    form.show.service_fee_type = 'percentage';
    form.show.service_fee_fixed_amount = null;
  }
}
function fill(snapshot) {
  form.show_id = snapshot.show_id; form.season_id = snapshot.season_id; form.venue_id = snapshot.venue_id;
  form.venue = snapshot.venue; form.capacity = snapshot.capacity;
  choosingVenue.value = false;
  for (const key of showFields) if (snapshot.show[key] !== undefined) form.show[key] = snapshot.show[key] ?? (key === 'faqs' ? [] : '');
  feeChoice.value = feeChoiceFor(form.show);
  form.credits = snapshot.credits ?? []; form.links = snapshot.links ?? []; form.ticket_types = snapshot.ticket_types ?? [];
  form.configuration = { groups: [], individual_dates: [], excluded_starts_at: [], ...snapshot.configuration };
  presentations.value = snapshot.presentations ?? []; status.value = snapshot.status ?? 'draft';
  imageId.value = snapshot.show.main_image?.id ?? null;
  imageUrl.value = snapshot.show.main_image?.url ?? snapshot.show.main_image?.path ?? '';
  imageRemoved.value = false;
}
function payload(action) {
  const show = Object.fromEntries(showFields.map(key => [key, nullable(form.show[key])]));
  show.format = 'theater';
  if (show.service_fee_type === 'fixed_amount') show.service_fee_percentage = null; else show.service_fee_fixed_amount = null;
  return {
    ...form, show, action, venue_id: form.venue_id ? Number(form.venue_id) : null, venue: { ...form.venue, capacity: nullable(form.venue.capacity) }, capacity: form.capacity === '' ? 0 : Number(form.capacity),
    credits: form.credits.map((c, i) => ({ id: c.id ?? null, person_id: Number(c.person_id), role_label: nullable(c.role_label), character_name: c.section === 'cast' ? nullable(c.character_name) : null, section: c.section, sort_order: Number(c.sort_order) || i + 1 })),
    links: form.links.map((l, i) => ({ id: l.id ?? null, text: l.text, url: l.url, sort_order: Number(l.sort_order) || i + 1 })),
    ticket_types: form.ticket_types.map((t, i) => ({ id: t.id ?? null, name: t.name, price: t.price, stock: nullable(t.stock), is_active: t.is_active, sort_order: Number(t.sort_order) || i + 1, promotion_name: nullable(t.promotion_name), promotion_type: nullable(t.promotion_type), promotion_value: nullable(t.promotion_value), promotion_bundle_quantity: nullable(t.promotion_bundle_quantity), promotion_pay_quantity: nullable(t.promotion_pay_quantity), promotion_access_code: nullable(t.promotion_access_code), promotion_is_active: Boolean(t.promotion_type && t.promotion_is_active) }))
  };
}
async function save(action) {
  if (busy.value) return false; busy.value = true; error.value = ''; errors.value = {}; notice.value = '';
  try {
    const pendingSection = pendingCreditSection();
    if (pendingSection) {
      step.value = 5;
      creditEditorErrors[pendingSection] = 'Guardá el crédito o descartá los cambios del formulario antes de guardar el evento.';
      await nextTick();
      document.getElementById('credit-editor-' + pendingSection)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return false;
    }
    const pendingItem = pendingItemKind();
    if (pendingItem) {
      step.value = 5;
      itemEditorErrors[pendingItem] = 'Guardá el elemento o descartá los cambios del formulario antes de guardar el evento.';
      await nextTick();
      document.getElementById(pendingItem + '-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return false;
    }
    if (!form.ticket_types.length) {
      step.value = 3;
      error.value = 'Creá al menos una entrada antes de guardar el evento.';
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return false;
    }
    if (!feeChoice.value) {
      step.value = 4;
      error.value = 'Elegí cómo se aplicarán los cargos extras antes de guardar.';
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return false;
    }
    if (action === 'publish' && !form.ticket_types.some(ticket => ticket.is_active && String(ticket.name ?? '').trim())) {
      step.value = 3;
      error.value = 'Para publicar necesitás al menos un tipo de entrada activo.';
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return false;
    }
    const imageToRemoveId = imageRemoved.value ? imageId.value : null;
    const response = await api.post('/api/admin/event-wizard', payload(action));
    const result = response.data.data; fill(result);
    window.history.replaceState({}, '', '/admin/events/new?season_id=' + form.season_id);
    if (imageToRemoveId) {
      try {
        await api.delete('/api/admin/images/' + imageToRemoveId);
        imageId.value = null;
        imageUrl.value = '';
      } catch (e) {
        showError(e);
        imageId.value = imageToRemoveId;
        imageRemoved.value = true;
        error.value = 'El evento se guardó, pero no se pudo eliminar la imagen. Reintentá el guardado.';
        return false;
      }
    }
    if (imageFile.value) {
      try {
        const upload = new FormData(); upload.append('image', imageFile.value); upload.append('show_id', form.show_id); upload.append('is_main', '1'); upload.append('type', 'grid');
        const image = await api.post('/api/admin/images', upload); imageId.value = image.data.data.id; imageUrl.value = image.data.data.url ?? ''; imageFile.value = null;
        if (imageInput.value) imageInput.value.value = '';
      } catch (e) {
        showError(e);
        error.value = 'El evento se guardó, pero no se pudo cargar la imagen. Reintentá el guardado.';
        return false;
      }
    }
    notice.value = (action === 'publish' ? 'Evento publicado.' : 'Cambios guardados.') + (result.preserved_presentation_ids?.length ? ' Se conservaron las funciones pasadas o con órdenes.' : '');
    savedState = currentState();
    leavingAfterSave = true; window.location.assign('/admin/events');
    return true;
  } catch (e) {
    showError(e);
    const field = Object.keys(errors.value)[0] ?? '';
    const showField = (field.startsWith('show.') ? field.slice(5) : field).split('.')[0];
    if (field === 'capacity' || field.startsWith('ticket_types') || field.startsWith('promotion_')) {
      step.value = 3;
      const ticketIndex = Number(field.match(/^ticket_types\.(\d+)\./)?.[1]);
      if (Number.isInteger(ticketIndex) && form.ticket_types[ticketIndex]) {
        editingTicketIndex.value = ticketIndex;
        setTicketDraft(form.ticket_types[ticketIndex]);
      }
    }
    else if (field.includes('service_fee')) step.value = 4;
    else if (field.startsWith('configuration')) step.value = 2;
    else if (field.startsWith('credits') || field.startsWith('links') || optionalShowFields.has(showField)) step.value = 5;
    else if (field && field !== 'action') step.value = 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return false;
  } finally { busy.value = false; }
}
async function next() {
  if (busy.value) return;
  busy.value = true;
  try {
    error.value = ''; errors.value = {};
    if (step.value === 3 && !form.ticket_types.length) {
      ticketEditorError.value = 'Creá la primera entrada con el botón «Crear entrada» para continuar.';
      document.getElementById('ticket-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    if (step.value === 3 && (editingTicketIndex.value !== null || ticketDraftHasChanges())) {
      ticketEditorError.value = 'Guardá la entrada o descartá los cambios del formulario antes de continuar.';
      document.getElementById('ticket-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    const pendingSection = step.value === 5 ? pendingCreditSection() : null;
    if (pendingSection) {
      creditEditorErrors[pendingSection] = 'Guardá el crédito o descartá los cambios del formulario antes de continuar.';
      document.getElementById('credit-editor-' + pendingSection)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    const pendingItem = step.value === 5 ? pendingItemKind() : null;
    if (pendingItem) {
      itemEditorErrors[pendingItem] = 'Guardá el elemento o descartá los cambios del formulario antes de continuar.';
      document.getElementById(pendingItem + '-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    const invalid = [...document.querySelectorAll('.event-wizard-page input, .event-wizard-page textarea, .event-wizard-page select')]
      .find(input => !input.closest('.modal') && !(step.value === 3 && input.closest('#ticket-editor')) && !input.closest('.wizard-credit-editor, .wizard-item-editor') && !input.disabled && !input.checkValidity());
    if (invalid) { invalid.reportValidity(); invalid.focus(); return; }
    if (step.value === 1 && imageFile.value) {
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(imageFile.value.type)) { error.value = 'Elegí una imagen JPG, PNG o WebP.'; return; }
      if (imageFile.value.size > 3 * 1024 * 1024) { error.value = 'La imagen no puede superar los 3 MB.'; return; }
    }
    if (step.value === 2) {
      if (individual.starts_at || individual.ends_at) {
        if (!await addIndividual()) return;
      } else if (!await preview()) return;
      if (!displayDates.value.length) {
        error.value = 'Agregá al menos una función para continuar.';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
      }
    }
    step.value++; window.scrollTo({ top: 0, behavior: 'smooth' });
  } finally {
    busy.value = false;
  }
}
async function previewConfiguration(configuration) {
  try {
    const r = await api.post('/api/admin/event-wizard/preview-dates', { configuration });
    dates.value = r.data.data;
    form.configuration = { groups: [], individual_dates: dates.value.map(d => ({ date: d.starts_at.slice(0, 10), start_time: d.starts_at.slice(11, 16), end_time: d.ends_at.slice(11, 16), ends_on: d.ends_at.slice(0, 10) === d.starts_at.slice(0, 10) ? null : d.ends_at.slice(0, 10) })), excluded_starts_at: [] };
    return true;
  } catch (e) { showError(e); return false; }
}
async function preview() { return previewConfiguration(form.configuration); }
async function generateRecurringDates() {
  error.value = ''; errors.value = {};
  const configuration = { ...form.configuration, groups: [...form.configuration.groups, { ...recurring, weekdays: [...recurring.weekdays] }] };
  if (!await previewConfiguration(configuration)) return;
  await nextTick();
  document.getElementById('wizard-functions')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
async function addIndividual() {
  error.value = ''; errors.value = {};
  if (!individual.starts_at || !individual.ends_at) { error.value = 'Completá el inicio y el fin de la función.'; return false; }
  if (individual.ends_at <= individual.starts_at) { error.value = 'El fin debe ser posterior al inicio de la función.'; return false; }
  const configuration = { ...form.configuration, individual_dates: [...form.configuration.individual_dates, {
    date: individual.starts_at.slice(0, 10), start_time: individual.starts_at.slice(11, 16),
    end_time: individual.ends_at.slice(11, 16),
    ends_on: individual.ends_at.slice(0, 10) === individual.starts_at.slice(0, 10) ? null : individual.ends_at.slice(0, 10),
  }] };
  if (!await previewConfiguration(configuration)) return false;
  Object.assign(individual, { starts_at: '', ends_at: '' });
  return true;
}
async function removeDate(d) {
  const configuration = { ...form.configuration, individual_dates: form.configuration.individual_dates.filter(value => `${value.date} ${value.start_time}:00` !== d.starts_at) };
  await previewConfiguration(configuration);
}
function setTicketDraft(ticket) {
  for (const key of Object.keys(ticketDraft)) delete ticketDraft[key];
  Object.assign(ticketDraft, emptyTicket(), ticket);
  ticketDraftBaseline = JSON.stringify(ticketDraft);
}
function ticketDraftHasChanges() { return JSON.stringify(ticketDraft) !== ticketDraftBaseline; }
function resetTicketDraft() {
  editingTicketIndex.value = null;
  ticketEditorError.value = '';
  setTicketDraft({ sort_order: Math.max(0, ...form.ticket_types.map(ticket => Number(ticket.sort_order) || 0)) + 1 });
}
function editTicket(index) {
  if (ticketDraftHasChanges()) {
    ticketEditorError.value = 'Guardá o descartá los cambios del formulario antes de editar otra entrada.';
    document.getElementById('ticket-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return;
  }
  ticketEditorError.value = '';
  editingTicketIndex.value = index;
  setTicketDraft(form.ticket_types[index]);
  nextTick(() => document.getElementById('ticket-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
}
function removeTicket(index) {
  if (form.ticket_types.length <= 1) return;
  form.ticket_types.splice(index, 1);
  if (editingTicketIndex.value === index) resetTicketDraft();
  else if (editingTicketIndex.value !== null && editingTicketIndex.value > index) editingTicketIndex.value--;
  else if (editingTicketIndex.value === null && !ticketDraftHasChanges()) resetTicketDraft();
}
function saveTicketDraft() {
  const invalid = [...document.querySelectorAll('#ticket-editor input, #ticket-editor select, #ticket-editor textarea')]
    .find(input => !input.disabled && !input.checkValidity());
  if (invalid) { invalid.reportValidity(); invalid.focus(); return; }
  ticketEditorError.value = '';
  const ticket = { ...ticketDraft };
  if (editingTicketIndex.value === null) form.ticket_types.push(ticket);
  else form.ticket_types.splice(editingTicketIndex.value, 1, ticket);
  resetTicketDraft();
  nextTick(() => document.getElementById('ticket-summaries')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
}
watch(step, value => {
  if (value === 3 && editingTicketIndex.value === null && !ticketDraftHasChanges()) resetTicketDraft();
});
function togglePromotion(t, event) { t.promotion_is_active = event.target.checked; t.promotion_type = t.promotion_is_active ? 'percent_discount' : null; }
function timeHint(start, end) { return start && end && end < start ? 'Finaliza al día siguiente.' : ''; }
function formatDate(value) { return new Date(value.replace(' ', 'T')).toLocaleString('es-AR', { dateStyle: 'medium', timeStyle: 'short', hourCycle: 'h23' }); }
function money(value) { return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(Number(value) || 0); }
function promotionSummary(ticket) {
  if (ticket.promotion_type === 'buy_x_get_y') return `${ticket.promotion_bundle_quantity}x${ticket.promotion_pay_quantity}`;
  if (ticket.promotion_type === 'percent_discount') return `${ticket.promotion_value}% de descuento`;
  return `${money(ticket.promotion_value)} de descuento`;
}
onMounted(async () => {
  try {
    await loadOptions();
    const params = new URLSearchParams(window.location.search);
    if (params.get('season_id')) { fill((await api.get('/api/admin/event-wizard/' + params.get('season_id'))).data.data); await preview(); }
    else if (params.get('show_id')) {
      const id = params.get('show_id'); const [s, c, l] = await Promise.all([api.get('/api/admin/shows/' + id), api.get('/api/admin/show-credits', { params: { show_id: id, per_page: 1000 } }), api.get('/api/admin/show-links', { params: { show_id: id, per_page: 1000 } })]);
      form.show_id = Number(id); sourceShowTitle.value = s.data.data.title ?? ''; for (const key of showFields) if (s.data.data[key] !== undefined) form.show[key] = s.data.data[key] ?? ''; form.credits = c.data.data.data; form.links = l.data.data.data; imageId.value = s.data.data.main_image?.id ?? null; imageUrl.value = s.data.data.main_image?.url ?? '';
    }
    resetCreditDraft('cast'); resetCreditDraft('technical'); resetItemDraft('link'); resetItemDraft('faq');
    savedState = currentState();
  } catch (e) { showError(e); } finally { loading.value = false; }
  window.addEventListener('beforeunload', beforeUnload);
});
onUnmounted(() => { window.removeEventListener('beforeunload', beforeUnload); if (localImageUrl.value) URL.revokeObjectURL(localImageUrl.value); });
</script>

<template>
  <div class="admin-form-page event-wizard-page">
    <a class="d-inline-block mb-3" href="/admin/events">← Listado de eventos</a>
    <div class="page-header mb-4 d-flex flex-row flex-nowrap align-items-center justify-content-between gap-3">
      <h1 class="page-title" :title="pageTitle" style="min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ pageTitle }}</h1>
      <span class="text-secondary text-nowrap flex-shrink-0" aria-live="polite">Paso {{ step }} de {{ steps.length }}</span>
    </div>
    <div v-if="error" class="alert alert-danger" role="alert">{{ error }}<ul v-if="Object.keys(errors).length"
        class="mb-0 mt-2">
        <li v-for="(messages, key) in errors" :key="key">{{ messages[0] }}</li>
      </ul>
    </div>
    <div v-if="notice" class="alert alert-success" role="status">{{ notice }}</div>
    <div v-if="sourceShowTitle && !form.season_id" class="alert alert-info" role="status">Nueva edición de <strong>{{
      sourceShowTitle }}</strong>. Se reutilizan los datos generales y la imagen del show; configurá las fechas, el
      lugar y las entradas de esta edición. Los cambios que hagas en los datos generales del show también afectarán a
      las ediciones anteriores.</div>
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary"></div>
    </div>
    <div v-else>
      <template v-if="step === 1">
        <section id="image" class="card mb-4">
          <div class="card-header">
            <h2 class="card-title">Imagen principal</h2>
          </div>
          <div class="card-body">
            <div v-if="displayedImageUrl" class="wizard-image-preview mb-3"
              :style="imagePreviewWidth ? { width: imagePreviewWidth + 'px' } : undefined">
              <img :src="displayedImageUrl" alt="Imagen principal del evento" class="wizard-image" @load="sizeImagePreview">
              <button class="btn btn-sm btn-danger wizard-image-remove" type="button"
                style="position: absolute; right: .5rem; bottom: .5rem" @click="removeImage">Eliminar</button>
            </div>
            <p v-else-if="imageRemoved" class="text-secondary small mb-3">La imagen se eliminará al guardar.
              <button class="btn btn-link btn-sm p-0" type="button" @click="imageRemoved = false">Deshacer</button>
            </p>
            <label for="event-image" class="form-label">Archivo</label>
            <input id="event-image" ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp" class="form-control"
              @change="imageFile = $event.target.files[0] ?? null">
          </div>
        </section>
        <section id="basic" class="card mb-4">
          <div class="card-header">
            <h2 class="card-title">Información básica</h2>
          </div>
          <div class="card-body">
            <WizardField id="event-title" v-model="form.show.title" label="Título" required />
            <WizardField id="event-subtitle" v-model="form.show.subtitle" label="Subtítulo" />
            <WizardField id="event-synopsis" v-model="form.show.synopsis" label="Sinopsis" type="textarea" />
            <div class="row">
              <div class="col-md-6">
                <WizardField id="event-genre" v-model="form.show.genre" label="Género" />
              </div>
              <div class="col-md-6">
                <WizardField id="event-age" v-model="form.show.age_rating" label="Público"
                  :options="['ATP', '+13', '+16', '+18'].map(v => ({ value: v, label: v }))" />
              </div>
            </div>
          </div>
        </section>
        <section id="venue" class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center gap-2">
            <h2 class="card-title">Ubicación del evento</h2>
            <button class="btn btn-sm btn-primary" type="button" :disabled="Boolean(form.season_id)"
              @click="venueModal.openForCreate()">Crear nueva ubicación</button>
          </div>
          <div class="card-body">
            <WizardField v-if="!selectedVenue || choosingVenue" id="event-venue" v-model="form.venue_id" label="Ubicación"
              :options="venueOptions" required @update:model-value="selectVenue" />
            <div v-else class="wizard-venue-card mb-3">
              <svg class="wizard-venue-icon" xmlns="http://www.w3.org/2000/svg" width="25" height="25"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round" aria-hidden="true">
                <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" />
                <circle cx="12" cy="10" r="2.5" />
              </svg>
              <div class="wizard-venue-details">
                <strong>{{ selectedVenue.name }}</strong>
                <span class="text-secondary">
                  {{ [selectedVenue.address, selectedVenue.city].filter(Boolean).join(', ') || 'Sin dirección cargada' }}
                </span>
                <span class="text-secondary">
                  Capacidad del lugar: {{ selectedVenue.capacity ?? 'Sin especificar' }}
                </span>
              </div>
              <button class="btn btn-outline-primary btn-sm" type="button" :disabled="Boolean(form.season_id)"
                @click="choosingVenue = true">Cambiar ubicación</button>
            </div>
            <p v-if="form.season_id" class="form-hint mb-3">Para usar otra ubicación, creá una nueva temporada de este
              show.</p>
          </div>
        </section>
      </template>
      <template v-if="step === 2">
        <section class="card mb-4">
          <div class="card-header">
            <h2 class="card-title">Generar fechas recurrentes (opcional)</h2>
          </div>
          <div class="card-body">
            <WizardField id="recurrence-frequency" v-model="recurring.frequency" label="El evento se repite cada"
              :options="[{ value: 'weekly', label: 'Semana' }, { value: 'daily', label: 'Día' }]" />
            <div v-if="recurring.frequency === 'weekly'" class="mb-3">
              <div class="form-label">El evento se realiza los días (podés elegir varios)</div>
              <div class="d-flex gap-2 flex-wrap"><label v-for="(day, i) in weekdays" :key="day" class="btn"
                  :class="recurring.weekdays.includes(i) ? 'btn-primary' : 'btn-outline-secondary'"><input
                    v-model="recurring.weekdays" class="visually-hidden" type="checkbox" :value="i">{{ day }}</label>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <DateTimeField id="recurrence-start-time" v-model="recurring.start_time" label="Hora de inicio" mode="time" />
              </div>
              <div class="col-md-6">
                <DateTimeField id="recurrence-end-time" v-model="recurring.end_time" label="Hora de fin" mode="time"
                  :hint="timeHint(recurring.start_time, recurring.end_time)" />
              </div>
              <div class="col-md-6">
                <DateTimeField id="recurrence-first" v-model="recurring.starts_on" label="Primer día" mode="date" />
              </div>
              <div class="col-md-6">
                <DateTimeField id="recurrence-last" v-model="recurring.ends_on" label="Último día" mode="date"
                  :earliest-on-or-after="recurring.starts_on" />
              </div>
            </div><button class="btn btn-success" @click="generateRecurringDates">Generar fechas</button>
          </div>
        </section>
        <section class="card mb-4">
          <div class="card-header">
            <h2 class="card-title">Agregar una función individual</h2>
          </div>
          <div class="card-body">
            <p class="text-secondary">Cargá una fecha o agregá varias funciones individuales.</p>
            <div class="row">
              <div class="col-md-6">
                <DateTimeField id="individual-start" v-model="individual.starts_at" label="Fecha y hora de inicio" />
              </div>
              <div class="col-md-6">
                <DateTimeField id="individual-end" v-model="individual.ends_at" label="Fecha y hora de fin"
                  :earliest-after="individual.starts_at" />
              </div>
            </div>
            <button class="btn btn-success" @click="addIndividual">Agregar función</button>
          </div>
        </section>
        <section id="wizard-functions" class="card mb-4">
          <div class="card-header">
            <h2 class="card-title">Funciones · {{ displayDates.length }}</h2>
          </div>
          <div class="card-body">
            <div v-for="d in displayDates" :key="d.starts_at"
              class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2"><span>{{
                formatDate(d.starts_at) }} → {{ formatDate(d.ends_at) }}</span><span v-if="d.is_protected"
                class="badge bg-secondary-lt">Conservada</span><button v-else class="btn btn-sm btn-outline-danger"
                @click="removeDate(d)">Quitar</button></div>
            <p v-if="!displayDates.length" class="text-secondary">Todavía no agregaste funciones.</p>
          </div>
        </section>
      </template>
      <template v-if="step === 3">
        <div class="mb-4">
          <h2 class="mb-2">Configurá las entradas de tus funciones</h2>
          <p class="text-secondary mb-0">Definí las entradas que se van a vender, su nombre, su precio, promoción y cuántas habrá disponibles a la venta.</p>
        </div>
        <section class="card mb-4">
          <div class="card-header"><h3 class="card-title">Entradas disponibles por función</h3></div>
          <div class="card-body">
            <!-- <p v-if="capacityMatchesVenue" class="text-secondary">Completamos este número con la capacidad de
              {{ selectedVenue?.name || form.venue.name || 'este lugar' }} que tiene declarados {{ venueCapacity }} lugares. Desde aca podes modificar la capacidad de cada función.</p>
            <p v-else-if="venueCapacity !== null" class="text-secondary">La capacidad de
              {{ selectedVenue?.name || form.venue.name || 'este lugar' }} es {{ venueCapacity }}. Podés definir otro límite de venta.</p> -->
            <WizardField id="event-capacity" v-model="form.capacity" label="Cantidad total de entradas disponibles por función" type="number" min="0" />
          </div>
        </section>
        <p class="text-secondary mb-4"><strong>Cómo se cuentan las entradas:</strong> la «Cantidad total de entradas disponibles por función»
          es el límite de ventas para cada fecha y horario, sumando todos los tipos de entrada. La «Cantidad de entradas disponibles»
          de cada tipo es un límite adicional: si se alcanza, solo ese tipo aparece agotado; si dejás el campo vacío, ese tipo no tiene límite propio.
          <strong>El total de la función siempre manda:</strong> cuando se alcanza, la función queda agotada y ya no se puede comprar ningún tipo de entrada.
          Por ejemplo, si configurás 50 para la función y 30 para cada uno de dos tipos, se venden 50 en total, no 60.</p>
        <div v-if="form.ticket_types.length" id="ticket-summaries" class="mb-4">
          <h4 class="mb-3">Entradas creadas · {{ form.ticket_types.length }}</h4>
          <article v-for="(t, i) in form.ticket_types" :key="t.id ?? i" class="wizard-ticket-summary mb-2"
            :class="{ 'is-editing': editingTicketIndex === i }">
            <div class="wizard-ticket-summary-details">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <strong>{{ t.name }}</strong><span v-if="editingTicketIndex === i" class="badge bg-blue-lt">Editando</span>
                <span v-if="!t.is_active" class="badge bg-secondary-lt">Venta deshabilitada</span>
              </div>
              <small class="text-secondary">{{ t.stock === null || t.stock === '' ? 'Sin límite propio' : `Hasta ${t.stock} por función` }}
                · Orden {{ t.sort_order }}</small>
              <small v-if="t.promotion_type" class="text-secondary">Promoción: {{ t.promotion_name || promotionSummary(t) }}
                <template v-if="t.promotion_name"> · {{ promotionSummary(t) }}</template>
                · {{ t.promotion_is_active ? 'Activa' : 'Inactiva' }}<template v-if="t.promotion_access_code"> · Código: {{ t.promotion_access_code }}</template></small>
            </div>
            <strong class="wizard-ticket-summary-price">{{ money(t.price) }}</strong>
            <div class="wizard-ticket-summary-actions">
              <button type="button" class="btn btn-sm btn-outline-primary" :disabled="editingTicketIndex === i" @click="editTicket(i)">Editar</button>
              <button type="button" class="btn btn-sm btn-outline-danger" :disabled="form.ticket_types.length === 1"
                :title="form.ticket_types.length === 1 ? 'Debe quedar al menos una entrada' : 'Quitar entrada'" @click="removeTicket(i)">Quitar</button>
            </div>
          </article>
        </div>
        <section id="ticket-editor" class="card mb-4">
          <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <h4 class="card-title">{{ editingTicketIndex === null ? (form.ticket_types.length ? 'Crear otra entrada' : 'Crear la primera entrada') : `Editar ${form.ticket_types[editingTicketIndex]?.name || 'entrada'}` }}</h4>
          </div>
          <div class="card-body">
            <div v-if="ticketEditorError" class="alert alert-warning" role="alert">{{ ticketEditorError }}</div>
            <div class="row">
              <div class="col-md-6"> 
                <WizardField id="ticket-name" v-model="ticketDraft.name" label="Nombre de la entrada" required hint="Es el nombre que verá quien compre la entrada." />
              </div>
              <div class="col-md-6"> 
                <WizardField id="ticket-price" v-model="ticketDraft.price" label="Precio de esta entrada ($)" type="number" min="0" required />
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <WizardField id="ticket-stock" v-model="ticketDraft.stock" label="Cantidad de entradas disponibles" type="number" min="0" />
              </div>
              <div class="col-md-6">
                <WizardField id="ticket-order" v-model="ticketDraft.sort_order" label="Orden en la venta" type="number" min="1" />
              </div>
            </div>
            <WizardField id="ticket-active" v-model="ticketDraft.is_active" label="Entrada habilitada para la venta" type="checkbox" />
            <hr class="my-3">
            <div class="wizard-promotion-panel">
              <div class="d-flex align-items-center justify-content-between gap-3">
                <div>
                  <label class="form-label mb-1" for="ticket-promotion">Esta entrada tiene promoción</label>
                  <p class="text-secondary small mb-0">Agregá un descuento o beneficio para esta entrada.</p>
                </div>
                <div class="form-check form-switch p-0 m-0">
                  <input id="ticket-promotion" :checked="Boolean(ticketDraft.promotion_type)" type="checkbox"
                    class="form-check-input m-0" @change="togglePromotion(ticketDraft, $event)">
                </div>
              </div>
              <div v-if="ticketDraft.promotion_type" class="wizard-promotion-fields mt-3 pt-3">
              <WizardField id="promo-name" v-model="ticketDraft.promotion_name" label="Nombre de la promoción" />
              <WizardField id="promo-type" v-model="ticketDraft.promotion_type" label="Tipo de promoción"
                :options="[{ value: 'percent_discount', label: 'Descuento porcentual' }, { value: 'fixed_discount', label: 'Descuento fijo' }, { value: 'buy_x_get_y', label: 'NxM (2x1, 3x1…)' }]" />
              <WizardField v-if="ticketDraft.promotion_type !== 'buy_x_get_y'" id="promo-value"
                v-model="ticketDraft.promotion_value"
                :label="ticketDraft.promotion_type === 'percent_discount' ? 'Porcentaje' : 'Monto fijo'" type="number" min="0"
                required />
              <div v-else class="row">
                <div class="col-md-6">
                  <WizardField id="promo-received" v-model="ticketDraft.promotion_bundle_quantity"
                    label="Entradas que recibe" type="number" min="2" required />
                </div>
                <div class="col-md-6">
                  <WizardField id="promo-paid" v-model="ticketDraft.promotion_pay_quantity" label="Entradas que paga"
                    type="number" min="1" required />
                </div>
              </div>
              <WizardField id="promo-code" v-model="ticketDraft.promotion_access_code" label="Código de acceso (opcional)"
                hint="Si lo agregás, el comprador deberá ingresarlo para ver esta entrada y su promoción. Debe ser único dentro de este evento." />
              <WizardField id="promo-active" v-model="ticketDraft.promotion_is_active" label="Promoción activa"
                type="checkbox" />
              </div>
            </div>
          </div>
          <div class="card-footer d-flex gap-2 justify-content-end flex-wrap">
            <button v-if="editingTicketIndex !== null || ticketDraftHasChanges()" type="button" class="btn" @click="resetTicketDraft">
              {{ editingTicketIndex === null ? 'Descartar' : 'Cancelar edición' }}
            </button>
            <button type="button" class="btn btn-success" @click="saveTicketDraft">{{ editingTicketIndex === null ? 'Crear entrada' : 'Guardar cambios de la entrada' }}</button>
          </div>
        </section>
      </template>
      <section v-if="step === 4" class="card mb-4">
        <div class="card-header">
          <h2 class="card-title">Cargo por servicio</h2>
        </div>
        <div class="card-body">
          <p class="text-secondary">El cargo por servicio se suman al valor de la entrada y es pagado por el comprador.</p>
          <WizardField id="fee-choice" v-model="feeChoice" label="¿Cómo querés cobrar el cargo por servicio?"
            :options="[{ value: '', label: 'Elegí una opción' }, { value: 'none', label: 'No cobrar cargo por servicio' }, { value: 'fixed_amount', label: 'Monto fijo' }, { value: 'percentage', label: 'Porcentaje' }]"
            required @update:model-value="chooseFee" />
          <WizardField v-if="feeChoice === 'fixed_amount'" id="fee-fixed"
            v-model="form.show.service_fee_fixed_amount" label="Monto fijo" type="number" min="0" required />
          <WizardField v-if="feeChoice === 'percentage'" id="fee-percentage"
            v-model="form.show.service_fee_percentage" label="Porcentaje" type="number" min="0" max="100" required />
          <WizardField v-if="feeChoice && feeChoice !== 'none'" id="fee-minimum"
            v-model="form.show.service_fee_minimum_unit_amount" label="Cargo por servicio mínimo por entrada pagada" type="number" min="0" />
        </div>
      </section>
      <template v-if="step === 5">
        <section class="card mb-4">
          <div class="card-header"><h2 class="card-title">Información adicional</h2></div>
          <div class="card-body">
            <WizardField id="event-additional" v-model="form.show.additional_information" label="Información adicional"
              type="textarea" />
          </div>
        </section>
        <section v-for="creditSection in creditSections" :id="'credits-' + creditSection.key" :key="creditSection.key" class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title">{{ creditSection.title }}</h2>
            <button type="button" class="btn btn-sm btn-primary" @click="personModal.openForCreate()">Crear persona</button>
          </div>
          <div class="card-body">
            <div v-if="creditsIn(creditSection.key).length" :id="'credit-summaries-' + creditSection.key" class="mb-4">
              <article v-for="credit in creditsIn(creditSection.key)" :key="credit.id ?? credit"
                class="wizard-ticket-summary wizard-form-summary mb-2" :class="{ 'is-editing': editingCredits[creditSection.key] === credit }">
                <span v-if="personPhoto(credit.person_id)" class="avatar avatar-sm rounded-circle flex-shrink-0"
                  :style="{ backgroundImage: `url(${personPhoto(credit.person_id)})` }" aria-hidden="true"></span>
                <span v-else class="avatar avatar-sm rounded-circle flex-shrink-0" aria-hidden="true">{{ personInitial(credit.person_id) }}</span>
                <div class="wizard-ticket-summary-details">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <strong>{{ personName(credit.person_id) }}</strong>
                    <span v-if="editingCredits[creditSection.key] === credit" class="badge bg-blue-lt">Editando</span>
                  </div>
                  <small class="text-secondary">{{ credit.role_label }}<template v-if="creditSection.key === 'cast' && credit.character_name"> · {{ credit.character_name }}</template> · Orden {{ credit.sort_order }}</small>
                </div>
                <div class="wizard-ticket-summary-actions">
                  <button type="button" class="btn btn-sm btn-outline-primary" :disabled="editingCredits[creditSection.key] === credit" @click="editCredit(creditSection.key, credit)">Editar</button>
                  <button type="button" class="btn btn-sm btn-outline-danger" @click="removeCredit(creditSection.key, credit)">Quitar</button>
                </div>
              </article>
            </div>
            <div :id="'credit-editor-' + creditSection.key" class="wizard-credit-editor">
              <h3 class="mb-3">{{ editingCredits[creditSection.key] ? 'Editar ' : 'Cargar ' }}{{ creditSection.key === 'cast' ? 'elenco' : 'equipo técnico' }}</h3>
              <div v-if="creditEditorErrors[creditSection.key]" class="alert alert-warning" role="alert">{{ creditEditorErrors[creditSection.key] }}</div>
              <WizardField :id="'credit-person-' + creditSection.key" v-model="creditDrafts[creditSection.key].person_id"
                label="Persona" :options="personOptions" required />
              <div class="row">
                <div v-if="creditSection.key === 'cast'" class="col-md-6">
                  <WizardField id="credit-character-cast" v-model="creditDrafts.cast.character_name" label="Nombre del personaje" />
                </div>
                <div class="col-md-6">
                  <WizardField :id="'credit-role-' + creditSection.key" v-model="creditDrafts[creditSection.key].role_label"
                    label="Rol" :options="creditSection.key === 'cast' ? [{ value: '', label: 'Elegir rol' }, { value: 'Actriz', label: 'Actriz' }, { value: 'Actor', label: 'Actor' }] : undefined" required />
                </div>
                <div class="col-md-6">
                  <WizardField :id="'credit-order-' + creditSection.key" v-model="creditDrafts[creditSection.key].sort_order"
                    label="Orden" type="number" min="1" required />
                </div>
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <button v-if="editingCredits[creditSection.key] || creditDraftHasChanges(creditSection.key)" type="button" class="btn" @click="resetCreditDraft(creditSection.key)">Descartar cambios</button>
                <button type="button" class="btn btn-success" @click="saveCreditDraft(creditSection.key)">{{ editingCredits[creditSection.key] ? 'Guardar cambios' : 'Cargar crédito' }}</button>
              </div>
            </div>
          </div>
        </section>
        <section id="links" class="card mb-4">
          <div class="card-header">
            <h2 class="card-title">Links de interés</h2>
          </div>
          <div class="card-body">
            <div v-if="form.links.length" id="link-summaries" class="mb-4">
              <h4 class="mb-3">Links cargados · {{ form.links.length }}</h4>
              <article v-for="(link, i) in sortedItems('link')" :key="link.id ?? i"
                class="wizard-ticket-summary wizard-form-summary mb-2" :class="{ 'is-editing': editingItems.link === link }">
                <div class="wizard-ticket-summary-details">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <strong>{{ link.text }}</strong>
                    <span v-if="editingItems.link === link" class="badge bg-blue-lt">Editando</span>
                  </div>
                  <small class="text-secondary">{{ link.url }} · Orden {{ link.sort_order }}</small>
                </div>
                <div class="wizard-ticket-summary-actions">
                  <button type="button" class="btn btn-sm btn-outline-primary" :disabled="editingItems.link === link" @click="editItem('link', link)">Editar</button>
                  <button type="button" class="btn btn-sm btn-outline-danger" @click="removeItem('link', link)">Quitar</button>
                </div>
              </article>
            </div>
            <div id="link-editor" class="wizard-item-editor">
              <h3 class="mb-3">{{ editingItems.link ? 'Editar enlace' : 'Cargar enlace' }}</h3>
              <div v-if="itemEditorErrors.link" class="alert alert-warning" role="alert">{{ itemEditorErrors.link }}</div>
              <WizardField id="link-title" v-model="itemDrafts.link.text" label="Título" required />
              <WizardField id="link-url" v-model="itemDrafts.link.url" label="Enlace" type="url" required />
              <WizardField id="link-order" v-model="itemDrafts.link.sort_order" label="Orden" type="number" min="1" required />
              <div class="d-flex gap-2 flex-wrap">
                <button v-if="editingItems.link || itemDraftHasChanges('link')" type="button" class="btn" @click="resetItemDraft('link')">Descartar cambios</button>
                <button type="button" class="btn btn-success" @click="saveItemDraft('link')">{{ editingItems.link ? 'Guardar cambios' : 'Cargar enlace' }}</button>
              </div>
            </div>
          </div>
        </section>
        <section id="faqs" class="card mb-4">
          <div class="card-header">
            <h2 class="card-title">Preguntas frecuentes</h2>
          </div>
          <div class="card-body">
            <div v-if="form.show.faqs.length" id="faq-summaries" class="mb-4">
              <h4 class="mb-3">Preguntas cargadas · {{ form.show.faqs.length }}</h4>
              <article v-for="(faq, i) in sortedItems('faq')" :key="faq.id ?? i"
                class="wizard-ticket-summary wizard-form-summary mb-2" :class="{ 'is-editing': editingItems.faq === faq }">
                <div class="wizard-ticket-summary-details">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <strong>{{ faq.question }}</strong>
                    <span v-if="editingItems.faq === faq" class="badge bg-blue-lt">Editando</span>
                  </div>
                  <small class="text-secondary">{{ faq.answer }} · Orden {{ faq.sort_order }}</small>
                </div>
                <div class="wizard-ticket-summary-actions">
                  <button type="button" class="btn btn-sm btn-outline-primary" :disabled="editingItems.faq === faq" @click="editItem('faq', faq)">Editar</button>
                  <button type="button" class="btn btn-sm btn-outline-danger" @click="removeItem('faq', faq)">Quitar</button>
                </div>
              </article>
            </div>
            <div id="faq-editor" class="wizard-item-editor">
              <h3 class="mb-3">{{ editingItems.faq ? 'Editar pregunta' : 'Cargar pregunta' }}</h3>
              <div v-if="itemEditorErrors.faq" class="alert alert-warning" role="alert">{{ itemEditorErrors.faq }}</div>
              <WizardField id="faq-question" v-model="itemDrafts.faq.question" label="Pregunta" required />
              <WizardField id="faq-answer" v-model="itemDrafts.faq.answer" label="Respuesta" type="textarea" required />
              <WizardField id="faq-order" v-model="itemDrafts.faq.sort_order" label="Orden" type="number" min="1" required />
              <div class="d-flex gap-2 flex-wrap">
                <button v-if="editingItems.faq || itemDraftHasChanges('faq')" type="button" class="btn" @click="resetItemDraft('faq')">Descartar cambios</button>
                <button type="button" class="btn btn-success" @click="saveItemDraft('faq')">{{ editingItems.faq ? 'Guardar cambios' : 'Cargar pregunta' }}</button>
              </div>
            </div>
          </div>
        </section>
        <section id="social" class="card mb-4">
          <div class="card-header">
            <h2 class="card-title">Redes</h2>
          </div>
          <div class="card-body">
            <WizardField v-for="[key, label] in socialFields" :id="'event-' + key" :key="key" v-model="form.show[key]"
              :label="label" type="url" />
          </div>
        </section>
        <section id="extra" class="card mb-4">
          <div class="card-header">
            <h2 class="card-title">Configuración extra</h2>
          </div>
          <div class="card-body">
            <WizardField id="event-comments" v-model="form.show.comments_enabled"
              label="Mostrar comentarios y permitir comentar" type="checkbox" />
            <hr>
            <WizardField id="event-production" v-model="form.show.production_note" label="Nota de la producción"
              type="textarea" />
          </div>
        </section>
      </template>
      <template v-if="step === 6">
        <section class="card mb-4">
          <div class="card-header">
            <h2 class="card-title">Resumen del evento</h2>
          </div>
          <div class="card-body"><img v-if="displayedImageUrl" :src="displayedImageUrl" alt="Imagen del evento"
              class="wizard-image mb-3">
            <h2>{{ form.show.title }}</h2>
            <p>{{ form.show.subtitle }}</p>
            <p class="text-secondary">{{ form.show.genre }} · {{ form.show.age_rating }} · Teatro</p>
            <p>{{ form.show.synopsis }}</p>
            <p v-if="form.show.additional_information">{{ form.show.additional_information }}</p>
            <p v-if="form.show.production_note">{{ form.show.production_note }}</p>
            <hr>
            <p><strong>Lugar:</strong> {{ form.venue.name }} · {{ form.venue.address }}</p>
            <p><strong>Capacidad por función:</strong> {{ form.capacity }}</p>
            <p><strong>Comentarios:</strong> {{ form.show.comments_enabled ? 'Habilitados' : 'Deshabilitados' }}</p>
            <p><strong>Cargos extras:</strong> {{ feeChoice === 'none' ? 'No se cobran' :
              (feeChoice === 'percentage' ? form.show.service_fee_percentage + '%' : money(form.show.service_fee_fixed_amount))
              }}<template v-if="feeChoice !== 'none'"> · Mínimo: {{ money(form.show.service_fee_minimum_unit_amount) }}</template></p>
            <h3 class="mt-4">Funciones ({{ displayDates.length }})</h3>
            <p v-for="d in displayDates" :key="d.starts_at" class="mb-1">{{ formatDate(d.starts_at) }} → {{
              formatDate(d.ends_at) }}</p>
            <h3 class="mt-4">Entradas</h3>
            <div v-for="(t, i) in form.ticket_types" :key="i" class="border-bottom py-2">{{ t.name }} · {{
              money(t.price)
              }} · Stock por función: {{ t.stock === null || t.stock === '' ? 'Sin límite propio' : t.stock }} · {{
                t.is_active ? 'Activa' : 'Inactiva' }}<div v-if="t.promotion_type" class="text-secondary">{{
                t.promotion_name || 'Promoción' }} · {{
                  t.promotion_type === 'buy_x_get_y' ? t.promotion_bundle_quantity + 'x' + t.promotion_pay_quantity :
                    t.promotion_type === 'percent_discount' ? t.promotion_value + '%' : money(t.promotion_value)
                }} · {{ t.promotion_access_code || 'Sin código' }} · {{ t.promotion_is_active ? 'Activa' : 'Inactiva' }}
              </div>
            </div>
            <h3 v-if="form.credits.length" class="mt-4">Créditos</h3>
            <p v-for="(c, i) in form.credits" :key="i">{{
              people.find(p => Number(p.id) === Number(c.person_id))?.display_name}} · {{ c.role_label }} · {{
                c.character_name }} · {{ c.section === 'cast' ? 'Elenco' : 'Equipo técnico' }}</p>
            <h3 v-if="form.links.length" class="mt-4">Links</h3>
            <p v-for="(l, i) in form.links" :key="i"><a :href="l.url" target="_blank" rel="noopener noreferrer">{{
              l.text
                }}</a></p>
            <h3 v-if="form.show.faqs.length" class="mt-4">Preguntas frecuentes</h3>
            <p v-for="(f, i) in form.show.faqs" :key="i"><strong>{{ f.question }}</strong><br>{{ f.answer }}</p>
            <h3 v-if="socialFields.some(([key]) => form.show[key])" class="mt-4">Redes</h3><template
              v-for="[key, label] in socialFields" :key="key">
              <p v-if="form.show[key]">{{ label }}: {{ form.show[key] }}</p>
            </template>
          </div>
        </section>
      </template>
      <footer class="wizard-actions">
        <div class="wizard-actions-inner"><a href="/admin/events" class="btn btn-sm btn-outline-danger me-auto">Salir</a><button v-if="step > 1"
            class="btn btn-outline-primary" :disabled="busy" @click="step--">Anterior</button><button v-if="step < 6"
            class="btn btn-primary" :disabled="busy" @click="next">{{ busy ? 'Validando…' : 'Continuar'
            }}</button>
            <template v-else>
              <button class="btn btn-success" :disabled="busy" @click="save(status === 'draft' ? 'draft' : 'save')">
                {{ status === 'draft' ? 'Guardar como borrador' : 'Guardar cambios' }}
              </button>
              <button v-if="status === 'draft'" class="btn btn-success" :disabled="busy" @click="save('publish')">
                Publicar
              </button>
            </template>
        </div>
      </footer>
    </div>
    <PersonModal ref="personModal" @saved="personSaved" />
    <VenueModal ref="venueModal" @saved="venueSaved" />
  </div>
</template>
