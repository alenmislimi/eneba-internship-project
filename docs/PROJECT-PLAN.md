# Project plan

## Assignment requirements

The attached assignment asks for a marketplace with a browsable catalog, filters and sort, global typo/partial search, homepage modules whose contents come from backend sources, product pages with competing seller offers, cart and checkout, authentication, and an Eneba-inspired responsive interface. It specifies React (TypeScript preferred), PHP or Go, a database, and three submission items: GitHub project link, public deployment URL, and a full AI conversation log. The brief allows a deliberately chosen scope and says a smaller set of thorough features is better than many shallow ones.

## Scope delivered in this workspace

- React and TypeScript storefront with home, store, product, cart, sign-in/register, simulated checkout, and order history.
- PHP REST API with SQLite and seeded games, gift card, subscription, three seller offers per product, demo accounts, and demo activation keys.
- Home shelves are separate API data collections (`deals`, `bestSellers`, `giftCards`) derived by separate queries.
- Catalog supports partial and approximate title search, platform, region, max price, and sort.
- Responsive Eneba-inspired dark UI with green price/CTA accent, seller trust cues, and region warning.
- A fake card form provides a payment step. It accepts only the seeded test card number, validates expiry and security code, and sends no payment values to the API; the backend marks the resulting order as simulated.

## Architecture and data model

```text
React SPA (Vite, TypeScript)
          | JSON REST + session cookie
          v
PHP API (single front controller for this demo)
          | PDO
          v
SQLite locally / PostgreSQL on Render: products, offers, users, orders
```

`products` hold shared marketplace identity and activation metadata. Each `offers` row belongs to a product and carries seller, price, stock, rating, delivery, and a demo key. `users` store a hashed password. `orders` connect an authenticated buyer to a selected seller offer and timestamp. Local development defaults to SQLite; setting `DATABASE_URL` switches to PostgreSQL for Render. A real implementation would snapshot the purchased amount and key into an order item at purchase time, enforce stock, add migrations and input validation, and use a transaction/payment webhook workflow.

## Decisions and limits

- A lightweight PHP front controller keeps the assignment easy to run and explain without introducing a large framework setup. The tradeoff is that routing and schema setup are in one file; split these into services, migrations, and controllers before production.
- SQLite avoids external setup and is appropriate for a local demonstration. A free hosted instance needs durable storage or a managed database.
- Checkout deliberately simulates payment and returns seeded codes. This demonstrates the buyer journey without collecting sensitive payment data.
- Search uses title partial matching and PHP edit distance for a small seed catalog. For production, use a dedicated search index and language-aware ranking.
- Homepage sections are responses from backend queries, rather than UI literals; sample catalog data is seeded into the database.
- The linked internship page could not be retrieved via the available web fetch. The attached PDF is treated as the source of project requirements; Eneba style is represented as an inspired demo rather than a claim of pixel-perfect parity.

## Suggested seven-day execution and handoff

1. Confirm the brief and choose a narrow vertical slice; sketch catalog, offer, account, and order entities.
2. Seed database and implement catalog and homepage endpoints.
3. Build the responsive storefront, catalog filters, and approximate search.
4. Add seller comparison and product details.
5. Add account sessions, cart, simulated payment, and order history.
6. Polish empty/error/loading states, document architecture, and review the demo flow.
7. Deploy frontend/API with persistent data, prepare GitHub repository and public URL, and submit the complete chat log.

The local project implements the first five milestones at demo depth. GitHub publication and hosted deployment need account/hosting access and should be completed as handoff steps once the app is reviewed.
