<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait SWH_Advanced_Trait {

	private function important_action_keys() {
		return array(
			'login_failed',
			'user_created',
			'user_deleted',
			'user_role_changed',
			'plugin_activated',
			'plugin_deactivated',
			'theme_switched',
			'software_updated',
		);
	}

	private function all_known_action_keys() {
		return array(
			'login_success','login_failed','logout',
			'content_created','content_updated','content_trashed','content_restored','content_deleted',
			'media_added','media_updated','media_deleted',
			'user_created','user_updated','user_deleted','user_role_changed',
			'plugin_activated','plugin_deactivated','theme_switched','software_updated',
			'option_updated','option_added','option_deleted','admin_click',
			'woocommerce_order_created','woocommerce_order_status','woocommerce_product_updated',
			'woocommerce_product_published','woocommerce_product_unpublished',
			'woocommerce_coupon_created','woocommerce_coupon_updated','woocommerce_coupon_deleted',
			'woocommerce_setting_updated',
		);
	}

	private function should_exclude_event( $action_key, $user_id = 0 ) {
		$excluded_events = (array) get_option( 'swh_excluded_events', array() );
		if ( in_array( $action_key, $excluded_events, true ) ) {
			return true;
		}

		if ( $user_id > 0 ) {
			$excluded_users = array_map( 'absint', (array) get_option( 'swh_excluded_users', array() ) );
			if ( in_array( (int) $user_id, $excluded_users, true ) ) {
				return true;
			}

			$excluded_roles = array_map( 'sanitize_key', (array) get_option( 'swh_excluded_roles', array() ) );
			if ( ! empty( $excluded_roles ) ) {
				$user = get_userdata( $user_id );
				if ( $user instanceof WP_User && array_intersect( $excluded_roles, (array) $user->roles ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function security_summary( $minutes = 10 ) {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			return array( 'total' => 0, 'usernames' => array(), 'ips' => array() );
		}

		$table = $this->table_name();
		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - ( absint( $minutes ) * MINUTE_IN_SECONDS ) );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE action_key = %s AND event_time >= %s",
				'login_failed',
				$since
			)
		);

		$usernames = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT username, COUNT(*) AS attempts FROM {$table}
				WHERE action_key = %s AND event_time >= %s AND username <> ''
				GROUP BY username ORDER BY attempts DESC LIMIT 5",
				'login_failed',
				$since
			)
		);

		$ips = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ip_address, COUNT(*) AS attempts FROM {$table}
				WHERE action_key = %s AND event_time >= %s AND ip_address <> ''
				GROUP BY ip_address ORDER BY attempts DESC LIMIT 5",
				'login_failed',
				$since
			)
		);

		return array( 'total' => $total, 'usernames' => $usernames, 'ips' => $ips );
	}

	private function user_activity_summary( $username ) {
		global $wpdb;

		$username = $this->limit_text( $username, 60 );
		if ( '' === $username || ! $this->table_exists() ) {
			return array();
		}

		$table = $this->table_name();

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE username = %s", $username )
		);

		$last_action = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT event_time, action_label, object_title FROM {$table} WHERE username = %s ORDER BY id DESC LIMIT 1",
				$username
			)
		);

		$last_login = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT event_time FROM {$table} WHERE username = %s AND action_key = %s ORDER BY id DESC LIMIT 1",
				$username,
				'login_success'
			)
		);

		$content_changes = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE username = %s AND action_key IN (%s,%s,%s,%s,%s)",
				$username,
				'content_created',
				'content_updated',
				'content_trashed',
				'content_restored',
				'content_deleted'
			)
		);

		return array(
			'total'           => $total,
			'last_action'     => $last_action,
			'last_login'      => $last_login,
			'content_changes' => $content_changes,
		);
	}

	private function database_health() {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			return array();
		}

		$table = $this->table_name();

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$oldest = $wpdb->get_var( "SELECT MIN(event_time) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ) );
		$bytes = $status ? (int) $status->Data_length + (int) $status->Index_length : 0;

		return array(
			'total_rows' => $total,
			'oldest'     => $oldest,
			'bytes'      => $bytes,
			'retention'  => absint( get_option( 'swh_retention_days', 180 ) ),
		);
	}

	public function handle_cleanup_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'simple-wp-history' ) );
		}

		check_admin_referer( 'swh_cleanup_logs' );

		$days = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 0;
		if ( ! in_array( $days, array( 30, 90, 180 ), true ) ) {
			wp_die( esc_html__( 'Invalid cleanup period.', 'simple-wp-history' ) );
		}

		global $wpdb;
		$table  = $this->table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - ( $days * DAY_IN_SECONDS ) );

		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE event_time < %s", $cutoff )
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'simple-wp-history',
					'swh_notice' => 'cleanup_done',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_export_json() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'simple-wp-history' ) );
		}

		check_admin_referer( 'swh_export_json' );

		$filters = $this->get_filters();
		$rows    = $this->query_logs( $filters, 10000, 0 );

		$data = array();
		foreach ( $rows as $row ) {
			$data[] = array(
				'time'         => $row->event_time,
				'category'     => $this->event_category( $row->action_key ),
				'user_id'      => (int) $row->user_id,
				'username'     => $row->username,
				'action_key'   => $row->action_key,
				'action_label' => $row->action_label,
				'object_type'  => $row->object_type,
				'object_id'    => (int) $row->object_id,
				'object_title' => $row->object_title,
				'ip_address'   => $row->ip_address,
				'details'      => $row->details ? json_decode( $row->details, true ) : array(),
			);
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="simple-wp-history-' . gmdate( 'Y-m-d-His' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public function log_wc_product_status( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		if ( 'publish' === $new_status ) {
			$this->log_event(
				'woocommerce_product_published',
				'WooCommerce product published',
				array(
					'object_type'  => 'product',
					'object_id'    => $post->ID,
					'object_title' => $post->post_title,
					'details'      => array( 'old_status' => $old_status, 'new_status' => $new_status ),
				)
			);
		} elseif ( 'publish' === $old_status ) {
			$this->log_event(
				'woocommerce_product_unpublished',
				'WooCommerce product unpublished',
				array(
					'object_type'  => 'product',
					'object_id'    => $post->ID,
					'object_title' => $post->post_title,
					'details'      => array( 'old_status' => $old_status, 'new_status' => $new_status ),
				)
			);
		}
	}

	public function log_wc_coupon_save( $post_id, $post, $update ) {
		if ( ! $post instanceof WP_Post || 'shop_coupon' !== $post->post_type || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->log_event(
			$update ? 'woocommerce_coupon_updated' : 'woocommerce_coupon_created',
			$update ? 'WooCommerce coupon updated' : 'WooCommerce coupon created',
			array(
				'object_type'  => 'shop_coupon',
				'object_id'    => $post_id,
				'object_title' => $post->post_title,
			)
		);
	}

	public function log_wc_coupon_deleted( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'shop_coupon' !== $post->post_type ) {
			return;
		}

		$this->log_event(
			'woocommerce_coupon_deleted',
			'WooCommerce coupon deleted',
			array(
				'object_type'  => 'shop_coupon',
				'object_id'    => $post_id,
				'object_title' => $post->post_title,
			)
		);
	}

}
