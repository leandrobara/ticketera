<script setup>
  import StaticPageLayout from '@/site/pages/static/StaticPageLayout.vue';
</script>

<template>
  <StaticPageLayout title="Gestionar mis tickets">
    <p>
      Si compraste entradas y no las recibiste por email, o si no tenés a mano
      el número de orden, podemos ayudarte a recuperarlas. Para cuidar la seguridad de cada compra, necesitamos verificar algunos
      datos antes de reenviar los tickets.
    </p>

    <h2>Qué datos necesitamos</h2>
    <p>
      Escribinos por email incluyendo la mayor cantidad posible de esta
      información:
    </p>
    <ul>
      <li>Nombre de la obra.</li>
      <li>Fecha y hora de la función que compraste.</li>
      <li>Marca de la tarjeta usada para pagar.</li>
      <li>Últimos 4 números de la tarjeta usada para pagar.</li>
      <li>Hora aproximada en la que realizaste la compra.</li>
    </ul>

    <h2>Cómo sigue</h2>
    <p>
      Vamos a revisar los datos de la operación. Si la información coincide con
      nuestros registros, te enviaremos nuevamente los tickets al email asociado
      a la compra.
    </p>

    <h2>Email</h2>
    <p>
      <a href="mailto:hola.entradatix@gmail.com">hola.entradatix@gmail.com</a>
    </p>
  </StaticPageLayout>
</template>
