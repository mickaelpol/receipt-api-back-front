# Décisions de Sécurité Réseau - Scan2Sheet

## Vue d'ensemble
Ce document décrit les décisions prises concernant la posture de sécurité réseau pour Scan2Sheet, en particulier pour le déploiement Cloud Run.

## Contexte
- **Application** : Scan2Sheet - Scanner de tickets vers Google Sheets
- **Plateforme** : Google Cloud Run (europe-west9)
- **Usage** : Service web public accessible pour scanner des reçus
- **Intégrations** : Google Document AI, Google Sheets, Google OAuth

## Décisions Prises

### 1. Ingress Configuration
**Décision** : Service public accessible (all traffic)

**Justification** :
- L'application est un service web destiné à être utilisé par des utilisateurs finaux
- Nécessité d'accès depuis des navigateurs web
- Pas de réseau privé ou de proxy requis pour l'usage actuel
- Google Cloud Run gère automatiquement le HTTPS et la sécurité de base

**Configuration actuelle** :
```bash
--allow-unauthenticated
```

**Alternatives considérées** :
- **VPC Connector + Load Balancer** : Trop complexe pour l'usage actuel
- **Cloud Armor** : Pourrait être ajouté si nécessaire pour la protection DDoS
- **API Gateway** : Pas nécessaire pour une SPA simple

### 2. VPC Connector
**Décision** : Pas de VPC connector requis actuellement

**Justification** :
- L'application n'accède qu'aux services Google Cloud publics (Document AI, Sheets)
- Pas de ressources privées (bases de données internes, services privés)
- Toutes les intégrations passent par des APIs Google publiques
- Simplifie la configuration et réduit les coûts

**Configuration actuelle** :
```bash
# Pas de --vpc-connector spécifié
```

**Cas d'usage futurs qui pourraient nécessiter un VPC** :
- Base de données Cloud SQL privée
- Services internes non exposés
- Connexions vers d'autres VPCs
- Audit de conformité strict

### 3. Firewall et Sécurité Réseau
**Décision** : Utilisation des contrôles Cloud Run natifs

**Configuration** :
- **HTTPS forcé** : Automatique via Cloud Run
- **CORS** : Configuré dans l'application pour les domaines autorisés
- **CSP** : Content Security Policy configurée dans l'application
- **Rate Limiting** : Géré par Cloud Run (concurrency=1)

**Protections en place** :
```apache
# CSP Headers dans l'application
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://accounts.google.com https://apis.google.com https://*.gstatic.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self'; connect-src 'self' https://oauth2.googleapis.com https://openidconnect.googleapis.com https://accounts.googleapis.com https://www.googleapis.com; frame-src 'self' https://accounts.google.com https://apis.google.com https://content.googleapis.com;
```

### 4. Authentification et Autorisation
**Décision** : OAuth Google avec validation côté serveur

**Configuration** :
- **Authentification** : Google OAuth 2.0
- **Autorisation** : Validation des emails autorisés côté serveur
- **Sessions** : Tokens JWT avec expiration
- **API Protection** : Validation des tokens sur chaque requête

### 5. Monitoring et Audit
**Décision** : Logs Cloud Logging + monitoring de base

**Configuration** :
- **Logs** : Cloud Logging avec rétention configurée
- **Monitoring** : Métriques Cloud Run (latence, erreurs, trafic)
- **Alertes** : Notification par email en cas d'échec de déploiement
- **Audit** : Logs d'audit Google Cloud Platform

## Recommandations Futures

### Phase 1 (Court terme)
1. **Cloud Armor** : Ajouter une protection DDoS si le trafic augmente
2. **Monitoring avancé** : Alertes sur les métriques de performance
3. **Backup automatique** : Sauvegarde des configurations

### Phase 2 (Moyen terme)
1. **VPC Connector** : Si des services privés sont ajoutés
2. **Private Google Access** : Pour optimiser les appels aux APIs Google
3. **WAF** : Web Application Firewall pour une protection avancée

### Phase 3 (Long terme)
1. **Zero Trust** : Architecture de sécurité Zero Trust
2. **Compliance** : Audit de conformité (RGPD, SOC2)
3. **Multi-région** : Déploiement multi-région avec failover

## Coûts de Sécurité
- **Cloud Run** : ~5€/mois (min instances)
- **Cloud Logging** : ~1€/mois (logs de base)
- **Secret Manager** : ~0.1€/mois (secrets de base)
- **Total estimé** : ~6€/mois pour la sécurité de base

## Checklist de Sécurité
- [x] HTTPS forcé via Cloud Run
- [x] CSP configurée
- [x] Secrets dans Secret Manager
- [x] Permissions IAM least privilege
- [x] Logs d'audit activés
- [x] Authentification OAuth
- [x] Validation des emails autorisés
- [ ] Cloud Armor (optionnel)
- [ ] Monitoring avancé (optionnel)
- [ ] Backup automatique (optionnel)

## Références
- [Google Cloud Run Security](https://cloud.google.com/run/docs/security)
- [Cloud Run VPC Connector](https://cloud.google.com/run/docs/configuring/vpc)
- [Cloud Armor](https://cloud.google.com/armor)
- [Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
