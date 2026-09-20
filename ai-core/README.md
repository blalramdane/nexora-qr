# Nexora QR

Nexora QR is a multi-tenant digital menu and ordering platform for restaurants and cafes.

## Current MVP

- Laravel + Inertia + React + TypeScript
- Restaurant registration and session authentication
- Restaurant / branch tenancy foundation
- Menu categories and products
- Product availability controls
- Modifier groups and modifiers
- Product ↔ modifier group attachment
- Public digital menu
- Table-specific QR menu URLs
- Browser-generated QR codes with print action
- Cart and checkout
- Server-side pricing and modifier validation
- Idempotent order creation
- Order status state machine
- Order dashboard
- Public order tracking
- WhatsApp action on order confirmation when a restaurant phone is configured
- Basic operating dashboard metrics

## Local development

    composer update
    npm install
    php artisan migrate
    php artisan serve
    npm run dev

Open `http://127.0.0.1:8000`.

## Main routes

- `/register`
- `/login`
- `/dashboard`
- `/menu`
- `/menu/modifiers`
- `/tables`
- `/orders`
- `/m/{restaurantSlug}`
- `/order/{orderNumber}`

## Architecture

Laravel owns routing, validation, authentication, tenancy, pricing, and order state. Inertia connects Laravel to React without introducing a separate frontend API/router layer. Vite builds the React/TypeScript application.

## Before production

- Run the full test suite and frontend build.
- Add role/policy authorization for every management action.
- Add image upload/storage pipeline.
- Add delivery areas and delivery fees if delivery is enabled.
- Connect an official WhatsApp provider/API for server-side notifications.
- Add Redis/queue workers and production observability.
- Review tenant-wide global scopes before adding more tenant-owned models.
