<?php
/**
 * Plugin Name: Send System Info
 * Description: Displays System Info for debugging.  This info can be emailed, or displayed via unique URL to Support personnel.
 * Version: 0.1
 * Author: johnregan3
 * Author URI: http://johnregan3.me
 * License: GPLv2+
 */

/**
 * Copyright (c) 2014 John Regan (http://johnregan3.com/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 *
 * Mad props to Matt Mullenweg for the original code
 * and Chris Olbekson (c3mdigital) for the encouragement to
 * puruse such a trivial pursuit.
 *
 * System Info textarea based on Easy Digital Downloads by Pippin Williamson.
 * http://easydigitaldownloads.com/
 */

include( 'includes/browser.php' );

class Send_System_Info_Plugin {

	//load actions, enqueue scripts
	static function setup() {
		add_action( 'register_activation_hook', array( __CLASS__, 'generate_url' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_submenu_page' ) );
		add_action( 'wp_ajax_regenerate_url', array( __CLASS__, 'generate_url' ) );
		add_action( 'template_redirect', array( __CLASS__, 'front_end_display' ) );
	}

	static function enqueue() {
		wp_register_script( 'system-info-script', plugins_url( 'send-system-info.js', __FILE__ ), array( 'jquery' ) );
		wp_localize_script( 'system-info-script', 'systemInfoAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
		wp_enqueue_script( 'system-info-script' );
	}

	//Create Tools Submenu Page
	static function register_submenu_page() {
		add_submenu_page( 'tools.php', __( 'System Info', 'system-info' ), __( 'System Info', 'system-info' ), 'manage_options', 'system-info', array( __CLASS__, 'render_page' ) );
	}

	//Render the page
	static function render_page() {
		$email_sent = self::send_email();
		if ( $email_sent && 'sent' == $email_sent ) : ?>
			<div id="message" class="updated"><p><?php _e( 'Email sent successfully.', 'sccss' ); ?></p></div>
		<?php elseif ( $email_sent && 'error' == $email_sent ) : ?>
			<div id="message" class="error"><p><?php _e( 'Error sending Email.', 'sccss' ); ?></p></div>
		<?php endif; ?>
		<div class="wrap">
			<h2 style="margin-bottom: 1em;"><?php _e( 'System Info', 'system-info' ); ?></h2>
			<form name="sccss-form" action="" method="post" enctype="multipart/form-data">
				<?php settings_fields( 'sccss_settings_group' ); ?>
				<div id="templateside">
					<?php do_action( '' ); ?>
					<p style="margin-top: 0"><?php _e( 'System Info displays...', 'sccss' ) ?></p>
					<?php do_action( '' ); ?>
				</div>
				<div id="template">
					<div>
						<textarea style="height: 500px;" readonly="readonly" onclick="this.focus();this.select()" id="system-info-textarea" title="<?php _e( 'To copy the system info, click below then press Ctrl + C (PC) or Cmd + C (Mac).', 'send-system-info' ); ?>">
<?php self::display() ?>
						</textarea>
					</div>
					<h3><?php _e( 'Send via Email', 'system-info' ) ?></h3>
					<?php self::email_form() ?>
					<h3><?php _e( 'Remote Viewing', 'system-info' ) ?></h3>
					<?php self::remote_viewing() ?>
				</div>
			</form>
		</div>
	<?php

	}

	static function display() {

		$browser = new Browser();
		if ( get_bloginfo( 'version' ) < '3.4' ) {
			$theme_data = get_theme_data( get_stylesheet_directory() . '/style.css' );
			$theme      = $theme_data['Name'] . ' ' . $theme_data['Version'];
		} else {
			$theme_data = wp_get_theme();
			$theme      = $theme_data->Name . ' ' . $theme_data->Version;
		}

			// Try to identifty the hosting provider
		$host = false;
		if( defined( 'WPE_APIKEY' ) ) {
			$host = 'WP Engine';
		} elseif( defined( 'PAGELYBIN' ) ) {
			$host = 'Pagely';
		}

		$request['cmd'] = '_notify-validate';

		$params = array(
			'sslverify'		=> false,
			'timeout'		=> 60,
			'body'			=> $request,
		);

		$response = wp_remote_post( 'https://www.paypal.com/cgi-bin/webscr', $params );

		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
			$WP_REMOTE_POST = 'wp_remote_post() works' . "\n";
		} else {
			$WP_REMOTE_POST = 'wp_remote_post() does not work' . "\n";
		}

self::display_output( $browser, $theme_data, $theme, $host, $WP_REMOTE_POST );

	}

	//Render Info Display
	static function display_output( $browser, $theme_data, $theme, $host, $WP_REMOTE_POST ) {
		global $wpdb; ?>
// Generated by Send System Info Plugin //

Multisite:                <?php echo is_multisite() ? 'Yes' . "\n" : 'No' . "\n" ?>

SITE_URL:                 <?php echo site_url() . "\n"; ?>
HOME_URL:                 <?php echo home_url() . "\n"; ?>

WordPress Version:        <?php echo get_bloginfo( 'version' ) . "\n"; ?>
Permalink Structure:      <?php echo get_option( 'permalink_structure' ) . "\n"; ?>
Active Theme:             <?php echo $theme . "\n"; ?>
<?php if( $host ) : ?>
Host:                     <?php echo $host . "\n"; ?>
<?php endif; ?>

Registered Post Stati:    <?php echo implode( ', ', get_post_stati() ) . "\n\n"; ?>
<?php if ( isset( $_GET['systeminfo'] ) ) {
	echo '// Browser of Current Viewer //';
	echo '<br /><br />';
} ?>
<?php echo $browser ; ?>
<?php if ( isset( $_GET['systeminfo'] ) ) {
	echo '<br />';
	echo '// End Browser of Current Viewer //';
	echo '<br />';
} ?>

PHP Version:              <?php echo PHP_VERSION . "\n"; ?>
MySQL Version:            <?php echo mysql_get_server_info() . "\n"; ?>
Web Server Info:          <?php echo $_SERVER['SERVER_SOFTWARE'] . "\n"; ?>

WordPress Memory Limit:   <?php echo ( self::let_to_num( WP_MEMORY_LIMIT )/( 1024 ) )."MB"; ?><?php echo "\n"; ?>
PHP Safe Mode:            <?php echo ini_get( 'safe_mode' ) ? "Yes" : "No\n"; ?>
PHP Memory Limit:         <?php echo ini_get( 'memory_limit' ) . "\n"; ?>
PHP Upload Max Size:      <?php echo ini_get( 'upload_max_filesize' ) . "\n"; ?>
PHP Post Max Size:        <?php echo ini_get( 'post_max_size' ) . "\n"; ?>
PHP Upload Max Filesize:  <?php echo ini_get( 'upload_max_filesize' ) . "\n"; ?>
PHP Time Limit:           <?php echo ini_get( 'max_execution_time' ) . "\n"; ?>
PHP Max Input Vars:       <?php echo ini_get( 'max_input_vars' ) . "\n"; ?>
PHP Arg Separator:        <?php echo ini_get( 'arg_separator.output' ) . "\n"; ?>
PHP Allow URL File Open:  <?php echo ini_get( 'allow_url_fopen' ) ? "Yes" : "No\n"; ?>

WP_DEBUG:                 <?php echo defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' . "\n" : 'Disabled' . "\n" : 'Not set' . "\n" ?>

WP Table Prefix:          <?php echo "Length: ". strlen( $wpdb->prefix ); echo " Status:"; if ( strlen( $wpdb->prefix )>16 ) {echo " ERROR: Too Long";} else {echo " Acceptable";} echo "\n"; ?>

Show On Front:            <?php echo get_option( 'show_on_front' ) . "\n" ?>
Page On Front:            <?php $id = get_option( 'page_on_front' ); echo get_the_title( $id ) . ' (#' . $id . ')' . "\n" ?>
Page For Posts:           <?php $id = get_option( 'page_for_posts' ); echo get_the_title( $id ) . ' (#' . $id . ')' . "\n" ?>

WP Remote Post:           <?php echo $WP_REMOTE_POST; ?>

Session:                  <?php echo isset( $_SESSION ) ? 'Enabled' : 'Disabled'; ?><?php echo "\n"; ?>
Session Name:             <?php echo esc_html( ini_get( 'session.name' ) ); ?><?php echo "\n"; ?>
Cookie Path:              <?php echo esc_html( ini_get( 'session.cookie_path' ) ); ?><?php echo "\n"; ?>
Save Path:                <?php echo esc_html( ini_get( 'session.save_path' ) ); ?><?php echo "\n"; ?>
Use Cookies:              <?php echo ini_get( 'session.use_cookies' ) ? 'On' : 'Off'; ?><?php echo "\n"; ?>
Use Only Cookies:         <?php echo ini_get( 'session.use_only_cookies' ) ? 'On' : 'Off'; ?><?php echo "\n"; ?>

DISPLAY ERRORS:           <?php echo ( ini_get( 'display_errors' ) ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A'; ?><?php echo "\n"; ?>
FSOCKOPEN:                <?php echo ( function_exists( 'fsockopen' ) ) ? 'Your server supports fsockopen.' : 'Your server does not support fsockopen.'; ?><?php echo "\n"; ?>
cURL:                     <?php echo ( function_exists( 'curl_init' ) ) ? 'Your server supports cURL.' : 'Your server does not support cURL.'; ?><?php echo "\n"; ?>
SOAP Client:              <?php echo ( class_exists( 'SoapClient' ) ) ? 'Your server has the SOAP Client enabled.' : 'Your server does not have the SOAP Client enabled.'; ?><?php echo "\n"; ?>
SUHOSIN:                  <?php echo ( extension_loaded( 'suhosin' ) ) ? 'Your server has SUHOSIN installed.' : 'Your server does not have SUHOSIN installed.'; ?><?php echo "\n"; ?>

ACTIVE PLUGINS:

<?php $plugins = get_plugins();
$active_plugins = get_option( 'active_plugins', array() );

foreach ( $plugins as $plugin_path => $plugin ) {
	// If the plugin isn't active, don't show it.
	if ( ! in_array( $plugin_path, $active_plugins ) )
		continue;

	echo $plugin['Name'] . ': ' . $plugin['Version'] ."\n";
}

if ( is_multisite() ) : ?>

	NETWORK ACTIVE PLUGINS:

	<?php $plugins  = wp_get_active_network_plugins();
	$active_plugins = get_site_option( 'active_sitewide_plugins', array() );

	foreach ( $plugins as $plugin_path ) {
		$plugin_base = plugin_basename( $plugin_path );

		// If the plugin isn't active, don't show it.
		if ( ! array_key_exists( $plugin_base, $active_plugins ) )
			continue;

		$plugin = get_plugin_data( $plugin_path );

		echo $plugin['Name'] . ' :' . $plugin['Version'] ."\n";
	}
endif;

}

	//Render Email Form
	static function email_form() {
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="system-info-email-address"><?php _e( 'Email Address', 'system-info' ) ?></label>
				</th>
				<td>
					<input type="email" name="system-info-email-address" id="system-info-email-address" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="system-info-email-subject"><?php _e( 'Subject', 'system-info' ) ?></label>
				</th>
				<td>
					<input type="text" name="system-info-email-subject" id="system-info-email-subject" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="system-info-email-message"><?php _e( 'Additional Message', 'system-info' ) ?></label>
				</th>
				<td>
					<textarea style="width: 50%; height: 200px;" name="system-info-email-message" id="system-info-email-message"></textarea>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Send Email', 'system-info' ) , 'secondary') ?>
		<?php
	}

	static function remote_viewing() {
		$value = get_option( 'system_info_remote_url' );
		$url   = home_url() . '/?systeminfo=' . $value;
		?>
		<p>Users with this URL can view a plain-text version of your System Data.</p>
		<p><span style="font-family: Consolas, Monaco, monospace;" class="system-info-url"><?php echo esc_url( $url ) ?></span></p>
		<p>Generating a new URL will void access to all who have the existing URL</p>
		<input type="submit" onClick="return false;" class="button-secondary" name="generate-new-url" value="<?php _e( 'Generate New URL', 'system-info' ) ?>" />

		<?php
	}

	static function generate_url() {
		$alphabet    = 'abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789';
		$value       = array();
		$alphaLength = strlen( $alphabet ) - 1;
		for ( $i = 0; $i < 32; $i++ ) {
			$n     = rand( 0, $alphaLength );
			$value[] = $alphabet[$n];
		}
		$value = implode( $value );
		update_option( 'system_info_remote_url', $value );
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$output = home_url() . '/?systeminfo=' . $value;
			wp_send_json( $output );
		}
	}

	static function send_email() {
		if ( isset( $_POST['system-info-email-address'] ) &&
			isset( $_POST['system-info-email-subject'] ) &&
			isset( $_POST['system-info-email-message'] ) ) {
			if ( ! empty( $_POST['system-info-email-address'] ) ) {
				$address = $_POST['system-info-email-address'];
			} else {
				return 'error';
			}
			if ( ! empty( $_POST['system-info-email-subject'] ) ) {
				$subject = $_POST['system-info-email-subject'];
			} else {
				return 'error';
			}
			if ( ! empty( $_POST['system-info-email-message'] ) ) {
				$message = $_POST['system-info-email-message'];
			} else {
				return 'error';
			}
			$user         = get_currentuserinfo();
			$display_name = $user->display_name;
			$headers = 'From: ' . $name .' <' . $address . '>' . "\r\n" . 'Reply-To: ' . $user->user_email;
			if ( wp_mail( $address, $subject, $headers ) ) {
				return 'sent';
			} else {
				return 'error';
			}
		}
		return false;
	}

	static function front_end_display() {
		$query_value = $_GET['systeminfo'];
		$value       = get_option( 'system_info_remote_url' );

		if ( $query_value == $value ) {
			echo '<pre>';
			self::display();
			echo '</pre>';
			exit;
		}

	}

	/**
	 * Does Size Conversions
	 *
	 * @author Chris Christoff
	 *
	 * @param unknown $v
	 * @return int|string
	 */
	static function let_to_num( $v ) {
		$l   = substr( $v, -1 );
		$ret = substr( $v, 0, -1 );

		switch ( strtoupper( $l ) ) {
			case 'P': // fall-through
			case 'T': // fall-through
			case 'G': // fall-through
			case 'M': // fall-through
			case 'K': // fall-through
				$ret *= 1024;
				break;
			default:
				break;
		}

		return $ret;
	}


}
add_action( 'plugins_loaded', array( 'Send_System_Info_Plugin', 'setup' ) );