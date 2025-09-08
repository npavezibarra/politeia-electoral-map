<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin activation handler.
 *
 * @package Politeia
 */

namespace Politeia\Core;

use Politeia\Modules\Database\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation.
 */
class Activator {
	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		Installer::install();
		flush_rewrite_rules( false );
	}
}
