// import IndexedDB
importScripts('src/js/idb.js');
importScripts('src/js/utility.js');

// quando cambi questi valori modificali anche in protected/view/layouts/js_sw.php
var CACHE_STATIC_NAME = 'napay-pos-static-v12b';
var CACHE_DYNAMIC_NAME = 'napay-pos-dynamic-v12b';
var STATIC_FILES = [
	'/',
	'offline.php',
	'manifest.json',
];

// Funzione Fix per apache
function cleanResponse(response) {
	const clonedResponse = response.clone();

	// Not all browsers support the Response.body stream, so fall back to reading
	// the entire body into memory as a blob.
	const bodyPromise = 'body' in clonedResponse ?
	  Promise.resolve(clonedResponse.body) :
	  clonedResponse.blob();

	return bodyPromise.then((body) => {
	  // new Response() is happy when passed either a stream or a Blob.
	  return new Response(body, {
			headers: clonedResponse.headers,
			status: clonedResponse.status,
			statusText: clonedResponse.statusText,
	  });
	});
}


function isInArray(string, array) {
	for(var i = 0; i < array.length; i++) {
		if (array[i] === string){
			return true;
		}
	}
	return false;
}
function trimCache(cacheName, maxItems) {
	caches.open(cacheName)
		.then(function(cache) {
			return cache.keys()
				.then(function(keys) {
					if (keys.lenght > maxItems) {
						cache.delete(keys[0])
							.then(trimCache(cacheName, maxItems));
					}
				});
		});
}

self.addEventListener('install', function (event) {
	//console.log('[Service Worker] Installing Service worker...', event);
	event.waitUntil(
		//versioning della cache. Per aggiornare le versioni del software
		caches.open(CACHE_STATIC_NAME)
			.then(function(cache){
				console.log('[Service Worker] Precaching app shell...');
				cache.addAll(STATIC_FILES);
			})
	)
});
self.addEventListener('activate', function (event) {
	console.log('[Service Worker] Activating Service worker...', event);
	event.waitUntil(
		caches.keys()
			.then(function(keyList) {
				return Promise.all(keyList.map(function(key){
					if (key !== CACHE_STATIC_NAME && key !== CACHE_DYNAMIC_NAME){
						console.log('[Service Worker] deleting cache', key);
						return caches.delete(key);
					}
				}));
			})

	);
	return self.clients.claim();
});

// // restituisco sempre l'originale e non carico da cache
// self.addEventListener('fetch', function (event) {
// 	event.respondWith(
// 		fetch(event.request)
// 	);
// });

function getFileExtension(filename) {
  return filename.split('.').pop();
}

// restituisco sempre l'originale e non carico da cache
self.addEventListener('fetch', function (event) {
	var parser = new URL(event.request.url);


	if (getFileExtension(parser.pathname) == 'php'
		|| getFileExtension(parser.pathname) == 'css'
	){
		console.log('[SW Parser] web ',parser.pathname);
		event.respondWith(
		 	fetch(event.request)
		);
	} else if (isInArray(event.request.url, STATIC_FILES)) {
		console.log('[SW Parser] static cache ',parser.pathname);
		event.respondWith(
			fetch(event.request).catch(function(){
				return	caches.match(event.request);
			})

		);
	} else {
		console.log('[SW Parser] dynamic cache ',parser.pathname);
		event.respondWith(
			caches.match(event.request)
				.then(function(response) {
					if (response) {
						// Inizio Fix per apache
						if(response.redirected) {
							return cleanResponse(response);
						} else {
							return response;
						}
						// END Fix per apache
					} else {
						return fetch(event.request)
							.then(function(res) {
								return caches.open(CACHE_DYNAMIC_NAME)
									.then(function(cache) {
										//trimCache(CACHE_DYNAMIC_NAME, 20);
										cache.put(event.request.url, res.clone());
										return res;
									})
							}).
							catch(function(err) {
								return caches.open(CACHE_STATIC_NAME)
									.then(function(cache) {
										if (event.request.headers.get('accept').includes('text/html')){
											return cache.match('offline.php');
										}
									})
							});
					}
				})
		);
	}
});
