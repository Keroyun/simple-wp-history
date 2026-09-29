<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait SWH_Admin_UI_Trait {

	public function admin_menu() {
		add_menu_page(
			'Simple WP History',
			'History',
			'manage_options',
			'simple-wp-history',
			array( $this, 'render_admin_page' ),
			'dashicons-backup',
			80
		);
	}

	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'swh_dashboard_widget',
			'Simple WP History',
			array( $this, 'render_dashboard_widget' )
		);
	}

	private function dashboard_count_since( $action_keys, $since ) {
		if ( ! $this->table_exists() || empty( $action_keys ) ) {
			return 0;
		}

		global $wpdb;
		$table = $this->table_name();

		$placeholders = implode( ',', array_fill( 0, count( $action_keys ), '%s' ) );
		$args         = array_merge( $action_keys, array( $since ) );

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE action_key IN ({$placeholders}) AND event_time >= %s",
			$args
		);

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function render_dashboard_widget() {
		if ( ! $this->table_exists() ) {
			echo '<p>' . esc_html__( 'History table is unavailable. Try deactivating and reactivating the plugin.', 'simple-wp-history' ) . '</p>';
			return;
		}

		global $wpdb;

		$table = $this->table_name();
		$today = current_time( 'Y-m-d 00:00:00' );

		$total_today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_time >= %s",
				$today
			)
		);

		$failed_logins = $this->dashboard_count_since( array( 'login_failed' ), $today );
		$content_changes = $this->dashboard_count_since(
			array( 'content_created', 'content_updated', 'content_trashed', 'content_restored', 'content_deleted' ),
			$today
		);
		$admin_actions = $this->dashboard_count_since( array( 'admin_click' ), $today );
		$plugin_changes = $this->dashboard_count_since( array( 'plugin_activated', 'plugin_deactivated' ), $today );
		$user_changes = $this->dashboard_count_since( array( 'user_created', 'user_updated', 'user_deleted', 'user_role_changed' ), $today );
		$security_10m = $this->security_summary( 10 );

		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 8" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		?>
		<style>
			.swh-dash-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:0 0 14px}
			.swh-dash-stat{background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:10px;min-width:0}
			.swh-dash-stat strong{display:block;font-size:20px;line-height:1.2;margin-bottom:3px}
			.swh-dash-stat span{font-size:11px;color:#646970;line-height:1.25;display:block}
			.swh-dash-list{margin:0}.swh-dash-item{border-top:1px solid #f0f0f1;padding:9px 0;margin:0}
			.swh-dash-item:first-child{border-top:0;padding-top:0}.swh-dash-meta{color:#646970;font-size:11px;margin-top:2px}
			.swh-dash-footer{margin:12px 0 0;padding-top:10px;border-top:1px solid #dcdcde;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
			@media(max-width:782px){.swh-dash-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
		</style>

		<div class="swh-dash-stats">
			<div class="swh-dash-stat"><strong><?php echo esc_html( $total_today ); ?></strong><span>Activities today</span></div>
			<div class="swh-dash-stat"><strong><?php echo esc_html( $content_changes ); ?></strong><span>Content changes</span></div>
			<div class="swh-dash-stat"><strong><?php echo esc_html( $failed_logins ); ?></strong><span>Failed logins</span></div>
			<div class="swh-dash-stat"><strong><?php echo esc_html( $admin_actions ); ?></strong><span>Admin actions</span></div>
		</div>

		<?php if ( empty( $rows ) ) : ?>
			<p><?php echo esc_html__( 'No activity recorded yet.', 'simple-wp-history' ); ?></p>
		<?php else : ?>
			<ul class="swh-dash-list">
				<?php foreach ( $rows as $row ) : ?>
					<li class="swh-dash-item">
						<strong><?php echo esc_html( $row->action_label ); ?></strong>
						<?php if ( $row->object_title ) : ?>
							— <?php echo esc_html( $row->object_title ); ?>
						<?php endif; ?>
						<div class="swh-dash-meta">
							<?php echo esc_html( ( $row->username ? $row->username : 'Guest/System' ) . ' • ' . $row->event_time ); ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $failed_logins > 0 || $plugin_changes > 0 || $user_changes > 0 ) : ?>
			<div style="margin:12px 0;padding:10px 12px;border-left:4px solid #dba617;background:#fff8e5;">
				<strong>Important activity today</strong><br>
				<?php if ( $failed_logins > 0 ) : ?><span><?php echo esc_html( $failed_logins ); ?> failed login(s)</span><?php endif; ?>
				<?php if ( $plugin_changes > 0 ) : ?><span> · <?php echo esc_html( $plugin_changes ); ?> plugin change(s)</span><?php endif; ?>
				<?php if ( $user_changes > 0 ) : ?><span> · <?php echo esc_html( $user_changes ); ?> user change(s)</span><?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $security_10m['total'] ) ) : ?>
			<div style="margin:12px 0;padding:10px 12px;border-left:4px solid #d63638;background:#fcf0f1;">
				<strong>Login security:</strong>
				<?php echo esc_html( $security_10m['total'] ); ?> failed login attempt(s) in the last 10 minutes.
			</div>
		<?php endif; ?>

		<div class="swh-dash-footer">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-wp-history' ) ); ?>">View Full History</a>
			<span style="color:#646970;font-size:11px;">Latest 8 recorded activities</span>
		</div>
		<?php
	}

	private function human_details( $details ) {
		if ( empty( $details ) ) {
			return '';
		}

		$decoded = json_decode( $details, true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}

		$parts = array();

		foreach ( $decoded as $key => $value ) {
			if ( is_bool( $value ) ) {
				$value = $value ? 'yes' : 'no';
			} elseif ( is_array( $value ) ) {
				$value = implode( ', ', array_map( array( $this, 'limit_text' ), $value ) );
			} else {
				$value = $this->limit_text( $value, 500 );
			}

			$parts[] = sanitize_text_field( $key ) . ': ' . $value;
		}

		return implode( ' | ', $parts );
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->table_exists() ) {
			echo '<div class="wrap"><h1>Simple WP History</h1><div class="notice notice-error"><p>' .
				esc_html__( 'History table is unavailable. Try deactivating and reactivating the plugin.', 'simple-wp-history' ) .
				'</p></div></div>';
			return;
		}

		global $wpdb;

		$filters  = $this->get_filters();
		$per_page = 50;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;

		$total_items = $this->count_logs( $filters );
		$rows        = $this->query_logs( $filters, $per_page, $offset );
		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );

		$table = $this->table_name();
		$actions = $wpdb->get_col( "SELECT DISTINCT action_key FROM {$table} ORDER BY action_key ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$categories = array( 'Authentication', 'Security', 'Content', 'Media', 'Users', 'System', 'Settings', 'Admin UI', 'WooCommerce' );

		$retention_days     = absint( get_option( 'swh_retention_days', 180 ) );
		$track_admin_clicks = (int) get_option( 'swh_track_admin_clicks', 1 );
		$ip_mode            = get_option( 'swh_ip_mode', 'masked' );
		$excluded_users     = array_map( 'absint', (array) get_option( 'swh_excluded_users', array() ) );
		$excluded_roles     = array_map( 'sanitize_key', (array) get_option( 'swh_excluded_roles', array() ) );
		$excluded_events    = array_map( 'sanitize_key', (array) get_option( 'swh_excluded_events', array() ) );
		$all_users          = get_users( array( 'fields' => array( 'ID', 'user_login' ), 'orderby' => 'user_login' ) );
		$all_roles          = wp_roles()->roles;
		$security_10m       = $this->security_summary( 10 );
		$health             = $this->database_health();
		$user_summary       = ! empty( $filters['username'] ) ? $this->user_activity_summary( $filters['username'] ) : array();

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Simple WP History', 'simple-wp-history' ); ?></h1>
			<p><?php echo esc_html__( 'A lightweight audit trail of meaningful WordPress activity, with privacy-focused logging.', 'simple-wp-history' ); ?></p>

			<div class="nav-tab-wrapper" style="margin-bottom:14px;">
				<a class="nav-tab <?php echo empty( $filters['important'] ) ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-wp-history' ) ); ?>">All Events</a>
				<a class="nav-tab <?php echo ! empty( $filters['important'] ) ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-wp-history&important=1' ) ); ?>">Important Events</a>
			</div>

			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin:14px 0;">
				<div class="swh-settings" style="margin:0;max-width:none;">
					<strong>Login security · last 10 minutes</strong>
					<p style="font-size:22px;margin:8px 0;"><?php echo esc_html( $security_10m['total'] ); ?> failed attempt(s)</p>
					<?php if ( ! empty( $security_10m['usernames'] ) ) : ?>
						<p class="description">Top username: <?php echo esc_html( $security_10m['usernames'][0]->username ); ?> (<?php echo esc_html( $security_10m['usernames'][0]->attempts ); ?>)</p>
					<?php endif; ?>
					<?php if ( ! empty( $security_10m['ips'] ) ) : ?>
						<p class="description">Top IP pattern: <?php echo esc_html( $security_10m['ips'][0]->ip_address ); ?> (<?php echo esc_html( $security_10m['ips'][0]->attempts ); ?>)</p>
					<?php endif; ?>
				</div>
				<div class="swh-settings" style="margin:0;max-width:none;">
					<strong>Database health</strong>
					<p style="font-size:22px;margin:8px 0;"><?php echo esc_html( number_format_i18n( isset( $health['total_rows'] ) ? $health['total_rows'] : 0 ) ); ?> rows</p>
					<p class="description">Size: <?php echo esc_html( size_format( isset( $health['bytes'] ) ? $health['bytes'] : 0 ) ); ?> · Oldest: <?php echo esc_html( ! empty( $health['oldest'] ) ? $health['oldest'] : 'N/A' ); ?></p>
					<p class="description">Retention: <?php echo esc_html( ! empty( $health['retention'] ) ? $health['retention'] . ' days' : 'Keep forever' ); ?></p>
				</div>
			</div>

			<?php if ( ! empty( $user_summary ) ) : ?>
				<div class="swh-settings" style="max-width:none;">
					<strong>User activity summary: <?php echo esc_html( $filters['username'] ); ?></strong>
					<p>Total actions: <?php echo esc_html( $user_summary['total'] ); ?> · Content changes: <?php echo esc_html( $user_summary['content_changes'] ); ?> · Last login: <?php echo esc_html( $user_summary['last_login'] ? $user_summary['last_login'] : 'N/A' ); ?></p>
					<?php if ( ! empty( $user_summary['last_action'] ) ) : ?>
						<p class="description">Last action: <?php echo esc_html( $user_summary['last_action']->action_label ); ?> at <?php echo esc_html( $user_summary['last_action']->event_time ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<style>
				.swh-settings{background:#fff;border:1px solid #dcdcde;padding:16px;margin:16px 0;max-width:980px}
				.swh-filter-grid{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:8px;align-items:end}
				.swh-filter-grid label{display:block;font-size:12px;font-weight:600;margin-bottom:4px}
				.swh-filter-grid input,.swh-filter-grid select{width:100%}
				.swh-badge{display:inline-block;padding:2px 7px;border-radius:10px;background:#f0f0f1;font-size:11px;white-space:nowrap}
				.swh-badge-security{background:#fcf0f1;color:#8a2424}
				.swh-badge-system{background:#f0f6fc;color:#135e96}
				.swh-badge-content{background:#edfaef;color:#1d6b2a}
				.swh-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px}
				.swh-ip-col{display:none}
				.swh-show-ip .swh-ip-col{display:table-cell}
				@media(max-width:1100px){.swh-filter-grid{grid-template-columns:repeat(3,minmax(140px,1fr))}}
				@media(max-width:782px){.swh-filter-grid{grid-template-columns:1fr}}
			</style>

			<?php if ( isset( $_GET['swh_notice'] ) && 'cleared' === sanitize_key( wp_unslash( $_GET['swh_notice'] ) ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'History cleared.', 'simple-wp-history' ); ?></p></div>
			<?php elseif ( isset( $_GET['swh_notice'] ) && 'settings_saved' === sanitize_key( wp_unslash( $_GET['swh_notice'] ) ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'simple-wp-history' ); ?></p></div>
			<?php elseif ( isset( $_GET['swh_notice'] ) && 'cleanup_done' === sanitize_key( wp_unslash( $_GET['swh_notice'] ) ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Old history logs deleted.', 'simple-wp-history' ); ?></p></div>
			<?php endif; ?>

			<div class="swh-settings">
				<h2 style="margin-top:0;">Settings</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="swh_save_settings">
					<?php wp_nonce_field( 'swh_save_settings' ); ?>

					<p>
						<label for="swh_retention_days"><strong>Retention:</strong></label>
						<select id="swh_retention_days" name="swh_retention_days">
							<option value="0" <?php selected( $retention_days, 0 ); ?>>Keep forever</option>
							<option value="30" <?php selected( $retention_days, 30 ); ?>>30 days</option>
							<option value="90" <?php selected( $retention_days, 90 ); ?>>90 days</option>
							<option value="180" <?php selected( $retention_days, 180 ); ?>>180 days</option>
							<option value="365" <?php selected( $retention_days, 365 ); ?>>365 days</option>
						</select>
					</p>

					<p>
						<label for="swh_ip_mode"><strong>IP storage:</strong></label>
						<select id="swh_ip_mode" name="swh_ip_mode">
							<option value="masked" <?php selected( $ip_mode, 'masked' ); ?>>Masked IP (recommended)</option>
							<option value="full" <?php selected( $ip_mode, 'full' ); ?>>Full IP</option>
							<option value="none" <?php selected( $ip_mode, 'none' ); ?>>Do not store IP</option>
						</select>
					</p>

					<p>
						<label>
							<input type="checkbox" name="swh_track_admin_clicks" value="1" <?php checked( $track_admin_clicks, 1 ); ?>>
							<strong>Track meaningful clicks/actions inside wp-admin</strong>
						</label>
					</p>

					<p class="description">
						Click tracking stores safe metadata such as button/link labels and admin screen paths only. Typed field values, passwords, cookies, nonces and authentication tokens are not stored.
					</p>

					<hr>
					<h3>Log exclusions</h3>
					<p class="description">Excluded users, roles and event types will not be written to the history table from this point onward.</p>
					<p>
						<label><strong>Exclude users</strong></label><br>
						<select name="swh_excluded_users[]" multiple size="5" style="min-width:320px;">
							<?php foreach ( $all_users as $u ) : ?>
								<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( in_array( (int) $u->ID, $excluded_users, true ) ); ?>><?php echo esc_html( $u->user_login ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label><strong>Exclude roles</strong></label><br>
						<select name="swh_excluded_roles[]" multiple size="5" style="min-width:320px;">
							<?php foreach ( $all_roles as $role_key => $role ) : ?>
								<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( in_array( $role_key, $excluded_roles, true ) ); ?>><?php echo esc_html( $role['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label><strong>Exclude event types</strong></label><br>
						<select name="swh_excluded_events[]" multiple size="7" style="min-width:320px;">
							<?php foreach ( $this->all_known_action_keys() as $event_key ) : ?>
								<option value="<?php echo esc_attr( $event_key ); ?>" <?php selected( in_array( $event_key, $excluded_events, true ) ); ?>><?php echo esc_html( $event_key ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p><button class="button button-primary">Save Settings</button></p>
				</form>
			</div>

			<form method="get" style="background:#fff;border:1px solid #dcdcde;padding:14px;margin:16px 0;">
				<input type="hidden" name="page" value="simple-wp-history">
				<?php if ( ! empty( $filters['important'] ) ) : ?><input type="hidden" name="important" value="1"><?php endif; ?>
				<div class="swh-filter-grid">
					<div>
						<label for="swh_search">Search</label>
						<input id="swh_search" type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Page, event, user...">
					</div>
					<div>
						<label for="swh_username">User</label>
						<select id="swh_username" name="username">
							<option value="">All users</option>
							<?php foreach ( $all_users as $u ) : ?>
								<option value="<?php echo esc_attr( $u->user_login ); ?>" <?php selected( $filters['username'], $u->user_login ); ?>><?php echo esc_html( $u->user_login ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label for="swh_category">Category</label>
						<select id="swh_category" name="category">
							<option value="">All categories</option>
							<?php foreach ( $categories as $category ) : ?>
								<option value="<?php echo esc_attr( $category ); ?>" <?php selected( $filters['category'], $category ); ?>><?php echo esc_html( $category ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label for="swh_action_key">Event</label>
						<select id="swh_action_key" name="action_key">
							<option value="">All events</option>
							<?php foreach ( $actions as $action ) : ?>
								<option value="<?php echo esc_attr( $action ); ?>" <?php selected( $filters['action_key'], $action ); ?>><?php echo esc_html( $action ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label for="swh_date_from">From</label>
						<input id="swh_date_from" type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
					</div>
					<div>
						<label for="swh_date_to">To</label>
						<input id="swh_date_to" type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
					</div>
				</div>
				<div class="swh-actions">
					<button class="button button-primary">Filter</button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-wp-history' ) ); ?>">Reset</a>
				</div>
			</form>

			<div class="swh-actions" style="justify-content:space-between;">
				<div>
					<strong><?php echo esc_html( number_format_i18n( $total_items ) ); ?></strong> matching events
				</div>
				<div style="display:flex;gap:8px;flex-wrap:wrap;">
					<button type="button" class="button" id="swh-toggle-ip" aria-pressed="false">Show IP</button>
					<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="swh_export_json">
						<?php wp_nonce_field( 'swh_export_json' ); ?>
						<?php foreach ( $filters as $key => $value ) : ?>
							<?php if ( '' !== $value ) : ?>
								<input type="hidden" name="<?php echo esc_attr( 'search' === $key ? 's' : $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
							<?php endif; ?>
						<?php endforeach; ?>
						<button class="button">Export JSON</button>
					</form>

					<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="swh_export_csv">
						<?php wp_nonce_field( 'swh_export_csv' ); ?>
						<?php foreach ( $filters as $key => $value ) : ?>
							<?php if ( '' !== $value ) : ?>
								<input type="hidden" name="<?php echo esc_attr( 'search' === $key ? 's' : $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
							<?php endif; ?>
						<?php endforeach; ?>
						<button class="button">Export CSV</button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Delete logs older than the selected age?');" style="display:flex;gap:6px;">
						<input type="hidden" name="action" value="swh_cleanup_logs">
						<?php wp_nonce_field( 'swh_cleanup_logs' ); ?>
						<select name="days">
							<option value="30">Older than 30 days</option>
							<option value="90">Older than 90 days</option>
							<option value="180">Older than 180 days</option>
						</select>
						<button class="button">Delete Old Logs</button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Clear all history logs? This cannot be undone.');">
						<input type="hidden" name="action" value="swh_clear_logs">
						<?php wp_nonce_field( 'swh_clear_logs' ); ?>
						<button class="button button-secondary">Clear All Logs</button>
					</form>
				</div>
			</div>

			<div style="overflow-x:auto;margin-top:12px;">
				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:145px;">Time</th>
							<th style="width:115px;">Category</th>
							<th style="width:130px;">User</th>
							<th style="width:190px;">Event</th>
							<th>Object</th>
							<th class="swh-ip-col" style="width:135px;">IP</th>
							<th>Details</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr><td colspan="7">No history found for the selected filters.</td></tr>
						<?php else : ?>
							<?php foreach ( $rows as $row ) : ?>
								<?php
								$category = $this->event_category( $row->action_key );
								$class = 'swh-badge';
								if ( 'Security' === $category ) {
									$class .= ' swh-badge-security';
								} elseif ( 'System' === $category ) {
									$class .= ' swh-badge-system';
								} elseif ( 'Content' === $category ) {
									$class .= ' swh-badge-content';
								}
								?>
								<tr>
									<td><?php echo esc_html( $row->event_time ); ?></td>
									<td><span class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $category ); ?></span></td>
									<td><?php echo esc_html( $row->username ? $row->username . ( $row->user_id ? ' (#' . $row->user_id . ')' : '' ) : 'Guest/System' ); ?></td>
									<td><strong><?php echo esc_html( $row->action_label ); ?></strong><br><code><?php echo esc_html( $row->action_key ); ?></code></td>
									<td>
										<?php if ( $row->object_type ) : ?><strong><?php echo esc_html( $row->object_type ); ?></strong><?php endif; ?>
										<?php if ( $row->object_id ) : ?> #<?php echo esc_html( $row->object_id ); ?><?php endif; ?>
										<?php if ( $row->object_title ) : ?><br><?php echo esc_html( $row->object_title ); ?><?php endif; ?>
									</td>
									<td class="swh-ip-col"><code><?php echo esc_html( $row->ip_address ); ?></code></td>
									<td><?php echo esc_html( $this->human_details( $row->details ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages" style="margin:16px 0;">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'current'   => $paged,
									'total'     => $total_pages,
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								)
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>

			<script>
			(function(){
				var button = document.getElementById('swh-toggle-ip');
				if (!button) {
					return;
				}

				button.addEventListener('click', function(){
					var wrap = document.querySelector('.wrap');
					if (!wrap) {
						return;
					}

					var showing = wrap.classList.toggle('swh-show-ip');
					button.textContent = showing ? 'Hide IP' : 'Show IP';
					button.setAttribute('aria-pressed', showing ? 'true' : 'false');
				});
			})();
			</script>
		</div>
		<?php
	}

}
