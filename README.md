# VSOA Widget — Refonte modulaire

Refonte complète du plugin VSOA avec séparation stricte des responsabilités (Core, Storage, API, Admin, Frontend, Obfuscation, Stats, Pricing).

- **Compatibilité JSON** : import/export conserve exactement l'ancien format de tableau JSON.
- **Front-end** : aucun lien ni URL rendu, uniquement des attributs `data-*`. La résolution s'opère via des jetons signés côté serveur.
- **Sécurité** : vérifications de capacité `manage_options`, nonces, assainissement systématique et redirections sécurisées.
- **Stats & Prix** : collecte différée via transients/cron, historique des prix avec audit log (à implémenter dans `PricingManager`).
- **CI** : GitHub Actions exécutant PHPCS (WPCS + VIP), PHPUnit, PHPStan et audit de sécurité Composer.

## Structure

```text
my-plugin/
├─ plugin-main.php
├─ composer.json
├─ src/
│  ├─ Core\Loader.php
│  ├─ Storage\Storage.php
│  ├─ API\RestController.php
│  ├─ Admin\Controller.php
│  ├─ Frontend\Enqueue.php
│  ├─ Obfuscation\Obfuscator.php
│  ├─ Stats\StatsCollector.php
│  └─ Pricing\PricingManager.php
├─ docs/
│  ├─ OBFUSCATION.md
│  ├─ STATS.md
│  └─ PRICING.md
├─ tests/
│  ├─ bootstrap.php
│  └─ Unit/
│     ├─ StorageTest.php
│     └─ RestControllerTest.php
└─ .github/workflows/ci.yml
```

## Scripts Composer

- `composer test` : exécute PHPUnit.
- `composer lint` : exécute PHPCS selon `.phpcs.xml.dist`.
- `composer stan` : exécute PHPStan niveau 6 (configuration dans `phpstan.neon`).

## Exemple JSON (compatible)

```json
[
  {
    "id": "offer-1",
    "title": "Offre Starter",
    "price": "19.99",
    "currency": "EUR"
  },
  {
    "id": "offer-2",
    "title": "Offre Premium",
    "price": "49.99",
    "currency": "EUR"
  }
]
```

## Prérequis

- PHP >= 8.1
- WordPress >= 6.2

## Développement

1. `composer install`
2. `npm install` *(optionnel si vous ajoutez un build JS)*
3. `composer test`, `composer lint`, `composer stan`

## QA Checklist

- [ ] Import JSON ancien format -> données persistées sans transformation.
- [ ] Export JSON identique (tests `StorageTest`).
- [ ] Frontend (`[vsoa_widget]`) sans URL (`href`, `http`, `https`).
- [ ] REST API protégée (tests `RestControllerTest`).
- [ ] CI GitHub Actions verte.
