<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait SWH_Events_Trait {

	public function log_login( $user_login, $user ) {
		$this->log_event(
			'login_success',
			'User logged in',
			array(
				'user_id'      => (int) $user->ID,
				'username'     => $user_login,
				'object_type'  => 'user',
				'object_id'    => (int) $user->ID,
				'object_title' => $user->display_name,
			)
		);
	}

	public function log_failed_login( $username ) {
		$username = sanitize_user( $username, true );

		$this->log_event(
			'login_failed',
			'Failed login attempt',
			array(
				'user_id'  => 0,
				'username' => $username,
				'details'  => array( 'attempted_username' => $username ),
			)
		);
	}

	public function log_logout( $user_id ) {
		$user = get_userdata( $user_id );

		$this->log_event(
			'logout',
			'User logged out',
			array(
				'user_id'      => absint( $user_id ),
				'username'     => $user ? $user->user_login : '',
				'object_type'  => 'user',
				'object_id'    => absint( $user_id ),
				'object_title' => $user ? $user->display_name : '',
			)
		);
	}

	public function capture_pre_post_update( $post_id, $data ) {
		$post = get_post( $post_id );

		if ( ! $post || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->pre_update_posts[ $post_id ] = array(
			'title'  => $this->limit_text( $post->post_title, 190 ),
			'slug'   => $this->limit_text( $post->post_name, 190 ),
			'status' => $this->limit_text( $post->post_status, 40 ),
		);
	}

	public function log_post_save( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( empty( $post->post_type ) || in_array( $post->post_type, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
			return;
		}

		$details = array(
			'post_type' => $post->post_type,
			'status'    => $post->post_status,
		);

		if ( $update && isset( $this->pre_update_posts[ $post_id ] ) ) {
			$old = $this->pre_update_posts[ $post_id ];
			$changed = array();

			if ( $old['title'] !== $post->post_title ) {
				$changed[] = 'title';
				$details['old_title'] = $old['title'];
				$details['new_title'] = $this->limit_text( $post->post_title, 190 );
			}

			if ( $old['slug'] !== $post->post_name ) {
				$changed[] = 'slug';
				$details['old_slug'] = $old['slug'];
				$details['new_slug'] = $this->limit_text( $post->post_name, 190 );
			}

			if ( $old['status'] !== $post->post_status ) {
				$changed[] = 'status';
				$details['old_status'] = $old['status'];
				$details['new_status'] = $this->limit_text( $post->post_status, 40 );
			}

			if ( ! empty( $changed ) ) {
				$details['changed'] = $changed;
			}

			unset( $this->pre_update_posts[ $post_id ] );
		}

		$this->log_event(
			$update ? 'content_updated' : 'content_created',
			$update ? 'Content updated' : 'Content created',
			array(
				'object_type'  => $post->post_type,
				'object_id'    => $post_id,
				'object_title' => get_the_title( $post_id ),
				'details'      => $details,
			)
		);
	}

	public function log_post_delete( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->log_event(
			'content_deleted',
			'Content permanently deleted',
			array(
				'object_type'  => $post->post_type,
				'object_id'    => $post_id,
				'object_title' => $post->post_title,
			)
		);
	}

	public function log_post_trashed( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$this->log_event(
			'content_trashed',
			'Content moved to trash',
			array(
				'object_type'  => $post->post_type,
				'object_id'    => $post_id,
				'object_title' => $post->post_title,
			)
		);
	}

	public function log_post_untrashed( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$this->log_event(
			'content_restored',
			'Content restored from trash',
			array(
				'object_type'  => $post->post_type,
				'object_id'    => $post_id,
				'object_title' => $post->post_title,
			)
		);
	}

	public function log_media_added( $attachment_id ) {
		$this->log_media_event( $attachment_id, 'media_added', 'Media uploaded' );
	}

	public function log_media_updated( $attachment_id ) {
		$this->log_media_event( $attachment_id, 'media_updated', 'Media updated' );
	}

	public function log_media_deleted( $attachment_id ) {
		$this->log_media_event( $attachment_id, 'media_deleted', 'Media deleted' );
	}

	private function log_media_event( $attachment_id, $action_key, $label ) {
		$post  = get_post( $attachment_id );
		$title = $post ? $post->post_title : 'Attachment #' . absint( $attachment_id );

		$this->log_event(
			$action_key,
			$label,
			array(
				'object_type'  => 'attachment',
				'object_id'    => absint( $attachment_id ),
				'object_title' => $title,
			)
		);
	}

	public function log_user_created( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->log_event(
			'user_created',
			'User created',
			array(
				'object_type'  => 'user',
				'object_id'    => $user_id,
				'object_title' => $user->user_login,
				'details'      => array( 'roles' => array_values( $user->roles ) ),
			)
		);
	}

	public function log_user_updated( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$details = array();

		$old_roles = ( $old_user_data instanceof WP_User ) ? array_values( $old_user_data->roles ) : array();
		$new_roles = array_values( $user->roles );

		if ( $old_roles !== $new_roles ) {
			$details['old_roles'] = $old_roles;
			$details['new_roles'] = $new_roles;
		}

		$this->log_event(
			'user_updated',
			'User profile updated',
			array(
				'object_type'  => 'user',
				'object_id'    => $user_id,
				'object_title' => $user->user_login,
				'details'      => $details,
			)
		);
	}

	public function log_user_deleted( $user_id ) {
		$user  = get_userdata( $user_id );
		$title = $user ? $user->user_login : 'User ID ' . absint( $user_id );

		$this->log_event(
			'user_deleted',
			'User deleted',
			array(
				'object_type'  => 'user',
				'object_id'    => $user_id,
				'object_title' => $title,
			)
		);
	}

	public function log_plugin_activated( $plugin, $network_wide ) {
		$this->log_event(
			'plugin_activated',
			'Plugin activated',
			array(
				'object_type'  => 'plugin',
				'object_title' => $plugin,
				'details'      => array( 'network_wide' => (bool) $network_wide ),
			)
		);
	}

	public function log_plugin_deactivated( $plugin, $network_wide ) {
		$this->log_event(
			'plugin_deactivated',
			'Plugin deactivated',
			array(
				'object_type'  => 'plugin',
				'object_title' => $plugin,
				'details'      => array( 'network_wide' => (bool) $network_wide ),
			)
		);
	}

	public function log_theme_switched( $new_name, $new_theme, $old_theme ) {
		$this->log_event(
			'theme_switched',
			'Theme switched',
			array(
				'object_type'  => 'theme',
				'object_title' => $new_name,
				'details'      => array(
					'new_theme' => $new_theme instanceof WP_Theme ? $new_theme->get_stylesheet() : '',
					'old_theme' => $old_theme instanceof WP_Theme ? $old_theme->get_stylesheet() : '',
				),
			)
		);
	}

	public function log_upgrade( $upgrader, $hook_extra ) {
		delete_transient( 'swh_github_release' );

		if ( empty( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] ) {
			return;
		}

		$type = isset( $hook_extra['type'] ) ? sanitize_key( $hook_extra['type'] ) : 'unknown';
		$details = array( 'type' => $type );

		if ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$details['plugins'] = array_map( 'sanitize_text_field', array_slice( $hook_extra['plugins'], 0, 20 ) );
		} elseif ( isset( $hook_extra['plugin'] ) ) {
			$details['plugin'] = sanitize_text_field( $hook_extra['plugin'] );
		}

		if ( isset( $hook_extra['themes'] ) && is_array( $hook_extra['themes'] ) ) {
			$details['themes'] = array_map( 'sanitize_text_field', array_slice( $hook_extra['themes'], 0, 20 ) );
		} elseif ( isset( $hook_extra['theme'] ) ) {
			$details['theme'] = sanitize_text_field( $hook_extra['theme'] );
		}

		$this->log_event(
			'software_updated',
			'WordPress software updated',
			array(
				'object_type' => $type,
				'details'     => $details,
			)
		);
	}

	private function option_should_be_logged( $option ) {
		$option = strtolower( (string) $option );

		if ( 0 === strpos( $option, 'swh_' ) ) {
			return false;
		}

		$ignored_prefixes = array(
			'_transient_',
			'_site_transient_',
			'_woocommerce_session_',
			'wc_session_',
			'elementor_css_',
		);

		foreach ( $ignored_prefixes as $prefix ) {
			if ( 0 === strpos( $option, $prefix ) ) {
				return false;
			}
		}

		$ignored_exact = array(
			'cron',
			'rewrite_rules',
			'_site_transient_update_plugins',
			'_site_transient_update_themes',
		);

		if ( in_array( $option, $ignored_exact, true ) ) {
			return false;
		}

		$sensitive = array(
			'password',
			'passwd',
			'secret',
			'token',
			'nonce',
			'api_key',
			'apikey',
			'auth',
			'cookie',
			'session',
			'salt',
			'private_key',
			'client_secret',
		);

		foreach ( $sensitive as $needle ) {
			if ( false !== strpos( $option, $needle ) ) {
				return false;
			}
		}

		return true;
	}

	public function log_option_updated( $option, $old_value, $value ) {
		if ( ! $this->option_should_be_logged( $option ) ) {
			return;
		}

		$this->log_event(
			'option_updated',
			'WordPress setting updated',
			array(
				'object_type'  => 'option',
				'object_title' => $option,
			)
		);
	}

	public function log_option_added( $option, $value ) {
		if ( ! $this->option_should_be_logged( $option ) ) {
			return;
		}

		$this->log_event(
			'option_added',
			'WordPress setting added',
			array(
				'object_type'  => 'option',
				'object_title' => $option,
			)
		);
	}

	public function log_option_deleted( $option ) {
		if ( ! $this->option_should_be_logged( $option ) ) {
			return;
		}

		$this->log_event(
			'option_deleted',
			'WordPress setting deleted',
			array(
				'object_type'  => 'option',
				'object_title' => $option,
			)
		);
	}

	public function log_wc_new_order( $order_id ) {
		$this->log_event(
			'woocommerce_order_created',
			'WooCommerce order created',
			array(
				'object_type'  => 'shop_order',
				'object_id'    => absint( $order_id ),
				'object_title' => 'Order #' . absint( $order_id ),
			)
		);
	}

	public function log_wc_order_status_changed( $order_id, $from, $to, $order ) {
		$this->log_event(
			'woocommerce_order_status',
			'WooCommerce order status changed',
			array(
				'object_type'  => 'shop_order',
				'object_id'    => absint( $order_id ),
				'object_title' => 'Order #' . absint( $order_id ),
				'details'      => array(
					'old_status' => $from,
					'new_status' => $to,
				),
			)
		);
	}

	public function log_wc_product_updated( $product_id ) {
		$this->log_event(
			'woocommerce_product_updated',
			'WooCommerce product updated',
			array(
				'object_type'  => 'product',
				'object_id'    => absint( $product_id ),
				'object_title' => get_the_title( $product_id ),
			)
		);
	}

}
