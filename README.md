# projectlimo2

## Backend proxy for Limo Anywhere

This repository includes a secure Node.js backend proxy (server/) to integrate with Limo Anywhere APIs without exposing credentials to the frontend. The existing interface and layout remain unchanged; the frontend continues to call `/api/*` endpoints.

### Setup

1) Install Node.js 18+.
2) In the repo root, run:

```
npm install
```

3) Create a `.env` file (do not commit it) with:

```
PORT=3000
NODE_ENV=development
LIMO_ANYWHERE_BASE_URL=https://api.limoanywhere.com
LIMO_ANYWHERE_API_KEY=your_api_key
LIMO_ANYWHERE_API_SECRET=your_api_secret
LIMO_ANYWHERE_COMPANY_ID=your_company_id
```

4) Start the API server:

```
npm run dev
```

This exposes `http://localhost:3000/api/*` routes used by the frontend.

### Security

- Secrets loaded from environment variables; nothing exposed to the browser.
- Helmet, rate limiting, and input validation (Joi) protect routes.
- Upstream errors normalized; sensitive details not leaked in production.
