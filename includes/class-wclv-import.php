<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCLV_Import {

	const DISMISSED_OPTION = 'wclv_iconic_import_dismissed';

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_notice' ) );
		add_action( 'admin_post_wclv_import_iconic', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_wclv_dismiss_iconic_import', array( __CLASS__, 'handle_dismiss' ) );
	}

	private static function iconic_tables_exist() {
		global $wpdb;

		$main  = $wpdb->prefix . 'iconic_woo_linked_variations';
		$terms = $wpdb->prefix . 'iconic_woo_linked_variations_terms';

		$main_exists  = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $main ) ) === $main;
		$terms_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $terms ) ) === $terms;

		return $main_exists && $terms_exists;
	}

	private static function get_iconic_group_count() {
		global $wpdb;
		$table = $wpdb->prefix . 'iconic_woo_linked_variations';
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	private static function is_our_cpt_screen() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		return $screen->post_type === WCLV_Post_Type::POST_TYPE;
	}

	public static function maybe_show_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! self::is_our_cpt_screen() ) {
			return;
		}

		if ( get_option( self::DISMISSED_OPTION ) ) {
			return;
		}

		if ( ! self::iconic_tables_exist() ) {
			return;
		}

		$count = self::get_iconic_group_count();
		if ( 0 === $count ) {
			return;
		}

		$result   = isset( $_GET['wclv_import_result'] ) ? sanitize_text_field( wp_unslash( $_GET['wclv_import_result'] ) ) : '';
		$imported = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
		$errors   = isset( $_GET['errors'] ) ? absint( $_GET['errors'] ) : 0;

		if ( 'success' === $result ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Iconic Import Complete.', 'wc-linked-variations' ); ?></strong>
					<?php
					/* translators: 1: number of groups imported, 2: number of errors */
					printf(
						esc_html__( '%1$d group(s) imported, %2$d error(s).', 'wc-linked-variations' ),
						(int) $imported,
						(int) $errors
					);
					?>
				</p>
			</div>
			<?php
			return;
		}

		$import_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wclv_import_iconic' ),
			'wclv_import_iconic',
			'wclv_import_nonce'
		);

		$dismiss_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wclv_dismiss_iconic_import' ),
			'wclv_dismiss_iconic_import',
			'wclv_dismiss_nonce'
		);

		?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'Iconic WooCommerce Linked Variations data detected.', 'wc-linked-variations' ); ?></strong>
				<?php
				/* translators: %d: number of importable groups */
				printf(
					esc_html__( 'Found %d group(s) that can be imported into WC Linked Variations.', 'wc-linked-variations' ),
					(int) $count
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $import_url ); ?>" class="button button-primary">
					<?php
					/* translators: %d: number of importable groups */
					printf( esc_html__( 'Import %d Group(s)', 'wc-linked-variations' ), (int) $count );
					?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button" style="margin-left: 8px;">
					<?php esc_html_e( 'Dismiss', 'wc-linked-variations' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	public static function handle_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized.', 'wc-linked-variations' ) );
		}

		check_admin_referer( 'wclv_dismiss_iconic_import', 'wclv_dismiss_nonce' );

		update_option( self::DISMISSED_OPTION, 1, false );

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . WCLV_Post_Type::POST_TYPE ) );
		exit;
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized.', 'wc-linked-variations' ) );
		}

		check_admin_referer( 'wclv_import_iconic', 'wclv_import_nonce' );

		$result = self::run_import();

		update_option( self::DISMISSED_OPTION, 1, false );

		wp_safe_redirect( add_query_arg( array(
			'post_type'          => WCLV_Post_Type::POST_TYPE,
			'wclv_import_result' => 'success',
			'imported'           => $result['imported'],
			'errors'             => $result['errors'],
		), admin_url( 'edit.php' ) ) );
		exit;
	}

	private static function run_import() {
		global $wpdb;

		$iconic_table       = $wpdb->prefix . 'iconic_woo_linked_variations';
		$iconic_terms_table = $wpdb->prefix . 'iconic_woo_linked_variations_terms';

		$rows = $wpdb->get_results( "SELECT * FROM {$iconic_table}" );

		$imported = 0;
		$errors   = 0;

		if ( empty( $rows ) ) {
			return compact( 'imported', 'errors' );
		}

		foreach ( $rows as $row ) {
			$title = '';
			if ( ! empty( $row->post_id ) ) {
				$post = get_post( $row->post_id );
				if ( $post ) {
					$title = $post->post_title;
				}
			}

			if ( empty( $title ) ) {
				/* translators: %d: source Iconic group ID */
				$title = sprintf( __( 'Imported Group #%d', 'wc-linked-variations' ), (int) $row->id );
			}

			$new_post_id = wp_insert_post( array(
				'post_title'  => $title,
				'post_type'   => WCLV_Post_Type::POST_TYPE,
				'post_status' => 'publish',
			), true );

			if ( is_wp_error( $new_post_id ) ) {
				$errors++;
				continue;
			}

			$link_by = isset( $row->link_by ) ? $row->link_by : 'specific_products';

			if ( empty( $link_by ) || 'specific_products' === $link_by ) {
				$product_source = 'manual';
				$taxonomy       = '';
			} else {
				$product_source = 'taxonomy';
				$taxonomy       = $link_by;
			}

			$product_ids = maybe_unserialize( $row->product_ids );
			if ( is_array( $product_ids ) ) {
				$product_ids = array_map( 'intval', $product_ids );
			} else {
				$product_ids = array();
			}

			$attributes = maybe_unserialize( $row->attributes );
			if ( ! is_array( $attributes ) ) {
				$attributes = array();
			}

			$style = isset( $row->style ) ? $row->style : 'button';
			if ( 'buttons' === $style ) {
				$style = 'button';
			}

			$taxonomy_terms = array();
			if ( 'taxonomy' === $product_source ) {
				$term_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT term_id FROM {$iconic_terms_table} WHERE lv_id = %d",
						$row->id
					)
				);
				if ( $term_rows ) {
					$taxonomy_terms = array_map( function ( $r ) {
						return (int) $r->term_id;
					}, $term_rows );
				}
			}

			$data = array(
				'product_source' => $product_source,
				'product_ids'    => $product_ids,
				'taxonomy'       => $taxonomy,
				'taxonomy_terms' => $taxonomy_terms,
				'attributes'     => $attributes,
				'show_image'     => ! empty( $row->show_image ) ? 1 : 0,
				'style'          => $style,
			);

			WCLV_Database::save( $new_post_id, $data );
			$imported++;
		}

		return compact( 'imported', 'errors' );
	}
}
