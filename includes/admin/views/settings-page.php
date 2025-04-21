<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="cookie-scanner-admin-header">
        <div class="cookie-scanner-admin-header-content">
            <h2><?php esc_html_e('Cookie Scanner Einstellungen', 'cookie-scanner'); ?></h2>
            <p><?php esc_html_e('Konfigurieren Sie hier die Einstellungen für den Cookie Scanner.', 'cookie-scanner'); ?></p>
        </div>
        <div class="cookie-scanner-admin-header-actions">
            <button type="button" class="button button-primary" id="cookie-scanner-scan">
                <?php esc_html_e('Jetzt scannen', 'cookie-scanner'); ?>
            </button>
        </div>
    </div>

    <div class="cookie-scanner-admin-content">
        <form method="post" action="options.php">
            <?php
            settings_fields('cookie_scanner_settings');
            do_settings_sections('cookie_scanner_settings');
            ?>

            <div class="cookie-scanner-admin-section">
                <h3><?php esc_html_e('Cookie-Kategorien', 'cookie-scanner'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Notwendige Cookies', 'cookie-scanner'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="cookie_scanner_options[necessary_cookies]" value="1" <?php checked(1, get_option('cookie_scanner_options')['necessary_cookies'] ?? 1); ?>>
                                    <?php esc_html_e('Aktivieren', 'cookie-scanner'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Diese Cookies sind für die Grundfunktionen der Website erforderlich.', 'cookie-scanner'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Funktionale Cookies', 'cookie-scanner'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="cookie_scanner_options[functional_cookies]" value="1" <?php checked(1, get_option('cookie_scanner_options')['functional_cookies'] ?? 1); ?>>
                                    <?php esc_html_e('Aktivieren', 'cookie-scanner'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Diese Cookies ermöglichen erweiterte Funktionen und Personalisierung.', 'cookie-scanner'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Analyse-Cookies', 'cookie-scanner'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="cookie_scanner_options[analytics_cookies]" value="1" <?php checked(1, get_option('cookie_scanner_options')['analytics_cookies'] ?? 0); ?>>
                                    <?php esc_html_e('Aktivieren', 'cookie-scanner'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Diese Cookies helfen uns, die Nutzung der Website zu verstehen.', 'cookie-scanner'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Marketing-Cookies', 'cookie-scanner'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="cookie_scanner_options[marketing_cookies]" value="1" <?php checked(1, get_option('cookie_scanner_options')['marketing_cookies'] ?? 0); ?>>
                                    <?php esc_html_e('Aktivieren', 'cookie-scanner'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Diese Cookies werden verwendet, um Werbung relevanter für Sie zu machen.', 'cookie-scanner'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="cookie-scanner-admin-section">
                <h3><?php esc_html_e('Erscheinungsbild', 'cookie-scanner'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Design', 'cookie-scanner'); ?></th>
                        <td>
                            <select name="cookie_scanner_options[design]">
                                <option value="modern" <?php selected(get_option('cookie_scanner_options')['design'] ?? 'modern', 'modern'); ?>><?php esc_html_e('Modern', 'cookie-scanner'); ?></option>
                                <option value="classic" <?php selected(get_option('cookie_scanner_options')['design'] ?? 'modern', 'classic'); ?>><?php esc_html_e('Klassisch', 'cookie-scanner'); ?></option>
                                <option value="minimal" <?php selected(get_option('cookie_scanner_options')['design'] ?? 'modern', 'minimal'); ?>><?php esc_html_e('Minimal', 'cookie-scanner'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(); ?>
        </form>
    </div>

    <div class="cookie-scanner-admin-footer">
        <p>
            <?php
            printf(
                esc_html__('Cookie Scanner Version %s', 'cookie-scanner'),
                COOKIE_SCANNER_VERSION
            );
            ?>
        </p>
    </div>
</div> 