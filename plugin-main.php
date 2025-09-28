<?php
/**
 * Plugin Name: VSOA Widget (Refactor)
 * Description: Refonte modulaire du plugin VSOA avec séparation stricte serveur/front et compatibilité JSON.
 * Version: 2.0.0
 * Author: VSOA Team
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Text Domain: vsoa-widget
 */

define( 'VSOA_PLUGIN_FILE', __FILE__ );
define( 'VSOA_PLUGIN_DIR', __DIR__ );
define( 'VSOA_PLUGIN_VERSION', '2.0.0' );

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( file_exists( VSOA_PLUGIN_DIR . '/vendor/autoload.php' ) ) {
require VSOA_PLUGIN_DIR . '/vendor/autoload.php';
}

spl_autoload_register(
static function ( $class ) {
$prefix   = 'Vsoa\\';
$base_dir = VSOA_PLUGIN_DIR . '/src/';
$len      = strlen( $prefix );

if ( strncmp( $prefix, $class, $len ) !== 0 ) {
return;
}

$relative_class = substr( $class, $len );
$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

if ( file_exists( $file ) ) {
require $file;
}
}
);

add_action(
'plugins_loaded',
static function () {
global $wp_version;

if ( version_compare( (string) $wp_version, '6.2', '<' ) ) {
add_action(
'admin_notices',
static function () {
printf(
'<div class="notice notice-error"><p>%s</p></div>',
esc_html__( 'VSOA Widget requiert WordPress 6.2 ou supérieur.', 'vsoa-widget' )
);
}
);

return;
}

try {
( new Vsoa\Core\Loader() )->init();
} catch ( Throwable $throwable ) {
error_log( 'VSOA bootstrap error: ' . $throwable->getMessage() );
}
}
);

register_activation_hook(
VSOA_PLUGIN_FILE,
static function () {
try {
( new Vsoa\Storage\Storage() )->install();
} catch ( Throwable $throwable ) {
error_log( 'VSOA activation error: ' . $throwable->getMessage() );
wp_die( esc_html__( 'Erreur lors de l\'installation du plugin. Consultez les logs.', 'vsoa-widget' ) );
}
}
);

register_deactivation_hook(
VSOA_PLUGIN_FILE,
static function () {
// Placeholder pour purge ou arrêt de cron.
}
);

register_uninstall_hook( VSOA_PLUGIN_FILE, 'vsoa_widget_uninstall' );

/**
 * Callback uninstall : suppression des données.
 */
function vsoa_widget_uninstall() {
try {
( new Vsoa\Storage\Storage() )->uninstall();
} catch ( Throwable $throwable ) {
error_log( 'VSOA uninstall error: ' . $throwable->getMessage() );
}
}

add_action(
'init',
static function () {
if ( ! is_admin() ) {
return;
}

if ( isset( $_GET['page'] ) && 'vsoa-widget' === $_GET['page'] ) {
wp_enqueue_script( 'wp-util' );
}
}
);

add_action(
'template_redirect',
static function () {
if ( empty( $_GET['vsoa_redirect'] ) ) {
return;
}

$token   = sanitize_text_field( wp_unslash( (string) $_GET['vsoa_redirect'] ) );
$payload = get_transient( 'vsoa_tok_' . $token );

delete_transient( 'vsoa_tok_' . $token );

if ( ! is_array( $payload ) || empty( $payload['target'] ) ) {
wp_safe_redirect( home_url( '/' ) );
exit;
}

if ( isset( $payload['value'] ) && class_exists( '\\Vsoa\\Stats\\StatsCollector' ) ) {
\Vsoa\Stats\StatsCollector::track( 'redirect', [ 'value' => (string) $payload['value'] ] );
}

wp_safe_redirect( esc_url_raw( (string) $payload['target'] ) );
exit;
}
);
