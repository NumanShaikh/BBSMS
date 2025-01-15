<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.1
 * @package    Bbsms
 * @subpackage Bbsms/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Bbsms
 * @subpackage Bbsms/admin
 * @author     Numan Hameed  <numan@humanitymedia.net>
 */

require_once( plugin_dir_path( __FILE__ ) . '/../twilio/src/Twilio/autoload.php' );

use Twilio\Rest\Client;

class Bbsms_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.1
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.1
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since    1.0.1
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register Cron Schedules
	 */

	public function add_bsms_cron_schedules( $schedules ) {
		$schedules['every_fifteen_minutes'] = array(
			'interval' => 60 * 15,
			'display'  => __( 'Every 15 Minutes', 'bbsms' )
		);

		return $schedules;
	}

	/**
	 * Check if cron is already scheduled if not then schedule it
	 */
	public function schedule_bsms_crons() {
		$this->bsms_check_number_delivery_fn();
		// Schedule an action if it's not already scheduled
		if ( ! wp_next_scheduled( 'bsms_check_number_delivery' ) ) {
			wp_schedule_event( time(), 'every_fifteen_minutes', 'bsms_check_number_delivery' );
		}
	}

	// Hook into that action that'll fire every fifteen minutes & Check Sms Delivery
	public function bsms_check_number_delivery_fn() {
		return;
		// Check 50 Number delivery in every 15 minutes
		global $wpdb;
		$thenot_table = $wpdb->prefix . 'bbsms';
		$thenotlist   = $wpdb->get_col( "SELECT `phone` FROM $thenot_table WHERE `updated_at` < DATE_SUB(CURDATE(), INTERVAL 1 DAY) LIMIT 50" );
		$api_details  = get_option( 'bbsms' );
		if ( ! empty( $thenotlist ) && ! empty( $api_details ) ) {
			foreach ( $thenotlist as $value ) {
				$TWILIO_SID   = $api_details['api_sid'];
				$TWILIO_TOKEN = $api_details['api_auth_token'];
				$twilio       = new Client( $TWILIO_SID, $TWILIO_TOKEN );
				try {
					$twilio->lookups->v1->phoneNumbers( $value )->fetch();
					$data  = [ 'updated_at' => date( 'Y-m-d' ), 'status' => 'active' ];
					$where = [ 'phone' => $value ];
					$wpdb->update( $wpdb->prefix . 'bbsms', $data, $where );
				} catch ( Exception $e ) {
					$data  = [ 'updated_at' => date( 'Y-m-d' ) ];
					$where = [ 'phone' => $value ];
					$wpdb->update( $wpdb->prefix . 'bbsms', $data, $where );

					$message = $e->getMessage();
					$number  = trim( substr( $message, strpos( $message, '+' ) - 1 ) );
					$number  = substr( $number, 0, 12 );

					Bbsms_Public::undeliverable_phone( $number );
					self::DisplayError( $message );
				}
			}
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.1
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Bbsms_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bbsms_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		if ( isset( $_GET['page'] ) && $_GET['page'] == 'bbsms-sms' ) {
			wp_enqueue_style( $this->plugin_name . '-select2-css', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), $this->version, 'all' );
		}
		wp_enqueue_style( $this->plugin_name . '-admin-css', plugin_dir_url( __FILE__ ) . 'css/bbsms-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.1
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Bbsms_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bbsms_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'bbsms-sms' ) {
			wp_enqueue_media();
			wp_enqueue_script( $this->plugin_name . '-select2-js', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), $this->version, true );
		}
		wp_enqueue_script( $this->plugin_name . '-admin-js', plugin_dir_url( __FILE__ ) . 'js/bbsms-admin.js', array( 'jquery' ), $this->version, false );

	}

	public function add_bbsms_admin_setting() {

		/*
		 * Add a settings page for this plugin to the Settings menu.
		 *
		 * Administration Menus: http://codex.wordpress.org/Administration_Menus
		 *
		 */
		add_menu_page( 'BB SMS PAGE', 'BBSMS', 'manage_options', $this->plugin_name, array(
				$this,
				'display_bbsms_settings_page'
			)
		);
	}

	/**
	 * Render the settings page for this plugin.( The html file )
	 *
	 * @since    1.0.1
	 */

	public function display_bbsms_settings_page() {
		include_once( 'partials/bbsms-admin-display.php' );
	}

	/**
	 * Registers and Defines the necessary fields we need.
	 *
	 */
	public function bbsms_admin_settings_save() {

		register_setting( $this->plugin_name, $this->plugin_name, array( $this, 'plugin_options_validate' ) );

		add_settings_section( 'bbsms_main', 'Main Settings', array(
			$this,
			'bbsms_section_text'
		), 'bbsms-settings-page' );

		add_settings_field( 'api_sid', 'API SID', array(
			$this,
			'bbsms_setting_sid'
		), 'bbsms-settings-page', 'bbsms_main' );

		add_settings_field( 'api_auth_token', 'API AUTH TOKEN', array(
			$this,
			'bbsms_setting_token'
		), 'bbsms-settings-page', 'bbsms_main' );

		add_settings_field( 'messaging_service_id', 'MESSAGING SERVICE ID', array(
			$this,
			'bbsms_setting_messaging_id'
		), 'bbsms-settings-page', 'bbsms_main' );
		add_settings_field( 'notify_service_id', 'Notify SERVICE ID', array(
			$this,
			'bbsms_setting_notify_id'
		), 'bbsms-settings-page', 'bbsms_main' );
	}

	/**
	 * Displays the settings sub header
	 *
	 */
	public function bbsms_section_text() {
		echo '<h3>Edit api details</h3>';
	}

	/**
	 * Renders the sid input field
	 *
	 */
	public function bbsms_setting_sid() {

		$options = get_option( $this->plugin_name );
		echo "<input id='plugin_text_string' name='$this->plugin_name[api_sid]' size='40' type='text' value='{$options['api_sid']}' />";
	}

	/**
	 * Renders the auth_token input field
	 *
	 */
	public function bbsms_setting_token() {
		$options = get_option( $this->plugin_name );
		echo "<input id='plugin_text_string' name='$this->plugin_name[api_auth_token]' size='40' type='text' value='{$options['api_auth_token']}' />";
	}

	/**
	 * Renders the messaging_service_id input field
	 *
	 */
	public function bbsms_setting_messaging_id() {
		$options = get_option( $this->plugin_name );
		echo "<input id='plugin_text_string' name='$this->plugin_name[messaging_service_id]' size='40' type='text' value='{$options['messaging_service_id']}' />";
	}

	/**
	 * Renders the messaging_service_id input field
	 *
	 */
	public function bbsms_setting_notify_id() {
		$options = get_option( $this->plugin_name );
		echo "<input id='plugin_text_string' name='$this->plugin_name[notify_service_id]' size='40' type='text' value='{$options['notify_service_id']}' />";
	}

	/**
	 * Sanitises all input fields.
	 *
	 */
	public function plugin_options_validate( $input ) {
		$newinput['api_sid']              = trim( $input['api_sid'] );
		$newinput['api_auth_token']       = trim( $input['api_auth_token'] );
		$newinput['messaging_service_id'] = trim( $input['messaging_service_id'] );
		$newinput['notify_service_id']    = trim( $input['notify_service_id'] );

		return $newinput;
	}

	/**
	 * Register the sms page for the admin area.
	 *
	 * @since    1.0.1
	 */
	public function register_bbsms_sms_page() {
		// Create our settings page as a submenu page.
		add_submenu_page(
			'bbsms',                                         // parent slug
			__( 'SEND SMS', $this->plugin_name . '-sms' ), // page title
			__( 'Send SMS', $this->plugin_name . '-sms' ),         // menu title
			'manage_options',                                 // capability
			$this->plugin_name . '-sms',                       // menu_slug
			array( $this, 'display_bbsms_sms_page' )       // callable function
		);
	}

	public function register_bbsms_message_logs() {
		// Create our settings page as a submenu page.
		add_submenu_page(
			'bbsms',                                         // parent slug
			__( 'MESSAGE LOGS', $this->plugin_name . '-logs' ), // page title
			__( 'Message Logs', $this->plugin_name . '-logs' ),         // menu title
			'manage_options',                                 // capability
			$this->plugin_name . '-logs',                       // menu_slug
			array( $this, 'display_bbsms_message_logs_page' )       // callable function
		);
	}

	/**
	 * Display the sms page - The page we are going to be sending message from.
	 *
	 * @since    1.0.1
	 */

	public function display_bbsms_sms_page() {
		include_once( 'partials/bbsms-admin-sms.php' );
	}

	public function display_bbsms_message_logs_page() {
		include_once( 'partials/bbsms-message-logs.php' );
	}

	public function message_logs() {
		$api_details = get_option( 'bbsms' ); #sendex is what we use to identify our option, it can be anything

		if ( is_array( $api_details ) and count( $api_details ) != 0 ) {
			$TWILIO_SID   = $api_details['api_sid'];
			$TWILIO_TOKEN = $api_details['api_auth_token'];
		}
		$client   = new Client( $TWILIO_SID, $TWILIO_TOKEN );
		$date     = date( 'Y-m-d', strtotime( '-1 day' ) );
		$messages = $client->messages->stream(
			array(
				'dateSentAfter' => $date,
			)
		);
		$string   = '<table><tr><th>Date</th><th>To</th><th>From</th><th>Message</th><th>Status</th></tr>';
		foreach ( $messages as $sms ) {
			if ( in_array( $sms->status, array( 'undelivered', 'failed' ) ) ) {
				Bbsms_Public::undeliverable_phone( $sms->to );
			} elseif ( $sms->status == 'received' && in_array( $sms->body, array(
					'Start',
					'START',
					'start',
					'STart',
					'STArt',
					'STARt',
					'sTART'
				) ) ) {
				Bbsms_Public::resubscribe_phone( $sms->from );
			} elseif ( $sms->status == 'received' && in_array( $sms->body, array(
					'Stop',
					'STOP',
					'stop',
					'STop',
					'STOp',
					'SToP',
					'stoP'
				) ) ) {
				Bbsms_Public::unsubscribe_phone( $sms->from );
			}
			//	elseif(in_array($sms->status,array('delivered','sent'))){$datesent = $sms->dateSent->format('Y-m-d'); Bbsms_Public::deliverable_to_phone($sms->to,$datesent); }
			$string .= '<tr><td>' . $sms->dateSent->format( 'Y-m-d H:i:s' ) . '</td><td>' . $sms->to . '</td><td>' . $sms->from . '</td><td>' . $sms->body . '</td><td>' . $sms->status . '</td></tr>';
		}
		$string .= '</table>';
		echo $string;

	}

	public function send_message() {
		if ( ! isset( $_POST['send_sms_message'] ) ) {
			return;
		}

//		echo "<pre>" . print_r( $thelist, 1 ) . "</pre>";
//		var_dump($_POST['attachment_image_url'] );
//		die;

		$api_details = get_option( 'bbsms' );
		$bulkarray   = array( 'Yes', 'Yes_Option_Two' );
		if ( is_array( $api_details ) and count( $api_details ) != 0 ) {
			$TWILIO_SID        = $api_details['api_sid'];
			$TWILIO_TOKEN      = $api_details['api_auth_token'];
			$TWILIO_MESSAGE_ID = $api_details['messaging_service_id'];
			$notify_sid        = $api_details['notify_service_id'];
		}
		$sender_id = ( isset( $_POST['sender'] ) ) ? $_POST['sender'] : $TWILIO_MESSAGE_ID;
		$message   = ( isset( $_POST['message'] ) ) ? $_POST['message'] : '';
		if ( isset( $_POST['bulksend'] ) && $_POST['bulksend'] == 'No' ) {
			$to = ( isset( $_POST['numbers'] ) ) ? array( $_POST['numbers'] ) : '';
		}
		if ( isset( $_POST['bulksend'] ) && $_POST['bulksend'] == 'Yes' ) {
			$sender_id  = $TWILIO_MESSAGE_ID;
			$orderrange = $_POST['orders'];
			global $wpdb;
			$table_name = $wpdb->prefix . 'bbsms';
			$values     = $wpdb->get_results( "SELECT `phone` FROM $table_name WHERE `status` LIKE 'active' ORDER BY `lastsent`,`id` ASC LIMIT $orderrange" );
			$to         = array();
			foreach ( $values as $value ) {
				$to[] = $value->phone;
			}
		}
		if ( isset( $_POST['bulksend'] ) && $_POST['bulksend'] == 'Yes_Option_Two' ) {
			$sender_id = $TWILIO_MESSAGE_ID;
			global $wpdb;
			$news_table_name = $wpdb->prefix . 'newsletter';
			$thelist         = [];
			$date            = "";
			if ( isset( $_POST['date'] ) && ! empty( $_POST['date'] ) ) {
				$date = strtotime( $_POST['date'] );
				$date = "AND last_activity >= $date";
			}
			foreach ( $_POST['bulksendlist'] as $list ) {
				$thelist[] = $wpdb->get_col( "SELECT `profile_1` FROM $news_table_name WHERE `profile_1` NOT LIKE '' AND `status` LIKE 'C' AND $list = 1 $date" );
			}

			$partial = array();
			foreach ( $thelist as $list ) {
				foreach ( $list as $item ) {
					$phone = preg_replace( '/\D+/', '', $item );
					if ( $phone[0] == '1' ) {
						$phone = substr( $phone, 1 );
					}
					if ( strlen( $phone ) == 10 ) {
						$partial[] = '+1' . $phone;
					}
				}
			}


			$thenot_table = $wpdb->prefix . 'bbsms';
			$thenotlist   = $wpdb->get_col( "SELECT `phone` FROM $thenot_table WHERE `status` NOT LIKE 'active' OR `lastsent` > NOW() - INTERVAL 1 WEEK OR `lastorder` > NOW() - INTERVAL 1 WEEK" );
			$to           = array_diff( $partial, $thenotlist );
			$orderrange   = count( $to );
		}
		try {
			$client     = new Client( $TWILIO_SID, $TWILIO_TOKEN );
			$n          = 1;
			$send_array = array(
				'from' => $sender_id,
				'body' => $message
			);
			if ( isset( $_POST['attachment_image_url'] ) ) {
				//$send_array['mediaUrl'] = [ $_POST['attachment_image_url'] ];
				$send_array['sms'] = [ "media_urls" => [ $_POST['attachment_image_url'] ] ];
			}
			$datesent    = date( "Y-m-d" );
			$subscribers = [];
			foreach ( $to as $t ) {
				$subscribers[] = json_encode( [ 'binding_type' => "sms", 'address' => $t ] );
				$n ++;
			}

			$send_array['toBinding'] = $subscribers;

//			echo "<pre>" . print_r( $send_array, 1 ) . "</pre>";
//			die;
			// Create a notification
			$notification = $client
				->notify->services( $notify_sid )
				->notifications->create( $send_array );

			self::DisplaySuccess();

		} catch ( Exception $e ) {
			$message = $e->getMessage();
			$number  = trim( substr( $message, strpos( $message, '+' ) - 1 ) );
			$number  = substr( $number, 0, 12 );
			Bbsms_Public::undeliverable_phone( $number );
			$message .= ' ' . $n . ' total messages sent';
			self::DisplayError( $message );
		}
		if ( isset( $_POST['bulksend'] ) && in_array( $_POST['bulksend'], $bulkarray ) ) {
			FrmEntry::create( array(
				'form_id'   => 32,
				'item_key'  => 'entry',
				'item_meta' => array(
					587 => $_POST['message'], //phonenumber from
					588 => $orderrange,
					590 => $n, //Message
				),
			) );
		}
	}

	/**
	 * Designs for displaying Notices
	 *
	 * @since    1.0.1
	 * @access   private
	 * @var $message - String - The message we are displaying
	 * @var $status - Boolean - its either true or false
	 */
	public static function admin_notice( $message, $status = true ) {
		$class   = ( $status ) ? 'notice notice-success' : 'notice notice-error';
		$message = __( $message, 'sample-text-domain' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Displays Error Notices
	 *
	 * @since    1.0.1
	 * @access   private
	 */
	public static function DisplayError( $message = "Aww!, there was an error." ) {
		add_action( 'admin_notices', function () use ( $message ) {
			self::admin_notice( $message, false );
		} );
	}

	/**
	 * Displays Success Notices
	 *
	 * @since    1.0.1
	 * @access   private
	 */
	public static function DisplaySuccess( $message = "Successful!" ) {
		add_action( 'admin_notices', function () use ( $message ) {
			self::admin_notice( $message, true );
		} );
	}

	public static function RemoveList( $message = "Some stuff" ) {
		add_action( 'admin_notices', function () use ( $message ) {
			self::admin_notice( $message, true );
		} );
	}
}
