# OBFUSCATION

## Objectifs

- Empêcher l'exposition des URLs d'affiliation côté client.
- Limiter les possibilités de rétro-ingénierie sans prétendre fournir une sécurité absolue.

## Approche recommandée

1. **Jetons signés courte durée** : chaque résolution d'offre génère un token opaque (`uuid` + signature HMAC) stocké en transient pour 60 secondes maximum. Le client ne manipule jamais l'URL réelle.
2. **Redirection serveur** : route `template_redirect` récupère le token, vérifie signature + expiration, effectue `wp_safe_redirect()` côté serveur.
3. **Minification/obfuscation JS** : build pipeline (Webpack/Rollup) avec minification agressive + option `js-obfuscator` légère. Les sources non minifiées restent hors distribution (repository privé).
4. **Tokens à usage unique** : chaque token est supprimé après usage pour empêcher la réutilisation.
5. **Validation serveur** : toute action critique (import, CRUD, resolve) est validée via `current_user_can()`, nonce et, si besoin, authentification par jeton JWT côté serveur.

## Limites et risques

- L'obfuscation JS ne constitue **pas** une barrière de sécurité : un attaquant peut analyser le trafic réseau ou décompiler le code.
- Les tokens courts nécessitent synchronisation horaire côté serveur ; prévoir tolérance (±30 s) et invalidation automatique.
- Les redirections doivent utiliser `wp_safe_redirect()` pour éviter les open redirect.
- RGPD : ne pas inclure d'informations personnelles dans les tokens ou charges utiles.

## Audit & tests

- Vérifier que les pages front n'exposent aucun `http`, `https`, `href` via `wp_strip_all_tags()` ou tests E2E.
- Tests unitaires sur la génération/validation de token (`ObfuscatorTest` à ajouter) : expiration, signature invalide, usage unique.
- Audit semestriel : revue du code obfuscation, renouvellement des clés de signature.

## Outils

- `openssl_random_pseudo_bytes()` pour générer des clés de signature stockées côté serveur (option/constante).
- `hash_hmac()` pour signer les tokens.
- Action Scheduler / WP-Cron pour nettoyer les tokens expirés si stockage base de données.
