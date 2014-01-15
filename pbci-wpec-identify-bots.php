<?php
/*
Plugin Name: WPEC Bot Detector by Pye Brook
Plugin URI: http://www.pyebrook.com
Description: Identify users as bots
Author: Pye Brook Company, Inc.
Author URI: http://www.pyebrook.com
Version: 1.0
*/

//////////////////////////////////////////////////////////////////////////////
// wrappers to retrieve current configuration values, it's where the defaults
// are set
function pbci_bot_detector_blocked_host_names() {
	return get_option( 'blocked_host_names', 'search.msn.com,.amazonaws.com' );
}

function pbci_bot_detector_blocked_countries() {
	return get_option( 'blocked_country_list', 'CN,RU');
}

function pbci_bot_detector_blocked_continents() {
	return get_option( 'blocked_continent_list', array() );
}

function pbci_bot_detector_blocked_agents() {
	return get_option( 'blocked_user_agents', 'squider,slurp,pinterest.com' );
}
//////////////////////////////////////////////////////////////////////////////



add_filter( 'wpsc_is_bot_user', 'pbci_filter_is_a_bot_user' );

function pbci_filter_is_a_bot_user( $is_bot_user = false) {

	if ( $is_bot_user )
		return $is_bot_user;

	$hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);

	// look for each host name string, if one is found we can return right away
	$blocked_host_name_list = explode( ',', pbci_bot_detector_blocked_host_names() );
	foreach( $blocked_host_name_list as $blocked_host_name ) {
		if ( stripos( $hostname, 'search.msn.com' ) !== false ) {
			return true;
		}
	}

	$blocked_country_list   = strtoupper( pbci_bot_detector_blocked_countries() );
	$blocked_country_list = explode( ',', $blocked_country_list );

	$blocked_continent_list = strtoupper( pbci_bot_detector_blocked_continents() );
	$blocked_continent_list = explode( ',', $blocked_continent_list );

	if ( !empty( $blocked_country_list ) || !empty( $blocked_continent_list ) ) {
		$ip_info = pbci_is_a_bot_get_ip_info( '93.199.212.79' );

		$country_code = $ip_info->country_code();
		if ( !empty( $country_code ) && isset($blocked_country_list[$country_code]) ) {
			$is_bot_user = true;
		}

		$continent_code = $ip_info->continent_code();
		if ( !empty( $continent_code ) && isset($blocked_continent_list[$continent_code]) ) {
			$is_bot_user = true;
		}
	}

	return $is_bot_user;
}


add_filter( 'wpsc_bot_user_agents', 'pbci_is_a_bot_filter_wpsc_bot_user_agents' );

function pbci_is_a_bot_filter_wpsc_bot_user_agents( $agent_array ) {

	$blocked_user_agent_list = pbci_bot_detector_blocked_agents();

	if ( empty ( $blocked_user_agent_list ) ) {
		$agent_array[] = 'squider';
		$agent_array[] = 'slurp';
		$agent_array[] = 'pinterest.com';
	} else {
		$also_check_agent_array = explode( ',' , $blocked_user_agent_list );
		$agent_array = array_merge( $also_check_agent_array, $agent_array );
	}

	return $agent_array;
}



////////////////////////////////////////////////////////////////////////////
// Check the user to see where they are from


//add_action ( 'after_setup_theme', 'get_ip_info' ); // after sp framework
function pbci_is_a_bot_get_ip_info($ip='') {

	if ( !defined( 'GEOIP_COUNTRY_BEGIN' ) ) {
		include_once( 'geoip.inc' );
	}

	if ( empty($ip) ) {
		$ip = $_SERVER['REMOTE_ADDR'];
	}

	$ip = new ip_info($ip);
	return $ip;
}


class pbci_is_a_bot_ip_info {
	private $ip = null;
	private $record = null;

	private static $gi = false;

	function __construct($ip='') {
		if(self::$gi === false)
		{
			$plugin_dir_path = dirname(__FILE__);
			$db = $plugin_dir_path.'/GeoIP.dat';
			self::$gi = geoip_open($db,GEOIP_STANDARD);
		}

		$this->ip = $ip;
		$this->record = geoip_record_by_addr(self::$gi,$ip);
	}

	function ip() {
		return isset($this->ip)?$this->ip:'';
	}

	function country_code() {
		return isset($this->record->country_code)?$this->record->country_code:'';
	}

	function continent_code() {
		return isset($this->record->country_name)?$this->record->country_name:'';
	}

}


function pbci_is_a_bot_customer_geo() {

	static $set_customer_geo = false;

	if ( $set_customer_geo )
		return;

	$customer_ip = wpsc_get_customer_meta( 'geo_ip' );

	if ( empty( $customer_ip) ) {
		$visitor_info = get_ip_info();

		$ip = $visitor_info->ip();
		$country_code = $visitor_info->country_code();
		$country_code3 = $visitor_info->country_code3();
		$country_name = $visitor_info->country_name();

		if ( !empty( $country_code ) ) {
			wpsc_update_customer_meta( 'geo_country_code', $country_code );
		}
		if ( !empty( $country_code3 ) ) {
			wpsc_update_customer_meta( 'geo_country_code3', $country_code3 );
		}
		if ( !empty( $country_name ) ) {
			wpsc_update_customer_meta( 'geo_country_name', $country_name );
		}
	}
}

if ( is_admin() ) {
	add_action('admin_menu', 'wpec_bot_detector_menu');
	add_action( 'admin_init', 'bot_detector_settings' );

	function bot_detector_settings() {
		register_setting( 'bot-detector-options-group', 'blocked_country_list' );
		register_setting( 'bot-detector-options-group', 'blocked_continent_list' );
		register_setting( 'bot-detector-options-group', 'blocked_user_agents' );
		register_setting( 'bot-detector-options-group', 'blocked_host_names' );
	}

	function wpec_bot_detector_menu() {
		add_options_page('WPEC Bot Detector', 'WPEC Bot Detector', 'manage_options', __FILE__, 'bot_detector_options');
	}

	function bot_detector_options() {

		$blocked_country_list    = pbci_bot_detector_blocked_countries();
		$blocked_continent_list  = pbci_bot_detector_blocked_continents();
		$blocked_host_name_list  = pbci_bot_detector_blocked_host_names();
		$blocked_user_agent_list =  pbci_bot_detector_blocked_agents();

		?>
		<div class="wrap">
		<h2>WPEC Bot Detector Options</h2>

		<form method="post" action="options.php">
		<?php settings_fields( 'bot-detector-options-group' ); ?>
		<?php do_settings_sections( 'bot-detector-options-group' ); ?>
		<table class="form-table">
	        <tr valign="top">
	        <th scope="row">Visits from these countries are bots (comma delim list of two letter country codes)</th>
	        <td><textarea rows="5" cols="60" name="blocked_country_list"><?php echo $blocked_country_list; ?> </textarea></td>
	        </tr>

	        <tr valign="top">
	        <th scope="row">Visits from these continents are bots (comma delim list of two letter continent codes)</th>
	        <td><textarea  rows="5" cols="60" name="blocked_host_names"><?php echo $blocked_continent_list; ?></textarea></td>
	        </tr>

	        <tr valign="top">
	        <th scope="row">Visits from these host names are bots (comma delim list)</th>
	        <td><textarea  rows="5" cols="60" name="blocked_host_names"><?php echo $blocked_host_name_list; ?></textarea></td>
	        </tr>

	        <tr valign="top">
	        <th scope="row">Visits from these user agents are bots (comma delim list)</th>
	        <td><textarea  rows="5" cols="60" name="blocked_user_agents"><?php echo $blocked_user_agent_list; ?></textarea></td>
	        </tr>


        </table>



		<?php submit_button(); ?>
		</form>
		</div>
		<?php
	}
}




