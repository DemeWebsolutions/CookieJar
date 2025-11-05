<?php
namespace DWIC\Admin;

if (!defined('ABSPATH')) exit;

class CookieJar_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version){
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_menu', [$this,'menu']);
        add_action('admin_enqueue_scripts', [$this,'assets']);
        // Removed global admin background tweaks to avoid affecting non-CookieJar pages

        // AJAX
        add_action('wp_ajax_cookiejar_recent_logs', [$this,'ajax_recent_logs']);
        add_action('wp_ajax_cookiejar_reset_notice', [$this,'ajax_reset_notice']);
        add_action('wp_ajax_cookiejar_banner_settings', [$this,'ajax_banner_settings']);
        add_action('wp_ajax_cookiejar_save_banner_settings', [$this,'ajax_save_banner_settings']);
        add_action('wp_ajax_cookiejar_save_language_settings', [$this,'ajax_save_language_settings']);
        add_action('wp_ajax_cookiejar_save_advanced_settings', [$this,'ajax_save_advanced_settings']);
        add_action('wp_ajax_cookiejar_export_logs', [$this,'ajax_export_logs']);
        
        // Enhanced AJAX endpoints
        add_action('wp_ajax_cookiejar_clear_cache', [$this,'ajax_clear_cache']);
        add_action('wp_ajax_cookiejar_warm_cache', [$this,'ajax_warm_cache']);
        add_action('wp_ajax_cookiejar_health_check', [$this,'ajax_health_check']);
        add_action('wp_ajax_cookiejar_clear_logs', [$this,'ajax_clear_logs']);
        
        // New settings AJAX endpoints
        add_action('wp_ajax_cookiejar_save_general_settings', [$this,'ajax_save_general_settings']);
        add_action('wp_ajax_cookiejar_save_appearance_settings', [$this,'ajax_save_appearance_settings']);
        add_action('wp_ajax_cookiejar_save_compliance_settings', [$this,'ajax_save_compliance_settings']);
        add_action('wp_ajax_cookiejar_save_security_settings', [$this,'ajax_save_security_settings']);
        add_action('wp_ajax_cookiejar_save_performance_settings', [$this,'ajax_save_performance_settings']);
        add_action('wp_ajax_cookiejar_save_integration_settings', [$this,'ajax_save_integration_settings']);
        add_action('wp_ajax_cookiejar_backup_settings', [$this,'ajax_backup_settings']);
        add_action('wp_ajax_cookiejar_restore_settings', [$this,'ajax_restore_settings']);
        add_action('wp_ajax_cookiejar_export_basic_report', [$this,'ajax_export_basic_report']);
        add_action('wp_ajax_cookiejar_save_settings', [$this,'ajax_save_settings']);
        add_action('wp_ajax_cookiejar_skip_wizard', [$this,'ajax_skip_wizard']);
        add_action('wp_ajax_cookiejar_save_wizard', [$this,'ajax_save_wizard']);
        add_action('wp_ajax_cookiejar_complete_wizard', [$this,'ajax_complete_wizard']);
        add_action('wp_ajax_cookiejar_reset_wizard', [$this,'ajax_reset_wizard']);
        add_action('wp_ajax_cookiejar_force_wizard_menu', [$this,'ajax_force_wizard_menu']);
        add_action('wp_ajax_cookiejar_apply_defaults', [$this,'ajax_apply_defaults']);
        add_action('wp_ajax_cookiejar_migrate_database', [$this,'ajax_migrate_database']);
        // Add a small, compliant footer link on our settings page only
        add_action('admin_footer', [$this,'render_settings_footer_link']);
    }

    public function menu(){
        
        // Use custom SVG icon for CookieJar branding
        $icon = \DWIC_URL . 'assets/icon/cookiejar.icn.svg';

        $main_page = add_menu_page(
            __('CookieJar Dashboard','cookiejar'),   // Page title
            __('CookieJar','cookiejar'),             // Menu title
            'manage_options',                   // Capability
            'cookiejar-dashboard',              // Menu slug
            [$this,'page_dashboard'],           // Callback
            $icon,                              // Icon (SVG or PNG fallback)
            2                                   // Position
        );

        // Ensure first submenu item is "Bakery Dashboard" pointing to the main page
        add_submenu_page(
            'cookiejar-dashboard',
            __('Bakery Dashboard','cookiejar'),
            __('Bakery Dashboard','cookiejar'),
            'manage_options',
            'cookiejar-dashboard',
            [$this,'page_dashboard']
        );

        $control_page = add_submenu_page(
            'cookiejar-dashboard',
            __('Control Panel','cookiejar'),
            __('Control Panel','cookiejar'),
            'manage_options',
            'cookiejar-control',
            [$this,'page_control_panel']
        );
    }

    public function assets($hook){
        // Enqueue admin CSS on all admin pages for menu icon styling
        // Add cache-busting timestamp to force CSS reload
        $css_version = $this->version . '.' . filemtime(\DWIC_PATH . 'assets/css/cookiejar-admin.css');
        wp_enqueue_style('cookiejar-admin', \DWIC_URL.'assets/css/cookiejar-admin.css', [], $css_version);
        
        // Only enqueue admin JS on CookieJar admin pages
        if (strpos($hook,'cookiejar-dashboard')===false && strpos($hook,'cookiejar-control')===false) return;

        wp_enqueue_script('cookiejar-admin', \DWIC_URL.'assets/js/cookiejar-admin.js', ['jquery'], $this->version, true);

        // Control Panel "pages" metadata
        $pages = [
            [ 'slug'=>'dashboard', 'title'=>__('CookieJar Control Panel','cookiejar'), 'explain'=>__('Overview & quick controls','cookiejar') ],
            [ 'slug'=>'reports',   'title'=>__('Review Reports','cookiejar'),              'explain'=>__('Manage Consent Reports','cookiejar') ],
            [ 'slug'=>'upgrade',   'title'=>__('Learn more','cookiejar'),              'explain'=>__('Documentation & licensing','cookiejar') ],
            [ 'slug'=>'wizard',    'title'=>__('Wizard Setup','cookiejar'),                'explain'=>__('Setup wizard for new users','cookiejar') ],
            [ 'slug'=>'banner',    'title'=>__('Cookie Banner Settings','cookiejar'),      'explain'=>__('Manage cookies banner','cookiejar') ],
            [ 'slug'=>'logs',      'title'=>__('Consent Logs','cookiejar'),                'explain'=>__('View and export consent logs','cookiejar') ],
            [ 'slug'=>'languages', 'title'=>__('Languages','cookiejar'),                   'explain'=>__('Localization & translations','cookiejar') ],
            [ 'slug'=>'advanced',  'title'=>__('Advanced Settings','cookiejar'),           'explain'=>__('Technical & advanced options','cookiejar') ],
            [ 'slug'=>'settings',  'title'=>__('Settings','cookiejar'),                    'explain'=>__('General plugin settings','cookiejar') ],
            [ 'slug'=>'general',   'title'=>__('General Settings','cookiejar'),            'explain'=>__('Core plugin settings','cookiejar') ],
            [ 'slug'=>'appearance','title'=>__('Appearance','cookiejar'),                  'explain'=>__('Banner styling & themes','cookiejar') ],
            [ 'slug'=>'compliance','title'=>__('Compliance','cookiejar'),                  'explain'=>__('GDPR, CCPA, LGPD settings','cookiejar') ],
            [ 'slug'=>'security',  'title'=>__('Security','cookiejar'),                    'explain'=>__('Privacy & security options','cookiejar') ],
            [ 'slug'=>'performance','title'=>__('Performance','cookiejar'),                'explain'=>__('Caching & optimization','cookiejar') ],
            [ 'slug'=>'integrations','title'=>__('Integrations','cookiejar'),              'explain'=>__('Third-party APIs','cookiejar') ],
            [ 'slug'=>'backup',    'title'=>__('Backup & Restore','cookiejar'),            'explain'=>__('Settings management','cookiejar') ],
            [ 'slug'=>'community', 'title'=>__('Community','cookiejar'),                   'explain'=>__('Join our community','cookiejar') ],
            [ 'slug'=>'support',   'title'=>__('Help & Support','cookiejar'),              'explain'=>__('Help center & support','cookiejar') ],
        ];

        // Predictive search routes
        $routes = [
            [ 'label'=>'Bakery Control Panel',    'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-dashboard'),              'keywords'=>['dashboard','control','panel','main'] ],
            [ 'label'=>'Wizard Setup',            'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-wizard'),                 'keywords'=>['wizard','setup','configure','start'] ],
            [ 'label'=>'Banner Settings',         'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=banner'),    'keywords'=>['banner','cookie','settings','appearance'] ],
            [ 'label'=>'Consent Logs',            'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=logs'),      'keywords'=>['log','logs','consent','history'] ],
            [ 'label'=>'Languages',               'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=languages'), 'keywords'=>['language','i18n','translations','localization'] ],
            [ 'label'=>'Advanced Settings',       'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=advanced'),  'keywords'=>['advanced','settings','config','technical'] ],
            [ 'label'=>'General Settings',        'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=general'),   'keywords'=>['general','basic','core','settings'] ],
            [ 'label'=>'Appearance',              'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=appearance'),'keywords'=>['appearance','styling','colors','fonts'] ],
            [ 'label'=>'Compliance',              'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=compliance'),'keywords'=>['compliance','gdpr','ccpa','lgpd'] ],
            [ 'label'=>'Security',                'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=security'),  'keywords'=>['security','privacy','protection'] ],
            [ 'label'=>'Performance',             'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=performance'),'keywords'=>['performance','caching','optimization'] ],
            [ 'label'=>'Integrations',            'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=integrations'),'keywords'=>['integrations','api','third-party'] ],
            [ 'label'=>'Backup & Restore',        'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=backup'),    'keywords'=>['backup','restore','export','import'] ],
            [ 'label'=>'Review Reports',          'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=reports'),   'keywords'=>['report','reports','review','analytics'] ],
            [ 'label'=>__('Learn more','cookiejar'),          'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=documentation'),   'keywords'=>['docs','license'] ],
            [ 'label'=>'Settings Summary',        'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=settings'),  'keywords'=>['settings','summary','overview'] ],
            [ 'label'=>'Community',               'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=community'), 'keywords'=>['community','social','connect'] ],
            [ 'label'=>'Help & Support',          'type'=>'goto',   'url'=>admin_url('admin.php?page=cookiejar-control#page=support'),   'keywords'=>['help','support','assistance'] ],
            [ 'label'=>'Export Basic Report',     'type'=>'goto',   'url'=>wp_nonce_url(admin_url('admin-ajax.php?action=cookiejar_export_basic_report'), 'cookiejar_export'), 'keywords'=>['export','report','download','csv'] ],
        ];

        wp_localize_script('cookiejar-admin','COOKIEJAR_ADMIN',[
            'ajaxurl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('cookiejar_admin'),
            'routes'        => $routes,
            'pages'         => $pages,
            'controlUrl'    => admin_url('admin.php?page=cookiejar-control'),
            'geoActive'     => (get_option('cookiejar_geo_auto','yes')==='yes') ? 1 : 0,
            'bannerEnabled' => (get_option('dwic_banner_enabled','1')==='1') ? 1 : 0,
            'gdprEnabled'   => (get_option('cookiejar_gdpr_mode','yes')==='yes') ? 1 : 0,
            'ccpaEnabled'   => (get_option('cookiejar_ccpa_mode','yes')==='yes') ? 1 : 0,
            'tier'          => cookiejar_get_tier(),
            'isPro'         => cookiejar_is_pro(),
            'wizardDone'    => get_option('cookiejar_wizard_done', 'no') === 'yes',
            'icons'   => [
                'cookie'    => \DWIC_ICON_URL,
                'search'    => \DWIC_URL.'assets/icon/cookiejar.icn.svg',
                'logo'      => \DWIC_URL.'assets/img/cookiejar-admin.svg',
                'gif'       => \DWIC_URL.'assets/icon/cookiejar.icn.svg',
                'watermark' => \DWIC_URL.'assets/img/cookiejar.png',
                'titleSvg'  => \DWIC_URL.'assets/icon/cookiejar.icn.svg'
            ]
        ]);
    }

    // Apply modifications ONLY to secondary (non-CookieJar) admin pages
    public function print_global_admin_bg(){ /* removed */ }

    public function page_dashboard(){
        ?>
        <div class="wrap cookiejar-wrap cookiejar-screen-dashboard">

          <div class="cookiejar-toolbar" role="toolbar" aria-label="<?php esc_attr_e('Quick navigation','cookiejar');?>">
            <button class="cookiejar-pill cookiejar-pill-link" type="button" data-page="banner"><?php esc_html_e('Banner Settings','cookiejar');?></button>
            <button class="cookiejar-pill cookiejar-pill-link" type="button" data-page="general"><?php esc_html_e('General Settings','cookiejar');?></button>
            <button class="cookiejar-pill cookiejar-pill-link" type="button" data-page="compliance"><?php esc_html_e('Compliance','cookiejar');?></button>
            <button class="cookiejar-pill cookiejar-pill-link" type="button" data-page="logs"><?php esc_html_e('Consent Log','cookiejar');?></button>
            <button class="cookiejar-pill cookiejar-pill-link" type="button" data-page="languages"><?php esc_html_e('Languages','cookiejar');?></button>
            <button class="cookiejar-pill cookiejar-pill-link" type="button" data-page="advanced"><?php esc_html_e('Advanced Settings','cookiejar');?></button>
            <?php if (!cookiejar_is_pro()): ?>
              <button class="cookiejar-pill cookiejar-pill-link" type="button" data-page="documentation"><?php esc_html_e('Documentation','cookiejar');?></button>
            <?php endif; ?>
            

            <div class="cookiejar-search" data-topics="">
              <input
                type="search"
                class="cookiejar-search-input"
                aria-label="<?php esc_attr_e('Search','cookiejar');?>"
                autocomplete="off"
                inputmode="search"
                enterkeyhint="search"
                spellcheck="false"
                role="combobox"
                aria-autocomplete="list"
                aria-expanded="false"
              >
              <button type="button" class="cookiejar-search-btn" aria-label="<?php esc_attr_e('Run search','cookiejar');?>">
                <img class="cookiejar-search-icon" src="<?php echo esc_url( DWIC_URL . 'assets/img/searchbar.icon_.cookie.jar_.gif' );?>" alt="">
              </button>
              <div class="cookiejar-search-suggestions" role="listbox" aria-label="<?php esc_attr_e('Search suggestions','cookiejar');?>" style="display:none;"></div>
            </div>

            <img class="cookiejar-logo" src="<?php echo esc_url( DWIC_URL . 'assets/img/Cookie.Jar_.header.png' );?>" alt="CookieJar">
          </div>

          <div class="cookiejar-grid">
            <!-- Section A -->
            <section class="cookiejar-card" aria-labelledby="cookiejar-sec-a-title">
              <div class="cookiejar-card-head">
                <h2 id="cookiejar-sec-a-title" class="cookiejar-section-title-bakery"><?php esc_html_e('Banner Status & Compliance','cookiejar');?></h2>
              </div>

              <div class="cookiejar-map" aria-label="<?php esc_attr_e('World map with recent activity markers','cookiejar');?>">
                <img src="<?php echo esc_url( DWIC_URL . 'assets/img/Cookie.Jar_.Map_.png' );?>" alt="<?php esc_attr_e('World map','cookiejar');?>">
                <canvas class="cookiejar-map-canvas" aria-hidden="true"></canvas>
                <div class="cookiejar-map-tip" role="tooltip" style="display:none;"></div>
              </div>

              <div class="cookiejar-status-panel">
                <div class="cookiejar-status-item" id="cookiejar-status-banner">
                  <span class="cookiejar-dot cookiejar-dot--red"></span>
                  <span class="cookiejar-status-text">Banner: Not Active</span>
                </div>
                <div class="cookiejar-status-item" id="cookiejar-status-geo">
                  <span class="cookiejar-dot cookiejar-dot--blue"></span>
                  <span class="cookiejar-status-text">Geo-location: Active</span>
                </div>
                <div class="cookiejar-status-item" id="cookiejar-status-gdpr">
                  <span class="cookiejar-dot cookiejar-dot--red"></span>
                  <span class="cookiejar-status-text">GDPR: Not Active</span>
                </div>
                <div class="cookiejar-status-item" id="cookiejar-status-ccpa">
                  <span class="cookiejar-dot cookiejar-dot--red"></span>
                  <span class="cookiejar-status-text">CCPA: Not Active</span>
                </div>
              </div>
            </section>

            <!-- Section B -->
            <section class="cookiejar-card" aria-labelledby="cookiejar-sec-b-title">
              <div class="cookiejar-card-head">
                <h2 id="cookiejar-sec-b-title" class="cookiejar-section-title-bakery"><?php esc_html_e('Consent Distribution: Quickview','cookiejar');?></h2>
              </div>

              <div class="cookiejar-quickview">
                <div class="cookiejar-donut" role="img" aria-label="<?php esc_attr_e('Consent distribution donut chart','cookiejar');?>">
                  <svg viewBox="-100 -100 200 200" width="220" height="220" aria-hidden="false">
                    <circle r="70" fill="none" stroke="#E2E8F0" stroke-width="14"></circle>
                    <circle class="arc arc-accept"  r="70" fill="none" stroke-width="14" stroke-linecap="round"
                      stroke-dasharray="0 439.822971502571" stroke-dashoffset="439.822971502571"
                      transform="rotate(-90)"></circle>
                    <circle class="arc arc-partial" r="70" fill="none" stroke-width="14" stroke-linecap="round"
                      stroke-dasharray="0 439.822971502571" stroke-dashoffset="439.822971502571"
                      transform="rotate(-90)"></circle>
                    <circle class="arc arc-reject"  r="70" fill="none" stroke-width="14" stroke-linecap="round"
                      stroke-dasharray="0 439.822971502571" stroke-dashoffset="439.822971502571"
                      transform="rotate(-90)"></circle>
                    <circle class="arc arc-unres"   r="70" fill="none" stroke-width="14" stroke-linecap="round"
                      stroke-dasharray="439.822971502571 0" stroke-dashoffset="439.822971502571"
                      transform="rotate(-90)"></circle>
                    <g transform="rotate(0)">
                      <circle r="46" fill="#FFFFFF" stroke="#E2E8F0"></circle>
                      <text class="cookiejar-center-val" x="0" y="-2" text-anchor="middle"
                        font-size="22" font-weight="700">0%</text>
                      <text class="cookiejar-center-label" x="0" y="16" text-anchor="middle" font-size="12"><?php esc_html_e('Accept','cookiejar');?></text>
                    </g>
                  </svg>
                </div>

                <div class="cookiejar-legend">
                  <div><span class="cookiejar-legend-dot cookiejar-accept"></span><span><?php esc_html_e('Accept','cookiejar');?> <b class="cookiejar-legend-pct cookiejar-accept-p">--%</b></span></div>
                  <div><span class="cookiejar-legend-dot cookiejar-partial"></span><span><?php esc_html_e('Partial','cookiejar');?> <b class="cookiejar-legend-pct cookiejar-partial-p">--%</b></span></div>
                  <div><span class="cookiejar-legend-dot cookiejar-reject"></span><span><?php esc_html_e('Reject','cookiejar');?> <b class="cookiejar-legend-pct cookiejar-reject-p">--%</b></span></div>
                  <div><span class="cookiejar-legend-dot cookiejar-unres"></span><span><?php esc_html_e('Unresolved','cookiejar');?> <b class="cookiejar-legend-pct cookiejar-unres-p">--%</b></span></div>
                </div>
              </div>

              <div class="cookiejar-quick-kpis" role="region" aria-label="<?php esc_attr_e('Quick KPIs','cookiejar');?>">
                <div class="cookiejar-quick-kpi">
                  <div class="cookiejar-kpi-label"><?php esc_html_e('Total Cookies','cookiejar');?></div>
                  <div class="cookiejar-kpi-value" data-qkpi="cookies">--</div>
                </div>
                <div class="cookiejar-quick-kpi">
                  <div class="cookiejar-kpi-label"><?php esc_html_e('Page Views','cookiejar');?></div>
                  <div class="cookiejar-kpi-value" data-qkpi="pageviews">--</div>
                </div>
                <div class="cookiejar-quick-kpi">
                  <div class="cookiejar-kpi-label"><?php esc_html_e('Conversion','cookiejar');?></div>
                  <div class="cookiejar-kpi-value" data-qkpi="conversion">--</div>
                </div>
                <div class="cookiejar-quick-kpi">
                  <div class="cookiejar-kpi-label"><?php esc_html_e('Avg. Decision Time','cookiejar');?></div>
                  <div class="cookiejar-kpi-value" data-qkpi="avgTime">--</div>
                </div>
              </div>

            </section>
          </div>

          <!-- Section C -->
          <div class="cookiejar-card cookiejar-wide">
            <div class="cookiejar-card-head"></div>

            <div class="cookiejar-trend-grid">
              <!-- Activity Table -->
              <div class="cookiejar-activity">
                <h2 id="cookiejar-sec-c-title" class="cookiejar-section-title-bakery">Recent&nbsp;Activity</h2>

                <div class="cookiejar-table" role="table" aria-label="Recent consent activity">
                  <div class="cookiejar-thead" role="rowgroup">
                    <div class="cookiejar-tr" role="row">
                      <div class="cookiejar-th" role="columnheader">IP Address</div>
                      <div class="cookiejar-th" role="columnheader">Country</div>
                      <div class="cookiejar-th" role="columnheader">Consent</div>
                      <div class="cookiejar-th" role="columnheader">Date</div>
                      <div class="cookiejar-th" role="columnheader">Time</div>
                    </div>
                  </div>
                  <div class="cookiejar-tbody" role="rowgroup" id="cookiejar-activity-body" aria-live="polite"></div>
                </div>

                <a class="cookiejar-link" href="#page=logs" data-page="logs">View more →</a>
              </div>

              <!-- Graph Section -->
              <div class="cookiejar-graph">
                <h2 id="cookiejar-sec-g-title" class="cookiejar-section-title-bakery">Traffic &amp; Consent Trend</h2>
                <div class="cookiejar-trend-controls" style="margin:8px 0 12px;display:flex;gap:8px;align-items:center;">
                  <span style="font-size:12px;color:#64748b;">Range:</span>
                  <button type="button" class="button button-small cookiejar-trend-range" data-days="7">7d</button>
                  <button type="button" class="button button-small cookiejar-trend-range" data-days="30">30d</button>
                  <button type="button" class="button button-small cookiejar-trend-range" data-days="90">90d</button>
                </div>
                <div class="cookiejar-trend-chart">
                  <div class="cookiejar-chart-placeholder is-empty" aria-live="polite"
                       style="--cookiejar-watermark: none;">
                    <span class="cookiejar-chart-note">No activity yet. Data will appear here when available.</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="cookiejar-footer-actions">
            <button type="button" class="cookiejar-pill cookiejar-pill-link" data-page="reports">Review Report</button>
            <button type="button" class="cookiejar-pill cookiejar-pill-link" data-page="logs">Generate Log/Report</button>
          </div>

          <p class="cookiejaropyright">
            CookieJar WordPress Plugin by
            <a href="<?php echo esc_url( admin_url('admin.php?page=cookiejar-control#page=documentation') );?>" rel="noopener noreferrer">
              <?php esc_html_e('Documentation','cookiejar');?>
            </a>
            Software Proprietary and the intellectual property of My Deme, Llc.
            <?php echo esc_html( gmdate('Y') ); ?> © All Rights Reserved.
          </p>
        </div>
        <?php
    }

    public function page_control_panel(){
        ?>
        <div class="wrap cookiejar-wrap cookiejar-screen-control">
          <div class="cookiejar-cp">
            <aside class="cookiejar-cp-sidebar">
              <img class="cookiejar-cp-logo" src="<?php echo esc_url( DWIC_URL . 'assets/img/Cookie.Jar_.header.png' );?>" alt="CookieJar">
              <div class="cookiejar-cp-search cookiejar-search" data-topics="">
                <input
                  type="search"
                  class="cookiejar-search-input"
                  aria-label="<?php esc_attr_e('Search','cookiejar');?>"
                  autocomplete="off"
                  inputmode="search"
                  enterkeyhint="search"
                  spellcheck="false"
                  role="combobox"
                  aria-autocomplete="list"
                  aria-expanded="false"
                >
                <button type="button" class="cookiejar-search-btn" aria-label="<?php esc_attr_e('Run search','cookiejar');?>">
                  <img class="cookiejar-search-icon" src="<?php echo esc_url( DWIC_URL . 'assets/img/searchbar.icon_.cookie.jar_.gif' );?>" alt="">
                </button>
                <div class="cookiejar-search-suggestions" role="listbox" aria-label="<?php esc_attr_e('Search suggestions','cookiejar');?>" style="display:none;"></div>
              </div>
              <nav class="cookiejar-cp-nav" aria-label="<?php esc_attr_e('CookieJar navigation','cookiejar');?>">
                <div class="cookiejar-nav-section"><?php esc_html_e('MAIN','cookiejar');?></div>
                <a href="<?php echo esc_url( admin_url('admin.php?page=cookiejar-dashboard') ); ?>" class="cookiejar-nav-link"><?php esc_html_e('Bakery Control Panel','cookiejar');?></a>
                <a href="#page=reports" class="cookiejar-nav-link" data-page="reports"><?php esc_html_e('Review Reports','cookiejar');?></a>
                <a href="#page=documentation" class="cookiejar-nav-link" data-page="documentation"><?php esc_html_e('Documentation','cookiejar');?></a>

                <div class="cookiejar-nav-section"><?php esc_html_e('MANAGE','cookiejar');?></div>
                <a href="<?php echo esc_url( admin_url('admin.php?page=cookiejar-wizard') ); ?>" class="cookiejar-nav-link"><?php esc_html_e('Wizard Setup','cookiejar');?></a>
                <a href="#page=banner" class="cookiejar-nav-link" data-page="banner"><?php esc_html_e('Cookie Banner Settings','cookiejar');?></a>
                <a href="#page=logs" class="cookiejar-nav-link" data-page="logs"><?php esc_html_e('Consent Logs','cookiejar');?></a>
                <a href="#page=languages" class="cookiejar-nav-link" data-page="languages"><?php esc_html_e('Languages','cookiejar');?></a>
                <a href="#page=advanced" class="cookiejar-nav-link" data-page="advanced"><?php esc_html_e('Advanced Settings','cookiejar');?></a>
                <a href="#page=settings" class="cookiejar-nav-link" data-page="settings"><?php esc_html_e('Settings','cookiejar');?></a>

                <div class="cookiejar-nav-section"><?php esc_html_e('SUPPORT','cookiejar');?></div>
                <a href="#page=community" class="cookiejar-nav-link" data-page="community"><?php esc_html_e('Community','cookiejar');?></a>
                <a href="#page=support" class="cookiejar-nav-link" data-page="support"><?php esc_html_e('Help & Support','cookiejar');?></a>
              </nav>
            </aside>

            <main class="cookiejar-cp-main" role="region" aria-label="<?php esc_attr_e('Admin analytics & controls','cookiejar');?>">
              <h2 class="cookiejar-section-title" id="cookiejar-cp-section-title"><?php esc_html_e('CookieJar Setup Wizard','cookiejar');?></h2>
              <div class="cookiejar-cp-surface">
                
                <!-- Dashboard Page -->
                <div id="page-dashboard" class="cookiejar-page">
                  <?php
                  // Get real-time data from backend
                  $stats = class_exists('\\DWIC\\DB') ? \DWIC\DB::get_stats() : [];
                  $recent_logs = class_exists('\\DWIC\\DB') ? \DWIC\DB::get_logs(10) : [];
                  $health_status = class_exists('\\DWIC\\Monitor') ? \DWIC\Monitor::get_health_status() : null;
                  $cache_stats = class_exists('\\DWIC\\Cache') ? \DWIC\Cache::stats() : [];
                  $config = class_exists('\\DWIC\\Config') ? \DWIC\Config::all() : [];
                  ?>
                  <div class="cookiejar-dashboard-overview">
                    <div class="cookiejar-stats-grid">
                      <div class="cookiejar-stat-card">
                        <h3><?php esc_html_e('Total Consents', 'cookiejar'); ?></h3>
                        <div class="cookiejar-stat-number"><?php echo esc_html($stats['total_consents'] ?? 0); ?></div>
                        <div class="cookiejar-stat-trend">
                          <?php 
                          $trend = $stats['consent_trend'] ?? 0;
                          $trend_class = $trend >= 0 ? 'positive' : 'negative';
                          $trend_icon = $trend >= 0 ? '↗' : '↘';
                          ?>
                          <span class="cookiejar-trend <?php echo esc_attr($trend_class); ?>">
                            <?php echo esc_html($trend_icon . abs($trend) . '%'); ?>
                          </span>
                      </div>
                    </div>
                      
                      <div class="cookiejar-stat-card">
                        <h3><?php esc_html_e('Compliance Rate', 'cookiejar'); ?></h3>
                        <div class="cookiejar-stat-number"><?php echo esc_html($stats['compliance_rate'] ?? 0); ?>%</div>
                        <div class="cookiejar-stat-description">
                          <?php esc_html_e('Users with valid consent', 'cookiejar'); ?>
                        </div>
                      </div>
                      
                      <div class="cookiejar-stat-card">
                        <h3><?php esc_html_e('Active Countries', 'cookiejar'); ?></h3>
                        <div class="cookiejar-stat-number"><?php echo esc_html($stats['active_countries'] ?? 0); ?></div>
                        <div class="cookiejar-stat-description">
                          <?php esc_html_e('Geographic coverage', 'cookiejar'); ?>
                        </div>
                      </div>
                      
                      
                    </div>
                    
                    <div class="cookiejar-dashboard-content">
                    <div class="cookiejar-card">
                        <h3><?php esc_html_e('Recent Consent Activity', 'cookiejar'); ?></h3>
                      <div id="cookiejar-recent-activity">
                          <?php if (!empty($recent_logs)): ?>
                            <div class="cookiejar-activity-list">
                              <?php foreach (array_slice($recent_logs, 0, 5) as $log): ?>
                                <div class="cookiejar-activity-item">
                                  <div class="cookiejar-activity-icon">
                                    <?php 
                                    $consent_type = $log['consent'] ?? 'none';
                                    $icon_class = 'consent-' . $consent_type;
                                    ?>
                                    <span class="cookiejar-consent-icon <?php echo esc_attr($icon_class); ?>">
                                      <?php echo esc_html(strtoupper(substr($consent_type, 0, 1))); ?>
                                    </span>
                      </div>
                                  <div class="cookiejar-activity-details">
                                    <div class="cookiejar-activity-action">
                                      <?php 
                                      $country = $log['country'] ?? 'Unknown';
                                      $consent = ucfirst($log['consent'] ?? 'none');
                                      /* translators: 1: consent decision (Accept/Partial/Reject), 2: country */
                                      printf(esc_html__('%1$s consent from %2$s', 'cookiejar'), esc_html($consent), esc_html($country));
                                      ?>
                                    </div>
                                    <div class="cookiejar-activity-time">
                                      <?php echo esc_html($log['created_at'] ?? 'Unknown time'); ?>
                                    </div>
                                  </div>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          <?php else: ?>
                            <p><?php esc_html_e('No recent consent activity found.', 'cookiejar'); ?></p>
                          <?php endif; ?>
                        </div>
                      </div>
                      
                      <div class="cookiejar-card">
                        <h3><?php esc_html_e('Quick Actions', 'cookiejar'); ?></h3>
                        <div class="cookiejar-quick-actions">
                          <button type="button" class="button button-primary" onclick="location.href='#page=banner'">
                            <?php esc_html_e('Configure Banner', 'cookiejar'); ?>
                          </button>
                          <button type="button" class="button" onclick="location.href='#page=logs'">
                            <?php esc_html_e('View Logs', 'cookiejar'); ?>
                          </button>
                          <button type="button" class="button" onclick="location.href='#page=reports'">
                            <?php esc_html_e('Generate Report', 'cookiejar'); ?>
                          </button>
                          
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Wizard Setup Page -->
                <div id="page-wizard" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-wizard-overview">
                    <div class="cookiejar-card">
                      <h3><?php esc_html_e('CookieJar Setup Wizard','cookiejar');?></h3>
                      <p><?php esc_html_e('Configure your cookie consent solution in just a few steps. Choose your setup level below.','cookiejar');?></p>
                      
                      <!-- Wizard Mode Selection -->
                      <div class="cookiejar-wizard-mode-selection" id="wizard-mode-selection">
                        <div class="cookiejar-mode-options">
                          <div class="cookiejar-mode-option">
                            <div class="cookiejar-mode-header">
                              <h4><?php esc_html_e('Basic Setup','cookiejar');?></h4>
                              <span class="cookiejar-mode-badge cookiejar-badge-basic"><?php esc_html_e('Recommended for Beginners','cookiejar');?></span>
                            </div>
                            <p><?php esc_html_e('Quick and easy setup with essential options. Perfect for getting started quickly.','cookiejar');?></p>
                            <ul class="cookiejar-mode-features">
                              <li><?php esc_html_e('Auto-detect language by region','cookiejar');?></li>
                              <li><?php esc_html_e('Essential cookie categories','cookiejar');?></li>
                              <li><?php esc_html_e('Basic appearance settings','cookiejar');?></li>
                              <li><?php esc_html_e('GDPR compliance setup','cookiejar');?></li>
                            </ul>
                            <button type="button" class="button button-primary" id="start-basic-wizard">
                              <?php esc_html_e('Start Basic Setup','cookiejar');?>
                            </button>
                          </div>
                          
                          <div class="cookiejar-mode-option">
                            <div class="cookiejar-mode-header">
                              <h4><?php esc_html_e('Advanced Setup','cookiejar');?></h4>
                              <span class="cookiejar-mode-badge cookiejar-badge-advanced"><?php esc_html_e('For Power Users','cookiejar');?></span>
                            </div>
                            <p><?php esc_html_e('Comprehensive setup with all available options and advanced configurations.','cookiejar');?></p>
                            <ul class="cookiejar-mode-features">
                              <li><?php esc_html_e('Multiple language configuration','cookiejar');?></li>
                              <li><?php esc_html_e('Custom cookie categories','cookiejar');?></li>
                              <li><?php esc_html_e('Advanced styling options','cookiejar');?></li>
                              <li><?php esc_html_e('Multi-region compliance (GDPR, CCPA, LGPD)','cookiejar');?></li>
                              <li><?php esc_html_e('Performance & security settings','cookiejar');?></li>
                            </ul>
                            <button type="button" class="button button-secondary" id="start-advanced-wizard">
                              <?php esc_html_e('Start Advanced Setup','cookiejar');?>
                            </button>
                          </div>
                        </div>
                        
                        <div class="cookiejar-wizard-skip-options">
                          <p><?php esc_html_e('Not ready to set up now?','cookiejar');?></p>
                          <button type="button" class="button" id="skip-wizard">
                            <?php esc_html_e('Skip Wizard & Use Defaults','cookiejar');?>
                          </button>
                          <button type="button" class="button cookiejar-nav-link" data-page="settings">
                            <?php esc_html_e('Go to Manual Settings','cookiejar');?>
                          </button>
                        </div>
                      </div>
                      
                      <!-- Basic Wizard Flow -->
                      <div class="cookiejar-wizard-flow" id="basic-wizard-flow" style="display:none;">
                        <div class="cookiejar-wizard-header">
                          <h3><?php esc_html_e('Basic Setup Wizard','cookiejar');?></h3>
                          <button type="button" class="button cookiejar-wizard-cancel" id="cancel-basic-wizard">
                            <?php esc_html_e('Cancel & Exit','cookiejar');?>
                          </button>
                        </div>
                        
                        <div class="cookiejar-wizard-progress">
                          <div class="progress-bar">
                            <div class="progress-fill" id="basic-wizard-progress" style="width: 25%;"></div>
                          </div>
                          <div class="progress-steps">
                            <span class="step active" data-step="1"><?php esc_html_e('Language','cookiejar');?></span>
                            <span class="step" data-step="2"><?php esc_html_e('Categories','cookiejar');?></span>
                            <span class="step" data-step="3"><?php esc_html_e('Appearance','cookiejar');?></span>
                            <span class="step" data-step="4"><?php esc_html_e('Compliance','cookiejar');?></span>
                          </div>
                        </div>
                        
                        <form id="basic-wizard-form">
                          <!-- Basic Step 1: Auto-detect Language -->
                          <div class="wizard-step active" id="basic-step-1">
                            <h4><?php esc_html_e('Step 1: Language Configuration','cookiejar');?></h4>
                            <p><?php esc_html_e('We\'ll auto-detect your region and suggest the best language settings.','cookiejar');?></p>
                            
                            <div class="cookiejar-auto-detect-section">
                              <div class="cookiejar-detected-info" id="detected-language-info">
                                <div class="cookiejar-detection-status">
                                  <span class="cookiejar-detect-icon">🌍</span>
                                  <div class="cookiejar-detect-details">
                                    <strong><?php esc_html_e('Detected Region:','cookiejar');?></strong> <span id="detected-region"><?php esc_html_e('Detecting...','cookiejar');?></span><br>
                                    <strong><?php esc_html_e('Suggested Language:','cookiejar');?></strong> <span id="detected-language"><?php esc_html_e('Detecting...','cookiejar');?></span>
                                  </div>
                                </div>
                              </div>
                              
                              <div class="form-group">
                                <label for="basic-primary-language"><?php esc_html_e('Primary Language','cookiejar');?></label>
                                <select id="basic-primary-language" name="primary_language" class="regular-text">
                                  <option value="en"><?php esc_html_e('English','cookiejar');?></option>
                                  <option value="es"><?php esc_html_e('Spanish (Español)','cookiejar');?></option>
                                  <option value="fr"><?php esc_html_e('French (Français)','cookiejar');?></option>
                                  <option value="de"><?php esc_html_e('German (Deutsch)','cookiejar');?></option>
                                  <option value="it"><?php esc_html_e('Italian (Italiano)','cookiejar');?></option>
                                  <option value="pt"><?php esc_html_e('Portuguese (Português)','cookiejar');?></option>
                                  <option value="nl"><?php esc_html_e('Dutch (Nederlands)','cookiejar');?></option>
                                  <option value="pl"><?php esc_html_e('Polish (Polski)','cookiejar');?></option>
                                  <option value="ru"><?php esc_html_e('Russian (Русский)','cookiejar');?></option>
                                  <option value="zh"><?php esc_html_e('Chinese (中文)','cookiejar');?></option>
                                  <option value="ja"><?php esc_html_e('Japanese (日本語)','cookiejar');?></option>
                                  <option value="ko"><?php esc_html_e('Korean (한국어)','cookiejar');?></option>
                                </select>
                              </div>
                              
                              <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                            </div>
                          </div>
                          
                          <div class="wizard-navigation">
                            <button type="button" id="basic-wizard-prev" class="button" style="display:none;">
                              <?php esc_html_e('Previous','cookiejar');?>
                            </button>
                            <button type="button" id="basic-wizard-next" class="button button-primary">
                              <?php esc_html_e('Next','cookiejar');?>
                            </button>
                            <button type="button" id="basic-wizard-finish" class="button button-primary" style="display:none;">
                              <?php esc_html_e('Complete Setup','cookiejar');?>
                            </button>
                            <button type="button" id="basic-wizard-skip-step" class="button button-link">
                              <?php esc_html_e('Skip This Step','cookiejar');?>
                            </button>
                          </div>
                        </form>
                      </div>
                      
                      <div class="wizard-status" id="wizard-status" style="display:none;"></div>
                    </div>
                  </div>
                </div>

                <!-- Banner Settings Page -->
                <div id="page-banner" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-settings-overview">
                  <div class="cookiejar-card">
                    <h3><?php esc_html_e('Cookie Banner Settings','cookiejar');?></h3>
                      <p><?php esc_html_e('Select options, modify toggles, and defaults for your cookie consent banner.','cookiejar');?></p>
                      
                      <div class="cookiejar-settings-accordion">
                        <h4><?php esc_html_e('Settings Categories','cookiejar');?></h4>
                        
                        <div class="cookiejar-accordion-item">
                          <div class="cookiejar-accordion-header" data-target="cookie-banner-settings">
                            <h5><?php esc_html_e('Cookie Banner Settings','cookiejar');?></h5>
                            <span class="cookiejar-accordion-toggle">▼</span>
                          </div>
                          <div class="cookiejar-accordion-content" id="cookie-banner-settings">
                            <p><?php esc_html_e('Configure the main cookie consent banner appearance and behavior.','cookiejar');?></p>
                            
                            <form class="cookiejar-settings-form" id="banner-settings-form">
                              <div class="form-group">
                                <label for="banner-enabled"><?php esc_html_e('Enable Banner', 'cookiejar'); ?></label>
                                <select id="banner-enabled" name="banner_enabled">
                                  <option value="yes" selected><?php esc_html_e('Yes', 'cookiejar'); ?></option>
                                  <option value="no"><?php esc_html_e('No', 'cookiejar'); ?></option>
                            </select>
                                <p class="description"><?php esc_html_e('Show cookie consent banner to visitors', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label for="banner-position"><?php esc_html_e('Banner Position', 'cookiejar'); ?></label>
                                <select id="banner-position" name="banner_position">
                                  <option value="bottom" selected><?php esc_html_e('Bottom', 'cookiejar'); ?></option>
                                  <option value="top"><?php esc_html_e('Top', 'cookiejar'); ?></option>
                                  <option value="center"><?php esc_html_e('Center Modal', 'cookiejar'); ?></option>
                            </select>
                                <p class="description"><?php esc_html_e('Where to display the banner on the page', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label for="banner-text"><?php esc_html_e('Banner Text', 'cookiejar'); ?></label>
                                <textarea id="banner-text" name="banner_text" rows="3" class="large-text" 
                                          placeholder="<?php esc_attr_e('We use cookies to enhance your experience...', 'cookiejar'); ?>"></textarea>
                                <p class="description"><?php esc_html_e('Customize the message shown to visitors', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="wizard-navigation">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Banner Settings', 'cookiejar'); ?></button>
                                <a href="#page=banner" class="button cookiejar-nav-link" data-page="banner"><?php esc_html_e('Advanced Banner Settings', 'cookiejar'); ?></a>
                              </div>
                            </form>
                          </div>
                        </div>

                        <div class="cookiejar-accordion-item">
                          <div class="cookiejar-accordion-header" data-target="general-settings">
                            <h5><?php esc_html_e('General Settings','cookiejar');?></h5>
                            <span class="cookiejar-accordion-toggle">▼</span>
                          </div>
                          <div class="cookiejar-accordion-content" id="general-settings">
                            <p><?php esc_html_e('Basic configuration including consent duration, storage mode, and anonymization options.','cookiejar');?></p>
                            
                            <form class="cookiejar-settings-form" id="general-settings-form">
                              <div class="form-group">
                                <label for="consent-duration"><?php esc_html_e('Consent Duration (days)', 'cookiejar'); ?></label>
                                <input type="number" id="consent-duration" name="consent_duration" value="180" min="1" max="180">
                                <p class="description"><?php esc_html_e('How long consent is valid (max 180 days in Basic)', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label for="storage-mode"><?php esc_html_e('Storage Mode', 'cookiejar'); ?></label>
                                <select id="storage-mode" name="storage_mode">
                                  <option value="hash" selected><?php esc_html_e('Hash (Anonymous)', 'cookiejar'); ?></option>
                                  <option value="anonymize"><?php esc_html_e('Anonymize', 'cookiejar'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('How to store consent data', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                            <label>
                                  <input type="checkbox" name="geo_auto" value="1" checked>
                                  <?php esc_html_e('Enable automatic geotargeting', 'cookiejar'); ?>
                                  <span class="description"><?php esc_html_e('Automatically detect user location for GDPR/CCPA compliance', 'cookiejar'); ?></span>
                            </label>
                              </div>

                              <div class="form-group">
                                <?php $auto = get_option('cookiejar_auto_updates', 'no'); ?>
                                <label>
                                  <input type="checkbox" name="auto_updates" value="1" <?php checked($auto, 'yes'); ?>>
                                  <?php esc_html_e('Enable automatic plugin updates', 'cookiejar'); ?>
                                  <span class="description"><?php esc_html_e('When enabled, WordPress will auto-install CookieJar updates as they become available.', 'cookiejar'); ?></span>
                                </label>
                              </div>
                              
                              <div class="wizard-navigation">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save General Settings', 'cookiejar'); ?></button>
                                <a href="#page=general" class="button cookiejar-nav-link" data-page="general"><?php esc_html_e('Advanced General Settings', 'cookiejar'); ?></a>
                              </div>
                            </form>
                          </div>
                        </div>

                        <div class="cookiejar-accordion-item">
                          <div class="cookiejar-accordion-header" data-target="appearance-settings" aria-expanded="true">
                            <h5><?php esc_html_e('Appearance','cookiejar');?></h5>
                            <span class="cookiejar-accordion-toggle">▲</span>
                          </div>
                          <div class="cookiejar-accordion-content" id="appearance-settings" style="display:block;">
                            <p><?php esc_html_e('Customize banner styling including colors, fonts, and visual themes.','cookiejar');?></p>
                            
                            <form class="cookiejar-settings-form" id="appearance-settings-form">
                              <div class="form-group">
                                <label for="banner-color"><?php esc_html_e('Primary Color', 'cookiejar'); ?></label>
                                <div class="cookiejar-color-edit-wrap">
                                  <input type="color" id="banner-color" name="banner_color" value="#008ed6" class="cookiejar-color-palette">
                                  <input type="text" id="banner-color-text" value="#008ed6" class="cookiejar-color-text" placeholder="#008ed6">
                                </div>
                                <p class="description"><?php esc_html_e('Primary color for buttons and accents', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label for="banner-bg"><?php esc_html_e('Background Color', 'cookiejar'); ?></label>
                                <div class="cookiejar-color-edit-wrap">
                                  <input type="color" id="banner-bg" name="banner_bg" value="#ffffff" class="cookiejar-color-palette">
                                  <input type="text" id="banner-bg-text" value="#ffffff" class="cookiejar-color-text" placeholder="#ffffff">
                                </div>
                                <p class="description"><?php esc_html_e('Background color for the banner', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label for="banner-prompt"><?php esc_html_e('Custom Prompt Text', 'cookiejar'); ?></label>
                                <textarea id="banner-prompt" name="banner_prompt" rows="3" class="large-text" 
                                          placeholder="<?php esc_attr_e('We use cookies to enhance your experience...', 'cookiejar'); ?>"></textarea>
                                <p class="description"><?php esc_html_e('Customize the message shown to visitors', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="wizard-navigation">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Appearance', 'cookiejar'); ?></button>
                                <a href="#page=appearance" class="button cookiejar-nav-link" data-page="appearance"><?php esc_html_e('Advanced Appearance Settings','cookiejar');?></a>
                              </div>
                            </form>
                          </div>
                        </div>
                        
                        <div class="cookiejar-accordion-item">
                          <div class="cookiejar-accordion-header" data-target="compliance-settings" aria-expanded="true">
                            <h5><?php esc_html_e('Compliance','cookiejar');?></h5>
                            <span class="cookiejar-accordion-toggle">▲</span>
                          </div>
                          <div class="cookiejar-accordion-content" id="compliance-settings" style="display:block;">
                            <p><?php esc_html_e('Configure compliance with privacy regulations (GDPR, CCPA, LGPD).','cookiejar');?></p>
                            
                            <form class="cookiejar-settings-form" id="compliance-settings-form">
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="gdpr_mode" value="1" checked>
                                  <?php esc_html_e('Enable GDPR compliance mode', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('European General Data Protection Regulation', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="ccpa_mode" value="1" <?php echo $is_pro ? '' : 'disabled'; ?>>
                                  <?php esc_html_e('Enable CCPA compliance mode', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('California Consumer Privacy Act', 'cookiejar'); ?></p>
                                <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                              </div>
                              
                              <div class="form-group">
                                <label for="policy-page"><?php esc_html_e('Privacy Policy Page', 'cookiejar'); ?></label>
                                <select id="policy-page" name="policy_url" class="regular-text">
                                  <option value="">— <?php esc_html_e('Select a page', 'cookiejar'); ?> —</option>
                                  <?php 
                                  // Get WordPress pages for policy dropdown
                                  $pages = get_pages([
                                    'post_type' => 'page',
                                    'post_status' => 'any',
                                    'number' => 0,
                                    'sort_column' => 'post_title',
                                    'sort_order' => 'ASC'
                                  ]);
                                  
                                  if (!empty($pages)) {
                                    foreach ($pages as $page) {
                                      $url = get_permalink($page->ID);
                                      echo '<option value="' . esc_url($url) . '">' . esc_html($page->post_title) . '</option>';
                                    }
                                  } else {
                                    echo '<option value="" disabled>' . esc_html__('No pages found. Please create a page first.', 'cookiejar') . '</option>';
                                  }
                                  ?>
                                </select>
                                <p class="description"><?php esc_html_e('Choose a WordPress page to use as your privacy policy', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="wizard-navigation">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Compliance', 'cookiejar'); ?></button>
                                <a href="#page=compliance" class="button cookiejar-nav-link" data-page="compliance"><?php esc_html_e('Advanced Compliance Settings','cookiejar');?></a>
                              </div>
                    </form>
                          </div>
                        </div>
                        
                        <div class="cookiejar-accordion-item">
                          <div class="cookiejar-accordion-header" data-target="cookie-categories" aria-expanded="true">
                            <h5><?php esc_html_e('Cookie Categories','cookiejar');?></h5>
                            <span class="cookiejar-accordion-toggle">▲</span>
                          </div>
                          <div class="cookiejar-accordion-content" id="cookie-categories" style="display:block;">
                            <p><?php esc_html_e('Choose which cookie categories to include in your consent banner.','cookiejar');?></p>
                            
                            <form class="cookiejar-settings-form" id="categories-settings-form">
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
                                    <input type="checkbox" name="categories[]" value="advertising" <?php echo $is_pro ? '' : 'disabled'; ?>>
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
                                <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                              </div>
                              
                              <div class="wizard-navigation">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Categories', 'cookiejar'); ?></button>
                                <a href="#page=categories" class="button cookiejar-nav-link" data-page="categories"><?php esc_html_e('Manage Categories','cookiejar');?></a>
                              </div>
                            </form>
                          </div>
                        </div>
                        
                        <div class="cookiejar-accordion-item">
                          <div class="cookiejar-accordion-header" data-target="languages-settings" aria-expanded="true">
                            <h5><?php esc_html_e('Languages','cookiejar');?></h5>
                            <span class="cookiejar-accordion-toggle">▲</span>
                          </div>
                          <div class="cookiejar-accordion-content" id="languages-settings" style="display:block;">
                            <p><?php esc_html_e('Choose the languages for your consent banner. Language names are shown in their native form.','cookiejar');?></p>
                            
                            <form class="cookiejar-settings-form" id="languages-settings-form">
                              <div class="form-group">
                                <fieldset>
                                  <legend class="screen-reader-text"><?php esc_html_e('Select languages', 'cookiejar'); ?></legend>
                                  <div class="cookiejar-language-grid">
                                    <?php 
                                    // Define available languages (matching wizard)
                                    $LANGUAGES = [
                                      'en_US' => 'English (US)',
                                      'en_GB' => 'English (UK)', 
                                      'es_ES' => 'Español',
                                      'fr_FR' => 'Français',
                                      'de_DE' => 'Deutsch',
                                      'it_IT' => 'Italiano',
                                      'pt_PT' => 'Português',
                                      'nl_NL' => 'Nederlands',
                                      'pl_PL' => 'Polski',
                                      'ru_RU' => 'Русский',
                                      'ja_JP' => '日本語',
                                      'ko_KR' => '한국어',
                                      'zh_CN' => '中文 (简体)',
                                      'zh_TW' => '中文 (繁體)',
                                      'ar_SA' => 'العربية',
                                      'hi_IN' => 'हिन्दी'
                                    ];
                                    
                                    $savedLangs = $config['supported_languages'] ?? ['en_US'];
                                    
                                    foreach ($LANGUAGES as $code => $name): 
                                      $checked = in_array($code, $savedLangs, true) ? 'checked' : '';
                                    ?>
                                      <label class="cookiejar-language-option">
                                        <input type="checkbox"
                                               class="wizard-language-checkbox"
                                               name="languages[]"
                                               value="<?php echo esc_attr($code); ?>"
                                               <?php echo esc_attr($checked); ?>
                                        />
                                        <span class="label-text"><?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)</span>
                                      </label>
                                    <?php endforeach; ?>
                                  </div>
                                  <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                                </fieldset>
                              </div>
                              
                              <div class="wizard-navigation">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Languages', 'cookiejar'); ?></button>
                                <a href="#page=languages" class="button cookiejar-nav-link" data-page="languages"><?php esc_html_e('Configure Languages','cookiejar');?></a>
                              </div>
                            </form>
                          </div>
                        </div>
                        
                        <div class="cookiejar-accordion-item">
                          <div class="cookiejar-accordion-header" data-target="analytics-settings" aria-expanded="true">
                            <h5><?php esc_html_e('Analytics','cookiejar');?></h5>
                            <span class="cookiejar-accordion-toggle">▲</span>
                          </div>
                          <div class="cookiejar-accordion-content" id="analytics-settings" style="display:block;">
                            <p><?php esc_html_e('Configure tracking and logging options for consent analytics.','cookiejar');?></p>
                            
                            <form class="cookiejar-settings-form" id="analytics-settings-form">
                              <div class="form-group">
                                <label for="logging-mode"><?php esc_html_e('Logging Mode', 'cookiejar'); ?></label>
                                <select id="logging-mode" name="logging_mode" <?php echo !$is_pro ? 'disabled' : ''; ?>>
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
                                <label for="google-analytics-id">
                                  <?php esc_html_e('Google Analytics ID', 'cookiejar'); ?>
                                  <span class="cookiejar-tooltip" title="<?php esc_attr_e('Integrate with Google Analytics (UA-XXXXX or G-XXXXXX).', 'cookiejar'); ?>">?</span>
                                </label>
                                <input type="text" name="google_analytics_id" id="google-analytics-id" value="" class="regular-text" aria-label="<?php esc_attr_e('Google Analytics ID', 'cookiejar'); ?>">
                                <p class="description"><?php esc_html_e('Add your analytics ID for tracking consent and conversions.', 'cookiejar'); ?></p>
                                <a href="https://support.google.com/analytics/answer/9539598" target="_blank" class="cookiejar-link"><?php esc_html_e('Get Tracking ID', 'cookiejar'); ?></a>
                              </div>
                              
                              <div class="wizard-navigation">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Analytics', 'cookiejar'); ?></button>
                                <a href="#page=analytics" class="button cookiejar-nav-link" data-page="analytics"><?php esc_html_e('Configure Analytics','cookiejar');?></a>
                              </div>
                            </form>
                          </div>
                        </div>
                        
                        <div class="cookiejar-accordion-item">
                          <div class="cookiejar-accordion-header" data-target="security-settings">
                            <h5><?php esc_html_e('Security','cookiejar');?></h5>
                            <span class="cookiejar-accordion-toggle">▼</span>
                          </div>
                          <div class="cookiejar-accordion-content" id="security-settings">
                            <p><?php esc_html_e('Configure security and privacy protection settings for your CookieJar installation.','cookiejar');?></p>
                            
                            <form class="cookiejar-settings-form" id="security-settings-form">
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="strict_privacy" value="1" <?php checked($config['strict_privacy'] ?? 'no', 'yes'); ?>>
                                  <?php esc_html_e('Enable Strict Privacy Mode', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Enhanced privacy protection with minimal data collection', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="anonymize_ips" value="1" <?php checked($config['anonymize_ips'] ?? 'yes', 'yes'); ?>>
                                  <?php esc_html_e('Anonymize IP Addresses', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Mask IP addresses for privacy compliance', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="debug_mode" value="1" <?php checked($config['debug_mode'] ?? 'no', 'yes'); ?>>
                                  <?php esc_html_e('Enable Debug Mode', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Show debug information for troubleshooting', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label for="log-retention"><?php esc_html_e('Log Retention (days)', 'cookiejar'); ?></label>
                                <input type="number" id="log-retention" name="log_retention" 
                                       value="<?php echo esc_attr($config['log_prune_days'] ?? 365); ?>" 
                                       min="30" max="<?php echo $is_pro ? 3650 : 365; ?>" />
                                <p class="description">
                                  <?php esc_html_e('How long to keep consent logs.', 'cookiejar'); ?>
                                </p>
                              </div>
                              
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="secure_cookies" value="1" <?php checked($config['secure_cookies'] ?? 'yes', 'yes'); ?>>
                                  <?php esc_html_e('Secure Cookie Transmission', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Only transmit cookies over HTTPS connections', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="httponly_cookies" value="1" <?php checked($config['httponly_cookies'] ?? 'yes', 'yes'); ?>>
                                  <?php esc_html_e('HTTPOnly Cookie Flag', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Prevent JavaScript access to consent cookies', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label for="data-encryption"><?php esc_html_e('Data Encryption Level', 'cookiejar'); ?></label>
                                <select id="data-encryption" name="data_encryption" <?php echo $is_pro ? '' : 'disabled'; ?>>
                                  <option value="basic" <?php selected($config['data_encryption'] ?? 'basic', 'basic'); ?>><?php esc_html_e('Basic (AES-128)', 'cookiejar'); ?></option>
                                  <option value="standard" <?php selected($config['data_encryption'] ?? 'basic', 'standard'); ?>><?php esc_html_e('Standard (AES-256)', 'cookiejar'); ?></option>
                                  <option value="enhanced" <?php selected($config['data_encryption'] ?? 'basic', 'enhanced'); ?>><?php esc_html_e('Enhanced (AES-256 + Salt)', 'cookiejar'); ?></option>
                                </select>
                                <p class="description">
                                  <?php esc_html_e('Encryption level for stored consent data', 'cookiejar'); ?>
                                </p>
                              </div>
                              
                              <div class="wizard-navigation">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Security Settings', 'cookiejar'); ?></button>
                                <a href="#page=security" class="button cookiejar-nav-link" data-page="security"><?php esc_html_e('Advanced Security Settings','cookiejar');?></a>
                              </div>
                            </form>
                          </div>
                        </div>
                        
                        <div class="cookiejar-accordion-item">
                          <div class="cookiejar-accordion-header" data-target="performance-settings">
                            <h5><?php esc_html_e('Performance','cookiejar');?></h5>
                            <span class="cookiejar-accordion-toggle">▼</span>
                          </div>
                          <div class="cookiejar-accordion-content" id="performance-settings">
                            <p><?php esc_html_e('Optimize CookieJar performance with caching and asset optimization settings.','cookiejar');?></p>
                            
                            <form class="cookiejar-settings-form" id="performance-settings-form">
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="enable_caching" value="1" <?php checked($config['enable_caching'] ?? 'yes', 'yes'); ?>>
                                  <?php esc_html_e('Enable Caching', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Cache analytics and settings for better performance', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label for="cache-ttl"><?php esc_html_e('Cache Duration (minutes)', 'cookiejar'); ?></label>
                                <input type="number" id="cache-ttl" name="cache_ttl" 
                                       value="<?php echo esc_attr($config['cache_ttl'] ?? 5); ?>" 
                                       min="1" max="60" />
                                <p class="description"><?php esc_html_e('How long to cache data before refreshing', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="minify_assets" value="1" <?php checked($config['minify_assets'] ?? 'no', 'yes'); ?> <?php echo $is_pro ? '' : 'disabled'; ?>>
                                  <?php esc_html_e('Minify CSS/JS Assets', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Compress CSS and JavaScript files for faster loading', 'cookiejar'); ?></p>
                                <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                              </div>
                              
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="lazy_load" value="1" <?php checked($config['lazy_load'] ?? 'no', 'yes'); ?> <?php echo $is_pro ? '' : 'disabled'; ?>>
                                  <?php esc_html_e('Lazy Load Components', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Load components only when needed to improve page speed', 'cookiejar'); ?></p>
                                <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                              </div>
                              
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="preload_critical" value="1" <?php checked($config['preload_critical'] ?? 'yes', 'yes'); ?>>
                                  <?php esc_html_e('Preload Critical Resources', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Preload essential CSS and JavaScript for faster initial load', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label for="cache-strategy"><?php esc_html_e('Cache Strategy', 'cookiejar'); ?></label>
                                <select id="cache-strategy" name="cache_strategy">
                                  <option value="aggressive" <?php selected($config['cache_strategy'] ?? 'balanced', 'aggressive'); ?>><?php esc_html_e('Aggressive (Maximum Performance)', 'cookiejar'); ?></option>
                                  <option value="balanced" <?php selected($config['cache_strategy'] ?? 'balanced', 'balanced'); ?>><?php esc_html_e('Balanced (Recommended)', 'cookiejar'); ?></option>
                                  <option value="conservative" <?php selected($config['cache_strategy'] ?? 'balanced', 'conservative'); ?>><?php esc_html_e('Conservative (Minimal Caching)', 'cookiejar'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Choose how aggressively to cache data', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="cdn_enabled" value="1" <?php checked($config['cdn_enabled'] ?? 'no', 'yes'); ?> <?php echo $is_pro ? '' : 'disabled'; ?>>
                                  <?php esc_html_e('Enable CDN Integration', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Use Content Delivery Network for faster asset delivery', 'cookiejar'); ?></p>
                                <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                              </div>
                              
                              <div class="wizard-navigation">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Performance Settings', 'cookiejar'); ?></button>
                                <button type="button" class="button button-secondary" id="clear-cache-mini"><?php esc_html_e('Clear Cache', 'cookiejar'); ?></button>
                                <a href="#page=performance" class="button cookiejar-nav-link" data-page="performance"><?php esc_html_e('Advanced Performance Settings','cookiejar');?></a>
                              </div>
                            </form>
                          </div>
                        </div>
                        
                        <div class="cookiejar-accordion-item">
                          <div class="cookiejar-accordion-header" data-target="integrations-settings">
                            <h5><?php esc_html_e('Integrations','cookiejar');?></h5>
                            <span class="cookiejar-accordion-toggle">▼</span>
                          </div>
                          <div class="cookiejar-accordion-content" id="integrations-settings">
                            <p><?php esc_html_e('Connect with third-party services and APIs for enhanced analytics and tracking.','cookiejar');?></p>
                            
                            <form class="cookiejar-settings-form" id="integrations-settings-form">
                              <div class="form-group">
                                <label for="ga-tracking-id"><?php esc_html_e('Google Analytics Tracking ID', 'cookiejar'); ?></label>
                                <input type="text" id="ga-tracking-id" name="ga_tracking_id" 
                                       value="<?php echo esc_attr($config['ga_tracking_id'] ?? ''); ?>" 
                                       placeholder="G-XXXXXXXXXX" />
                                <p class="description"><?php esc_html_e('Your Google Analytics 4 measurement ID', 'cookiejar'); ?></p>
                                <a href="https://support.google.com/analytics/answer/9539598" target="_blank" class="cookiejar-link"><?php esc_html_e('Get Tracking ID', 'cookiejar'); ?></a>
                              </div>
                              
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="ga_advanced" value="1" <?php checked($config['ga_advanced'] ?? 'no', 'yes'); ?>>
                                  <?php esc_html_e('Enable Advanced GA Features', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Enhanced Google Analytics integration with consent mode', 'cookiejar'); ?></p>
                              </div>
                              
                              <div class="form-group">
                                <label for="gtm-container-id"><?php esc_html_e('Google Tag Manager ID', 'cookiejar'); ?></label>
                                <input type="text" id="gtm-container-id" name="gtm_container_id" 
                                       value="<?php echo esc_attr($config['gtm_container_id'] ?? ''); ?>" 
                                       placeholder="GTM-XXXXXXX" <?php echo $is_pro ? '' : 'disabled'; ?> />
                                <p class="description"><?php esc_html_e('Your Google Tag Manager container ID', 'cookiejar'); ?></p>
                                <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                              </div>
                              
                              <div class="form-group">
                                <label for="facebook-pixel-id"><?php esc_html_e('Facebook Pixel ID', 'cookiejar'); ?></label>
                                <input type="text" id="facebook-pixel-id" name="facebook_pixel_id" 
                                       value="<?php echo esc_attr($config['facebook_pixel_id'] ?? ''); ?>" 
                                       placeholder="123456789012345" <?php echo $is_pro ? '' : 'disabled'; ?> />
                                <p class="description"><?php esc_html_e('Your Facebook Pixel ID for conversion tracking', 'cookiejar'); ?></p>
                                <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                              </div>
                              
                              <div class="form-group">
                                <label for="custom-scripts"><?php esc_html_e('Custom Scripts', 'cookiejar'); ?></label>
                                <textarea id="custom-scripts" name="custom_scripts" rows="4" <?php echo $is_pro ? '' : 'disabled'; ?>><?php echo esc_textarea($config['custom_scripts'] ?? ''); ?></textarea>
                                <p class="description"><?php esc_html_e('Custom JavaScript code for additional tracking', 'cookiejar'); ?></p>
                                <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                              </div>
                              
                              <div class="form-group">
                                <label for="webhook-url"><?php esc_html_e('Webhook URL', 'cookiejar'); ?></label>
                                <input type="url" id="webhook-url" name="webhook_url" 
                                       value="<?php echo esc_attr($config['webhook_url'] ?? ''); ?>" 
                                       placeholder="https://your-service.com/webhook" <?php echo $is_pro ? '' : 'disabled'; ?> />
                                <p class="description"><?php esc_html_e('Send consent data to external services via webhook', 'cookiejar'); ?></p>
                                <?php /* Removed upsell notice for WordPress.org compliance */ ?>
                              </div>
                              
                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="api_enabled" value="1" <?php checked($config['api_enabled'] ?? 'no', 'yes'); ?> <?php echo $is_pro ? '' : 'disabled'; ?>>
                                  <?php esc_html_e('Enable REST API', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Allow external applications to access consent data via API', 'cookiejar'); ?></p>
                                <?php if (!$is_pro): ?>
                                  <p class="description">
                                    <strong><?php esc_html_e('Basic Plan:', 'cookiejar'); ?></strong>
                                    <?php esc_html_e('REST API requires', 'cookiejar'); ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=cookiejar-control#page=upgrade')); ?>" target="_blank" style="color: #008ed6; text-decoration: none; font-weight: 600;">
                                      <?php esc_html_e('Pro', 'cookiejar'); ?>
                                    </a>.
                                  </p>
                                <?php endif; ?>
                              </div>
                              
                              <div class="wizard-navigation">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Integration Settings', 'cookiejar'); ?></button>
                                <a href="https://analytics.google.com/" target="_blank" class="button"><?php esc_html_e('Setup Google Analytics', 'cookiejar'); ?></a>
                                <a href="#page=integrations" class="button cookiejar-nav-link" data-page="integrations"><?php esc_html_e('Advanced Integration Settings','cookiejar');?></a>
                              </div>
                            </form>
                          </div>
                        </div>
                        
                        <div class="cookiejar-accordion-item">
                          <div class="cookiejar-accordion-header" data-target="backup-restore">
                            <h5><?php esc_html_e('Backup & Restore','cookiejar');?></h5>
                            <span class="cookiejar-accordion-toggle">▼</span>
                          </div>
                          <div class="cookiejar-accordion-content" id="backup-restore">
                            <form class="cookiejar-settings-form" id="backup-restore-form">
                              <div class="form-group">
                                <label><?php esc_html_e('Export Settings', 'cookiejar'); ?></label>
                                <p class="description"><?php esc_html_e('Download your current plugin settings as a JSON file', 'cookiejar'); ?></p>
                                <button type="button" class="button button-primary" id="export-settings">
                                  <?php esc_html_e('Export Settings', 'cookiejar'); ?>
                                </button>
                              </div>

                              <div class="form-group">
                                <label><?php esc_html_e('Import Settings', 'cookiejar'); ?></label>
                                <p class="description"><?php esc_html_e('Upload a previously exported settings file to restore your configuration', 'cookiejar'); ?></p>
                                <input type="file" name="settings_file" id="settings_file" accept=".json" />
                                <button type="button" class="button" id="import-settings">
                                  <?php esc_html_e('Import Settings', 'cookiejar'); ?>
                                </button>
                              </div>

                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="auto_backup" value="1" <?php checked($config['auto_backup'] ?? 'no', 'yes'); ?>>
                                  <?php esc_html_e('Enable Automatic Backups', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Automatically create backups when settings are changed', 'cookiejar'); ?></p>
                              </div>

                              <div class="form-group">
                                <label for="backup_frequency"><?php esc_html_e('Backup Frequency', 'cookiejar'); ?></label>
                                <select id="backup_frequency" name="backup_frequency" class="regular-text">
                                  <option value="daily" <?php selected($config['backup_frequency'] ?? 'weekly', 'daily'); ?>><?php esc_html_e('Daily', 'cookiejar'); ?></option>
                                  <option value="weekly" <?php selected($config['backup_frequency'] ?? 'weekly', 'weekly'); ?>><?php esc_html_e('Weekly', 'cookiejar'); ?></option>
                                  <option value="monthly" <?php selected($config['backup_frequency'] ?? 'weekly', 'monthly'); ?>><?php esc_html_e('Monthly', 'cookiejar'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('How often to create automatic backups', 'cookiejar'); ?></p>
                              </div>

                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="backup_retention" value="1" <?php checked($config['backup_retention'] ?? 'no', 'yes'); ?>>
                                  <?php esc_html_e('Enable Backup Retention', 'cookiejar'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Keep only the most recent backups to save storage space', 'cookiejar'); ?></p>
                              </div>

                              <div class="form-group">
                                <label for="retention_count"><?php esc_html_e('Retention Count', 'cookiejar'); ?></label>
                                <input type="number" id="retention_count" name="retention_count" value="<?php echo esc_attr($config['retention_count'] ?? '5'); ?>" min="1" max="50" class="small-text">
                                <p class="description"><?php esc_html_e('Number of backups to keep (1-50)', 'cookiejar'); ?></p>
                              </div>

                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="cloud_backup" value="1" <?php checked($config['cloud_backup'] ?? 'no', 'yes'); ?>>
                                  <?php esc_html_e('Enable Cloud Backup', 'cookiejar'); ?>
                                  <span class="cookiejar-pro-badge"><?php esc_html_e('Pro', 'cookiejar'); ?></span>
                                </label>
                                <p class="description"><?php esc_html_e('Store backups in cloud storage (Google Drive, Dropbox)', 'cookiejar'); ?></p>
                              </div>

                              <div class="form-group">
                                <label>
                                  <input type="checkbox" name="email_backup" value="1" <?php checked($config['email_backup'] ?? 'no', 'yes'); ?>>
                                  <?php esc_html_e('Email Backup Notifications', 'cookiejar'); ?>
                                  <span class="cookiejar-pro-badge"><?php esc_html_e('Pro', 'cookiejar'); ?></span>
                                </label>
                                <p class="description"><?php esc_html_e('Receive email notifications when backups are created', 'cookiejar'); ?></p>
                              </div>

                              <div class="form-group">
                                <label><?php esc_html_e('Reset to Defaults', 'cookiejar'); ?></label>
                                <p class="description"><?php esc_html_e('Reset all plugin settings to their default values. This action cannot be undone', 'cookiejar'); ?></p>
                                <button type="button" class="button button-secondary" id="reset-all-settings">
                                  <?php esc_html_e('Reset All Settings', 'cookiejar'); ?>
                                </button>
                              </div>

                              <div class="wizard-navigation">
                                <button type="submit" class="button button-primary">
                                  <?php esc_html_e('Save Backup Settings', 'cookiejar'); ?>
                                </button>
                                <a href="#page=backup" class="button cookiejar-nav-link" data-page="backup">
                                  <?php esc_html_e('Advanced Backup Settings', 'cookiejar'); ?>
                                </a>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Consent Logs Page -->
                <div id="page-logs" class="cookiejar-page" style="display:none;">
                  <?php
                  // Include logs table partial from Core.Settings
                  $logs_table = DWIC_PATH . 'admin/partials/logs-table.php';
                  if (file_exists($logs_table)) {
                      include $logs_table;
                  } else {
                      // Fallback to inline content
                      ?>
                  <div class="cookiejar-card">
                    <h3><?php esc_html_e('Consent Logs','cookiejar');?></h3>
                    <div class="cookiejar-logs-controls">
                      <button type="button" class="button" id="refresh-logs"><?php esc_html_e('Refresh','cookiejar');?></button>
                      <button type="button" class="button" id="export-logs"><?php esc_html_e('Export CSV','cookiejar');?></button>
                    </div>
                    <div id="consent-logs-table">
                      <p><?php esc_html_e('Loading consent logs...','cookiejar');?></p>
                    </div>
                  </div>
                      <?php
                  }
                  ?>
                </div>

                <!-- Languages Page -->
                <div id="page-languages" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-settings-overview">
                    <div class="cookiejar-card" role="region" aria-labelledby="languages-settings-title">
                      <div class="cookiejar-card-head">
                        <h3 id="languages-settings-title" class="cookiejar-section-title"><?php esc_html_e('Language Settings • Multilingual Support', 'cookiejar'); ?></h3>
                        <p class="cookiejar-lead"><?php esc_html_e('Configure multilingual support for your cookie consent banner with descriptive labels and examples.', 'cookiejar'); ?></p>
                      </div>
                      
                      <form id="language-settings-form" class="cookiejar-settings-form" aria-describedby="languages-settings-desc">
                        <div id="languages-settings-desc" class="sr-only">
                          <?php esc_html_e('Language configuration including supported languages, custom translations, and detection settings.', 'cookiejar'); ?>
                        </div>
                        
                        <div class="form-group">
                          <fieldset>
                            <legend><?php esc_html_e('Supported Languages', 'cookiejar'); ?></legend>
                            <div class="cookiejar-language-grid">
                              <?php 
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
                              $savedLangs = $config['supported_languages'] ?? ['en_US'];
                              $is_pro = function_exists('cookiejar_is_pro') ? cookiejar_is_pro() : false;
                              foreach ($LANGUAGES as $code => $name): 
                                $code_norm = strtolower(str_replace('_', '-', $code));
                                $checked = in_array($code_norm, $savedLangs, true) ? 'checked' : '';
                                $disabled = (!$is_pro && count(array_filter($savedLangs)) >= 2 && !$checked) ? 'disabled' : '';
                              ?>
                                <label class="cookiejar-language-option">
                                  <input type="checkbox"
                                         class="wizard-language-checkbox"
                                         name="languages[]"
                                         value="<?php echo esc_attr($code_norm); ?>"
                                         <?php echo esc_attr($checked); ?>
                                         <?php echo esc_attr($disabled); ?>
                                  />
                                  <span class="label-text"><?php echo esc_html($name); ?> (<?php echo esc_html($code_norm); ?>)</span>
                                </label>
                              <?php endforeach; ?>
                            </div>
                            <p class="description wizard-footnote">
                              <?php if (!$is_pro): ?>
                                <?php esc_html_e('Basic plan: select up to 2 languages. For additional languages and automatic geo-based language selection,', 'cookiejar'); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=cookiejar-control#page=upgrade')); ?>" target="_blank" style="color: #008ed6; text-decoration: none; font-weight: 600;">
                                  <?php esc_html_e('upgrade to Pro', 'cookiejar'); ?>
                                </a>.
                              <?php else: ?>
                                <?php esc_html_e('Pro plan: select all the languages you need. You can enable automatic geo-based language selection below.', 'cookiejar'); ?>
                              <?php endif; ?>
                            </p>
                          </fieldset>
                        </div>

                        <div class="form-group">
                          <label for="default_language"><?php esc_html_e('Default Language', 'cookiejar'); ?></label>
                          <select id="default_language" name="default_language" class="regular-text">
                            <?php foreach ($savedLangs as $lang): ?>
                              <option value="<?php echo esc_attr($lang); ?>" <?php selected($config['default_language'] ?? 'en-us', $lang); ?>>
                                <?php echo esc_html(ucfirst(str_replace('-', ' ', $lang))); ?>
                              </option>
                            <?php endforeach; ?>
                            </select>
                          <p class="description"><?php esc_html_e('The language shown when auto-detection fails or is disabled', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label>
                            <input type="checkbox" name="auto_detect_language" value="1" <?php checked($config['auto_detect_language'] ?? 'yes', 'yes'); ?>>
                            <?php esc_html_e('Enable Auto-Detection', 'cookiejar'); ?>
                            <span class="cookiejar-pro-badge"><?php esc_html_e('Pro', 'cookiejar'); ?></span>
                          </label>
                          <p class="description"><?php esc_html_e('Automatically detect visitor language based on browser settings and geolocation', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label>
                            <input type="checkbox" name="show_language_selector" value="1" <?php checked($config['show_language_selector'] ?? 'yes', 'yes'); ?>>
                            <?php esc_html_e('Show Language Selector', 'cookiejar'); ?>
                          </label>
                          <p class="description"><?php esc_html_e('Display a language dropdown in the banner for manual language selection', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label for="language_selector_position"><?php esc_html_e('Language Selector Position', 'cookiejar'); ?></label>
                          <select id="language_selector_position" name="language_selector_position" class="regular-text">
                            <option value="top-right" <?php selected($config['language_selector_position'] ?? 'top-right', 'top-right'); ?>><?php esc_html_e('Top Right', 'cookiejar'); ?></option>
                            <option value="top-left" <?php selected($config['language_selector_position'] ?? 'top-right', 'top-left'); ?>><?php esc_html_e('Top Left', 'cookiejar'); ?></option>
                            <option value="bottom-right" <?php selected($config['language_selector_position'] ?? 'top-right', 'bottom-right'); ?>><?php esc_html_e('Bottom Right', 'cookiejar'); ?></option>
                            <option value="bottom-left" <?php selected($config['language_selector_position'] ?? 'top-right', 'bottom-left'); ?>><?php esc_html_e('Bottom Left', 'cookiejar'); ?></option>
                          </select>
                          <p class="description"><?php esc_html_e('Position of the language selector in the banner', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label for="custom_translations"><?php esc_html_e('Custom Translation Override', 'cookiejar'); ?></label>
                          <textarea name="custom_translations" id="custom_translations" rows="8" 
                                    placeholder='{"en": {"accept_all": "Accept All Cookies", "settings": "Cookie Settings", "decline": "Decline All"}, "es": {"accept_all": "Aceptar Todas las Cookies", "settings": "Configuración de Cookies", "decline": "Rechazar Todo"}}'
                                    class="cookiejar-translation-textarea"><?php echo esc_textarea($config['custom_translations'] ?? ''); ?></textarea>
                          <p class="description">
                            <?php esc_html_e('Enter custom translations in JSON format. Use this to override default translations or add new languages.', 'cookiejar'); ?>
                            <br><strong><?php esc_html_e('Format Example:', 'cookiejar'); ?></strong>
                            <code>{"language_code": {"key": "translated_text"}}</code>
                          </p>
                        </div>

                        <div class="form-group">
                          <label>
                            <input type="checkbox" name="rtl_support" value="1" <?php checked($config['rtl_support'] ?? 'no', 'yes'); ?>>
                            <?php esc_html_e('Enable RTL Support', 'cookiejar'); ?>
                            <span class="cookiejar-pro-badge"><?php esc_html_e('Pro', 'cookiejar'); ?></span>
                          </label>
                          <p class="description"><?php esc_html_e('Enable right-to-left text direction for Arabic, Hebrew, and other RTL languages', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label>
                            <input type="checkbox" name="save_language_preference" value="1" <?php checked($config['save_language_preference'] ?? 'yes', 'yes'); ?>>
                            <?php esc_html_e('Save Language Preference', 'cookiejar'); ?>
                          </label>
                          <p class="description"><?php esc_html_e('Remember the visitor\'s language choice for future visits', 'cookiejar'); ?></p>
                        </div>

                        <div class="wizard-navigation">
                          <button type="submit" class="button button-primary">
                            <?php esc_html_e('Save Language Settings', 'cookiejar'); ?>
                          </button>
                          <button type="button" class="button button-secondary" id="reset-language-settings">
                            <?php esc_html_e('Reset to Defaults', 'cookiejar'); ?>
                          </button>
                          <button type="button" class="button" id="test-translations">
                            <?php esc_html_e('Test Translations', 'cookiejar'); ?>
                          </button>
                        </div>
                    </form>
                    </div>
                  </div>
                </div>

                <!-- Advanced Settings Page -->
                <div id="page-advanced" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-settings-overview">
                    <div class="cookiejar-card" role="region" aria-labelledby="advanced-settings-title">
                      <div class="cookiejar-card-head">
                        <h3 id="advanced-settings-title" class="cookiejar-section-title"><?php esc_html_e('Advanced Settings • Automation & Integrations', 'cookiejar'); ?></h3>
                        <p class="cookiejar-lead"><?php esc_html_e('Power-user options for compliance, automation, security, and integrations. Adjust advanced features below.', 'cookiejar'); ?></p>
                      </div>
                      
                      <form id="advanced-settings-form" class="cookiejar-settings-form" aria-describedby="advanced-settings-desc">
                        <div id="advanced-settings-desc" class="sr-only">
                          <?php esc_html_e('Advanced site configuration including logging, security, integration, and automation.', 'cookiejar'); ?>
                        </div>
                        
                        <div class="form-group">
                          <label for="logging_mode">
                            <?php esc_html_e('Logging Mode', 'cookiejar'); ?>
                            <span class="cookiejar-tooltip" title="<?php esc_attr_e('Cached = 24hr summary, Live = real-time. Live requires Pro.', 'cookiejar'); ?>">?</span>
                            </label>
                          <select name="logging_mode" id="logging_mode" aria-label="<?php esc_attr_e('Logging Mode', 'cookiejar'); ?>">
                            <option value="cached"><?php esc_html_e('Cached (24-hour summaries)', 'cookiejar'); ?></option>
                            <option value="live"><?php esc_html_e('Live (real-time)', 'cookiejar'); ?></option>
                          </select>
                          <div class="description"><?php esc_html_e('Live mode is recommended for high-traffic sites. Pro only.', 'cookiejar'); ?></div>
                        </div>
                        
                        <div class="form-group">
                          <label for="privacy_mode">
                            <?php esc_html_e('Privacy Mode', 'cookiejar'); ?>
                            <span class="cookiejar-tooltip" title="<?php esc_attr_e('Hide IP addresses and anonymize logs.', 'cookiejar'); ?>">?</span>
                          </label>
                          <input type="checkbox" name="privacy_mode" id="privacy_mode" aria-label="<?php esc_attr_e('Privacy Mode', 'cookiejar'); ?>">
                          <div class="description"><?php esc_html_e('Enable to improve privacy for all logs.', 'cookiejar'); ?></div>
                        </div>
                        
                        <div class="form-group">
                          <label for="auto_clear_logs">
                            <?php esc_html_e('Auto-clear Consent Logs', 'cookiejar'); ?>
                            <span class="cookiejar-tooltip" title="<?php esc_attr_e('Automatically delete logs after set days.', 'cookiejar'); ?>">?</span>
                          </label>
                          <input type="number" min="7" max="365" step="1" name="auto_clear_logs" id="auto_clear_logs" 
                                 value="90" class="regular-text" aria-label="<?php esc_attr_e('Auto-clear log days', 'cookiejar'); ?>">
                          <div class="description"><?php esc_html_e('Delete consent logs older than N days automatically.', 'cookiejar'); ?></div>
                        </div>
                        
                        <div class="form-group">
                          <label for="google_analytics_id">
                            <?php esc_html_e('Google Analytics ID', 'cookiejar'); ?>
                            <span class="cookiejar-tooltip" title="<?php esc_attr_e('Integrate with Google Analytics (UA-XXXXX or G-XXXXXX).', 'cookiejar'); ?>">?</span>
                          </label>
                          <input type="text" name="google_analytics_id" id="google_analytics_id" 
                                 value="" class="regular-text" aria-label="<?php esc_attr_e('Google Analytics ID', 'cookiejar'); ?>">
                          <div class="description"><?php esc_html_e('Add your analytics ID for tracking consent and conversions.', 'cookiejar'); ?></div>
                        </div>
                        
                        <div class="form-group">
                          <label for="custom_css">
                            <?php esc_html_e('Custom Banner CSS', 'cookiejar'); ?>
                            <span class="cookiejar-tooltip" title="<?php esc_attr_e('Paste any extra CSS for banner styling.', 'cookiejar'); ?>">?</span>
                          </label>
                          <textarea name="custom_css" id="custom_css" rows="3" class="large-text"></textarea>
                          <div class="description"><?php esc_html_e('Advanced: Add custom CSS rules for banner appearance.', 'cookiejar'); ?></div>
                        </div>
                        
                        <div class="wizard-navigation">
                          <button type="submit" class="button button-primary" aria-label="<?php esc_attr_e('Save Advanced Settings', 'cookiejar'); ?>">
                            <?php esc_html_e('Save Advanced', 'cookiejar'); ?>
                          </button>
                          <button type="button" class="button button-secondary" id="reset-advanced-settings" aria-label="<?php esc_attr_e('Reset Advanced', 'cookiejar'); ?>">
                            <?php esc_html_e('Reset to Defaults', 'cookiejar'); ?>
                          </button>
                        </div>
                        
                        <div id="advanced-status" class="wizard-status" aria-live="polite" style="display:none;"></div>
                    </form>
                    </div>
                  </div>
                </div>

                <!-- Settings Page -->
                <div id="page-settings" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-settings-overview">
                    <div class="cookiejar-card" role="region" aria-labelledby="settings-title">
                      <div class="cookiejar-card-head">
                        <h3 id="settings-title" class="cookiejar-section-title"><?php esc_html_e('Settings • Configuration & Management', 'cookiejar'); ?></h3>
                        <p class="cookiejar-lead"><?php esc_html_e('Configure your CookieJar settings, manage imports/exports, and reset configurations.', 'cookiejar'); ?></p>
                      </div>
                      
                      <form id="settings-form" class="cookiejar-settings-form" aria-describedby="settings-desc">
                        <div id="settings-desc" class="sr-only">
                          <?php esc_html_e('General settings configuration including banner, compliance, appearance, and management options.', 'cookiejar'); ?>
                        </div>
                        
                        <div class="form-group">
                          <label>
                            <input type="checkbox" name="banner_enabled" value="1" <?php checked($config['banner_enabled'] ?? 'yes', 'yes'); ?>>
                            <?php esc_html_e('Enable Cookie Banner', 'cookiejar'); ?>
                          </label>
                          <p class="description"><?php esc_html_e('Show cookie consent banner to visitors', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label for="banner_position"><?php esc_html_e('Banner Position', 'cookiejar'); ?></label>
                          <select id="banner_position" name="banner_position" class="regular-text">
                            <option value="bottom" <?php selected($config['banner_position'] ?? 'bottom', 'bottom'); ?>><?php esc_html_e('Bottom', 'cookiejar'); ?></option>
                            <option value="top" <?php selected($config['banner_position'] ?? 'bottom', 'top'); ?>><?php esc_html_e('Top', 'cookiejar'); ?></option>
                            <option value="center" <?php selected($config['banner_position'] ?? 'bottom', 'center'); ?>><?php esc_html_e('Center', 'cookiejar'); ?></option>
                          </select>
                          <p class="description"><?php esc_html_e('Position of the cookie banner on the page', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label for="policy_page"><?php esc_html_e('Privacy Policy Page', 'cookiejar'); ?></label>
                          <select id="policy_page" name="policy_url" class="regular-text">
                            <option value="">— <?php esc_html_e('Select a page', 'cookiejar'); ?> —</option>
                            <?php 
                            $pages = get_pages(['post_status' => 'publish', 'number' => 0]);
                            foreach ($pages as $page): 
                              $selected = ($config['policy_url'] ?? '') === get_permalink($page->ID);
                            ?>
                              <option value="<?php echo esc_attr(get_permalink($page->ID)); ?>" <?php selected($selected); ?>>
                                <?php echo esc_html($page->post_title); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <p class="description"><?php esc_html_e('Choose a WordPress page to use as your privacy policy', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label for="primary_color"><?php esc_html_e('Primary Color', 'cookiejar'); ?></label>
                          <div class="cookiejar-color-edit-wrap">
                            <input type="color" id="primary_color" name="primary_color" value="<?php echo esc_attr($config['primary_color'] ?? '#008ed6'); ?>" class="cookiejar-color-palette">
                            <input type="text" id="primary_color_text" value="<?php echo esc_attr($config['primary_color'] ?? '#008ed6'); ?>" class="cookiejar-color-text" placeholder="#008ed6">
                          </div>
                          <p class="description"><?php esc_html_e('Primary color for buttons and links', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label for="background_color"><?php esc_html_e('Background Color', 'cookiejar'); ?></label>
                          <div class="cookiejar-color-edit-wrap">
                            <input type="color" id="background_color" name="background_color" value="<?php echo esc_attr($config['background_color'] ?? '#ffffff'); ?>" class="cookiejar-color-palette">
                            <input type="text" id="background_color_text" value="<?php echo esc_attr($config['background_color'] ?? '#ffffff'); ?>" class="cookiejar-color-text" placeholder="#ffffff">
                          </div>
                          <p class="description"><?php esc_html_e('Background color for the banner', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label>
                            <input type="checkbox" name="geo_auto" value="1" <?php checked($config['geo_auto'] ?? 'yes', 'yes'); ?>>
                            <?php esc_html_e('Enable Automatic Geotargeting', 'cookiejar'); ?>
                            <span class="cookiejar-pro-badge"><?php esc_html_e('Pro', 'cookiejar'); ?></span>
                          </label>
                          <p class="description"><?php esc_html_e('Automatically detect user location for GDPR/CCPA compliance', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label for="logging_mode"><?php esc_html_e('Logging Mode', 'cookiejar'); ?></label>
                          <select id="logging_mode" name="logging_mode" class="regular-text" <?php echo !$is_pro ? 'disabled' : ''; ?>>
                            <option value="cached" <?php selected($config['logging_mode'] ?? 'cached', 'cached'); ?>>
                              <?php esc_html_e('Cached (24-hour summaries)', 'cookiejar'); ?>
                            </option>
                            <option value="live" <?php selected($config['logging_mode'] ?? 'cached', 'live'); ?> <?php echo !$is_pro ? 'disabled' : ''; ?>>
                              <?php esc_html_e('Live (real-time data)', 'cookiejar'); ?>
                            </option>
                          </select>
                          <?php if (!function_exists('cookiejar_is_pro') || !cookiejar_is_pro()): ?>
                            <p class="description">
                              <strong><?php esc_html_e('Basic Plan:', 'cookiejar'); ?></strong>
                              <?php esc_html_e('Live logging requires', 'cookiejar'); ?>
                              <a href="<?php echo esc_url(admin_url('admin.php?page=cookiejar-control#page=upgrade')); ?>" target="_blank" style="color: #008ed6; text-decoration: none; font-weight: 600;">
                                <?php esc_html_e('Pro', 'cookiejar'); ?>
                              </a>.
                            </p>
                          <?php else: ?>
                            <p class="description"><?php esc_html_e('Choose between cached summaries or real-time logging', 'cookiejar'); ?></p>
                          <?php endif; ?>
                        </div>

                        <div class="form-group">
                          <label>
                            <input type="checkbox" name="gdpr_mode" value="1" <?php checked($config['gdpr_mode'] ?? 'yes', 'yes'); ?>>
                            <?php esc_html_e('Enable GDPR Compliance Mode', 'cookiejar'); ?>
                          </label>
                          <p class="description"><?php esc_html_e('Enable GDPR compliance features and requirements', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label>
                            <input type="checkbox" name="ccpa_mode" value="1" <?php checked($config['ccpa_mode'] ?? 'yes', 'yes'); ?> <?php echo !$is_pro ? 'disabled' : ''; ?>>
                            <?php esc_html_e('Enable CCPA Compliance Mode', 'cookiejar'); ?>
                            <?php if (!$is_pro): ?>
                              <span class="cookiejar-pro-badge"><?php esc_html_e('Pro', 'cookiejar'); ?></span>
                            <?php endif; ?>
                          </label>
                          <?php if (!function_exists('cookiejar_is_pro') || !cookiejar_is_pro()): ?>
                            <p class="description">
                              <strong><?php esc_html_e('Basic Plan:', 'cookiejar'); ?></strong>
                              <?php esc_html_e('CCPA compliance requires', 'cookiejar'); ?>
                              <a href="<?php echo esc_url(admin_url('admin.php?page=cookiejar-control#page=upgrade')); ?>" target="_blank" style="color: #008ed6; text-decoration: none; font-weight: 600;">
                                <?php esc_html_e('Pro', 'cookiejar'); ?>
                              </a>.
                            </p>
                          <?php else: ?>
                            <p class="description"><?php esc_html_e('Enable CCPA compliance features and requirements', 'cookiejar'); ?></p>
                          <?php endif; ?>
                        </div>

                        <div class="form-group">
                          <label for="consent_duration"><?php esc_html_e('Consent Duration (Days)', 'cookiejar'); ?></label>
                          <input type="number" id="consent_duration" name="consent_duration" value="<?php echo esc_attr($config['consent_duration'] ?? '365'); ?>" min="1" max="3650" class="small-text">
                          <p class="description"><?php esc_html_e('How long to remember user consent (1-3650 days)', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label for="storage_mode"><?php esc_html_e('Storage Mode', 'cookiejar'); ?></label>
                          <select id="storage_mode" name="storage_mode" class="regular-text">
                            <option value="localStorage" <?php selected($config['storage_mode'] ?? 'localStorage', 'localStorage'); ?>><?php esc_html_e('Local Storage', 'cookiejar'); ?></option>
                            <option value="sessionStorage" <?php selected($config['storage_mode'] ?? 'localStorage', 'sessionStorage'); ?>><?php esc_html_e('Session Storage', 'cookiejar'); ?></option>
                            <option value="cookie" <?php selected($config['storage_mode'] ?? 'localStorage', 'cookie'); ?>><?php esc_html_e('Cookie', 'cookiejar'); ?></option>
                          </select>
                          <p class="description"><?php esc_html_e('How to store consent data', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label>
                            <input type="checkbox" name="auto_clear_logs" value="1" <?php checked($config['auto_clear_logs'] ?? 'no', 'yes'); ?>>
                            <?php esc_html_e('Auto-Clear Old Logs', 'cookiejar'); ?>
                          </label>
                          <p class="description"><?php esc_html_e('Automatically remove logs older than 30 days', 'cookiejar'); ?></p>
                        </div>

                        <div class="form-group">
                          <label for="custom_css"><?php esc_html_e('Custom CSS', 'cookiejar'); ?></label>
                          <textarea id="custom_css" name="custom_css" rows="6" class="large-text" placeholder="/* Add your custom CSS here */"><?php echo esc_textarea($config['custom_css'] ?? ''); ?></textarea>
                          <p class="description"><?php esc_html_e('Add custom CSS to style the cookie banner', 'cookiejar'); ?></p>
                        </div>

                        <div class="wizard-navigation">
                          <button type="submit" class="button button-primary">
                            <?php esc_html_e('Save Settings', 'cookiejar'); ?>
                          </button>
                          <button type="button" class="button button-secondary" id="reset-settings">
                            <?php esc_html_e('Reset to Defaults', 'cookiejar'); ?>
                          </button>
                        </div>
                      </form>
                    </div>

                    <div class="cookiejar-card" role="region" aria-labelledby="import-export-title">
                      <div class="cookiejar-card-head">
                        <h3 id="import-export-title" class="cookiejar-section-title"><?php esc_html_e('Import / Export • Settings Management', 'cookiejar'); ?></h3>
                        <p class="cookiejar-lead"><?php esc_html_e('Manage your CookieJar settings with import, export, and reset functionality.', 'cookiejar'); ?></p>
                      </div>
                      
                      <div class="form-group">
                        <label><?php esc_html_e('Export Settings', 'cookiejar'); ?></label>
                        <p class="description"><?php esc_html_e('Download your current CookieJar configuration as a JSON file', 'cookiejar'); ?></p>
                        <button type="button" class="button button-primary" id="export-settings">
                          <?php esc_html_e('Export Settings', 'cookiejar'); ?>
                        </button>
                      </div>

                      <div class="form-group">
                        <label><?php esc_html_e('Import Settings', 'cookiejar'); ?></label>
                        <p class="description"><?php esc_html_e('Upload a JSON file to restore your CookieJar configuration', 'cookiejar'); ?></p>
                        <textarea id="import-textarea" rows="8" placeholder="<?php esc_attr_e('Paste JSON settings here or upload a file...', 'cookiejar'); ?>"></textarea>
                        <div class="wizard-navigation">
                          <input type="file" id="import-file" accept=".json" style="display:none;">
                          <button type="button" class="button" id="select-file">
                            <?php esc_html_e('Select File', 'cookiejar'); ?>
                          </button>
                          <button type="button" class="button button-primary" id="import-settings">
                            <?php esc_html_e('Import Settings', 'cookiejar'); ?>
                          </button>
                        </div>
                      </div>

                      <div class="form-group">
                        <label><?php esc_html_e('Reset to Defaults', 'cookiejar'); ?></label>
                        <p class="description"><?php esc_html_e('Restore all settings to their default values and clear caches', 'cookiejar'); ?></p>
                        <button type="button" class="button button-secondary" id="reset-defaults">
                          <?php esc_html_e('Reset to Defaults', 'cookiejar'); ?>
                        </button>
                      </div>

                      <div id="settings-status" class="cookiejar-status" style="display:none;"></div>
                    </div>
                  </div>
                </div>

                <!-- Reports Page -->
                <div id="page-reports" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-reports-overview">
                  <div class="cookiejar-card">
                      <h3><?php esc_html_e('Review Reports','cookiejar');?></h3>
                      <p><?php esc_html_e('Reports (Limited in free version)','cookiejar');?></p>
                      
                      <div class="cookiejar-report-types">
                        <div class="cookiejar-report-type">
                          <h4><?php esc_html_e('Export Reports','cookiejar');?></h4>
                          <p><?php esc_html_e('Export basic consent data and analytics','cookiejar');?></p>
                          <a href="<?php echo esc_url( wp_nonce_url(admin_url('admin-ajax.php?action=cookiejar_export_basic_report'), 'cookiejar_export') ); ?>" 
                             class="button button-primary" id="export-basic-report">
                            <?php esc_html_e('Export Basic Report','cookiejar');?>
                          </a>
                        </div>
                        
                        <div class="cookiejar-report-type">
                          <h4><?php esc_html_e('Custom Reports','cookiejar');?></h4>
                          <p><?php esc_html_e('Create custom reports with advanced filtering and analytics.','cookiejar');?></p>
                          <?php if(!function_exists('cookiejar_is_pro') || !cookiejar_is_pro()): ?>
                            <button type="button" class="button" disabled>
                              <?php esc_html_e('Custom Reports','cookiejar');?> <span class="cookiejar-pro-lock"><?php esc_html_e('Pro Only','cookiejar');?></span>
                            </button>
                          <?php else: ?>
                            <button type="button" class="button" id="create-custom-report">
                              <?php esc_html_e('Create Custom Report','cookiejar');?>
                            </button>
                          <?php endif; ?>
                        </div>
                        
                        <div class="cookiejar-report-type">
                          <h4><?php esc_html_e('Compliance Reports','cookiejar');?></h4>
                          <p><?php esc_html_e('Generate detailed compliance reports for GDPR, CCPA, and other regulations.','cookiejar');?></p>
                          <?php if(!function_exists('cookiejar_is_pro') || !cookiejar_is_pro()): ?>
                            <button type="button" class="button" disabled>
                              <?php esc_html_e('Compliance Reports','cookiejar');?> <span class="cookiejar-pro-lock"><?php esc_html_e('Pro Only','cookiejar');?></span>
                            </button>
                          <?php else: ?>
                            <button type="button" class="button" id="generate-compliance-report">
                              <?php esc_html_e('Generate Compliance Report','cookiejar');?>
                            </button>
                          <?php endif; ?>
                        </div>
                        
                        <div class="cookiejar-report-type">
                          <h4><?php esc_html_e('Consent Logs','cookiejar');?></h4>
                          <p><?php esc_html_e('View and export detailed consent logs and user interactions.','cookiejar');?></p>
                          <button type="button" class="button cookiejar-nav-link" data-page="logs">
                            <?php esc_html_e('View Consent Logs','cookiejar');?>
                          </button>
                        </div>
                        
                        <div class="cookiejar-report-type">
                          <h4><?php esc_html_e('Minimum Requirements Report','cookiejar');?></h4>
                          <p><?php esc_html_e('Monitor system performance and health metrics.','cookiejar');?></p>
                          
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Upgrade Page -->
                <div id="page-upgrade" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-upgrade-overview">
                    <div class="cookiejar-card">
                      <h3><?php esc_html_e('About','cookiejar');?></h3>
                      
                      <?php if(!function_exists('cookiejar_is_pro') || !cookiejar_is_pro()): ?>
                        <div class="cookiejar-upgrade-section">
                          <h4><?php esc_html_e('Get CookieJar Pro','cookiejar');?></h4>
                          <p><?php esc_html_e('Unlock advanced features, unlimited languages, extended logging, and priority support.','cookiejar');?></p>
                          <a href="#" class="button" disabled="disabled">
                            <?php esc_html_e('Upgrade Now','cookiejar');?>
                          </a>
                        </div>
                        
                        <hr>
                        
                        <div class="cookiejar-pro-key-section">
                          <h4><?php esc_html_e('Already have Pro? Enter your key','cookiejar');?></h4>
                          <form method="post" action="" id="pro-key-form">
                            <?php wp_nonce_field('cookiejar_pro_key', 'cookiejar_pro_key_nonce'); ?>
                      <table class="form-table">
                        <tr>
                                <th scope="row">
                                  <label for="cookiejar_pro_key"><?php esc_html_e('Pro License Key','cookiejar');?></label>
                                </th>
                                <td>
                                  <input type="text" name="cookiejar_pro_key" id="cookiejar_pro_key" 
                                         value="<?php echo esc_attr(get_option('cookiejar_pro_key', '')); ?>" 
                                         class="regular-text" placeholder="<?php esc_attr_e('Enter your Pro license key','cookiejar');?>">
                                  <p class="description"><?php esc_html_e('Enter the license key you received after purchasing CookieJar Pro.','cookiejar');?></p>
                          </td>
                        </tr>
                      </table>
                      <p class="submit">
                              <input type="submit" name="submit_pro_key" class="button button-primary" 
                                     value="<?php esc_attr_e('Activate Pro License','cookiejar');?>">
                      </p>
                    </form>
                  </div>
                      <?php else: ?>
                        <div class="cookiejar-pro-active">
                          <div class="cookiejar-pro-status">
                            <span class="cookiejar-pro-icon">✓</span>
                            <strong><?php esc_html_e('CookieJar Pro is Active','cookiejar');?></strong>
                </div>
                          <p><?php esc_html_e('You are using CookieJar Pro. Thank you for supporting our development!','cookiejar');?></p>
                          
                          <div class="cookiejar-pro-info">
                            <p><strong><?php esc_html_e('License Key:','cookiejar');?></strong> <?php echo esc_html(substr(get_option('cookiejar_pro_key', ''), 0, 8) . '...' . substr(get_option('cookiejar_pro_key', ''), -4)); ?></p>
                            <p><strong><?php esc_html_e('Status:','cookiejar');?></strong> <span class="cookiejar-status-active"><?php esc_html_e('Active','cookiejar');?></span></p>
                          </div>
                          
                          <div class="cookiejar-pro-actions">
                            <a href="#" class="button" disabled="disabled">
                              <?php esc_html_e('Manage License','cookiejar');?>
                            </a>
                            <button type="button" class="button" id="deactivate-pro">
                              <?php esc_html_e('Deactivate License','cookiejar');?>
                            </button>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <!-- Community Page -->
                <div id="page-community" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-community-overview">
                  <div class="cookiejar-card">
                      <h3><?php esc_html_e('Community','cookiejar');?></h3>
                      
                      <div class="cookiejar-social-section">
                        <h4><?php esc_html_e('Connect With Us','cookiejar');?></h4>
                        <div class="cookiejar-social-links">
                          <a href="https://www.facebook.com/demewebsolutions" target="_blank" class="cookiejar-social-link">
                            <span class="cookiejar-social-icon">📘</span>
                            <div class="cookiejar-social-content">
                              <strong><?php esc_html_e('Facebook','cookiejar');?></strong>
                              <p><?php esc_html_e('Follow us for updates and community discussions','cookiejar');?></p>
                            </div>
                          </a>
                          
                          <a href="https://www.instagram.com/demewebsolutions" target="_blank" class="cookiejar-social-link">
                            <span class="cookiejar-social-icon">📷</span>
                            <div class="cookiejar-social-content">
                              <strong><?php esc_html_e('Instagram','cookiejar');?></strong>
                              <p><?php esc_html_e('Behind-the-scenes content and product updates','cookiejar');?></p>
                            </div>
                          </a>
                          
                          <span class="cookiejar-social-link" aria-hidden="true">
                            <span class="cookiejar-social-icon">🌐</span>
                            <div class="cookiejar-social-content">
                              <strong><?php esc_html_e('DemeWebSolution.com','cookiejar');?></strong>
                              <p><?php esc_html_e('Visit our main website for all products and services','cookiejar');?></p>
                            </div>
                          </span>
                        </div>
                      </div>
                      
                      <div class="cookiejar-newsletter-section">
                        <h4><?php esc_html_e('Mailing List Subscription','cookiejar');?></h4>
                        <p><?php esc_html_e('Promotions, updates, future software announcements','cookiejar');?></p>
                        
                        <form class="cookiejar-newsletter-form" id="newsletter-signup">
                          <div class="cookiejar-form-group">
                            <label for="newsletter-email"><?php esc_html_e('Email Address','cookiejar');?></label>
                            <input type="email" id="newsletter-email" name="email" required 
                                   placeholder="<?php esc_attr_e('Enter your email address','cookiejar');?>">
                          </div>
                          
                          <div class="cookiejar-form-group">
                            <label>
                              <input type="checkbox" name="newsletter-consent" required="">
                              <?php esc_html_e('I agree to receive updates','cookiejar');?>
                            </label>
                          </div>
                          
                          <div class="cookiejar-form-actions">
                            <button type="submit" class="button button-primary">
                              <?php esc_html_e('Subscribe to Newsletter','cookiejar');?>
                      </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                    </div>
                    
                <!-- Support Page -->
                <div id="page-support" class="cookiejar-page" style="display:none;">
                  <?php
                  // Get support data from backend
                  $config = class_exists('\\DWIC\\Config') ? \DWIC\Config::all() : [];
                  $health_status = class_exists('\\DWIC\\Monitor') ? \DWIC\Monitor::get_health_status() : null;
                  $recent_logs = class_exists('\\DWIC\\Logger') ? \DWIC\Logger::get_recent(5) : [];
                  $cache_stats = class_exists('\\DWIC\\Cache') ? \DWIC\Cache::stats() : [];
                  
                  // System information
                  $wp_version = get_bloginfo('version');
                  $php_version = PHP_VERSION;
                  $plugin_version = DWIC_VERSION;
                  $current_tier = cookiejar_get_tier();
                  $is_pro = cookiejar_is_pro();
                  ?>
                  <div class="cookiejar-support-overview">
                    <div class="cookiejar-card">
                      <h3><?php esc_html_e('Help & Support Center', 'cookiejar'); ?></h3>
                      <div class="cookiejar-support-options">
                        <div class="cookiejar-support-option">
                          <div class="cookiejar-support-icon">📚</div>
                          <h4><?php esc_html_e('Knowledge Base', 'cookiejar'); ?></h4>
                          <p><?php esc_html_e('Comprehensive guides and troubleshooting articles', 'cookiejar'); ?></p>
                          <a href="https://help.cookiejar.com" target="_blank" class="button button-primary">
                            <?php esc_html_e('Browse Knowledge Base', 'cookiejar'); ?>
                          </a>
                        </div>
                        
                        <div class="cookiejar-support-option">
                          <div class="cookiejar-support-icon">💬</div>
                          <h4><?php esc_html_e('Contact Support', 'cookiejar'); ?></h4>
                          <p><?php esc_html_e('Get direct help from our support team', 'cookiejar'); ?></p>
                          <a href="https://support.cookiejar.com" target="_blank" class="button">
                            <?php esc_html_e('Contact Support', 'cookiejar'); ?>
                          </a>
                        </div>
                        
                        <div class="cookiejar-support-option">
                          <div class="cookiejar-support-icon">🔧</div>
                          <h4><?php esc_html_e('System Diagnostics', 'cookiejar'); ?></h4>
                          <p><?php esc_html_e('Run system diagnostics and health checks', 'cookiejar'); ?></p>
                          <button type="button" class="button" onclick="location.href='#page=health'">
                            <?php esc_html_e('Run Diagnostics', 'cookiejar'); ?>
                          </button>
                        </div>
                        
                        <div class="cookiejar-support-option">
                          <div class="cookiejar-support-icon">🐛</div>
                          <h4><?php esc_html_e('Bug Reports', 'cookiejar'); ?></h4>
                          <p><?php esc_html_e('Report bugs and suggest improvements', 'cookiejar'); ?></p>
                          <a href="https://github.com/cookiejar/issues" target="_blank" class="button">
                            <?php esc_html_e('Report Bug', 'cookiejar'); ?>
                          </a>
                        </div>
                      </div>
                    </div>
                    
                    <div class="cookiejar-card">
                      <h3><?php esc_html_e('System Information', 'cookiejar'); ?></h3>
                      <div class="cookiejar-system-info">
                        <div class="cookiejar-info-grid">
                          <div class="cookiejar-info-item">
                            <span class="cookiejar-info-label"><?php esc_html_e('WordPress Version', 'cookiejar'); ?></span>
                            <span class="cookiejar-info-value"><?php echo esc_html($wp_version); ?></span>
                          </div>
                          
                          <div class="cookiejar-info-item">
                            <span class="cookiejar-info-label"><?php esc_html_e('PHP Version', 'cookiejar'); ?></span>
                            <span class="cookiejar-info-value"><?php echo esc_html($php_version); ?></span>
                          </div>
                          
                          <div class="cookiejar-info-item">
                            <span class="cookiejar-info-label"><?php esc_html_e('CookieJar Version', 'cookiejar'); ?></span>
                            <span class="cookiejar-info-value"><?php echo esc_html($plugin_version); ?></span>
                          </div>
                          
                          <div class="cookiejar-info-item">
                            <span class="cookiejar-info-label"><?php esc_html_e('License Tier', 'cookiejar'); ?></span>
                            <span class="cookiejar-info-value">
                              <span class="cookiejar-tier-badge cookiejar-tier-<?php echo esc_attr($current_tier); ?>">
                                <?php echo esc_html(ucfirst($current_tier)); ?>
                              </span>
                            </span>
                          </div>
                          
                          <div class="cookiejar-info-item">
                            <span class="cookiejar-info-label"><?php esc_html_e('Minimum Requirements', 'cookiejar'); ?></span>
                            <span class="cookiejar-info-value">
                              <?php if ($health_status): ?>
                                <span class="cookiejar-health-status cookiejar-status-<?php echo esc_attr($health_status['overall_status']); ?>">
                                  <?php echo esc_html(ucfirst($health_status['overall_status'])); ?>
                                </span>
                              <?php else: ?>
                                <span class="cookiejar-health-status cookiejar-status-unknown">
                                  <?php esc_html_e('Unknown', 'cookiejar'); ?>
                                </span>
                              <?php endif; ?>
                            </span>
                          </div>
                          
                          <div class="cookiejar-info-item">
                            <span class="cookiejar-info-label"><?php esc_html_e('Cache Status', 'cookiejar'); ?></span>
                            <span class="cookiejar-info-value">
                              <?php echo esc_html($cache_stats['total_caches'] ?? 0); ?> <?php esc_html_e('caches', 'cookiejar'); ?>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                    
                    <div class="cookiejar-card">
                      <h3><?php esc_html_e('Recent System Logs', 'cookiejar'); ?></h3>
                      <div class="cookiejar-support-logs">
                        <?php if (!empty($recent_logs)): ?>
                          <div class="cookiejar-logs-list">
                            <?php foreach ($recent_logs as $log): ?>
                              <div class="cookiejar-log-entry cookiejar-log-<?php echo esc_attr($log->level); ?>">
                                <div class="cookiejar-log-header">
                                  <span class="cookiejar-log-level"><?php echo esc_html(strtoupper($log->level)); ?></span>
                                  <span class="cookiejar-log-time"><?php echo esc_html($log->created_at); ?></span>
                                </div>
                                <div class="cookiejar-log-message"><?php echo esc_html($log->message); ?></div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php else: ?>
                          <p><?php esc_html_e('No recent system logs found.', 'cookiejar'); ?></p>
                        <?php endif; ?>
                      </div>
                    </div>
                    
                    <div class="cookiejar-card">
                      <h3><?php esc_html_e('Quick Support Actions', 'cookiejar'); ?></h3>
                      <div class="cookiejar-support-actions">
                        <button type="button" class="button" id="export-system-info">
                          <?php esc_html_e('Export System Info', 'cookiejar'); ?>
                        </button>
                        <button type="button" class="button" id="clear-cache-support">
                          <?php esc_html_e('Clear Cache', 'cookiejar'); ?>
                        </button>
                        <button type="button" class="button" id="run-health-check-support">
                          <?php esc_html_e('Run Health Check', 'cookiejar'); ?>
                        </button>
                        <button type="button" class="button" id="reset-settings">
                          <?php esc_html_e('Reset to Defaults', 'cookiejar'); ?>
                          </button>
                      </div>
                        </div>
                      </div>
                    </div>
                    
                <!-- General Settings Page -->
                <div id="page-general" class="cookiejar-page" style="display:none;">
                  <?php
                  $config = class_exists('\\DWIC\\Config') ? \DWIC\Config::all() : [];
                  $tier = cookiejar_get_tier();
                  $is_pro = cookiejar_is_pro();
                  ?>
                  <div class="cookiejar-settings-overview">
                    <div class="cookiejar-card" role="region" aria-labelledby="general-settings-title">
                      <div class="cookiejar-card-head">
                        <h3 id="general-settings-title" class="cookiejar-section-title"><?php esc_html_e('General Settings • Site Consent Options', 'cookiejar'); ?></h3>
                        <p class="cookiejar-lead"><?php esc_html_e('Basic options for most sites. Set consent duration, enable banner, and choose privacy essentials.', 'cookiejar'); ?></p>
                      </div>
                      
                      <form id="general-settings-form" class="cookiejar-settings-form" aria-describedby="general-settings-desc">
                        <div id="general-settings-desc" class="sr-only">
                          <?php esc_html_e('Basic settings for consent duration, banner enable, privacy policy.', 'cookiejar'); ?>
                        </div>
                        <div class="form-group">
                          <label for="consent_duration">
                            <?php esc_html_e('Consent Duration (days)', 'cookiejar'); ?>
                            <span class="cookiejar-tooltip" title="<?php esc_attr_e('How long user consent is remembered. Typical: 180 days.', 'cookiejar'); ?>">?</span>
                          </label>
                          <input type="number" min="30" max="365" step="1" name="consent_duration" id="consent_duration" 
                                 value="<?php echo esc_attr($config['consent_duration'] ?? 180); ?>" 
                                 class="regular-text" aria-label="<?php esc_attr_e('Consent Duration', 'cookiejar'); ?>">
                          <div class="description">
                            <?php esc_html_e('How long users\' choices are saved. GDPR max is 180 days.', 'cookiejar'); ?>
                          </div>
                        </div>
                        
                        <div class="form-group">
                          <label for="banner_enabled">
                            <?php esc_html_e('Show Consent Banner', 'cookiejar'); ?>
                            <span class="cookiejar-tooltip" title="<?php esc_attr_e('Enable or disable the banner for all visitors.', 'cookiejar'); ?>">?</span>
                          </label>
                          <select name="banner_enabled" id="banner_enabled" aria-label="<?php esc_attr_e('Enable Banner', 'cookiejar'); ?>">
                            <option value="yes"><?php esc_html_e('Yes', 'cookiejar'); ?></option>
                            <option value="no"><?php esc_html_e('No', 'cookiejar'); ?></option>
                          </select>
                          <div class="description"><?php esc_html_e('Turn off only if you want no consent collection.', 'cookiejar'); ?></div>
                        </div>
                        
                        <div class="form-group">
                          <label for="policy_url">
                            <?php esc_html_e('Privacy Policy Page', 'cookiejar'); ?>
                            <span class="cookiejar-tooltip" title="<?php esc_attr_e('Link to your privacy policy page.', 'cookiejar'); ?>">?</span>
                          </label>
                          <input type="url" name="policy_url" id="policy_url" 
                                 value="<?php echo esc_attr($config['policy_url'] ?? ''); ?>" 
                                 placeholder="https://example.com/privacy-policy" 
                                 class="regular-text" aria-label="<?php esc_attr_e('Privacy Policy URL', 'cookiejar'); ?>">
                          <div class="description"><?php esc_html_e('Paste a valid URL to your privacy policy.', 'cookiejar'); ?></div>
                        </div>
                          
                          <div class="cookiejar-settings-section">
                            <h4><?php esc_html_e('Advanced Configuration', 'cookiejar'); ?></h4>
                            <div class="cookiejar-form-grid">
                              <div class="cookiejar-form-group">
                                <label for="logging_mode"><?php esc_html_e('Logging Mode', 'cookiejar'); ?></label>
                                <select name="logging_mode" id="logging_mode">
                                  <option value="cached" <?php selected($config['logging_mode'] ?? 'cached', 'cached'); ?>><?php esc_html_e('Cached', 'cookiejar'); ?></option>
                                  <option value="live" <?php echo $is_pro ? '' : 'disabled'; ?>><?php esc_html_e('Live', 'cookiejar'); ?></option>
                                </select>
                                <div class="description"><?php esc_html_e('How consent data is logged and processed.', 'cookiejar'); ?></div>
                              </div>
                              
                              <div class="cookiejar-form-group">
                                <label for="geo_auto"><?php esc_html_e('Auto Geolocation', 'cookiejar'); ?></label>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                  <input type="checkbox" name="geo_auto" id="geo_auto" <?php checked($config['geo_auto'] ?? 'yes', 'yes'); ?> />
                                  <span><?php esc_html_e('Automatically detect user location', 'cookiejar'); ?></span>
                                </div>
                                <div class="description"><?php esc_html_e('Enable automatic location detection for compliance.', 'cookiejar'); ?></div>
                              </div>
                            </div>
                          </div>
                        
                        <div class="wizard-navigation">
                          <button type="submit" class="button button-primary" aria-label="<?php esc_attr_e('Save General Settings', 'cookiejar'); ?>">
                            <?php esc_html_e('Save General Settings', 'cookiejar'); ?>
                      </button>
                    </div>
                    
                        <div id="general-status" class="wizard-status" aria-live="polite" style="display:none;"></div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- Appearance Settings Page -->
                <div id="page-appearance" class="cookiejar-page" style="display:none;">
                  <?php
                  $banner_theme = $config['banner_theme'] ?? [];
                  ?>
                  <div class="cookiejar-settings-overview">
                    <div class="cookiejar-card" role="region" aria-labelledby="appearance-settings-title">
                      <div class="cookiejar-card-head">
                        <h3 id="appearance-settings-title" class="cookiejar-section-title"><?php esc_html_e('Appearance Settings • Banner Styling', 'cookiejar'); ?></h3>
                        <p class="cookiejar-lead"><?php esc_html_e('Customize the visual appearance of your cookie consent banner with step-by-step styling options.', 'cookiejar'); ?></p>
                      </div>
                      
                      <form id="appearance-settings-form" class="cookiejar-settings-form" aria-describedby="appearance-settings-desc">
                        <div id="appearance-settings-desc" class="sr-only">
                          <?php esc_html_e('Configure banner colors, typography, and text. Fields have inline help and tooltips for guidance.', 'cookiejar'); ?>
                        </div>
                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Step 1: Banner Configuration', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-grid">
                            <div class="cookiejar-form-group">
                              <label for="banner_color">
                                <?php esc_html_e('Primary Banner Color', 'cookiejar'); ?>
                                <span class="cookiejar-tooltip" title="<?php esc_attr_e('Change the main color used in your consent banner buttons and accents.', 'cookiejar'); ?>">?</span>
                              </label>
                              <div class="cookiejar-color-edit-wrap">
                                <input type="color" name="banner_color" id="banner_color" 
                                       value="<?php echo esc_attr($banner_theme['color'] ?? '#008ed6'); ?>" 
                                       class="cookiejar-color-palette" 
                                       aria-label="<?php esc_attr_e('Primary Banner Color', 'cookiejar'); ?>" />
                                <input type="text" id="banner_color_text" 
                                       value="<?php echo esc_attr($banner_theme['color'] ?? '#008ed6'); ?>" 
                                       class="cookiejar-color-text" 
                                       placeholder="#008ed6" 
                                       aria-label="<?php esc_attr_e('Primary Color Hex', 'cookiejar'); ?>" />
                                <span class="cookiejar-color-preview" 
                                      style="background-color: <?php echo esc_attr($banner_theme['color'] ?? '#008ed6'); ?>"
                                      title="<?php echo esc_attr($banner_theme['color'] ?? '#008ed6'); ?>"></span>
                              </div>
                              <div class="description"><?php esc_html_e('Accepts any valid hex color (e.g., #008ed6).', 'cookiejar'); ?></div>
                            </div>
                            
                            <div class="cookiejar-form-group">
                              <label for="banner_bg">
                                <?php esc_html_e('Background Color', 'cookiejar'); ?>
                                <span class="cookiejar-tooltip" title="<?php esc_attr_e('Background color for the banner container.', 'cookiejar'); ?>">?</span>
                              </label>
                              <div class="cookiejar-color-edit-wrap">
                                <input type="color" name="banner_bg" id="banner_bg" 
                                       value="<?php echo esc_attr($banner_theme['bg'] ?? '#ffffff'); ?>" 
                                       class="cookiejar-color-palette" 
                                       aria-label="<?php esc_attr_e('Banner Background Color', 'cookiejar'); ?>" />
                                <input type="text" id="banner_bg_text" 
                                       value="<?php echo esc_attr($banner_theme['bg'] ?? '#ffffff'); ?>" 
                                       class="cookiejar-color-text" 
                                       placeholder="#ffffff" 
                                       aria-label="<?php esc_attr_e('Background Color Hex', 'cookiejar'); ?>" />
                                <span class="cookiejar-color-preview" 
                                      style="background-color: <?php echo esc_attr($banner_theme['bg'] ?? '#ffffff'); ?>"
                                      title="<?php echo esc_attr($banner_theme['bg'] ?? '#ffffff'); ?>"></span>
                              </div>
                              <div class="description"><?php esc_html_e('Example: #ffffff for white.', 'cookiejar'); ?></div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Step 2: Typography Settings', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-grid">
                            <div class="cookiejar-form-group">
                              <label for="banner_font"><?php esc_html_e('Font Family', 'cookiejar'); ?></label>
                              <select name="banner_font" id="banner_font" class="cookiejar-font-select">
                                <option value="inherit" <?php selected($banner_theme['font'] ?? 'inherit', 'inherit'); ?>><?php esc_html_e('Inherit from site', 'cookiejar'); ?></option>
                                <option value="Arial, sans-serif" <?php selected($banner_theme['font'] ?? '', 'Arial, sans-serif'); ?>><?php esc_html_e('Arial (Modern)', 'cookiejar'); ?></option>
                                <option value="Georgia, serif" <?php selected($banner_theme['font'] ?? '', 'Georgia, serif'); ?>><?php esc_html_e('Georgia (Elegant)', 'cookiejar'); ?></option>
                                <option value="'Times New Roman', serif" <?php selected($banner_theme['font'] ?? '', "'Times New Roman', serif"); ?>><?php esc_html_e('Times New Roman (Classic)', 'cookiejar'); ?></option>
                                <option value="'Helvetica Neue', sans-serif" <?php selected($banner_theme['font'] ?? '', "'Helvetica Neue', sans-serif"); ?>><?php esc_html_e('Helvetica Neue (Clean)', 'cookiejar'); ?></option>
                              </select>
                              <div class="description"><?php esc_html_e('Select a font family that matches your website\'s design aesthetic.', 'cookiejar'); ?></div>
                            </div>
                            
                            <div class="cookiejar-form-group">
                              <label for="banner_font_size"><?php esc_html_e('Font Size (px)', 'cookiejar'); ?></label>
                              <div class="cookiejar-range-container">
                                <input type="range" name="banner_font_size_range" id="banner_font_size_range" 
                                       min="10" max="36" value="<?php echo esc_attr($banner_theme['font_size'] ?? 16); ?>" 
                                       class="cookiejar-range-input" />
                                <input type="number" name="banner_font_size" id="banner_font_size" 
                                       value="<?php echo esc_attr($banner_theme['font_size'] ?? 16); ?>" 
                                       min="10" max="36" class="cookiejar-number-input" />
                                <span class="cookiejar-font-preview" style="font-size: <?php echo esc_attr($banner_theme['font_size'] ?? 16); ?>px;"><?php esc_html_e('Preview Text', 'cookiejar'); ?></span>
                              </div>
                              <div class="description"><?php esc_html_e('Adjust the text size for optimal readability on all devices.', 'cookiejar'); ?></div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Step 3: Banner Text', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-group">
                            <label for="banner_text">
                              <?php esc_html_e('Banner Text', 'cookiejar'); ?>
                              <span class="cookiejar-tooltip" title="<?php esc_attr_e('The message users will see in the banner.', 'cookiejar'); ?>">?</span>
                            </label>
                            <textarea name="banner_text" id="banner_text" rows="3" 
                                      class="large-text" 
                                      placeholder="<?php esc_attr_e('We use cookies to enhance your experience...', 'cookiejar'); ?>" 
                                      aria-label="<?php esc_attr_e('Banner Text', 'cookiejar'); ?>"><?php echo esc_textarea($banner_theme['text'] ?? 'We use cookies to enhance your browsing experience and analyze our traffic.'); ?></textarea>
                            <div class="description"><?php esc_html_e('Example: "We use cookies to enhance your experience."', 'cookiejar'); ?></div>
                          </div>
                        </div>

                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Step 4: Branding Footer', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-group">
                            <?php 
                              $branding_enabled = get_option('dwic_branding_enabled', 'yes');
                              $branding_html = get_option('dwic_branding_html', '<div class="dwic-footer" aria-hidden="true">Cookie consent provided by CookieJar</div>');
                            ?>
                            <label>
                              <input type="checkbox" name="branding_enabled" value="yes" <?php checked($branding_enabled, 'yes'); ?> />
                              <?php esc_html_e('Show small "Powered by CookieJar" footer on banner', 'cookiejar'); ?>
                            </label>
                            <div class="description"><?php esc_html_e('You can customize or remove this footer. Leave empty to hide.', 'cookiejar'); ?></div>
                          </div>
                          <div class="cookiejar-form-group">
                            <label for="branding_html"><?php esc_html_e('Branding HTML', 'cookiejar'); ?></label>
                            <textarea id="branding_html" name="branding_html" rows="2" class="large-text" aria-label="<?php esc_attr_e('Branding HTML', 'cookiejar'); ?>"><?php echo esc_textarea($branding_html); ?></textarea>
                          </div>
                        </div>
                        
                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Step 5: Live Preview', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-group">
                            <label><?php esc_html_e('Live Preview', 'cookiejar'); ?></label>
                            <div class="cookiejar-banner-preview" id="banner-preview" aria-live="polite" tabindex="0" style="outline:none;">
                              <div class="cookiejar-preview-banner" style="background-color: <?php echo esc_attr($banner_theme['bg'] ?? '#ffffff'); ?>; color: <?php echo esc_attr($banner_theme['color'] ?? '#008ed6'); ?>; font-family: <?php echo esc_attr($banner_theme['font'] ?? 'inherit'); ?>; font-size: <?php echo esc_attr($banner_theme['font_size'] ?? 16); ?>px;">
                                <div class="cookiejar-preview-content">
                                  <p id="banner-preview-text"><?php echo esc_html($banner_theme['text'] ?? 'We use cookies to enhance your browsing experience and analyze our traffic.'); ?></p>
                                  <div class="cookiejar-preview-buttons">
                                    <button class="cookiejar-preview-btn cookiejar-preview-accept" style="background-color: <?php echo esc_attr($banner_theme['color'] ?? '#008ed6'); ?>;" aria-label="<?php esc_attr_e('Accept All', 'cookiejar'); ?>"><?php esc_html_e('Accept All', 'cookiejar'); ?></button>
                                    <button class="cookiejar-preview-btn cookiejar-preview-settings" aria-label="<?php esc_attr_e('Settings', 'cookiejar'); ?>"><?php esc_html_e('Settings', 'cookiejar'); ?></button>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <div class="description"><?php esc_html_e('Preview updates live with color/text changes.', 'cookiejar'); ?></div>
                          </div>
                        </div>
                        
                        <div class="wizard-navigation">
                          <button type="submit" class="button button-primary" aria-label="<?php esc_attr_e('Save Appearance Settings', 'cookiejar'); ?>">
                            <?php esc_html_e('Save Appearance Settings', 'cookiejar'); ?>
                          </button>
                          <button type="button" class="button button-secondary" id="preview-banner" aria-label="<?php esc_attr_e('Preview Banner', 'cookiejar'); ?>">
                            <?php esc_html_e('Preview Banner', 'cookiejar'); ?>
                          </button>
                          <button type="button" class="button" id="reset-appearance-settings" aria-label="<?php esc_attr_e('Reset to Defaults', 'cookiejar'); ?>">
                            <?php esc_html_e('Reset to Defaults', 'cookiejar'); ?>
                          </button>
                        </div>
                        
                        <div id="appearance-status" class="wizard-status" aria-live="polite" style="display:none;"></div>
                      </form>
                      
                      <div id="banner-preview" class="cookiejar-banner-preview" style="margin-top: 20px; display: none;">
                        <h4><?php esc_html_e('Banner Preview', 'cookiejar'); ?></h4>
                        <div id="banner-preview-content"></div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Compliance Settings Page -->
                <div id="page-compliance" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-settings-overview">
                    <div class="cookiejar-card" role="region" aria-labelledby="compliance-settings-title">
                      <div class="cookiejar-card-head">
                        <h3 id="compliance-settings-title" class="cookiejar-section-title"><?php esc_html_e('Compliance Settings • Regulatory Framework', 'cookiejar'); ?></h3>
                        <p class="cookiejar-lead"><?php esc_html_e('Configure compliance settings for GDPR, CCPA, and LGPD regulations with live status indicators.', 'cookiejar'); ?></p>
                      </div>
                      
                      <form id="compliance-settings-form" class="cookiejar-settings-form">
                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Compliance Settings', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-grid">
                            <div class="cookiejar-form-group">
                              <label for="cookiejar-gdpr-mode"><?php esc_html_e('Enable GDPR Mode', 'cookiejar'); ?></label>
                              <select id="cookiejar-gdpr-mode" name="gdpr_mode">
                                <option value="yes" <?php selected($config['gdpr_mode'] ?? 'yes', 'yes'); ?>><?php esc_html_e('Yes', 'cookiejar'); ?></option>
                                <option value="no" <?php selected($config['gdpr_mode'] ?? 'yes', 'no'); ?>><?php esc_html_e('No', 'cookiejar'); ?></option>
                              </select>
                              <div class="description"><?php esc_html_e('Enable GDPR compliance features.', 'cookiejar'); ?></div>
                            </div>
                            <div class="cookiejar-form-group">
                              <label for="cookiejar-ccpa-mode"><?php esc_html_e('Enable CCPA Mode', 'cookiejar'); ?></label>
                              <select id="cookiejar-ccpa-mode" name="ccpa_mode" <?php echo $is_pro ? '' : 'disabled'; ?>>
                                <option value="yes" <?php selected($config['ccpa_mode'] ?? 'no', 'yes'); ?>><?php esc_html_e('Yes', 'cookiejar'); ?></option>
                                <option value="no" <?php selected($config['ccpa_mode'] ?? 'no', 'no'); ?>><?php esc_html_e('No', 'cookiejar'); ?></option>
                              </select>
                              <div class="description"><?php esc_html_e('Enable CCPA compliance features.', 'cookiejar'); ?></div>
                              <?php if (!$is_pro): ?>
                                <div class="cookiejar-pro-notice"><?php esc_html_e('Pro Only', 'cookiejar'); ?></div>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                        
                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Policy Configuration', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-group">
                            <label for="cookiejar-policy-url"><?php esc_html_e('Policy URL', 'cookiejar'); ?></label>
                            <input type="url" id="cookiejar-policy-url" name="policy_url" value="<?php echo esc_attr($config['policy_url'] ?? ''); ?>" placeholder="https://yoursite.com/privacy-policy">
                            <div class="description"><?php esc_html_e('Link to your privacy/cookie policy page.', 'cookiejar'); ?></div>
                          </div>
                        </div>
                        
                        <div class="cookiejar-form-actions">
                          <button type="submit" class="button button-primary"><?php esc_html_e('Save Compliance Settings', 'cookiejar'); ?></button>
                          <button type="button" class="button button-secondary" id="reset-compliance-settings"><?php esc_html_e('Reset to Defaults', 'cookiejar'); ?></button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- Security Settings Page -->
                <div id="page-security" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-settings-overview">
                    <div class="cookiejar-card">
                      <h3><?php esc_html_e('Security Settings', 'cookiejar'); ?></h3>
                      <p><?php esc_html_e('Configure security and privacy protection settings for your CookieJar installation.', 'cookiejar'); ?></p>
                      
                      <form id="security-settings-form" class="cookiejar-settings-form">
                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Privacy Protection', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-grid">
                            <div class="cookiejar-form-group">
                              <label>
                                <input type="checkbox" name="strict_privacy" <?php checked($config['strict_privacy'] ?? 'no', 'yes'); ?> />
                                <?php esc_html_e('Enable Strict Privacy Mode', 'cookiejar'); ?>
                              </label>
                              <div class="description"><?php esc_html_e('Enhanced privacy protection with minimal data collection', 'cookiejar'); ?></div>
                            </div>
                            
                            <div class="cookiejar-form-group">
                              <label>
                                <input type="checkbox" name="anonymize_ips" <?php checked($config['anonymize_ips'] ?? 'yes', 'yes'); ?> />
                                <?php esc_html_e('Anonymize IP Addresses', 'cookiejar'); ?>
                              </label>
                              <div class="description"><?php esc_html_e('Mask IP addresses for privacy compliance', 'cookiejar'); ?></div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Debug & Logging', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-grid">
                            <div class="cookiejar-form-group">
                              <label>
                                <input type="checkbox" name="debug_mode" <?php checked($config['debug_mode'] ?? 'no', 'yes'); ?> />
                                <?php esc_html_e('Enable Debug Mode', 'cookiejar'); ?>
                              </label>
                              <div class="description"><?php esc_html_e('Show debug information for troubleshooting', 'cookiejar'); ?></div>
                            </div>
                            
                            <div class="cookiejar-form-group">
                              <label for="log_retention"><?php esc_html_e('Log Retention (days)', 'cookiejar'); ?></label>
                              <input type="number" name="log_retention" id="log_retention" 
                                     value="<?php echo esc_attr($config['log_prune_days'] ?? 365); ?>" 
                                     min="30" max="<?php echo $is_pro ? 3650 : 365; ?>" />
                              <div class="description">
                                <?php esc_html_e('How long to keep consent logs.', 'cookiejar'); ?>
                                <?php if (!$is_pro): ?>
                                  <span class="cookiejar-pro-lock"><?php esc_html_e('365-day max in Basic', 'cookiejar'); ?></span>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="cookiejar-form-actions">
                          <button type="submit" class="button button-primary"><?php esc_html_e('Save Security Settings', 'cookiejar'); ?></button>
                          <button type="button" class="button button-secondary" id="reset-security-settings"><?php esc_html_e('Reset to Defaults', 'cookiejar'); ?></button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- Performance Settings Page -->
                <div id="page-performance" class="cookiejar-page" style="display:none;">
                  <?php
                  $cache_stats = class_exists('\\DWIC\\Cache') ? \DWIC\Cache::stats() : [];
                  ?>
                  <div class="cookiejar-settings-overview">
                    <div class="cookiejar-card">
                      <h3><?php esc_html_e('Performance Settings', 'cookiejar'); ?></h3>
                      <p><?php esc_html_e('Optimize CookieJar performance with caching and asset optimization settings.', 'cookiejar'); ?></p>
                      
                      <form id="performance-settings-form" class="cookiejar-settings-form">
                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Caching Configuration', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-grid">
                            <div class="cookiejar-form-group">
                              <label>
                                <input type="checkbox" name="enable_caching" <?php checked($config['enable_caching'] ?? 'yes', 'yes'); ?> />
                                <?php esc_html_e('Enable Caching', 'cookiejar'); ?>
                              </label>
                              <div class="description"><?php esc_html_e('Cache analytics and settings for better performance', 'cookiejar'); ?></div>
                            </div>
                            
                            <div class="cookiejar-form-group">
                              <label for="cache_ttl"><?php esc_html_e('Cache Duration (minutes)', 'cookiejar'); ?></label>
                              <input type="number" name="cache_ttl" id="cache_ttl" 
                                     value="<?php echo esc_attr($config['cache_ttl'] ?? 5); ?>" 
                                     min="1" max="60" />
                              <div class="description"><?php esc_html_e('How long to cache data before refreshing.', 'cookiejar'); ?></div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Asset Optimization', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-grid">
                            <div class="cookiejar-form-group">
                              <label>
                                <input type="checkbox" name="minify_assets" <?php checked($config['minify_assets'] ?? 'no', 'yes'); ?> <?php echo $is_pro ? '' : 'disabled'; ?> />
                                <?php esc_html_e('Minify CSS/JS Assets', 'cookiejar'); ?>
                                <?php if (!$is_pro): ?>
                                  <span class="cookiejar-pro-lock"><?php esc_html_e('Pro Only', 'cookiejar'); ?></span>
                                <?php endif; ?>
                              </label>
                              <div class="description"><?php esc_html_e('Compress CSS and JavaScript files for faster loading.', 'cookiejar'); ?></div>
                            </div>
                            
                            <div class="cookiejar-form-group">
                              <label>
                                <input type="checkbox" name="lazy_load" <?php checked($config['lazy_load'] ?? 'no', 'yes'); ?> <?php echo $is_pro ? '' : 'disabled'; ?> />
                                <?php esc_html_e('Lazy Load Components', 'cookiejar'); ?>
                                <?php if (!$is_pro): ?>
                                  <span class="cookiejar-pro-lock"><?php esc_html_e('Pro Only', 'cookiejar'); ?></span>
                                <?php endif; ?>
                              </label>
                              <div class="description"><?php esc_html_e('Load components only when needed to improve page speed.', 'cookiejar'); ?></div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="cookiejar-settings-section">
                          <h4><?php esc_html_e('Cache Statistics', 'cookiejar'); ?></h4>
                          <div class="cookiejar-form-group">
                            <div class="cookiejar-status info">
                              <strong><?php esc_html_e('Total Caches:', 'cookiejar'); ?></strong> <?php echo esc_html($cache_stats['total_caches'] ?? 0); ?><br>
                              <strong><?php esc_html_e('Memory Usage:', 'cookiejar'); ?></strong> <?php echo esc_html(size_format($cache_stats['memory_usage'] ?? 0)); ?><br>
                              <strong><?php esc_html_e('Hit Rate:', 'cookiejar'); ?></strong> <?php echo esc_html(($cache_stats['hit_rate'] ?? 0) . '%'); ?>
                            </div>
                          </div>
                        </div>
                        
                        <div class="cookiejar-form-actions">
                          <button type="submit" class="button button-primary"><?php esc_html_e('Save Performance Settings', 'cookiejar'); ?></button>
                          <button type="button" class="button button-secondary" id="clear-all-cache"><?php esc_html_e('Clear All Cache', 'cookiejar'); ?></button>
                          <button type="button" class="button button-secondary" id="reset-performance-settings"><?php esc_html_e('Reset to Defaults', 'cookiejar'); ?></button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- Integrations Settings Page -->
                <div id="page-integrations" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-settings-overview">
                    <div class="cookiejar-card">
                      <h3><?php esc_html_e('Integration Settings', 'cookiejar'); ?></h3>
                      <form id="integration-settings-form" class="cookiejar-settings-form">
                        <div class="cookiejar-form-grid">
                          <div class="cookiejar-form-group">
                            <label for="ga_tracking_id"><?php esc_html_e('Google Analytics Tracking ID', 'cookiejar'); ?></label>
                            <input type="text" name="ga_tracking_id" id="ga_tracking_id" 
                                   value="<?php echo esc_attr($config['ga_tracking_id'] ?? ''); ?>" 
                                   placeholder="G-XXXXXXXXXX" />
                          </div>
                          
                          <div class="cookiejar-form-group">
                            <label>
                              <input type="checkbox" name="ga_advanced" <?php checked($config['ga_advanced'] ?? 'no', 'yes'); ?> />
                              <?php esc_html_e('Enable Advanced GA Features', 'cookiejar'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Enhanced Google Analytics integration with consent mode', 'cookiejar'); ?></p>
                          </div>
                          
                          <div class="cookiejar-form-group">
                            <label for="gtm_container_id"><?php esc_html_e('Google Tag Manager ID', 'cookiejar'); ?></label>
                            <input type="text" name="gtm_container_id" id="gtm_container_id" 
                                   value="<?php echo esc_attr($config['gtm_container_id'] ?? ''); ?>" 
                                   placeholder="GTM-XXXXXXX" <?php echo $is_pro ? '' : 'disabled'; ?> />
                            <?php if (!$is_pro): ?>
                              <span class="cookiejar-pro-lock"><?php esc_html_e('Pro Only', 'cookiejar'); ?></span>
                            <?php endif; ?>
                          </div>
                          
                          <div class="cookiejar-form-group">
                            <label for="facebook_pixel_id"><?php esc_html_e('Facebook Pixel ID', 'cookiejar'); ?></label>
                            <input type="text" name="facebook_pixel_id" id="facebook_pixel_id" 
                                   value="<?php echo esc_attr($config['facebook_pixel_id'] ?? ''); ?>" 
                                   placeholder="123456789012345" <?php echo $is_pro ? '' : 'disabled'; ?> />
                            <?php if (!$is_pro): ?>
                              <span class="cookiejar-pro-lock"><?php esc_html_e('Pro Only', 'cookiejar'); ?></span>
                            <?php endif; ?>
                          </div>
                          
                          <div class="cookiejar-form-group">
                            <label for="custom_scripts"><?php esc_html_e('Custom Scripts', 'cookiejar'); ?></label>
                            <textarea name="custom_scripts" id="custom_scripts" rows="5" <?php echo $is_pro ? '' : 'disabled'; ?>><?php echo esc_textarea($config['custom_scripts'] ?? ''); ?></textarea>
                            <?php if (!$is_pro): ?>
                              <span class="cookiejar-pro-lock"><?php esc_html_e('Pro Only', 'cookiejar'); ?></span>
                            <?php endif; ?>
                          </div>
                        </div>
                        
                        <div class="cookiejar-form-actions">
                          <button type="submit" class="button button-primary"><?php esc_html_e('Save Integration Settings', 'cookiejar'); ?></button>
                          <a href="https://analytics.google.com/" target="_blank" class="button"><?php esc_html_e('Setup Google Analytics', 'cookiejar'); ?></a>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- Backup & Restore Settings Page -->
                <div id="page-backup" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-settings-overview">
                    <div class="cookiejar-card">
                      <h3><?php esc_html_e('Backup & Restore', 'cookiejar'); ?></h3>
                      
                      <div class="cookiejar-backup-section">
                        <h4><?php esc_html_e('Export Settings', 'cookiejar'); ?></h4>
                        <p><?php esc_html_e('Download your current plugin settings as a JSON file.', 'cookiejar'); ?></p>
                        <button type="button" class="button button-primary" id="export-settings">
                          <?php esc_html_e('Export Settings', 'cookiejar'); ?>
                        </button>
                      </div>
                      
                      <div class="cookiejar-restore-section">
                        <h4><?php esc_html_e('Import Settings', 'cookiejar'); ?></h4>
                        <p><?php esc_html_e('Upload a previously exported settings file to restore your configuration.', 'cookiejar'); ?></p>
                        <form id="import-settings-form" enctype="multipart/form-data">
                          <input type="file" name="settings_file" id="settings_file" accept=".json" />
                          <button type="submit" class="button"><?php esc_html_e('Import Settings', 'cookiejar'); ?></button>
                        </form>
                      </div>
                      
                      <div class="cookiejar-reset-section">
                        <h4><?php esc_html_e('Reset to Defaults', 'cookiejar'); ?></h4>
                        <p><?php esc_html_e('Reset all plugin settings to their default values. This action cannot be undone.', 'cookiejar'); ?></p>
                        <button type="button" class="button button-secondary" id="reset-all-settings">
                          <?php esc_html_e('Reset All Settings', 'cookiejar'); ?>
                        </button>
                      </div>
                      
                      <div class="cookiejar-backup-info">
                        <h4><?php esc_html_e('Backup Information', 'cookiejar'); ?></h4>
                        <ul>
                          <li><?php esc_html_e('Last Export:', 'cookiejar'); ?> <span id="last-export-date"><?php echo esc_html(get_option('cookiejar_last_export', __('Never', 'cookiejar'))); ?></span></li>
                          <li><?php esc_html_e('Settings Version:', 'cookiejar'); ?> <span><?php echo esc_html(DWIC_VERSION); ?></span></li>
                          <li><?php esc_html_e('Total Settings:', 'cookiejar'); ?> <span><?php echo esc_html(count($config)); ?></span></li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>

                

                <!-- Default content for other pages -->
                <div id="page-default" class="cookiejar-page" style="display:none;">
                  <div class="cookiejar-card">
                    <h3><?php esc_html_e('Welcome to CookieJar Control Panel','cookiejar');?></h3>
                    <p><?php esc_html_e('Select a section from the sidebar to manage your cookie consent settings.','cookiejar');?></p>
                  </div>
                </div>

              </div>

              <div class="cookiejar-cp-footer">
                <div class="cookiejar-footer-tile">
                  <a href="#page=banner" class="cookiejar-footer-link cookiejar-nav-link" data-page="banner"><?php esc_html_e('Manage Cookies Banner','cookiejar');?></a>
                </div>
                <div class="cookiejar-footer-actions-right">
                  <a href="#page=settings" class="cookiejar-footer-link cookiejar-nav-link" data-page="settings"><?php esc_html_e('Import / Export Settings','cookiejar');?></a>
                  <a href="#" class="cookiejar-footer-link dim" id="reset-all-settings"><?php esc_html_e('Reset to Default','cookiejar');?></a>
                </div>
              </div>
            </main>
          </div>

          <p class="cookiejaropyright">
            CookieJar WordPress Plugin by
            <a href="<?php echo esc_url( admin_url('admin.php?page=cookiejar-control#page=documentation') );?>" rel="noopener noreferrer">
              <?php esc_html_e('Documentation','cookiejar');?>
            </a>
            Software Proprietary and the intellectual property of My Deme, Llc.
            <?php echo esc_html( gmdate('Y') ); ?> © All Rights Reserved.
          </p>
        </div>
        <?php
    }

    public function ajax_recent_logs(){
        if(!current_user_can('manage_options')) wp_send_json_error('no_perms');

        $count = isset($_GET['count']) ? max(1, min(50, intval($_GET['count']))) : 12;

        if (!method_exists('\\DWIC\\DB','get_logs')){
            wp_send_json_success([]);
        }

        $logs = \DWIC\DB::get_logs($count);
        wp_send_json_success(is_array($logs) ? $logs : []);
    }

    public function ajax_reset_notice(){
        if(!current_user_can('manage_options')) wp_send_json_error('no_perms');
        
        // Reset consent notice by clearing the consent cookie
        if(isset($_POST['reset']) && $_POST['reset'] === '1') {
            // Clear the consent cookie
            setcookie('dwic_consent_v2', '', time() - 3600, '/');
            wp_send_json_success(['message' => 'Consent notice has been reset. Users will see the banner again.']);
        }
        
        wp_send_json_error('Invalid request');
    }

    public function ajax_banner_settings(){
        if(!current_user_can('manage_options')) wp_send_json_error('no_perms');
        if (!wp_verify_nonce($_GET['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error('bad_nonce');
        }
        
        // Get current banner settings
        $settings = [
            'enabled' => get_option('dwic_banner_enabled', '1'),
            'position' => get_option('dwic_banner_position', 'bottom'),
            'theme' => get_option('dwic_banner_theme', 'light'),
            'categories' => get_option('dwic_categories', []),
            'custom_css' => get_option('dwic_custom_css', ''),
        ];
        
        wp_send_json_success($settings);
    }

    public function ajax_save_banner_settings(){
        if(!current_user_can('manage_options')) wp_send_json_error('no_perms');
        if (!wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error('bad_nonce');
        }
        
        $banner_enabled = isset($_POST['banner_enabled']) ? '1' : '0';
        $banner_position = sanitize_text_field($_POST['banner_position'] ?? 'bottom');
        $banner_theme = sanitize_text_field($_POST['banner_theme'] ?? 'light');
        
        update_option('dwic_banner_enabled', $banner_enabled);
        update_option('dwic_banner_position', $banner_position);
        update_option('dwic_banner_theme', $banner_theme);
        
        wp_send_json_success(['message' => 'Banner settings saved successfully']);
    }

    public function ajax_save_language_settings(){
        if(!current_user_can('manage_options')) wp_send_json_error('no_perms');
        
        $default_language = sanitize_text_field($_POST['default_language'] ?? 'en');
        $custom_translations = sanitize_textarea_field($_POST['custom_translations'] ?? '');
        
        update_option('dwic_default_language', $default_language);
        update_option('dwic_custom_translations', $custom_translations);
        
        wp_send_json_success(['message' => 'Language settings saved successfully']);
    }

    public function ajax_save_advanced_settings(){
        if(!current_user_can('manage_options')) wp_send_json_error('no_perms');
        
        $geotargeting_enabled = isset($_POST['geotargeting_enabled']) ? '1' : '0';
        $consent_duration = intval($_POST['consent_duration'] ?? 180);
        $custom_css = sanitize_textarea_field($_POST['custom_css'] ?? '');
        
        update_option('dwic_geotargeting_enabled', $geotargeting_enabled);
        update_option('dwic_consent_duration', $consent_duration);
        update_option('dwic_custom_css', $custom_css);
        
        wp_send_json_success(['message' => 'Advanced settings saved successfully']);
    }

    public function ajax_clear_cache(){
        if(!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }
        
        if (class_exists('\\DWIC\\Cache')) {
            $cleared = \DWIC\Cache::clear_all();
            if (class_exists('\\DWIC\\Logger')) {
                \DWIC\Logger::info('Cache cleared manually by admin');
            }
            wp_send_json_success(['message' => 'Cache cleared successfully', 'cleared' => $cleared]);
        } else {
            wp_send_json_error(['error' => 'Cache system not available']);
        }
    }

    public function ajax_warm_cache(){
        if(!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }
        
        if (class_exists('\\DWIC\\Cache')) {
            $warmed = \DWIC\Cache::warm_up();
            if (class_exists('\\DWIC\\Logger')) {
                \DWIC\Logger::info('Cache warmed manually by admin');
            }
            wp_send_json_success(['message' => 'Cache warmed successfully', 'warmed' => $warmed]);
        } else {
            wp_send_json_error(['error' => 'Cache system not available']);
        }
    }

    public function ajax_health_check(){
        if(!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }
        
        if (class_exists('\\DWIC\\Monitor')) {
            $health_status = \DWIC\Monitor::health_check();
            if (class_exists('\\DWIC\\Logger')) {
                \DWIC\Logger::info('Health check run manually by admin', ['status' => $health_status['overall_status']]);
            }
            wp_send_json_success(['message' => 'Health check completed', 'status' => $health_status]);
        } else {
            wp_send_json_error(['error' => 'Monitor system not available']);
        }
    }

    public function ajax_clear_logs(){
        if(!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }
        
        if (class_exists('\\DWIC\\Logger')) {
            $cleared = \DWIC\Logger::clear_old(30); // Clear logs older than 30 days
            \DWIC\Logger::info('Old logs cleared manually by admin', ['cleared_count' => $cleared]);
            wp_send_json_success(['message' => 'Old logs cleared successfully', 'cleared' => $cleared]);
        } else {
            wp_send_json_error(['error' => 'Logger system not available']);
        }
    }

    public function ajax_save_general_settings() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error' => 'no_perms'], 403);
        }
        
        $is_pro = cookiejar_is_pro();
        
        $settings = [
            'consent_storage_mode' => \DWIC\Validator::sanitize($_POST['consent_storage_mode'] ?? 'hash'),
            'consent_duration' => max(1, min($is_pro ? 365 : 180, intval($_POST['consent_duration'] ?? 180))),
            'logging_mode' => \DWIC\Validator::sanitize($_POST['logging_mode'] ?? 'cached'),
            'geo_auto' => isset($_POST['geo_auto']) ? 'yes' : 'no',
        ];
        
        // Save to config
        \DWIC\Config::set('hash_enabled', $settings['consent_storage_mode'] === 'hash' ? 'yes' : 'no');
        \DWIC\Config::set('consent_duration', $settings['consent_duration']);
        \DWIC\Config::set('logging_mode', $settings['logging_mode']);
        \DWIC\Config::set('geo_auto', $settings['geo_auto']);

        // Persist auto-updates option
        $auto_updates = isset($_POST['auto_updates']) ? 'yes' : 'no';
        update_option('cookiejar_auto_updates', $auto_updates, false);
        
        // Log the change
        if (class_exists('\\DWIC\\Logger')) {
            \DWIC\Logger::info('General settings updated', $settings);
        }
        
        wp_send_json_success(['message' => 'General settings saved successfully']);
    }

    public function ajax_save_appearance_settings() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error' => 'no_perms'], 403);
        }
        
        $banner_theme = [
            'color' => \DWIC\Validator::color($_POST['banner_color'] ?? '#008ed6'),
            'bg' => \DWIC\Validator::color($_POST['banner_bg'] ?? '#ffffff'),
            'font' => \DWIC\Validator::sanitize($_POST['banner_font'] ?? 'inherit'),
            'font_size' => max(10, min(36, intval($_POST['banner_font_size'] ?? 16))),
        ];
        
        \DWIC\Config::set('banner_theme', $banner_theme);

        // Mirror colors to frontend dynamic options for immediate effect
        $custom_colors = [
            'primary' => $banner_theme['color'] ?: '#008ed6',
            'background' => $banner_theme['bg'] ?: '#ffffff',
            'text' => '#222222',
            'accent' => '#0075b2'
        ];
        update_option('dwic_custom_colors', $custom_colors, false);

        // Save optional banner text prompt for frontend banner
        if (isset($_POST['banner_text'])) {
            $prompt = sanitize_textarea_field($_POST['banner_text']);
            update_option('dwic_message', $prompt, false);
        }

        // Save branding footer controls
        $branding_enabled = isset($_POST['branding_enabled']) && $_POST['branding_enabled'] === 'yes' ? 'yes' : 'no';
        update_option('dwic_branding_enabled', $branding_enabled, false);
        if (isset($_POST['branding_html'])) {
            $branding_html = wp_kses_post($_POST['branding_html']);
            update_option('dwic_branding_html', $branding_html, false);
        }
        
        if (class_exists('\\DWIC\\Logger')) {
            \DWIC\Logger::info('Appearance settings updated', $banner_theme);
        }
        
        wp_send_json_success(['message' => 'Appearance settings saved successfully']);
    }

    public function ajax_save_compliance_settings() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error' => 'no_perms'], 403);
        }
        
        $is_pro = cookiejar_is_pro();
        
        $settings = [
            'gdpr_mode' => isset($_POST['gdpr_enabled']) ? 'yes' : 'no',
            'ccpa_mode' => ($is_pro && isset($_POST['ccpa_enabled'])) ? 'yes' : 'no',
            'lgpd_mode' => ($is_pro && isset($_POST['lgpd_enabled'])) ? 'yes' : 'no',
            'policy_url' => \DWIC\Validator::url($_POST['policy_url'] ?? ''),
        ];
        
        foreach ($settings as $key => $value) {
            \DWIC\Config::set($key, $value);
        }
        
        if (class_exists('\\DWIC\\Logger')) {
            \DWIC\Logger::info('Compliance settings updated', $settings);
        }
        
        wp_send_json_success(['message' => 'Compliance settings saved successfully']);
    }

    public function ajax_save_security_settings() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error' => 'no_perms'], 403);
        }
        
        $is_pro = cookiejar_is_pro();
        
        $settings = [
            'strict_privacy' => isset($_POST['strict_privacy']) ? 'yes' : 'no',
            'debug_mode' => isset($_POST['debug_mode']) ? 'yes' : 'no',
            'log_prune_days' => max(30, min($is_pro ? 3650 : 365, intval($_POST['log_retention'] ?? 365))),
            'anonymize_ips' => isset($_POST['anonymize_ips']) ? 'yes' : 'no',
        ];
        
        foreach ($settings as $key => $value) {
            \DWIC\Config::set($key, $value);
        }
        
        if (class_exists('\\DWIC\\Logger')) {
            \DWIC\Logger::info('Security settings updated', $settings);
        }
        
        wp_send_json_success(['message' => 'Security settings saved successfully']);
    }

    public function ajax_save_performance_settings() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error' => 'no_perms'], 403);
        }
        
        $is_pro = cookiejar_is_pro();
        
        $settings = [
            'enable_caching' => isset($_POST['enable_caching']) ? 'yes' : 'no',
            'cache_ttl' => max(1, min(60, intval($_POST['cache_ttl'] ?? 5))),
            'minify_assets' => ($is_pro && isset($_POST['minify_assets'])) ? 'yes' : 'no',
            'lazy_load' => ($is_pro && isset($_POST['lazy_load'])) ? 'yes' : 'no',
        ];
        
        foreach ($settings as $key => $value) {
            \DWIC\Config::set($key, $value);
        }
        
        if (class_exists('\\DWIC\\Logger')) {
            \DWIC\Logger::info('Performance settings updated', $settings);
        }
        
        wp_send_json_success(['message' => 'Performance settings saved successfully']);
    }

    public function ajax_save_integration_settings() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error' => 'no_perms'], 403);
        }
        
        $is_pro = cookiejar_is_pro();
        
        $settings = [
            'ga_tracking_id' => \DWIC\Validator::sanitize($_POST['ga_tracking_id'] ?? ''),
            'ga_advanced' => isset($_POST['ga_advanced']) ? 'yes' : 'no',
            'gtm_container_id' => $is_pro ? \DWIC\Validator::sanitize($_POST['gtm_container_id'] ?? '') : '',
            'facebook_pixel_id' => $is_pro ? \DWIC\Validator::sanitize($_POST['facebook_pixel_id'] ?? '') : '',
            'custom_scripts' => $is_pro ? \DWIC\Validator::sanitize($_POST['custom_scripts'] ?? '') : '',
        ];
        
        foreach ($settings as $key => $value) {
            \DWIC\Config::set($key, $value);
        }
        
        if (class_exists('\\DWIC\\Logger')) {
            \DWIC\Logger::info('Integration settings updated', $settings);
        }
        
        wp_send_json_success(['message' => 'Integration settings saved successfully']);
    }

    public function ajax_backup_settings() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error' => 'no_perms'], 403);
        }
        
        $config = \DWIC\Config::all();
        $backup_data = [
            'version' => DWIC_VERSION,
            'timestamp' => current_time('mysql'),
            'settings' => $config
        ];
        
        // Update last export time
        update_option('cookiejar_last_export', current_time('mysql'));
        
        if (class_exists('\\DWIC\\Logger')) {
            \DWIC\Logger::info('Settings exported by admin');
        }
        
        // Return JSON data for download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="cookiejar-settings-' . gmdate('Y-m-d-H-i-s') . '.json"');
        echo json_encode($backup_data, JSON_PRETTY_PRINT);
        wp_die();
    }

    public function ajax_restore_settings() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error' => 'no_perms'], 403);
        }
        
        if (empty($_FILES['settings_file']['tmp_name'])) {
            wp_send_json_error(['error' => 'No file uploaded']);
        }
        
        $file_content = file_get_contents($_FILES['settings_file']['tmp_name']);
        $backup_data = json_decode($file_content, true);
        
        if (!$backup_data || !isset($backup_data['settings'])) {
            wp_send_json_error(['error' => 'Invalid backup file']);
        }
        
        // Restore settings
        $restored_count = 0;
        foreach ($backup_data['settings'] as $key => $value) {
            \DWIC\Config::set($key, $value);
            $restored_count++;
        }
        
        if (class_exists('\\DWIC\\Logger')) {
            \DWIC\Logger::info('Settings restored by admin', ['count' => $restored_count]);
        }
        
        wp_send_json_success(['message' => "Successfully restored {$restored_count} settings"]);
    }

    public function ajax_export_logs(){
        if(!current_user_can('manage_options')) wp_send_json_error('no_perms');
        
        $logs = \DWIC\DB::get_logs(1000); // Export up to 1000 logs
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cookiejar-consent-logs-' . gmdate('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['IP', 'Country', 'Consent', 'Created At']);
        
        foreach($logs as $log) {
            fputcsv($output, [
                $log['ip'] ?? '',
                $log['country'] ?? '',
                $log['consent'] ?? '',
                $log['created_at'] ?? ''
            ]);
        }
        
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream, not a filesystem file
        fclose($output);
        exit;
    }

    public function ajax_export_basic_report(){
        if(!current_user_can('manage_options')) wp_die('Unauthorized');
        if(!wp_verify_nonce($_GET['_wpnonce'], 'cookiejar_export')) wp_die('Invalid nonce');
        
        $logs = class_exists('\\DWIC\\DB') ? \DWIC\DB::get_logs(1000) : [];
        $stats = class_exists('\\DWIC\\DB') ? \DWIC\DB::get_stats() : [];
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cookiejar-basic-report-' . gmdate('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add report header
        fputcsv($output, ['CookieJar Basic Report - Generated: ' . gmdate('Y-m-d H:i:s')]);
        fputcsv($output, []);
        
        // Add summary stats
        fputcsv($output, ['Summary Statistics']);
        fputcsv($output, ['Total Consents', $stats['total_consents'] ?? 0]);
        fputcsv($output, ['Full Consents', $stats['full_consents'] ?? 0]);
        fputcsv($output, ['Partial Consents', $stats['partial_consents'] ?? 0]);
        fputcsv($output, ['No Consents', $stats['no_consents'] ?? 0]);
        fputcsv($output, []);
        
        // Add consent logs
        fputcsv($output, ['Consent Logs']);
        fputcsv($output, ['Date', 'IP Address', 'Country', 'Consent Type', 'Categories']);
        
        foreach($logs as $log) {
            fputcsv($output, [
                $log['created_at'] ?? '',
                $log['ip'] ?? '',
                $log['country'] ?? '',
                $log['consent'] ?? '',
                $log['categories'] ?? ''
            ]);
        }
        
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream, not a filesystem file
        fclose($output);
        exit;
    }

    public function ajax_save_settings() {
        // Verify nonce and permissions
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error('Permission denied');
        }

        $form_id = sanitize_text_field($_POST['form_id'] ?? '');
        
        try {
            switch ($form_id) {
                case 'banner-settings-form':
                    $settings = [
                        'banner_enabled' => sanitize_text_field($_POST['banner_enabled'] ?? 'yes'),
                        'banner_position' => sanitize_text_field($_POST['banner_position'] ?? 'bottom'),
                        'banner_text' => sanitize_textarea_field($_POST['banner_text'] ?? '')
                    ];
                    update_option('cookiejar_banner_settings', $settings);
                    break;

                case 'general-settings-form':
                    $settings = [
                        'consent_duration' => min(180, max(1, intval($_POST['consent_duration'] ?? 180))),
                        'storage_mode' => sanitize_text_field($_POST['storage_mode'] ?? 'hash'),
                        'geo_auto' => isset($_POST['geo_auto']) ? 'yes' : 'no'
                    ];
                    update_option('cookiejar_general_settings', $settings);
                    break;

                case 'appearance-settings-form':
                    $settings = [
                        'banner_color' => sanitize_hex_color($_POST['banner_color'] ?? '#008ed6'),
                        'banner_bg' => sanitize_hex_color($_POST['banner_bg'] ?? '#ffffff'),
                        'banner_font' => sanitize_text_field($_POST['banner_font'] ?? 'inherit'),
                        'banner_font_size' => min(36, max(10, intval($_POST['banner_font_size'] ?? 16)))
                    ];
                    update_option('cookiejar_appearance_settings', $settings);
                    break;

                default:
                    wp_send_json_error('Unknown form type');
            }

            wp_send_json_success('Settings saved successfully');
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to save settings: ' . $e->getMessage());
        }
    }

    public function ajax_skip_wizard() {
        // Verify nonce and permissions
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error('Permission denied');
        }

        try {
            // Mark wizard as completed
            update_option('cookiejar_wizard_done', 'yes');
            
            // Set default settings
            $default_settings = [
                'banner_enabled' => 'yes',
                'banner_position' => 'bottom',
                'banner_text' => 'We use cookies to enhance your experience. By continuing to visit this site you agree to our use of cookies.',
                'consent_duration' => 180,
                'storage_mode' => 'hash',
                'geo_auto' => 'yes',
                'banner_color' => '#008ed6',
                'banner_bg' => '#ffffff',
                'banner_font' => 'inherit',
                'banner_font_size' => 16
            ];
            
            update_option('cookiejar_banner_settings', array_slice($default_settings, 0, 3));
            update_option('cookiejar_general_settings', array_slice($default_settings, 3, 3));
            update_option('cookiejar_appearance_settings', array_slice($default_settings, 6, 4));
            
            wp_send_json_success('Wizard skipped successfully');
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to skip wizard: ' . $e->getMessage());
        }
    }

    public function ajax_save_wizard() {
        // Verify nonce and permissions
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error('Permission denied');
        }

        try {
            $step = intval($_POST['step'] ?? 0);
            $raw = $_POST['data'] ?? '{}';
            $data = is_string($raw) ? json_decode(stripslashes($raw), true) : (array)$raw;
            if (!is_array($data)) $data = [];

            if ($step < 1 || $step > 4) {
                wp_send_json_error('Invalid step number');
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
                        $wizard_settings['banner_bg'] = sanitize_hex_color($data['bg'] ?? '#ffffff');
                    }
                    if (array_key_exists('policy_url', $data)) {
                        $wizard_settings['policy_url'] = esc_url_raw($data['policy_url'] ?? '');
                    }
                    if (array_key_exists('prompt', $data)) {
                        $wizard_settings['prompt_text'] = sanitize_textarea_field($data['prompt'] ?? '');
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
                'step' => $step
            ]);

        } catch (Exception $e) {
            wp_send_json_error('Failed to save wizard step: ' . $e->getMessage());
        }
    }

    public function ajax_complete_wizard() {
        // Verify nonce and permissions
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error('Permission denied');
        }

        try {
            $is_pro = function_exists('cookiejar_is_pro') ? cookiejar_is_pro() : false;

            // Accept languages as array or CSV string
            $rawLanguages = $_POST['languages'] ?? 'en_US';

            $settings = [
                'languages' => $this->sanitize_languages($rawLanguages, $is_pro),
                'categories' => $this->sanitize_categories($_POST['categories'] ?? []),
                'banner_color' => sanitize_hex_color($_POST['banner_color'] ?? $_POST['color'] ?? '#008ed6'),
                'banner_bg' => sanitize_hex_color($_POST['banner_bg'] ?? $_POST['bg'] ?? '#ffffff'),
                'policy_url' => esc_url_raw($_POST['policy_url'] ?? ''),
                'prompt_text' => sanitize_textarea_field($_POST['prompt_text'] ?? $_POST['prompt'] ?? ''),
                'geo_auto' => !empty($_POST['geo_auto']) && $_POST['geo_auto'] === '1',
                'logging_mode' => sanitize_text_field($_POST['logging_mode'] ?? 'cached'),
                'gdpr_mode' => !empty($_POST['gdpr_mode']) && $_POST['gdpr_mode'] === '1',
                'ccpa_mode' => !empty($_POST['ccpa_mode']) && $_POST['ccpa_mode'] === '1',
            ];

            // Enforce plan rules server-side
            $settings['logging_mode'] = ($is_pro && $settings['logging_mode'] === 'live') ? 'live' : 'cached';
            if (!$settings['categories'] || !in_array('necessary', $settings['categories'], true)) {
                array_unshift($settings['categories'], 'necessary');
                $settings['categories'] = array_values(array_unique($settings['categories']));
            }

            update_option('cookiejar_wizard_settings', $settings, false);
            update_option('cookiejar_wizard_done', 'yes');

            wp_send_json_success([
                'message' => __('Setup completed successfully!', 'cookiejar'),
                'redirect' => admin_url('admin.php?page=cookiejar-dashboard'),
            ]);

        } catch (Exception $e) {
            wp_send_json_error('Failed to complete wizard: ' . $e->getMessage());
        }
    }

    /**
     * Normalize a locale code to "ll_RR" format (e.g., en_US, zh_CN).
     */
    private function normalize_locale($code) {
        $code = (string)$code;
        $code = trim($code);
        if ($code === '') return '';
        // Accept patterns like ll, ll-RR, ll_RR, lll (3-letter language)
        if (preg_match('/^([a-zA-Z]{2,3})(?:[-_]?([a-zA-Z]{2}))?$/', $code, $m)) {
            $lang = strtolower($m[1]);
            $region = isset($m[2]) ? strtoupper($m[2]) : '';
            return $region ? "{$lang}_{$region}" : $lang; // keep language-only codes as 'll'
        }
        return strtolower($code);
    }

    /**
     * Sanitize languages input and enforce plan limits; return CSV string in normalized form.
     * Accepts array of codes or CSV string; normalizes to "ll_RR" where region exists.
     */
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

    public function ajax_reset_wizard() {
        // Verify nonce and permissions
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error('Permission denied');
        }

        try {
            $clear_settings = intval($_POST['clear_settings'] ?? 0);
            
            // Reset wizard completion status
            update_option('cookiejar_wizard_done', 'no');
            
            // Optionally clear wizard settings
            if ($clear_settings) {
                delete_option('cookiejar_wizard_settings');
            }
            
            wp_send_json_success([
                'message' => __('Wizard reset successfully! The setup wizard will now appear in the admin menu.', 'cookiejar')
            ]);

        } catch (Exception $e) {
            wp_send_json_error('Failed to reset wizard: ' . $e->getMessage());
        }
    }

    public function ajax_force_wizard_menu() {
        // Verify nonce and permissions
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error('Permission denied');
        }

        try {
            // Force wizard menu to appear by resetting completion status
            update_option('cookiejar_wizard_done', 'no');
            
            wp_send_json_success([
                'message' => __('Wizard menu created! Please refresh the page to see it in the admin menu.', 'cookiejar')
            ]);

        } catch (Exception $e) {
            wp_send_json_error('Failed to create wizard menu: ' . $e->getMessage());
        }
    }

    public function ajax_apply_defaults() {
        // Verify nonce and permissions
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error('Permission denied');
        }

        try {
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
                'message' => __('Defaults applied.', 'cookiejar'),
                'redirect' => admin_url('admin.php?page=cookiejar-dashboard'),
            ]);

        } catch (Exception $e) {
            wp_send_json_error('Failed to apply defaults: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for database migration.
     */
    public function ajax_migrate_database() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Insufficient permissions'], 403);
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cookiejar_admin')) {
            wp_send_json_error(['error' => 'Invalid nonce'], 403);
        }
        
        try {
            // Load DB class if not already loaded
            if (!class_exists('DWIC\DB')) {
                $db_path = DWIC_PATH . 'includes/class-cookiejar-db.php';
                if (file_exists($db_path)) {
                    require_once $db_path;
                } else {
                    wp_send_json_error(['error' => 'DB class not found'], 500);
                }
            }
            
            // Run migration
            \DWIC\DB::create_tables();
            
            wp_send_json_success([
                'message' => 'Database migration completed successfully',
                'timestamp' => current_time('mysql')
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'error' => 'Migration failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function render_settings_footer_link(){
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) return;
        // Only show on our control panel page
        if (strpos($screen->base, 'cookiejar-control') === false && strpos($screen->id ?? '', 'cookiejar-control') === false) return;
        echo '<div style="margin-top:12px;color:#64748B;font-size:12px;">'
            . esc_html__('Learn about Pro','cookiejar') . ': '
            . '<a href="' . esc_url( admin_url('admin.php?page=cookiejar-control#page=documentation') ) . '" style="text-decoration:none;">' . esc_html__('Documentation','cookiejar') . '</a>'
            . '</div>';
    }
}