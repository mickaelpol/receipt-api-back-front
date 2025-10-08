/* ========= CONFIG ========= */
/**
 * Dynamic API base URL determination
 * Priority: localStorage override > Cloud Run detection > localhost fallback
 */
(() => {
    const override = (localStorage.getItem('BACK_BASE') || '').trim();
    let base = override;
    if (!base) {
        const h = location.hostname;
        if (h.endsWith('run.app') || h.endsWith('appspot.com')) {
            base = location.origin;                // Cloud Run / App Engine → même origine
        } else {
            base = 'http://localhost:8080';        // dev local
        }
    }
    // on fige dans window pour debug si besoin
    window.BACK_BASE = base;
})();
const BACK_BASE = window.BACK_BASE;

// /config renvoyée par le back
let CLIENT_ID = null;
let DEFAULT_SHEET = null;
let RECEIPT_API_URL = null;
let WHO_OPTIONS = [];

/* ========= STATE ========= */
let accessToken = null;
let tokenClient = null;
let gisReady = false, gapiReady = false;
let currentUserEmail = null;
let _previewUrl = null;

/* ========= LOCAL STORAGE KEYS ========= */
// const LS_TOKEN_KEY = 'gis_access_token'; // Non utilisé (stockage en mémoire uniquement pour la sécurité)
const LS_TOKEN_EXP = 'gis_access_token_exp';
const LS_ACCOUNT_HINT = 'gis_account_hint';

let MAX_UPLOADS = 10;

/* ========= HELPERS ========= */
/**
 * DOM selector shorthand
 * @param {string} selector CSS selector
 * @returns {Element|null} First matching element
 */
const $ = s => document.querySelector(s);

/**
 * Update status message display
 * @param {string} msg Status message to display
 */
const setStatus = msg => {
    const el = $('#status');
    if (el) {
        // Si le message contient du HTML (comme notre point vert), utiliser innerHTML
        if (msg.includes('<span')) {
            el.innerHTML = msg;
        } else {
            el.textContent = msg;
        }
    }
};

/**
 * Enable/disable save button
 * @param {boolean} on Whether to enable the button
 */
const enableSave = on => {
    const b = $('#btnSave');
    if (b) {
        // Désactiver le bouton si l'utilisateur n'est pas autorisé
        b.disabled = !on || !isUserAuthorized();
    }
};

/**
 * Gérer l'état des champs de fichiers selon l'autorisation
 */
function updateFileInputsState() {
    const singleFile = $('#file');
    const multiFile = $('#multiFiles');

    if (singleFile) {
        singleFile.disabled = !isUserAuthorized();
    }
    if (multiFile) {
        multiFile.disabled = !isUserAuthorized();
    }
}

const TOAST_DURATION = 3000;

// Configuration du monitoring des services
const SERVICE_MONITOR_INTERVAL = 10000; // 10 secondes (modifiable)
let serviceMonitorInterval = null;

/**
 * Mettre à jour le statut d'un service
 * @param {string} serviceId ID de l'élément (healthStatus ou readyStatus)
 * @param {boolean} isOnline État du service
 * @param {string} message Message à afficher
 * @param {string} details Détails supplémentaires
 */
function updateServiceStatus(serviceId, isOnline, message, details = '') {
    const element = document.getElementById(serviceId);
    if (!element) return;

    if (isOnline) {
        element.className = 'badge bg-success';
        element.textContent = `● ${message}`;
        element.title = details || `${serviceId === 'healthStatus' ? 'Application' : 'Services'} opérationnel`;
    } else {
        element.className = 'badge bg-danger';
        element.textContent = `● ${message}`;
        element.title = details || `${serviceId === 'healthStatus' ? 'Application' : 'Services'} hors service`;
    }
}

/**
 * Vérifier l'état d'un service
 * @param {string} endpoint Endpoint à tester (/health ou /ready)
 * @param {string} serviceId ID de l'élément à mettre à jour
 * @returns {Promise<void>}
 */
async function checkService(endpoint, serviceId) {
    try {
        const response = await fetch(`${BACK_BASE}${endpoint}`, {
            method: 'GET',
            cache: 'no-store'
        });

        if (response.ok) {
            const data = await response.json();
            let status = data.status || 'OK';
            let details = '';

            // Ajouter des détails selon l'endpoint
            if (endpoint === '/health') {
                details = `Application en vie - ${data.timestamp || 'maintenant'}`;
            } else if (endpoint === '/ready') {
                details = 'PHP + DocAI + Credentials OK';
                if (data.credentials) {
                    details += ` (${data.credentials.project_id || 'GCP'})`;
                }
                if (data.timestamp) {
                    details += ` - ${data.timestamp}`;
                }
            }

            updateServiceStatus(serviceId, true, status, details);
        } else {
            const errorText = await response.text();
            let errorDetails = `HTTP ${response.status}`;
            try {
                const errorData = JSON.parse(errorText);
                if (errorData.error) {
                    errorDetails += `: ${errorData.error}`;
                }
            } catch (e) {
                errorDetails += `: ${errorText.substring(0, 50)}`;
            }

            updateServiceStatus(serviceId, false, 'Error', errorDetails);
        }
    } catch (error) {
        updateServiceStatus(serviceId, false, 'Offline', `Connexion impossible: ${error.message}`);
    }
}

/**
 * Vérifier l'état de tous les services
 */
async function checkAllServices() {
    await Promise.all([
        checkService('/health', 'healthStatus'),
        checkService('/ready', 'readyStatus')
    ]);
}

/**
 * Démarrer le monitoring des services
 */
function startServiceMonitoring() {
    // Arrêter le monitoring existant s'il y en a un
    if (serviceMonitorInterval) {
        clearInterval(serviceMonitorInterval);
    }

    // Vérification immédiate
    checkAllServices();

    // Puis toutes les 10 secondes
    serviceMonitorInterval = setInterval(checkAllServices, SERVICE_MONITOR_INTERVAL);
}

/**
 * Arrêter le monitoring des services
 */
function stopServiceMonitoring() {
    if (serviceMonitorInterval) {
        clearInterval(serviceMonitorInterval);
        serviceMonitorInterval = null;
    }
}

// === FONCTIONS DE TEST/SIMULATION ===
/**
 * Simuler une panne d'un service (pour test)
 * @param {string} serviceId ID du service à simuler en panne
 */
function simulateServiceFailure(serviceId) {
    if (serviceId === 'healthStatus') {
        updateServiceStatus('healthStatus', false, 'Error', 'Simulation: Application hors service');
    } else if (serviceId === 'readyStatus') {
        updateServiceStatus('readyStatus', false, 'Error', 'Simulation: DocAI inaccessible');
    }
}

/**
 * Simuler une récupération d'un service (pour test)
 * @param {string} serviceId ID du service à simuler en récupération
 */
function simulateServiceRecovery(serviceId) {
    if (serviceId === 'healthStatus') {
        updateServiceStatus('healthStatus', true, 'alive', 'Simulation: Application récupérée');
    } else if (serviceId === 'readyStatus') {
        updateServiceStatus('readyStatus', true, 'ready', 'Simulation: Services récupérés');
    }
}

/**
 * Tester tous les scénarios d'erreur (pour debug)
 */
function testAllErrorScenarios() {
    console.log('🧪 Test des scénarios d\'erreur...');

    // Test 1: App en panne
    setTimeout(() => {
        simulateServiceFailure('healthStatus');
        console.log('❌ Simulé: App en panne');
    }, 1000);

    // Test 2: Services en panne
    setTimeout(() => {
        simulateServiceFailure('readyStatus');
        console.log('❌ Simulé: Services en panne');
    }, 2000);

    // Test 3: Récupération App
    setTimeout(() => {
        simulateServiceRecovery('healthStatus');
        console.log('✅ Simulé: App récupérée');
    }, 5000);

    // Test 4: Récupération Services
    setTimeout(() => {
        simulateServiceRecovery('readyStatus');
        console.log('✅ Simulé: Services récupérés');
    }, 7000);

    // Test 5: Retour au monitoring normal
    setTimeout(() => {
        startServiceMonitoring();
        console.log('🔄 Retour au monitoring normal');
    }, 10000);
}

// Exposer les fonctions de test dans la console pour debug
if (typeof window !== 'undefined') {
    window.simulateServiceFailure = simulateServiceFailure;
    window.simulateServiceRecovery = simulateServiceRecovery;
    window.testAllErrorScenarios = testAllErrorScenarios;
    window.checkAllServices = checkAllServices;
}

/* --- Toast (warning soft) --- */
function ensureToastStyles() {
    if (document.getElementById('tiny-toast-css')) return;
    const css = document.createElement('style');
    css.id = 'tiny-toast-css';
    css.textContent = `
  .tiny-toast-wrap {
    position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%);
    z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none;
  }
  .tiny-toast {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(180deg, #FFF8DB, #FFE9A8);
    color: #5C4400; border: 1px solid #F6C552;
    border-radius: 10px; padding: 10px 14px;
    box-shadow: 0 6px 18px rgba(0,0,0,.12);
    font-size: 14px; max-width: 92vw; pointer-events: auto;
    transition: opacity .2s ease, transform .2s ease; opacity: 0; transform: translateY(6px);
  }
  .tiny-toast::before { content: "⚠️"; }
  .tiny-toast.show { opacity: 1; transform: translateY(0); }
  .tiny-toast.hide { opacity: 0; transform: translateY(6px); }
  @media (prefers-color-scheme: dark) {
    .tiny-toast { background: linear-gradient(180deg, #3A3205, #4A3E07); color: #FFE9A8; border-color: #8A6B02; }
  }`;
    document.head.appendChild(css);
}

function showToast(message) {
    ensureToastStyles();
    let wrap = document.getElementById('tiny-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'tiny-toast-wrap';
        wrap.className = 'tiny-toast-wrap';
        document.body.appendChild(wrap);
    }
    const el = document.createElement('div');
    el.className = 'tiny-toast';
    el.textContent = message;
    wrap.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
        el.classList.remove('show');
        el.classList.add('hide');
        setTimeout(() => el.remove(), 220);
    }, TOAST_DURATION);
}

/* ------- Token storage helpers ------- */
/**
 * Store authentication token in memory only (security)
 * @param {string} token Access token
 * @param {number} expiresInSec Token expiration in seconds
 */
function storeToken(token, expiresInSec) {
    accessToken = token;
    // Store only session flag, not the token itself
    try {
        sessionStorage.setItem('wasAuthenticated', 'true');
        // Optionally store expiration for better token management
        if (expiresInSec) {
            const expirationTime = Date.now() + (expiresInSec * 1000);
            localStorage.setItem(LS_TOKEN_EXP, expirationTime.toString());
        }
    } catch { }
}

/**
 * Load valid token from memory
 * @returns {string|null} Valid token or null
 */
function loadValidToken() {
    try {
        const wasAuth = sessionStorage.getItem('wasAuthenticated');
        if (wasAuth && accessToken) {
            // Check if token is still valid (not expired)
            const expirationTime = localStorage.getItem(LS_TOKEN_EXP);
            if (expirationTime && Date.now() > parseInt(expirationTime)) {
                // Token expired, clear it
                clearStoredToken();
                return null;
            }
            return accessToken; // Memory-only token
        }
    } catch { }
    return null;
}

/**
 * Clear stored authentication data
 */
function clearStoredToken() {
    accessToken = null;
    try {
        sessionStorage.removeItem('wasAuthenticated');
        localStorage.removeItem(LS_TOKEN_EXP);
    } catch { }
}

/**
 * Store user account hint for next login
 * @param {string} email User email address
 */
function storeAccountHint(email) {
    try { if (email) localStorage.setItem(LS_ACCOUNT_HINT, email); } catch { }
}

/**
 * Load stored account hint
 * @returns {string} Stored email or empty string
 */
function loadAccountHint() {
    try { return localStorage.getItem(LS_ACCOUNT_HINT) || ''; } catch { return ''; }
}

/* ========= Helpers upload ========= */
/**
 * Encode file for Document AI processing
 * @param {File} file Image file to encode
 * @returns {Promise<string>} Base64 encoded string
 */
async function encodeForDocAI(file) {
    if (file.size <= 2.5 * 1024 * 1024) return await fileToBase64NoPrefix(file);
    return await compressToBase64(file, 2400, 0.96, 'image/jpeg');
}

/**
 * Reset file input element
 */
function resetFileInput() {
    const old = document.getElementById('file');
    if (!old) return;
    try { old.value = ''; } catch { }
    const fresh = old.cloneNode(true);
    old.parentNode.replaceChild(fresh, old);
    fresh.addEventListener('change', onImagePicked);
}

/* ========= UI helpers (auth) ========= */
function setAuthStatus(text, ok = null) {
    const el = $('#authStatus');
    if (!el) return;
    el.textContent = text;
    el.classList.remove('text-muted', 'text-success', 'text-danger');
    el.style.removeProperty('color');
    if (ok === true) {
        el.classList.add('text-success');
        el.style.setProperty('color', '#28a745', 'important');
    } else if (ok === false) {
        el.classList.add('text-danger');
        el.style.setProperty('color', '#dc3545', 'important');
    }
}
const showAuthButton = () => { const b = $('#btnAuth'); if (b) b.style.display = 'inline-block'; };
const hideAuthButton = () => { const b = $('#btnAuth'); if (b) b.style.display = 'none'; };
const showSwitchButton = () => { const b = $('#btnSwitch'); if (b) b.style.display = 'inline-block'; };
const hideSwitchButton = () => { const b = $('#btnSwitch'); if (b) b.style.display = 'none'; };
const hideAuthStatus = () => { const s = $('#authStatus'); if (s) s.style.display = 'none'; };
const showAuthStatus = () => { const s = $('#authStatus'); if (s) s.style.display = 'inline'; };

// Variable globale pour tracker l'état d'erreur d'authentification
let hasAuthError = false;

/**
 * Vérifier si l'utilisateur est autorisé à effectuer des actions
 * @returns {boolean} True si autorisé, false sinon
 */
function isUserAuthorized() {
    return currentUserEmail && !hasAuthError;
}

/**
 * Bloquer l'action si l'utilisateur n'est pas autorisé
 * @param {string} action Description de l'action bloquée
 * @returns {boolean} True si autorisé, false si bloqué
 */
function checkAuthorization(action = 'cette action') {
    if (!isUserAuthorized()) {
        setAnalysisStatus(`❌ Compte non autorisé - ${action} bloquée`, '#dc3545');
        return false;
    }
    return true;
}

/**
 * Afficher un message d'analyse en dessous du statut de connexion
 * @param {string} message Le message à afficher
 * @param {string} color Couleur du message (par défaut: #666)
 */
function setAnalysisStatus(message, color = '#666') {
    // Créer ou mettre à jour un div pour les messages d'analyse
    let analysisDiv = document.getElementById('analysisStatus');
    if (!analysisDiv) {
        analysisDiv = document.createElement('div');
        analysisDiv.id = 'analysisStatus';
        analysisDiv.style.fontSize = '0.9em';
        analysisDiv.style.marginTop = '5px';

        // Insérer après le div status
        const statusDiv = document.getElementById('status');
        if (statusDiv && statusDiv.parentNode) {
            statusDiv.parentNode.insertBefore(analysisDiv, statusDiv.nextSibling);
        }
    }

    analysisDiv.style.color = color;
    analysisDiv.textContent = message;
    analysisDiv.style.display = message ? 'block' : 'none';
}

/**
 * Effacer le message d'analyse
 */
function clearAnalysisStatus() {
    setAnalysisStatus('');
}

function clearSheetsSelect(placeholder = '') {
    const sel = $('#sheetSelect');
    if (!sel) return;
    if (placeholder) sel.innerHTML = `<option disabled selected>${placeholder}</option>`; else sel.innerHTML = '';
}
function needAuthUI(msg = 'Veuillez vous connecter.') {
    showAuthButton();
    hideSwitchButton();
    showAuthStatus();
    setAuthStatus(msg, false);
    enableSave(false);
    clearSheetsSelect('Feuilles indisponibles');
}
function neutralAuthUI(msg = 'Connexion…') {
    showAuthButton();
    hideSwitchButton();
    showAuthStatus();
    setAuthStatus(msg, null);
    enableSave(false);
}

/* ========= HTTP helper ========= */
/**
 * Make authenticated API request with automatic retry on 401
 * @param {string} path API endpoint path
 * @param {Object} options Request options
 * @param {string} options.method HTTP method
 * @param {Object} options.headers Additional headers
 * @param {*} options.body Request body
 * @returns {Promise<*>} API response data
 */
async function api(path, { method = 'GET', headers = {}, body = null } = {}) {
    const needsAuth = path.startsWith('/api/') && !path.startsWith('/api/config');

    // Headers par défaut pour toutes les requêtes API
    const h = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...headers
    };

    // Ajouter l'authentification si nécessaire
    if (needsAuth) {
        if (!accessToken) {
            try {
                await ensureConnected(false);
                if (!accessToken) throw new Error('Token absent après ensureConnected');
            } catch {
                throw new Error('Connexion requise');
            }
        }
        h['Authorization'] = `Bearer ${accessToken}`;
    }

    const res = await fetch(`${BACK_BASE}${path}`, {
        method,
        headers: h,
        body: body ? (typeof body === 'string' ? body : JSON.stringify(body)) : null,
        credentials: 'omit',
        cache: 'no-store'
    });

    const txt = await res.text();
    let json;
    try { json = JSON.parse(txt); } catch { json = null; }

    if (!res.ok) {
        // Gestion spéciale des erreurs 401
        if (res.status === 401 && needsAuth) {
            try {
                // Une seule tentative de refresh
                accessToken = null;
                clearStoredToken();
                await ensureConnected(false);
                if (accessToken) {
                    // Retry avec le nouveau token
                    h['Authorization'] = `Bearer ${accessToken}`;
                    const retryRes = await fetch(`${BACK_BASE}${path}`, {
                        method,
                        headers: h,
                        body: body ? (typeof body === 'string' ? body : JSON.stringify(body)) : null,
                        credentials: 'omit',
                        cache: 'no-store'
                    });
                    const retryTxt = await retryRes.text();
                    let retryJson;
                    try { retryJson = JSON.parse(retryTxt); } catch { retryJson = null; }

                    if (retryRes.ok) {
                        return retryJson ?? retryTxt;
                    }
                }
            } catch {
                // Refresh failed, continue to error handling
            }

            // Si on arrive ici, le refresh a échoué
            showToast('Session expirée — veuillez vous reconnecter');
            needAuthUI('Session expirée');
            throw new Error('Session expirée');
        }

        throw new Error(json?.error || `HTTP ${res.status}`);
    }

    return json ?? txt;
}

/* ========= PREVIEW (simple) ========= */
function syncPreviewHeight() {
    const formCol = $('#formCol');
    const wrap = $('#previewWrap');
    if (!formCol || !wrap) return;
    const hForm = Math.round(formCol.getBoundingClientRect().height);
    const hVh = Math.round(window.innerHeight * 0.75);
    const h = Math.max(160, Math.min(hForm, hVh));
    wrap.style.setProperty('--preview-h', `${h}px`);
}
function setPreview(urlOrNull) {
    const wrap = $('#previewWrap');
    const img = $('#preview');
    if (!img || !wrap) return;
    if (_previewUrl) { URL.revokeObjectURL(_previewUrl); _previewUrl = null; }
    if (!urlOrNull) {
        img.removeAttribute('src');
        wrap.classList.remove('has-image');
        syncPreviewHeight();
        return;
    }
    _previewUrl = urlOrNull;
    img.onload = () => { syncPreviewHeight(); };
    img.src = _previewUrl;
    wrap.classList.add('has-image');
}

/* ========= BOOT ========= */
document.addEventListener('DOMContentLoaded', init);
window.addEventListener('resize', () => syncPreviewHeight());
function applyAuthStack() {
    const s = document.getElementById('authStatus');
    // const _a = document.getElementById('btnAuth'); // Non utilisé
    // const _sw = document.getElementById('btnSwitch'); // Non utilisé
    if (!s) return;
    const parent = s.parentElement;
    if (!parent) return;
    if (window.matchMedia('(max-width: 992px)').matches) parent.classList.add('auth-stack');
    else parent.classList.remove('auth-stack');
}
window.addEventListener('resize', applyAuthStack);
document.addEventListener('DOMContentLoaded', applyAuthStack);

// Arrêter le monitoring des services quand la page se ferme
window.addEventListener('beforeunload', stopServiceMonitoring);

async function init() {
    try {
        // Afficher un splash de connexion
        neutralAuthUI('Connexion…');

        // Ne pas modifier le statut si on est déjà connecté
        if (!currentUserEmail) {
            setStatus('Initialisation...');
        }

        await loadConfig();
        bindUI();
        await bootGoogle();

        // Connexion silencieuse avec feedback
        if (!currentUserEmail) {
            setStatus('Connexion...');
        }
        await autoSignIn();

        syncPreviewHeight();
        initMultiUIGrid();
        setupFloatingActions();
        renderMultiEmptyIfNeeded();

        // Démarrer le monitoring des services
        startServiceMonitoring();
    } catch {
        if (!currentUserEmail) {
            setStatus('Config indisponible');
        }
        needAuthUI('Erreur de configuration');
    }
}

/* ========= /config ========= */
async function loadConfig() {
    const u = new URL(`${BACK_BASE}/api/config`);
    u.searchParams.set('t', String(Date.now()));
    const cfg = await fetch(u, { cache: 'no-store' }).then(r => r.json());
    if (!cfg.ok) throw new Error(cfg.error || 'Config error');

    CLIENT_ID = cfg.client_id || null;
    DEFAULT_SHEET = cfg.default_sheet || 'Feuille 1';
    RECEIPT_API_URL = cfg.receipt_api_url || `${BACK_BASE}/api/scan`;
    MAX_UPLOADS = Number.isFinite(cfg.max_batch) ? Number(cfg.max_batch) : 10;

    WHO_OPTIONS = Array.isArray(cfg.who_options) ? cfg.who_options : [];
    renderWhoOptions(WHO_OPTIONS);
}

/* ========= UI ========= */
function bindUI() {
    // Simple
    $('#file')?.addEventListener('change', onImagePicked);
    $('#btnSave')?.addEventListener('click', saveToSheet);
    $('#btnReset')?.addEventListener('click', resetForm);

    // Auth
    $('#btnAuth')?.addEventListener('click', async () => {
        try {
            neutralAuthUI('Connexion…');
            await ensureConnected(true);
            await afterSignedIn();
            setStatus(`<span style="color: #28a745; font-weight: bold;">●</span> Connecté - ${currentUserEmail}`);
            clearAnalysisStatus();
            hideAuthStatus();
            hasAuthError = false;
            updateFileInputsState();
        } catch (e) { handleAuthError(e); }
    });
    $('#btnSwitch')?.addEventListener('click', switchAccount);
    hideSwitchButton();

    // Format total
    $('#total')?.addEventListener('blur', () => {
        const n = parseEuroToNumber($('#total').value);
        if (n != null) $('#total').value = n.toFixed(2).replace('.', ',');
        validateCanSave();
    });
    ['merchant', 'date', 'total'].forEach(id => $('#' + id)?.addEventListener('input', validateCanSave));

    // Toggle simple ↔ multi
    $('#modeToggle')?.addEventListener('change', (e) => {
        switchMode(e.target.checked ? 'multi' : 'single');
    });

    // Multi
    $('#multiFiles')?.addEventListener('change', onMultiFilesPicked);

    switchMode('single');
}

/* Segmented control dynamique */
const LS_WHO = 'scan_who_last';
const slug = s => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '').toLowerCase();

function renderWhoOptions(names = []) {
    const wrap = document.getElementById('whoGroup');
    if (!wrap) return;

    if (!Array.isArray(names) || names.length === 0) {
        wrap.innerHTML = '<span class="subtle">Aucun profil configuré.</span>';
        return;
    }
    const last = localStorage.getItem(LS_WHO);
    const def = names.includes(last) ? last : names[0];

    wrap.classList.add('seg');
    wrap.innerHTML = names.map((n) => {
        const id = `whoTop-${slug(n)}`;
        const checked = (n === def) ? 'checked' : '';
        return `
      <input type="radio" name="whoTop" id="${id}" value="${n}" ${checked}>
      <label for="${id}">${n}</label>
    `;
    }).join('');

    wrap.addEventListener('change', () => {
        const val = document.querySelector('input[name="whoTop"]:checked')?.value || '';
        if (val) localStorage.setItem(LS_WHO, val);
        validateCanSave();
    });
}

function resetForm() {
    ['merchant', 'date', 'total'].forEach(id => {
        const el = $('#' + id);
        if (el) el.value = '';
    });
    setPreview(null);
    resetFileInput();
    enableSave(false);
    clearAnalysisStatus();

    // Ne pas modifier le statut si on est connecté ou si on a une erreur d'auth
    if (!currentUserEmail && !hasAuthError) {
        setStatus('<span style="color: #28a745; font-weight: bold;">●</span> Connecté.');
    }
}

function parseEuroToNumber(s) {
    if (!s) return null;
    const n = parseFloat(String(s).replace(/\s+/g, '').replace(/[€]/g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : null;
}

function validateCanSave() {
    const label = ($('#merchant')?.value || '').trim();
    const dateOk = !!$('#date')?.value;
    const totalOk = parseEuroToNumber($('#total')?.value) != null;
    const sheetOk = !!$('#sheetSelect')?.value;
    const who = document.querySelector('input[name="whoTop"]:checked')?.value || '';
    enableSave(!!label && dateOk && totalOk && sheetOk && !!currentUserEmail && !!who);
}

/* ========= Google ========= */
async function bootGoogle() {
    if (!CLIENT_ID) { setStatus('CLIENT_ID manquant'); }
    await waitFor(() => window.google?.accounts?.oauth2, 150, 10000).catch(() => { });
    if (window.google?.accounts?.oauth2 && CLIENT_ID) {
        tokenClient = google.accounts.oauth2.initTokenClient({
            client_id: CLIENT_ID, scope: 'openid email profile', prompt: '',
            callback: (resp) => {
                if (resp && !resp.error) {
                    accessToken = resp.access_token;
                    if (resp.expires_in) storeToken(accessToken, Number(resp.expires_in));
                }
            }
        });
        gisReady = true;
    }
    await waitFor(() => typeof gapi !== 'undefined', 150, 10000).catch(() => { });
    if (typeof gapi !== 'undefined') {
        await new Promise(r => gapi.load('client', r));
        await gapi.client.init({ discoveryDocs: ['https://www.googleapis.com/discovery/v1/apis/oauth2/v2/rest'] });
        gapiReady = true;
    }
}

async function autoSignIn() {
    try {
        // Essayer de se connecter avec un token en cache
        const cached = loadValidToken();
        if (cached) {
            accessToken = cached;
            await afterSignedIn();
            setStatus(`<span style="color: #28a745; font-weight: bold;">●</span> Connecté - ${currentUserEmail}`);
            clearAnalysisStatus();
            hideAuthStatus();
            hasAuthError = false;
            updateFileInputsState();
            return;
        }

        // Tentative de connexion silencieuse avec Google Identity
        if (gisReady && CLIENT_ID) {
            try {
                await ensureConnected(false); // false = pas de popup forcé
                await afterSignedIn();
                setStatus(`<span style="color: #28a745; font-weight: bold;">●</span> Connecté - ${currentUserEmail}`);
                clearAnalysisStatus();
                hideAuthStatus();
                hasAuthError = false;
                updateFileInputsState();
                return;
            } catch {
                // Continue vers l'UI de connexion
            }
        }

        needAuthUI('Veuillez vous connecter.');
    } catch (e) {
        if (isUnauthorizedEmailError(e)) handleAuthError(e); else needAuthUI('Veuillez vous connecter.');
    }
}

async function ensureConnected(forceConsent = false) {
    if (!gisReady) throw new Error('Google Identity non prêt');
    const cached = loadValidToken();
    if (cached) { accessToken = cached; return; }
    const hint = loadAccountHint();
    accessToken = null;
    await new Promise((resolve, reject) => {
        tokenClient.callback = (resp) => {
            if (resp?.error) return reject(resp);
            accessToken = resp.access_token;
            if (resp.expires_in) storeToken(accessToken, Number(resp.expires_in));
            resolve();
        };
        tokenClient.requestAccessToken({ prompt: forceConsent ? 'consent' : '', hint: hint || undefined });
    }).catch(async err => {
        const needConsent = err && (String(err.error).includes('consent') || String(err.error).includes('interaction'));
        if (!needConsent && !forceConsent) throw err;
        return new Promise((resolve2, reject2) => {
            tokenClient.callback = (resp2) => {
                if (resp2?.error) return reject2(resp2);
                accessToken = resp2.access_token;
                if (resp2.expires_in) storeToken(accessToken, Number(resp2.expires_in));
                resolve2();
            };
            tokenClient.requestAccessToken({ prompt: 'consent', hint: hint || undefined });
        });
    });
}

async function afterSignedIn() {
    let me;
    try { me = await api('/api/auth/me'); }
    catch (e) { handleAuthError(e); throw e; }

    currentUserEmail = me.email || null;
    if (currentUserEmail) storeAccountHint(currentUserEmail);

    try {
        if (gapiReady && accessToken) {
            gapi.client.setToken({ access_token: accessToken });
            const ui = await gapi.client.oauth2.userinfo.get();
            currentUserEmail = (ui.result?.email || currentUserEmail || '').toLowerCase();
            if (currentUserEmail) storeAccountHint(currentUserEmail);
        }
    } catch { }
    if (accessToken && !loadValidToken()) storeToken(accessToken, 55 * 60);

    if (currentUserEmail) {
        setStatus(`<span style="color: #28a745; font-weight: bold;">●</span> Connecté - ${currentUserEmail}`);
        clearAnalysisStatus();
        hideAuthButton();
        hideAuthStatus();
        showSwitchButton();
        hasAuthError = false; // Réinitialiser l'erreur d'auth
        updateFileInputsState(); // Réactiver les champs de fichiers
    }
    try {
        await populateSheets();
    } catch (e) {
        console.log('Erreur populateSheets:', e);
        // Ne pas modifier le statut en cas d'erreur
    }
    validateCanSave();
    syncPreviewHeight();
}

function isUnauthorizedEmailError(err) {
    const msg = String(err?.message || err || '');
    return /Email non autoris(é|e)/i.test(msg) || /403/.test(msg);
}
function handleAuthError(err, { showButton = true } = {}) {
    const raw = String(err?.message || '\'Erreur d\'authentification\'');
    signOutQuiet({ keepHint: false });
    const isUnauth = isUnauthorizedEmailError(err);
    setAuthStatus(isUnauth ? (raw || 'Email non autorisé') : 'Connexion requise.', false);

    // Afficher le point rouge pour les emails non autorisés
    if (isUnauth) {
        setStatus('<span style="color: #dc3545; font-weight: bold;">●</span> Email non autorisé');
        showAuthStatus();
        hasAuthError = true; // Marquer qu'on a une erreur d'auth
    }

    if (showButton) showAuthButton(); else hideAuthButton();
    hideSwitchButton();
    clearSheetsSelect('Feuilles indisponibles');
    enableSave(false);
    updateFileInputsState(); // Désactiver les champs de fichiers
}

/* ========= Sheets ========= */
async function populateSheets() {
    const res = await api('/api/sheets');
    const props = (res.sheets || []).sort((a, b) => (a.index || 0) - (b.index || 0));
    const sel = $('#sheetSelect');
    if (!sel) return;
    sel.innerHTML = props.map(p => `<option>${p.title}</option>`).join('');
    const preferred = res.default_sheet || DEFAULT_SHEET;
    const pre = props.find(p => p.title === preferred) || props.at(-1);
    if (pre) sel.value = pre.title;
}

function waitFor(test, every = 100, timeout = 10000) {
    return new Promise((resolve, reject) => {
        const t0 = Date.now();
        (function loop() {
            try { if (test()) return resolve(); } catch { }
            if (Date.now() - t0 > timeout) return reject(new Error('waitFor timeout'));
            setTimeout(loop, every);
        })();
    });
}

/* ========= Scan — mode simple ========= */
async function onImagePicked(e) {
    const file = e.target.files?.[0];
    if (!file) { setPreview(null); return; }

    // Vérifier l'autorisation avant de continuer
    if (!checkAuthorization('upload de photo')) {
        // Réinitialiser le champ file pour permettre une nouvelle sélection
        e.target.value = '';
        return;
    }

    enableSave(false);
    setAnalysisStatus('Analyse du ticket…', '#007bff');
    setPreview(URL.createObjectURL(file));
    try {
        if (!accessToken) await ensureConnected(false);
        await api('/api/auth/me');
        const b64 = await encodeForDocAI(file);
        const resp = await fetch(RECEIPT_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...(accessToken ? { 'Authorization': `Bearer ${accessToken}` } : {}) },
            body: JSON.stringify({ imageBase64: b64 })
        });
        const txt = await resp.text();
        let json = {};
        try { json = JSON.parse(txt); } catch { }
        if (!resp.ok || json.ok === false) throw new Error(json.error || `HTTP ${resp.status}`);
        if (json.supplier_name) $('#merchant').value = json.supplier_name;
        if (json.receipt_date) $('#date').value = json.receipt_date;
        if (json.total_amount != null) $('#total').value = Number(json.total_amount).toFixed(2).replace('.', ',');
        setAnalysisStatus('Reconnaissance OK. Vérifie puis « Enregistrer ».', '#28a745');
    } catch (err) {
        if (isUnauthorizedEmailError(err)) handleAuthError(err);
        else { setAnalysisStatus('Analyse indisponible — complète manuellement.', '#dc3545'); }
    } finally {
        const fi = document.getElementById('file');
        if (fi) { try { fi.value = ''; } catch { } }
        validateCanSave();
        syncPreviewHeight();
    }
}
function fileToBase64NoPrefix(file) {
    return new Promise((resolve, reject) => {
        const r = new FileReader();
        r.onload = () => {
            const s = String(r.result || '');
            resolve(s.includes(',') ? s.split(',')[1] : s);
        };
        r.onerror = reject;
        r.readAsDataURL(file);
    });
}

/* ========= Écriture simple ========= */
async function saveToSheet() {
    // Vérifier l'autorisation avant de continuer
    if (!checkAuthorization('enregistrement')) {
        return;
    }

    try {
        if (!accessToken) await ensureConnected(false);
        await api('/api/auth/me');
        const sheetName = $('#sheetSelect')?.value || DEFAULT_SHEET;
        const who = document.querySelector('input[name="whoTop"]:checked')?.value || '';
        const supplier = ($('#merchant').value || '').trim();
        const dateISO = $('#date').value;
        const totalNum = parseEuroToNumber($('#total').value);
        if (!supplier || !dateISO || totalNum == null || !sheetName || !who) throw new Error('Champs incomplets');
        setAnalysisStatus('Écriture…', '#007bff');
        const res = await api('/api/sheets/write', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sheetName, who, supplier, dateISO, total: totalNum })
        });
        if (!res.ok) throw new Error(res.error || 'Échec écriture');
        setAnalysisStatus(`Enregistré ✔ (onglet « ${sheetName} », ${who})`, '#28a745');
        enableSave(false);
    } catch (e) {
        if (isUnauthorizedEmailError(e)) handleAuthError(e);
        else { setAnalysisStatus('Erreur : ' + (e.message || e), '#dc3545'); }
    }
}

/* ========= Déconnexion ========= */
async function revokeAccessToken(token) {
    if (!token) return;
    try {
        await fetch('https://oauth2.googleapis.com/revoke?token=' + encodeURIComponent(token), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        });
    } catch { }
}
function signOutQuiet({ keepHint = true } = {}) {
    if (accessToken) revokeAccessToken(accessToken);
    clearStoredToken();
    if (!keepHint) { try { localStorage.removeItem(LS_ACCOUNT_HINT); } catch { } }
    accessToken = null;
    currentUserEmail = null;
    hideSwitchButton();
    clearSheetsSelect('Feuilles indisponibles');
    enableSave(false);
}
async function switchAccount() {
    try {
        // On enlève le token courant et on force le choix d’un autre compte
        signOutQuiet({ keepHint: false });
        hideAuthButton();
        hideSwitchButton();
        clearSheetsSelect('Changement de compte…');
        setAuthStatus('Changement de compte…', null);
        setStatus('<span style="color: #ff8c00; font-weight: bold;">●</span> Changement de compte...');

        await new Promise((resolve, reject) => {
            tokenClient.callback = (resp) => {
                if (resp?.error) return reject(resp);
                accessToken = resp.access_token;
                if (resp.expires_in) storeToken(accessToken, Number(resp.expires_in));
                resolve();
            };
            tokenClient.requestAccessToken({ prompt: 'select_account' });
        });

        await afterSignedIn();
        setStatus('<span style="color: #28a745; font-weight: bold;">●</span> Connecté (compte changé)');
        clearAnalysisStatus();
        hideAuthButton();
        hideAuthStatus();
        showSwitchButton();
        hasAuthError = false;
        updateFileInputsState();
    } catch (e) {
        if (isUnauthorizedEmailError(e)) { handleAuthError(e, { showButton: true }); }
        else { needAuthUI('Veuillez vous connecter.'); }
    }
}
// function signOut({ keepHint = true } = {}) {
//     signOutQuiet({ keepHint });
//     needAuthUI('Déconnecté');
// }

/* ========================================================================
   MODE MULTIPLE
   ===================================================================== */
let multiItems = []; // { id, file, thumbUrl, status, supplier, dateISO, total }

/* Grille responsive */
function initMultiUIGrid() {
    // Le CSS est dans app.css
}

/* Barre d’actions flottante */
let _fabMounted = false;
let _fabSpacerEl = null;
let _fabAddLabel = null;

function setupFloatingActions() {
    if (_fabMounted) return;

    const fileInput = document.getElementById('multiFiles');
    if (!fileInput) {
        return;
    }

    const wrap = document.createElement('div');
    wrap.className = 'fab-wrap';
    wrap.id = 'floatingActions';
    wrap.hidden = true;

    // ➕ Ajouter (label lié à l'input)
    const addLabel = document.createElement('label');
    addLabel.setAttribute('for', 'multiFiles');
    addLabel.className = 'fab-btn fab-add';
    addLabel.title = 'Ajouter des photos';
    addLabel.setAttribute('aria-label', 'Ajouter des photos');
    addLabel.innerHTML = '<span class="fab-icon" aria-hidden="true">+</span>';
    _fabAddLabel = addLabel;

    // 💾 Enregistrer (nouveau bouton)
    const saveBtn = document.createElement('button');
    saveBtn.id = 'btnBatchSave';
    saveBtn.className = 'fab-btn fab-save';
    saveBtn.title = 'Enregistrer tout';
    saveBtn.setAttribute('aria-label', 'Enregistrer tout');
    saveBtn.innerHTML = '<span class="fab-icon" aria-hidden="true">💾</span>';
    saveBtn.disabled = true;

    // 🗑️ Vider (nouveau bouton)
    const resetBtn = document.createElement('button');
    resetBtn.id = 'btnBatchReset';
    resetBtn.className = 'fab-btn fab-clear';
    resetBtn.title = 'Vider';
    resetBtn.setAttribute('aria-label', 'Vider');
    resetBtn.innerHTML = '<span class="fab-icon" aria-hidden="true">🗑️</span>';

    // Ajouter les event listeners
    saveBtn.addEventListener('click', runBatchSave);
    resetBtn.addEventListener('click', clearMulti);

    // ordre : ajouter / enregistrer / vider
    wrap.appendChild(addLabel);
    wrap.appendChild(saveBtn);
    wrap.appendChild(resetBtn);
    document.body.appendChild(wrap);

    // Espace pour que les cards ne passent pas sous les FAB
    const grid = document.getElementById('multiCards');
    if (grid && !_fabSpacerEl) {
        _fabSpacerEl = document.createElement('div');
        _fabSpacerEl.className = 'fab-spacer';
        grid.after(_fabSpacerEl);
    }
    const resizeSpacer = () => {
        if (!_fabSpacerEl) return;
        const h = wrap.getBoundingClientRect().height || 76;
        _fabSpacerEl.style.height = `${Math.ceil(h + 12)}px`;
    };
    resizeSpacer();
    window.addEventListener('resize', resizeSpacer);
    new ResizeObserver(resizeSpacer).observe(wrap);

    _fabMounted = true;
}
function showFloatingActions(show) {
    const el = document.getElementById('floatingActions');
    if (!el) return;
    el.hidden = !show;
}

/* Empty state (dropzone/guide) */
function renderMultiEmptyIfNeeded() {
    const grid = document.getElementById('multiCards');
    if (!grid) return;
    let empty = document.getElementById('multiEmpty');

    if (multiItems.length === 0) {
        if (!empty) {
            empty = document.createElement('div');
            empty.id = 'multiEmpty';
            empty.innerHTML = `
        <div class="inner">
          <div class="emoji">🧾</div>
          <div class="hint">Ajoute jusqu’à ${MAX_UPLOADS} tickets en une fois.<br>Conseil : vise bien le montant total sur la photo.</div>
          <label class="btn btn-warning pick" for="multiFiles">📷 Ajouter des photos</label>
        </div>`;
            grid.before(empty);
        }
    } else {
        if (empty) empty.remove();
    }
}

/** Bascule entre modes */
function switchMode(mode) {
    const single = $('#singleSection');
    const multi = $('#multiSection');
    const toggle = $('#modeToggle');

    if (mode === 'multi') {
        if (toggle) toggle.checked = true;
        if (single) single.style.display = 'none';
        if (multi) multi.style.display = '';
        resetForm();
        showFloatingActions(true);
        renderMultiEmptyIfNeeded();
    } else {
        if (toggle) toggle.checked = false;
        if (single) single.style.display = '';
        if (multi) multi.style.display = 'none';
        clearMulti();
        showFloatingActions(false);
    }
}

/** Ajout de fichiers (multiple) */
async function onMultiFilesPicked(e) {
    const incoming = Array.from(e.target.files || []);
    if (!incoming.length) return;

    // Vérifier l'autorisation avant de continuer
    if (!checkAuthorization('upload multiple de photos')) {
        // Réinitialiser le champ file pour permettre une nouvelle sélection
        try { e.target.value = ''; } catch { }
        return;
    }

    try { e.target.value = ''; } catch { }

    const remaining = Math.max(0, MAX_UPLOADS - multiItems.length);
    if (remaining <= 0) {
        showToast(`Limite atteinte : ${MAX_UPLOADS} tickets max.`);
        setStatus(`Limite atteinte (${MAX_UPLOADS} tickets).`);
        updateBatchButtons();
        return;
    }

    const files = incoming.slice(0, remaining);
    if (files.length < incoming.length) {
        showToast(`Seuls ${files.length}/${incoming.length} ajoutés (max ${MAX_UPLOADS}).`);
        setStatus(`Seuls ${files.length}/${incoming.length} ajoutés (max ${MAX_UPLOADS}).`);
    }

    for (const f of files) {
        const id = 'card_' + Math.random().toString(36).slice(2);
        const thumbUrl = URL.createObjectURL(f);
        multiItems.push({ id, file: f, thumbUrl, status: 'En attente…', supplier: '', dateISO: '', total: null });
        renderCard({ id, thumbUrl, status: 'En attente…' });
    }
    renderMultiEmptyIfNeeded();
    updateBatchButtons();
    runBatchScan().catch(() => {
        // Error handling is done in runBatchScan
    });
}

/** Rendu carte */
function renderCard(item) {
    const wrap = $('#multiCards'); if (!wrap) return;
    let card = document.getElementById(item.id);

    const status = String(item.status || '').toUpperCase();
    const statusClass =
        status.startsWith('ANALY') ? 'status-analyse' :
            status === 'OK' ? 'status-ok' :
                status ? 'status-error' : '';

    const html = `
    <div class="card bg-dark text-white p-2" style="width:100%;max-width:100%;box-sizing:border-box;">
      <div class="text-center mb-2">
        <img src="${item.thumbUrl}" alt="" style="max-width:100%;height:auto;object-fit:contain;">
      </div>

      <div class="status-badge ${statusClass}">${item.status || ''}</div>

      <div class="mb-2">
        <label class="form-label small">Intitulé (enseigne)</label>
        <input type="text" class="form-control form-control-sm" data-k="supplier" value="${item.supplier || ''}">
      </div>
      <div class="mb-2">
        <label class="form-label small">Date</label>
        <input type="date" class="form-control form-control-sm" data-k="dateISO" value="${item.dateISO || ''}">
      </div>
      <div class="mb-2">
        <label class="form-label small">Total (€)</label>
        <input type="text" inputmode="decimal" class="form-control form-control-sm" data-k="total" value="${item.total != null ? String(item.total) : ''}">
      </div>
    </div>
  `;
    if (!card) { card = document.createElement('div'); card.id = item.id; wrap.appendChild(card); }
    card.innerHTML = html;

    card.querySelectorAll('input[data-k]').forEach(inp => {
        inp.addEventListener('input', () => {
            const k = inp.getAttribute('data-k');
            const it = multiItems.find(x => x.id === item.id); if (!it) return;
            if (k === 'total') it.total = parseFloat(String(inp.value).replace(',', '.'));
            else it[k] = inp.value;
        });
    });
}

/** Vider le mode multiple */
function clearMulti() {
    multiItems.forEach(it => { if (it.thumbUrl) URL.revokeObjectURL(it.thumbUrl); });
    multiItems = [];
    const wrap = $('#multiCards');
    if (wrap) wrap.innerHTML = '';
    renderMultiEmptyIfNeeded();
    updateBatchButtons();
}

/** Utils image */
function loadImageURL(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve({ img, url });
        img.onerror = reject;
        img.src = url;
    });
}
async function compressToBase64(file, maxLongSide = 2400, quality = 0.96, mime = 'image/jpeg') {
    const { img, url } = await loadImageURL(URL.createObjectURL(file));
    const longSide = Math.max(img.naturalWidth || img.width, img.naturalHeight || img.height);
    const ratio = Math.min(1, maxLongSide / longSide);
    const w = Math.round((img.naturalWidth || img.width) * ratio);
    const h = Math.round((img.naturalHeight || img.height) * ratio);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(img, 0, 0, w, h);
    const dataUrl = canvas.toDataURL(mime, quality);
    URL.revokeObjectURL(url);
    return dataUrl.split(',')[1];
}

/** Concurrence */
function runWithConcurrency(items, worker, concurrency = 3) {
    return new Promise((resolve) => {
        let i = 0, running = 0;
        const results = [];
        function next() {
            while (running < concurrency && i < items.length) {
                const idx = i++;
                running++;
                Promise.resolve(worker(items[idx], idx))
                    .then(r => results[idx] = r)
                    .catch(e => results[idx] = { ok: false, error: String(e) })
                    .finally(() => {
                        running--;
                        if (results.length === items.length && !results.includes(undefined)) resolve(results);
                        else next();
                    });
            }
        }
        next();
    });
}

/** Analyse multi */
async function runBatchScan() {
    if (!multiItems.length) return;
    $('#btnBatchScan') && ($('#btnBatchScan').disabled = true);
    $('#btnBatchSave') && ($('#btnBatchSave').disabled = true);

    try {
        if (!accessToken) await ensureConnected(false);
        await api('/api/auth/me');

        const worker = async (it) => {
            const card = document.getElementById(it.id);
            it.status = 'Analyse…';
            if (card) renderCard(it);

            const b64 = await encodeForDocAI(it.file);
            const resp = await fetch(`${BACK_BASE}/api/scan`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${accessToken}` },
                body: JSON.stringify({ imageBase64: b64 })
            });
            const json = await resp.json();
            if (!resp.ok || json.ok === false) throw new Error(json.error || `HTTP ${resp.status}`);
            it.supplier = json.supplier_name || '';
            it.dateISO = json.receipt_date || '';
            it.total = (json.total_amount != null) ? Number(json.total_amount) : null;
            it.status = 'OK';
            renderCard(it);
            return { ok: true };
        };

        await runWithConcurrency(multiItems, worker, 3);

    } catch {
        multiItems.forEach(it => {
            if (it.status !== 'OK') {
                it.status = 'Erreur';
                renderCard(it);
            }
        });
    } finally {
        updateBatchButtons();
    }
}

/** Enregistrer tout */
async function runBatchSave() {
    // Vérifier l'autorisation avant de continuer
    if (!checkAuthorization('enregistrement multiple')) {
        return;
    }

    try {
        if (!accessToken) await ensureConnected(false);
        await api('/api/auth/me');

        const sheetName = $('#sheetSelect')?.value || DEFAULT_SHEET;
        const who = document.querySelector('input[name="whoTop"]:checked')?.value || '';
        if (!sheetName || !who) throw new Error('Choisissez la feuille et “Qui scanne ?”.');

        const rows = multiItems
            .map(it => ({
                supplier: (it.supplier || '').trim(),
                dateISO: it.dateISO || '',
                total: typeof it.total === 'number' ? it.total : NaN,
                id: it.id
            }))
            .filter(r => r.supplier && r.dateISO && Number.isFinite(r.total));

        if (!rows.length) throw new Error('Aucune carte complète à enregistrer.');

        const writer = async (r) => {
            const res = await api('/api/sheets/write', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sheetName, who, supplier: r.supplier, dateISO: r.dateISO, total: r.total })
            });

            const card = document.getElementById(r.id);
            if (card) {
                const badge = card.querySelector('.status-badge');
                if (res.ok) {
                    if (badge) {
                        badge.textContent = 'Enregistré ✔';
                        badge.classList.remove('status-analyse', 'status-error');
                        badge.classList.add('status-ok');
                    }
                } else {
                    if (badge) {
                        badge.textContent = 'Erreur écriture';
                        badge.classList.remove('status-analyse', 'status-ok');
                        badge.classList.add('status-error');
                    }
                }
            }
            return res.ok;
        };

        await runWithConcurrency(rows, writer, 3);
        setAnalysisStatus(`Enregistrement terminé (${rows.length} lignes).`, '#28a745');

    } catch (e) {
        setAnalysisStatus('Erreur enregistrement multiple : ' + (e.message || e), '#dc3545');
    }
}

/** Boutons multi */
function updateBatchButtons() {
    const any = multiItems.length > 0;
    const allAnalysed = any && multiItems.every(it => it.status === 'OK');
    const saveBtn = document.getElementById('btnBatchSave');
    if (saveBtn) saveBtn.disabled = !allAnalysed;

    const addInp = $('#multiFiles');
    if (addInp) {
        const disableAdd = (multiItems.length >= MAX_UPLOADS);
        addInp.disabled = disableAdd;
        if (_fabAddLabel) {
            _fabAddLabel.classList.toggle('is-disabled', disableAdd);
            _fabAddLabel.title = disableAdd
                ? `Limite atteinte (${MAX_UPLOADS})`
                : 'Ajouter des photos';
        }
    }
}
