/**
 * PWA Update Manager - Version améliorée
 * Gère la détection et notification des mises à jour de la PWA
 */

(function() {
  'use strict';

  console.log('[PWA Update] Initialisation du gestionnaire de mises à jour...');

  // État de la mise à jour
  let updateAvailable = false;
  let newServiceWorker = null;
  let registration = null;

  /**
   * Affiche une notification de mise à jour à l'utilisateur
   */
  function showUpdateNotification(version) {
    console.log('[PWA Update] Affichage de la notification de mise à jour:', version);

    // Créer un élément de notification
    const notification = document.createElement('div');
    notification.id = 'pwa-update-notification';
    notification.innerHTML = `
      <div style="
        position: fixed;
        top: 16px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 14px 16px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        z-index: 10000;
        max-width: min(90vw, 500px);
        width: 90vw;
        box-sizing: border-box;
        animation: slideDown 0.3s ease-out;
      ">
        <div style="display: flex; flex-direction: column; gap: 12px; width: 100%;">
          <div style="display: flex; align-items: start; gap: 12px;">
            <div style="flex: 1; min-width: 0;">
              <div style="font-weight: bold; margin-bottom: 4px; font-size: 15px;">
                ✨ Nouvelle version disponible !
              </div>
              <div style="font-size: 13px; opacity: 0.95; line-height: 1.4;">
                Cliquez sur "Actualiser" pour profiter des dernières améliorations
              </div>
            </div>
            <button id="pwa-dismiss-btn" style="
              background: transparent;
              color: white;
              border: 1px solid rgba(255,255,255,0.3);
              padding: 8px 10px;
              border-radius: 6px;
              cursor: pointer;
              transition: transform 0.2s;
              font-size: 16px;
              line-height: 1;
              flex-shrink: 0;
            ">
              ✕
            </button>
          </div>
          <button id="pwa-update-btn" style="
            background: white;
            color: #667eea;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
            width: 100%;
            font-size: 15px;
          ">
            Actualiser maintenant
          </button>
        </div>
      </div>
    `;

    // Ajouter les styles d'animation
    if (!document.getElementById('pwa-update-styles')) {
      const style = document.createElement('style');
      style.id = 'pwa-update-styles';
      style.textContent = `
        @keyframes slideDown {
          from {
            opacity: 0;
            transform: translate(-50%, -20px);
          }
          to {
            opacity: 1;
            transform: translate(-50%, 0);
          }
        }
        #pwa-update-btn:hover {
          transform: scale(1.05);
        }
        #pwa-dismiss-btn:hover {
          background: rgba(255,255,255,0.1);
        }
      `;
      document.head.appendChild(style);
    }

    // Supprimer l'ancienne notification si elle existe
    const existingNotification = document.getElementById('pwa-update-notification');
    if (existingNotification) {
      existingNotification.remove();
    }

    document.body.appendChild(notification);

    // Gérer le clic sur le bouton "Actualiser"
    document.getElementById('pwa-update-btn').addEventListener('click', () => {
      console.log('[PWA Update] Utilisateur a cliqué sur Actualiser');
      notification.remove();

      // Si un nouveau service worker est en attente, l'activer
      if (newServiceWorker) {
        console.log('[PWA Update] Envoi de SKIP_WAITING au nouveau SW');
        newServiceWorker.postMessage({ type: 'SKIP_WAITING' });
      }

      // Attendre un peu puis recharger
      setTimeout(() => {
        console.log('[PWA Update] Rechargement de la page...');
        window.location.reload();
      }, 100);
    });

    // Gérer le clic sur le bouton "Fermer"
    document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
      console.log('[PWA Update] Utilisateur a fermé la notification');
      notification.remove();
    });
  }

  /**
   * Détecte si une mise à jour est disponible
   */
  function checkForUpdate() {
    if (!registration) {
      console.log('[PWA Update] Pas de registration disponible');
      return;
    }

    console.log('[PWA Update] Vérification des mises à jour...');

    // Vérifier s'il y a un SW en attente
    if (registration.waiting) {
      console.log('[PWA Update] ⚠️ Un Service Worker est déjà en attente !');
      newServiceWorker = registration.waiting;
      updateAvailable = true;
      showUpdateNotification('nouvelle version');
      return;
    }

    // Vérifier s'il y a un SW en cours d'installation
    if (registration.installing) {
      console.log('[PWA Update] 🔄 Un Service Worker est en cours d\'installation...');
      trackInstalling(registration.installing);
      return;
    }

    // Forcer la vérification
    registration.update().catch(err => {
      console.error('[PWA Update] Erreur lors de la vérification:', err);
    });
  }

  /**
   * Surveille l'installation d'un nouveau SW
   */
  function trackInstalling(worker) {
    worker.addEventListener('statechange', () => {
      console.log('[PWA Update] État du nouveau SW:', worker.state);

      if (worker.state === 'installed') {
        console.log('[PWA Update] ✅ Nouveau Service Worker installé !');
        newServiceWorker = worker;
        updateAvailable = true;
        showUpdateNotification('nouvelle version');
      }
    });
  }

  /**
   * Configure les écouteurs d'événements
   */
  function setupListeners() {
    if (!('serviceWorker' in navigator)) {
      console.log('[PWA Update] Service Worker non supporté');
      return;
    }

    // Écouter les messages du service worker
    navigator.serviceWorker.addEventListener('message', (event) => {
      console.log('[PWA Update] Message reçu du SW:', event.data);

      if (event.data && event.data.type === 'SW_UPDATED') {
        console.log('[PWA Update] Mise à jour détectée via message:', event.data.version);
        updateAvailable = true;
        showUpdateNotification(event.data.version);
      }
    });

    // Écouter les changements de contrôleur
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      console.log('[PWA Update] Changement de contrôleur détecté');

      if (updateAvailable) {
        // Éviter les rechargements en boucle
        if (!window.sessionStorage.getItem('pwa-reloading')) {
          console.log('[PWA Update] Rechargement automatique...');
          window.sessionStorage.setItem('pwa-reloading', 'true');
          window.location.reload();
        } else {
          console.log('[PWA Update] Flag de rechargement détecté, nettoyage');
          window.sessionStorage.removeItem('pwa-reloading');
        }
      }
    });

    // Obtenir la registration
    navigator.serviceWorker.getRegistration().then((reg) => {
      if (!reg) {
        console.log('[PWA Update] Aucune registration trouvée');
        return;
      }

      console.log('[PWA Update] Registration obtenue');
      registration = reg;

      // Vérifier immédiatement s'il y a une mise à jour
      checkForUpdate();

      // Écouter les mises à jour de la registration
      reg.addEventListener('updatefound', () => {
        console.log('[PWA Update] 🆕 Mise à jour trouvée !');
        const newWorker = reg.installing;
        if (newWorker) {
          trackInstalling(newWorker);
        }
      });

      // Vérifier périodiquement (toutes les 60 secondes)
      setInterval(() => {
        console.log('[PWA Update] Vérification périodique...');
        reg.update();
      }, 60 * 1000);
    });

    console.log('[PWA Update] ✅ Listeners configurés');
  }

  // Initialiser quand le DOM est prêt
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupListeners);
  } else {
    setupListeners();
  }

  // Exposer une fonction de debug
  window.checkPWAUpdate = function() {
    console.log('[PWA Update] Vérification manuelle demandée');
    checkForUpdate();
  };

  // Fonction pour obtenir la version actuelle du SW
  async function getCurrentVersion() {
    try {
      const reg = await navigator.serviceWorker.getRegistration();
      if (!reg || !reg.active) return null;

      const response = await fetch(reg.active.scriptURL);
      const text = await response.text();
      const match = text.match(/CACHE_VERSION = '(.+?)'/);
      return match ? match[1] : null;
    } catch (err) {
      console.error('[PWA Update] Erreur lors de la récupération de version:', err);
      return null;
    }
  }

  // Afficher la version dans l'UI
  async function displayVersion() {
    const badge = document.getElementById('swVersion');
    if (!badge) return;

    const version = await getCurrentVersion();
    if (version) {
      badge.textContent = `⟳ ${version}`;
      badge.title = `Version du Service Worker: ${version}\nCliquez pour vérifier les mises à jour`;
      console.log('[PWA Update] Version affichée:', version);
    } else {
      badge.textContent = '⟳ v?';
      badge.title = 'Version inconnue';
    }

    // Clic sur le badge pour forcer une vérification
    badge.addEventListener('click', () => {
      console.log('[PWA Update] Clic sur le badge version, vérification...');
      badge.textContent = '⟳ ...';
      checkForUpdate();
      setTimeout(() => displayVersion(), 1000);
    });
  }

  // Afficher la version après initialisation
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(displayVersion, 500);
    });
  } else {
    setTimeout(displayVersion, 500);
  }

  // Script de diagnostic (accessible via console)
  window.diagPWA = async function() {
    console.log('🔍 DIAGNOSTIC PWA\n' + '='.repeat(50));

    // 1. Service Worker
    const reg = await navigator.serviceWorker.getRegistration();
    console.log('\n📦 Service Worker:');
    console.log('  ✓ Enregistré:', !!reg);
    if (reg) {
      console.log('  ✓ Actif:', !!reg.active);
      console.log('  ✓ En attente:', !!reg.waiting);
      console.log('  ✓ En installation:', !!reg.installing);
    }

    // 2. Version
    const version = await getCurrentVersion();
    console.log('\n📌 Version:');
    console.log('  ✓ Actuelle:', version || 'Inconnue');

    // 3. Cache
    const cacheNames = await caches.keys();
    console.log('\n💾 Caches:', cacheNames.length);
    cacheNames.forEach(name => console.log('  •', name));

    // 4. Fichiers en cache
    const staticCache = cacheNames.find(n => n.includes('static'));
    if (staticCache) {
      const cache = await caches.open(staticCache);
      const keys = await cache.keys();
      const hasPwaUpdate = keys.some(k => k.url.includes('pwa-update'));
      console.log('\n📄 Fichiers critiques:');
      console.log('  ✓ pwa-update.js:', hasPwaUpdate ? '✅ En cache' : '❌ Manquant');
    }

    // 5. État de la mise à jour
    console.log('\n🔄 État mise à jour:');
    console.log('  ✓ updateAvailable:', updateAvailable);
    console.log('  ✓ newServiceWorker:', !!newServiceWorker);
    console.log('  ✓ registration:', !!registration);

    console.log('\n' + '='.repeat(50));
    console.log('💡 Commandes utiles:');
    console.log('  • checkPWAUpdate() - Forcer une vérification');
    console.log('  • Cliquer sur le badge "⟳ v6" pour vérifier');
    console.log('='.repeat(50));
  };

  console.log('[PWA Update] ✅ Gestionnaire initialisé');
  console.log('[PWA Update] 💡 Commandes: checkPWAUpdate() ou diagPWA()');
})();
