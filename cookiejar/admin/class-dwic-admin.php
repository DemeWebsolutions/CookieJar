<?php
namespace DWIC\Admin;
if (!defined('ABSPATH')) exit;

class Admin {
    private $plugin_name, $version;
    public function __construct($plugin_name,$version){
        $this->plugin_name=$plugin_name;
        $this->version=$version;
        add_action('admin_menu',[$this,'menu']);
        add_action('admin_init',[$this,'register_settings']);
        add_action('admin_enqueue_scripts',[$this,'assets']);
        add_action('admin_post_dwic_export',[$this,'export_logs']);
        add_action('admin_notices',[$this,'notice']);
        add_action('admin_init',[$this,'maybe_lock_hash']);
    }

    private function embed_svg($file, $ariaLabel){
        $path = DWIC_PATH.'assets/img/'.$file;
        if(!file_exists($path)) {
            $design = DWIC_PATH.'assets/design/'.$file;
            $path = file_exists($design) ? $design : '';
        }
        if(!$path) return '';
        $svg = @file_get_contents($path);
        if(!$svg) return '';
        $svg = preg_replace('/^\xEF\xBB\xBF|<\?xml[^>]*\?>/i','',$svg);
        return '<div class="cookiejar-admin-hero" aria-label="'.esc_attr($ariaLabel).'">'.$svg.'<div><p class="title">'.esc_html($ariaLabel).'</p><p class="subtitle">'.esc_html__('CookieJar plugin administration','cookiejar').'</p></div></div>';
    }

    public function menu(){
        add_menu_page(
            __('International Compliance','cookiejar'),
            __('Intl. Compliance','cookiejar'),
            'manage_options',
            'dwic-main',
            [$this,'dashboard'],
            DWIC_ICON_URL, // local PNG icon
            66
        );
        add_submenu_page('dwic-main',__('Setup & Appearance','cookiejar'),__('Setup & Appearance','cookiejar'),'manage_options','dwic-setup-appearance',[$this,'setup_appearance']);
    }

    public function register_settings(){
        $default_msg = '<a style="color:#151515b5;border-color:transparent;background-color:transparent;"><strong>Your Privacy is Our Top Priority</strong><br><br>We use cookies to enhance your browsing experience, serve personalized ads or content, and analyze our traffic. By clicking &quot;Accept All&quot;, you consent to our use of cookies. To learn more, visit our </a><a href="/privacy-policy/" aria-label="Cookie Policy" style="color:#008ed6;border-color:transparent;background-color:transparent;">Cookie Policy</a>.';
        $sanitize_msg = function($val) use ($default_msg){
            if(!is_string($val) || trim($val)==='') $val = $default_msg;
            $val = preg_replace('/\s+/u',' ', $val);
            return trim($val);
        };
        register_setting('dwic_options','dwic_message',['type'=>'string','sanitize_callback'=>$sanitize_msg,'default'=>$default_msg]);
        register_setting('dwic_options','dwic_days',['type'=>'integer','sanitize_callback'=>'absint','default'=>180]);
        register_setting('dwic_options','dwic_scheme',['type'=>'string','sanitize_callback'=>function($v){
            $allowed=['light','dark','custom'];
            return in_array($v,$allowed)?$v:'light';
        },'default'=>'light']);

        register_setting('dwic_options','dwic_custom_colors',[
            'type'=>'array',
            'sanitize_callback'=>function($arr){
                $defaults=['primary'=>'#008ed6','background'=>'#ffffff','text'=>'#222222','accent'=>'#0075b2'];
                if(!is_array($arr)) $arr=[];
                foreach($defaults as $k=>$v){
                    if(empty($arr[$k]) || !preg_match('/^#[0-9a-f]{6}$/i',$arr[$k])) $arr[$k]=$v;
                }
                return $arr;
            },'default'=>[]
        ]);

        register_setting('dwic_options','dwic_logo_style',[
            'type'=>'array',
            'sanitize_callback'=>function($arr){
                $def=['mode'=>'gradient','primary'=>'#008ed6','secondary'=>'#57bff3','angle'=>'135','auto_applied'=>'0'];
                if(!is_array($arr)) $arr=[];
                $mode = in_array($arr['mode']??'gradient',['gradient','solid']) ? $arr['mode'] : 'gradient';
                $p = isset($arr['primary']) && preg_match('/^#[0-9a-f]{6}$/i',$arr['primary']) ? strtolower($arr['primary']) : $def['primary'];
                $s = isset($arr['secondary']) && preg_match('/^#[0-9a-f]{6}$/i',$arr['secondary']) ? strtolower($arr['secondary']) : $def['secondary'];
                $angle = isset($arr['angle']) && preg_match('/^[0-9]{1,3}$/',$arr['angle']) ? $arr['angle'] : $def['angle'];
                return ['mode'=>$mode,'primary'=>$p,'secondary'=>$s,'angle'=>$angle,'auto_applied'=>!empty($arr['auto_applied'])?'1':'0'];
            },
            'default'=>['mode'=>'gradient','primary'=>'#008ed6','secondary'=>'#57bff3','angle'=>'135','auto_applied'=>'0']
        ]);

        register_setting('dwic_options','dwic_privacy_page',['type'=>'integer','sanitize_callback'=>'absint','default'=>0]);
        register_setting('dwic_options','dwic_features',['type'=>'array','sanitize_callback'=>function($v){
            if(!is_array($v)) return [];
            return array_values(array_unique(array_map('sanitize_text_field',$v)));
        },'default'=>[]]);

        // Still register, but we may ignore/hide in template mode
        register_setting('dwic_options','dwic_anonymize_ip',['type'=>'string','sanitize_callback'=>function($v){
            return in_array($v,['off','truncate','hash'])?$v:'truncate';
        },'default'=>'truncate']);
        register_setting('dwic_options','dwic_log_retention_days',['type'=>'integer','sanitize_callback'=>'absint','default'=>365]);
        register_setting('dwic_options','dwic_ga',['type'=>'string','sanitize_callback'=>function($v){
            $v=trim($v);
            if($v==='' || preg_match('/^(G|UA|GTAG|GTM)-[A-Z0-9\-]+$/i',$v)) return $v;
            return '';
        },'default'=>'']);
        register_setting('dwic_options','dwic_enable_consent_mode',['type'=>'boolean','sanitize_callback'=>function($v){ return (string)$v==='1'?1:0;},'default'=>0]);
        register_setting('dwic_options','dwic_i18n_overrides',['type'=>'string','sanitize_callback'=>function($v){
            if(trim($v)==='') return '';
            $decoded=json_decode($v,true);
            return is_array($decoded)?json_encode($decoded, JSON_UNESCAPED_UNICODE):'';
        },'default'=>'']);
        register_setting('dwic_options','dwic_license_key',['type'=>'string','sanitize_callback'=>function($v){ return preg_replace('/[^A-Z0-9@\-\._]/i','',$v);},'default'=>'']);
        register_setting('dwic_options','dwic_harden_css',['type'=>'boolean','sanitize_callback'=>function($v){
            return (string)$v==='0'?0:1;
        },'default'=>1]);
    }

    public function assets($hook){
        if(strpos($hook,'dwic-setup-appearance')===false && strpos($hook,'dwic-main')===false) return;
        wp_enqueue_style('dwic-admin', DWIC_URL.'assets/css/admin.css',[],$this->version);
        wp_enqueue_script('dwic-admin', DWIC_URL.'assets/js/admin.js',['jquery'],$this->version,true);

        // In template mode, skip color-suggestion enhancer to keep UI lean
        if (!defined('DWIC_TEMPLATE_MODE') || !DWIC_TEMPLATE_MODE) {
            wp_enqueue_script('dwic-admin-colors', DWIC_URL.'assets/js/admin-colors.js',['jquery'],$this->version,true);
            wp_localize_script('dwic-admin-colors','DWIC_COLOR_API',[
                'ajaxurl'=>admin_url('admin-ajax.php'),
                'nonce'=>wp_create_nonce('dwic_palette_nonce')
            ]);
        }

        wp_localize_script('dwic-admin','DWIC_ADMIN_SETTINGS',[
            'hashLock'=> (bool)get_option('dwic_hash_lock',''),
            'templateMode'=> (bool)(defined('DWIC_TEMPLATE_MODE') && DWIC_TEMPLATE_MODE)
        ]);
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    public function dashboard(){
        $health = isset($GLOBALS['DWIC_HEALTH_RESULTS']) ? $GLOBALS['DWIC_HEALTH_RESULTS'] : [];
        ?>
        <div class="wrap">
            <?php echo wp_kses_post( $this->embed_svg('cookiejar-bakery.svg', __('CookieJar Dashboard - The Bakery','cookiejar')) ); ?>
            <h1 class="screen-reader-text"><?php esc_html_e('International Compliance Dashboard','cookiejar');?></h1>
            <div id="dwic-map-wrap" class="dwic-map-wrap"></div>
            <p class="description"><?php esc_html_e('Live consent summaries (refresh every 30s).','cookiejar');?></p>

            <h2><?php esc_html_e('Health Check','cookiejar');?></h2>
            <table class="widefat" style="max-width:760px;">
                <thead><tr><th><?php esc_html_e('Check','cookiejar');?></th><th><?php esc_html_e('Status','cookiejar');?></th></tr></thead>
                <tbody>
                <?php foreach($health as $key=>$row): ?>
                    <tr>
                        <td><?php echo esc_html($row['msg']);?></td>
                        <td><?php echo !empty($row['ok']) ? '<span style="color:#0a0">OK</span>' : '<span style="color:#a00">FAIL</span>';?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php $this->hash_diag(); ?>
            <div class="dwic-footer">Demewebsolutions.com, My Deme, Llc. ©2025 All Rights Reserved</div>
        </div>
        <?php
    }

    public function setup_appearance(){
        $scheme        = get_option('dwic_scheme','light');
        $colors        = get_option('dwic_custom_colors',[]);
        $logo_style    = get_option('dwic_logo_style', ['mode'=>'gradient','primary'=>'#008ed6','secondary'=>'#57bff3','angle'=>'135','auto_applied'=>'0']);
        $message       = get_option('dwic_message','');
        $privacy_page  = (int)get_option('dwic_privacy_page',0);
        $days          = (int)get_option('dwic_days',180);
        $anonymize     = get_option('dwic_anonymize_ip','truncate');
        $retention     = (int)get_option('dwic_log_retention_days',365);
        $ga            = get_option('dwic_ga','');
        $consent_mode  = (int)get_option('dwic_enable_consent_mode',0);
        $i18n_overrides= get_option('dwic_i18n_overrides','');
        $license_key   = get_option('dwic_license_key','');
        $harden_css    = (int)get_option('dwic_harden_css',1);
        $pages = get_pages(['number'=>300,'sort_column'=>'post_title','post_status'=>'publish']);
        ?>
        <div class="wrap">
          <?php echo wp_kses_post( $this->embed_svg('cookiejar-admin.svg', __('CookieJar General Admin','cookiejar')) ); ?>
          <h1 class="screen-reader-text"><?php esc_html_e('Setup & Appearance','cookiejar');?></h1>
          <form method="post" action="options.php" id="dwic-main-form">
            <?php settings_fields('dwic_options'); ?>
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><?php esc_html_e('Consent Prompt HTML','cookiejar');?></th>
                <td>
                  <textarea name="dwic_message" rows="6" style="width:100%;max-width:760px;"><?php echo esc_textarea($message);?></textarea>
                  <p class="description"><?php esc_html_e('Edit the banner message.','cookiejar');?></p>
                </td>
              </tr>
              <tr>
                <th scope="row"><?php esc_html_e('Privacy Policy Page','cookiejar');?></th>
                <td>
                  <select name="dwic_privacy_page">
                    <option value="0"><?php esc_html_e('-- Select Page --','cookiejar');?></option>
                    <?php foreach($pages as $p): ?>
                      <option value="<?php echo (int)$p->ID;?>" <?php selected($privacy_page,$p->ID);?>><?php echo esc_html($p->post_title);?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th scope="row"><?php esc_html_e('Color Scheme','cookiejar');?></th>
                <td>
                  <select name="dwic_scheme" id="dwic_scheme_select">
                    <option value="light" <?php selected($scheme,'light');?>><?php esc_html_e('Light','cookiejar');?></option>
                    <option value="dark" <?php selected($scheme,'dark');?>><?php esc_html_e('Dark','cookiejar');?></option>
                    <option value="custom" <?php selected($scheme,'custom');?>><?php esc_html_e('Custom','cookiejar');?></option>
                  </select>
                  <p class="description"><?php esc_html_e('Select banner scheme.','cookiejar');?></p>
                  <div id="dwic-custom-colors" style="<?php echo $scheme==='custom'?'':'display:none;';?>margin-top:10px;">
                    <label><?php esc_html_e('Primary','cookiejar');?>:
                        <input type="text" class="color-picker" name="dwic_custom_colors[primary]" value="<?php echo esc_attr($colors['primary']??'#008ed6');?>"></label><br>
                    <label><?php esc_html_e('Background','cookiejar');?>:
                        <input type="text" class="color-picker" name="dwic_custom_colors[background]" value="<?php echo esc_attr($colors['background']??'#ffffff');?>"></label><br>
                    <label><?php esc_html_e('Text','cookiejar');?>:
                        <input type="text" class="color-picker" name="dwic_custom_colors[text]" value="<?php echo esc_attr($colors['text']??'#222222');?>"></label><br>
                    <label><?php esc_html_e('Accent','cookiejar');?>:
                        <input type="text" class="color-picker" name="dwic_custom_colors[accent]" value="<?php echo esc_attr($colors['accent']??'#0075b2');?>"></label>
                  </div>
                </td>
              </tr>

              <tr>
                <th scope="row"><?php esc_html_e('Logo Style (Cookie Jar & Revisit Button)','cookiejar');?></th>
                <td>
                  <fieldset>
                    <label><input type="radio" name="dwic_logo_style[mode]" value="gradient" <?php checked($logo_style['mode'],'gradient');?>> <?php esc_html_e('Transparent Gradient','cookiejar');?></label><br>
                    <label><input type="radio" name="dwic_logo_style[mode]" value="solid" <?php checked($logo_style['mode'],'solid');?>> <?php esc_html_e('Solid','cookiejar');?></label>
                  </fieldset>
                  <div style="margin-top:8px;">
                    <label><?php esc_html_e('Primary','cookiejar');?>:
                      <input type="text" class="color-picker" name="dwic_logo_style[primary]" value="<?php echo esc_attr($logo_style['primary']);?>">
                    </label>
                    <span class="dwic-logo-secondary-wrap" style="<?php echo $logo_style['mode']==='gradient'?'':'display:none;';?>">
                      <label style="margin-left:12px;"><?php esc_html_e('Secondary','cookiejar');?>:
                        <input type="text" class="color-picker" name="dwic_logo_style[secondary]" value="<?php echo esc_attr($logo_style['secondary']);?>">
                      </label>
                      <label style="margin-left:12px;"><?php esc_html_e('Angle (deg)','cookiejar');?>:
                        <input type="number" min="0" max="360" name="dwic_logo_style[angle]" value="<?php echo esc_attr($logo_style['angle']);?>" style="width:80px;">
                      </label>
                    </span>
                  </div>

                  <?php if (!defined('DWIC_TEMPLATE_MODE') || !DWIC_TEMPLATE_MODE): ?>
                  <div id="dwic-logo-suggestions" style="margin-top:10px;">
                    <strong><?php esc_html_e('Suggested Colors','cookiejar');?>:</strong>
                    <span class="description"><?php esc_html_e('(Derived from site buttons & palette)','cookiejar');?></span>
                    <div class="dwic-logo-suggest-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;"></div>
                  </div>
                  <?php endif; ?>

                  <p class="description"><?php esc_html_e('Gradient starts transparent and fades into your chosen colors.','cookiejar');?></p>
                </td>
              </tr>

              <?php if (!defined('DWIC_TEMPLATE_MODE') || !DWIC_TEMPLATE_MODE): ?>
              <tr>
                <th scope="row"><?php esc_html_e('Auto Palette Suggestions','cookiejar');?></th>
                <td>
                  <button type="button" class="button button-secondary" id="dwic-run-palette"><?php esc_html_e('Analyze Site Colors','cookiejar');?></button>
                  <span id="dwic-palette-status" style="margin-left:8px;"></span>
                  <div id="dwic-palette-results" style="margin-top:14px;display:none;">
                    <div class="dwic-palette-card dwic-light-card" style="border:1px solid #ccd5dd;padding:12px;border-radius:6px;margin-bottom:10px;">
                      <strong><?php esc_html_e('Suggested Light Palette','cookiejar');?></strong>
                      <div class="dwic-palette-swatches" style="display:flex;gap:8px;margin:8px 0;"></div>
                      <div class="dwic-palette-contrast" style="font-size:11px;opacity:.8;"></div>
                      <button type="button" class="button button-small dwic-apply-light"><?php esc_html_e('Apply as Custom','cookiejar');?></button>
                    </div>
                    <div class="dwic-palette-card dwic-dark-card" style="border:1px solid #ccd5dd;padding:12px;border-radius:6px;">
                      <strong><?php esc_html_e('Suggested Dark Palette','cookiejar');?></strong>
                      <div class="dwic-palette-swatches" style="display:flex;gap:8px;margin:8px 0;"></div>
                      <div class="dwic-palette-contrast" style="font-size:11px;opacity:.8;"></div>
                      <button type="button" class="button button-small dwic-apply-dark"><?php esc_html_e('Apply as Custom','cookiejar');?></button>
                    </div>
                    <p class="description" style="margin-top:8px;"><?php esc_html_e('Suggestions are based on this page’s computed styles & theme settings.','cookiejar');?></p>
                  </div>
                </td>
              </tr>
              <?php endif; ?>

              <tr>
                <th scope="row"><?php esc_html_e('Harden CSS (Force Override)','cookiejar');?></th>
                <td>
                  <label>
                    <input type="checkbox" name="dwic_harden_css" value="1" <?php checked($harden_css,1);?>>
                    <?php esc_html_e('Enable hardening (default ON) for stronger style enforcement.','cookiejar');?>
                  </label>
                </td>
              </tr>

              <tr>
                <th scope="row"><?php esc_html_e('Consent Retention (days)','cookiejar');?></th>
                <td><input type="number" name="dwic_days" min="1" value="<?php echo esc_attr($days);?>"></td>
              </tr>
              <tr>
                <th scope="row"><?php esc_html_e('IP Anonymization','cookiejar');?></th>
                <td>
                  <select name="dwic_anonymize_ip">
                    <option value="truncate" <?php selected($anonymize,'truncate');?>><?php esc_html_e('Truncate','cookiejar');?></option>
                    <option value="hash" <?php selected($anonymize,'hash');?>><?php esc_html_e('Hash','cookiejar');?></option>
                    <option value="off" <?php selected($anonymize,'off');?>><?php esc_html_e('Off','cookiejar');?></option>
                  </select>
                </td>
              </tr>
              <tr>
                <th scope="row"><?php esc_html_e('Log Retention (days)','cookiejar');?></th>
                <td><input type="number" name="dwic_log_retention_days" min="1" value="<?php echo esc_attr($retention);?>"></td>
              </tr>

              <?php if (!defined('DWIC_TEMPLATE_MODE') || !DWIC_TEMPLATE_MODE): ?>
              <tr>
                <th scope="row"><?php esc_html_e('GA4 Measurement ID','cookiejar');?></th>
                <td><input type="text" name="dwic_ga" value="<?php echo esc_attr($ga);?>" placeholder="G-XXXXXXX" style="max-width:240px;"></td>
              </tr>
              <tr>
                <th scope="row"><?php esc_html_e('Google Consent Mode v2','cookiejar');?></th>
                <td><label><input type="checkbox" name="dwic_enable_consent_mode" value="1" <?php checked($consent_mode,1);?>> <?php esc_html_e('Enable Consent Mode v2 integration','cookiejar');?></label></td>
              </tr>
              <tr>
                <th scope="row"><?php esc_html_e('License Key','cookiejar');?></th>
                <td>
                  <input type="text" name="dwic_license_key" value="<?php echo esc_attr($license_key);?>" placeholder="<?php echo esc_attr(defined('DWIC_MASTER_LICENSE')?substr(DWIC_MASTER_LICENSE,0,28).'…':''); ?>" style="max-width:420px;">
                </td>
              </tr>
              <tr>
                <th scope="row"><?php esc_html_e('Custom Translation Overrides (JSON)','cookiejar');?></th>
                <td><textarea name="dwic_i18n_overrides" rows="4" style="width:100%;max-width:520px;"><?php echo esc_textarea($i18n_overrides);?></textarea></td>
              </tr>
              <?php endif; ?>
            </table>
            <?php submit_button(__('Save Settings','cookiejar')); ?>
          </form>
        </div>
        <script>
        (function($){
           $('#dwic_scheme_select').on('change',function(){
               if(this.value==='custom') $('#dwic-custom-colors').show(); else $('#dwic-custom-colors').hide();
           });
           $('input[name="dwic_logo_style[mode]"]').on('change',function(){
              if(this.value==='gradient') $('.dwic-logo-secondary-wrap').show();
              else $('.dwic-logo-secondary-wrap').hide();
           });
           $('.color-picker').wpColorPicker && $('.color-picker').wpColorPicker();
        })(jQuery);
        </script>
        <?php
    }

    private function hash_diag(){
        $features = get_option('dwic_features',[]);
        if(!is_array($features)) $features=[];
        sort($features,SORT_STRING);
        $cats = ['necessary|1','functional|0','analytics|0','ads|0','chatbot|0'];
        sort($cats,SORT_STRING);
        $fingerprint = [
            'categories'=>$cats,
            'duration'=>(int)get_option('dwic_days',180),
            'features'=>$features
        ];
        if(!defined('DWIC_HASH_INCLUDE_MESSAGE') || DWIC_HASH_INCLUDE_MESSAGE){
            $fingerprint['message']=preg_replace('/\s+/',' ', (string)get_option('dwic_message',''));
        }
        $current_hash = md5(json_encode($fingerprint, JSON_UNESCAPED_UNICODE));
        $cookie_version='';
        if(isset($_COOKIE['dwic_consent_v2'])){
            $cookie_value = sanitize_text_field( wp_unslash($_COOKIE['dwic_consent_v2']) );
            $c=json_decode(stripslashes($cookie_value),true);
            $cookie_version=$c['version']??'';
        }
        ?>
        <h2><?php esc_html_e('Config Hash Diagnostics','cookiejar');?></h2>
        <table class="widefat" style="max-width:760px;">
          <tr><th><?php esc_html_e('Computed Hash','cookiejar');?></th><td><?php echo esc_html($current_hash);?></td></tr>
          <tr><th><?php esc_html_e('Cookie Version','cookiejar');?></th><td><?php echo esc_html($cookie_version?:'(none)');?></td></tr>
          <tr><th><?php esc_html_e('Match','cookiejar');?></th><td><?php echo ($cookie_version && $cookie_version===$current_hash)?'YES':'NO';?></td></tr>
          <tr><th><?php esc_html_e('Hash Lock Active','cookiejar');?></th><td><?php echo get_option('dwic_hash_lock')?'YES':'NO';?></td></tr>
        </table>
        <?php
    }

    public function export_logs(){
        if(!current_user_can('manage_options')) wp_die('No permission');
        $logs = \DWIC\DB::get_logs(20000);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dwic_logs.csv"');
        $out=fopen('php://output','w');
        if($logs){
            fputcsv($out,array_keys($logs[0]));
            foreach($logs as $row) fputcsv($out,$row);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream, not a filesystem file
        fclose($out); exit;
    }

    public function notice(){
        $screen = get_current_screen();
        if($screen && strpos($screen->id,'cookiejar')!==false){
            // Removed proprietary notice for WordPress.org compliance
        }
    }

    public function maybe_lock_hash(){
        if(!current_user_can('manage_options')) return;
        if(isset($_POST['dwic_lock_hash_action']) && check_admin_referer('dwic_lock_hash','dwic_lock_hash_nonce')){
            $lock = $this->compute_current_hash();
            update_option('dwic_hash_lock',$lock);
            add_settings_error('dwic_messages','dwic_hash_locked',__('Config hash locked.','cookiejar'),'updated');
        }
        if(isset($_POST['dwic_unlock_hash_action']) && check_admin_referer('dwic_lock_hash','dwic_lock_hash_nonce')){
            delete_option('dwic_hash_lock');
            add_settings_error('dwic_messages','dwic_hash_unlocked',__('Config hash unlocked.','cookiejar'),'updated');
        }
    }

    private function compute_current_hash(){
        $features = get_option('dwic_features',[]);
        if(!is_array($features)) $features=[];
        sort($features,SORT_STRING);
        $cats=['necessary|1','functional|0','analytics|0','ads|0','chatbot|0'];
        sort($cats,SORT_STRING);
        $fp=[
            'categories'=>$cats,
            'duration'=>(int)get_option('dwic_days',180),
            'features'=>$features
        ];
        if(!defined('DWIC_HASH_INCLUDE_MESSAGE') || DWIC_HASH_INCLUDE_MESSAGE){
            $fp['message']=preg_replace('/\s+/',' ', (string)get_option('dwic_message',''));
        }
        return md5(json_encode($fp, JSON_UNESCAPED_UNICODE));
    }
}