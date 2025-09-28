=== VSOA Widget (Refactor) ===
Contributors: vsoa-team
Requires at least: 6.2
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Refonte modulaire du plugin VSOA : import/export JSON, obfuscation côté serveur, stats agrégées et gestion des prix.

== Description ==

* Import/export JSON conservant exactement l'ancien format (tableau de lignes).
* API REST sécurisée (`manage_options`) pour CRUD, statistiques et prix.
* Front-end minimal : attributs `data-*` uniquement, résolution serveur par jetons signés.
* Stats agrégées 24h/7j/30j via transients + cron, respect RGPD.
* Gestion des prix avec audit log.

== Installation ==

1. Uploadez le dossier du plugin dans `wp-content/plugins`.
2. Activez le plugin via "Extensions".
3. Accédez au menu "VSOA Widget" pour importer votre JSON historique.

== Changelog ==

= 2.0.0 =
* Réarchitecture complète (PSR-4) et séparation logique front/back.
* Ajout de l'API REST namespacée `vsoa/v1` (CRUD, import/export, stats, pricing).
* Documentation sécurité (obfuscation, stats, pricing) et pipeline CI.
