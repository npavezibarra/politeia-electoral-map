<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin deactivation handler.
 *
 * @package Politeia
 */
namespace Politeia\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Deactivator {
	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}
}
