<script setup>
  import {
    BadgeCheck,
    CalendarDays,
    ChartNoAxesCombined,
    CheckCircle2,
    CircleDollarSign,
    Clock3,
    Mail,
    MapPin,
    MousePointerClick,
    ReceiptText,
    ScanLine,
    ShieldCheck,
    Sparkles,
    UsersRound,
  } from '@lucide/vue';
  import SiteHeader from '@/site/components/layout/SiteHeader.vue';
  import SiteFooter from '@/site/components/show/SiteFooter.vue';

  /*
    THESIS: Entrada Tix sells tickets by making the public purchase and the producer's control room visible in one flow; it refuses the generic SaaS feature grid.
    OWN-WORLD: dark theater lobby surfaces, white operational panels, pink-purple action gradients, Inter/system UI inherited from the show page.
    STORY: producers see a show published, tickets sold, payments confirmed, and audience data organized without losing the buyer's trust.
    FIRST VIEWPORT: left-side sales promise and CTA, right-side live show checkout dossier with function, inventory, buyer and payment signals.
    FORM: candidate 6 from degraded persuade seed 7b296c8e; staged as a ticket booth/control-room walkthrough.
  */

  // data
  const proofStats = [
    {
      value: '4',
      label: 'superficies conectadas',
    },
    {
      value: '1',
      label: 'flujo de compra',
    },
    {
      value: '24/7',
      label: 'venta online',
    },
  ];
  const checkoutSteps = [
    {
      title: 'Publicás la obra',
      description: 'Cargá la ficha, funciones, sala, tipos de entrada, promos y links de la producción.',
      icon: Sparkles,
    },
    {
      title: 'Vendés con Mercado Pago',
      description: 'El público elige función, ve el precio final y continúa el pago desde una experiencia clara.',
      icon: CircleDollarSign,
    },
    {
      title: 'Entregás tickets',
      description: 'Cuando el pago se aprueba, se generan entradas y se envían por email transaccional.',
      icon: Mail,
    },
    {
      title: 'Operás la sala',
      description: 'El panel mantiene compradores, órdenes, pagos, tickets y disponibilidad bajo control.',
      icon: ScanLine,
    },
  ];
  const capabilities = [
    {
      title: 'Ficha pública pensada para vender',
      description: 'Sinopsis, sala, funciones, precios, promociones, comentarios y llamada directa a comprar.',
      icon: MousePointerClick,
    },
    {
      title: 'Funciones y cupos ordenados',
      description: 'Cada presentación conserva fecha, sala, capacidad, tipos de entrada y stock disponible.',
      icon: CalendarDays,
    },
    {
      title: 'Pagos y órdenes trazables',
      description: 'Checkout público, órdenes manuales, estados internos y conciliación con Mercado Pago.',
      icon: ReceiptText,
    },
    {
      title: 'Audiencia en una sola base',
      description: 'Compradores, asistentes, comentarios y newsletter quedan disponibles para seguir el vínculo.',
      icon: UsersRound,
    },
  ];
  const operatingSignals = [
    'Orden aprobada',
    'Pago Mercado Pago aprobado',
    'Comprador confirmado',
    'Stock sincronizado',
  ];
  const trustItems = [
    {
      title: 'Precio final antes de pagar',
      icon: BadgeCheck,
    },
    {
      title: 'Control de stock por función',
      icon: ShieldCheck,
    },
    {
      title: 'Datos listos para soporte',
      icon: ChartNoAxesCombined,
    },
  ];
</script>

<template>
  <div class="home-page">
    <section class="home-hero">
      <SiteHeader />

      <div class="home-hero-inner">
        <div class="home-hero-copy">
          <p class="home-eyebrow">Ticketera para teatro y proyectos culturales</p>
          <h1>Vendé entradas sin perder el control de cada función.</h1>
          <p>
            Entrada Tix conecta una ficha pública lista para comprar con un panel
            operativo para ordenar funciones, pagos, compradores y tickets desde
            el primer día.
          </p>
          <div class="home-hero-actions">
            <a class="primary-button home-primary-action" href="/publica-tu-obra">
              Publicar mi obra
            </a>
            <a class="home-secondary-action" href="#como-funciona">
              Ver cómo funciona
            </a>
          </div>
        </div>

        <div class="home-ticket-dossier" aria-label="Vista ilustrativa del flujo de venta">
          <div class="home-dossier-topline">
            <span>Ficha publicada</span>
            <strong>Venta activa</strong>
          </div>
          <div class="home-show-card">
            <div>
              <p class="home-show-kicker">La noche antes</p>
              <h2>Viernes 21:00</h2>
              <p><MapPin aria-hidden="true" /> Teatro independiente</p>
            </div>
            <div class="home-show-price">
              desde
              <strong>$12.000</strong>
            </div>
          </div>
          <div class="home-ticket-stack">
            <div class="home-ticket-row is-selected">
              <span>General</span>
              <strong>34 disponibles</strong>
            </div>
            <div class="home-ticket-row">
              <span>Promo 2x1</span>
              <strong>12 disponibles</strong>
            </div>
          </div>
          <div class="home-operation-panel">
            <div
              v-for="signal in operatingSignals"
              :key="signal"
              class="home-operation-signal"
            >
              <CheckCircle2 aria-hidden="true" />
              <span>{{ signal }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <main class="home-main">
      <section class="home-proof-strip" aria-label="Resumen de producto">
        <div
          v-for="stat in proofStats"
          :key="stat.label"
          class="home-proof-item"
        >
          <strong>{{ stat.value }}</strong>
          <span>{{ stat.label }}</span>
        </div>
      </section>

      <section id="como-funciona" class="home-section home-flow-section" aria-labelledby="home-flow-title">
        <div class="home-section-head">
          <p class="home-section-kicker">Cómo funciona</p>
          <h2 id="home-flow-title">De la publicación al ingreso a sala, todo sigue el mismo hilo.</h2>
          <p>
            La venta pública y la administración no corren por caminos separados:
            cada compra alimenta la operación real del evento.
          </p>
        </div>

        <div class="home-flow">
          <article
            v-for="(step, index) in checkoutSteps"
            :key="step.title"
            class="home-flow-step"
          >
            <span class="home-flow-index">{{ index + 1 }}</span>
            <component :is="step.icon" class="home-flow-icon" aria-hidden="true" />
            <h3>{{ step.title }}</h3>
            <p>{{ step.description }}</p>
          </article>
        </div>
      </section>

      <section class="home-section home-split-section" aria-labelledby="home-capabilities-title">
        <div class="home-section-head">
          <p class="home-section-kicker">Qué resuelve</p>
          <h2 id="home-capabilities-title">Una ticketera hecha para obras, salas y productoras.</h2>
        </div>
        <div class="home-capability-list">
          <article
            v-for="capability in capabilities"
            :key="capability.title"
            class="home-capability"
          >
            <component :is="capability.icon" class="home-capability-icon" aria-hidden="true" />
            <div>
              <h3>{{ capability.title }}</h3>
              <p>{{ capability.description }}</p>
            </div>
          </article>
        </div>
      </section>

      <section class="home-section home-trust-section" aria-labelledby="home-trust-title">
        <div>
          <p class="home-section-kicker">Confianza para vender</p>
          <h2 id="home-trust-title">El público entiende qué compra. El equipo entiende qué pasó.</h2>
          <p>
            La experiencia prioriza precio claro, disponibilidad visible y estados
            operativos trazables para reducir dudas antes y después del pago.
          </p>
        </div>
        <div class="home-trust-list">
          <article
            v-for="item in trustItems"
            :key="item.title"
            class="home-trust-item"
          >
            <component :is="item.icon" aria-hidden="true" />
            <span>{{ item.title }}</span>
          </article>
        </div>
      </section>

      <section class="home-final-cta" aria-labelledby="home-final-title">
        <div>
          <Clock3 class="home-final-icon" aria-hidden="true" />
          <h2 id="home-final-title">Estamos incorporando proyectos puntuales.</h2>
          <p>
            Si producís teatro, gestionás una sala o estás preparando una función,
            escribinos y vemos cómo publicar tu obra en Entrada Tix.
          </p>
        </div>
        <a class="primary-button home-final-button" href="/publica-tu-obra">
          Quiero vender entradas
        </a>
      </section>
    </main>

    <SiteFooter />
  </div>
</template>
