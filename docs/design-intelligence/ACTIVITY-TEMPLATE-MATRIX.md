# NEXORA QR — Activity Template Matrix

## Objective

Make the same Menu Engine capable of producing very different experiences without duplicating business logic.

## Shared engine

All templates share Header, Brand, Category navigation, Product listing, Product details, Variants, Modifiers, Availability, Cart, Checkout/order flow, Order status, Restaurant context and Theme tokens.

## Template families

### Fast Food
Goal: maximum scan speed and fast ordering.

Visual: bold typography, strong contrast, compact category rail, large food imagery, clear price and prominent add button.

### Burger
Goal: make photography and customization dominant.

Visual: large product images, strong product hierarchy, signature add-ons.

### Pizza
Goal: make configuration understandable.

Visual: product image plus configuration, size-first selection, crust/topping grouping.

### Cafe
Goal: quick browsing across many small items.

Visual: warm palette, compact cards, horizontal categories, optional image/no-image variants.

### Bakery
Goal: editorial discovery.

Visual: photography-led sections, story-style product cards, seasonal featured items.

### Desserts
Goal: visual appeal plus impulse purchase.

Visual: playful typography, image-forward grid, promotional highlights.

### Juice / Smoothie
Goal: configuration without confusion.

Visual: fresh clean theme, ingredient highlights, size options.

### Fine Dining
Goal: premium storytelling.

Visual: restrained typography, generous spacing, editorial sections, subtle transitions.

### Food Truck
Goal: speed under real-world conditions.

Visual: compact, high contrast, strong CTAs, minimal taps.

### Resort / Beach Club
Goal: immersive browsing while keeping ordering simple.

Visual: photography-led, airy spacing, large section headers.

## Theme system

`Template = Structure`

`Theme = Visual identity`

Theme tokens:

- primary
- secondary
- accent
- background
- foreground
- muted
- border
- success
- warning
- danger
- radius
- shadow
- typography
- spacing density
- image radius
- card style

This separation is critical: the same Cafe template can look completely different for two brands.

## Signature component variants

The following should have multiple design variants:

- ProductCard
- CategoryNav
- MenuHeader
- FeaturedSection
- ModifierSheet
- CartBar
- CartDrawer
- EmptyState
- Search
- RestaurantHero
- PromoBanner
- OrderStatus
- QuantityControl

## ProductCard variants

Minimum variants:

1. compact
2. image-top
3. image-side
4. editorial
5. featured
6. quick-add
7. modifier-required
8. out-of-stock

All variants preserve the same product contract.

## Design quality gate

A new template is not accepted because it looks attractive. It must pass:

- 3-second recognition test
- mobile thumb test
- product price visibility
- availability visibility
- add-to-cart discoverability
- long Arabic text test
- narrow viewport test
- reduced-motion test
- keyboard/focus test
- loading/error/empty states
- performance check

## Future template expansion

Potential future families: Sushi, Seafood, Healthy Food, Cloud Kitchen, Shisha Lounge, Dessert Kiosk, Corporate Cafeteria and Hotel Room Service.

Add only when there is a commercial demand signal.
