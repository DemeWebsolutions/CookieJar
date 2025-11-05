<?php
if (!defined('ABSPATH')) exit;

use DWIC\Config;

$categories = Config::get('categories', []);
// Ensure $categories is always an array
if (!is_array($categories)) {
    $categories = [];
}

// Set default values if empty
if (empty($categories)) {
    $categories = ['necessary', 'functional', 'analytics', 'advertising'];
}
?>
<div class="cookiejar-category-manager">
    <h2><?php esc_html_e('Cookie Categories', 'cookiejar'); ?></h2>
    <div class="form-group">
        <?php
        $all_cats = [
            'necessary'   => __('Required for site operation', 'cookiejar'),
            'functional'  => __('Enhance site functionality', 'cookiejar'),
            'analytics'   => __('Help us understand usage', 'cookiejar'),
            'advertising' => __('Personalize ads and marketing', 'cookiejar'),
            'chatbot'     => __('Enable chat features', 'cookiejar'),
            'donotsell'   => __('Opt out of sale of personal data', 'cookiejar')
        ];
        foreach ($all_cats as $slug => $desc): ?>
            <label style="display:block;">
                <input type="checkbox" name="categories[]" value="<?php echo esc_attr($slug); ?>"
                    <?php checked(in_array($slug, (array)$categories)); ?>
                    <?php if ($slug === 'necessary') echo ' checked disabled'; ?>>
                <b><?php echo esc_html(ucfirst($slug)); ?></b>
                <small><?php echo esc_html($desc); ?></small>
                <?php if ($slug === 'necessary'): ?>
                    <span style="color: #999; font-size:smaller;"><?php esc_html_e('Always required', 'cookiejar'); ?></span>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
    </div>
</div>
