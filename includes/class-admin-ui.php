<?php

class WPB2EL_Admin_UI {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_wpb2el_convert', [ $this, 'handle_convert' ] );
        add_action( 'admin_post_wpb2el_convert_all', [ $this, 'handle_convert_all' ] );
        add_action( 'admin_post_wpb2el_reset', [ $this, 'handle_reset' ] );
        add_action( 'admin_post_wpb2el_save_settings', [ $this, 'handle_save_settings' ] );
    }

    public function register_menu(): void {
        add_management_page( 'WPB2Elementor', 'WPB2Elementor', 'manage_options', 'wpb2elementor', [ $this, 'render_page' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'tools_page_wpb2elementor' ) return;
        wp_enqueue_style( 'wpb2el-admin', WPB2EL_URL . 'assets/admin.css', [], WPB2EL_VERSION );
    }

    public function render_page(): void {
        $pages             = $this->get_pages();
        $api_key           = get_option( 'wpb2el_api_key', '' );
        $notice            = get_transient( 'wpb2el_notice' );
        $container_enabled = $this->is_container_enabled();
        if ( $notice ) delete_transient( 'wpb2el_notice' );
        ?>
        <div class="wrap wpb2el-wrap">
            <h1>WPB2Elementor</h1>

            <?php if ( $notice ) : ?>
                <div class="wpb2el-notice <?php echo esc_attr( $notice['type'] ); ?>">
                    <?php echo esc_html( $notice['message'] ); ?>
                </div>
            <?php endif; ?>

            <?php if ( ! $container_enabled ) : ?>
                <div class="wpb2el-notice error">
                    ⚠️ <strong>Flexbox Container nicht aktiviert.</strong>
                    Das Plugin konvertiert in das moderne Elementor Container-Format — dieses muss zuerst aktiviert werden.<br>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=elementor#tab-experiments' ) ); ?>" class="button button-primary" style="margin-top:8px">
                        Elementor Experimente öffnen → "Flexbox Container" aktivieren
                    </a>
                </div>
            <?php endif; ?>

            <h2>Einstellungen</h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="wpb2el_save_settings">
                <?php wp_nonce_field( 'wpb2el_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Claude API Key <small>(optional)</small></th>
                        <td>
                            <input type="password" name="wpb2el_api_key"
                                   value="<?php echo esc_attr( $api_key ); ?>"
                                   class="regular-text" placeholder="sk-ant-...">
                            <p class="description">Ohne Key: unbekannte Widgets → HTML-Platzhalter + Prompt-Export.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Speichern' ); ?>
            </form>

            <h2>Seiten</h2>
            <?php if ( $container_enabled ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="wpb2el_convert_all">
                <?php wp_nonce_field( 'wpb2el_convert_all' ); ?>
                <?php submit_button( 'Alle konvertieren', 'secondary', 'submit', false ); ?>
            </form>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Seite</th><th>Status</th><th>Aktion</th></tr></thead>
                <tbody>
                <?php foreach ( $pages as $page ) : ?>
                <tr>
                    <td><a href="<?php echo esc_url( get_edit_post_link( $page['id'] ) ); ?>" target="_blank"><?php echo esc_html( $page['title'] ); ?></a></td>
                    <td>
                        <?php if ( $page['status'] === 'elementor' ) : ?>
                            <span class="status-elementor">✅ Elementor</span>
                        <?php else : ?>
                            <span class="status-wpbakery">⚠ WPBakery</span>
                        <?php endif; ?>
                    </td>
                    <td class="wpb2el-actions">
                        <?php if ( $page['status'] === 'elementor' ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $page['copy_id'] . '&action=elementor' ) ); ?>" class="button button-primary" target="_blank">Mit Elementor bearbeiten</a>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <input type="hidden" name="action" value="wpb2el_reset">
                                <input type="hidden" name="page_id" value="<?php echo esc_attr( $page['id'] ); ?>">
                                <?php wp_nonce_field( 'wpb2el_reset_' . $page['id'] ); ?>
                                <button type="submit" class="button button-secondary" onclick="return confirm('Kopie löschen?')">Zurücksetzen</button>
                            </form>
                        <?php else : ?>
                        <?php if ( $container_enabled ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wpb2el_convert">
                            <input type="hidden" name="page_id" value="<?php echo esc_attr( $page['id'] ); ?>">
                            <?php wp_nonce_field( 'wpb2el_convert_' . $page['id'] ); ?>
                            <button type="submit" class="button button-primary">Konvertieren</button>
                        </form>
                        <?php else : ?>
                            <button class="button button-primary" disabled title="Flexbox Container erst aktivieren">Konvertieren</button>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_save_settings(): void {
        check_admin_referer( 'wpb2el_settings' );
        update_option( 'wpb2el_api_key', sanitize_text_field( $_POST['wpb2el_api_key'] ?? '' ) );
        set_transient( 'wpb2el_notice', [ 'type' => 'success', 'message' => 'Einstellungen gespeichert.' ], 30 );
        wp_redirect( admin_url( 'tools.php?page=wpb2elementor' ) );
        exit;
    }

    public function handle_convert(): void {
        $page_id = intval( $_POST['page_id'] ?? 0 );
        check_admin_referer( 'wpb2el_convert_' . $page_id );
        $result = $this->convert_page( $page_id );
        set_transient( 'wpb2el_notice', [
            'type'    => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ], 30 );
        wp_redirect( admin_url( 'tools.php?page=wpb2elementor' ) );
        exit;
    }

    public function handle_convert_all(): void {
        check_admin_referer( 'wpb2el_convert_all' );
        $pages  = $this->get_pages();
        $count  = 0;
        $errors = 0;
        foreach ( $pages as $page ) {
            if ( $page['status'] === 'elementor' ) continue;
            $this->convert_page( $page['id'] )['success'] ? $count++ : $errors++;
        }
        $msg = "Konvertiert: {$count} Seiten.";
        if ( $errors ) $msg .= " Fehler: {$errors}.";
        set_transient( 'wpb2el_notice', [ 'type' => 'success', 'message' => $msg ], 30 );
        wp_redirect( admin_url( 'tools.php?page=wpb2elementor' ) );
        exit;
    }

    public function handle_reset(): void {
        $page_id = intval( $_POST['page_id'] ?? 0 );
        check_admin_referer( 'wpb2el_reset_' . $page_id );
        $copy_id = (int) get_post_meta( $page_id, '_wpb2el_copy_id', true );
        if ( ! $copy_id ) {
            set_transient( 'wpb2el_notice', [ 'type' => 'error', 'message' => 'Keine Kopie gefunden.' ], 30 );
        } else {
            wp_delete_post( $copy_id, true );
            delete_post_meta( $page_id, '_wpb2el_copy_id' );
            set_transient( 'wpb2el_notice', [ 'type' => 'success', 'message' => 'Kopie gelöscht.' ], 30 );
        }
        wp_redirect( admin_url( 'tools.php?page=wpb2elementor' ) );
        exit;
    }

    private function convert_page( int $page_id ): array {
        $post = get_post( $page_id );
        if ( ! $post ) return [ 'success' => false, 'message' => "Seite #{$page_id} nicht gefunden." ];

        $content = $post->post_content;
        if ( empty( trim( $content ) ) ) {
            return [ 'success' => false, 'message' => "{$post->post_title}: leerer Inhalt." ];
        }

        $parser    = new WPB2EL_Parser();
        $mapper    = new WPB2EL_Mapper();
        $converter = new WPB2EL_Converter( $mapper );
        $api_key   = get_option( 'wpb2el_api_key', '' );
        $claude    = $api_key ? new WPB2EL_Claude_API( $api_key ) : null;
        $exporter  = new WPB2EL_Prompt_Export();

        try {
            $nodes    = $parser->parse( $content );
            $elements = $converter->convert( $nodes, $claude, $exporter, $post->post_title );

            $copy_id = wp_insert_post( [
                'post_title'   => $post->post_title . ' [Elementor]',
                'post_status'  => 'draft',
                'post_type'    => 'page',
                'post_content' => '',
                'post_author'  => $post->post_author,
            ], true );

            if ( is_wp_error( $copy_id ) ) {
                return [ 'success' => false, 'message' => 'Kopie konnte nicht erstellt werden: ' . $copy_id->get_error_message() ];
            }

            update_post_meta( $copy_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
            update_post_meta( $copy_id, '_elementor_edit_mode', 'builder' );
            update_post_meta( $copy_id, '_elementor_version', '3.0.0' );
            update_post_meta( $page_id, '_wpb2el_copy_id', $copy_id );

            $msg = "✅ {$post->post_title} → Entwurf #{$copy_id} erstellt.";
            if ( $exporter->has_items() ) {
                $exporter->write_file();
                $msg .= ' Unbekannte Widgets → Prompt-Datei gespeichert.';
            }
            return [ 'success' => true, 'message' => $msg ];
        } catch ( \Throwable $e ) {
            return [ 'success' => false, 'message' => 'Fehler: ' . $e->getMessage() ];
        }
    }

    private function get_pages(): array {
        $wc_pages = array_filter( [
            (int) get_option( 'woocommerce_shop_page_id' ),
            (int) get_option( 'woocommerce_cart_page_id' ),
            (int) get_option( 'woocommerce_checkout_page_id' ),
            (int) get_option( 'woocommerce_myaccount_page_id' ),
        ] );

        $posts  = get_posts( [ 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ] );
        $result = [];
        $copy_ids = [];
        foreach ( $posts as $post ) {
            $copy_id = (int) get_post_meta( $post->ID, '_wpb2el_copy_id', true );
            if ( $copy_id ) $copy_ids[] = $copy_id;
        }
        foreach ( $posts as $post ) {
            if ( in_array( $post->ID, $wc_pages, true ) ) continue;
            if ( in_array( $post->ID, $copy_ids, true ) ) continue;
            $copy_id = (int) get_post_meta( $post->ID, '_wpb2el_copy_id', true );
            $result[] = [
                'id'      => $post->ID,
                'title'   => $post->post_title,
                'status'  => $copy_id ? 'elementor' : 'wpbakery',
                'copy_id' => $copy_id ?: null,
            ];
        }
        return $result;
    }

    private function is_container_enabled(): bool {
        $experiments = get_option( 'elementor_experiments', [] );
        return isset( $experiments['container'] ) && $experiments['container'] === 'active';
    }
}
