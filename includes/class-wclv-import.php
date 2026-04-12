<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCLV_Import {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 20 );
		add_action( 'admin_post_wclv_import_iconic', array( __CLASS__, 'handle_import' ) );
	}

	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'Import Linked Variations', 'wc-linked-variations' ),
			__( 'Import from Iconic', 'wc-linked-variations' ),
			'manage_options',
			'wclv-import',
			array( __CLASS__, 'render_page' )
		);
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

	public static function render_page() {
		$result = isset( $_GET['wclv_import_result'] ) ? sanitize_text_field( $_GET['wclv_import_result'] ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import from Iconic WooCommerce Linked Variations', 'wc-linked-variations' ); ?></h1>

			<?php if ( 'success' === $result ) : ?>
				<div class="notice notice-success">
					<p>
						<?php
						printf(
							__( 'Import complete. %d group(s) imported, %d skipped, %d error(s).', 'wc-linked-variations' ),
							isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0,
							isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0,
							isset( $_GET['errors'] ) ? absint( $_GET['errors'] ) : 0
						);
						?>
					</p>
				</div>
			<?php elseif ( 'no_tables' === $result ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Iconic plugin database tables were not found.', 'wc-linked-variations' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! self::iconic_tables_exist() ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php esc_html_e(
							'Iconic WooCommerce Linked Variations data was not found in this database. The Iconic plugin must have been active on this site at some point for its tables to exist.',
							'wc-linked-variations'
						); ?>
					</p>
				</div>
			<?php else : ?>
				<?php $count = self::get_iconic_group_count(); ?>
				<?php if ( 0 === $count ) : ?>
					<p><?php esc_html_e( 'The Iconic tables exist but contain no linked variation groups.', 'wc-linked-variations' ); ?></p>
				<?php else : ?>
					<p>
						<?php
						printf(
							__( 'Found <strong>%d</strong> linked variation group(s) in the Iconic plugin database. Click the button below to import them.', 'wc-linked-variations' ),
							$count
						);
						?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wclv_import_iconic">
						<?php wp_nonce_field( 'wclv_import_iconic', 'wclv_import_nonce' ); ?>
						<p>
							<button type="submit" class="button button-primary">
								<?php printf( __( 'Import %d Group(s)', 'wc-linked-variations' ), $count ); ?>
							</button>
						</p>
					</form>
					<p class="description">
						<?php esc_html_e( 'This will create new linked variation groups in WC Linked Variations. Existing groups will not be affected. You can safely run this multiple times, but duplicates will be created.', 'wc-linked-variations' ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized.', 'wc-linked-variations' ) );
		}

		check_admin_referer( 'wclv_import_iconic', 'wclv_import_nonce' );

		if ( ! self::iconic_tables_exist() ) {
			wp_safe_redirect( add_query_arg( array(
				'post_type'          => 'product',
				'page'               => 'wclv-import',
				'wclv_import_result' => 'no_tables',
			), admin_url( 'edit.php' ) ) );
			exit;
		}

		$result = self::run_import();

		wp_safe_redirect( add_query_arg( array(
			'post_type'          => 'product',
			'page'               => 'wclv-import',
			'wclv_import_result' => 'success',
			'imported'           => $result['imported'],
			'skipped'            => $result['skipped'],
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
		$skipped  = 0;
		$errors   = 0;

		if ( empty( $rows ) ) {
			return compact( 'imported', 'skipped', 'errors' );
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
				$title = sprintf( __( 'Imported Group #%d', 'wc-linked-variations' ), $row->id );
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

		return compact( 'imported', 'skipped', 'errors' );
	}
}
