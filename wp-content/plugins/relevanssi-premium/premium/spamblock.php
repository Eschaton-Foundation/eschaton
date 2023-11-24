<?php
/**
 * /premium/spamblock.php
 *
 * @package Relevanssi_Premium
 * @author  Mikko Saari
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 * @see     https://www.relevanssi.com/
 */

/**
 * Runs on plugins_loaded and stops spam search requests based on keywords.
 */
function relevanssi_spamblock() {
	if ( ! isset( $_REQUEST['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$query    = $_REQUEST['s']; // phpcs:ignore WordPress.Security.NonceVerification
	$settings = get_option( 'relevanssi_spamblock', array() );
	$keywords = $settings['keywords'] ?? '';
	$regex    = $settings['regex'] ?? '';
	$chinese  = $settings['chinese'] ?? 'off';
	$cyrillic = $settings['cyrillic'] ?? 'off';
	$emoji    = $settings['emoji'] ?? 'off';

	if ( 'on' === $chinese && relevanssi_string_contains_chinese( $query ) ) {
		exit();
	}

	if ( 'on' === $cyrillic && relevanssi_string_contains_cyrillic( $query ) ) {
		exit();
	}

	if ( 'on' === $emoji && relevanssi_string_contains_emoji( $query ) ) {
		exit();
	}

	foreach ( explode( "\n", $keywords ) as $keyword ) {
		$keyword = trim( $keyword );
		if ( empty( $keyword ) ) {
			continue;
		}
		if ( false !== relevanssi_stripos( $query, $keyword ) ) {
			exit();
		}
	}
	foreach ( explode( "\n", $regex ) as $pattern ) {
		$pattern = trim( $pattern );
		if ( empty( $pattern ) ) {
			continue;
		}
		if ( 1 === preg_match( '/' . $pattern . '/ui', $query ) ) {
			exit();
		}
	}
}

/**
 * Checks if a string contains Chinese characters.
 *
 * @param string $text The text to check.
 *
 * @return boolean
 */
function relevanssi_string_contains_chinese( string $text ) : bool {
	return (bool) preg_match( '/\p{Han}/u', $text );
}

/**
 * Checks if a string contains Cyrillic characters. Uses {Cyr} to check.
 *
 * @param string $text The text to check.
 *
 * @return boolean
 */
function relevanssi_string_contains_cyrillic( string $text ) : bool {
	return (bool) preg_match( '/\p{Cyrillic}/u', $text );
}

/**
 * Checks if a string contains emoji characters.
 *
 * @param string $text The text to check.
 *
 * @return boolean
 */
function relevanssi_string_contains_emoji( string $text ) : bool {
	$emoji = array(
		'/[\x{1F1E6}-\x{1F1FF}]/u', // Flags.
		'/[\x{1F300}-\x{1F5FF}]/u', // Misc and pictographs.
		'/[\x{1F600}-\x{1F64F}]/u', // Emoticons.
		'/[\x{1F680}-\x{1F6FF}]/u', // Transport and maps.
		'/[\x{1F700}-\x{1F9FF}]/u', // Hotel and misc.
		'/[\x{2300}-\x{23FF}]/u', // Time.
		'/[\x{2600}-\x{26FF}]/u', // Miscellaneous.
		'/[\x{2700}-\x{27BF}]/u', // Dingbats.
	);

	foreach ( $emoji as $pattern ) {
		if ( preg_match( $pattern, $text ) ) {
			return true;
		}
	}

	return false;
}
