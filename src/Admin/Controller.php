<?php
/**
 * Gestion de l'interface administrateur (import/export, CRUD).
 *
 * @package Vsoa\Admin
 */

namespace Vsoa\Admin;

use Vsoa\Storage\Storage;

/**
 * Contrôleur Admin.
 */
class Controller {
/**
 * Enregistre les hooks admin.
 */
public function hooks(): void {
add_action( 'admin_menu', [ $this, 'register_menu' ] );
add_action( 'admin_post_vsoa_import', [ $this, 'handle_import' ] );
}

/**
 * Ajoute le menu principal.
 */
public function register_menu(): void {
add_menu_page(
'VSOA Widget',
'VSOA Widget',
'manage_options',
'vsoa-widget',
[ $this, 'render_page' ],
'dashicons-database',
65
);
}

/**
 * Affiche la page.
 */
public function render_page(): void {
if ( ! current_user_can( 'manage_options' ) ) {
return;
}

$storage   = new Storage();
$rows_json = $storage->export_json();
?>
<div class="wrap">
<h1><?php esc_html_e( 'VSOA Widget — Import/Export JSON', 'vsoa-widget' ); ?></h1>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
<?php wp_nonce_field( 'vsoa_import_action' ); ?>
<input type="hidden" name="action" value="vsoa_import" />
<p><?php esc_html_e( 'Collez le JSON (format historique, tableau).', 'vsoa-widget' ); ?></p>
<textarea name="vsoa_json" rows="12" class="large-text code"><?php echo esc_textarea( $rows_json ); ?></textarea>
<p>
<label>
<input type="checkbox" name="vsoa_mode" value="merge" />
<?php esc_html_e( 'Fusionner (ajout/mise à jour).', 'vsoa-widget' ); ?>
</label>
</p>
<p>
<button type="submit" class="button button-primary">
<?php esc_html_e( 'Importer', 'vsoa-widget' ); ?>
</button>
<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vsoa_import&mode=delete' ), 'vsoa_import_action' ) ); ?>">
<?php esc_html_e( 'Supprimer via JSON', 'vsoa-widget' ); ?>
</a>
<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vsoa_import&mode=reset' ), 'vsoa_import_action' ) ); ?>">
<?php esc_html_e( 'Reset complet', 'vsoa-widget' ); ?>
</a>
</p>
</form>

<h2><?php esc_html_e( 'Export JSON', 'vsoa-widget' ); ?></h2>
<textarea readonly rows="12" class="large-text code"><?php echo esc_textarea( wp_json_encode( $storage->get_rows(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
</div>
<?php
}

/**
 * Traitement de l'import.
 */
public function handle_import(): void {
if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'Permission refusée.', 'vsoa-widget' ) );
}

check_admin_referer( 'vsoa_import_action' );

$mode = isset( $_REQUEST['mode'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['mode'] ) ) : ( isset( $_POST['vsoa_mode'] ) ? 'merge' : 'replace' );

$storage = new Storage();
try {
if ( 'delete' === $mode ) {
$storage->import_json( $this->get_json_input(), 'delete' );
} elseif ( 'reset' === $mode ) {
$storage->replace_all( [] );
} else {
$storage->import_json( $this->get_json_input(), $mode );
}

$message = __( 'Import effectué.', 'vsoa-widget' );
} catch ( \InvalidArgumentException $exception ) {
$message = $exception->getMessage();
}

wp_safe_redirect(
add_query_arg(
[
'page'    => 'vsoa-widget',
'message' => rawurlencode( $message ),
],
admin_url( 'admin.php' )
)
);
exit;
}

/**
 * Récupère le JSON soumis.
 */
private function get_json_input(): string {
if ( isset( $_POST['vsoa_json'] ) ) {
return (string) wp_unslash( $_POST['vsoa_json'] );
}

return '[]';
}
}
