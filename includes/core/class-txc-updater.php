<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Updater {

    /**
     * GitHub repository URL.
     */
    const GITHUB_REPO = 'https://github.com/alphastrem/rafflexl/';

    /**
     * Initialize the GitHub-based update checker.
     *
     * Uses YahnisElsts/plugin-update-checker v5.x to compare the installed
     * version against tagged GitHub Releases. When a newer release exists,
     * WordPress shows an update notification and can install the attached ZIP.
     */
    public static function init() {
        $puc_file = TXC_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
        if ( ! file_exists( $puc_file ) ) {
            return;
        }

        require_once $puc_file;

        $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            self::GITHUB_REPO,
            TXC_PLUGIN_FILE,
            'telxl-competitions'
        );

        // Check the main branch for stable releases.
        $update_checker->setBranch( 'main' );

        // Download the ZIP attached to the release, not the source archive.
        $update_checker->getVcsApi()->enableReleaseAssets();

        // Authenticate for private repo access.
        // Uses TXC_GITHUB_TOKEN from txc-config.php if available.
        $token = defined( 'TXC_GITHUB_TOKEN' ) ? TXC_GITHUB_TOKEN : '';
        if ( ! empty( $token ) ) {
            $update_checker->setAuthentication( $token );
        }
    }
}
