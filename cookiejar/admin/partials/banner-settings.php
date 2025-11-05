<?php
if (!defined('ABSPATH')) exit;

use DWIC\Config;

$theme = Config::get('banner_theme', []);
// Ensure $theme is always an array
if (!is_array($theme)) {
    $theme = [];
}

// Set default values for missing keys
$defaults = [
    'color' => '#008ed6',
    'bg' => '#ffffff',
    'font' => 'inherit',
    'prompt' => ''
];

$theme = array_merge($defaults, $theme);
?>
<div class="cookiejar-banner-settings">
    <h2><?php esc_html_e('Banner Appearance & Prompt', 'cookiejar'); ?></h2>
    <table class="form-table">
        <tr>
            <th><label for="cookiejar-banner-color"><?php esc_html_e('Banner Color', 'cookiejar'); ?></label></th>
            <td>
                <input type="text" id="cookiejar-banner-color" name="color" value="<?php echo esc_attr($theme['color']); ?>" class="color-picker" />
            </td>
        </tr>
        <tr>
            <th><label for="cookiejar-banner-bg"><?php esc_html_e('Background Color', 'cookiejar'); ?></label></th>
            <td>
                <input type="text" id="cookiejar-banner-bg" name="bg" value="<?php echo esc_attr($theme['bg']); ?>" class="color-picker" />
            </td>
        </tr>
        <tr>
            <th><label for="cookiejar-banner-font"><?php esc_html_e('Font Family', 'cookiejar'); ?></label></th>
            <td>
                <input type="text" id="cookiejar-banner-font" name="font" value="<?php echo esc_attr($theme['font']); ?>" />
                <span class="description"><?php esc_html_e('Use CSS font family or "inherit".', 'cookiejar'); ?></span>
            </td>
        </tr>
        <tr>
            <th><label for="cookiejar-banner-prompt"><?php esc_html_e('Banner Prompt Message', 'cookiejar'); ?></label></th>
            <td>
                <input type="text" id="cookiejar-banner-prompt" name="prompt" value="<?php echo esc_attr($theme['prompt']); ?>" style="width:400px;" />
                <span class="description"><?php esc_html_e('Displayed on the cookie banner.', 'cookiejar'); ?></span>
            </td>
        </tr>
    </table>
</div>
