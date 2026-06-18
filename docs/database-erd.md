# Ticketera Database ERD

Este diagrama representa las tablas de negocio y autenticacion administrativa
definidas por las migraciones actuales.

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email UK
        string password
        string role
        timestamp email_verified_at
        timestamp deleted_at
    }

    ADMIN_ACCESS_TOKENS {
        bigint id PK
        bigint user_id FK
        string name
        string token_hash UK
        timestamp last_used_at
        timestamp expires_at
    }

    SHOWS {
        bigint id PK
        string title
        string slug
        text description
        int duration_minutes
        string genre
        string format
        enum age_rating
        string main_image_path
        enum status
        timestamp published_at
        timestamp deleted_at
    }

    VENUES {
        bigint id PK
        string name
        int capacity
        text description
        string address
        string neighborhood
        string city
        string google_maps_url
        boolean has_bar
        boolean is_accessible
        boolean has_parking
        timestamp deleted_at
    }

    PRESENTATIONS {
        bigint id PK
        bigint show_id FK
        bigint venue_id FK
        enum status
        datetime starts_at
        int capacity
        text notes
        timestamp deleted_at
    }

    PRESENTATION_TICKET_TYPES {
        bigint id PK
        bigint show_id FK
        bigint presentation_id FK
        string name
        decimal price
        int stock
        boolean is_active
        int sort_order
        timestamp deleted_at
    }

    PROMOTIONS {
        bigint id PK
        bigint presentation_ticket_type_id FK
        string name
        enum type
        decimal value
        int bundle_quantity
        int pay_quantity
        string access_code UK
        timestamp starts_at
        timestamp ends_at
        boolean is_active
        timestamp deleted_at
    }

    BUYERS {
        bigint id PK
        string name
        string last_name
        string email UK
        string phone
        string dni
        timestamp deleted_at
    }

    ORDERS {
        bigint id PK
        bigint show_id FK
        bigint presentation_id FK
        bigint buyer_id FK
        bigint created_by_user_id FK
        string code UK
        enum source
        enum status
        enum payment_method
        int total_quantity
        decimal total_amount
        string currency
        timestamp approved_at
        timestamp expires_at
        text notes
        timestamp deleted_at
    }

    ORDER_ITEMS {
        bigint id PK
        bigint show_id FK
        bigint order_id FK
        bigint presentation_ticket_type_id FK
        string name
        int quantity
        decimal unit_price
        decimal subtotal_amount
        decimal discount_amount
        decimal total_amount
        timestamp deleted_at
    }

    ORDER_ITEM_PROMOTIONS {
        bigint id PK
        bigint order_item_id FK
        bigint promotion_id FK
        string promotion_name
        enum promotion_type
        decimal promotion_value
        string promotion_access_code
        int bundle_quantity
        int pay_quantity
        decimal discount_amount
        timestamp created_at
    }

    TICKETS {
        bigint id PK
        bigint show_id FK
        bigint order_id FK
        bigint order_item_id FK
        bigint presentation_id FK
        bigint presentation_ticket_type_id FK
        string code UK
        enum status
        timestamp checked_in_at
        timestamp canceled_at
        timestamp deleted_at
    }

    PAYMENTS {
        bigint id PK
        bigint show_id FK
        bigint order_id FK
        string provider
        string provider_payment_id
        string provider_preference_id
        string provider_status
        decimal amount
        string currency
        json raw_response
        timestamp paid_at
        timestamp deleted_at
    }

    USERS ||--o{ ADMIN_ACCESS_TOKENS : authenticates_with
    USERS o|--o{ ORDERS : creates

    SHOWS ||--o{ PRESENTATIONS : has
    VENUES o|--o{ PRESENTATIONS : hosts

    SHOWS ||--o{ PRESENTATION_TICKET_TYPES : defines
    PRESENTATIONS ||--o{ PRESENTATION_TICKET_TYPES : offers
    PRESENTATION_TICKET_TYPES ||--o| PROMOTIONS : has_active_pricing_rule

    BUYERS ||--o{ ORDERS : places
    SHOWS ||--o{ ORDERS : receives
    PRESENTATIONS ||--o{ ORDERS : receives

    ORDERS ||--|{ ORDER_ITEMS : contains
    SHOWS ||--o{ ORDER_ITEMS : references
    PRESENTATION_TICKET_TYPES o|--o{ ORDER_ITEMS : classifies
    ORDER_ITEMS ||--o| ORDER_ITEM_PROMOTIONS : promotion_snapshot
    PROMOTIONS o|--o{ ORDER_ITEM_PROMOTIONS : originated_from

    ORDERS ||--|{ TICKETS : generates
    ORDER_ITEMS ||--|{ TICKETS : expands_into
    SHOWS ||--o{ TICKETS : belongs_to
    PRESENTATIONS ||--o{ TICKETS : admits_to
    PRESENTATION_TICKET_TYPES o|--o{ TICKETS : classifies

    ORDERS ||--o{ PAYMENTS : has
    SHOWS ||--o{ PAYMENTS : references
```

## Cardinalidades principales

- Un show tiene muchas funciones.
- Un espacio puede alojar muchas funciones.
- Una funcion ofrece muchos tipos de entrada.
- Un tipo de entrada puede tener como maximo una promocion activa asociada.
- Un comprador puede tener muchas ordenes.
- Una orden pertenece a una funcion y contiene uno o mas items.
- Un item puede tener un unico snapshot historico de la promocion aplicada.
- Cada item genera una o mas entradas individuales.
- Una orden puede tener uno o mas registros de pago.
- Un usuario administrador puede crear ordenes manuales.

## Tablas internas de Laravel

No se incluyen en el grafico principal porque no forman parte directa del
dominio de venta:

- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
