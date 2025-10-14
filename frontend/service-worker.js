/**
 * Service Worker for Scan2Sheet PWA
 *
 * Caching strategy:
 * - Static assets: Cache-first with background update
 * - API calls: Network-first with fallback to cache
 * - Images: Cache with size limit
 */

const CACHE_VERSION = 'v6'; // Fix PWA update detection + add SKIP_WAITING handler
const STATIC_CACHE = `scan2sheet-static-${CACHE_VERSION}`;
const API_CACHE = `scan2sheet-api-${CACHE_VERSION}`;
const IMAGE_CACHE = `scan2sheet-images-${CACHE_VERSION}`;

// Static assets to cache on install
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/js/pwa-update.js',
  '/assets/icons/icon-192.svg',
  '/assets/icons/icon-512.svg',
  // Bootstrap CSS (CDN fallback)
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  // Bootstrap Icons (CDN fallback)
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css'
];

// API endpoints to cache (for offline fallback)
const CACHEABLE_API_ENDPOINTS = [
  '/api/config',
  '/api/health',
  '/api/ready'
];

/**
 * Message event - handle messages from clients
 */
self.addEventListener('message', (event) => {
  console.log('[SW] Message received:', event.data);

  if (event.data && event.data.type === 'SKIP_WAITING') {
    console.log('[SW] SKIP_WAITING requested, activating immediately...');
    self.skipWaiting();
  }
});

/**
 * Install event - cache static assets
 */
self.addEventListener('install', (event) => {
  console.log(`[SW] Installing new version ${CACHE_VERSION}...`);

  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => {
        console.log(`[SW] Version ${CACHE_VERSION} installed, waiting to activate...`);
        return self.skipWaiting(); // Activate immediately
      })
      .catch((error) => {
        console.error('[SW] Failed to cache static assets:', error);
      })
  );
});

/**
 * Activate event - clean up old caches
 */
self.addEventListener('activate', (event) => {
  console.log(`[SW] Activating version ${CACHE_VERSION}...`);

  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            // Delete old versions of caches
            if (cacheName.startsWith('scan2sheet-') &&
                cacheName !== STATIC_CACHE &&
                cacheName !== API_CACHE &&
                cacheName !== IMAGE_CACHE) {
              console.log(`[SW] Deleting old cache: ${cacheName}`);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log(`[SW] Version ${CACHE_VERSION} activated!`);
        return self.clients.claim(); // Take control immediately
      })
      .then(() => {
        // Notify all clients that a new version is active
        return self.clients.matchAll().then((clients) => {
          clients.forEach((client) => {
            client.postMessage({
              type: 'SW_UPDATED',
              version: CACHE_VERSION
            });
          });
        });
      })
  );
});

/**
 * Fetch event - handle requests with appropriate caching strategy
 */
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip chrome-extension and other non-http(s) requests
  if (!url.protocol.startsWith('http')) {
    return;
  }

  // ⚠️ CRITICAL FIX: Ne PAS intercepter les APIs externes (Google OAuth, etc.)
  // Ces scripts DOIVENT être chargés directement par le navigateur pour éviter CORS/CSP
  const externalDomainsToSkip = [
    'accounts.google.com',          // Google Sign-In / OAuth
    'apis.google.com',              // Google APIs (gapi)
    'www.googleapis.com',           // Google APIs
    'oauth2.googleapis.com',        // OAuth endpoints
    'openidconnect.googleapis.com', // OpenID Connect
    'fonts.googleapis.com',         // Google Fonts
    'fonts.gstatic.com'             // Google Fonts static
  ];

  // Skip external APIs - let browser handle them natively
  if (externalDomainsToSkip.some(domain => url.hostname === domain || url.hostname.endsWith('.' + domain))) {
    // Silencieux pour éviter le spam dans la console
    return; // Do NOT call event.respondWith() - let browser handle it
  }

  // API requests: Network-first strategy
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirstStrategy(request, API_CACHE));
    return;
  }

  // Static assets: Cache-first with background update
  if (isStaticAsset(url.pathname)) {
    event.respondWith(cacheFirstWithBackgroundUpdate(request, STATIC_CACHE));
    return;
  }

  // Images: Cache-first strategy
  if (isImageRequest(request)) {
    event.respondWith(cacheFirstStrategy(request, IMAGE_CACHE));
    return;
  }

  // Default: Network-first for same-origin requests only
  if (url.origin === self.location.origin) {
    event.respondWith(networkFirstStrategy(request, STATIC_CACHE));
  }
  // For other external resources, let browser handle them
});

/**
 * Network-first strategy: Try network, fallback to cache
 * IMPORTANT: Only GET requests can be cached (Cache API limitation)
 */
async function networkFirstStrategy(request, cacheName) {
  try {
    const networkResponse = await fetch(request);

    // Cache successful responses (only GET requests)
    if (networkResponse.ok && request.method === 'GET') {
      const cache = await caches.open(cacheName);
      // Clone BEFORE any usage to avoid "body already used" error
      cache.put(request, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {

    // Only try cache for GET requests
    if (request.method === 'GET') {
      const cachedResponse = await caches.match(request);
      if (cachedResponse) {
        return cachedResponse;
      }
    }

    // Return offline page or error response
    return new Response(
      JSON.stringify({
        ok: false,
        error: 'Offline - no cached data available'
      }),
      {
        status: 503,
        headers: { 'Content-Type': 'application/json' }
      }
    );
  }
}

/**
 * Cache-first strategy: Try cache, fallback to network
 * IMPORTANT: Only GET requests can be cached (Cache API limitation)
 */
async function cacheFirstStrategy(request, cacheName) {
  // Only try cache for GET requests
  if (request.method === 'GET') {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
  }

  try {
    const networkResponse = await fetch(request);

    // Cache successful GET responses only
    if (networkResponse.ok && request.method === 'GET') {
      const cache = await caches.open(cacheName);

      // Limit image cache size (max 50 images)
      if (cacheName === IMAGE_CACHE) {
        await limitCacheSize(cache, 50);
      }

      // Clone BEFORE any usage
      cache.put(request, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {
    console.error('[SW] Cache-first failed:', error);
    return new Response('Offline', { status: 503 });
  }
}

/**
 * Cache-first with background update: Return cached immediately, update in background
 * IMPORTANT: Only GET requests can be cached (Cache API limitation)
 */
async function cacheFirstWithBackgroundUpdate(request, cacheName) {
  // Only try cache for GET requests
  let cachedResponse = null;
  if (request.method === 'GET') {
    cachedResponse = await caches.match(request);
  }

  // If cache exists, return it immediately and update in background
  if (cachedResponse) {
    // Update cache in background (fire and forget) - only for GET
    if (request.method === 'GET') {
      fetch(request)
        .then((networkResponse) => {
          if (networkResponse && networkResponse.ok) {
            caches.open(cacheName)
              .then((cache) => {
                // Clone BEFORE putting in cache
                cache.put(request, networkResponse.clone());
              })
              .catch((error) => {
                // Silent fail for background updates
              });
          }
        })
        .catch(() => {
          // Silent fail - network might be offline
        });
    }

    return cachedResponse;
  }

  // No cache: fetch from network and cache it (GET only)
  try {
    const networkResponse = await fetch(request);

    if (networkResponse.ok && request.method === 'GET') {
      const cache = await caches.open(cacheName);
      // Clone BEFORE returning to avoid "body already used"
      cache.put(request, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {
    return new Response('Offline', { status: 503 });
  }
}

/**
 * Limit cache size by removing oldest entries
 */
async function limitCacheSize(cache, maxSize) {
  const keys = await cache.keys();

  if (keys.length > maxSize) {
    // Remove oldest entries (FIFO)
    const keysToDelete = keys.slice(0, keys.length - maxSize);
    await Promise.all(keysToDelete.map((key) => cache.delete(key)));
  }
}

/**
 * Check if request is for a static asset
 */
function isStaticAsset(pathname) {
  return pathname.endsWith('.css') ||
         pathname.endsWith('.js') ||
         pathname.endsWith('.html') ||
         pathname === '/';
}

/**
 * Check if request is for an image
 */
function isImageRequest(request) {
  return request.destination === 'image' ||
         /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(request.url);
}

/**
 * Background sync for failed write operations
 */
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-receipts') {
    event.waitUntil(syncReceipts());
  }
});

/**
 * Sync pending receipts when online
 */
async function syncReceipts() {
  // This would retrieve pending receipts from IndexedDB and retry sending
  // Implementation depends on frontend storing failed requests
}

/**
 * Push notifications (future enhancement)
 */
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};

  const options = {
    body: data.body || 'New notification from Scan2Sheet',
    icon: '/assets/icons/icon-192.png',
    badge: '/assets/icons/icon-192.png',
    vibrate: [200, 100, 200]
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'Scan2Sheet', options)
  );
});

