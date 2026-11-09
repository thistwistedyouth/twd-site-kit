<?php
/**
 * TWD_SK_Shop_Gate: the one shop file every site loads. It decides whether the Shop module
 * (modules/shop/) wakes up, and holds its on and off switch.
 *
 * The module loads only when all of these hold: safe mode is off, the switch is on, and
 * WooCommerce is loaded at version MIN_WC or newer. WooCommerce loads after this plugin (plugins
 * load in name order), so the check runs on plugins_loaded, never when this file is read. A site
 * with the switch off gets no shop classes beyond this one, no shop routes, no shop CSS and no
 * shop JS. A fault while loading the module is caught and leaves it off.
 *
 * The switch is in WooCommerce > Settings > Products > Site Kit shop tools (so it is only there
 * when WooCommerce is), and in WP-CLI: wp twd-sk shop on|off|status.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_Gate {

	const OPTION  = 'twd_sk_shop';
	const SECTION = 'twd_sk_shop';
	const MIN_WC  = '8.0';

	private static $loaded = false;
	private static $fault  = '';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_load' ), 20 );
		add_filter( 'woocommerce_get_sections_products', array( __CLASS__, 'settings_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( __CLASS__, 'settings' ), 10, 2 );
	}

	/** The switch as saved (WooCommerce saves a tick box as yes or no). */
	public static function switched_on() {
		$v = get_option( self::OPTION, '' );
		return 'yes' === $v || '1' === $v || 1 === $v || true === $v;
	}

	public static function set( $on ) {
		update_option( self::OPTION, $on ? 'yes' : 'no' );
	}

	/** WooCommerce is loaded and new enough. */
	public static function wc_ok() {
		return class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' ) && version_compare( (string) WC_VERSION, self::MIN_WC, '>=' );
	}

	public static function on() {
		return ! TWD_SK_Safe::on() && self::switched_on() && self::wc_ok();
	}

	public static function loaded() {
		return self::$loaded;
	}

	public static function maybe_load() {
		if ( self::$loaded || ! self::on() ) {
			return;
		}
		try {
			require_once TWD_SK_PATH . 'modules/shop/load.php';
			TWD_SK_Shop_Loader::init();
			self::$loaded = true;
		} catch ( Throwable $e ) {
			self::$fault = 'The shop tools could not start: ' . $e->getMessage();
		}
	}

	/** One plain line for the CLI and the site report. */
	public static function status() {
		if ( '' !== self::$fault ) {
			return self::$fault;
		}
		if ( ! self::switched_on() ) {
			return 'The shop tools are off.';
		}
		if ( TWD_SK_Safe::on() ) {
			return 'The shop tools are switched on but resting, because safe mode is on.';
		}
		if ( ! self::wc_ok() ) {
			return 'The shop tools are switched on but resting, because WooCommerce ' . self::MIN_WC . ' or newer is not active.';
		}
		return 'The shop tools are on.';
	}

	// -- The switch in WooCommerce > Settings > Products -----------------------

	public static function settings_section( $sections ) {
		if ( is_array( $sections ) ) {
			$sections[ self::SECTION ] = 'Site Kit shop tools';
		}
		return $sections;
	}

	public static function settings( $settings, $section = '' ) {
		if ( self::SECTION !== $section ) {
			return $settings;
		}
		$desc = 'An Edit product window on each product page, and an Add a product button on the shop and category pages, for people who can edit products. Visitors see no difference.';
		if ( TWD_SK_Safe::on() ) {
			$desc .= ' Site Kit is in safe mode, so the tools rest until safe mode is off.';
		}
		return array(
			array( 'title' => 'Site Kit shop tools', 'type' => 'title', 'desc' => $desc, 'id' => 'twd_sk_shop_options' ),
			array(
				'title'   => 'Edit product window',
				'desc'    => 'Turn on the shop tools',
				'id'      => self::OPTION,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => 'Product page layout',
				'desc'    => 'Use the tidier product page (story, details card, sections that open)',
				'id'      => 'twd_sk_shop_layout',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'    => 'Shop colour',
				'desc'     => 'Headings and buttons on product pages, for example the colour of your header.',
				'id'       => 'twd_sk_shop_colour',
				'type'     => 'color',
				'default'  => '#1f3a33',
				'css'      => 'width:7em;',
				'desc_tip' => false,
			),
			array(
				'title'    => 'Enquiry page',
				'desc'     => 'Where "Ask about this piece" goes. Leave empty for no button.',
				'id'       => 'twd_sk_shop_enquiry_page',
				'type'     => 'single_select_page',
				'default'  => '',
				'args'     => array( 'show_option_none' => 'No button' ),
				'desc_tip' => false,
			),
			array(
				'title'    => 'Delivery and returns',
				'desc'     => 'One text for every product, shown as a section that opens. Leave empty to leave it out.',
				'id'       => 'twd_sk_shop_delivery',
				'type'     => 'textarea',
				'default'  => '',
				'css'      => 'min-height:140px;width:100%;max-width:640px;',
				'desc_tip' => false,
			),
			array( 'type' => 'sectionend', 'id' => 'twd_sk_shop_options' ),
		);
	}
}
