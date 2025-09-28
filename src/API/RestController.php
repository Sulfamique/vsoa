<?php
/**
 * Déclaration des endpoints REST sécurisés.
 *
 * @package Vsoa\API
 */

namespace Vsoa\API;

use Vsoa\Storage\Storage;
use Vsoa\Stats\StatsCollector;
use Vsoa\Pricing\PricingManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Contrôleur REST principal.
 */
class RestController {
	private const REST_NAMESPACE = 'vsoa/v1';

	/**
	 * Enregistre toutes les routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/rows',
			[
				'callback'            => [ $this, 'list_rows' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'methods'             => 'GET',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/row',
			[
				'callback'            => [ $this, 'create_row' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'methods'             => 'POST',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/row/(?P<id>[\w\-]+)',
			[
				'callback'            => [ $this, 'update_row' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'methods'             => [ 'PUT', 'PATCH' ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/row/(?P<id>[\w\-]+)',
			[
				'callback'            => [ $this, 'delete_row' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'methods'             => 'DELETE',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/import',
			[
				'callback'            => [ $this, 'import_rows' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'methods'             => 'POST',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/export',
			[
				'callback'            => [ $this, 'export_rows' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'methods'             => 'GET',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/resolve',
			[
				'callback'            => [ $this, 'resolve_offer' ],
				'permission_callback' => '__return_true',
				'methods'             => 'POST',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/stats',
			[
				'callback'            => [ $this, 'get_stats' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'methods'             => 'GET',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/stats/reset',
			[
				'callback'            => [ $this, 'reset_stats' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'methods'             => 'POST',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/pricing/(?P<id>[\w\-]+)',
			[
				'callback'            => [ $this, 'get_pricing' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'methods'             => 'GET',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/pricing/(?P<id>[\w\-]+)',
			[
				'callback'            => [ $this, 'update_pricing' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'methods'             => [ 'PUT', 'PATCH' ],
			]
		);
	}

	/**
	 * Vérifie les permissions.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Retourne la liste des lignes.
	 */
	public function list_rows(): WP_REST_Response {
		return new WP_REST_Response( ( new Storage() )->get_rows(), 200 );
	}

	/**
	 * Crée une ligne.
	 */
	public function create_row( WP_REST_Request $request ) {
		$params = (array) $request->get_json_params();
		$id     = sanitize_text_field( (string) ( $params['id'] ?? '' ) );

		if ( '' === $id ) {
			return new WP_Error( 'vsoa_missing_id', __( 'Identifiant requis.', 'vsoa-widget' ), [ 'status' => 400 ] );
		}

		unset( $params['id'] );
		( new Storage() )->upsert_row( $id, $params );

		return new WP_REST_Response( [ 'success' => true, 'id' => $id ], 201 );
	}

	/**
	 * Met à jour une ligne.
	 */
	public function update_row( WP_REST_Request $request ) {
		$id     = sanitize_text_field( (string) $request['id'] );
		$params = (array) $request->get_json_params();
		unset( $params['id'] );

		( new Storage() )->upsert_row( $id, $params );

		return new WP_REST_Response( [ 'success' => true, 'id' => $id ], 200 );
	}

	/**
	 * Supprime une ligne.
	 */
	public function delete_row( WP_REST_Request $request ): WP_REST_Response {
		$id = sanitize_text_field( (string) $request['id'] );
		( new Storage() )->delete_row( $id );

		return new WP_REST_Response( [ 'success' => true, 'id' => $id ], 200 );
	}

	/**
	 * Import JSON (reset, merge, delete).
	 */
	public function import_rows( WP_REST_Request $request ) {
		$mode = sanitize_key( (string) $request->get_param( 'mode' ) ?: 'replace' );

		try {
			$result = ( new Storage() )->import_json( (string) $request->get_body(), $mode );
		} catch ( \InvalidArgumentException $exception ) {
			return new WP_Error( 'vsoa_bad_json', $exception->getMessage(), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( [ 'success' => true, 'result' => $result ], 200 );
	}

	/**
	 * Export JSON.
	 */
	public function export_rows(): WP_REST_Response {
		$data = json_decode( ( new Storage() )->export_json(), true );

		return new WP_REST_Response( is_array( $data ) ? $data : [], 200 );
	}

	/**
	 * Résolution d'offre -> token signé, jamais d'URL renvoyée.
	 */
	public function resolve_offer( WP_REST_Request $request ) {
		$data_value = sanitize_text_field( (string) $request->get_param( 'data_value' ) );

		if ( '' === $data_value ) {
			return new WP_Error( 'vsoa_missing_value', __( 'data_value manquant.', 'vsoa-widget' ), [ 'status' => 400 ] );
		}

		$token = wp_generate_uuid4();
		set_transient(
			'vsoa_tok_' . $token,
			[
				'target' => apply_filters( 'vsoa_resolve_target', '', $data_value ),
				'value'  => $data_value,
			],
			MINUTE_IN_SECONDS
		);

		if ( has_filter( 'vsoa_resolve_target' ) ) {
			/**
			 * Permet de journaliser le clic.
			 */
			StatsCollector::track( 'resolve_request', [ 'value' => $data_value ] );
		}

		return new WP_REST_Response(
			[
				'token' => $token,
				'ttl'   => MINUTE_IN_SECONDS,
			],
			200
		);
	}

	/**
	 * Retourne les statistiques agrégées.
	 */
	public function get_stats(): WP_REST_Response {
		return new WP_REST_Response( StatsCollector::get_aggregates(), 200 );
	}

	/**
	 * Réinitialise les statistiques.
	 */
	public function reset_stats(): WP_REST_Response {
		StatsCollector::reset();

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Récupère le pricing pour une ligne.
	 */
	public function get_pricing( WP_REST_Request $request ): WP_REST_Response {
		$id = sanitize_text_field( (string) $request['id'] );

		return new WP_REST_Response( ( new PricingManager() )->get_price_for( $id ), 200 );
	}

	/**
	 * Met à jour le pricing.
	 */
	public function update_pricing( WP_REST_Request $request ) {
		$id   = sanitize_text_field( (string) $request['id'] );
		$data = (array) $request->get_json_params();

		try {
			$result = ( new PricingManager() )->update_price_for( $id, $data );
		} catch ( \InvalidArgumentException $exception ) {
			return new WP_Error( 'vsoa_bad_price', $exception->getMessage(), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( $result, 200 );
	}
}
