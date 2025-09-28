# PRICING

## Objectifs

- Stocker les prix canoniques, promotions et remises sans modifier le format JSON historique.
- Fournir des API sécurisées pour la mise à jour des prix par ligne.
- Conserver un historique (audit log) des modifications pour conformité commerciale.

## Modèle de données

- Les lignes JSON existantes contiennent `price`, `currency`, `label`, etc. Aucun champ obligatoire supplémentaire n'est ajouté.
- Les enrichissements sont stockés dans un champ optionnel `metadata` (ex: `{ "pricing": { ... } }`).
- Audit log stocké dans une table personnalisée `{$wpdb->prefix}vsoa_price_log` :

```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
row_id VARCHAR(190) NOT NULL,
old_price JSON NULL,
new_price JSON NOT NULL,
changed_by BIGINT UNSIGNED NULL,
changed_at DATETIME NOT NULL,
INDEX (row_id)
```

## API REST

- `GET /wp-json/vsoa/v1/pricing/{id}` -> retourne le prix courant + historique résumé (dernier changement).
- `PUT /wp-json/vsoa/v1/pricing/{id}` -> met à jour le prix :
  - Vérification `current_user_can( 'manage_options' )`.
  - Validation du format (montant numérique, devise ISO 4217, dates promo).
  - Écriture transactionnelle : mise à jour Storage JSON + insertion audit log.
- `GET /wp-json/vsoa/v1/pricing` -> liste paginée des prix.

## Gestion des promotions

- Support des paliers : `tiers` (array) dans `metadata.pricing.tiers`.
- Remises : `discounts` avec période `start_at` / `end_at`, pourcentages ou montants fixes.
- Prix dynamiques : possibilité de définir des règles via filtres `vsoa_pricing_rules`.

## Tests

- `PricingManagerTest` :
  - Lecture de prix existant (fallback JSON).
  - Ajout de promotion -> audit log créé.
  - Expiration de promo -> suppression automatique via cron.

## Procédure d'audit

- Export mensuel de l'audit log (CSV) pour la comptabilité.
- Vérification de la cohérence des montants (double entrée).
- Revue des accès (capabilities) à chaque release majeure.
