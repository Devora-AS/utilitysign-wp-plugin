<?php
/**
 * UtilitySign Routes
 *
 * Defines and registers custom API routes for the UtilitySign using the Haruncpi\WpApi library.
 *
 * @package UtilitySign\Routes
 */

namespace UtilitySign\Routes;

use UtilitySign\Libs\API\Route;

Route::prefix(
	WPB_ROUTE_PREFIX,
	function ( Route $route ) {
		// UtilitySign-specific API routes
		// Note: Product endpoints are now handled by REST/ProductsController.php
		// Note: Accounts and Posts controllers removed (unused boilerplate code)

		// Allow hooks to add custom API routes
		do_action( 'utilitysign_api_routes', $route );
		
		// Backward compatibility hook
		do_action( 'wpb_api', $route );
	}
);
