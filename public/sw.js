/** A small cache for the app shell, so the browser launches and the static
 *  assets (CSS, JS, logo) load even on a spotty connection. Authenticated
 *  pages are deliberately NOT cached (they'd leak data between users). */
// v3: v2 had a zero-byte favicon cached, and stale-while-revalidate kept
// handing it back after the file itself was fixed. Bumping the name makes the
// activate handler drop the old cache entirely.
var CACHE_NAME = 'pmas-shell-v3';
var OFFLINE_URL = '/offline.html';
var CACHEABLE_EXT = /\.(css|js|png|jpe?g|gif|svg|webp|woff2?|ico|avif)(\?.*)?$/i;

self.addEventListener('install', function (event) {
	event.waitUntil(
		caches.open(CACHE_NAME).then(function (cache) {
			return cache.add(OFFLINE_URL);
		})
	);
	self.skipWaiting();
});

self.addEventListener('activate', function (event) {
	event.waitUntil(
		caches.keys().then(function (keys) {
			return Promise.all(keys.filter(function (key) {
				return key !== CACHE_NAME;
			}).map(function (key) {
				return caches.delete(key);
			}));
		})
	);
	self.clients.claim();
});

self.addEventListener('fetch', function (event) {
	var request = event.request;
	var url = new URL(request.url);

	if (request.method !== 'GET' || url.origin !== self.location.origin) {
		return;
	}

	// Cache static assets, stale-while-revalidate.
	if (CACHEABLE_EXT.test(url.pathname)) {
		event.respondWith(
			caches.match(request).then(function (cached) {
				var network = fetch(request).then(function (response) {
					// An empty 200 is still a 200. favicon.ico answered with zero
					// bytes for months, and caching that made the blank tab
					// outlive the fix: the cache kept handing back the empty file
					// faster than the network could replace it.
					var length = response.headers.get('content-length');
					var empty = length !== null && parseInt(length, 10) === 0;

					if (response.status === 200 && !empty) {
						var clone = response.clone();
						caches.open(CACHE_NAME).then(function (cache) { cache.put(request, clone); });
					}
					return response;
				}).catch(function () {
					return cached;
				});
				return cached || network;
			})
		);
		return;
	}

	// Only navigations can fall back to the offline page.
	if (request.mode === 'navigate') {
		event.respondWith(
			fetch(request).catch(function () {
				return caches.match(OFFLINE_URL);
			})
		);
	}
});
