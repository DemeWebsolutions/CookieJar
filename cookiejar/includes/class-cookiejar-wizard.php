<?php
namespace DWIC;

if (!defined('ABSPATH')) exit;

/**
 * CookieJar Setup Wizard
 *
 * Provides a step-by-step interface for configuring cookie consent settings.
 *
 * @package CookieJar
 * @since 1.0.0
 */
class Wizard {
    private $pageOptions = [];
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_wizard_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_init', [$this, 'maybe_redirect_to_wizard']);

        // AJAX actions
        add_action('wp_ajax_cookiejar_complete_wizard', [$this, 'ajax_complete_wizard']);
        add_action('wp_ajax_cookiejar_skip_wizard', [$this, 'ajax_skip_wizard']);
        add_action('wp_ajax_cookiejar_save_wizard', [$this, 'ajax_save_wizard']);
        add_action('wp_ajax_cookiejar_reset_wizard', [$this, 'ajax_reset_wizard']);
        add_action('wp_ajax_cookiejar_apply_defaults', [$this, 'ajax_apply_defaults']);
        
        // Debug action removed
        
        // Policy module no longer needed - using direct dropdowns
    }
    
    public function add_wizard_page() {
        add_menu_page(
            __('CookieJar Setup', 'cookiejar'),
            __('CookieJar Setup', 'cookiejar'),
            'manage_options',
            'cookiejar-wizard',
            [$this, 'render_wizard'],
            (defined('DWIC_URL') ? \DWIC_URL . 'assets/icon/cookiejar.icn.svg' : ''),
            66
        );
        if (get_option('cookiejar_wizard_done', 'no') === 'yes') {
            remove_menu_page('cookiejar-wizard');
        }
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_cookiejar-wizard') return;
        
        if (defined('DWIC_URL') && defined('DWIC_VERSION')) {
            wp_enqueue_style('cookiejar-wizard', \DWIC_URL . 'assets/css/wizard.css', [], \DWIC_VERSION);
            wp_enqueue_script('cookiejar-wizard', \DWIC_URL . 'assets/js/wizard.js', ['jquery'], \DWIC_VERSION, true);
        } else {
            wp_enqueue_style('cookiejar-wizard', plugins_url('assets/css/wizard.css', dirname(__FILE__, 3)), [], \DWIC_VERSION);
            wp_enqueue_script('cookiejar-wizard', plugins_url('assets/js/wizard.js', dirname(__FILE__, 3)), ['jquery'], \DWIC_VERSION, true);
        }

        // Get WordPress pages for policy dropdown (WordPress best practices)
        // Method 1: Try get_pages with minimal parameters first
        $pages = get_pages([
            'post_type' => 'page',
            'post_status' => 'any', // Get all statuses
            'number' => 0, // Get all pages
            'sort_column' => 'post_title',
            'sort_order' => 'ASC'
        ]);
        
        // Method 2: If still empty, try WP_Query with minimal parameters
        if (empty($pages)) {
            $query = new \WP_Query([
                'post_type' => 'page',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'suppress_filters' => false
            ]);
            $pages = $query->posts;
            wp_reset_postdata();
        }
        
        // Method 3: Last resort - direct database query
        if (empty($pages)) {
            global $wpdb;
            $page_ids = $wpdb->get_col("
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'page' 
                AND post_status IN ('publish', 'draft', 'pending', 'private', 'trash')
                ORDER BY post_title ASC
            ");
            
            if (!empty($page_ids)) {
                $pages = [];
                foreach ($page_ids as $id) {
                    $post = get_post($id);
                    if ($post) {
                        $pages[] = $post;
                    }
                }
            }
        }
        
        // Build page options array
        $this->pageOptions = [];
        foreach ($pages as $p) {
            $status_label = '';
            switch ($p->post_status) {
                case 'publish':
                    $status_label = '';
                    break;
                case 'draft':
                    $status_label = ' (Draft)';
                    break;
                case 'pending':
                    $status_label = ' (Pending)';
                    break;
                case 'private':
                    $status_label = ' (Private)';
                    break;
                default:
                    $status_label = ' (' . ucfirst($p->post_status) . ')';
            }
            
            $this->pageOptions[] = [
                'id'    => $p->ID,
                'title' => html_entity_decode(wp_strip_all_tags($p->post_title)) . $status_label,
                'url'   => get_permalink($p->ID),
                'status' => $p->post_status,
            ];
        }
        
        // Diagnostics removed for production
        
        if (empty($this->pageOptions)) {
            error_log('CookieJar Wizard: No pages found - check WordPress page creation and permissions');
            error_log('CookieJar Wizard: WordPress version: ' . get_bloginfo('version'));
            error_log('CookieJar Wizard: Multisite: ' . (is_multisite() ? 'Yes' : 'No'));
            error_log('CookieJar Wizard: User can edit pages: ' . (current_user_can('edit_pages') ? 'Yes' : 'No'));
            
            // Additional debug: Check if any posts exist
            global $wpdb;
            $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
            $total_pages = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page'");
            error_log('CookieJar Wizard: Total posts in database: ' . $total_posts);
            error_log('CookieJar Wizard: Total pages in database: ' . $total_pages);
        }
        
        wp_localize_script('cookiejar-wizard', 'COOKIEJAR_WIZARD', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('cookiejar_admin'),
            'tier'    => function_exists('cookiejar_get_tier') ? cookiejar_get_tier() : 'basic',
            'isPro'   => function_exists('cookiejar_is_pro') ? cookiejar_is_pro() : false,
            'pages'   => $this->pageOptions,
            'i18n'    => [
                'selectAtLeastOneLanguage'       => __('Please select at least one language.', 'cookiejar'),
                'basicPlanTwoLanguages'          => __('Basic plan is limited to 2 languages.', 'cookiejar'),
                'selectAtLeastOneCategory'       => __('Please select at least one cookie category.', 'cookiejar'),
                'enterValidPolicyUrl'            => __('Please select a privacy policy page.', 'cookiejar'),
                'saveStepFailed'                 => __('Failed to save step', 'cookiejar'),
                'unknownError'                   => __('Unknown error', 'cookiejar'),
                'completeSetup'                  => __('Completing setup...', 'cookiejar'),
                'completeSetupFailed'            => __('Failed to complete setup.', 'cookiejar'),
                'saveStepFailedTryAgain'         => __('Failed to save step. Please try again.', 'cookiejar'),
                'completeSetupFailedTryAgain'    => __('Failed to complete setup. Please try again.', 'cookiejar'),
                'skipConfirm'                    => __('Are you sure you want to skip the setup wizard? Default settings will be used.', 'cookiejar'),
                'skippingWizard'                 => __('Skipping wizard...', 'cookiejar'),
                'skipWizardFailed'               => __('Failed to skip wizard.', 'cookiejar'),
                'completedTitle'                 => __('CookieJar Setup Wizard', 'cookiejar'),
                'completedLead'                  => __('✅ Setup Wizard has been successfully completed!', 'cookiejar'),
                'completedDesc'                  => __('You can review or update your current settings, restore defaults, or return to the dashboard.', 'cookiejar'),
                'enabledClickToDisable'          => __('Enabled (click to disable)', 'cookiejar'),
                'disabledClickToEnable'          => __('Disabled (click to enable)', 'cookiejar'),
                'confirmUpdate'                  => __('Are you sure you\'d like to save changes?', 'cookiejar'),
                'restartWizard'                  => __('Restart Wizard', 'cookiejar'),
                'resetPrompt'                    => __('You\'re about to restore all settings to their default values. What would you like to do?', 'cookiejar'),
                'confirmReset'                   => __('Reset All Settings to Default', 'cookiejar'),
                'confirmRestart'                 => __('Restart Setup Wizard', 'cookiejar'),
                'exitWithoutUpdate'               => __('Cancel and Exit', 'cookiejar'),
                'invalidChoice'                   => __('Invalid choice. Please enter 1, 2, or 3.', 'cookiejar'),
                'working'                        => __('Working...', 'cookiejar'),
                'updating'                       => __('Updating...', 'cookiejar'),
                'settingsUpdated'                => __('Settings Updated!', 'cookiejar'),
                'defaultsApplied'                => __('Defaults applied.', 'cookiejar'),
                'resetSuccess'                   => __('Settings reset successfully! Restarting wizard...', 'cookiejar'),
            ],
        ]);
        
        // Diagnostics removed for production
    }

    public function maybe_redirect_to_wizard() {
        if (is_network_admin() || (defined('DOING_AJAX') && DOING_AJAX)) return;
        if (get_transient('cookiejar_activation_redirect') && current_user_can('manage_options')) {
            delete_transient('cookiejar_activation_redirect');
            if (get_option('cookiejar_wizard_done', 'no') === 'no') {
                wp_safe_redirect(admin_url('admin.php?page=cookiejar-wizard'));
                exit;
            }
        }
    }
    
    public function render_wizard() {
        if (!current_user_can('manage_options')) {
            $this->render_limited_wizard();
            return;
        }

        $wizard_done = get_option('cookiejar_wizard_done', 'no') === 'yes';

        if ($wizard_done) {
            $this->render_completed_prompt();
            return;
        }

        // Load previously saved settings to preselect
        $wizard_settings = get_option('cookiejar_wizard_settings', []);
        $savedLangs = [];

        if (isset($wizard_settings['languages'])) {
            $raw = $wizard_settings['languages'];
            if (is_array($raw)) {
                $savedLangs = $raw;
            } else {
                $savedLangs = array_filter(array_map('trim', explode(',', (string)$raw)));
            }
        }
        $savedLangs = array_map([$this, 'normalize_locale'], $savedLangs);
        $savedLangs = array_values(array_unique(array_filter($savedLangs)));
        if (empty($savedLangs)) $savedLangs = ['en_US'];

        // Supported languages (autonyms + locales)
        $LANGUAGES = [
            'ar_SA' => 'العربية (السعودية)','bg_BG' => 'Български','cs_CZ' => 'Čeština','da_DK' => 'Dansk',
            'el_GR' => 'Ελληνικά','es_ES' => 'Español (España)','fa_IR' => 'فارسی (ایران)','fi_FI' => 'Suomi',
            'fr_FR' => 'Français (France)','he_IL' => 'עברית (ישראל)','hi_IN' => 'हिन्दी (भारत)','hr_HR' => 'Hrvatski',
            'hu_HU' => 'Magyar','id_ID' => 'Bahasa Indonesia','it_IT' => 'Italiano','ja_JP' => '日本語',
            'ko_KR' => '한국어','ms_MY' => 'Bahasa Melayu','nl_NL' => 'Nederlands (Nederland)','no_NO' => 'Norsk',
            'pl_PL' => 'Polski','pt_BR' => 'Português (Brasil)','ro_RO' => 'Română','ru_RU' => 'Русский',
            'sk_SK' => 'Slovenčina','sr_RS' => 'Српски','sv_SE' => 'Svenska','th_TH' => 'ไทย',
            'tr_TR' => 'Türkçe','uk_UA' => 'Українська','vi_VN' => 'Tiếng Việt','zh_CN' => '简体中文',
            'zh_TW' => '繁體中文','en_US' => 'English','de_DE' => 'Deutsch',
        ];
        $is_pro = function_exists('cookiejar_is_pro') ? cookiejar_is_pro() : false;
        ?>
        <div class="wrap cookiejar-wizard-wrap">
            <div class="cookiejar-wizard-header">
                <h1><?php esc_html_e('CookieJar Setup Wizard', 'cookiejar'); ?></h1>
                <p><?php esc_html_e('Let\'s configure your cookie consent solution in just a few steps.', 'cookiejar'); ?></p>
            </div>
            
            <div class="cookiejar-wizard-progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="wizard-progress"></div>
                </div>
                <div class="progress-steps">
                    <span class="step active" data-step="1"><?php esc_html_e('Languages', 'cookiejar'); ?></span>
                    <span class="step" data-step="2"><?php esc_html_e('Categories', 'cookiejar'); ?></span>
                    <span class="step" data-step="3"><?php esc_html_e('Appearance', 'cookiejar'); ?></span>
                    <span class="step" data-step="4"><?php esc_html_e('Settings', 'cookiejar'); ?></span>
                </div>
            </div>
            
            <div class="cookiejar-wizard-content">
                <form id="cookiejar-wizard-form">
                    <!-- Step 1: Languages -->
                    <div class="wizard-step" id="step-1" style="display: block;">
                        <h2><?php esc_html_e('Step 1: Language Configuration', 'cookiejar'); ?></h2>
                        <p><?php esc_html_e('Choose the languages for your consent banner. Language names are shown in their native form.', 'cookiejar'); ?></p>
                        
                        <div class="form-group">
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Select languages', 'cookiejar'); ?></legend>
                                <div class="cookiejar-language-grid">
                                    <?php foreach ($LANGUAGES as $code => $name): ?>
                                        <?php
                                            $code_norm = $this->normalize_locale($code);
                                            $checked = in_array($code_norm, $savedLangs, true) ? 'checked' : '';
                                        ?>
                                        <label class="cookiejar-language-option">
                                            <input type="checkbox"
                                                   class="wizard-language-checkbox"
                                                   name="languages[]"
                                                   value="<?php echo esc_attr($code_norm); ?>"
                                                   <?php echo esc_attr($checked); ?>
                                            />
                                            <span class="label-text"><?php echo esc_html($name); ?> (<?php echo esc_html($code_norm); ?>)</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description wizard-footnote" style="margin-top:8px;">
                                    <?php esc_html_e('Basic plan: select up to 2 languages.', 'cookiejar'); ?>
                                </p>
                            </fieldset>
                        </div>
                    </div>
                    
                    <!-- Step 2: Categories -->
                    <div class="wizard-step" id="step-2">
                        <h2><?php esc_html_e('Step 2: Consent Categories', 'cookiejar'); ?></h2>
                        <p><?php esc_html_e('Choose which cookie categories to include in your consent banner.', 'cookiejar'); ?></p>
                        
                        <div class="form-group">
                            <fieldset>
                                <legend><?php esc_html_e('Cookie Categories', 'cookiejar'); ?></legend>
                                
                                <label>
                                    <input type="checkbox" name="categories[]" value="necessary" checked disabled>
                                    <strong><?php esc_html_e('Necessary', 'cookiejar'); ?></strong> 
                                    <span class="description"><?php esc_html_e('(Always required)', 'cookiejar'); ?></span>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="categories[]" value="functional" checked>
                                    <?php esc_html_e('Functional', 'cookiejar'); ?>
                                    <span class="description"><?php esc_html_e('For enhanced features', 'cookiejar'); ?></span>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="categories[]" value="analytics" checked>
                                    <?php esc_html_e('Analytics', 'cookiejar'); ?>
                                    <span class="description"><?php esc_html_e('To help us improve our site', 'cookiejar'); ?></span>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="categories[]" value="advertising" checked>
                                    <?php esc_html_e('Advertising', 'cookiejar'); ?>
                                    <span class="description"><?php esc_html_e('For personalized ads', 'cookiejar'); ?></span>
                                </label><br>
                                
                                <?php if ($is_pro): ?>
                                    <label>
                                        <input type="checkbox" name="categories[]" value="chatbot">
                                        <?php esc_html_e('AI Chatbot', 'cookiejar'); ?>
                                        <span class="description"><?php esc_html_e('Enable our AI-powered assistant', 'cookiejar'); ?></span>
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="categories[]" value="donotsell">
                                        <?php esc_html_e('Do Not Sell', 'cookiejar'); ?>
                                        <span class="description"><?php esc_html_e('CPRA opt-out option', 'cookiejar'); ?></span>
                                    </label><br>
                                <?php endif; ?>
                            </fieldset>
                        </div>
                    </div>
                    
                    <!-- Step 3: Appearance -->
                    <div class="wizard-step" id="step-3">
                        <h2><?php esc_html_e('Step 3: Appearance & Policy', 'cookiejar'); ?></h2>
                        <p><?php esc_html_e('Customize the look and feel of your consent banner.', 'cookiejar'); ?></p>
                        
                        <div class="form-group">
                            <label for="wizard-color"><?php esc_html_e('Primary Color', 'cookiejar'); ?></label>
                            <div class="cookiejar-color-edit-wrap">
                                <input type="color" id="wizard-color" name="color" value="#008ed6" class="cookiejar-color-palette">
                                <input type="text" id="wizard-color-text" value="#008ed6" class="cookiejar-color-text" placeholder="#008ed6">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="wizard-bg"><?php esc_html_e('Background Color', 'cookiejar'); ?></label>
                            <div class="cookiejar-color-edit-wrap">
                                <input type="color" id="wizard-bg" name="bg" value="#ffffff" class="cookiejar-color-palette">
                                <input type="text" id="wizard-bg-text" value="#ffffff" class="cookiejar-color-text" placeholder="#ffffff">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="wizard-policy-page">Privacy Policy Page</label>
                            <select id="wizard-policy-page" name="policy_url" class="regular-text">
                                <option value="">— <?php esc_html_e('Select a page', 'cookiejar'); ?> —</option>
                                <?php 
                                ?>
                                <?php if (!empty($this->pageOptions)): ?>
                                    <?php foreach ($this->pageOptions as $page): ?>
                                        <option value="<?php echo esc_url($page['url']); ?>">
                                            <?php echo esc_html($page['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled><?php esc_html_e('No pages found. Please create a page first.', 'cookiejar'); ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="wizard-prompt"><?php esc_html_e('Custom Prompt Text', 'cookiejar'); ?></label>
                            <textarea id="wizard-prompt" name="prompt" rows="3" class="large-text" 
                                      placeholder="<?php esc_attr_e('We use cookies to enhance your experience...', 'cookiejar'); ?>"></textarea>
                        </div>
                    </div>
                    
                    <!-- Step 4: Settings -->
                    <div class="wizard-step" id="step-4">
                        <h2><?php esc_html_e('Step 4: Geo & Logging Settings', 'cookiejar'); ?></h2>
                        <p><?php esc_html_e('Configure geotargeting and logging preferences.', 'cookiejar'); ?></p>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="geo_auto" value="1" checked>
                                <?php esc_html_e('Enable automatic geotargeting', 'cookiejar'); ?>
                                <span class="description"><?php esc_html_e('Automatically detect user location for GDPR/CCPA compliance', 'cookiejar'); ?></span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label for="wizard-logging-mode"><?php esc_html_e('Logging Mode', 'cookiejar'); ?></label>
                            <select id="wizard-logging-mode" name="logging_mode" <?php echo !$is_pro ? 'disabled' : ''; ?>>
                                <option value="cached" <?php echo !$is_pro ? 'selected' : ''; ?>>
                                    <?php esc_html_e('Cached (24-hour summaries)', 'cookiejar'); ?>
                                </option>
                                <option value="live" <?php echo $is_pro ? 'selected' : ''; ?>>
                                    <?php esc_html_e('Live (real-time data)', 'cookiejar'); ?>
                                </option>
                            </select>
                            <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="gdpr_mode" value="1" checked>
                                <?php esc_html_e('Enable GDPR compliance mode', 'cookiejar'); ?>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="ccpa_mode" value="1" checked <?php echo !$is_pro ? 'disabled' : ''; ?>>
                                <?php esc_html_e('Enable CCPA compliance mode', 'cookiejar'); ?>
                            </label>
                            <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                        </div>
                    </div>
                    
                    <div class="wizard-navigation">
                        <button type="button" id="wizard-prev" class="button" style="display: none;">
                            <?php esc_html_e('Previous', 'cookiejar'); ?>
                        </button>
                        <button type="button" id="wizard-next" class="button button-primary">
                            <?php esc_html_e('Next', 'cookiejar'); ?>
                        </button>
                        <button type="button" id="wizard-finish" class="button button-primary" style="display: none;">
                            <?php esc_html_e('Finish Setup', 'cookiejar'); ?>
                        </button>
                        <button type="button" id="wizard-skip" class="button button-link">
                            <?php esc_html_e('Skip Wizard & Use Defaults', 'cookiejar'); ?>
                        </button>
                    </div>
                    
                    <div class="wizard-status" id="wizard-status" style="display: none;"></div>
                </form>
            </div>
        </div>
        <?php
    }

    private function render_completed_prompt() {
        $settings = get_option('cookiejar_wizard_settings', []);
        
        // Parse languages
        $raw_languages = isset($settings['languages']) ? $settings['languages'] : 'en_US';
        $languages = is_array($raw_languages) ? $raw_languages : array_filter(array_map('trim', explode(',', (string)$raw_languages)));
        $languages = array_map([$this, 'normalize_locale'], $languages);
        $languages = array_values(array_unique(array_filter($languages)));
        if (empty($languages)) $languages = ['en_US'];
        
        // Parse categories
        $categories = isset($settings['categories']) && is_array($settings['categories']) ? $settings['categories'] : ['necessary','functional','analytics','advertising'];
        
        // Other settings
        $banner_color = isset($settings['banner_color']) ? esc_html($settings['banner_color']) : '#008ed6';
        $banner_bg    = isset($settings['banner_bg']) ? esc_html($settings['banner_bg']) : '#ffffff';
        $policy_url   = isset($settings['policy_url']) ? esc_url($settings['policy_url']) : '';
        $prompt_text  = isset($settings['prompt_text']) ? esc_html($settings['prompt_text']) : '';
        $logging_mode = isset($settings['logging_mode']) ? esc_html($settings['logging_mode']) : 'cached';
        $geo_auto     = !empty($settings['geo_auto']);
        $gdpr_mode    = !empty($settings['gdpr_mode']);
        $ccpa_mode    = !empty($settings['ccpa_mode']);
        
        // Language names for display
        $LANGUAGE_NAMES = [
            'ar_SA' => 'العربية (السعودية)', 'bg_BG' => 'Български', 'cs_CZ' => 'Čeština', 'da_DK' => 'Dansk',
            'el_GR' => 'Ελληνικά', 'es_ES' => 'Español (España)', 'fa_IR' => 'فارسی (ایران)', 'fi_FI' => 'Suomi',
            'fr_FR' => 'Français (France)', 'he_IL' => 'עברית (ישראל)', 'hi_IN' => 'हिन्दी (भारत)', 'hr_HR' => 'Hrvatski',
            'hu_HU' => 'Magyar', 'id_ID' => 'Bahasa Indonesia', 'it_IT' => 'Italiano', 'ja_JP' => '日本語',
            'ko_KR' => '한국어', 'ms_MY' => 'Bahasa Melayu', 'nl_NL' => 'Nederlands (Nederland)', 'no_NO' => 'Norsk',
            'pl_PL' => 'Polski', 'pt_BR' => 'Português (Brasil)', 'ro_RO' => 'Română', 'ru_RU' => 'Русский',
            'sk_SK' => 'Slovenčina', 'sr_RS' => 'Српски', 'sv_SE' => 'Svenska', 'th_TH' => 'ไทย',
            'tr_TR' => 'Türkçe', 'uk_UA' => 'Українська', 'vi_VN' => 'Tiếng Việt', 'zh_CN' => '简体中文',
            'zh_TW' => '繁體中文', 'en_US' => 'English', 'de_DE' => 'Deutsch',
        ];
        
        // Category names
        $CATEGORY_NAMES = [
            'necessary' => __('Necessary', 'cookiejar'),
            'functional' => __('Functional', 'cookiejar'),
            'analytics' => __('Analytics', 'cookiejar'),
            'advertising' => __('Advertising', 'cookiejar'),
            'chatbot' => __('AI Chatbot', 'cookiejar'),
            'donotsell' => __('Do Not Sell', 'cookiejar'),
        ];
        
        // Pages already retrieved above with proper parameters
        ?>
        <div class="wrap cookiejar-wizard-wrap">
            <div class="cookiejar-wizard-header">
                <h1><?php esc_html_e('CookieJar Setup Wizard', 'cookiejar'); ?></h1>
                <div id="cookiejar-wizard-prompt-status" class="notice" style="display:none; margin-top:12px;"></div>
                <p class="description">
                    ✅ 
                    <?php esc_html_e('Your setup is already complete.', 'cookiejar'); ?><br>
                    <?php esc_html_e('You can review or update your current settings, restore all settings to their default values, or return to the dashboard.', 'cookiejar'); ?>
                </p>
            </div>

            <div class="cookiejar-wizard-content">
                <div class="cookiejar-review-card">
                    <details id="cookiejar-review-toggle" style="margin: 0;">
                        <summary style="cursor: pointer; font-weight: 600; padding: 8px 0;">
                            <?php esc_html_e('Review & Edit Current Settings', 'cookiejar'); ?>
                        </summary>
                        
                        <div id="cookiejar-quick-tip" style="margin: 12px 0; padding: 8px 12px; background: #f0f6fc; border-left: 3px solid #008ed6; font-size: 14px; display: none;">
                            <?php esc_html_e('Click on any setting below to edit it. Changes will be saved when you click "Update Settings".', 'cookiejar'); ?>
                        </div>
                        
                        <div id="cookiejar-review-list" style="margin-top: 16px;">
                            <ul class="cookiejar-review-list">
                                <!-- Languages -->
                                <li>
                                    <strong><?php esc_html_e('Languages:', 'cookiejar'); ?></strong>
                                    <div id="cookiejar-langs" style="margin-top: 4px;">
                                        <?php foreach ($LANGUAGE_NAMES as $code => $name): ?>
                                            <?php $is_active = in_array($code, $languages, true); ?>
                                            <span class="cookiejar-chip <?php echo $is_active ? 'is-active' : ''; ?>" 
                                                  data-type="lang" 
                                                  data-code="<?php echo esc_attr($code); ?>"
                                                  <?php echo $is_active ? 'aria-pressed="true"' : 'aria-pressed="false"'; ?>
                                                  title="<?php echo $is_active ? esc_attr__('Enabled (click to disable)', 'cookiejar') : esc_attr__('Disabled (click to enable)', 'cookiejar'); ?>">
                                                <?php echo esc_html($name); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description wizard-footnote" style="margin-top:8px; font-size:12px; color:#646970;">
                                        <?php esc_html_e('Basic plan: select up to 2 languages.', 'cookiejar'); ?>
                                    </p>
                                </li>
                                
                                <!-- Categories -->
                                <li>
                                    <strong><?php esc_html_e('Categories:', 'cookiejar'); ?></strong>
                                    <div id="cookiejar-cats" style="margin-top: 4px;">
                                        <?php foreach ($CATEGORY_NAMES as $code => $name): ?>
                                            <?php 
                                                // Skip Pro-only categories for basic users
                                                if (!$is_pro && in_array($code, ['chatbot', 'donotsell'], true)) {
                                                    continue;
                                                }
                                                $is_active = in_array($code, $categories, true); 
                                            ?>
                                            <span class="cookiejar-chip <?php echo $is_active ? 'is-active' : ''; ?>" 
                                                  data-type="cat" 
                                                  data-code="<?php echo esc_attr($code); ?>"
                                                  <?php echo $is_active ? 'aria-pressed="true"' : 'aria-pressed="false"'; ?>
                                                  title="<?php echo $is_active ? esc_attr__('Enabled (click to disable)', 'cookiejar') : esc_attr__('Disabled (click to enable)', 'cookiejar'); ?>">
                                                <?php echo esc_html($name); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                                </li>
                                
                                <!-- Colors -->
                                <li>
                                    <strong><?php esc_html_e('Colors:', 'cookiejar'); ?></strong>
                                    <div style="margin-top: 4px; display: flex; gap: 12px; flex-wrap: wrap;">
                                        <div id="cookiejar-color-trigger" class="cookiejar-color-swatch cookiejar-color-trigger" 
                                             data-color-type="primary" 
                                             data-current-color="<?php echo esc_attr($banner_color); ?>"
                                             title="<?php esc_attr_e('Click to edit primary color', 'cookiejar'); ?>">
                                            <div class="cookiejar-color-dot" style="background: <?php echo esc_attr($banner_color); ?>;"></div>
                                            <span><?php esc_html_e('Primary:', 'cookiejar'); ?></span>
                                            <code><?php echo esc_html($banner_color); ?></code>
                                        </div>
                                        <div id="cookiejar-bg-trigger" class="cookiejar-color-swatch cookiejar-color-trigger" 
                                             data-color-type="background" 
                                             data-current-color="<?php echo esc_attr($banner_bg); ?>"
                                             title="<?php esc_attr_e('Click to edit background color', 'cookiejar'); ?>">
                                            <div class="cookiejar-color-dot" style="background: <?php echo esc_attr($banner_bg); ?>;"></div>
                                            <span><?php esc_html_e('Background:', 'cookiejar'); ?></span>
                                            <code><?php echo esc_html($banner_bg); ?></code>
                                        </div>
                                    </div>
                                </li>
                                
                                <!-- Policy URL -->
                                <li>
                                    <strong><?php esc_html_e('Privacy Policy:', 'cookiejar'); ?></strong>
                                    <div style="margin-top: 4px;">
                                        <select id="cookiejar-policy-page" class="regular-text cookiejar-color-swatch" style="max-width: 300px;">
                                            <option value="">— <?php esc_html_e('Select a page', 'cookiejar'); ?> —</option>
                                            <?php if (!empty($this->pageOptions)): ?>
                                                <?php foreach ($this->pageOptions as $page): ?>
                                                    <option value="<?php echo esc_url($page['url']); ?>" <?php selected($policy_url, $page['url']); ?>>
                                                        <?php echo esc_html($page['title']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="" disabled><?php esc_html_e('No pages found. Please create a page first.', 'cookiejar'); ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </li>
                                
                                <!-- Prompt Text -->
                                <li>
                                    <strong><?php esc_html_e('Prompt Text:', 'cookiejar'); ?></strong>
                                    <div style="margin-top: 4px;">
                                        <div id="cookiejar-prompt-trigger" class="cookiejar-prompt-trigger" 
                                             data-current-text="<?php echo esc_attr($prompt_text); ?>"
                                             title="<?php esc_attr_e('Click to edit prompt text', 'cookiejar'); ?>"
                                             role="button" 
                                             tabindex="0"
                                             aria-label="<?php esc_attr_e('Click to edit prompt text', 'cookiejar'); ?>">
                                            <span class="cookiejar-prompt-display">
                                                <?php echo $prompt_text ? esc_html($prompt_text) : '—'; ?>
                                            </span>
                                            <span class="cookiejar-edit-indicator">✏️</span>
                                        </div>
                                    </div>
                                </li>
                                
                                <!-- Logging Mode -->
                                <li>
                                    <strong><?php esc_html_e('Logging Mode:', 'cookiejar'); ?></strong>
                                    <div style="margin-top: 4px;">
                                        <span id="cookiejar-log-cached" class="cookiejar-chip <?php echo $logging_mode === 'cached' ? 'is-active' : ''; ?>" 
                                              data-type="log" 
                                              data-code="cached"
                                              <?php echo $logging_mode === 'cached' ? 'aria-pressed="true"' : 'aria-pressed="false"'; ?>
                                              title="<?php echo $logging_mode === 'cached' ? esc_attr__('Enabled (click to disable)', 'cookiejar') : esc_attr__('Disabled (click to enable)', 'cookiejar'); ?>">
                                            <?php esc_html_e('Cached (24-hour summaries)', 'cookiejar'); ?>
                                        </span>
                                        <span id="cookiejar-log-live" class="cookiejar-chip <?php echo $logging_mode === 'live' ? 'is-active' : ''; ?>" 
                                              data-type="log" 
                                              data-code="live"
                                              <?php echo $logging_mode === 'live' ? 'aria-pressed="true"' : 'aria-pressed="false"'; ?>
                                              title="<?php echo $logging_mode === 'live' ? esc_attr__('Enabled (click to disable)', 'cookiejar') : esc_attr__('Disabled (click to enable)', 'cookiejar'); ?>">
                                            <?php esc_html_e('Live (real-time data)', 'cookiejar'); ?>
                                        </span>
                                    </div>
                                    <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                                </li>
                                
                                <!-- Boolean Settings -->
                                <li>
                                    <strong><?php esc_html_e('Settings:', 'cookiejar'); ?></strong>
                                    <div style="margin-top: 4px; display: flex; gap: 16px; flex-wrap: wrap;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span class="cookiejar-dot <?php echo $geo_auto ? 'on' : 'off'; ?>" 
                                                  id="cookiejar-geo" 
                                                  <?php echo $geo_auto ? 'aria-checked="true"' : 'aria-checked="false"'; ?>
                                                  title="<?php echo $geo_auto ? esc_attr__('Enabled (click to disable)', 'cookiejar') : esc_attr__('Disabled (click to enable)', 'cookiejar'); ?>"></span>
                                            <span><?php esc_html_e('Geo-targeting', 'cookiejar'); ?></span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span class="cookiejar-dot <?php echo $gdpr_mode ? 'on' : 'off'; ?>" 
                                                  id="cookiejar-gdpr" 
                                                  <?php echo $gdpr_mode ? 'aria-checked="true"' : 'aria-checked="false"'; ?>
                                                  title="<?php echo $gdpr_mode ? esc_attr__('Enabled (click to disable)', 'cookiejar') : esc_attr__('Disabled (click to enable)', 'cookiejar'); ?>"></span>
                                            <span><?php esc_html_e('GDPR Mode', 'cookiejar'); ?></span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span class="cookiejar-dot <?php echo ($is_pro && $ccpa_mode) ? 'on' : 'off'; ?>" 
                                                  id="cookiejar-ccpa" 
                                                  <?php echo ($is_pro && $ccpa_mode) ? 'aria-checked="true"' : 'aria-checked="false"'; ?>
                                                  title="<?php echo ($is_pro && $ccpa_mode) ? esc_attr__('Enabled (click to disable)', 'cookiejar') : esc_attr__('Disabled (click to enable)', 'cookiejar'); ?>"
                                                  <?php echo !$is_pro ? 'style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>></span>
                                            <span><?php esc_html_e('CCPA Mode', 'cookiejar'); ?></span>
                                        </div>
                                    </div>
                                    <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                                </li>
                            </ul>
                        </div>
                    </details>
                </div>

                <div class="cookiejar-actions">
                    <button type="button" id="cookiejar-wizard-update" class="button button-primary">
                        <?php esc_html_e('Update Settings', 'cookiejar'); ?>
                    </button>
                    <button type="button" id="cookiejar-wizard-reset" class="button">
                        <?php esc_html_e('Reset to Default', 'cookiejar'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=cookiejar-control')); ?>" class="button button-secondary">
                        <?php esc_html_e('Exit to Control Panel', 'cookiejar'); ?>
                    </a>
                </div>

                <div id="cookiejar-wizard-prompt-status" class="notice" style="display:none; margin-top:12px;"></div>
            </div>
            
            <!-- Reset Confirmation Modal -->
            <div id="cookiejar-reset-modal" class="cookiejar-modal" style="display: none;">
                <div class="cookiejar-modal-content">
                    <div class="cookiejar-modal-header">
                        <h3><?php esc_html_e('Reset to Default Settings', 'cookiejar'); ?></h3>
                        <button type="button" id="cookiejar-reset-modal-close" class="cookiejar-modal-close">&times;</button>
                    </div>
                    <div class="cookiejar-modal-body">
                        <p><?php esc_html_e('You\'re about to restore all settings to their default values. What would you like to do?', 'cookiejar'); ?></p>
                        <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                            <button type="button" id="cookiejar-reset-confirm" class="button button-primary">
                                <?php esc_html_e('Reset All Settings to Default', 'cookiejar'); ?>
                            </button>
                            <button type="button" id="cookiejar-reset-restart" class="button button-secondary">
                                <?php esc_html_e('Restart Setup Wizard', 'cookiejar'); ?>
                            </button>
                            <button type="button" id="cookiejar-reset-exit" class="button">
                                <?php esc_html_e('Cancel and Exit', 'cookiejar'); ?>
                            </button>
                        </div>
                        <p class="description" style="margin-top: 15px; font-size: 12px; color: #646970;">
                            <?php esc_html_e('Reset All Settings to Default: Applies defaults and exits to Control Panel', 'cookiejar'); ?><br>
                            <?php esc_html_e('Restart Setup Wizard: Clears settings and restarts the setup wizard', 'cookiejar'); ?><br>
                            <?php esc_html_e('Cancel and Exit: Closes this dialog without making any changes', 'cookiejar'); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Policy modals removed - using direct dropdowns instead -->
            
            <!-- Color Picker Modal -->
            <div id="cookiejar-color-modal" class="cookiejar-modal" style="display: none;">
                <div class="cookiejar-modal-content">
                    <div class="cookiejar-modal-header">
                        <h3 id="cookiejar-color-modal-title"><?php esc_html_e('Edit Color', 'cookiejar'); ?></h3>
                        <button type="button" id="cookiejar-color-modal-close" class="cookiejar-modal-close">&times;</button>
                    </div>
                    <div class="cookiejar-modal-body">
                        <div class="form-group">
                            <label id="cookiejar-color-modal-label"><?php esc_html_e('Color', 'cookiejar'); ?></label>
                            <div class="cookiejar-color-edit-wrap">
                                <input type="color" id="cookiejar-color-modal-picker" class="cookiejar-color-palette">
                                <input type="text" id="cookiejar-color-modal-text" class="cookiejar-color-text" placeholder="#008ed6">
                            </div>
                        </div>
                    </div>
                    <div class="cookiejar-modal-footer">
                        <button type="button" id="cookiejar-color-modal-cancel" class="button"><?php esc_html_e('Cancel', 'cookiejar'); ?></button>
                        <button type="button" id="cookiejar-color-modal-save" class="button button-primary"><?php esc_html_e('Save', 'cookiejar'); ?></button>
                    </div>
                </div>
            </div>
            
            <!-- Privacy Policy Modal -->
            <div id="cookiejar-policy-modal" class="cookiejar-modal" style="display: none;">
                <div class="cookiejar-modal-content">
                    <div class="cookiejar-modal-header">
                        <h3><?php esc_html_e('Select Privacy Policy Page', 'cookiejar'); ?></h3>
                        <button type="button" id="cookiejar-policy-modal-close" class="cookiejar-modal-close">&times;</button>
                    </div>
                    <div class="cookiejar-modal-body">
                        <div class="cookiejar-policy-search">
                            <input type="search" id="cookiejar-policy-modal-search" class="cookiejar-policy-search-input" placeholder="<?php esc_attr_e('Search pages...', 'cookiejar'); ?>" aria-label="<?php esc_attr_e('Search pages...', 'cookiejar'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="cookiejar-policy-modal-select"><?php esc_html_e('Privacy Policy Page', 'cookiejar'); ?></label>
                            <select id="cookiejar-policy-modal-select" class="regular-text cookiejar-policy-select" size="8">
                                <option value="">— <?php esc_html_e('Select a page', 'cookiejar'); ?> —</option>
                                <?php if (!empty($this->pageOptions)): ?>
                                    <?php foreach ($this->pageOptions as $page): ?>
                                        <option value="<?php echo esc_url($page['url']); ?>" <?php selected($policy_url, $page['url']); ?>>
                                            <?php echo esc_html($page['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled><?php esc_html_e('No pages found. Please create a page first.', 'cookiejar'); ?></option>
                                <?php endif; ?>
                            </select>
                            <div class="cookiejar-policy-noresults" style="display:none;"><?php esc_html_e('No pages match', 'cookiejar'); ?></div>
                            <p class="description">
                                <?php esc_html_e('Choose a WordPress page to use as your privacy policy. Includes all pages regardless of status.', 'cookiejar'); ?>
                                <?php if (empty($this->pageOptions)): ?>
                                    <br><strong><?php esc_html_e('Note: No pages found. Please create a page first.', 'cookiejar'); ?></strong>
                                    <?php if (current_user_can('manage_options')): ?>
                                        <br><em><?php esc_html_e('Debug: Check WordPress error logs for page retrieval details.', 'cookiejar'); ?></em>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php /* translators: %d: number of pages */ printf(esc_html__('Found %d pages available for selection.', 'cookiejar'), count($this->pageOptions)); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="cookiejar-modal-footer">
                        <button type="button" id="cookiejar-policy-modal-cancel" class="button"><?php esc_html_e('Cancel', 'cookiejar'); ?></button>
                        <button type="button" id="cookiejar-policy-modal-save" class="button button-primary"><?php esc_html_e('Save', 'cookiejar'); ?></button>
                    </div>
                </div>
            </div>
            
            <!-- Prompt Text Modal -->
            <div id="cookiejar-prompt-modal" class="cookiejar-modal" style="display: none;">
                <div class="cookiejar-modal-content">
                    <div class="cookiejar-modal-header">
                        <h3><?php esc_html_e('Edit Prompt Text', 'cookiejar'); ?></h3>
                        <button type="button" id="cookiejar-prompt-modal-close" class="cookiejar-modal-close">&times;</button>
                    </div>
                    <div class="cookiejar-modal-body">
                        <div class="form-group">
                            <label for="cookiejar-prompt-modal-textarea"><?php esc_html_e('Custom Prompt Text', 'cookiejar'); ?></label>
                            <textarea id="cookiejar-prompt-modal-textarea" rows="4" class="large-text" 
                                      placeholder="<?php esc_attr_e('We use cookies to enhance your experience...', 'cookiejar'); ?>"><?php echo esc_textarea($prompt_text); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Enter the text that will be displayed in your cookie consent banner.', 'cookiejar'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="cookiejar-modal-footer">
                        <button type="button" id="cookiejar-prompt-modal-cancel" class="button"><?php esc_html_e('Cancel', 'cookiejar'); ?></button>
                        <button type="button" id="cookiejar-prompt-modal-save" class="button button-primary"><?php esc_html_e('Save', 'cookiejar'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <?php
    }

    public function ajax_reset_wizard() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cookiejar'));
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(__('Invalid nonce', 'cookiejar'));
        }

        $clear = isset($_POST['clear_settings']) ? (int) $_POST['clear_settings'] : 0;

        update_option('cookiejar_wizard_done', 'no');
        if ($clear === 1) {
            delete_option('cookiejar_wizard_settings');
        }

        wp_send_json_success([
            'message'  => __('Wizard reset successfully. Page will refresh to restart the setup.', 'cookiejar'),
        ]);
    }

    public function ajax_apply_defaults() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cookiejar'));
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(__('Invalid nonce', 'cookiejar'));
        }

        $defaults = [
            'languages'    => 'en_US',
            'categories'   => ['necessary','functional','analytics','advertising'],
            'banner_color' => '#008ed6',
            'banner_bg'    => '#ffffff',
            'policy_url'   => '',
            'prompt_text'  => __('We use cookies to enhance your experience.', 'cookiejar'),
            'geo_auto'     => true,
            'logging_mode' => 'cached',
            'gdpr_mode'    => true,
            'ccpa_mode'    => true,
        ];
        update_option('cookiejar_wizard_settings', $defaults, false);
        update_option('cookiejar_wizard_done', 'yes');

        wp_send_json_success([
            'message'  => __('Defaults applied successfully. Page will refresh.', 'cookiejar'),
        ]);
    }

    public function ajax_complete_wizard() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cookiejar'));
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(__('Invalid nonce', 'cookiejar'));
        }

        $is_pro = function_exists('cookiejar_is_pro') ? cookiejar_is_pro() : false;

        $rawLanguages = $_POST['languages'] ?? 'en_US';

        $settings = [
            'languages'    => $this->sanitize_languages($rawLanguages, $is_pro),
            'categories'   => $this->sanitize_categories($_POST['categories'] ?? []),
            'banner_color' => sanitize_hex_color($_POST['banner_color'] ?? $_POST['color'] ?? '#008ed6'),
            'banner_bg'    => sanitize_hex_color($_POST['banner_bg'] ?? $_POST['bg'] ?? '#ffffff'),
            'policy_url'   => esc_url_raw($_POST['policy_url'] ?? ''),
            'prompt_text'  => sanitize_textarea_field($_POST['prompt_text'] ?? $_POST['prompt'] ?? ''),
            'geo_auto'     => !empty($_POST['geo_auto']) && ($_POST['geo_auto'] === '1' || $_POST['geo_auto'] === 'true'),
            'logging_mode' => sanitize_text_field($_POST['logging_mode'] ?? 'cached'),
            'gdpr_mode'    => !empty($_POST['gdpr_mode']) && ($_POST['gdpr_mode'] === '1' || $_POST['gdpr_mode'] === 'true'),
            'ccpa_mode'    => !empty($_POST['ccpa_mode']) && ($_POST['ccpa_mode'] === '1' || $_POST['ccpa_mode'] === 'true'),
        ];

        $settings['logging_mode'] = ($is_pro && $settings['logging_mode'] === 'live') ? 'live' : 'cached';
        if (!$settings['categories'] || !in_array('necessary', $settings['categories'], true)) {
            array_unshift($settings['categories'], 'necessary');
            $settings['categories'] = array_values(array_unique($settings['categories']));
        }

        update_option('cookiejar_wizard_settings', $settings, false);
        update_option('cookiejar_wizard_done', 'yes');

        wp_send_json_success([
            'message'  => __('Settings saved successfully.', 'cookiejar'),
            'redirect' => admin_url('admin.php?page=cookiejar-control'),
        ]);
    }

    public function ajax_skip_wizard() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cookiejar'));
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(__('Invalid nonce', 'cookiejar'));
        }

        update_option('cookiejar_wizard_done', 'yes');

        $redirect_url = admin_url('admin.php?page=cookiejar-control');
        
        // Debug logging
        error_log('CookieJar: Skip wizard - redirect URL: ' . $redirect_url);

        wp_send_json_success([
            'message'  => __('Wizard skipped. Using default settings.', 'cookiejar'),
            'redirect' => $redirect_url,
        ]);
    }

    public function ajax_save_wizard() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cookiejar'));
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(__('Invalid nonce', 'cookiejar'));
        }

        $step = intval($_POST['step'] ?? 0);
        $raw  = $_POST['data'] ?? '{}';
        $data = is_string($raw) ? json_decode(stripslashes($raw), true) : (array)$raw;
        if (!is_array($data)) $data = [];

        if ($step < 1 || $step > 4) {
            wp_send_json_error(__('Invalid step number', 'cookiejar'));
        }

        $wizard_settings = get_option('cookiejar_wizard_settings', []);
        $is_pro = function_exists('cookiejar_is_pro') ? cookiejar_is_pro() : false;

        switch ($step) {
            case 1:
                if (array_key_exists('languages', $data)) {
                    $wizard_settings['languages'] = $this->sanitize_languages($data['languages'] ?? 'en_US', $is_pro);
                }
                break;
            case 2:
                if (array_key_exists('categories', $data)) {
                    $wizard_settings['categories'] = $this->sanitize_categories($data['categories'] ?? []);
                }
                break;
            case 3:
                if (array_key_exists('color', $data)) {
                    $wizard_settings['banner_color'] = sanitize_hex_color($data['color'] ?? '#008ed6');
                }
                if (array_key_exists('bg', $data)) {
                    $wizard_settings['banner_bg']    = sanitize_hex_color($data['bg'] ?? '#ffffff');
                }
                if (array_key_exists('policy_url', $data)) {
                    $wizard_settings['policy_url']   = esc_url_raw($data['policy_url'] ?? '');
                }
                if (array_key_exists('prompt', $data)) {
                    $wizard_settings['prompt_text']  = sanitize_textarea_field($data['prompt'] ?? '');
                }
                break;
            case 4:
                if (array_key_exists('geo_auto', $data)) {
                    $wizard_settings['geo_auto'] = !empty($data['geo_auto']);
                }
                if (array_key_exists('logging_mode', $data)) {
                    $wizard_settings['logging_mode'] = ($is_pro && ($data['logging_mode'] ?? 'cached') === 'live') ? 'live' : 'cached';
                }
                if (array_key_exists('gdpr_mode', $data)) {
                    $wizard_settings['gdpr_mode'] = !empty($data['gdpr_mode']);
                }
                if (array_key_exists('ccpa_mode', $data)) {
                    $wizard_settings['ccpa_mode'] = !empty($data['ccpa_mode']);
                }
                break;
        }

        update_option('cookiejar_wizard_settings', $wizard_settings, false);

        wp_send_json_success([
            'message' => __('Step saved successfully', 'cookiejar'),
            'step'    => $step,
        ]);
    }

    private function normalize_locale($code) {
        $code = (string) $code;
        $code = trim($code);
        if ($code === '') return '';
        if (preg_match('/^([a-zA-Z]{2,3})(?:[-_]?([a-zA-Z]{2}))?$/', $code, $m)) {
            $lang = strtolower($m[1]);
            $region = isset($m[2]) ? strtoupper($m[2]) : '';
            return $region ? "{$lang}_{$region}" : $lang;
        }
        return strtolower($code);
    }

    private function sanitize_languages($raw, $is_pro) {
        $codes = is_array($raw) ? $raw : array_filter(array_map('trim', explode(',', (string)$raw)));
        $codes = array_map([$this, 'normalize_locale'], $codes);
        $codes = array_values(array_unique(array_filter($codes)));
        if (!$is_pro) $codes = array_slice($codes, 0, 2);
        if (!$codes) $codes = ['en_US'];
        return implode(',', $codes);
    }

    private function sanitize_categories($input) {
        $allowed = ['necessary','functional','analytics','advertising','chatbot','donotsell'];
        $cats = array_map('sanitize_key', (array)$input);
        $cats = array_values(array_intersect($cats, $allowed));
        if (!in_array('necessary', $cats, true)) array_unshift($cats, 'necessary');
        return $cats;
    }

    private function render_limited_wizard() {
        ?>
        <div class="wrap cookiejar-wizard-wrap">
            <div class="cookiejar-wizard-header">
                <h1><?php esc_html_e('CookieJar Setup Wizard', 'cookiejar'); ?></h1>
                <p><?php esc_html_e('CookieJar Setup Wizard is available to administrators only.', 'cookiejar'); ?></p>
            </div>
            <div class="cookiejar-wizard-content">
                <div class="notice notice-warning">
                    <p><strong><?php esc_html_e('Access Restricted', 'cookiejar'); ?></strong></p>
                    <p><?php esc_html_e('The CookieJar Setup Wizard requires administrator privileges to configure cookie consent settings.', 'cookiejar'); ?></p>
                    <p><?php esc_html_e('Please contact your site administrator to complete the setup.', 'cookiejar'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Comprehensive One-Click Diagnostic Tool
     */
    public function ajax_debug_pages() { wp_send_json_error(['message' => 'disabled'], 400); }

    private function get_site_info() { return []; }
    
    private function get_wordpress_info() { return []; }
    
    private function get_database_info() { return []; }
    
    private function get_page_retrieval_tests() { return []; }
    
    private function get_permissions_check() { return []; }
    
    private function get_conflict_check() { return []; }
    
    private function get_filter_analysis() { return []; }
    
    private function generate_recommendations($diagnostic) { return []; }
}