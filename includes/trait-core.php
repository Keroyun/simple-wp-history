<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait SWH_Core_Trait {

	public function activate() {
		global $wpdb;

		$table_name      = $this->table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_time DATETIME NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			username VARCHAR(60) NOT NULL DEFAULT '',
			action_key VARCHAR(80) NOT NULL,
			action_label VARCHAR(190) NOT NULL,
			object_type VARCHAR(80) NOT NULL DEFAULT '',
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			object_title TEXT NULL,
			ip_address VARCHAR(45) NOT NULL DEFAULT '',
			details LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY event_time (event_time),
			KEY user_id (user_id),
			KEY username (username),
			KEY action_key (action_key),
			KEY object_type (object_type),
			KEY object_id (object_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'swh_db_version', self::DB_VERSION, false );

		if ( false === get_option( 'swh_retention_days', false ) ) {
			add_option( 'swh_retention_days', 180, '', false );
		}

		if ( false === get_option( 'swh_track_admin_clicks', false ) ) {
			add_option( 'swh_track_admin_clicks', 1, '', false );
		}

		if ( false === get_option( 'swh_ip_mode', false ) ) {
			add_option( 'swh_ip_mode', 'masked', '', false );
		}
	}

	private function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'simple_wp_history';
	}

	private function table_exists() {
		if ( null !== $this->table_exists_cache ) {
			return $this->table_exists_cache;
		}

		global $wpdb;
		$table = $this->table_name();

		$this->table_exists_cache = ( $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		) === $table );

		return $this->table_exists_cache;
	}

	private function limit_text( $value, $length = 190 ) {
		$value = sanitize_text_field( (string) $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}
		return substr( $value, 0, $length );
	}

	private function current_user_snapshot() {
		$user = wp_get_current_user();

		return array(
			'user_id'  => ( $user instanceof WP_User ) ? (int) $user->ID : 0,
			'username' => ( $user instanceof WP_User && $user->exists() ) ? $this->limit_text( $user->user_login, 60 ) : '',
		);
	}

	private function current_ip() {
		$mode = get_option( 'swh_ip_mode', 'masked' );

		if ( 'none' === $mode ) {
			return '';
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		if ( 'full' === $mode ) {
			return $ip;
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );
			$parts[3] = '0';
			return implode( '.', $parts );
		}

		$packed = @inet_pton( $ip );
		if ( false !== $packed && 16 === strlen( $packed ) ) {
			$masked = substr( $packed, 0, 8 ) . str_repeat( "\0", 8 );
			$out = @inet_ntop( $masked );
			return $out ? $out : '';
		}

		return '';
	}

	private function sanitize_details( $details ) {
		if ( ! is_array( $details ) ) {
			return array();
		}

		$clean = array();
		$count = 0;

		foreach ( $details as $key => $value ) {
			if ( $count >= 20 ) {
				break;
			}

			$key = sanitize_key( $key );
			if ( '' === $key ) {
				continue;
			}

			if ( is_bool( $value ) ) {
				$clean[ $key ] = $value;
			} elseif ( is_numeric( $value ) ) {
				$clean[ $key ] = $value;
			} elseif ( is_array( $value ) ) {
				$items = array();
				foreach ( array_slice( $value, 0, 20 ) as $item ) {
					$items[] = $this->limit_text( $item, 190 );
				}
				$clean[ $key ] = $items;
			} else {
				$clean[ $key ] = $this->limit_text( $value, 500 );
			}

			$count++;
		}

		return $clean;
	}

	private function log_event( $action_key, $action_label, $args = array() ) {
		if ( ! $this->table_exists() ) {
			return;
		}

		global $wpdb;

		$defaults = array(
			'user_id'      => null,
			'username'     => null,
			'object_type'  => '',
			'object_id'    => 0,
			'object_title' => '',
			'details'      => array(),
			'ip_address'   => null,
		);

		$args    = wp_parse_args( $args, $defaults );
		$current = $this->current_user_snapshot();

		$user_id  = ( null === $args['user_id'] ) ? $current['user_id'] : absint( $args['user_id'] );
		$username = ( null === $args['username'] ) ? $current['username'] : $this->limit_text( $args['username'], 60 );
		$ip       = ( null === $args['ip_address'] ) ? $this->current_ip() : $this->limit_text( $args['ip_address'], 45 );

		$details = $this->sanitize_details( $args['details'] );
		$json    = ! empty( $details ) ? wp_json_encode( $details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';

		$wpdb->insert(
			$this->table_name(),
			array(
				'event_time'   => current_time( 'mysql' ),
				'user_id'      => $user_id,
				'username'     => $username,
				'action_key'   => $this->limit_text( sanitize_key( $action_key ), 80 ),
				'action_label' => $this->limit_text( $action_label, 190 ),
				'object_type'  => $this->limit_text( sanitize_key( $args['object_type'] ), 80 ),
				'object_id'    => absint( $args['object_id'] ),
				'object_title' => $this->limit_text( $args['object_title'], 500 ),
				'ip_address'   => $ip,
				'details'      => $json,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	private function event_category( $action_key ) {
		$map = array(
			'login_success'               => 'Authentication',
			'login_failed'                => 'Security',
			'logout'                      => 'Authentication',
			'content_created'             => 'Content',
			'content_updated'             => 'Content',
			'content_trashed'             => 'Content',
			'content_restored'            => 'Content',
			'content_deleted'             => 'Content',
			'media_added'                 => 'Media',
			'media_updated'               => 'Media',
			'media_deleted'               => 'Media',
			'user_created'                => 'Users',
			'user_updated'                => 'Users',
			'user_deleted'                => 'Security',
			'plugin_activated'            => 'System',
			'plugin_deactivated'          => 'System',
			'theme_switched'              => 'System',
			'software_updated'            => 'System',
			'option_updated'              => 'Settings',
			'option_added'                => 'Settings',
			'option_deleted'              => 'Settings',
			'admin_click'                 => 'Admin UI',
			'woocommerce_order_created'   => 'WooCommerce',
			'woocommerce_order_status'    => 'WooCommerce',
			'woocommerce_product_updated' => 'WooCommerce',
		);

		return isset( $map[ $action_key ] ) ? $map[ $action_key ] : 'Other';
	}

	private function category_actions( $category ) {
		$category = sanitize_text_field( $category );
		$all = array(
			'Authentication' => array( 'login_success', 'logout' ),
			'Security'       => array( 'login_failed', 'user_deleted' ),
			'Content'        => array( 'content_created', 'content_updated', 'content_trashed', 'content_restored', 'content_deleted' ),
			'Media'          => array( 'media_added', 'media_updated', 'media_deleted' ),
			'Users'          => array( 'user_created', 'user_updated' ),
			'System'         => array( 'plugin_activated', 'plugin_deactivated', 'theme_switched', 'software_updated' ),
			'Settings'       => array( 'option_updated', 'option_added', 'option_deleted' ),
			'Admin UI'       => array( 'admin_click' ),
			'WooCommerce'    => array( 'woocommerce_order_created', 'woocommerce_order_status', 'woocommerce_product_updated' ),
		);

		return isset( $all[ $category ] ) ? $all[ $category ] : array();
	}

}
