/**
 * PWA Update Manager
 * Gère la détection et notification des mises à jour de la PWA
 */

(function() {
  'use strict';

  // État de la mise à jour
  let updateAvailable = false;
  let newServiceWorker = null;

  /**
   * Affiche une notification de mise à jour à l'utilisateur
   */
  function showUpdateNotification(version) {
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
            Une mise à jour est prête à être installée (${version || 'nouvelle version'})
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
      notification.remove();
      // Si un nouveau service worker est en attente, l'activer
      if (newServiceWorker) {
        newServiceWorker.postMessage({ type: 'SKIP_WAITING' });
      }
      // Recharger la page
      window.location.reload();
    });

    // Gérer le clic sur le bouton "Fermer"
    document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
      notification.remove();
    });
  }

  /**
   * Écouter les messages du service worker
   */
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', (event) => {
      if (event.data && event.data.type === 'SW_UPDATED') {
        console.log('[PWA] Nouvelle version détectée:', event.data.version);
        updateAvailable = true;
        showUpdateNotification(event.data.version);
      }
    });

    // Vérifier les mises à jour périodiquement (toutes les 5 minutes)
    if (navigator.serviceWorker.controller) {
      setInterval(() => {
        navigator.serviceWorker.getRegistration().then((registration) => {
          if (registration) {
            console.log('[PWA] Vérification des mises à jour...');
            registration.update();
          }
        });
      }, 5 * 60 * 1000); // 5 minutes
    }

    // Écouter les changements de contrôleur (nouveau SW activé)
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      console.log('[PWA] Nouveau service worker activé');
      if (updateAvailable) {
        // Éviter les rechargements en boucle
        if (!window.localStorage.getItem('pwa-reloading')) {
          window.localStorage.setItem('pwa-reloading', 'true');
          window.location.reload();
        } else {
          window.localStorage.removeItem('pwa-reloading');
        }
      }
    });
  }

  console.log('[PWA] Update manager initialized');
})();
