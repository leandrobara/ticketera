<script setup>
  import { computed } from 'vue';
  import CheckoutResultPage from '@/site/pages/CheckoutResultPage.vue';
  import CommentPage from '@/site/pages/CommentPage.vue';
  import HomePage from '@/site/pages/HomePage.vue';
  import ShowPage from '@/site/pages/ShowPage.vue';
  import AboutUsPage from '@/site/pages/static/AboutUsPage.vue';
  import ContactPage from '@/site/pages/static/ContactPage.vue';
  import CookiesPage from '@/site/pages/static/CookiesPage.vue';
  import FrequentlyAskedQuestionsPage from '@/site/pages/static/FrequentlyAskedQuestionsPage.vue';
  import ManageMyTicketsPage from '@/site/pages/static/ManageMyTicketsPage.vue';
  import PaymentMethodsPage from '@/site/pages/static/PaymentMethodsPage.vue';
  import PrivacyPage from '@/site/pages/static/PrivacyPage.vue';
  import PublishYourShowPage from '@/site/pages/static/PublishYourShowPage.vue';
  import TermsPage from '@/site/pages/static/TermsPage.vue';

  // data
  const page = window.ticketeraSite?.page ?? 'show';
  const showId = window.ticketeraSite?.showId;
  const seasonId = window.ticketeraSite?.seasonId;
  const checkoutStatus = window.ticketeraSite?.checkoutStatus;
  const commentToken = window.ticketeraSite?.commentToken;

  // computed
  const isAboutUsPage = computed(() => page === 'about-us');
  const isContactPage = computed(() => page === 'contact');
  const isCheckoutResult = computed(() => page === 'checkout-result');
  const isCommentPage = computed(() => page === 'comment');
  const isCookiesPage = computed(() => page === 'cookies');
  const isFrequentlyAskedQuestionsPage = computed(() => page === 'frequently-asked-questions');
  const isHomePage = computed(() => page === 'home');
  const isManageMyTicketsPage = computed(() => page === 'manage-my-tickets');
  const isPaymentMethodsPage = computed(() => page === 'payment-methods');
  const isPrivacyPage = computed(() => page === 'privacy');
  const isPublishYourShowPage = computed(() => page === 'publish-your-show');
  const isTermsPage = computed(() => page === 'terms');
</script>

<template>
  <CheckoutResultPage
    v-if="isCheckoutResult"
    :status="checkoutStatus"
  />
  <CommentPage v-else-if="isCommentPage" :token="commentToken" />
  <HomePage v-else-if="isHomePage" />
  <AboutUsPage v-else-if="isAboutUsPage" />
  <TermsPage v-else-if="isTermsPage" />
  <PrivacyPage v-else-if="isPrivacyPage" />
  <CookiesPage v-else-if="isCookiesPage" />
  <ContactPage v-else-if="isContactPage" />
  <PaymentMethodsPage v-else-if="isPaymentMethodsPage" />
  <FrequentlyAskedQuestionsPage v-else-if="isFrequentlyAskedQuestionsPage" />
  <ManageMyTicketsPage v-else-if="isManageMyTicketsPage" />
  <PublishYourShowPage v-else-if="isPublishYourShowPage" />
  <ShowPage v-else :season-id="seasonId" />
</template>
