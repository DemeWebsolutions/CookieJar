<?php
// CookieJar Settings Form Partial
if (!defined('ABSPATH')) exit;

use DWIC\Config;

$banner_enabled = Config::get('banner_enabled', 'yes');
$policy_url     = Config::get('policy_url', '');
$gdpr_mode      = Config::get('gdpr_mode', 'yes');
$ccpa_mode      = Config::get('ccpa_mode', 'yes');
$languages      = Config::get('languages', ['en']);

?>
<div class="cookiejar-settings-form">
    <div class="cookiejar-card">
        <h3><?php esc_html_e('General Settings', 'cookiejar'); ?></h3>
        <p><?php esc_html_e('Configure your basic CookieJar settings and compliance options.', 'cookiejar'); ?></p>
        
        <div class="cookiejar-settings-section">
            <h4><?php esc_html_e('Banner Configuration', 'cookiejar'); ?></h4>
            <div class="cookiejar-form-grid">
                <div class="cookiejar-form-group">
                    <label for="cookiejar-banner-enabled"><?php esc_html_e('Enable Banner', 'cookiejar'); ?></label>
                    <select id="cookiejar-banner-enabled" name="banner_enabled">
                        <option value="yes" <?php selected($banner_enabled, 'yes'); ?>><?php esc_html_e('Yes', 'cookiejar'); ?></option>
                        <option value="no" <?php selected($banner_enabled, 'no'); ?>><?php esc_html_e('No', 'cookiejar'); ?></option>
                    </select>
                    <div class="description"><?php esc_html_e('Show cookie consent banner to visitors.', 'cookiejar'); ?></div>
                </div>

                <div class="cookiejar-form-group">
                    <label for="cookiejar-policy-url"><?php esc_html_e('Policy URL', 'cookiejar'); ?></label>
                    <input type="url" id="cookiejar-policy-url" name="policy_url" value="<?php echo esc_attr($policy_url); ?>" placeholder="https://yoursite.com/privacy-policy" />
                    <div class="description"><?php esc_html_e('Link to your privacy/cookie policy page.', 'cookiejar'); ?></div>
                </div>
            </div>
        </div>

        <div class="cookiejar-settings-section">
            <h4><?php esc_html_e('Compliance Settings', 'cookiejar'); ?></h4>
            <div class="cookiejar-form-grid">
                <div class="cookiejar-form-group">
                    <label for="cookiejar-gdpr-mode"><?php esc_html_e('Enable GDPR Mode', 'cookiejar'); ?></label>
                    <select id="cookiejar-gdpr-mode" name="gdpr_mode">
                        <option value="yes" <?php selected($gdpr_mode, 'yes'); ?>><?php esc_html_e('Yes', 'cookiejar'); ?></option>
                        <option value="no" <?php selected($gdpr_mode, 'no'); ?>><?php esc_html_e('No', 'cookiejar'); ?></option>
                    </select>
                    <div class="description"><?php esc_html_e('Enable GDPR compliance features.', 'cookiejar'); ?></div>
                </div>

                <div class="cookiejar-form-group">
                    <label for="cookiejar-ccpa-mode"><?php esc_html_e('Enable CCPA Mode', 'cookiejar'); ?></label>
                    <select id="cookiejar-ccpa-mode" name="ccpa_mode">
                        <option value="yes" <?php selected($ccpa_mode, 'yes'); ?>><?php esc_html_e('Yes', 'cookiejar'); ?></option>
                        <option value="no" <?php selected($ccpa_mode, 'no'); ?>><?php esc_html_e('No', 'cookiejar'); ?></option>
                    </select>
                    <div class="description"><?php esc_html_e('Enable CCPA compliance features.', 'cookiejar'); ?></div>
                </div>
            </div>
        </div>

        <div class="cookiejar-settings-section">
            <h4><?php esc_html_e('Language Settings', 'cookiejar'); ?></h4>
            <div class="cookiejar-form-group">
                <label for="cookiejar-languages"><?php esc_html_e('Languages', 'cookiejar'); ?></label>
                <input type="text" id="cookiejar-languages" name="languages" value="<?php echo esc_attr(implode(',', (array) $languages)); ?>" placeholder="en,fr,de" />
                <div class="description"><?php esc_html_e('Comma-separated language codes, e.g. en,fr,de', 'cookiejar'); ?></div>
            </div>
        </div>

        <div class="cookiejar-form-actions">
            <button type="button" class="button button-primary" id="save-settings">
                <?php esc_html_e('Save Settings', 'cookiejar'); ?>
            </button>
            <button type="button" class="button button-secondary" id="reset-settings">
                <?php esc_html_e('Reset to Defaults', 'cookiejar'); ?>
            </button>
        </div>
    </div>

    <div class="cookiejar-card">
        <h3><?php esc_html_e('Import / Export', 'cookiejar'); ?></h3>
        <p><?php esc_html_e('Manage your CookieJar settings with import, export, and reset functionality.', 'cookiejar'); ?></p>
        
        <div class="cookiejar-settings-section">
            <h4><?php esc_html_e('Export Settings', 'cookiejar'); ?></h4>
            <p><?php esc_html_e('Download your current CookieJar configuration as a JSON file.', 'cookiejar'); ?></p>
            <div class="cookiejar-form-actions">
                <button type="button" class="button button-primary" id="export-settings">
                    <?php esc_html_e('Export Settings', 'cookiejar'); ?>
                </button>
            </div>
        </div>
        
        <div class="cookiejar-settings-section">
            <h4><?php esc_html_e('Import Settings', 'cookiejar'); ?></h4>
            <p><?php esc_html_e('Upload a JSON file to restore your CookieJar configuration.', 'cookiejar'); ?></p>
            <div class="cookiejar-form-group">
                <textarea id="import-textarea" rows="8" placeholder="<?php esc_attr_e('Paste JSON settings here or upload a file...', 'cookiejar'); ?>"></textarea>
            </div>
            <div class="cookiejar-form-actions">
                <input type="file" id="import-file" accept=".json" style="display:none;">
                <button type="button" class="button" id="select-file">
                    <?php esc_html_e('Select File', 'cookiejar'); ?>
                </button>
                <button type="button" class="button button-primary" id="import-settings">
                    <?php esc_html_e('Import Settings', 'cookiejar'); ?>
                </button>
            </div>
        </div>
        
        <div id="settings-status" class="cookiejar-status" style="display:none;"></div>
    </div>
</div>
