// firebase-messaging-sw.js
// Ce fichier DOIT être dans le dossier public/

importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey:            "AIzaSyBjdiADDYJ-ED0fLWQDwch68lbKlW0excE",
  authDomain:        "voyage-app-90eb2.firebaseapp.com",
  projectId:         "voyage-app-90eb2",
  storageBucket:     "voyage-app-90eb2.firebasestorage.app",
  messagingSenderId: "280920414423",
  appId:             "1:280920414423:web:18c223c1f9e256d637f892"
});

const messaging = firebase.messaging();

// Gérer les notifications en arrière-plan
messaging.onBackgroundMessage(function(payload) {
  console.log('[SW] Notification reçue en arrière-plan :', payload);

  const title   = payload.notification?.title || 'Vianova';
  const options = {
    body: payload.notification?.body || '',
    icon: '/assets_front/images/logo.png',
    badge: '/assets_front/images/logo.png',
    data: payload.data || {}
  };

  self.registration.showNotification(title, options);
});

// Clic sur la notification → rediriger vers l'URL
self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  const url = event.notification.data?.url || '/profile';
  event.waitUntil(clients.openWindow(url));
});