<?php
/**
 * Gestion des prix et de l'audit.
 *
 * @package Vsoa\Pricing
 */

namespace Vsoa\Pricing;

use Vsoa\Storage\Storage;
use wpdb;

/**
 * Pricing manager.
 */
class PricingManager {
	/**
	 * Retourne le prix courant pour un ID.
	 */
	public function get_price_for( string $id ): ?array {
		$row = ( new Storage() )->find_row( $id );

		if ( null === $row ) {
			return null;
		}

		return [
			'base'     => $row['price'] ?? null,
			'currency' => $row['currency'] ?? null,
			'metadata' => $row['metadata']['pricing'] ?? [],
			'history'  => $this->get_audit_log( $id ),
		];
	}

	/**
	 * Met à jour le prix d'une ligne.
	 *
	 * @param string               $id   Identifiant.
	 * @param array<string, mixed> $data Données prix.
	 */
	public function update_price_for( string $id, array $data ): array {
		$this->validate_price_payload( $data );

		$storage = new Storage();
		$row     = $storage->find_row( $id ) ?? [ 'id' => $id ];

		$old_price                   = $row['metadata']['pricing'] ?? [];
		$row['metadata']['pricing'] = [
			'amount'    => (float) $data['amount'],
			'currency'  => sanitize_text_field( (string) $data['currency'] ),
			'tiers'     => $data['tiers'] ?? [],
			'discounts' => $data['discounts'] ?? [],
		];

		$storage->upsert_row( $id, $row );

		$this->log_price_change( $id, $old_price, $row['metadata']['pricing'] );

		return $row['metadata']['pricing'];
	}

	/**
	 * Valide le payload.
	 *
	 * @param array<string, mixed> $data Données.
	 */
	private function validate_price_payload( array $data ): void {
		if ( empty( $data['amount'] ) || ! is_numeric( $data['amount'] ) ) {
			throw new \InvalidArgumentException( 'Montant invalide.' );
		}

		if ( empty( $data['currency'] ) || 3 !== strlen( (string) $data['currency'] ) ) {
			throw new \InvalidArgumentException( 'Devise invalide.' );
		}

		if ( isset( $data['discounts'] ) && ! is_array( $data['discounts'] ) ) {
			throw new \InvalidArgumentException( 'Discounts doit être un tableau.' );
		}

		if ( isset( $data['tiers'] ) && ! is_array( $data['tiers'] ) ) {
			throw new \InvalidArgumentException( 'Tiers doit être un tableau.' );
		}
	}

	/**
	 * Retourne l'historique.
	 */
	private function get_audit_log( string $id ): array {
		global $wpdb;

		$table = $this->get_table();

		if ( ! $table ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT old_price, new_price, changed_at FROM {$table} WHERE row_id = %s ORDER BY changed_at DESC LIMIT 10",
				$id
			),
			ARRAY_A
		);

		return array_map(
			static function ( $row ) {
				return [
					'old' => json_decode( (string) $row['old_price'], true ),
					'new' => json_decode( (string) $row['new_price'], true ),
					'at'  => $row['changed_at'],
				];
			},
			$results
		);
	}

	/**
	 * Journalise le changement.
	 */
	private function log_price_change( string $id, array $old, array $new ): void {
		global $wpdb;

		$table = $this->get_table();

		if ( ! $table ) {
			return;
		}

		$wpdb->insert(
			$table,
			[
				'row_id'     => $id,
				'old_price'  => wp_json_encode( $old ),
				'new_price'  => wp_json_encode( $new ),
				'changed_by' => get_current_user_id(),
				'changed_at' => current_time( 'mysql', true ),
			]
		);
	}

	/**
	 * Retourne le nom de table si disponible.
	 */
	private function get_table(): ?string {
		global $wpdb;

		$table = $wpdb->prefix . Storage::PRICE_LOG_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return $exists ? $table : null;
	}
}
