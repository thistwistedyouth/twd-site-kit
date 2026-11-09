<?php
/**
 * TWD_SK_Shop_Reviews_Admin (0.21.0): the reviews in wp-admin, for people who can edit products.
 *
 * - Products > Shop reviews: the list (platform, stars, month, the start of the words, Favourite,
 *   Hidden) and an edit screen with one box of fields, checked like the import.
 * - Products > Import reviews: choose a CSV, Check (saves nothing) or Import, and a report on
 *   every row; the blank template downloads from there.
 * - The review settings, added to WooCommerce > Settings > Products > Site Kit shop tools.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_Reviews_Admin {

	const PAGE     = 'twd-sk-reviews-import';
	const NONCE    = 'twd_sk_reviews';
	const TEMPLATE = 'twd_sk_reviews_template';

	public static function init() {
		add_filter( 'woocommerce_get_settings_products', array( __CLASS__, 'settings' ), 20, 2 );
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'add_meta_boxes_' . TWD_SK_Shop_Reviews::TYPE, array( __CLASS__, 'add_box' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'title' ), 10, 2 );
		add_action( 'save_post_' . TWD_SK_Shop_Reviews::TYPE, array( __CLASS__, 'save_box' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_filter( 'manage_' . TWD_SK_Shop_Reviews::TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . TWD_SK_Shop_Reviews::TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'admin_post_' . self::TEMPLATE, array( __CLASS__, 'download_template' ) );
	}

	// -- Settings -----------------------------------------------------------------------------

	/** The review settings, put before the end of the Site Kit shop tools section. */
	public static function settings( $settings, $section = '' ) {
		if ( TWD_SK_Shop_Gate::SECTION !== $section || ! is_array( $settings ) ) {
			return $settings;
		}
		$ours = array(
			array( 'title' => 'Shop reviews', 'type' => 'title', 'desc' => 'Reviews from Etsy, eBay and elsewhere, kept on this site: Products > Shop reviews, and Products > Import reviews. Show them with [twd_reviews] (strip), [twd_reviews style="wall"] or [twd_reviews style="single"]; on a Site Kit page, put the shortcode in an Embed section.', 'id' => 'twd_sk_shop_reviews_options' ),
			array( 'title' => 'Reviews page', 'desc' => 'Where "Read all reviews" goes (the page with the wall). Leave empty for no link.', 'id' => TWD_SK_Shop_Reviews::OPT_PAGE, 'type' => 'single_select_page', 'default' => '', 'args' => array( 'show_option_none' => 'No link' ), 'desc_tip' => false ),
			array( 'title' => 'Reviewer names', 'id' => TWD_SK_Shop_Reviews::OPT_NAMES, 'type' => 'select', 'default' => '', 'options' => array( '' => 'As given', 'initials' => 'First name and initial (Sarah K.)' ) ),
			array( 'title' => 'On product pages', 'desc' => 'Show one short favourite review under Ask about this piece', 'id' => TWD_SK_Shop_Reviews::OPT_PRODUCT, 'type' => 'checkbox', 'default' => 'yes' ),
			array( 'title' => 'Etsy total', 'desc' => 'How many reviews the Etsy shop shows. Typed by you, never counted. Leave empty to leave Etsy out of the summary line.', 'id' => 'twd_sk_shop_reviews_etsy_total', 'type' => 'number', 'default' => '', 'custom_attributes' => array( 'min' => 0, 'step' => 1 ), 'desc_tip' => false ),
			array( 'title' => 'Etsy reviews link', 'desc' => 'The address of the shop\'s reviews on Etsy.', 'id' => 'twd_sk_shop_reviews_etsy_link', 'type' => 'url', 'default' => '', 'desc_tip' => false ),
			array( 'title' => 'eBay total', 'desc' => 'How many reviews (feedback) the eBay shop shows. Typed by you, never counted.', 'id' => 'twd_sk_shop_reviews_ebay_total', 'type' => 'number', 'default' => '', 'custom_attributes' => array( 'min' => 0, 'step' => 1 ), 'desc_tip' => false ),
			array( 'title' => 'eBay reviews link', 'desc' => 'The address of the shop\'s feedback on eBay.', 'id' => 'twd_sk_shop_reviews_ebay_link', 'type' => 'url', 'default' => '', 'desc_tip' => false ),
			array( 'title' => 'Words before "reviews"', 'desc' => 'Optional, for example five-star. The line reads: 312 five-star reviews on Etsy and eBay.', 'id' => TWD_SK_Shop_Reviews::OPT_WORDS, 'type' => 'text', 'default' => '', 'desc_tip' => false ),
			array( 'type' => 'sectionend', 'id' => 'twd_sk_shop_reviews_options' ),
		);
		return array_merge( $settings, $ours );
	}

	// -- Menu and the import screen --------------------------------------------------------------

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=product', 'Import reviews', 'Import reviews', 'edit_products', self::PAGE, array( __CLASS__, 'import_page' ) );
	}

	public static function template_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::TEMPLATE ), self::TEMPLATE );
	}

	public static function download_template() {
		if ( ! current_user_can( 'edit_products' ) || ! check_admin_referer( self::TEMPLATE ) ) {
			wp_die( 'You do not have permission to do that.' );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="reviews-template.csv"' );
		echo TWD_SK_Shop_Reviews_Import::template(); // phpcs:ignore WordPress.Security.EscapeOutput -- a fixed header row.
		exit;
	}

	/** Handle a posted file. Returns a report, an array( 'error' ), or null when nothing was posted. */
	private static function handle_post() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || ! isset( $_POST['twd_sk_reviews_go'] ) ) { // phpcs:ignore
			return null;
		}
		if ( ! current_user_can( 'edit_products' ) || ! check_admin_referer( self::NONCE ) ) {
			return array( 'error' => 'You do not have permission to do that.' );
		}
		$file = isset( $_FILES['twd_sk_reviews_csv'] ) ? $_FILES['twd_sk_reviews_csv'] : null; // phpcs:ignore
		if ( ! is_array( $file ) || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return array( 'error' => 'Choose a CSV file first.' );
		}
		if ( (int) $file['size'] > TWD_SK_Shop_Reviews_Import::MAX_BYTES ) {
			return array( 'error' => 'The file is larger than 2 MB. Split it into smaller files.' );
		}
		$platform = isset( $_POST['twd_sk_reviews_platform'] ) ? sanitize_key( wp_unslash( $_POST['twd_sk_reviews_platform'] ) ) : ''; // phpcs:ignore
		if ( ! isset( TWD_SK_Shop_Reviews::platforms()[ $platform ] ) ) {
			$platform = '';
		}
		$dry = 'import' !== sanitize_key( wp_unslash( $_POST['twd_sk_reviews_go'] ) ); // phpcs:ignore
		return TWD_SK_Shop_Reviews_Import::run( (string) file_get_contents( $file['tmp_name'] ), $dry, $platform );
	}

	public static function import_page() {
		if ( ! current_user_can( 'edit_products' ) ) {
			return;
		}
		$result = self::handle_post();
		echo '<div class="wrap"><h1>Import reviews</h1>';
		echo '<p>Reviews from Etsy, eBay or elsewhere, from a spreadsheet saved as CSV. Columns: <code>' . esc_html( implode( ', ', TWD_SK_Shop_Reviews_Import::columns() ) ) . '</code>, in any order. Name, Date, Stars and Review are needed in every row; the words are kept exactly as written. A review already saved is never added twice, so the same file can be imported again safely.</p>';
		echo '<p><a class="button" href="' . esc_url( self::template_url() ) . '">Download the blank template</a></p>';
		echo '<p>Dates: 2025-03, 14/03/2025 (day first), March 2025 or Mar 14, 2025 all work; only the month and year are kept. Stars: a whole number from 1 to 5, or for eBay feedback the word Positive, Neutral or Negative. A year on its own (2026) is fine when that is all the platform shows, and Undated when it shows no date at all; a later file with the date fills it in.</p>';
		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( self::NONCE );
		echo '<p><label>CSV file <input type="file" name="twd_sk_reviews_csv" accept=".csv,text/csv"></label></p>';
		echo '<p><label>Rows with no platform are from <select name="twd_sk_reviews_platform"><option value="">Other</option>';
		foreach ( array( 'etsy' => 'Etsy', 'ebay' => 'eBay' ) as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><button class="button" name="twd_sk_reviews_go" value="check">Check (saves nothing)</button> <button class="button button-primary" name="twd_sk_reviews_go" value="import">Import</button></p></form>';
		if ( is_array( $result ) ) {
			echo self::report_html( $result ); // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped.
		}
		echo '</div>';
	}

	/** The report as a table, every value escaped. */
	public static function report_html( $result ) {
		if ( isset( $result['error'] ) ) {
			return '<div class="notice notice-error"><p>' . esc_html( $result['error'] ) . '</p></div>';
		}
		$c    = $result['counts'];
		$dry  = ! empty( $result['dry'] );
		$head = $dry
			? 'Checked, nothing saved: ' . $c['added'] . ' ready to add, ' . $c['updated'] . ' dates ready to fill in, ' . $c['there'] . ' already there, ' . $c['refused'] . ' refused.'
			: 'Imported: ' . $c['added'] . ' added, ' . $c['updated'] . ' dates filled in, ' . $c['there'] . ' already there, ' . $c['refused'] . ' refused.';
		$out  = '<div class="notice ' . ( $c['refused'] ? 'notice-warning' : 'notice-success' ) . '"><p>' . esc_html( $head ) . '</p></div>';
		$out .= '<table class="widefat striped"><thead><tr><th>Row</th><th>Name</th><th>Result</th></tr></thead><tbody>';
		foreach ( $result['rows'] as $r ) {
			$out .= '<tr><td>' . (int) $r[0] . '</td><td>' . esc_html( $r[2] ) . '</td><td>' . esc_html( $r[3] ) . '</td></tr>';
		}
		return $out . '</tbody></table>';
	}

	// -- The edit screen -----------------------------------------------------------------------

	public static function add_box() {
		add_meta_box( 'twd-sk-review', 'The review', array( __CLASS__, 'box' ), TWD_SK_Shop_Reviews::TYPE, 'normal', 'high' );
	}

	public static function box( $post ) {
		$r = TWD_SK_Shop_Reviews::read( $post->ID );
		$r = $r ? $r : array( 'platform' => 'etsy', 'name' => '', 'month' => '', 'stars' => 5, 'rating' => '', 'text' => '', 'item' => '', 'link' => '', 'fav' => false, 'hidden' => false );
		wp_nonce_field( self::NONCE, 'twd_sk_review_nonce' );
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="twd-sk-rv-platform">Platform</label></th><td><select id="twd-sk-rv-platform" name="twd_sk_rv[platform]">';
		foreach ( TWD_SK_Shop_Reviews::platforms() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['platform'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="twd-sk-rv-name">Name</label></th><td><input id="twd-sk-rv-name" class="regular-text" name="twd_sk_rv[name]" value="' . esc_attr( $r['name'] ) . '" maxlength="' . (int) TWD_SK_Shop_Reviews::MAX_NAME . '"></td></tr>';
		echo '<tr><th><label for="twd-sk-rv-date">Month and year</label></th><td><input id="twd-sk-rv-date" name="twd_sk_rv[date]" value="' . esc_attr( ! empty( $r['undated'] ) ? 'Undated' : TWD_SK_Shop_Reviews::month_label( $r['month'] ) ) . '" placeholder="March 2025"><p class="description">Or a year on its own (2026), or Undated when the platform gives no date and nobody can say.</p></td></tr>';
		echo '<tr><th><label for="twd-sk-rv-stars">Stars or rating</label></th><td><select id="twd-sk-rv-stars" name="twd_sk_rv[stars]">';
		for ( $i = 5; $i >= 1; $i-- ) {
			echo '<option value="' . (int) $i . '"' . selected( '' === $r['rating'] ? (int) $r['stars'] : 0, $i, false ) . '>' . (int) $i . ' stars</option>';
		}
		foreach ( TWD_SK_Shop_Reviews::ratings() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['rating'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">eBay feedback has no stars: choose Positive, Neutral or Negative feedback.</p></td></tr>';
		echo '<tr><th><label for="twd-sk-rv-text">Review</label></th><td><textarea id="twd-sk-rv-text" class="large-text" rows="7" name="twd_sk_rv[text]">' . esc_textarea( $r['text'] ) . '</textarea><p class="description">Exactly as the customer wrote it.</p></td></tr>';
		echo '<tr><th><label for="twd-sk-rv-item">Item (optional)</label></th><td><input id="twd-sk-rv-item" class="regular-text" name="twd_sk_rv[item]" value="' . esc_attr( $r['item'] ) . '"></td></tr>';
		echo '<tr><th><label for="twd-sk-rv-link">Link to the review (optional)</label></th><td><input id="twd-sk-rv-link" class="regular-text" type="url" name="twd_sk_rv[link]" value="' . esc_attr( $r['link'] ) . '"></td></tr>';
		echo '<tr><th>Show</th><td><label><input type="checkbox" name="twd_sk_rv[fav]" value="yes"' . checked( $r['fav'], true, false ) . '> Favourite (used for the strip and on product pages)</label><br>';
		echo '<label><input type="checkbox" name="twd_sk_rv[hidden]" value="yes"' . checked( $r['hidden'], true, false ) . '> Hidden (kept, never shown)</label></td></tr>';
		echo '</tbody></table>';
	}

	/** The posted fields, when our box was posted with a good nonce; else null. */
	private static function posted() {
		if ( ! isset( $_POST['twd_sk_review_nonce'], $_POST['twd_sk_rv'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['twd_sk_review_nonce'] ) ), self::NONCE ) ) { // phpcs:ignore
			return null;
		}
		$in = wp_unslash( $_POST['twd_sk_rv'] ); // phpcs:ignore -- each field is checked by TWD_SK_Shop_Reviews::check().
		return is_array( $in ) ? $in : null;
	}

	/** The list shows the reviewer's name as the title. */
	public static function title( $data, $postarr ) {
		if ( isset( $data['post_type'] ) && TWD_SK_Shop_Reviews::TYPE === $data['post_type'] ) {
			$in = self::posted();
			if ( $in && isset( $in['name'] ) ) {
				$data['post_title'] = sanitize_text_field( TWD_SK_Shop_Reviews::strip_html( $in['name'] ) );
			}
		}
		return $data;
	}

	public static function save_box( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) ) {
			return;
		}
		$in = self::posted();
		if ( null === $in || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$checked = TWD_SK_Shop_Reviews::check( $in );
		if ( isset( $checked['error'] ) ) {
			self::remember( 'Not saved. ' . $checked['error'] );
			return;
		}
		$dup = TWD_SK_Shop_Reviews::keys();
		$key = $checked['review']['key'];
		if ( isset( $dup[ $key ] ) && (int) $dup[ $key ] !== (int) $post_id ) {
			self::remember( 'Not saved: the same review is already kept (review ' . (int) $dup[ $key ] . ').' );
			return;
		}
		TWD_SK_Shop_Reviews::save( $checked['review'], $post_id );
	}

	private static function remember( $words ) {
		set_transient( 'twd_sk_rv_notice_' . get_current_user_id(), $words, 120 );
	}

	public static function notice() {
		$key   = 'twd_sk_rv_notice_' . get_current_user_id();
		$words = get_transient( $key );
		if ( is_string( $words ) && '' !== $words ) {
			delete_transient( $key );
			echo '<div class="notice notice-error"><p>' . esc_html( $words ) . '</p></div>';
		}
	}

	// -- The list --------------------------------------------------------------------------------

	public static function columns( $cols ) {
		$out = array();
		foreach ( (array) $cols as $k => $v ) {
			if ( 'date' === $k ) {
				continue;
			}
			$out[ $k ] = 'title' === $k ? 'Name' : $v;
			if ( 'title' === $k ) {
				$out['twd_sk_rv_platform'] = 'Platform';
				$out['twd_sk_rv_stars']    = 'Stars or rating';
				$out['twd_sk_rv_month']    = 'Month';
				$out['twd_sk_rv_text']     = 'Review';
				$out['twd_sk_rv_show']     = 'Shown';
			}
		}
		return $out;
	}

	public static function column( $col, $post_id ) {
		$r = TWD_SK_Shop_Reviews::read( $post_id );
		if ( ! $r ) {
			return;
		}
		switch ( $col ) {
			case 'twd_sk_rv_platform':
				echo esc_html( TWD_SK_Shop_Reviews::platforms()[ $r['platform'] ] ?? 'Other' );
				break;
			case 'twd_sk_rv_stars':
				echo '' !== $r['rating'] ? esc_html( TWD_SK_Shop_Reviews::ratings()[ $r['rating'] ] ) : (int) $r['stars'];
				break;
			case 'twd_sk_rv_month':
				echo esc_html( $r['undated'] ? 'Undated' : TWD_SK_Shop_Reviews::month_label( $r['month'] ) );
				break;
			case 'twd_sk_rv_text':
				$t = preg_replace( '/\s+/u', ' ', $r['text'] );
				echo esc_html( function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $t, 0, 120, '...', 'UTF-8' ) : substr( $t, 0, 120 ) );
				break;
			case 'twd_sk_rv_show':
				echo esc_html( $r['hidden'] ? 'Hidden' : ( $r['fav'] ? 'Favourite' : 'Yes' ) );
				break;
		}
	}
}
