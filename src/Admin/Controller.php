<?php
/**
 * Gestion de l'interface administrateur (import/export, CRUD, pricing, stats).
 *
 * @package Vsoa\Admin
 */

namespace Vsoa\Admin;

use Vsoa\Pricing\PricingManager;
use Vsoa\Stats\StatsCollector;
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
        add_action( 'admin_post_vsoa_row_save', [ $this, 'handle_row_save' ] );
        add_action( 'admin_post_vsoa_row_delete', [ $this, 'handle_row_delete' ] );
        add_action( 'admin_post_vsoa_price_update', [ $this, 'handle_price_update' ] );
        add_action( 'admin_post_vsoa_stats_reset', [ $this, 'handle_stats_reset' ] );
        add_action( 'admin_post_vsoa_stats_flush', [ $this, 'handle_stats_flush' ] );
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

        $storage = new Storage();
        $tabs    = [
            'links'   => __( 'Gestion des liens', 'vsoa-widget' ),
            'pricing' => __( 'Gestion des prix', 'vsoa-widget' ),
            'stats'   => __( 'Statistiques', 'vsoa-widget' ),
        ];
        $active  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'links';

        if ( ! isset( $tabs[ $active ] ) ) {
            $active = 'links';
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'VSOA Widget — Tableau de bord', 'vsoa-widget' ); ?></h1>
            <?php $this->render_admin_notice(); ?>
            <h2 class="nav-tab-wrapper">
                <?php foreach ( $tabs as $slug => $label ) : ?>
                    <?php
                    $url   = add_query_arg(
                        [
                            'page' => 'vsoa-widget',
                            'tab'  => $slug,
                        ],
                        admin_url( 'admin.php' )
                    );
                    $class = 'nav-tab' . ( $active === $slug ? ' nav-tab-active' : '' );
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </h2>
            <div class="vsoa-admin-tab">
                <?php
                if ( 'pricing' === $active ) {
                    $this->render_pricing_tab( $storage );
                } elseif ( 'stats' === $active ) {
                    $this->render_stats_tab();
                } else {
                    $this->render_links_tab( $storage );
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Onglet de gestion des liens.
     */
    private function render_links_tab( Storage $storage ): void {
        $rows    = $storage->get_rows();
        $edit_id = isset( $_GET['row'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['row'] ) ) : '';
        $edit    = $edit_id ? $storage->find_row( $edit_id ) : null;
        $payload = $edit ? wp_json_encode( $edit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : '{}';
        ?>
        <div class="vsoa-links">
            <h2><?php esc_html_e( 'Importer / Exporter', 'vsoa-widget' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'vsoa_import_action' ); ?>
                <input type="hidden" name="action" value="vsoa_import" />
                <p><?php esc_html_e( 'Collez le JSON (format historique, tableau).', 'vsoa-widget' ); ?></p>
                <textarea name="vsoa_json" rows="12" class="large-text code"><?php echo esc_textarea( $storage->export_json() ); ?></textarea>
                <p>
                    <label>
                        <input type="checkbox" name="vsoa_mode" value="merge" />
                        <?php esc_html_e( 'Fusionner (ajout/mise à jour).', 'vsoa-widget' ); ?>
                    </label>
                </p>
                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Importer', 'vsoa-widget' ); ?></button>
                    <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vsoa_import&mode=delete' ), 'vsoa_import_action' ) ); ?>"><?php esc_html_e( 'Supprimer via JSON', 'vsoa-widget' ); ?></a>
                    <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vsoa_import&mode=reset' ), 'vsoa_import_action' ) ); ?>"><?php esc_html_e( 'Reset complet', 'vsoa-widget' ); ?></a>
                </p>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Gestion des liens (CRUD)', 'vsoa-widget' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vsoa-row-form">
                <?php wp_nonce_field( 'vsoa_row_save' ); ?>
                <input type="hidden" name="action" value="vsoa_row_save" />
                <p>
                    <label for="vsoa_row_id"><strong><?php esc_html_e( 'Identifiant (id)', 'vsoa-widget' ); ?></strong></label><br />
                    <input type="text" id="vsoa_row_id" name="vsoa_row_id" value="<?php echo esc_attr( $edit['id'] ?? '' ); ?>" class="regular-text" <?php echo $edit ? 'readonly' : ''; ?> required />
                </p>
                <p>
                    <label for="vsoa_row_payload"><strong><?php esc_html_e( 'Payload JSON (sans le champ id)', 'vsoa-widget' ); ?></strong></label><br />
                    <textarea id="vsoa_row_payload" name="vsoa_row_payload" rows="8" class="large-text code"><?php echo esc_textarea( $payload ); ?></textarea>
                </p>
                <p>
                    <button type="submit" class="button button-primary"><?php echo $edit ? esc_html__( 'Mettre à jour la ligne', 'vsoa-widget' ) : esc_html__( 'Créer la ligne', 'vsoa-widget' ); ?></button>
                    <?php if ( $edit ) : ?>
                        <a class="button" href="<?php echo esc_url( remove_query_arg( 'row' ) ); ?>"><?php esc_html_e( 'Annuler', 'vsoa-widget' ); ?></a>
                    <?php endif; ?>
                </p>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'vsoa-widget' ); ?></th>
                        <th><?php esc_html_e( 'Valeur', 'vsoa-widget' ); ?></th>
                        <th><?php esc_html_e( 'Affiliate', 'vsoa-widget' ); ?></th>
                        <th><?php esc_html_e( 'Offre', 'vsoa-widget' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'vsoa-widget' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e( 'Aucune ligne enregistrée pour le moment.', 'vsoa-widget' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( (string) ( $row['id'] ?? '' ) ); ?></code></td>
                                <td><?php echo esc_html( $this->extract_field( $row, [ 'data_value', 'data-value', 'value' ] ) ); ?></td>
                                <td><?php echo esc_html( $this->extract_field( $row, [ 'data_aff', 'data-aff', 'affiliate' ] ) ); ?></td>
                                <td><?php echo esc_html( $this->extract_field( $row, [ 'data_offer', 'data-offer', 'offer' ] ) ); ?></td>
                                <td>
                                    <?php
                                    $edit_url = add_query_arg(
                                        [
                                            'page' => 'vsoa-widget',
                                            'tab'  => 'links',
                                            'row'  => $row['id'] ?? '',
                                        ],
                                        admin_url( 'admin.php' )
                                    );
                                    ?>
                                    <a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Modifier', 'vsoa-widget' ); ?></a>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                                        <?php wp_nonce_field( 'vsoa_row_delete' ); ?>
                                        <input type="hidden" name="action" value="vsoa_row_delete" />
                                        <input type="hidden" name="vsoa_row_id" value="<?php echo esc_attr( $row['id'] ?? '' ); ?>" />
                                        <button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Supprimer cette ligne ?', 'vsoa-widget' ) ); ?>');"><?php esc_html_e( 'Supprimer', 'vsoa-widget' ); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Onglet pricing.
     */
    private function render_pricing_tab( Storage $storage ): void {
        $rows            = $storage->get_rows();
        $pricing_manager = new PricingManager();
        $current_id      = isset( $_GET['row'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['row'] ) ) : ( $rows[0]['id'] ?? '' );
        $current_price   = $current_id ? $pricing_manager->get_price_for( $current_id ) : null;
        $metadata        = (array) ( $current_price['metadata'] ?? [] );
        $tiers           = isset( $metadata['tiers'] ) ? wp_json_encode( $metadata['tiers'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : '[]';
        $discounts       = isset( $metadata['discounts'] ) ? wp_json_encode( $metadata['discounts'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : '[]';
        ?>
        <div class="vsoa-pricing">
            <p><?php esc_html_e( 'Administrez les prix, devises, paliers et remises pour chaque ligne.', 'vsoa-widget' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vsoa-price-form">
                <?php wp_nonce_field( 'vsoa_price_update' ); ?>
                <input type="hidden" name="action" value="vsoa_price_update" />
                <p>
                    <label for="vsoa_price_row"><strong><?php esc_html_e( 'Ligne cible', 'vsoa-widget' ); ?></strong></label><br />
                    <select id="vsoa_price_row" name="vsoa_price_row" class="regular-text">
                        <?php foreach ( $rows as $row ) : ?>
                            <option value="<?php echo esc_attr( $row['id'] ?? '' ); ?>" <?php selected( $current_id, $row['id'] ?? '' ); ?>><?php echo esc_html( (string) ( $row['id'] ?? '' ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="vsoa_price_amount"><strong><?php esc_html_e( 'Montant', 'vsoa-widget' ); ?></strong></label><br />
                    <input type="number" step="0.01" min="0" id="vsoa_price_amount" name="vsoa_price_amount" value="<?php echo esc_attr( isset( $metadata['amount'] ) ? (string) $metadata['amount'] : '' ); ?>" class="regular-text" required />
                </p>
                <p>
                    <label for="vsoa_price_currency"><strong><?php esc_html_e( 'Devise (ISO 4217)', 'vsoa-widget' ); ?></strong></label><br />
                    <input type="text" maxlength="3" id="vsoa_price_currency" name="vsoa_price_currency" value="<?php echo esc_attr( isset( $metadata['currency'] ) ? (string) $metadata['currency'] : 'EUR' ); ?>" class="regular-text" required />
                </p>
                <p>
                    <label for="vsoa_price_tiers"><strong><?php esc_html_e( 'Tiers (JSON)', 'vsoa-widget' ); ?></strong></label><br />
                    <textarea id="vsoa_price_tiers" name="vsoa_price_tiers" rows="4" class="large-text code"><?php echo esc_textarea( $tiers ); ?></textarea>
                </p>
                <p>
                    <label for="vsoa_price_discounts"><strong><?php esc_html_e( 'Remises (JSON)', 'vsoa-widget' ); ?></strong></label><br />
                    <textarea id="vsoa_price_discounts" name="vsoa_price_discounts" rows="4" class="large-text code"><?php echo esc_textarea( $discounts ); ?></textarea>
                </p>
                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Mettre à jour le prix', 'vsoa-widget' ); ?></button>
                </p>
            </form>

            <h2><?php esc_html_e( 'Historique récent', 'vsoa-widget' ); ?></h2>
            <?php if ( empty( $current_price['history'] ?? [] ) ) : ?>
                <p><?php esc_html_e( 'Aucune modification de prix enregistrée.', 'vsoa-widget' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'vsoa-widget' ); ?></th>
                            <th><?php esc_html_e( 'Ancien prix', 'vsoa-widget' ); ?></th>
                            <th><?php esc_html_e( 'Nouveau prix', 'vsoa-widget' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $current_price['history'] as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( (string) ( $entry['at'] ?? '' ) ); ?></td>
                                <td><code><?php echo esc_html( wp_json_encode( $entry['old'] ?? [] ) ); ?></code></td>
                                <td><code><?php echo esc_html( wp_json_encode( $entry['new'] ?? [] ) ); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Résumé global', 'vsoa-widget' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'vsoa-widget' ); ?></th>
                        <th><?php esc_html_e( 'Montant', 'vsoa-widget' ); ?></th>
                        <th><?php esc_html_e( 'Devise', 'vsoa-widget' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) :
                        $price = $pricing_manager->get_price_for( (string) ( $row['id'] ?? '' ) );
                        $meta  = (array) ( $price['metadata'] ?? [] );
                        ?>
                        <tr>
                            <td><?php echo esc_html( (string) ( $row['id'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( isset( $meta['amount'] ) ? (string) $meta['amount'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $meta['currency'] ) ? (string) $meta['currency'] : '' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Onglet statistiques.
     */
    private function render_stats_tab(): void {
        $aggregates = StatsCollector::get_aggregates();
        $queue_size = StatsCollector::get_queue_size();
        ?>
        <div class="vsoa-stats">
            <p><?php esc_html_e( 'Aperçu des clics pseudonymisés (sans PII).', 'vsoa-widget' ); ?></p>
            <p><?php printf( esc_html__( 'File en attente : %d événements.', 'vsoa-widget' ), absint( $queue_size ) ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1em; display:inline-block;">
                <?php wp_nonce_field( 'vsoa_stats_flush' ); ?>
                <input type="hidden" name="action" value="vsoa_stats_flush" />
                <button type="submit" class="button"><?php esc_html_e( 'Forcer l\'agrégation', 'vsoa-widget' ); ?></button>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-left:1em; display:inline-block;">
                <?php wp_nonce_field( 'vsoa_stats_reset' ); ?>
                <input type="hidden" name="action" value="vsoa_stats_reset" />
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Réinitialiser toutes les stats ?', 'vsoa-widget' ) ); ?>');"><?php esc_html_e( 'Reset statistiques', 'vsoa-widget' ); ?></button>
            </form>

            <table class="widefat striped" style="margin-top:1.5em;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Identifiant pseudonymisé', 'vsoa-widget' ); ?></th>
                        <th><?php esc_html_e( '24h', 'vsoa-widget' ); ?></th>
                        <th><?php esc_html_e( '7 jours', 'vsoa-widget' ); ?></th>
                        <th><?php esc_html_e( '30 jours', 'vsoa-widget' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $aggregates ) ) : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e( 'Aucune donnée agrégée pour le moment.', 'vsoa-widget' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $aggregates as $hash => $counts ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( (string) $hash ); ?></code></td>
                                <td><?php echo esc_html( isset( $counts['24h'] ) ? (string) $counts['24h'] : '0' ); ?></td>
                                <td><?php echo esc_html( isset( $counts['7d'] ) ? (string) $counts['7d'] : '0' ); ?></td>
                                <td><?php echo esc_html( isset( $counts['30d'] ) ? (string) $counts['30d'] : '0' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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

        $mode    = isset( $_REQUEST['mode'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['mode'] ) ) : ( isset( $_POST['vsoa_mode'] ) ? 'merge' : 'replace' );
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

        $this->redirect_with_message( $message, 'links' );
    }

    /**
     * Crée ou met à jour une ligne.
     */
    public function handle_row_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission refusée.', 'vsoa-widget' ) );
        }

        check_admin_referer( 'vsoa_row_save' );

        $id = isset( $_POST['vsoa_row_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['vsoa_row_id'] ) ) : '';

        if ( '' === $id ) {
            $this->redirect_with_message( __( 'Identifiant manquant.', 'vsoa-widget' ), 'links' );
        }

        $payload_raw = isset( $_POST['vsoa_row_payload'] ) ? wp_unslash( (string) $_POST['vsoa_row_payload'] ) : '{}';
        $decoded     = json_decode( $payload_raw, true );

        if ( null === $decoded || ! is_array( $decoded ) ) {
            $this->redirect_with_message( __( 'Payload JSON invalide.', 'vsoa-widget' ), 'links', [ 'row' => $id ] );
        }

        unset( $decoded['id'] );

        $storage = new Storage();
        $storage->upsert_row( $id, $decoded );

        $this->redirect_with_message( __( 'Ligne enregistrée.', 'vsoa-widget' ), 'links', [ 'row' => $id ] );
    }

    /**
     * Supprime une ligne.
     */
    public function handle_row_delete(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission refusée.', 'vsoa-widget' ) );
        }

        check_admin_referer( 'vsoa_row_delete' );

        $id = isset( $_POST['vsoa_row_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['vsoa_row_id'] ) ) : '';

        if ( '' !== $id ) {
            ( new Storage() )->delete_row( $id );
        }

        $this->redirect_with_message( __( 'Ligne supprimée.', 'vsoa-widget' ), 'links' );
    }

    /**
     * Met à jour un prix.
     */
    public function handle_price_update(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission refusée.', 'vsoa-widget' ) );
        }

        check_admin_referer( 'vsoa_price_update' );

        $id       = isset( $_POST['vsoa_price_row'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['vsoa_price_row'] ) ) : '';
        $amount   = isset( $_POST['vsoa_price_amount'] ) ? (float) $_POST['vsoa_price_amount'] : 0.0;
        $currency = isset( $_POST['vsoa_price_currency'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['vsoa_price_currency'] ) ) : '';
        $tiers    = isset( $_POST['vsoa_price_tiers'] ) ? wp_unslash( (string) $_POST['vsoa_price_tiers'] ) : '[]';
        $discount = isset( $_POST['vsoa_price_discounts'] ) ? wp_unslash( (string) $_POST['vsoa_price_discounts'] ) : '[]';

        $tiers_decoded    = json_decode( $tiers, true );
        $discount_decoded = json_decode( $discount, true );

        if ( ! is_array( $tiers_decoded ) || ! is_array( $discount_decoded ) ) {
            $this->redirect_with_message( __( 'Les champs tiers/remises doivent être du JSON valide.', 'vsoa-widget' ), 'pricing', [ 'row' => $id ] );
        }

        try {
            ( new PricingManager() )->update_price_for(
                $id,
                [
                    'amount'    => $amount,
                    'currency'  => $currency,
                    'tiers'     => $tiers_decoded,
                    'discounts' => $discount_decoded,
                ]
            );
            $message = __( 'Prix mis à jour.', 'vsoa-widget' );
        } catch ( \InvalidArgumentException $exception ) {
            $message = $exception->getMessage();
        }

        $this->redirect_with_message( $message, 'pricing', [ 'row' => $id ] );
    }

    /**
     * Forçage de l'agrégation des stats.
     */
    public function handle_stats_flush(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission refusée.', 'vsoa-widget' ) );
        }

        check_admin_referer( 'vsoa_stats_flush' );
        StatsCollector::flush_queue();
        $this->redirect_with_message( __( 'Agrégation exécutée.', 'vsoa-widget' ), 'stats' );
    }

    /**
     * Reset complet des stats.
     */
    public function handle_stats_reset(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission refusée.', 'vsoa-widget' ) );
        }

        check_admin_referer( 'vsoa_stats_reset' );
        StatsCollector::reset();
        $this->redirect_with_message( __( 'Statistiques réinitialisées.', 'vsoa-widget' ), 'stats' );
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

    /**
     * Affiche un message de confirmation.
     */
    private function render_admin_notice(): void {
        if ( empty( $_GET['message'] ) ) {
            return;
        }

        $message = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['message'] ) ) );

        printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $message ) );
    }

    /**
     * Redirige avec message.
     *
     * @param string               $message Message.
     * @param string               $tab     Onglet cible.
     * @param array<string,string> $extra   Params supplémentaires.
     */
    private function redirect_with_message( string $message, string $tab, array $extra = [] ): void {
        $args = array_merge(
            [
                'page'    => 'vsoa-widget',
                'tab'     => $tab,
                'message' => $message,
            ],
            $extra
        );

        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Extrait une valeur probable de ligne.
     *
     * @param array<string,mixed> $row  Ligne.
     * @param array<int,string>   $keys Clés candidates.
     */
    private function extract_field( array $row, array $keys ): string {
        foreach ( $keys as $key ) {
            if ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ) {
                return (string) $row[ $key ];
            }
        }

        return '';
    }
}
