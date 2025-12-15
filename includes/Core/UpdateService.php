<?php

namespace UtilitySign\Core;

use UtilitySign\Traits\Base;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class UpdateService {
    use Base;

    /** @var \YahnisElsts\PluginUpdateChecker\v5\UpdateChecker */
    private $update_checker;

    public function init() {
        add_action( 'plugins_loaded', [ $this, 'bootstrap' ] );
    }

    public function bootstrap() {
        if ( $this->update_checker ) {
            return;
        }

        if ( ! defined( 'UTILITYSIGN_PLUGIN_FILE' ) ) {
            return;
        }

        $this->update_checker = PucFactory::buildUpdateChecker(
            'https://github.com/Devora-AS/utilitysign-wp-plugin/',
            UTILITYSIGN_PLUGIN_FILE,
            'utilitysign'
        );

        $this->update_checker->getVcsApi()->enableReleaseAssets();

        // Cache busting: allow slowing down update checks if needed later via filter.
        add_filter( 'puc_request_info_query_args-utilitysign', function ( $query_args ) {
            $query_args['timestamp'] = time();
            return $query_args;
        } );
    }
}
