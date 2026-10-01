# Ticketera

Backend y panel administrativo para venta y gestion de entradas. La aplicacion esta construida con Laravel 12, expone una API JSON para administracion y checkout, y usa Vue/Vite para el panel admin.

## Stack

- PHP 8.2+
- Laravel 12
- MySQL/MariaDB en desarrollo compartido
- Queue driver `database`
- Vue 3, Vite 7 y Tabler para el admin
- Mercado Pago para checkout publico
- Brevo para emails transaccionales de entradas

## Inicio rapido

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
composer run dev
```

`composer run dev` levanta en paralelo:

- `php artisan serve`
- `php artisan queue:listen --tries=1 --timeout=0`
- `php artisan pail --timeout=0`
- `npm run dev`

Usuario admin seed:

- Email: `admin@ticketera.test`
- Password: `password`

## Configuracion

Variables principales:

```dotenv
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=tickets
DB_USERNAME=root
DB_PASSWORD=root

QUEUE_CONNECTION=database
ADMIN_ACCESS_TOKEN_TTL_MINUTES=480

MERCADO_PAGO_ACCESS_TOKEN=
MERCADO_PAGO_PUBLIC_KEY=
MERCADO_PAGO_SUCCESS_URL="${APP_URL}/checkout/success"
MERCADO_PAGO_FAILURE_URL="${APP_URL}/checkout/failure"
MERCADO_PAGO_PENDING_URL="${APP_URL}/checkout/pending"
MERCADO_PAGO_NOTIFICATION_URL="${APP_URL}/api/notifications/mercado-pago"

BREVO_API_KEY=
BREVO_SENDER_NAME="${APP_NAME}"
BREVO_SENDER_EMAIL="${MAIL_FROM_ADDRESS}"
```

Para desarrollo sin proveedores externos se puede trabajar el admin y las ordenes manuales. El checkout publico requiere `MERCADO_PAGO_ACCESS_TOKEN`. El envio real de emails requiere `BREVO_API_KEY` y un remitente valido.

## Comandos utiles

```bash
composer run dev          # servidor, queue, logs y vite
composer run test         # limpia config y corre tests
npm run dev               # solo Vite
npm run build             # build frontend
php artisan migrate       # migraciones
php artisan db:seed       # datos de prueba
php artisan queue:listen  # worker de emails y jobs
```

## Superficie HTTP

Todas las respuestas exitosas de API usan el formato:

```json
{
  "success": true,
  "data": {}
}
```

Rutas publicas:

- `POST /api/checkout/create-order`: crea orden checkout, buyer, item, preference de Mercado Pago y payment pendiente.
- `POST /api/checkout/price-preview`: calcula precio para el admin al armar una orden a mano. El site calcula el preview en el front.
- `POST /api/notifications/mercado-pago`: procesa webhooks de Mercado Pago.

Rutas admin:

- `POST /api/admin/auth/login`
- `GET /api/admin/auth/me`
- `POST /api/admin/auth/logout`
- Organizaciones, equipo, invitaciones y wizard bajo `/api/admin`.
- CRUD de shows, venues, seasons, presentations, season ticket types, buyers, orders, order items, tickets y payments bajo `/api/admin`.

Las rutas admin protegidas requieren `Authorization: Bearer <token>`. Los recursos de una organización requieren además `X-Organization-Id: <id>` y una membresía activa. El token se emite en login, se guarda hasheado en `admin_access_tokens` y expira según `ADMIN_ACCESS_TOKEN_TTL_MINUTES`.

El nuevo flujo está en `/admin/events/new`. Ver [organizaciones y wizard](EVENT_WIZARD.md) para permisos, fechas, stock, contrato de API y verificación. Se conservan los CRUD anteriores; `/admin/users` abre ahora el equipo de la organización.

## Dominio principal

- `Organization`: productora con creador permanente y equipo de usuarios globales.
- `Show`: identidad reutilizable de la obra, sin estado propio. Conserva contenido, contacto, comentarios y cargo fijo o porcentual.
- `Venue`: sala o espacio.
- `Season`: temporada del show en un lugar; agrupa funciones y definiciones de entradas.
- `Presentation`: función de una temporada, con inicio, fin y capacidad.
- `SeasonTicketType`: entrada definida una vez por temporada. Precio y activación son comunes; su stock es un máximo independiente para cada función. Conserva una promoción opcional embebida.
- `SeasonSchedule`: configuración JSON editable que genera las funciones de una temporada.
- `Buyer`: comprador.
- `Order`: compra o reserva, de origen `CHECKOUT` o `ADMIN`.
- `OrderItem`: linea de orden asociada a un tipo de entrada.
- `OrderItemPromotion`: snapshot historico de la promocion aplicada al item.
- `Ticket`: entrada individual generada cuando una orden queda aprobada.
- `Payment`: registro del pago manual o del proveedor.

Estados importantes:

- Orden: `PENDING`, `APPROVED`, `REJECTED`, `IN_PROCESS`, `CANCELED`, `EXPIRED`, `REFUNDED`.
- Ticket: `VALID`, `USED`, `CANCELED` segun servicios actuales.
- Presentacion: `draft`, `published`, `sold_out`, `cancelled`.
- Temporada: `draft`, `published`, `finished`, `cancelled`. Show no tiene estado.

## Flujos principales

### Checkout publico

1. El cliente envía buyer, cantidad, `presentation_id`, `season_ticket_type_id` y código promocional opcional.
2. El backend valida tipo activo, consistencia show/presentacion y token de Mercado Pago.
3. En transaccion se bloquea el tipo de entrada y la presentacion, se valida capacidad y stock, se crea buyer, order, item y snapshot de promocion si aplica.
4. Fuera de la transaccion se crea la preference en Mercado Pago.
5. Se registra un `Payment` pendiente y se devuelve `init_point`/`sandbox_init_point`.

### Notificacion Mercado Pago

1. El webhook recibe `payment` o `merchant_order`.
2. El backend consulta Mercado Pago para obtener el estado real.
3. Se toma un lock por payment id para evitar reprocesos concurrentes.
4. Se ubica la orden por metadata o `external_reference`.
5. Se actualizan `payments` y `orders`.
6. Si el pago queda aprobado, se generan tickets faltantes de forma idempotente, se sincroniza `sold_out` y se encola el email de entradas.

### Orden manual admin

1. Un admin crea una orden desde `/api/admin/orders`.
2. La orden queda `APPROVED` inmediatamente.
3. Se crea el pago con provider igual al metodo manual (`CASH`, `BANK_TRANSFER`, `FREE`, etc.).
4. Se generan tickets, se sincroniza capacidad y se encola el email.

## Documentacion

- [Arquitectura](docs/architecture.md)
- [Guia de desarrollo](docs/development-guide.md)
- [Guia de API y flujos](docs/api-guide.md)
- [Roadmap incremental del producto](docs/product-roadmap.md)
- [Architecture Decision Records](docs/adr/README.md)
- [ERD de base de datos](docs/database-erd.md)
- [Coleccion Postman admin](docs/postman/ticketera-admin.postman_collection.json)

## Notas de mantenimiento

- Mantener `docs/database-erd.md` actualizado cuando cambien migraciones o relaciones.
- Agregar ejemplos de requests en `docs/api-guide.md` cuando se incorporen endpoints publicos.
- Los cambios de reglas de precio deben reflejarse en `OrderItemPricingService` y en la guia de API.
- Los jobs de email dependen de `queue:listen`/worker activo; sin worker, las entradas se generan pero no se envia el correo.
