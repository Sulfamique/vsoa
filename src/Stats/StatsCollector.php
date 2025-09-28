<?php
/**
 * Collecte et agrégation des statistiques.
 *
 * @package Vsoa\Stats
 */

namespace Vsoa\Stats;

/**
 * Stats collector.
 */
class StatsCollector {
private const QUEUE_TRANSIENT = 'vsoa_stats_queue';
private const AGGREGATE_OPTION = 'vsoa_stats_aggregates';

/**
 * Enfile un événement.
 *
 * @param string               $event   Type d'événement.
 * @param array<string, mixed> $context Contexte.
 */
public static function track( string $event, array $context = [] ): void {
if ( ! apply_filters( 'vsoa_stats_enabled', true ) ) {
return;
}

$queue = (array) get_transient( self::QUEUE_TRANSIENT );
$queue[] = [
'event'   => $event,
'context' => $context,
'time'    => time(),
];

set_transient( self::QUEUE_TRANSIENT, $queue, HOUR_IN_SECONDS );
}

/**
 * Flush la file et agrège.
 */
public static function flush_queue(): void {
$queue = (array) get_transient( self::QUEUE_TRANSIENT );

if ( empty( $queue ) ) {
return;
}

delete_transient( self::QUEUE_TRANSIENT );

$aggregates = self::get_aggregates();
$now        = time();

foreach ( $queue as $item ) {
$row_id = isset( $item['context']['value'] ) ? self::hash_identifier( (string) $item['context']['value'] ) : 'generic';

if ( ! isset( $aggregates[ $row_id ] ) ) {
$aggregates[ $row_id ] = [];
}

$aggregates[ $row_id ][] = [
'event' => $item['event'] ?? 'unknown',
'time'  => $item['time'] ?? $now,
];
}

update_option( self::AGGREGATE_OPTION, wp_json_encode( $aggregates ) );
}

/**
 * Retourne les agrégats décodés.
 */
public static function get_aggregates(): array {
$data = json_decode( (string) get_option( self::AGGREGATE_OPTION, '{}' ), true );

if ( ! is_array( $data ) ) {
return [];
}

$windowed = [];
$now      = time();

foreach ( $data as $row_id => $events ) {
$windowed[ $row_id ] = [ '24h' => 0, '7d' => 0, '30d' => 0 ];

foreach ( (array) $events as $event ) {
$time = isset( $event['time'] ) ? (int) $event['time'] : $now;
if ( $time >= $now - DAY_IN_SECONDS ) {
$windowed[ $row_id ]['24h']++;
}
if ( $time >= $now - WEEK_IN_SECONDS ) {
$windowed[ $row_id ]['7d']++;
}
if ( $time >= $now - MONTH_IN_SECONDS ) {
$windowed[ $row_id ]['30d']++;
}
}
}

return $windowed;
}

/**
 * Reset complet.
 */
public static function reset(): void {
delete_transient( self::QUEUE_TRANSIENT );
delete_option( self::AGGREGATE_OPTION );
}

/**
 * Retourne le nombre d'évènements en file.
 */
public static function get_queue_size(): int {
$queue = get_transient( self::QUEUE_TRANSIENT );

return is_array( $queue ) ? count( $queue ) : 0;
}

/**
 * Hash pseudonymisé.
 */
private static function hash_identifier( string $value ): string {
$key = apply_filters( 'vsoa_stats_hash_key', wp_salt( 'vsoa-stats' ) );

return hash_hmac( 'sha256', $value, (string) $key );
}
}
