<script setup>
import { computed, onMounted, provide, ref } from 'vue';
import ApiService from '@/admin/services/ApiService';
import LoginService from '@/admin/services/LoginService';
import AppLayout from './layouts/AppLayout.vue';
import LoginPage from './pages/LoginPage.vue';
import ShowsIndex from './pages/ShowsIndex.vue';
import SeasonsIndex from './pages/SeasonsIndex.vue';
import PresentationsIndex from './pages/PresentationsIndex.vue';
import SeasonTicketTypesIndex from './pages/SeasonTicketTypesIndex.vue';
import OrdersIndex from './pages/OrdersIndex.vue';
import PeopleIndex from './pages/PeopleIndex.vue';
import VenuesIndex from './pages/VenuesIndex.vue';
import BuyersIndex from './pages/BuyersIndex.vue';
import CommentsIndex from './pages/CommentsIndex.vue';
import OrganizationsPage from './pages/OrganizationsPage.vue';
import AccountPage from './pages/AccountPage.vue';
import OrganizationFormPage from './pages/OrganizationFormPage.vue';
import TeamPage from './pages/TeamPage.vue';
import MemberFormPage from './pages/MemberFormPage.vue';
import InvitationFormPage from './pages/InvitationFormPage.vue';
import EventWizard from './pages/EventWizard.vue';
import EventList from './pages/EventList.vue';
import EventDetailPage from './pages/EventDetailPage.vue';
import DoorPage from './pages/DoorPage.vue';
import NewsletterSubscribersIndex from './pages/NewsletterSubscribersIndex.vue';

const api = ApiService.getInstance();
const user = ref(null);
const token = ref(localStorage.getItem('token_tickets'));
const isLoading = ref(true);
const isLoggingOut = ref(false);
const showOrganizationCreated = ref(sessionStorage.getItem('organization_created') === '1');
sessionStorage.removeItem('organization_created');
const currentPath = ref(window.location.pathname);
const organizationId = ref(null);
const membership = computed(() => user.value?.memberships?.find(m => Number(m.organization_id) === Number(organizationId.value)) ?? null);
const canManage = computed(() => Boolean(membership.value && (membership.value.organization.creator_user_id === user.value.id || membership.value.roles.includes('administrator'))));
const canEditEvents = computed(() => canManage.value || membership.value?.roles.includes('collaborator'));
provide('adminSession', {user, membership, canManage, canEditEvents});
const invite = currentPath.value.match(/^\/admin\/invitation\/([A-Za-z0-9]{64})$/)?.[1];
if (invite) sessionStorage.setItem('organization_invitation', invite);

const pages = {
  '/admin/events': EventList,
  '/admin/shows': ShowsIndex, '/admin/seasons': SeasonsIndex, '/admin/presentations': PresentationsIndex,
  '/admin/season-ticket-types': SeasonTicketTypesIndex, '/admin/orders': OrdersIndex,
  '/admin/people': PeopleIndex, '/admin/venues': VenuesIndex, '/admin/buyers': BuyersIndex,
  '/admin/comments': CommentsIndex, '/admin/organizations': OrganizationsPage,
  '/admin/account': AccountPage,
  '/admin/team': TeamPage, '/admin/users': TeamPage, '/admin/events/new': EventWizard,
  '/admin/invitations/new': InvitationFormPage,
  '/admin/door': DoorPage,
  '/admin/newsletter-subscribers': NewsletterSubscribersIndex,
};
const currentPage = computed(() => {
  if (currentPath.value === '/admin/organizations/new' || /^\/admin\/organizations\/\d+\/edit$/.test(currentPath.value)) return OrganizationFormPage;
  if (/^\/admin\/team\/\d+\/edit$/.test(currentPath.value)) return MemberFormPage;
  if (/^\/admin\/invitations\/\d+\/edit$/.test(currentPath.value)) return InvitationFormPage;
  if (/^\/admin\/events\/\d+$/.test(currentPath.value)) return EventDetailPage;
  if (currentPath.value === '/admin/account') return AccountPage;
  return !membership.value ? OrganizationsPage : pages[currentPath.value] ?? EventList;
});
const redirect = path => { window.history.replaceState({}, '', path); currentPath.value = path; };
function selectOrganization(id, reload = true) {
  organizationId.value = Number(id) || null;
  api.setOrganizationId(organizationId.value);
  if (user.value) localStorage.setItem('organization_'+user.value.id, organizationId.value ?? '');
  if (reload) window.location.assign(canEditEvents.value ? '/admin/events' : '/admin/door');
}
async function refreshUser() {
  const response = await LoginService.getInstance().me();
  user.value = response.data.data.user;
  const selected = Number(localStorage.getItem('organization_'+user.value.id));
  const memberships = user.value.memberships ?? [];
  selectOrganization(memberships.some(m => Number(m.organization_id) === selected) ? selected : memberships[0]?.organization_id, false);
}
async function acceptInvitation() {
  const pending = sessionStorage.getItem('organization_invitation');
  if (!pending) return;
  try {
    const response = await api.post('/api/admin/invitations/'+pending+'/accept');
    sessionStorage.removeItem('organization_invitation');
    await refreshUser();
    selectOrganization(response.data.data.organization_id, false);
    redirect('/admin/events');
  } catch (error) {
    sessionStorage.removeItem('organization_invitation');
    window.alert(error.response?.data?.error?.message ?? error.response?.data?.message ?? 'No se pudo aceptar la invitación.');
    redirect('/admin/organizations');
  }
}
function clearSession() {
  token.value = null; user.value = null;
  localStorage.removeItem('token_tickets'); api.clearAccessToken(); api.setOrganizationId(null);
  redirect('/admin/login');
}
async function loadSession() {
  if (!token.value) { clearSession(); isLoading.value = false; return; }
  api.setAccessToken(token.value);
  try {
    await refreshUser();
    await acceptInvitation();
    if (['/admin','/admin/login'].includes(currentPath.value)) redirect(membership.value ? (canEditEvents.value ? '/admin/events' : '/admin/door') : '/admin/organizations');
    else if (!membership.value && !['/admin/organizations', '/admin/organizations/new', '/admin/account'].includes(currentPath.value)) redirect('/admin/organizations');
  } catch { clearSession(); }
  finally { isLoading.value = false; }
}
async function handleLogin({token: newToken}) {
  token.value = newToken; localStorage.setItem('token_tickets', newToken); await loadSession();
}
async function logout() {
  isLoggingOut.value = true;
  try { await LoginService.getInstance().logout(); } finally { clearSession(); isLoggingOut.value = false; }
}
async function organizationsChanged(id) {
  await refreshUser();
  if (id) selectOrganization(id);
}
onMounted(loadSession);
</script>

<template>
  <div v-if="isLoading" class="page page-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando sesión</span></div></div>
  <LoginPage v-else-if="!user" @login="handleLogin" />
  <AppLayout v-else :user="user" :membership="membership" :can-manage="canManage" :can-edit-events="canEditEvents" :is-logging-out="isLoggingOut" @logout="logout" @select-organization="selectOrganization">
    <div v-if="showOrganizationCreated" class="alert alert-success" role="status">Organización creada. Ya podés crear tu primer evento.</div>
    <component :is="currentPage" :key="organizationId" @organizations-changed="organizationsChanged" />
  </AppLayout>
</template>
