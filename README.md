# Project Subaybay

The repository has a React/Vite frontend and an Express API backed by MariaDB or TiDB Cloud.

```text
Reading/
|- frontend/   React/Vite application (deploy to Vercel)
|- backend/    Node.js/Express API (deploy to Render)
`- render.yaml Render Node service blueprint
```

## Run locally

1. Configure `backend/.env` using `backend/.env.example` and your local MariaDB credentials.
2. In `backend/`, run `npm install` once, then `npm run dev`. The API listens at `http://127.0.0.1:8000`.
3. In `frontend/`, set `VITE_API_BASE_URL=http://127.0.0.1:8000/api`, run `npm install`, and run `npm run dev`.

The Node API uses the existing database schema and does not automatically run schema changes or seed sample data. It reuses the existing `personal_access_tokens` format, so valid current bearer tokens remain compatible and new logins issue tokens in the same format.

## Deploy

The frontend belongs on Vercel with root directory `frontend` and `VITE_API_BASE_URL` set to the Render API origin plus `/api`. The backend belongs on Render with root directory `backend`; the root Blueprint uses `npm ci` and `npm start`. Configure `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSL`, and `FRONTEND_ORIGINS` in Render. For TiDB Cloud, use the Public connection endpoint, authorize all Render outbound ranges in TiDB, and enable TLS.

Render Free does not provide persistent disks. Files uploaded to this API's local storage can be lost when the service restarts. Use durable object storage or a paid Render service with a persistent disk if uploaded files must persist.
