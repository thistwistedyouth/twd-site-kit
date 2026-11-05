<?php
/**
 * TWD_SK_ExportNotes: the notes kept on the site for the export for an AI (what the owner wants checked or changed).
 *
 * One plain-text option, not autoloaded, read only by administrators' exports and written only through the Site tab's
 * Export for an AI section (the tick "Keep these notes on the site", or Clear the saved notes). The text goes through
 * TWD_SK_SiteExport::clean_notes() (tags and control characters out, line breaks kept, 20,000 characters at most).
 * An empty text clears the option. They are never printed on a page, never sent anywhere but into an export an
 * administrator asks for, and are separate from the facts.
 *
 * @package TWD_Site_Kit
 */

defined( 'ABSPATH' ) || exit;

class TWD_SK_ExportNotes {

	const OPTION = 'twd_sk_export_notes';

	/** The notes kept on the site, or ''. */
	public static function get() {
		$n = get_option( self::OPTION, '' );
		return is_string( $n ) ? $n : '';
	}

	/** Keep these notes (an empty text clears them). Returns the notes as kept. */
	public static function save( $text ) {
		$clean = TWD_SK_SiteExport::clean_notes( $text );
		if ( '' === $clean ) {
			delete_option( self::OPTION );
		} else {
			update_option( self::OPTION, $clean, false );
		}
		return $clean;
	}
}
