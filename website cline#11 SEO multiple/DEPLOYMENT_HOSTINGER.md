# Hostinger hPanel Deployment Guide (Secure API Key)

This guide ensures your Limo Anywhere API key remains hidden and not accessible by users or attackers, while deploying your site to Hostinger.

## 1) Keep the key OUTSIDE public_html
- In hPanel → Files → File Manager:
  - At your account home (the parent directory that contains `public_html/`), create a folder: `secure_config/`.
  - Inside it, create `limo_anywhere_key.php` with EXACTLY:
    ```php
    <?php
    $apiKey = 'YOUR_REAL_LIMO_ANYWHERE_KEY';
    ```
  - Do not echo/print anything. No closing `?>` tag required.
  - Permissions: 600 or 640 is recommended.

## 2) Upload only what belongs in public_html
Upload these files into `public_html/`:
- `index.html`
- `styles.css`
- `script.js`
- All referenced images and assets (e.g., `1.png`, `herobg.png`, `about-bg.jpg`, `about-card.jpg`, `fleet-*.png`, `booking-bg.png`, `contactus-bg.png`)
- `public_html/api.php` (place it inside `public_html/`)

Do NOT upload these to `public_html/` (exclude from hosting upload):
- `server.js` (Node dev server)
- `package.json` (Node project file)
- `.env` (local dev env file)
- `test-api.js` (dev test script)
- `LIMO_ANYWHERE_INTEGRATION.md`, `README.md` (docs)
- Any `node_modules/` folder

## 3) Important: Backend choice for production
Your current frontend (`script.js`) is configured to call a backend route at `/backend/limo`, which expects a Node.js server to be running. You have two options:

- Option A: Use Hostinger Node.js App (keeps the PHP proxy completely private)
  1. In hPanel → Advanced → Node.js, create a Node app and deploy `server.js`.
  2. Set environment variable `PHP_API_URL` to point to your PHP proxy:
     - Example: `http://127.0.0.1/public_html/api.php`
  3. Start the Node app. The browser will call `/backend/limo`, Node will call the local PHP `api.php`, and `api.php` will call Limo Anywhere using the hidden key.
  4. Keep `public_html/api.php` as-is (it returns 404 to non-local requests), preserving invisibility.

- Option B: PHP-only (no Node)
  - If your plan does not include Node, tell the developer to switch the frontend to call `public_html/api.php` directly and to add a strict path allowlist inside `api.php`. This keeps the key hidden server-side, but the endpoint will be public (hardened) instead of local-only.

## 4) Sanity checks after upload
- Visiting `https://yourdomain.com/api.php` should return `404 Not Found` (by design) if you’re using Option A (Node).
- The API key should never appear in page source, JS, or network responses.
- `secure_config/limo_anywhere_key.php` must NOT be inside `public_html/`.

## 5) Security best practices
- Never commit real keys to version control.
- Restrict file permissions on `secure_config/limo_anywhere_key.php`.
- Ensure PHP error display is off in production (already handled in `api.php`).
- Consider defense-in-depth: a private shared-secret header between your backend and `api.php`.
