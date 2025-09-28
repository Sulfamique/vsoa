<?php
/**
 * Core loader qui initialise les modules du plugin.
 *
 * @package Vsoa\Core
 */

namespace Vsoa\Core;

use Vsoa\API\RestController;
use Vsoa\Admin\Controller as AdminController;
use Vsoa\Frontend\Enqueue as FrontendEnqueue;
use Vsoa\Stats\StatsCollector;

/**
 * Classe principale de chargement.
 */
class Loader {
	/**
	 * Initialise tous les hooks.
	 */
	public function init(): void {
		if ( is_admin() ) {
			( new AdminController() )->hooks();
		}

		add_action( 'rest_api_init', [ $this, 'register_rest' ] );
		( new FrontendEnqueue() )->hooks();

		add_action( 'init', [ $this, 'register_cron_events' ] );
	}

	/**
	 * Déclare les routes REST.
	 */
	public function register_rest(): void {
		( new RestController() )->register_routes();
	}

	/**
	 * Enregistre les évènements cron nécessaires.
	 */
	public function register_cron_events(): void {
		if ( ! wp_next_scheduled( 'vsoa_stats_flush' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'vsoa_stats_flush' );
		}

		add_action( 'vsoa_stats_flush', [ StatsCollector::class, 'flush_queue' ] );
	}
}
