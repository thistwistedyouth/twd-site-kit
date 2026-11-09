<?php
/**
 * The Shop module (0.20.0): the gate, the product text cleaner, reading and writing a product,
 * versions with Undo and Restore, publishing, and the browser script's rules. WooCommerce is
 * stood in for by a small fake product below.
 */

if ( ! class_exists( 'WooCommerce' ) ) {
	class WooCommerce {} // phpcs:ignore
}
if ( ! defined( 'WC_VERSION' ) ) {
	define( 'WC_VERSION', '11.2.2' );
}

class WC_Product_Attribute {
	private $d = array( 'id' => 0, 'name' => '', 'options' => array(), 'position' => 0, 'visible' => true, 'variation' => false );
	public function set_id( $v ) { $this->d['id'] = (int) $v; }
	public function get_id() { return $this->d['id']; }
	public function set_name( $v ) { $this->d['name'] = $v; }
	public function get_name() { return $this->d['name']; }
	public function set_options( $v ) { $this->d['options'] = $v; }
	public function get_options() { return $this->d['options']; }
	public function set_position( $v ) { $this->d['position'] = $v; }
	public function get_position() { return $this->d['position']; }
	public function set_visible( $v ) { $this->d['visible'] = $v; }
	public function set_variation( $v ) { $this->d['variation'] = $v; }
	public function is_taxonomy() { return $this->d['id'] > 0; }
}

class Twd_Fake_Product {
	public $d     = array();
	public $calls = array();
	public $saves = 0;
	public function __construct( $id = 0, $d = array() ) {
		$this->d = $d + array(
			'id' => $id, 'type' => 'simple', 'name' => 'Victorian garnet brooch', 'regular_price' => '450', 'sale_price' => '', 'sku' => '',
			'category_ids' => array(), 'stock_status' => 'instock', 'manage_stock' => false, 'stock_quantity' => null, 'sold_individually' => false,
			'short_description' => '', 'description' => '', 'attributes' => array(), 'status' => 'publish',
		);
	}
	public function __call( $name, $args ) {
		if ( 0 === strpos( $name, 'get_' ) ) {
			return $this->d[ substr( $name, 4 ) ];
		}
		if ( 0 === strpos( $name, 'set_' ) ) {
			$key = substr( $name, 4 );
			if ( 'sku' === $key && 'TAKEN' === $args[0] ) {
				throw new Exception( 'Invalid or duplicated SKU.' );
			}
			$this->calls[]   = $key;
			$this->d[ $key ] = $args[0];
			return null;
		}
		throw new Exception( 'No method ' . $name );
	}
	public function get_type() { return $this->d['type']; }
	public function save() {
		$this->saves++;
		if ( ! $this->d['id'] ) {
			$this->d['id'] = 900;
		}
		$GLOBALS['twd_fake_products'][ $this->d['id'] ] = $this;
		return $this->d['id'];
	}
}

class WC_Product_Simple extends Twd_Fake_Product {
	public function __construct() {
		parent::__construct( 0 );
	}
}

function wc_get_product( $id ) {
	return isset( $GLOBALS['twd_fake_products'][ (int) $id ] ) ? $GLOBALS['twd_fake_products'][ (int) $id ] : false;
}
function get_terms( $args ) {
	$out = array();
	foreach ( (array) $GLOBALS['twd_fake_cats'] as $id => $name ) {
		$out[] = (object) array( 'term_id' => $id, 'name' => $name, 'parent' => 0 );
	}
	return $out;
}
function wc_attribute_label( $name ) {
	return 'pa_metal' === $name ? 'Metal' : $name;
}
function wc_get_product_terms( $id, $name, $args ) {
	return array( 'Gold' );
}

function twd_shop_setup( $d = array() ) {
	$GLOBALS['twd_fake_cats']     = array( 11 => 'Brooches', 12 => 'Rings' );
	$GLOBALS['twd_fake_products'] = array();
	$p                            = new Twd_Fake_Product( 42, $d );
	$GLOBALS['twd_fake_products'][42] = $p;
	twd_stub_add_post( 42, 'product' );
	return $p;
}

function twd_shop_input( $over = array() ) {
	return $over + array(
		'title'    => 'Victorian garnet brooch',
		'price'    => '450',
		'sale'     => '',
		'sku'      => '',
		'cats'     => array(),
		'sold'     => false,
		'details'  => array(),
		'summary'  => '',
		'sections' => array(),
	);
}

function twd_shop_base( $p ) {
	return TWD_SK_Shop_Product::fingerprint( TWD_SK_Shop_Product::fields( $p ) );
}

// -- The gate -----------------------------------------------------------------

twd_sk_test( 'shop gate: off until switched on, off in safe mode, on with WooCommerce and the switch', function () {
	twd_sk_true( false === TWD_SK_Shop_Gate::on(), 'off by default' );
	TWD_SK_Shop_Gate::set( true );
	twd_sk_eq( 'yes', get_option( 'twd_sk_shop' ) );
	twd_sk_true( TWD_SK_Shop_Gate::on(), 'on' );
	update_option( 'twd_sk_safe_mode', true );
	twd_sk_true( false === TWD_SK_Shop_Gate::on(), 'safe mode wins' );
	twd_sk_has( 'safe mode', TWD_SK_Shop_Gate::status() );
	TWD_SK_Shop_Gate::set( false );
	twd_sk_eq( 'no', get_option( 'twd_sk_shop' ) );
	twd_sk_eq( 'The shop tools are off.', TWD_SK_Shop_Gate::status() );
} );

twd_sk_test( 'shop gate: the switch sits in its own WooCommerce settings section and nowhere else', function () {
	$sections = TWD_SK_Shop_Gate::settings_section( array( '' => 'General' ) );
	twd_sk_eq( 'Site Kit shop tools', $sections['twd_sk_shop'] );
	twd_sk_eq( array( 'x' ), TWD_SK_Shop_Gate::settings( array( 'x' ), '' ), 'other sections untouched' );
	$ours = TWD_SK_Shop_Gate::settings( array( 'x' ), 'twd_sk_shop' );
	$ids  = array_map( function ( $s ) {
		return $s['id'];
	}, $ours );
	twd_sk_true( in_array( 'twd_sk_shop', $ids, true ) );
	twd_sk_eq( 'checkbox', $ours[1]['type'] );
} );

twd_sk_test( 'shop isolation: the main file loads only the gate; every module file is in the loader; the kit allowlist has no shop class', function () {
	$main = file_get_contents( ABSPATH . 'twd-site-kit.php' );
	twd_sk_has( "includes/class-twd-sk-shop-gate.php'", $main );
	twd_sk_true( 0 === preg_match( "#require_once[^\n]*modules/#", $main ), 'no module file required by the main file' );
	$load = file_get_contents( ABSPATH . 'modules/shop/load.php' );
	foreach ( glob( ABSPATH . 'modules/shop/includes/*.php' ) as $file ) {
		twd_sk_has( "'/includes/" . basename( $file ) . "'", $load, basename( $file ) );
	}
	foreach ( array_keys( TWD_SK_Registry::allowed_classes() ) as $class ) {
		twd_sk_true( 0 !== strpos( $class, 'twd-sk-shop' ), $class );
	}
	$gate = file_get_contents( ABSPATH . 'includes/class-twd-sk-shop-gate.php' );
	twd_sk_has( "add_action( 'plugins_loaded'", $gate, 'checked once WooCommerce has loaded' );
	twd_sk_has( 'modules', file_get_contents( ABSPATH . 'bin/build-zip.sh' ), 'the zip carries the module folder' );
} );

twd_sk_test( 'shop REST: a module route without the capability gets a plain refusal, not the create-pages wording', function () {
	$GLOBALS['twd_stub']['caps'] = array( 'read' );
	$req = new WP_REST_Request( array( 'id' => 42 ), array( 'X-WP-Nonce' => 'good-nonce' ) );
	$r = TWD_SK_REST::gate( $req, 'read', 'edit_products' );
	twd_sk_is_error( 'twd_sk_forbidden', $r );
	twd_sk_eq( 'You do not have permission to do that.', $r->get_error_message() );
} );

// -- The text cleaner ----------------------------------------------------------------

twd_sk_test( 'shop text: keeps paragraphs, bold, italic, lists and safe links; drops scripts, styles, classes, pictures and bad links', function () {
	$out = TWD_SK_Shop_Text::clean_section( '<div class="x" style="color:red"><p onclick="a()">A <b>gold</b> and <i>garnet</i> brooch</p><script>bad()</script><img src="/a.jpg" alt="a"><ul><li>One</li><li>Two</li></ul><p><a href="javascript:alert(1)">bad</a> and <a href="https://example.com/x" class="y">good</a></p><h4>Small heading</h4></div>' );
	twd_sk_has( '<p>A <strong>gold</strong> and <em>garnet</em> brooch</p>', $out );
	twd_sk_has( '<ul><li>One</li><li>Two</li></ul>', $out );
	twd_sk_has( '<a href="https://example.com/x">good</a>', $out );
	twd_sk_has( '<p>Small heading</p>', $out );
	foreach ( array( 'script', 'bad()', 'img', 'class=', 'style=', 'onclick', 'javascript' ) as $gone ) {
		twd_sk_hasnt( $gone, $out, $gone );
	}
} );

twd_sk_test( 'shop text: plain text with blank lines becomes paragraphs; long dashes go; an apostrophe stays one', function () {
	$out = TWD_SK_Shop_Text::clean_section( "A ring from about 1870 \xE2\x80\x94 in its box.\n\nIt's lovely." );
	twd_sk_has( '<p>', $out );
	twd_sk_has( "It's lovely.", $out );
	twd_sk_hasnt( "\xE2\x80\x94", $out );
	twd_sk_true( 2 === substr_count( $out, '<p>' ), 'two paragraphs' );
	twd_sk_eq( '<p>Short <strong>bold</strong></p>', TWD_SK_Shop_Text::clean_summary( '<ul><li>Short <b>bold</b></li></ul>' ) );
} );

twd_sk_test( 'shop text: the description splits into sections by heading and joins back the same', function () {
	$desc     = '<p>The story.</p><h2>Condition</h2><p>Light wear.</p><h3 class="z">History</h3><p>From a collection.</p>';
	$sections = TWD_SK_Shop_Text::split( $desc );
	twd_sk_eq( 3, count( $sections ) );
	twd_sk_eq( 'About this piece', $sections[0]['heading'] );
	twd_sk_eq( 'Condition', $sections[1]['heading'] );
	twd_sk_eq( '<p>From a collection.</p>', $sections[2]['html'] );
	$joined = TWD_SK_Shop_Text::join( $sections );
	twd_sk_has( '<h3>Condition</h3>', $joined );
	twd_sk_eq( $sections, TWD_SK_Shop_Text::split( $joined ), 'round trip' );
	twd_sk_eq( array(), TWD_SK_Shop_Text::split( '' ) );
	twd_sk_hasnt( 'Empty', TWD_SK_Shop_Text::join( array( array( 'heading' => 'Empty', 'html' => '' ) ) ), 'an empty section is left out' );
} );

// -- Checking and saving ----------------------------------------------------------------

twd_sk_test( 'shop validate: prices, reduced price, title, labels, | and the label ticks', function () {
	$p = twd_shop_setup();
	twd_sk_eq( '1660.50', TWD_SK_Shop_Product::price( "\xC2\xA31,660.50" ) );
	twd_sk_eq( '', TWD_SK_Shop_Product::price( '' ) );
	twd_sk_true( false === TWD_SK_Shop_Product::price( 'about 400' ) );
	$bad = TWD_SK_Shop_Product::validate( twd_shop_input( array( 'title' => '', 'price' => 'lots', 'sale' => '500' ) ), $p );
	twd_sk_is_error( 'twd_sk_shop_invalid', $bad );
	twd_sk_has( 'title cannot be empty', $bad->get_error_message() );
	twd_sk_has( 'price must be a number', $bad->get_error_message() );
	twd_sk_is_error( 'twd_sk_shop_invalid', TWD_SK_Shop_Product::validate( twd_shop_input( array( 'sale' => '500' ) ), $p ), 'reduced above price' );
	twd_sk_is_error( 'twd_sk_shop_invalid', TWD_SK_Shop_Product::validate( twd_shop_input( array( 'details' => array( array( 'label' => 'Metal', 'value' => 'Gold' ), array( 'label' => 'metal', 'value' => 'Silver' ) ) ) ), $p ), 'label twice' );
	$rows = array();
	foreach ( array( 'Period', 'Metal', 'Stones', 'Size', 'Weight' ) as $l ) {
		$rows[] = array( 'label' => $l, 'value' => 'A | B', 'tag' => true );
	}
	$ok = TWD_SK_Shop_Product::validate( twd_shop_input( array( 'details' => $rows, 'cats' => array( 12, 99, '11' ) ) ), $p );
	twd_sk_eq( 'A / B', $ok['fields']['details'][0]['value'] );
	twd_sk_true( false === $ok['fields']['details'][4]['tag'], 'fifth tick dropped' );
	twd_sk_eq( array( 11, 12 ), $ok['fields']['cats'], 'unknown category dropped, sorted' );
	twd_sk_eq( 2, count( $ok['notes'] ) );
} );

twd_sk_test( 'shop save: only what changed is written; versions keep the state before and after', function () {
	$p   = twd_shop_setup( array( 'description' => '<p>Old story.</p>' ) );
	$res = TWD_SK_Shop_Rest::save( 42, twd_shop_input( array(
		'price'    => '480',
		'details'  => array( array( 'label' => 'Period', 'value' => 'Victorian, about 1870', 'tag' => true ) ),
		'sections' => array( array( 'heading' => 'About this piece', 'html' => '<p>Old story.</p>' ), array( 'heading' => 'Condition', 'html' => '<p>Light wear.</p>' ) ),
	) ), twd_shop_base( $p ) );
	twd_sk_true( true === $res['saved'] );
	twd_sk_eq( array( 'regular_price', 'attributes', 'description' ), $p->calls );
	twd_sk_eq( 1, $p->saves );
	twd_sk_has( '<h3>Condition</h3>', $p->d['description'] );
	twd_sk_eq( array( 'Period' ), get_post_meta( 42, '_twd_sk_shop_labels', true ) );
	$v = TWD_SK_Shop_History::all( 42 );
	twd_sk_eq( 2, count( $v ) );
	twd_sk_eq( '450', $v[0]['fields']['price'] );
	twd_sk_eq( '480', $v[1]['fields']['price'] );
	$again = TWD_SK_Shop_Rest::save( 42, TWD_SK_Shop_Product::fields( $p ), twd_shop_base( $p ) );
	twd_sk_true( false === $again['saved'], 'nothing changed' );
	twd_sk_eq( 2, count( TWD_SK_Shop_History::all( 42 ) ) );
} );

twd_sk_test( 'shop save: a stale window is refused; a used stock number is refused in plain words; other products are refused', function () {
	$p = twd_shop_setup();
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'price' => '1' ) ), 'old' ) );
	$r = TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'sku' => 'TAKEN' ) ), twd_shop_base( $p ) );
	twd_sk_is_error( 'twd_sk_shop_refused', $r );
	twd_sk_has( 'stock number', $r->get_error_message() );
	$GLOBALS['twd_fake_products'][43] = new Twd_Fake_Product( 43, array( 'type' => 'variable' ) );
	twd_sk_is_error( 'twd_sk_shop_not_simple', TWD_SK_Shop_Rest::save( 43, twd_shop_input(), 'x' ) );
} );

twd_sk_test( 'shop save: sold and for sale set the stock of a one-off piece', function () {
	$p = twd_shop_setup();
	TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'sold' => true ) ), twd_shop_base( $p ) );
	twd_sk_eq( 'outofstock', $p->d['stock_status'] );
	twd_sk_eq( 0, $p->d['stock_quantity'] );
	twd_sk_true( true === $p->d['manage_stock'] && true === $p->d['sold_individually'] );
	TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'sold' => false ) ), twd_shop_base( $p ) );
	twd_sk_eq( 'instock', $p->d['stock_status'] );
	twd_sk_eq( 1, $p->d['stock_quantity'] );
} );

twd_sk_test( 'shop save: global attributes are kept and shown read-only; local rows replace the old local ones', function () {
	$metal = new WC_Product_Attribute();
	$metal->set_id( 3 );
	$metal->set_name( 'pa_metal' );
	$old = new WC_Product_Attribute();
	$old->set_name( 'Size' );
	$old->set_options( array( 'Small' ) );
	$p = twd_shop_setup( array( 'attributes' => array( 'pa_metal' => $metal, 'size' => $old ) ) );
	$read = TWD_SK_Shop_Product::read( 42 );
	twd_sk_eq( array( array( 'label' => 'Metal', 'value' => 'Gold' ) ), $read['fixed'] );
	twd_sk_eq( 'Size', $read['fields']['details'][0]['label'] );
	twd_sk_is_error( 'twd_sk_shop_invalid', TWD_SK_Shop_Product::validate( twd_shop_input( array( 'details' => array( array( 'label' => 'Metal', 'value' => 'Silver' ) ) ) ), $p ), 'clash with a global one' );
	TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'details' => array( array( 'label' => 'Weight', 'value' => '4.2 g' ) ) ) ), twd_shop_base( $p ) );
	twd_sk_eq( array( 'pa_metal', 'weight' ), array_keys( $p->d['attributes'] ) );
	twd_sk_eq( array( '4.2 g' ), $p->d['attributes']['weight']->get_options() );
} );

twd_sk_test( 'shop undo: goes back one step, then says there is nothing earlier; restore picks a version', function () {
	$p = twd_shop_setup();
	TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'price' => '500' ) ), twd_shop_base( $p ) );
	TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'price' => '550' ) ), twd_shop_base( $p ) );
	TWD_SK_Shop_Rest::undo( 42 );
	twd_sk_eq( '500', $p->d['regular_price'] );
	TWD_SK_Shop_Rest::undo( 42 );
	twd_sk_eq( '450', $p->d['regular_price'] );
	twd_sk_is_error( 'twd_sk_no_undo', TWD_SK_Shop_Rest::undo( 42 ) );
	TWD_SK_Shop_Rest::restore( 42, 3 );
	twd_sk_eq( '550', $p->d['regular_price'] );
	twd_sk_is_error( 'twd_sk_shop_no_version', TWD_SK_Shop_Rest::restore( 42, 99 ) );
} );

twd_sk_test( 'shop undo: a change made in WooCommerce since is kept as a version first, and Undo goes back to the last save here', function () {
	$p = twd_shop_setup();
	TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'price' => '500' ) ), twd_shop_base( $p ) );
	$p->d['regular_price'] = '999';
	TWD_SK_Shop_Rest::undo( 42 );
	twd_sk_eq( '500', $p->d['regular_price'] );
	$notes = array_map( function ( $v ) {
		return $v['note'];
	}, TWD_SK_Shop_History::all( 42 ) );
	twd_sk_true( in_array( 'Changed outside this window', $notes, true ) );
	twd_sk_true( null !== TWD_SK_Shop_History::find( 42, 3 ) && '999' === TWD_SK_Shop_History::find( 42, 3 )['fields']['price'] );
} );

twd_sk_test( 'shop history: ten versions at most, numbers never reused', function () {
	$p = twd_shop_setup();
	for ( $i = 1; $i <= 12; $i++ ) {
		TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'price' => (string) ( 500 + $i ) ) ), twd_shop_base( $p ) );
	}
	$all = TWD_SK_Shop_History::all( 42 );
	twd_sk_eq( 10, count( $all ) );
	twd_sk_eq( 13, end( $all )['v'] );
} );

twd_sk_test( 'shop publish: refused while TO CONFIRM or a placeholder is left; a new draft starts with a placeholder title', function () {
	twd_shop_setup();
	$id = TWD_SK_Shop_Product::create( 12 );
	twd_sk_eq( 900, $id );
	$p = wc_get_product( 900 );
	twd_sk_eq( 'draft', $p->d['status'] );
	twd_sk_eq( array( 12 ), $p->d['category_ids'] );
	twd_sk_is_error( 'twd_sk_shop_unfinished', TWD_SK_Shop_Product::publish( 900 ) );
	$p->d['name']        = 'Garnet ring';
	$p->d['description'] = '<h3>Condition</h3><p>Light wear, TO CONFIRM.</p>';
	$r = TWD_SK_Shop_Product::publish( 900 );
	twd_sk_is_error( 'twd_sk_shop_unfinished', $r );
	twd_sk_has( 'the section Condition', $r->get_error_message() );
	$p->d['description'] = '<h3>Condition</h3><p>Light wear.</p>';
	twd_sk_true( true === TWD_SK_Shop_Product::publish( 900 ) );
	twd_sk_eq( 'publish', $p->d['status'] );
} );

// -- The browser script ------------------------------------------------------------------

twd_sk_test( 'shop window js: no way to inject or run code, no browser storage, no web address', function () {
	$js = file_get_contents( ABSPATH . 'modules/shop/assets/twd-site-kit-shop-editor.js' );
	foreach ( array( 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'eval(', 'new Function', 'document.write', 'srcdoc', 'localStorage', 'sessionStorage', 'http://', 'https://', 'confirm(', 'alert(' ) as $bad ) {
		twd_sk_hasnt( $bad, $js, $bad );
	}
	twd_sk_has( "'X-WP-Nonce'", $js );
	twd_sk_has( "aria-modal", $js );
} );
