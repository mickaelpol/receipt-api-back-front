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
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 16px 24px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        z-index: 10000;
        max-width: 90%;
        display: flex;
        align-items: center;
        gap: 16px;
        animation: slideDown 0.3s ease-out;
      ">
        <div style="flex: 1;">
          <div style="font-weight: bold; margin-bottom: 4px;">
            ✨ Nouvelle version disponible !
          </div>
          <div style="font-size: 13px; opacity: 0.95;">
            Cliquez sur "Actualiser" pour profiter des dernières améliorations
          </div>
        </div>
        <button id="pwa-update-btn" style="
          background: white;
          color: #667eea;
          border: none;
          padding: 10px 20px;
          border-radius: 8px;
          font-weight: bold;
          cursor: pointer;
          transition: transform 0.2s;
          white-space: nowrap;
        ">
          Actualiser
        </button>
        <button id="pwa-dismiss-btn" style="
          background: transparent;
          color: white;
          border: 1px solid rgba(255,255,255,0.3);
          padding: 10px 16px;
          border-radius: 8px;
          cursor: pointer;
          transition: transform 0.2s;
        ">
          ✕
        </button>
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

  console.log('[PWA Update] ✅ Gestionnaire initialisé - Tapez checkPWAUpdate() pour forcer une vérification');
})();
