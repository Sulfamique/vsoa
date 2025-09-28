<?php
/**
 * Gestion du front-end : scripts et shortcode.
 *
 * @package Vsoa\Frontend
 */

namespace Vsoa\Frontend;

/**
 * Enqueue & Shortcode front.
 */
class Enqueue {
/**
 * Déclare les hooks.
 */
public function hooks(): void {
add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
add_shortcode( 'vsoa_widget', [ $this, 'render_shortcode' ] );
}

/**
 * Enregistre le script front minimal.
 */
public function register_assets(): void {
wp_register_script(
'vsoa-front',
plugins_url( 'assets/js/vsoa-front.js', VSOA_PLUGIN_FILE ),
[],
VSOA_PLUGIN_VERSION,
true
);

wp_enqueue_script( 'vsoa-front' );

wp_localize_script(
'vsoa-front',
'VSOA_FRONT_CFG',
[
'rest' => [
'namespace' => 'vsoa/v1',
'root'      => esc_url_raw( rest_url() ),
],
]
);
}

/**
 * Shortcode principal.
 *
 * @param array<string, mixed> $atts Attributs.
 */
public function render_shortcode( array $atts ): string {
$atts = shortcode_atts(
[
'value' => '',
'aff'   => '',
'offer' => '',
],
$atts,
'vsoa_widget'
);

$value = sanitize_text_field( (string) $atts['value'] );
$aff   = sanitize_text_field( (string) $atts['aff'] );
$offer = sanitize_text_field( (string) $atts['offer'] );

if ( '' === $value ) {
return '';
}

$attributes = sprintf( ' data-value="%s"', esc_attr( $value ) );

if ( '' !== $aff ) {
$attributes .= sprintf( ' data-aff="%s"', esc_attr( $aff ) );
}

if ( '' !== $offer ) {
$attributes .= sprintf( ' data-offer="%s"', esc_attr( $offer ) );
}

return '<span class="vsoa-item"' . $attributes . '></span>';
}
}
