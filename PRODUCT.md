# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

The product serves the general public buying tickets for shows and the theater producers, venues, and cultural projects that publish those shows. Future product decisions should prioritize the public selling experience for ticket buyers while preserving the producer/admin workflows needed to operate sales reliably.

## Product Purpose

Ticketera is a Spanish-first web ticketing product for selling and managing tickets to theater and cultural events. It supports public checkout, post-payment ticket delivery, and back-office administration for shows, venues, presentations, ticket types, buyers, orders, payments, tickets, comments, and newsletter subscribers.

Success means buyers can discover enough event information, choose tickets, pay, and receive valid tickets with low friction, while producers and admins can keep availability, payments, and attendee records accurate.

## Positioning

Ticketera should be treated as a focused public selling experience for local theater and cultural events, backed by operational controls for producers and admins. It is not a generic ecommerce storefront; its domain is shows, functions, ticket inventory, buyer trust, payment confirmation, and ticket delivery.

## Operating Context

The product has two main web surfaces:

- A public site with static information pages, show pages, checkout, checkout result pages, newsletter subscription, comments, and buyer-facing ticket flows.
- An admin SPA for authenticated internal operation of shows, seasons, venues, people, presentations, ticket types, buyers, orders, order items, tickets, payments, comments, images, credits, performance history, and newsletter subscribers.

Public checkout uses Mercado Pago. Transactional ticket email uses Brevo. Jobs and queue workers are part of normal operation for email delivery. Admin access uses bearer tokens issued by the application.

## Capabilities and Constraints

Confirmed capabilities include public order creation, price preview, Mercado Pago webhook processing, idempotent ticket generation, manual admin orders, payment tracking, ticket cancellation and usage marking, show publication states, presentation capacity/stock checks, service fees, promotional pricing, comment invitations, and newsletter subscriptions.

The implementation is Laravel 12 with PHP 8.2+, MySQL/MariaDB, Vue 3, Vite, and Tabler for the admin. API responses use a consistent `{ success, data }` shape for successful responses. Money is stored as decimals with scale 6. Pricing and promotion decisions are snapshotted where historical accuracy matters.

Future UI work should preserve Spanish-language product terminology unless explicitly changed.

## Brand Commitments

The repository contains public copy using the name "Entrada Tix" and a brand logo component at `resources/js/site/components/brand/EntradatixLogo.vue`. No additional binding visual direction, palette, typography, or brand system has been confirmed during init.

## Evidence on Hand

Evidence comes from:

- `README.md` for stack, domain model, public/admin routes, checkout, Mercado Pago, Brevo, and development commands.
- `docs/architecture.md` for backend architecture, domain relationships, transactional boundaries, and frontend/admin structure.
- `docs/product-roadmap.md` for product priorities, including stabilizing current flows, back-office strength, public checkout completeness, stock/reservation policy, check-in, communications, reporting, security, and automation.
- `routes/web.php` and `routes/api.php` for public, admin, checkout, comments, newsletter, and notification surfaces.
- `resources/js/site/pages/static/PublishYourShowPage.vue` for producer-facing public copy and contact flow.

There are no confirmed testimonials, customer logos, public benchmarks, pricing plans, legal claims, or accessibility certification claims in the current product context.

## Product Principles

Prioritize buyer confidence in the public sales flow: availability, price, payment state, and ticket delivery should be clear and trustworthy.

Preserve operational accuracy behind the public experience: stock, capacity, payments, tickets, and email state must stay traceable and difficult to corrupt.

Keep domain language close to live event operations: shows, functions, venues, ticket types, buyers, orders, payments, and tickets are core concepts.

Avoid broad ecommerce assumptions unless they directly support cultural event ticketing.

Favor small, verifiable changes because checkout, payments, ticket generation, and email delivery are critical flows.
