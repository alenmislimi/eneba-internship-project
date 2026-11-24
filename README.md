# Eneba Marketplace Assignment

A compact digital games marketplace demo built for the Eneba internship assignment. The React/TypeScript storefront talks to a PHP REST API backed by SQLite locally and PostgreSQL on Render. It uses seeded demo products, sellers, and activation keys; checkout is simulated and does not collect payment details.

GitHub repository: https://github.com/alenmislimi/eneba-internship-project

## Run locally

Requirements: Node.js 20+, PHP 8+ with PDO SQLite enabled.

```sh
npm install
npm run api
```

In another terminal:

```sh
npm run dev
```

Open http://localhost:5173. The API is at http://localhost:8000/api. The SQLite database is created and seeded on the first API request in `backend/data/marketplace.sqlite`.

Create a demo account with any valid email and a password of at least six characters. Add a game to the cart, continue to the fake card payment form, then submit the prefilled test card (`4242 4242 4242 4242`) to complete the simulated purchase and receive a demo activation key. The payment form can remember the demo method on this device; it stores only the cardholder, expiry, brand, and last four digits, never the full number or security code. Card values are not sent to the API. Use **Orders** to view purchase history, and **Sign out** in the header to end the session.

## Project map

- `frontend/src/App.tsx` - storefront, product, account, cart, and order flows
- `frontend/src/styles.css` - responsive Eneba-inspired dark storefront styles
- `backend/index.php` - REST routes, SQLite/PostgreSQL schema, demo seed data, and session auth
- `docs/PROJECT-PLAN.md` - requirements, architecture, tradeoffs, and delivery plan
- `AI-CHAT-LOG.md` - conversation log for this project

## API overview

`GET /api/homepage`, `GET /api/products`, `GET /api/products/{id}`, `GET /api/me`, `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`, `POST /api/checkout`, and `GET /api/orders`.

Homepage shelves are queried independently from catalog data. Search supports partial names and a simple edit-distance fallback. Auth passwords are hashed; session cookies guard checkout and order history.

## Deploy on Render

The repository includes a Render Blueprint (`render.yaml`) for a free static frontend, PHP Docker API, and free Postgres database. In Render, create a new Blueprint and select this GitHub repository. The API uses `DATABASE_URL` for Postgres; local development continues to use SQLite when that variable is unset.

Render free web services spin down after inactivity, so the first request can be slow. The free Postgres database expires after 30 days; upgrade or export the data before then if the app needs to stay online. The included digital keys are demo values and must not be used for real products.
