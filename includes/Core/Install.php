<?php

namespace UtilitySign\Core;

use UtilitySign\Database\Migrations\SecurityLog;
use UtilitySign\Database\Migrations\AuthLog;
use UtilitySign\Database\Migrations\Cache;
use UtilitySign\Database\Migrations\ErrorLog;
use UtilitySign\Traits\Base;

/**
 * This class is responsible for the functionality
 * which is required to set up after activating the plugin
 */
class Install {


	use Base;

	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public function init() {

		$this->install_pages();
		$this->install_tables();
		$this->insert_data();
	}

	/**
	 * Install the pages
	 *
	 * @return void
	 */
	private function install_pages() {
		wpb_install_page(
			Template::FRONTEND_TEMPLATE_NAME,
			Template::FRONTEND_TEMPLATE_SLUG,
			Template::FRONTEND_TEMPLATE
		);
	}

	/**
	 * Install the tables
	 *
	 * @return void
	 */
	private function install_tables() {
		SecurityLog::up();
		AuthLog::up();
		Cache::up();
		ErrorLog::up();
	}

	/**
	 * Insert data to the tables
	 *
	 * @return void
	 */
	private function insert_data() {
		// No seeders needed for UtilitySign plugin
		// All data is created dynamically through admin interface
	}
}
