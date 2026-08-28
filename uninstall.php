<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package WCRAC
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wcrac_settings' );
