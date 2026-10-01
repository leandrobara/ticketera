# Organizaciones y creación de eventos

El panel conserva los CRUD y agrega un wizard en `/admin/events/new`. Los cambios se guardan en las tablas operativas actuales, con entradas definidas una sola vez por temporada.

## Datos y permisos

`users` representa la cuenta global. `organizations.creator_user_id` identifica a su líder permanente. `organization_memberships` es única por usuario y organización; sus `roles` son una lista JSON que permite combinar roles, sin tablas de permisos. `show_ids = null` habilita todos los shows y una lista limita el acceso a los shows asignados.

| Rol | Acceso |
| --- | --- |
| Líder | Toda su organización, incluida su baja lógica. No puede transferirse ni desactivarse. |
| Administrador | Gestión completa salvo eliminar la organización o cambiar al líder. |
| Colaborador | Catálogo, contenidos y ventas; no administra equipo, invitaciones ni suscriptores. Puede limitarse por show. |
| Puerta | Lectura de shows, temporadas, funciones y tickets; validación de tickets. Puede limitarse por show. |

Shows, lugares, personas y compradores tienen `organization_id`. El resto obtiene su organización a través de sus padres. Los endpoints validan membresía, rol, ids recibidos y relaciones entre organizaciones. Una organización eliminada deja de estar disponible en el panel y en la venta pública.

`organization_invitations` guarda roles, shows opcionales, límite de usos, usos, vencimiento y revocación. El token se valida por hash y se conserva cifrado para recuperar el enlace en el panel. Su aceptación bloquea el registro y crea la membresía e incrementa usos en una transacción. Las invitaciones no pueden asignar el rol líder.

## Cinco pasos

1. Información del show, imagen principal, lugar, capacidad, créditos, links, FAQs, redes, contacto y configuración extra.
2. Fechas y funciones. Primero se pueden generar fechas diarias o semanales con varios días seleccionables; debajo se pueden agregar funciones individuales con fecha y horario. Las fechas generadas se muestran en un único listado y se pueden quitar una por una. Se requiere al menos una función para continuar.
3. Entradas, stock por función y una promoción opcional por tipo.
4. Cargos actuales del show: monto fijo o porcentaje y mínimo por entrada efectivamente pagada. No se cambia la política de cargos por método de pago.
5. Resumen, guardar borrador, publicar o guardar cambios de una temporada publicada.

El primer paso requiere título y lugar para continuar. El segundo requiere al menos una función, incluso para guardar un borrador desde el wizard. El paso de entradas requiere crear al menos una entrada antes de continuar o guardar; el último tipo no se puede quitar desde el wizard. Avanzar entre pasos solo valida y previsualiza fechas; no guarda el evento, tanto al crear como al editar. Los cambios se persisten únicamente en el último paso al guardar como borrador, publicar o guardar una temporada publicada. Un borrador guardado puede retomarse con `?season_id=ID`. Salir antes de guardar descarta los cambios pendientes. La imagen seleccionada se previsualiza localmente y se carga al guardar el evento. Crear una persona desde Créditos sigue siendo una operación independiente que se guarda en ese momento. Para repetir un show se usa `?show_id=ID`; conserva identidad, contenido y comentarios y crea otra temporada. Una temporada cerrada no se edita con el wizard. El lugar de una temporada existente no se traslada: se crea otra temporada del mismo show.

Show no tiene estado. `comments_enabled` oculta comentarios y bloquea lectura pública, solicitud de tokens y publicación, sin borrar opiniones existentes. `contact_email` y `whatsapp_phone` pertenecen al show. El formato se fuerza a `theater` en el backend y aparece como Teatro deshabilitado. Género es libre; público conserva ATP, +13, +16 y +18.

## Fechas

Cada función persiste `starts_at` y `ends_at`; la duración se deriva de su diferencia. El horario del formulario se interpreta en `APP_TIMEZONE`, por defecto `America/Argentina/Buenos_Aires`. La API serializa datetimes en UTC y el wizard entrega también fechas locales para edición.

`season_schedules` tiene una fila por temporada. El wizard convierte las recurrencias generadas en funciones individuales y guarda una configuración de esta forma:

```json
{
  "groups": [],
  "individual_dates": [{
    "date": "2026-11-01",
    "start_time": "21:00",
    "end_time": "23:00",
    "ends_on": null
  }],
  "excluded_starts_at": []
}
```

Domingo es 0 y sábado es 6 en el generador semanal. Si fin es anterior a inicio, termina al día siguiente. Horarios iguales se rechazan para no inferir una duración de 24 horas accidentalmente. Una fecha individual puede especificar `ends_on` para eventos de varios días. Los rangos incluyen ambos extremos; se deduplican fechas por inicio y se rechazan finales incompatibles para el mismo inicio. Al abrir configuraciones anteriores con grupos o exclusiones, el wizard las convierte a la lista de funciones resultantes.

La configuración sirve para editar, no para contabilizar ventas. La sincronización crea, actualiza, restaura o elimina lógicamente únicamente funciones futuras sin órdenes ni tickets. Preserva funciones pasadas y cualquiera con órdenes, incluso pendientes o eliminadas lógicamente; no mueve sus fechas ni cambia su capacidad. También conserva bajas manuales de funciones canceladas.

## Entradas y disponibilidad

Solo existe `season_ticket_types`, con `season_id`, nombre, precio, stock nullable, `is_active`, orden y promoción. No existe una tabla de disponibilidad por función. `Order` mantiene `presentation_id`; `OrderItem` y `Ticket` referencian `season_ticket_type_id`, y Ticket conserva además la función.

Para cada función:

- Cupo restante = `max(0, capacity - tickets VALID y USED de la función)`.
- Si stock es null, el límite del tipo es el cupo restante.
- Si stock está definido, disponibilidad = `min(cupo restante, max(0, stock - tickets VALID y USED del par función/tipo))`.
- Stock 0 significa agotado. Cancelados no consumen cupo. General agotada no bloquea otros tipos; `presentations.status = sold_out` corresponde al cupo completo.
- Precio, promoción e `is_active` del tipo se comparten entre todas las funciones de la temporada.

Una promoción por tipo permite porcentaje, monto fijo o NxM. El código se normaliza a minúsculas y es único por temporada entre tipos no eliminados, incluso inactivos. Puede repetirse en otras temporadas. Una columna generada y un índice compuesto garantizan esta regla también en MySQL. Los items y promociones conservan snapshots, y tickets y emails usan primero el nombre histórico de la compra.

Capacidad se precarga desde el lugar y puede ajustarse. No hay un segundo tope de venta por `venues.capacity` ni una nueva restricción que impida superarla.

## API

El login usa `Authorization: Bearer TOKEN`; todos los recursos de organización requieren además `X-Organization-Id: ID`. Consultar organizaciones, crear una y aceptar una invitación solo requieren autenticación. Registro de cuenta: `POST /api/admin/auth/register`.

- `GET/POST /api/admin/organizations`; `PUT/DELETE /api/admin/organizations/{organization}`.
- `GET /api/admin/team`; `PUT /api/admin/team/{member}`.
- `GET/POST /api/admin/invitations`; `DELETE /api/admin/invitations/{invitation}`; `POST /api/admin/invitations/{token}/accept`.
- `GET /api/admin/event-wizard/{season}` devuelve el estado editable.
- `POST /api/admin/event-wizard/preview-dates` recibe `configuration` y devuelve fechas sin persistir.
- `POST /api/admin/event-wizard` recibe `show`, lugar, capacidad, créditos, links, entradas, configuración y `action = save|draft|publish`. Los ids existentes permiten actualizar sin duplicados. Guardado transaccional al confirmar en el resumen; la imagen se carga por la API de imágenes después de ese guardado.
- CRUD de `/api/admin/season-ticket-types` usa `season_id`. El listado acepta `presentation_id` para encontrar los tipos de su temporada y mostrar su disponibilidad en esa función.
- Checkout, preview y venta manual requieren conjuntamente `presentation_id` y `season_ticket_type_id`. Ambos deben corresponder a la misma temporada.

## Regeneración y verificación

Se modificaron las migraciones originales, según lo acordado. No son migraciones incrementales para una base con ventas reales. Usar `php artisan migrate:fresh --seed` exclusivamente sobre una base descartable y verificada. Se unificaron los datos de ejemplo en `DatabaseSeeder`: un líder, una organización y cinco tipos definidos por temporada.

Pruebas automatizadas: `php artisan test` con SQLite en memoria; no modifican la base local. Frontend: `npm run build`. Pruebas incluyen permisos, aislamiento, invitaciones, recurrencia, stock/capacidad, comentarios, borradores, publicación, snapshots, seeder repetible y pago simulado con notificaciones idempotentes. Las migraciones también se probaron sobre el MySQL local.

## Pendiente expresamente acordado

Mercado Pago conserva el flujo actual: orden PENDING, sin reserva de stock, y emisión de tickets al aprobarse el pago. Dos órdenes pendientes pueden competir por el último cupo; la política de reserva temporal y el manejo de una aprobación tardía siguen pendientes. No se resuelve ese riesgo ni se realizan cobros reales en estas pruebas. El envío real depende de Brevo, su configuración y los workers existentes.
