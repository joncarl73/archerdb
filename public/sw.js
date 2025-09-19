/* public/sw.js */
const CACHE_NAME = "archerdb-v1"; // bump on deploy to invalidate old caches

// App shell to precache (add more static assets here if you want them offline by default)
const APP_SHELL = [
  "/",
  "/manifest.webmanifest",
  "/icons/icon-192.png",
  "/icons/icon-512.png",
  "/icons/maskable-192.png",
  "/icons/maskable-512.png",
];

// A tiny HTML fallback for offline navigations
const OFFLINE_HTML = `
<!doctype html>
<html lang="en">
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Offline – ArcherDB</title>
<style>
  body{margin:0;display:grid;place-content:center;min-height:100vh;background:#0b1020;color:#e5e7eb;font:16px/1.4 system-ui,sans-serif}
  .card{padding:24px 28px;border:1px solid rgba(255,255,255,.1);border-radius:14px;background:rgba(255,255,255,.03);max-width:480px}
  h1{margin:0 0 8px;font-size:20px}
  p{margin:0;color:#9ca3af}
  a{color:#8ea2ff}
</style>
<div class="card">
  <h1>You're offline</h1>
  <p>ArcherDB couldn’t reach the network. Try again when you’re back online.</p>
</div>
`;

// Utility: is this request a same-origin static asset?
const STATIC_ASSET_RE = /\.(?:css|js|mjs|json|woff2?|ttf|eot|png|jpg|jpeg|gif|webp|svg|ico)$/i;

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    (async () => {
      // Enable navigation preload for faster navigations if supported
      if ("navigationPreload" in self.registration) {
        await self.registration.navigationPreload.enable();
      }
      // Delete old caches
      const names = await caches.keys();
      await Promise.all(names.map((n) => (n !== CACHE_NAME ? caches.delete(n) : Promise.resolve())));
      await self.clients.claim();
    })()
  );
});

// Fetch strategy:
// - Navigations: network-first, fall back to preload, then cache, then offline HTML
// - Same-origin static assets: cache-first, fall back to network
// - Everything else: pass-through
self.addEventListener("fetch", (event) => {
  const { request } = event;

  // Only handle GET
  if (request.method !== "GET") return;

  // Handle navigations (document requests)
  if (request.mode === "navigate") {
    event.respondWith(
      (async () => {
        try {
          // Try the network
          const net = await fetch(request);
          // Optionally: cache a copy of successful navigations
          const cache = await caches.open(CACHE_NAME);
          cache.put(request, net.clone());
          return net;
        } catch (err) {
          // Use navigation preload if available
          const preload = await event.preloadResponse;
          if (preload) return preload;

          // Try cached page
          const cached = await caches.match(request);
          if (cached) return cached;

          // Fallback to offline HTML
          return new Response(OFFLINE_HTML, { headers: { "Content-Type": "text/html; charset=UTF-8" } });
        }
      })()
    );
    return;
  }

  const url = new URL(request.url);
  const sameOrigin = url.origin === self.location.origin;

  // Cache-first for same-origin static assets
  if (sameOrigin && STATIC_ASSET_RE.test(url.pathname)) {
    event.respondWith(
      (async () => {
        const cached = await caches.match(request);
        if (cached) return cached;
        try {
          const res = await fetch(request);
          // Cache successful 200s
          if (res && res.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, res.clone());
          }
          return res;
        } catch (err) {
          // As a last resort, try cache (might be a hashed file with different name)
          return cached || Response.error();
        }
      })()
    );
    return;
  }

  // Default: network pass-through
  // (you can add more fine-grained caching for APIs here if desired)
});

// Allow the page to trigger a SW update immediately
// Example from the page: navigator.serviceWorker.controller?.postMessage({type: 'SKIP_WAITING'})
self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});
