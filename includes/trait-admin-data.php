<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait SWH_Admin_Data_Trait {

	private function allowed_retention_days() {
		return array( 0, 30, 90, 180, 365 );
	}

	private function allowed_ip_modes() {
		return array( 'full', 'masked', 'none' );
	}

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'simple-wp-history' ) );
		}

		check_admin_referer( 'swh_save_settings' );

		$retention = isset( $_POST['swh_retention_days'] ) ? absint( $_POST['swh_retention_days'] ) : 180;
		if ( ! in_array( $retention, $this->allowed_retention_days(), true ) ) {
			$retention = 180;
		}

		$ip_mode = isset( $_POST['swh_ip_mode'] ) ? sanitize_key( wp_unslash( $_POST['swh_ip_mode'] ) ) : 'masked';
		if ( ! in_array( $ip_mode, $this->allowed_ip_modes(), true ) ) {
			$ip_mode = 'masked';
		}

		$track_admin_clicks = isset( $_POST['swh_track_admin_clicks'] ) ? 1 : 0;

		$excluded_users = isset( $_POST['swh_excluded_users'] ) && is_array( $_POST['swh_excluded_users'] )
			? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['swh_excluded_users'] ) ) ) ) )
			: array();

		$excluded_roles = isset( $_POST['swh_excluded_roles'] ) && is_array( $_POST['swh_excluded_roles'] )
			? array_values( array_unique( array_map( 'sanitize_key', wp_unslash( $_POST['swh_excluded_roles'] ) ) ) )
			: array();

		$excluded_events = isset( $_POST['swh_excluded_events'] ) && is_array( $_POST['swh_excluded_events'] )
			? array_values( array_intersect( array_map( 'sanitize_key', wp_unslash( $_POST['swh_excluded_events'] ) ), $this->all_known_action_keys() ) )
			: array();

		update_option( 'swh_retention_days', $retention, false );
		update_option( 'swh_ip_mode', $ip_mode, false );
		update_option( 'swh_track_admin_clicks', $track_admin_clicks, false );
		update_option( 'swh_excluded_users', $excluded_users, false );
		update_option( 'swh_excluded_roles', $excluded_roles, false );
		update_option( 'swh_excluded_events', $excluded_events, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'simple-wp-history',
					'swh_notice' => 'settings_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function maybe_cleanup_old_logs() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) || ! $this->table_exists() ) {
			return;
		}

		$retention = absint( get_option( 'swh_retention_days', 180 ) );
		if ( 0 === $retention || ! in_array( $retention, $this->allowed_retention_days(), true ) ) {
			return;
		}

		$last_cleanup = absint( get_option( 'swh_last_cleanup_timestamp', 0 ) );
		if ( $last_cleanup && ( time() - $last_cleanup ) < DAY_IN_SECONDS ) {
			return;
		}

		if ( get_transient( 'swh_cleanup_lock' ) ) {
			return;
		}

		set_transient( 'swh_cleanup_lock', 1, 5 * MINUTE_IN_SECONDS );

		global $wpdb;
		$table  = $this->table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - ( $retention * DAY_IN_SECONDS ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE event_time < %s",
				$cutoff
			)
		);

		update_option( 'swh_last_cleanup_timestamp', time(), false );
		delete_transient( 'swh_cleanup_lock' );
	}

	public function handle_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'simple-wp-history' ) );
		}

		check_admin_referer( 'swh_clear_logs' );

		if ( $this->table_exists() ) {
			global $wpdb;
			$table = $this->table_name();
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'simple-wp-history',
					'swh_notice' => 'cleared',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function valid_date( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $date ) );
		return checkdate( $month, $day, $year ) ? $date : '';
	}

	private function get_filters() {
		return array(
			'action_key' => isset( $_GET['action_key'] ) ? sanitize_key( wp_unslash( $_GET['action_key'] ) ) : '',
			'category'   => isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '',
			'username'   => isset( $_GET['username'] ) ? $this->limit_text( wp_unslash( $_GET['username'] ), 60 ) : '',
			'search'     => isset( $_GET['s'] ) ? $this->limit_text( wp_unslash( $_GET['s'] ), 190 ) : '',
			'date_from'  => isset( $_GET['date_from'] ) ? $this->valid_date( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'    => isset( $_GET['date_to'] ) ? $this->valid_date( wp_unslash( $_GET['date_to'] ) ) : '',
			'important'    => isset( $_GET['important'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['important'] ) ) ? '1' : '',
		);
	}

	private function build_where( $filters ) {
		global $wpdb;

		$where = array( '1=1' );
		$args  = array();

		if ( ! empty( $filters['action_key'] ) ) {
			$where[] = 'action_key = %s';
			$args[]  = $filters['action_key'];
		}

		if ( ! empty( $filters['important'] ) ) {
			$important = $this->important_action_keys();
			$placeholders = implode( ',', array_fill( 0, count( $important ), '%s' ) );
			$where[] = "action_key IN ({$placeholders})";
			foreach ( $important as $action ) {
				$args[] = $action;
			}
		}

		if ( ! empty( $filters['category'] ) ) {
			$actions = $this->category_actions( $filters['category'] );
			if ( ! empty( $actions ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $actions ), '%s' ) );
				$where[] = "action_key IN ({$placeholders})";
				foreach ( $actions as $action ) {
					$args[] = $action;
				}
			}
		}

		if ( ! empty( $filters['username'] ) ) {
			$where[] = 'username LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $filters['username'] ) . '%';
		}

		if ( ! empty( $filters['search'] ) ) {
			$like = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[] = '(object_title LIKE %s OR action_label LIKE %s OR username LIKE %s OR object_type LIKE %s)';
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[] = 'event_time >= %s';
			$args[]  = $filters['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[] = 'event_time <= %s';
			$args[]  = $filters['date_to'] . ' 23:59:59';
		}

		return array( implode( ' AND ', $where ), $args );
	}

	private function query_logs( $filters, $limit = 50, $offset = 0 ) {
		global $wpdb;

		$table = $this->table_name();
		list( $where_sql, $args ) = $this->build_where( $filters );

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$args[] = absint( $limit );
		$args[] = absint( $offset );

		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	}

	private function count_logs( $filters ) {
		global $wpdb;

		$table = $this->table_name();
		list( $where_sql, $args ) = $this->build_where( $filters );

		$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		if ( empty( $args ) ) {
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
	}

	private function csv_safe( $value ) {
		$value = (string) $value;
		if ( preg_match( '/^[=\+\-@]/', $value ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	public function handle_export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'simple-wp-history' ) );
		}

		check_admin_referer( 'swh_export_csv' );

		if ( ! $this->table_exists() ) {
			wp_die( esc_html__( 'History table is not available.', 'simple-wp-history' ) );
		}

		$filters = $this->get_filters();
		$rows    = $this->query_logs( $filters, 10000, 0 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="simple-wp-history-' . gmdate( 'Y-m-d-His' ) . '.csv"' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, array( 'Time', 'Category', 'User', 'Event', 'Object Type', 'Object ID', 'Object Title', 'IP Address', 'Details' ) );

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					$this->csv_safe( $row->event_time ),
					$this->csv_safe( $this->event_category( $row->action_key ) ),
					$this->csv_safe( $row->username ),
					$this->csv_safe( $row->action_label ),
					$this->csv_safe( $row->object_type ),
					$this->csv_safe( $row->object_id ),
					$this->csv_safe( $row->object_title ),
					$this->csv_safe( $row->ip_address ),
					$this->csv_safe( $row->details ),
				)
			);
		}

		fclose( $output );
		exit;
	}

}
