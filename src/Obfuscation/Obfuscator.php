<?php
/**
 * Utilitaires liés à l'obfuscation et aux tokens.
 *
 * @package Vsoa\Obfuscation
 */

namespace Vsoa\Obfuscation;

/**
 * Gère la génération/validation de jetons.
 */
class Obfuscator {
private const TOKEN_OPTION = 'vsoa_obfuscation_secret';

/**
 * Génère un token signé.
 */
public function generate_token( string $value, int $ttl = 60 ): array {
$secret = $this->get_secret();
$expires = time() + max( 10, $ttl );

$payload = [
'value'   => $value,
'expires' => $expires,
];

$signature = hash_hmac( 'sha256', wp_json_encode( $payload ), $secret );

return [
'token'     => base64_encode( wp_json_encode( $payload ) ),
'signature' => $signature,
'expires'   => $expires,
];
}

/**
 * Valide un token signé.
 */
public function validate_token( string $token, string $signature ): bool {
$secret  = $this->get_secret();
$decoded = json_decode( base64_decode( $token, true ), true );

if ( ! is_array( $decoded ) ) {
return false;
}

if ( empty( $decoded['value'] ) || empty( $decoded['expires'] ) ) {
return false;
}

$expected = hash_hmac( 'sha256', wp_json_encode( $decoded ), $secret );

if ( ! hash_equals( $expected, $signature ) ) {
return false;
}

return (int) $decoded['expires'] >= time();
}

/**
 * Retourne la clé secrète.
 */
private function get_secret(): string {
$secret = get_option( self::TOKEN_OPTION );

if ( ! $secret ) {
$secret = bin2hex( random_bytes( 32 ) );
update_option( self::TOKEN_OPTION, $secret, false );
}

return (string) $secret;
}
}
