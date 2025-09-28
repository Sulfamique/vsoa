# STATS

## Objectifs

- Mesurer l'engagement (clics, conversions proxy) sans stocker de données personnelles.
- Offrir un reporting 24h / 7 jours / 30 jours avec possibilité de remise à zéro.

## Collecte

- `Vsoa\Stats\StatsCollector::track()` est appelé depuis la redirection serveur (sécurisée) lorsque l'utilisateur clique.
- Les événements sont stockés en mémoire via transient `vsoa_stats_queue` (structure FIFO) pour limiter les écritures.
- Un hook WP-Cron (`vsoa_stats_flush`) agrège les événements en batch vers une option/ table dédiée.
- Les identifiants (row_id, data_value) sont pseudonymisés via `hash_hmac('sha256', $row_id, SECRET_KEY)`.

## Agrégation

- Trois fenêtres temporelles (24h, 7j, 30j) sont gérées via timestamps.
- Les données agrégées sont stockées sous forme :

```php
[
  'offer-1' => [
    '24h' => 12,
    '7d' => 84,
    '30d' => 280
  ]
]
```

- Un job de maintenance supprime les événements expirés pour respecter la durée de rétention (30 jours par défaut).

## RGPD

- Pas d'adresse IP ni d'agent utilisateur persisté.
- Opt-out via filtre `apply_filters( 'vsoa_stats_enabled', true )`.
- Documentation des traitements dans le registre RGPD. Durée de conservation configurable.

## API & Permissions

- Endpoints REST `GET /wp-json/vsoa/v1/stats` (admin ou jeton service) -> retourne agrégats.
- `POST /wp-json/vsoa/v1/stats/reset` -> remet à zéro toutes les statistiques, opération transactionnelle.

## Tests & Audit

- Tests unitaires : `StatsCollectorTest` (file d'attente), `StatsAggregatorTest` (agrégation / purge).
- Tests d'intégration : simulation de clics, exécution du cron, vérification export.
- Audit trimestriel : vérifier anonymisation, purge automatique, respect opt-out.
