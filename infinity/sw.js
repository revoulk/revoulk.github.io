self.addEventListener("install", e => {
  self.skipWaiting();
});

self.addEventListener("activate", e => {
  self.clients.claim();
});

// setiap kali tab baru kasih pesan "NEW_TAB" → broadcast alert
self.addEventListener("message", e => {
  if (e.data === "NEW_TAB") {
    self.clients.matchAll().then(clients => {
      clients.forEach(client => client.postMessage("ALERT"));
    });
  }
});
