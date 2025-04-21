<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$cookies = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cookie_scanner_cookies ORDER BY category_id ASC, name ASC");
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="cookie-scanner-admin-header">
        <div class="cookie-scanner-admin-header-content">
            <h2><?php esc_html_e('Gefundene Cookies', 'cookie-scanner'); ?></h2>
            <p><?php esc_html_e('Übersicht aller auf Ihrer Website gefundenen Cookies.', 'cookie-scanner'); ?></p>
        </div>
        <div class="cookie-scanner-admin-header-actions">
            <button type="button" class="button button-primary" id="cookie-scanner-export">
                <?php esc_html_e('Exportieren', 'cookie-scanner'); ?>
            </button>
        </div>
    </div>

    <div class="cookie-scanner-admin-content">
        <div class="cookie-scanner-filters">
            <select id="cookie-category-filter">
                <option value=""><?php esc_html_e('Alle Kategorien', 'cookie-scanner'); ?></option>
                <?php
                $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cookie_scanner_categories ORDER BY name ASC");
                foreach ($categories as $category) {
                    printf(
                        '<option value="%s">%s</option>',
                        esc_attr($category->id),
                        esc_html($category->name)
                    );
                }
                ?>
            </select>

            <input type="text" id="cookie-search" placeholder="<?php esc_attr_e('Cookie suchen...', 'cookie-scanner'); ?>">
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Name', 'cookie-scanner'); ?></th>
                    <th scope="col"><?php esc_html_e('Kategorie', 'cookie-scanner'); ?></th>
                    <th scope="col"><?php esc_html_e('Dauer', 'cookie-scanner'); ?></th>
                    <th scope="col"><?php esc_html_e('Anbieter', 'cookie-scanner'); ?></th>
                    <th scope="col"><?php esc_html_e('Zweck', 'cookie-scanner'); ?></th>
                    <th scope="col"><?php esc_html_e('Aktionen', 'cookie-scanner'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($cookies) {
                    foreach ($cookies as $cookie) {
                        $category = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}cookie_scanner_categories WHERE id = %d",
                            $cookie->category_id
                        ));
                        ?>
                        <tr>
                            <td><?php echo esc_html($cookie->name); ?></td>
                            <td><?php echo esc_html($category ? $category->name : ''); ?></td>
                            <td><?php echo esc_html($cookie->duration); ?></td>
                            <td><?php echo esc_html($cookie->provider); ?></td>
                            <td><?php echo esc_html($cookie->purpose); ?></td>
                            <td>
                                <button type="button" class="button button-small edit-cookie" data-id="<?php echo esc_attr($cookie->id); ?>">
                                    <?php esc_html_e('Bearbeiten', 'cookie-scanner'); ?>
                                </button>
                                <button type="button" class="button button-small button-link-delete delete-cookie" data-id="<?php echo esc_attr($cookie->id); ?>">
                                    <?php esc_html_e('Löschen', 'cookie-scanner'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('Keine Cookies gefunden.', 'cookie-scanner'); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Cookie-Bearbeiten-Modal -->
    <div id="cookie-edit-modal" class="cookie-scanner-modal" style="display: none;">
        <div class="cookie-scanner-modal-content">
            <h3><?php esc_html_e('Cookie bearbeiten', 'cookie-scanner'); ?></h3>
            <form id="cookie-edit-form">
                <input type="hidden" id="cookie-id" name="cookie_id">
                
                <div class="form-field">
                    <label for="cookie-name"><?php esc_html_e('Name', 'cookie-scanner'); ?></label>
                    <input type="text" id="cookie-name" name="name" required>
                </div>

                <div class="form-field">
                    <label for="cookie-category"><?php esc_html_e('Kategorie', 'cookie-scanner'); ?></label>
                    <select id="cookie-category" name="category_id" required>
                        <?php
                        foreach ($categories as $category) {
                            printf(
                                '<option value="%s">%s</option>',
                                esc_attr($category->id),
                                esc_html($category->name)
                            );
                        }
                        ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="cookie-duration"><?php esc_html_e('Dauer', 'cookie-scanner'); ?></label>
                    <input type="text" id="cookie-duration" name="duration">
                </div>

                <div class="form-field">
                    <label for="cookie-provider"><?php esc_html_e('Anbieter', 'cookie-scanner'); ?></label>
                    <input type="text" id="cookie-provider" name="provider">
                </div>

                <div class="form-field">
                    <label for="cookie-purpose"><?php esc_html_e('Zweck', 'cookie-scanner'); ?></label>
                    <textarea id="cookie-purpose" name="purpose" rows="3"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Speichern', 'cookie-scanner'); ?></button>
                    <button type="button" class="button cancel-edit"><?php esc_html_e('Abbrechen', 'cookie-scanner'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div> 