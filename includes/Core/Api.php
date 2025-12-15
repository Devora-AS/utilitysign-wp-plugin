<?php

namespace UtilitySign\Core;

use UtilitySign\Traits\Base;
use UtilitySign\Libs\API\Config;

/**
 * Class API
 *
 * Initializes and configures the API for the UtilitySign.
 *
 * @package UtilitySign\Core
 */
class API {

	use Base;

	/**
	 * Initializes the API for the UtilitySign.
	 *
	 * @return void
	 */
	public function init() {
		Config::set_route_file( WPB_DIR . '/includes/Routes/Api.php' )
			->set_namespace( 'UtilitySign\Api' )
			->init();
	}
}
