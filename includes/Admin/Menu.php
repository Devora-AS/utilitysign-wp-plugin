<?php

namespace UtilitySign\Admin;

use UtilitySign\Traits\Base;

/**
 * Class Menu
 *
 * Represents the admin menu management for the UtilitySign plugin.
 *
 * @package UtilitySign\Admin
 */
class Menu {

	use Base;

	/**
	 * Parent slug for the menu.
	 *
	 * @var string
	 */
	private $parent_slug = 'utilitysign';

	/**
	 * Initializes the admin menu.
	 *
	 * @return void
	 */
	public function init() {
		// Hook the function to the admin menu.
		add_action( 'admin_menu', array( $this, 'menu' ) );
		
		// Add custom CSS for the menu logo
		add_action( 'admin_head', array( $this, 'add_menu_logo_styles' ) );
	}
	
	/**
	 * Add custom CSS styles for the UtilitySign menu logo.
	 * 
	 * Note: When using base64 data URI SVG, WordPress's svg-painter.js automatically
	 * handles color changes. This CSS is minimal and only for sizing/alignment.
	 *
	 * @return void
	 */
	public function add_menu_logo_styles() {
		?>
		<style type="text/css">
			/* UtilitySign menu logo - let WordPress handle default sizing */
			/* WordPress's default .wp-menu-image styling handles sizing and alignment correctly.
			   The SVG data URI with fill="#000000" enables WordPress's svg-painter.js for colors. */
			
			/* Remove any img elements that might interfere */
			#adminmenu #toplevel_page_utilitysign .wp-menu-image img {
				display: none;
			}
			
			/* Override background-size to match other menu icons */
			/* WordPress may add background-size: 20px auto; which makes the icon smaller */
			#adminmenu #toplevel_page_utilitysign .wp-menu-image.svg {
				background-size: contain !important;
			}
		</style>
		<?php
	}

	/**
	 * Adds a menu to the WordPress admin dashboard.
	 *
	 * @return void
	 */
	public function menu() {
		// Use custom UtilitySign logo as base64 data URI for proper WordPress admin menu icon behavior
		// WordPress's svg-painter.js requires SVG to have fill="#000000" (black) to work correctly
		$icon_path = UTILITYSIGN_DIR . 'assets/images/UtilitySign_Logo.svg';
		
		$icon_data = '';
		if (file_exists($icon_path)) {
			$icon_svg = file_get_contents($icon_path);
			if ($icon_svg !== false) {
				// Ensure SVG has black fill for svg-painter.js compatibility
				// Replace any existing fill colors with black
				$icon_svg = preg_replace('/fill="[^"]*"/i', 'fill="#000000"', $icon_svg);
				// If no fill attribute exists, add it to the root <g> element
				if (strpos($icon_svg, 'fill=') === false) {
					$icon_svg = preg_replace('/(<g[^>]*>)/i', '$1 fill="#000000"', $icon_svg, 1);
				}
				
				// Encode SVG as base64 data URI
				$icon_data = 'data:image/svg+xml;base64,' . base64_encode($icon_svg);
			}
		}
		
		// Fallback to dashicon if SVG file not found
		if (empty($icon_data)) {
			$icon_data = 'dashicons-admin-generic';
		}

		add_menu_page(
			__( 'Dashboard', 'utilitysign' ),
			__( 'UtilitySign', 'utilitysign' ),
			'manage_utilitysign',
			$this->parent_slug,
			array( $this, 'admin_page' ),
			$icon_data,
			2
		);

		$submenu_pages = array(
			array(
				'parent_slug' => $this->parent_slug,
				'page_title'  => __( 'Dashboard', 'utilitysign' ),
				'menu_title'  => __( 'Dashboard', 'utilitysign' ),
				'capability'  => 'manage_utilitysign',
				'menu_slug'   => $this->parent_slug,
				'function'    => array( $this, 'admin_page' ),
			),
			array(
				'parent_slug' => $this->parent_slug,
				'page_title'  => __( 'Settings', 'utilitysign' ),
				'menu_title'  => __( 'Settings', 'utilitysign' ),
				'capability'  => 'manage_utilitysign',
				'menu_slug'   => $this->parent_slug . '-settings',
				'function'    => array( $this, 'admin_page' ),
			),
		);

		$plugin_submenu_pages = apply_filters( 'utilitysign_submenu_pages', $submenu_pages );

		foreach ( $plugin_submenu_pages as $submenu ) {

			add_submenu_page(
				$submenu['parent_slug'],
				$submenu['page_title'],
				$submenu['menu_title'],
				$submenu['capability'],
				$submenu['menu_slug'],
				$submenu['function']
			);
		}

        if ( current_user_can( 'manage_utilitysign_internal' ) ) {
            add_submenu_page(
                $this->parent_slug,
                __( 'Security Settings', 'utilitysign' ),
                __( 'Security', 'utilitysign' ),
                'manage_utilitysign_internal',
                'utilitysign-security',
                array( $this, 'security_page' )
            );
        }
	}

	/**
	 * Callback function for the main "UtilitySign" menu page.
	 *
	 * @return void
	 */
	public function admin_page() {
		?>
		<div id="utilitysign" class="utilitysign-app"></div>
		<?php
	}

	/**
	 * Callback function for the security settings page.
	 *
	 * @return void
	 */
	public function security_page() {
		$security_settings = new SecuritySettings();
		$security_settings->render_security_page();
	}
}
