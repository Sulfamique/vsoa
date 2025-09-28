<?php
/**
 * Gestion du stockage des lignes JSON et migrations.
 *
 * @package Vsoa\Storage
 */

namespace Vsoa\Storage;

use wpdb;
use WP_Error;

/**
 * Stockage principal en option WP (compat JSON) + audit pricing.
 */
class Storage {
public const OPTION_KEY = 'vsoa_rows_json';
public const VERSION_OPTION = 'vsoa_storage_version';
public const PRICE_LOG_TABLE = 'vsoa_price_log';

/**
 * Installe ou met à jour la structure.
 */
public function install(): void {
$this->maybe_create_option();
$this->maybe_install_tables();
update_option( self::VERSION_OPTION, '1.0.0', false );
}

/**
 * Nettoie les données lors de la désinstallation.
 */
public function uninstall(): void {
delete_option( self::OPTION_KEY );
delete_option( self::VERSION_OPTION );

global $wpdb;
$table = $this->get_price_log_table();

if ( $this->table_exists( $table ) ) {
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
}

/**
 * Retourne toutes les lignes JSON (format identique à l'historique).
 */
public function get_rows(): array {
$json = (string) get_option( self::OPTION_KEY, '[]' );
$data = json_decode( $json, true );

return is_array( $data ) ? array_values( $data ) : [];
}

/**
 * Export brut du JSON.
 */
public function export_json(): string {
return (string) get_option( self::OPTION_KEY, '[]' );
}

/**
 * Remplace l'intégralité du JSON (reset total).
 *
 * @param array<int, array<string, mixed>> $rows Rows.
 */
public function replace_all( array $rows ): void {
update_option( self::OPTION_KEY, wp_json_encode( array_values( $rows ) ) );
}

/**
 * Ajoute ou met à jour une ligne.
 *
 * @param string                       $id      Identifiant.
 * @param array<string, mixed>         $payload Données.
 */
public function upsert_row( string $id, array $payload ): void {
$rows  = $this->get_rows();
$found = false;

foreach ( $rows as $index => $row ) {
if ( isset( $row['id'] ) && (string) $row['id'] === $id ) {
$rows[ $index ] = $this->merge_row( $row, $payload, $id );
$found          = true;
break;
}
}

if ( ! $found ) {
$payload['id'] = $id;
$rows[]        = $this->sanitize_row( $payload );
}

$this->replace_all( $rows );
}

/**
 * Supprime une ligne.
 */
public function delete_row( string $id ): void {
$rows = array_filter(
$this->get_rows(),
static fn ( $row ) => ! ( isset( $row['id'] ) && (string) $row['id'] === $id )
);

$this->replace_all( array_values( $rows ) );
}

/**
 * Import JSON : ajoute/maj/supprime selon options.
 *
 * @param string $json JSON reçu.
 * @param string $mode Mode (replace|merge|delete).
 *
 * @return array<string, int>
 */
public function import_json( string $json, string $mode = 'replace' ): array {
$data = json_decode( $json, true );

if ( ! is_array( $data ) ) {
throw new \InvalidArgumentException( 'JSON invalide (tableau attendu).' );
}

if ( 'replace' === $mode ) {
$this->replace_all( $this->sanitize_rows( $data ) );

return [
'inserted' => count( $data ),
'updated'  => 0,
'deleted'  => 0,
];
}

$inserted = 0;
$updated  = 0;
$deleted  = 0;

if ( 'delete' === $mode ) {
foreach ( $data as $row ) {
if ( isset( $row['id'] ) ) {
$this->delete_row( (string) $row['id'] );
$deleted++;
}
}
} else {
foreach ( $data as $row ) {
if ( empty( $row['id'] ) ) {
continue;
}

$before = $this->find_row( (string) $row['id'] );
$this->upsert_row( (string) $row['id'], (array) $row );

if ( null === $before ) {
$inserted++;
} else {
$updated++;
}
}
}

return [
'inserted' => $inserted,
'updated'  => $updated,
'deleted'  => $deleted,
];
}

/**
 * Recherche une ligne par ID.
 */
public function find_row( string $id ): ?array {
foreach ( $this->get_rows() as $row ) {
if ( isset( $row['id'] ) && (string) $row['id'] === $id ) {
return $row;
}
}

return null;
}

/**
 * Fusionne et nettoie les données.
 */
private function merge_row( array $existing, array $payload, string $id ): array {
$payload['id'] = $id;

return $this->sanitize_row( array_merge( $existing, $payload ) );
}

/**
 * Sanitize une ligne.
 */
private function sanitize_row( array $row ): array {
foreach ( $row as $key => $value ) {
if ( is_string( $value ) ) {
$row[ $key ] = sanitize_text_field( $value );
} elseif ( is_array( $value ) ) {
$row[ $key ] = $this->sanitize_row( $value );
}
}

return $row;
}

/**
 * Sanitize une liste de lignes.
 *
 * @param array<int, array<string, mixed>> $rows Rows.
 */
private function sanitize_rows( array $rows ): array {
return array_map( fn ( $row ) => $this->sanitize_row( (array) $row ), $rows );
}

/**
 * Crée l'option de stockage si nécessaire.
 */
private function maybe_create_option(): void {
if ( null === get_option( self::OPTION_KEY, null ) ) {
add_option( self::OPTION_KEY, '[]', '', false );
}
}

/**
 * Crée la table d'audit de prix si besoin.
 */
private function maybe_install_tables(): void {
global $wpdb;

$table_name = $this->get_price_log_table();

if ( $this->table_exists( $table_name ) ) {
return;
}

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$charset_collate = $wpdb->get_charset_collate();
$sql             = "CREATE TABLE {$table_name} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
row_id VARCHAR(190) NOT NULL,
old_price LONGTEXT NULL,
new_price LONGTEXT NOT NULL,
changed_by BIGINT UNSIGNED NULL,
changed_at DATETIME NOT NULL,
PRIMARY KEY  (id),
KEY row_id (row_id)
) {$charset_collate};";

dbDelta( $sql );
}

/**
 * Vérifie l'existence d'une table.
 */
private function table_exists( string $table ): bool {
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
}

/**
 * Retourne le nom fully-qualified de la table de log.
 */
private function get_price_log_table(): string {
global $wpdb;

return $wpdb->prefix . self::PRICE_LOG_TABLE;
}
}
