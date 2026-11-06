<?php
/**
 * Discussion groups, membership, and private mail.
 *
 * @package Wordpress_Groups_Plugin
 */

defined( 'ABSPATH' ) || exit;

final class WPG_Plugin {
	const VERSION = '1.0.0';

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'init' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_wpg_action', array( __CLASS__, 'handle_action' ) );
		add_action( 'delete_user', array( __CLASS__, 'delete_user' ) );
		add_action( 'wpmu_delete_user', array( __CLASS__, 'delete_user' ) );
	}

	public static function init() {
		if ( get_option( 'wpg_version' ) !== self::VERSION ) {
			self::install();
		}
		load_plugin_textdomain( 'wordpress-groups-plugin', false, 'wordpress-groups-plugin/languages' );
		add_shortcode( 'wordpress_groups', array( __CLASS__, 'groups_shortcode' ) );
		add_shortcode( 'wordpress_groups_messages', array( __CLASS__, 'messages_shortcode' ) );
	}

	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'wpg_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$schemas = array(
			'groups'   => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				owner_id bigint(20) unsigned NOT NULL,
				name varchar(160) NOT NULL,
				description text NOT NULL,
				privacy varchar(10) NOT NULL DEFAULT 'public',
				membership varchar(10) NOT NULL DEFAULT 'open',
				posting varchar(10) NOT NULL DEFAULT 'members',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY owner_id (owner_id)",
			'members'  => "group_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				role varchar(10) NOT NULL DEFAULT 'member',
				status varchar(10) NOT NULL DEFAULT 'pending',
				PRIMARY KEY  (group_id,user_id),
				KEY user_id (user_id)",
			'topics'   => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				group_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				title varchar(200) NOT NULL,
				body text NOT NULL,
				locked tinyint(1) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY group_id (group_id)",
			'replies'  => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				topic_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				body text NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY topic_id (topic_id)",
			'messages' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				sender_id bigint(20) unsigned NOT NULL,
				recipient_id bigint(20) unsigned NOT NULL,
				body text NOT NULL,
				is_read tinyint(1) NOT NULL DEFAULT 0,
				sender_deleted tinyint(1) NOT NULL DEFAULT 0,
				recipient_deleted tinyint(1) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY sender_id (sender_id),
				KEY recipient_id (recipient_id)",
		);
		foreach ( $schemas as $name => $columns ) {
			dbDelta( 'CREATE TABLE ' . self::table( $name ) . " ($columns) $charset;" );
		}
		add_option( 'wpg_options', self::defaults() );
		update_option( 'wpg_version', self::VERSION );
	}

	private static function defaults() {
		return array(
			'creator_roles'     => array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),
			'messages_enabled'  => 0,
			'default_privacy'   => 'public',
			'default_join'      => 'open',
		);
	}

	public static function options() {
		return wp_parse_args( get_option( 'wpg_options', array() ), self::defaults() );
	}

	public static function admin_menu() {
		add_options_page( __( 'WordPress Groups Plugin', 'wordpress-groups-plugin' ), __( 'Tartışma grupları', 'wordpress-groups-plugin' ), 'manage_options', 'wordpress-groups-plugin', array( __CLASS__, 'settings_page' ) );
	}

	public static function register_settings() {
		register_setting( 'wpg_settings', 'wpg_options', array( 'sanitize_callback' => array( __CLASS__, 'sanitize_options' ), 'type' => 'array' ) );
	}

	public static function sanitize_options( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'creator_roles'    => array_values( array_intersect( array_keys( wp_roles()->roles ), isset( $input['creator_roles'] ) && is_array( $input['creator_roles'] ) ? $input['creator_roles'] : array() ) ),
			'messages_enabled' => empty( $input['messages_enabled'] ) ? 0 : 1,
			'default_privacy'  => self::choice( $input['default_privacy'] ?? '', array( 'public', 'private', 'hidden' ), 'public' ),
			'default_join'     => self::choice( $input['default_join'] ?? '', array( 'open', 'request', 'invite' ), 'open' ),
		);
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$options = self::options();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WordPress Groups Plugin ayarları', 'wordpress-groups-plugin' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wpg_settings' ); ?>
				<fieldset>
					<legend><h2><?php esc_html_e( 'Kimler grup oluşturabilir?', 'wordpress-groups-plugin' ); ?></h2></legend>
					<p><?php esc_html_e( 'Site yöneticileri her zaman grup oluşturabilir ve grupları yönetebilir.', 'wordpress-groups-plugin' ); ?></p>
					<?php foreach ( wp_roles()->roles as $role => $details ) : ?>
						<p><label><input type="checkbox" name="wpg_options[creator_roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $options['creator_roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $details['name'] ) ); ?></label></p>
					<?php endforeach; ?>
				</fieldset>
				<p><label><input type="checkbox" name="wpg_options[messages_enabled]" value="1" <?php checked( $options['messages_enabled'] ); ?>> <?php esc_html_e( 'Üyeler arasında özel mesajlaşmayı etkinleştir', 'wordpress-groups-plugin' ); ?></label></p>
				<p><?php esc_html_e( 'Kapatıldığında mesaj gönderme ve posta kutularına erişim durur; mevcut mesajlar korunur.', 'wordpress-groups-plugin' ); ?></p>
				<?php
				self::select( 'wpg_options[default_privacy]', __( 'Yeni grupların varsayılan görünürlüğü', 'wordpress-groups-plugin' ), self::privacy_labels(), $options['default_privacy'] );
				self::select( 'wpg_options[default_join]', __( 'Yeni grupların varsayılan üyelik yöntemi', 'wordpress-groups-plugin' ), self::join_labels(), $options['default_join'] );
				submit_button();
				?>
			</form>
			<h2><?php esc_html_e( 'Sayfalara ekleme', 'wordpress-groups-plugin' ); ?></h2>
			<p><code>[wordpress_groups]</code> — <?php esc_html_e( 'Grup dizini, grup oluşturma ve tartışmalar.', 'wordpress-groups-plugin' ); ?></p>
			<p><code>[wordpress_groups_messages]</code> — <?php esc_html_e( 'Özel mesajlar ve kişisel mesaj alma tercihi.', 'wordpress-groups-plugin' ); ?></p>
			<p><?php esc_html_e( 'Bu kısa kodları içeren sayfaları tam sayfa önbelleğinden hariç tutun. Özel mesajlar uçtan uca şifrelenmez; veritabanına erişimi olan yöneticiler bunlara erişebilir.', 'wordpress-groups-plugin' ); ?></p>
		</div>
		<?php
	}

	private static function choice( $value, $allowed, $fallback ) {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	private static function privacy_labels() {
		return array(
			'public'  => __( 'Herkese açık: herkes tartışmaları okuyabilir', 'wordpress-groups-plugin' ),
			'private' => __( 'Özel: dizinde görünür, tartışmaları yalnızca üyeler okur', 'wordpress-groups-plugin' ),
			'hidden'  => __( 'Gizli: yalnızca üyeler ve davetliler görebilir', 'wordpress-groups-plugin' ),
		);
	}

	private static function join_labels() {
		return array(
			'open'    => __( 'Açık üyelik', 'wordpress-groups-plugin' ),
			'request' => __( 'Yönetici onayıyla üyelik', 'wordpress-groups-plugin' ),
			'invite'  => __( 'Yalnızca davetle üyelik', 'wordpress-groups-plugin' ),
		);
	}

	public static function group( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'groups' ) . ' WHERE id = %d', $id ) );
	}

	public static function member( $group_id, $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'members' ) . ' WHERE group_id = %d AND user_id = %d', $group_id, $user_id ) );
	}

	public static function can_create() {
		return current_user_can( 'manage_options' ) || ( current_user_can( 'read' ) && (bool) array_intersect( wp_get_current_user()->roles, self::options()['creator_roles'] ) );
	}

	public static function can_manage( $group, $owner_only = false ) {
		if ( current_user_can( 'manage_options' ) || ( is_user_logged_in() && (int) $group->owner_id === get_current_user_id() ) ) {
			return true;
		}
		$member = self::member( $group->id, get_current_user_id() );
		return ! $owner_only && $member && 'active' === $member->status && 'moderator' === $member->role;
	}

	public static function can_view( $group, $content = false ) {
		if ( self::can_manage( $group ) ) {
			return true;
		}
		$member = self::member( $group->id, get_current_user_id() );
		if ( $member && 'banned' === $member->status ) {
			return false;
		}
		if ( 'public' === $group->privacy || ( ! $content && 'private' === $group->privacy ) ) {
			return true;
		}
		return $member && ( 'active' === $member->status || ( ! $content && 'invited' === $member->status ) );
	}

	private static function can_post( $group, $new_topic = false ) {
		if ( self::can_manage( $group ) ) {
			return true;
		}
		$member = self::member( $group->id, get_current_user_id() );
		return current_user_can( 'read' ) && $member && 'active' === $member->status && ( ! $new_topic || 'members' === $group->posting );
	}

	private static function post( $key ) {
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? trim( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	private static function query_id( $key ) {
		return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? absint( $_GET[ $key ] ) : 0;
	}

	private static function fail() {
		wp_die( esc_html__( 'İşlem tamamlanamadı. Yetkinizi, alanları ve grubun güncel durumunu kontrol edin.', 'wordpress-groups-plugin' ), '', array( 'response' => 403 ) );
	}

	private static function require_permission( $allowed ) {
		if ( ! $allowed ) {
			self::fail();
		}
	}

	private static function write( $result ) {
		if ( false === $result ) {
			wp_die( esc_html__( 'Veriler kaydedilemedi. Lütfen yeniden deneyin.', 'wordpress-groups-plugin' ), '', array( 'response' => 500 ) );
		}
	}

	public static function handle_action() {
		global $wpdb;
		self::require_permission( is_user_logged_in() && current_user_can( 'read' ) );
		check_admin_referer( 'wpg_action', 'wpg_nonce' );
		$action   = self::post( 'operation' );
		$user_id  = get_current_user_id();
		$group_id = absint( self::post( 'group_id' ) );
		$group    = $group_id ? self::group( $group_id ) : null;
		$redirect = wp_validate_redirect( self::post( 'return_url' ), home_url( '/' ) );
		$redirect = remove_query_arg( array( 'wpg_notice', 'wpg_p' ), $redirect );

		if ( 'create_group' === $action || 'save_group' === $action ) {
			self::require_permission( 'create_group' === $action ? self::can_create() : $group && self::can_manage( $group, true ) );
			$name = sanitize_text_field( self::post( 'name' ) );
			$description = sanitize_textarea_field( self::post( 'description' ) );
			self::require_permission( '' !== $name && mb_strlen( $name ) <= 160 && mb_strlen( $description ) <= 10000 );
			$data = array(
				'name'        => $name,
				'description' => $description,
				'privacy'     => self::choice( self::post( 'privacy' ), array( 'public', 'private', 'hidden' ), 'private' ),
				'membership'  => self::choice( self::post( 'membership' ), array( 'open', 'request', 'invite' ), 'request' ),
				'posting'     => self::choice( self::post( 'posting' ), array( 'members', 'moderators' ), 'members' ),
			);
			// A hidden group must not be joinable by guessing its ID.
			if ( 'hidden' === $data['privacy'] ) {
				$data['membership'] = 'invite';
			}
			if ( 'create_group' === $action ) {
				$data['owner_id']   = $user_id;
				$data['created_at'] = current_time( 'mysql', true );
				self::write( $wpdb->insert( self::table( 'groups' ), $data ) );
				$group_id = (int) $wpdb->insert_id;
				$result = $wpdb->insert( self::table( 'members' ), array( 'group_id' => $group_id, 'user_id' => $user_id, 'role' => 'owner', 'status' => 'active' ) );
				if ( false === $result ) {
					$wpdb->delete( self::table( 'groups' ), array( 'id' => $group_id ) );
					self::write( $result );
				}
				$redirect = add_query_arg( 'wpg_group', $group_id, $redirect );
			} else {
				self::write( $wpdb->update( self::table( 'groups' ), $data, array( 'id' => $group_id ) ) );
			}
		} elseif ( in_array( $action, array( 'join', 'leave', 'invite', 'member', 'delete_group' ), true ) ) {
			self::require_permission( $group && self::can_view( $group ) );
			$member = self::member( $group_id, $user_id );
			if ( 'join' === $action ) {
				self::require_permission( ! $member || in_array( $member->status, array( 'pending', 'invited' ), true ) );
				self::require_permission( ( $member && 'invited' === $member->status ) || ( 'invite' !== $group->membership && 'hidden' !== $group->privacy ) );
				$status = ( $member && 'invited' === $member->status ) || 'open' === $group->membership ? 'active' : 'pending';
				self::write( $wpdb->replace( self::table( 'members' ), array( 'group_id' => $group_id, 'user_id' => $user_id, 'role' => 'member', 'status' => $status ) ) );
			} elseif ( 'leave' === $action ) {
				self::require_permission( $member && 'banned' !== $member->status && $user_id !== (int) $group->owner_id );
				self::write( $wpdb->delete( self::table( 'members' ), array( 'group_id' => $group_id, 'user_id' => $user_id ) ) );
				$redirect = remove_query_arg( array( 'wpg_group', 'wpg_topic' ), $redirect );
			} elseif ( 'delete_group' === $action ) {
				self::require_permission( self::can_manage( $group, true ) && '1' === self::post( 'confirm_delete' ) );
				self::delete_group( $group_id );
				$redirect = remove_query_arg( array( 'wpg_group', 'wpg_topic' ), $redirect );
			} else {
				self::require_permission( self::can_manage( $group ) );
				if ( 'invite' === $action ) {
					$target = get_user_by( 'login', self::post( 'username' ) );
					self::require_permission( $target && user_can( $target, 'read' ) && ! self::member( $group_id, $target->ID ) );
					self::write( $wpdb->insert( self::table( 'members' ), array( 'group_id' => $group_id, 'user_id' => $target->ID, 'role' => 'member', 'status' => 'invited' ) ) );
				} else {
					$target_id = absint( self::post( 'user_id' ) );
					$target    = self::member( $group_id, $target_id );
					$change    = self::post( 'change' );
					self::require_permission( $target && $target_id !== (int) $group->owner_id && $target_id !== $user_id );
					self::require_permission( 'moderator' !== $target->role || self::can_manage( $group, true ) );
					if ( in_array( $change, array( 'promote', 'demote' ), true ) ) {
						self::require_permission( self::can_manage( $group, true ) && 'active' === $target->status );
						self::write( $wpdb->update( self::table( 'members' ), array( 'role' => 'promote' === $change ? 'moderator' : 'member' ), array( 'group_id' => $group_id, 'user_id' => $target_id ) ) );
					} elseif ( 'approve' === $change ) {
						self::require_permission( 'pending' === $target->status );
						self::write( $wpdb->update( self::table( 'members' ), array( 'status' => 'active' ), array( 'group_id' => $group_id, 'user_id' => $target_id ) ) );
					} elseif ( 'ban' === $change ) {
						self::write( $wpdb->update( self::table( 'members' ), array( 'status' => 'banned', 'role' => 'member' ), array( 'group_id' => $group_id, 'user_id' => $target_id ) ) );
					} elseif ( 'remove' === $change ) {
						self::write( $wpdb->delete( self::table( 'members' ), array( 'group_id' => $group_id, 'user_id' => $target_id ) ) );
					} else {
						self::fail();
					}
				}
			}
		} elseif ( in_array( $action, array( 'topic', 'reply', 'lock', 'delete_topic', 'delete_reply' ), true ) ) {
			self::require_permission( $group && self::can_view( $group, true ) );
			$topic_id = absint( self::post( 'topic_id' ) );
			$topic = $topic_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'topics' ) . ' WHERE id = %d AND group_id = %d', $topic_id, $group_id ) ) : null;
			if ( 'topic' === $action || 'reply' === $action ) {
				self::require_permission( self::can_post( $group, 'topic' === $action ) );
				$body = sanitize_textarea_field( self::post( 'body' ) );
				self::require_permission( '' !== $body && mb_strlen( $body ) <= 10000 );
				$data = array( 'user_id' => $user_id, 'body' => $body, 'created_at' => current_time( 'mysql', true ) );
				if ( 'topic' === $action ) {
					$title = sanitize_text_field( self::post( 'title' ) );
					self::require_permission( '' !== $title && mb_strlen( $title ) <= 200 );
					$data['group_id'] = $group_id;
					$data['title']    = $title;
					self::write( $wpdb->insert( self::table( 'topics' ), $data ) );
					$redirect = add_query_arg( 'wpg_topic', $wpdb->insert_id, $redirect );
				} else {
					self::require_permission( $topic && ! $topic->locked );
					$data['topic_id'] = $topic_id;
					self::write( $wpdb->insert( self::table( 'replies' ), $data ) );
				}
			} else {
				self::require_permission( $topic && self::can_manage( $group ) );
				if ( 'lock' === $action ) {
					self::write( $wpdb->update( self::table( 'topics' ), array( 'locked' => $topic->locked ? 0 : 1 ), array( 'id' => $topic_id ) ) );
				} elseif ( 'delete_topic' === $action ) {
					self::write( $wpdb->delete( self::table( 'replies' ), array( 'topic_id' => $topic_id ) ) );
					self::write( $wpdb->delete( self::table( 'topics' ), array( 'id' => $topic_id ) ) );
					$redirect = remove_query_arg( 'wpg_topic', $redirect );
				} else {
					self::write( $wpdb->delete( self::table( 'replies' ), array( 'id' => absint( self::post( 'reply_id' ) ), 'topic_id' => $topic_id ) ) );
				}
			}
		} elseif ( in_array( $action, array( 'send_message', 'delete_message', 'message_preferences' ), true ) ) {
			self::require_permission( ! empty( self::options()['messages_enabled'] ) );
			if ( 'message_preferences' === $action ) {
				update_user_meta( $user_id, 'wpg_disable_messages', '1' === self::post( 'disable_messages' ) ? 1 : 0 );
			} elseif ( 'send_message' === $action ) {
				$recipient = get_user_by( 'login', self::post( 'recipient' ) );
				$body = sanitize_textarea_field( self::post( 'body' ) );
				self::require_permission( $recipient && $recipient->ID !== $user_id && user_can( $recipient, 'read' ) && ! get_user_meta( $recipient->ID, 'wpg_disable_messages', true ) );
				self::require_permission( '' !== $body && mb_strlen( $body ) <= 10000 );
				$recent = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'messages' ) . ' WHERE sender_id = %d AND created_at > %s', $user_id, gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ) ) );
				self::require_permission( (int) $recent < 5 );
				self::write( $wpdb->insert( self::table( 'messages' ), array( 'sender_id' => $user_id, 'recipient_id' => $recipient->ID, 'body' => $body, 'created_at' => current_time( 'mysql', true ) ) ) );
			} else {
				$message_id = absint( self::post( 'message_id' ) );
				$message = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'messages' ) . ' WHERE id = %d AND (sender_id = %d OR recipient_id = %d)', $message_id, $user_id, $user_id ) );
				self::require_permission( $message );
				$column = (int) $message->sender_id === $user_id ? 'sender_deleted' : 'recipient_deleted';
				self::write( $wpdb->update( self::table( 'messages' ), array( $column => 1 ), array( 'id' => $message_id ) ) );
				self::write( $wpdb->query( 'DELETE FROM ' . self::table( 'messages' ) . ' WHERE sender_deleted = 1 AND recipient_deleted = 1' ) );
			}
		} else {
			self::fail();
		}
		wp_safe_redirect( add_query_arg( 'wpg_notice', 'saved', $redirect ) );
		exit;
	}

	private static function delete_group( $group_id ) {
		global $wpdb;
		self::write( $wpdb->query( $wpdb->prepare( 'DELETE r FROM ' . self::table( 'replies' ) . ' r INNER JOIN ' . self::table( 'topics' ) . ' t ON r.topic_id = t.id WHERE t.group_id = %d', $group_id ) ) );
		self::write( $wpdb->delete( self::table( 'topics' ), array( 'group_id' => $group_id ) ) );
		self::write( $wpdb->delete( self::table( 'members' ), array( 'group_id' => $group_id ) ) );
		self::write( $wpdb->delete( self::table( 'groups' ), array( 'id' => $group_id ) ) );
	}

	public static function delete_user( $user_id ) {
		global $wpdb;
		$groups = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'groups' ) . ' WHERE owner_id = %d', $user_id ) );
		foreach ( $groups as $group_id ) {
			self::delete_group( $group_id );
		}
		$wpdb->delete( self::table( 'members' ), array( 'user_id' => $user_id ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table( 'messages' ) . ' WHERE sender_id = %d OR recipient_id = %d', $user_id, $user_id ) );
		// Keep discussions readable without retaining a deleted account's identifier.
		$wpdb->update( self::table( 'topics' ), array( 'user_id' => 0 ), array( 'user_id' => $user_id ) );
		$wpdb->update( self::table( 'replies' ), array( 'user_id' => 0 ), array( 'user_id' => $user_id ) );
	}

	private static function no_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	private static function url( $args = array() ) {
		return add_query_arg( $args, get_permalink() );
	}

	private static function form_start( $operation, $fields = array() ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="wpg_nonce" value="' . esc_attr( wp_create_nonce( 'wpg_action' ) ) . '">';
		$fields = array_merge( array( 'action' => 'wpg_action', 'operation' => $operation, 'return_url' => self::url( array( 'wpg_group' => self::query_id( 'wpg_group' ), 'wpg_topic' => self::query_id( 'wpg_topic' ) ) ) ), $fields );
		foreach ( $fields as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
	}

	private static function button( $label ) {
		echo '<p><button type="submit">' . esc_html( $label ) . '</button></p></form>';
	}

	private static function input( $name, $label, $value = '', $textarea = false, $max = 200 ) {
		$id = wp_unique_id( 'wpg-' );
		echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><br>';
		if ( $textarea ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="6" cols="40" maxlength="' . esc_attr( $max ) . '" required>' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" type="text" maxlength="' . esc_attr( $max ) . '" required>';
		}
		echo '</p>';
	}

	private static function select( $name, $label, $choices, $value ) {
		$id = wp_unique_id( 'wpg-' );
		echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><br><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $choices as $key => $text ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></p>';
	}

	private static function notice() {
		if ( isset( $_GET['wpg_notice'] ) && 'saved' === $_GET['wpg_notice'] ) {
			echo '<p role="status">' . esc_html__( 'İşlem kaydedildi.', 'wordpress-groups-plugin' ) . '</p>';
		}
	}

	private static function login_notice() {
		echo '<p><a href="' . esc_url( wp_login_url( self::url() ) ) . '">' . esc_html__( 'Devam etmek için giriş yapın.', 'wordpress-groups-plugin' ) . '</a></p>';
	}

	private static function author( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Silinmiş kullanıcı', 'wordpress-groups-plugin' );
	}

	private static function body( $text ) {
		echo wpautop( esc_html( $text ) );
	}

	private static function date( $date ) {
		echo '<time datetime="' . esc_attr( str_replace( ' ', 'T', $date ) . 'Z' ) . '">' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date . ' UTC' ) ) ) . '</time>';
	}

	private static function pagination( $count, $args = array() ) {
		$page = max( 1, self::query_id( 'wpg_p' ) );
		echo '<nav aria-label="' . esc_attr__( 'Sayfalama', 'wordpress-groups-plugin' ) . '">';
		if ( $page > 1 ) {
			echo '<a href="' . esc_url( self::url( array_merge( $args, array( 'wpg_p' => $page - 1 ) ) ) ) . '">' . esc_html__( 'Önceki sayfa', 'wordpress-groups-plugin' ) . '</a> ';
		}
		if ( $count > 20 ) {
			echo '<a href="' . esc_url( self::url( array_merge( $args, array( 'wpg_p' => $page + 1 ) ) ) ) . '">' . esc_html__( 'Sonraki sayfa', 'wordpress-groups-plugin' ) . '</a>';
		}
		echo '</nav>';
	}

	private static function offset() {
		return ( min( 100000, max( 1, self::query_id( 'wpg_p' ) ) ) - 1 ) * 20;
	}

	public static function groups_shortcode() {
		global $wpdb;
		self::no_cache();
		ob_start();
		echo '<section aria-label="' . esc_attr__( 'Tartışma grupları', 'wordpress-groups-plugin' ) . '">';
		self::notice();
		$group_id = self::query_id( 'wpg_group' );
		if ( $group_id ) {
			$group = self::group( $group_id );
			echo '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Tüm gruplar', 'wordpress-groups-plugin' ) . '</a></p>';
			if ( $group && self::can_view( $group ) ) {
				self::render_group( $group );
			} else {
				echo '<p>' . esc_html__( 'Grup bulunamadı veya bu grubu görme izniniz yok.', 'wordpress-groups-plugin' ) . '</p>';
			}
		} else {
			echo '<h2>' . esc_html__( 'Tartışma grupları', 'wordpress-groups-plugin' ) . '</h2>';
			$groups_table  = self::table( 'groups' );
			$members_table = self::table( 'members' );
			$visibility = current_user_can( 'manage_options' ) ? '1=1' : $wpdb->prepare(
				"(g.privacy <> 'hidden' OR EXISTS (SELECT 1 FROM $members_table m WHERE m.group_id = g.id AND m.user_id = %d AND m.status IN ('active','invited'))) AND NOT EXISTS (SELECT 1 FROM $members_table b WHERE b.group_id = g.id AND b.user_id = %d AND b.status = 'banned')",
				get_current_user_id(),
				get_current_user_id()
			);
			$groups = $wpdb->get_results( $wpdb->prepare( "SELECT g.* FROM $groups_table g WHERE $visibility ORDER BY g.id DESC LIMIT 21 OFFSET %d", self::offset() ) );
			if ( ! $groups ) {
				echo '<p>' . esc_html__( 'Görüntülenecek grup yok.', 'wordpress-groups-plugin' ) . '</p>';
			}
			foreach ( array_slice( $groups, 0, 20 ) as $group ) {
				echo '<article><h3><a href="' . esc_url( self::url( array( 'wpg_group' => $group->id ) ) ) . '">' . esc_html( $group->name ) . '</a></h3>';
				self::body( $group->description );
				echo '<p>' . esc_html( self::privacy_labels()[ $group->privacy ] ) . '</p></article>';
			}
			self::pagination( count( $groups ) );
			if ( self::can_create() ) {
				echo '<h2>' . esc_html__( 'Grup oluştur', 'wordpress-groups-plugin' ) . '</h2>';
				self::group_form();
			} elseif ( ! is_user_logged_in() ) {
				self::login_notice();
			}
		}
		echo '</section>';
		return ob_get_clean();
	}

	private static function group_form( $group = null ) {
		$options = self::options();
		self::form_start( $group ? 'save_group' : 'create_group', array( 'group_id' => $group ? $group->id : 0 ) );
		self::input( 'name', __( 'Grup adı (zorunlu)', 'wordpress-groups-plugin' ), $group ? $group->name : '', false, 160 );
		// Descriptions are optional, unlike discussion and message bodies.
		$id = wp_unique_id( 'wpg-description-' );
		echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html__( 'Açıklama', 'wordpress-groups-plugin' ) . '</label><br><textarea id="' . esc_attr( $id ) . '" name="description" rows="4" cols="40" maxlength="10000">' . esc_textarea( $group ? $group->description : '' ) . '</textarea></p>';
		self::select( 'privacy', __( 'Grubu kimler görebilir?', 'wordpress-groups-plugin' ), self::privacy_labels(), $group ? $group->privacy : $options['default_privacy'] );
		self::select( 'membership', __( 'Üyelik yöntemi (gizli gruplar her zaman davet gerektirir)', 'wordpress-groups-plugin' ), self::join_labels(), $group ? $group->membership : $options['default_join'] );
		self::select( 'posting', __( 'Kimler tartışma başlatabilir?', 'wordpress-groups-plugin' ), array( 'members' => __( 'Tüm üyeler', 'wordpress-groups-plugin' ), 'moderators' => __( 'Grup sahibi ve moderatörler', 'wordpress-groups-plugin' ) ), $group ? $group->posting : 'members' );
		self::button( __( 'Grubu kaydet', 'wordpress-groups-plugin' ) );
	}

	private static function render_group( $group ) {
		global $wpdb;
		$member = self::member( $group->id, get_current_user_id() );
		echo '<h2>' . esc_html( $group->name ) . '</h2>';
		self::body( $group->description );
		echo '<p>' . esc_html( self::privacy_labels()[ $group->privacy ] ) . ' — ' . esc_html( self::join_labels()[ $group->membership ] ) . '</p>';
		echo '<p>' . esc_html__( 'Grup sahibi:', 'wordpress-groups-plugin' ) . ' ' . esc_html( self::author( $group->owner_id ) ) . '</p>';
		if ( ! is_user_logged_in() ) {
			self::login_notice();
		} elseif ( $member && 'pending' === $member->status ) {
			echo '<p role="status">' . esc_html__( 'Üyelik isteğiniz onay bekliyor.', 'wordpress-groups-plugin' ) . '</p>';
		} elseif ( ( $member && 'invited' === $member->status ) || ( ! $member && 'invite' !== $group->membership && 'hidden' !== $group->privacy ) ) {
			self::form_start( 'join', array( 'group_id' => $group->id ) );
			self::button( $member ? __( 'Daveti kabul et', 'wordpress-groups-plugin' ) : __( 'Katıl / üyelik isteği gönder', 'wordpress-groups-plugin' ) );
		}
		if ( $member && get_current_user_id() !== (int) $group->owner_id ) {
			self::form_start( 'leave', array( 'group_id' => $group->id ) );
			self::button( __( 'Gruptan ayrıl / isteği veya daveti reddet', 'wordpress-groups-plugin' ) );
		}
		if ( self::can_view( $group, true ) ) {
			$topic_id = self::query_id( 'wpg_topic' );
			if ( $topic_id ) {
				$topic = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'topics' ) . ' WHERE id = %d AND group_id = %d', $topic_id, $group->id ) );
				echo '<p><a href="' . esc_url( self::url( array( 'wpg_group' => $group->id ) ) ) . '">' . esc_html__( 'Grup tartışmalarına dön', 'wordpress-groups-plugin' ) . '</a></p>';
				if ( $topic ) {
					self::render_topic( $group, $topic );
				} else {
					echo '<p>' . esc_html__( 'Tartışma bulunamadı.', 'wordpress-groups-plugin' ) . '</p>';
				}
			} else {
				echo '<h3>' . esc_html__( 'Tartışmalar', 'wordpress-groups-plugin' ) . '</h3>';
				$topics = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'topics' ) . ' WHERE group_id = %d ORDER BY id DESC LIMIT 21 OFFSET %d', $group->id, self::offset() ) );
				if ( ! $topics ) {
					echo '<p>' . esc_html__( 'Henüz tartışma yok.', 'wordpress-groups-plugin' ) . '</p>';
				}
				echo '<ul>';
				foreach ( array_slice( $topics, 0, 20 ) as $topic ) {
					echo '<li><a href="' . esc_url( self::url( array( 'wpg_group' => $group->id, 'wpg_topic' => $topic->id ) ) ) . '">' . esc_html( $topic->title ) . '</a> — ' . esc_html( self::author( $topic->user_id ) ) . '</li>';
				}
				echo '</ul>';
				self::pagination( count( $topics ), array( 'wpg_group' => $group->id ) );
				if ( self::can_post( $group, true ) ) {
					echo '<h3>' . esc_html__( 'Tartışma başlat', 'wordpress-groups-plugin' ) . '</h3>';
					self::form_start( 'topic', array( 'group_id' => $group->id ) );
					self::input( 'title', __( 'Başlık (zorunlu)', 'wordpress-groups-plugin' ) );
					self::input( 'body', __( 'Tartışma metni (zorunlu)', 'wordpress-groups-plugin' ), '', true, 10000 );
					self::button( __( 'Tartışmayı yayımla', 'wordpress-groups-plugin' ) );
				}
			}
		} else {
			echo '<p>' . esc_html__( 'Tartışmaları okumak için grup üyesi olmalısınız.', 'wordpress-groups-plugin' ) . '</p>';
		}
		if ( self::can_manage( $group ) ) {
			self::render_management( $group );
		}
	}

	private static function render_topic( $group, $topic ) {
		global $wpdb;
		echo '<article><h3>' . esc_html( $topic->title ) . '</h3><p>' . esc_html( self::author( $topic->user_id ) ) . ' — ';
		self::date( $topic->created_at );
		echo '</p>';
		self::body( $topic->body );
		echo '</article>';
		if ( $topic->locked ) {
			echo '<p role="status">' . esc_html__( 'Bu tartışma yanıtlara kapalı.', 'wordpress-groups-plugin' ) . '</p>';
		}
		$replies = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'replies' ) . ' WHERE topic_id = %d ORDER BY id ASC LIMIT 21 OFFSET %d', $topic->id, self::offset() ) );
		echo '<h4>' . esc_html__( 'Yanıtlar', 'wordpress-groups-plugin' ) . '</h4>';
		foreach ( array_slice( $replies, 0, 20 ) as $reply ) {
			echo '<article><h5>' . esc_html( self::author( $reply->user_id ) ) . '</h5><p>';
			self::date( $reply->created_at );
			echo '</p>';
			self::body( $reply->body );
			if ( self::can_manage( $group ) ) {
				self::form_start( 'delete_reply', array( 'group_id' => $group->id, 'topic_id' => $topic->id, 'reply_id' => $reply->id ) );
				self::button( __( 'Bu yanıtı sil', 'wordpress-groups-plugin' ) );
			}
			echo '</article>';
		}
		self::pagination( count( $replies ), array( 'wpg_group' => $group->id, 'wpg_topic' => $topic->id ) );
		if ( ! $topic->locked && self::can_post( $group ) ) {
			self::form_start( 'reply', array( 'group_id' => $group->id, 'topic_id' => $topic->id ) );
			self::input( 'body', __( 'Yanıtınız (zorunlu)', 'wordpress-groups-plugin' ), '', true, 10000 );
			self::button( __( 'Yanıtı yayımla', 'wordpress-groups-plugin' ) );
		}
		if ( self::can_manage( $group ) ) {
			self::form_start( 'lock', array( 'group_id' => $group->id, 'topic_id' => $topic->id ) );
			self::button( $topic->locked ? __( 'Yanıtları aç', 'wordpress-groups-plugin' ) : __( 'Yanıtları kapat', 'wordpress-groups-plugin' ) );
			self::form_start( 'delete_topic', array( 'group_id' => $group->id, 'topic_id' => $topic->id ) );
			self::button( __( 'Tartışmayı ve yanıtlarını sil', 'wordpress-groups-plugin' ) );
		}
	}

	private static function render_management( $group ) {
		global $wpdb;
		echo '<details><summary>' . esc_html__( 'Grup yönetimi', 'wordpress-groups-plugin' ) . '</summary>';
		if ( self::can_manage( $group, true ) ) {
			echo '<h3>' . esc_html__( 'Grup ayarları', 'wordpress-groups-plugin' ) . '</h3>';
			self::group_form( $group );
		}
		echo '<h3>' . esc_html__( 'Üye davet et', 'wordpress-groups-plugin' ) . '</h3>';
		self::form_start( 'invite', array( 'group_id' => $group->id ) );
		self::input( 'username', __( 'Davet edilecek kullanıcının giriş adı (zorunlu)', 'wordpress-groups-plugin' ), '', false, 60 );
		self::button( __( 'Davet gönder', 'wordpress-groups-plugin' ) );
		echo '<h3>' . esc_html__( 'Üyeler ve üyelik istekleri', 'wordpress-groups-plugin' ) . '</h3>';
		$members = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'members' ) . ' WHERE group_id = %d ORDER BY user_id ASC LIMIT 21 OFFSET %d', $group->id, self::offset() ) );
		$statuses = array( 'active' => __( 'Üye', 'wordpress-groups-plugin' ), 'pending' => __( 'Onay bekliyor', 'wordpress-groups-plugin' ), 'invited' => __( 'Davet edildi', 'wordpress-groups-plugin' ), 'banned' => __( 'Engellendi', 'wordpress-groups-plugin' ) );
		$roles = array( 'owner' => __( 'Grup sahibi', 'wordpress-groups-plugin' ), 'moderator' => __( 'Moderatör', 'wordpress-groups-plugin' ), 'member' => __( 'Üye', 'wordpress-groups-plugin' ) );
		foreach ( array_slice( $members, 0, 20 ) as $member ) {
			echo '<p>' . esc_html( self::author( $member->user_id ) ) . ' — ' . esc_html( $statuses[ $member->status ] ) . ' / ' . esc_html( $roles[ $member->role ] ) . '</p>';
			if ( (int) $member->user_id === (int) $group->owner_id || (int) $member->user_id === get_current_user_id() || ( 'moderator' === $member->role && ! self::can_manage( $group, true ) ) ) {
				continue;
			}
			$changes = array( 'remove' => __( 'Çıkar / reddet / engeli kaldır', 'wordpress-groups-plugin' ) );
			if ( 'banned' !== $member->status ) {
				$changes['ban'] = __( 'Engelle (yeniden katılamaz)', 'wordpress-groups-plugin' );
			}
			if ( 'pending' === $member->status ) {
				$changes['approve'] = __( 'Üyeliği onayla', 'wordpress-groups-plugin' );
			}
			if ( 'active' === $member->status && self::can_manage( $group, true ) ) {
				$changes[ 'moderator' === $member->role ? 'demote' : 'promote' ] = 'moderator' === $member->role ? __( 'Üye yap', 'wordpress-groups-plugin' ) : __( 'Moderatör yap', 'wordpress-groups-plugin' );
			}
			self::form_start( 'member', array( 'group_id' => $group->id, 'user_id' => $member->user_id ) );
			self::select( 'change', sprintf( __( '%s için üyelik işlemi', 'wordpress-groups-plugin' ), self::author( $member->user_id ) ), $changes, 'remove' );
			self::button( __( 'Üyelik işlemini uygula', 'wordpress-groups-plugin' ) );
		}
		self::pagination( count( $members ), array( 'wpg_group' => $group->id ) );
		if ( self::can_manage( $group, true ) ) {
			echo '<h3>' . esc_html__( 'Grubu sil', 'wordpress-groups-plugin' ) . '</h3>';
			self::form_start( 'delete_group', array( 'group_id' => $group->id ) );
			echo '<p><label><input type="checkbox" name="confirm_delete" value="1" required> ' . esc_html__( 'Grubu, tüm tartışmaları ve üyelikleri kalıcı olarak silmek istiyorum.', 'wordpress-groups-plugin' ) . '</label></p>';
			self::button( __( 'Grubu kalıcı olarak sil', 'wordpress-groups-plugin' ) );
		}
		echo '</details>';
	}

	public static function messages_shortcode() {
		global $wpdb;
		self::no_cache();
		ob_start();
		echo '<section aria-label="' . esc_attr__( 'Özel mesajlar', 'wordpress-groups-plugin' ) . '"><h2>' . esc_html__( 'Özel mesajlar', 'wordpress-groups-plugin' ) . '</h2>';
		if ( empty( self::options()['messages_enabled'] ) ) {
			echo '<p>' . esc_html__( 'Özel mesajlaşma yönetici tarafından kapatılmıştır.', 'wordpress-groups-plugin' ) . '</p>';
		} elseif ( ! current_user_can( 'read' ) ) {
			self::login_notice();
		} else {
			self::notice();
			$user_id = get_current_user_id();
			echo '<h3>' . esc_html__( 'Mesaj alma tercihi', 'wordpress-groups-plugin' ) . '</h3>';
			self::form_start( 'message_preferences' );
			echo '<p><label><input type="checkbox" name="disable_messages" value="1" ' . checked( get_user_meta( $user_id, 'wpg_disable_messages', true ), 1, false ) . '> ' . esc_html__( 'Diğer üyelerden yeni özel mesaj almak istemiyorum', 'wordpress-groups-plugin' ) . '</label></p>';
			self::button( __( 'Tercihi kaydet', 'wordpress-groups-plugin' ) );
			echo '<h3>' . esc_html__( 'Mesaj gönder', 'wordpress-groups-plugin' ) . '</h3>';
			echo '<p>' . esc_html__( 'Mesajlar uçtan uca şifrelenmez. Dakikada en fazla beş mesaj gönderilebilir.', 'wordpress-groups-plugin' ) . '</p>';
			self::form_start( 'send_message' );
			self::input( 'recipient', __( 'Alıcının giriş adı (zorunlu)', 'wordpress-groups-plugin' ), '', false, 60 );
			self::input( 'body', __( 'Mesajınız (zorunlu)', 'wordpress-groups-plugin' ), '', true, 10000 );
			self::button( __( 'Özel mesaj gönder', 'wordpress-groups-plugin' ) );
			echo '<h3>' . esc_html__( 'Gelen ve gönderilen mesajlar', 'wordpress-groups-plugin' ) . '</h3>';
			$messages = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'messages' ) . ' WHERE (sender_id = %d AND sender_deleted = 0) OR (recipient_id = %d AND recipient_deleted = 0) ORDER BY id DESC LIMIT 21 OFFSET %d', $user_id, $user_id, self::offset() ) );
			if ( ! $messages ) {
				echo '<p>' . esc_html__( 'Henüz mesaj yok.', 'wordpress-groups-plugin' ) . '</p>';
			}
			foreach ( array_slice( $messages, 0, 20 ) as $message ) {
				$incoming = (int) $message->recipient_id === $user_id;
				$other_id = $incoming ? $message->sender_id : $message->recipient_id;
				$other    = get_userdata( $other_id );
				echo '<article><h4>' . esc_html( $incoming ? __( 'Gönderen:', 'wordpress-groups-plugin' ) : __( 'Alıcı:', 'wordpress-groups-plugin' ) ) . ' ' . esc_html( self::author( $other_id ) ) . '</h4><p>';
				if ( $other ) {
					echo esc_html( $other->user_login ) . ' — ';
				}
				self::date( $message->created_at );
				echo '</p>';
				if ( $incoming && ! $message->is_read ) {
					echo '<p>' . esc_html__( 'Yeni mesaj', 'wordpress-groups-plugin' ) . '</p>';
					$wpdb->update( self::table( 'messages' ), array( 'is_read' => 1 ), array( 'id' => $message->id, 'recipient_id' => $user_id ) );
				}
				self::body( $message->body );
				self::form_start( 'delete_message', array( 'message_id' => $message->id ) );
				self::button( __( 'Mesajı kendi posta kutumdan sil', 'wordpress-groups-plugin' ) );
				echo '</article>';
			}
			self::pagination( count( $messages ) );
		}
		echo '</section>';
		return ob_get_clean();
	}
}
