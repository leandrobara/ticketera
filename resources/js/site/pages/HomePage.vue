<script setup>
  import {
    BarChart3,
    CalendarDays,
    Mail,
    ScanLine,
    Ticket,
  } from '@lucide/vue';
  import SiteHeader from '@/site/components/layout/SiteHeader.vue';
  import SiteFooter from '@/site/components/show/SiteFooter.vue';

  // data
  const features = [
    {
      title: 'Venta de entradas online',
      icon: Ticket,
    },
    {
      title: 'Gestión de funciones',
      icon: CalendarDays,
    },
    {
      title: 'Tickets por email',
      icon: Mail,
    },
    {
      title: 'Check-in simple',
      icon: ScanLine,
    },
    {
      title: 'Datos de audiencia',
      icon: BarChart3,
    },
  ];
</script>

<template>
  <div class="home-page">
    <section class="home-hero">
      <SiteHeader />

      <div class="home-hero-inner">
        <h1>Estamos construyendo Entradatix</h1>
        <p>
          Una plataforma para obras, salas y productoras independientes que
          quieren vender entradas, gestionar funciones y cuidar la relación con
          su público.
        </p>
      </div>
    </section>

    <main class="home-main">
      <section class="home-features-card" aria-labelledby="home-features-title">
        <h2 id="home-features-title">Qué estamos preparando</h2>

        <div class="home-features-grid">
          <article
            v-for="feature in features"
            :key="feature.title"
            class="home-feature"
          >
            <component :is="feature.icon" class="home-feature-icon" />
            <h3>{{ feature.title }}</h3>
          </article>
        </div>
      </section>
    </main>

    <SiteFooter />
  </div>
</template>
