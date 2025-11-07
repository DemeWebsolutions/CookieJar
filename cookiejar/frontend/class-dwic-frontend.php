<?php
namespace DWIC\Frontend;
if (!defined('ABSPATH')) exit;

class Frontend {
    private $plugin_name, $version;
    const DWIC_COOKIE_ICON = '';

    public function __construct($plugin, $ver) {
        $this->plugin_name = $plugin;
        $this->version = $ver;
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_footer',        [$this, 'render_banner']);
        add_action('wp_footer',        [$this, 'render_revisit']);
        add_action('wp_head',          [$this, 'inject_ga'], 5);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_ga'], 25);
        add_filter('script_loader_tag', [$this, 'add_async_to_gtag'], 10, 2);
        add_action('wp_head',          [$this, 'inject_ga_after'], 25);
        add_action('init',             [$this, 'define_icon']);
    }

    public function define_icon(){
        if (!defined('DWIC_ICON_FALLBACK')) {
            define('DWIC_ICON_FALLBACK', plugins_url('assets/icon/Frontend-CookieLogo.png', dirname(__FILE__, 2) . '/cookiejar.php'));
        }
    }

    private function translations(){
        $locale = determine_locale();
        $lang = strtolower(substr($locale,0,2));
        $base = [
            'accept'         => 'Accept All',
            'reject'         => 'Reject',
            'preferences'    => 'Preferences',
            'save'           => 'Save Preferences',
            'always_on'      => 'Always On',
            'necessary_desc' => 'Essential for the site to function properly.',
            'do_not_sell'    => 'Do Not Sell My Information',
            'do_not_sell_desc' => 'Opt out of sale or sharing of personal information (California / CPRA).'
        ];
        $over = get_option('dwic_i18n_overrides','');
        if($over){
            $dec = json_decode($over,true);
            if(is_array($dec)) $base = array_merge($base,$dec);
        }
        return apply_filters('dwic_i18n_strings',$base,$lang,$locale);
    }

    private function maybe_harden_css($css){
        $enabled = get_option('dwic_harden_css','1');
        if(!(int)$enabled) return $css;
        $lines = preg_split('/\R/u',$css);
        $out = [];
        foreach($lines as $line){
            $l = ltrim($line);
            if(preg_match('/^[a-zA-Z\-\_][^{};]*:\s*[^;]+;$/',$l) && strpos($line,'!important')===false){
                if(preg_match('/^(background|color|font|padding|margin|border|box-shadow|position|z-index|display|flex|grid|top|bottom|left|right|width|height|transform|transition)/i',$l)){
                    $line = rtrim(substr($line,0,-1)).' !important;';
                }
            }
            if(preg_match('/^\.dwic-/',$l)){
                $line=':where(body) '.$line;
            }
            $out[]=$line;
        }
        $css=implode("\n",$out)."\n/* Hardened additions */\n";
        $css.=':where(body) .dwic-bar .dwic-btn{appearance:none !important;line-height:1.2 !important;}';
        $css.=':where(body) .dwic-bar .dwic-btn:focus{outline:2px solid #004d80 !important;outline-offset:2px !important;}';
        $css.="\n/* End hardened */\n";
        return $css;
    }

    private function valid_hex($h){
        return (is_string($h) && preg_match('/^#[0-9a-f]{6}$/i',$h)) ? strtolower($h) : '#008ed6';
    }
    private function shade($hex,$percent){
        $hex=ltrim($hex,'#'); $r=hexdec(substr($hex,0,2)); $g=hexdec(substr($hex,2,2)); $b=hexdec(substr($hex,4,2));
        $f=(100+$percent)/100; $r=max(0,min(255,round($r*$f))); $g=max(0,min(255,round($g*$f))); $b=max(0,min(255,round($b*$f)));
        return sprintf('#%02x%02x%02x',$r,$g,$b);
    }
    private function mix($a,$b,$ratio){
        $a=ltrim($a,'#');$b=ltrim($b,'#');
        $ar=hexdec(substr($a,0,2));$ag=hexdec(substr($a,2,2));$ab=hexdec(substr($a,4,2));
        $br=hexdec(substr($b,0,2));$bg=hexdec(substr($b,2,2));$bb=hexdec(substr($b,4,2));
        $r=round($ar*$ratio + $br*(1-$ratio));
        $g=round($ag*$ratio + $bg*(1-$ratio));
        $l=round($ab*$ratio + $bb*(1-$ratio));
        return sprintf('#%02x%02x%02x',$r,$g,$l);
    }
    private function hex_to_rgb($h){$h=ltrim($h,'#');return[hexdec(substr($h,0,2)),hexdec(substr($h,2,2)),hexdec(substr($h,4,2))];}
    private function lum($hex){
        $hex=ltrim($hex,'#');
        $r=hexdec(substr($hex,0,2))/255;$g=hexdec(substr($hex,2,2))/255;$b=hexdec(substr($hex,4,2))/255;
        foreach([$r,&$g,&$b] as &$v){$v=($v<=0.03928)?$v/12.92:pow(($v+0.055)/1.055,2.4);}return 0.2126*$r+0.7152*$g+0.0722*$b;
    }

    private function runtime_dynamic_css($scheme){
        $css="/* Runtime dynamic (appended last) */\n";
        $custom = get_option('dwic_custom_colors',[]);
        $primary=$this->valid_hex($custom['primary']??'#008ed6');
        $bg=$this->valid_hex($custom['background']??'#ffffff');
        $text=$this->valid_hex($custom['text']??'#222222');
        $accent=$this->valid_hex($custom['accent']??'#0075b2');

        $css.=':where(body) .dwic-bar.dwic-custom{background:'.$bg.' !important;color:'.$text.' !important;}';
        $css.=':where(body) .dwic-bar.dwic-custom .dwic-desc,:where(body) .dwic-bar.dwic-custom .dwic-footer{color:'.$text.' !important;}';
        $css.=':where(body) .dwic-bar.dwic-custom .dwic-btn.dwic-prefs,:where(body) .dwic-bar.dwic-custom .dwic-btn.dwic-save{background:'.$primary.' !important;color:#fff !important;border:none !important;}';
        $css.=':where(body) .dwic-bar.dwic-custom .dwic-btn.dwic-prefs:hover,:where(body) .dwic-bar.dwic-custom .dwic-btn.dwic-save:hover{background:'.$this->shade($primary,-12).' !important;}';
        $css.=':where(body) .dwic-bar.dwic-custom .dwic-btn.dwic-accept,:where(body) .dwic-bar.dwic-custom .dwic-btn.dwic-reject{background:#fff !important;color:'.$primary.' !important;border:1px solid rgba(0,0,0,0.12) !important;}';
        $css.=':where(body) .dwic-bar.dwic-custom .dwic-btn.dwic-accept:hover,:where(body) .dwic-bar.dwic-custom .dwic-btn.dwic-reject:hover{background:'.$this->mix($primary,'#ffffff',0.88).' !important;}';
        $css.=':where(body) .dwic-bar.dwic-custom .dwic-always-on-note{color:'.$accent.' !important;}';
        $css.=':where(body) .dwic-bar.dwic-custom .dwic-cat input[type=checkbox]:checked + .dwic-toggle{background:'.$primary.' !important;}';
        $css.=':where(body) .dwic-bar.dwic-custom .dwic-btn:focus{outline:2px solid '.$this->shade($primary,-25).' !important;outline-offset:2px !important;}';

        $logo_style = get_option('dwic_logo_style',['mode'=>'gradient','angle'=>'135','stops'=>['#008ed6','#57bff3']]);
        $mode = in_array($logo_style['mode']??'gradient',['gradient','solid']) ? $logo_style['mode'] : 'gradient';
        $angle = isset($logo_style['angle']) && preg_match('/^[0-9]{1,3}$/',$logo_style['angle']) ? $logo_style['angle'] : '135';
        $stops = (isset($logo_style['stops']) && is_array($logo_style['stops'])) ? $logo_style['stops'] : [$primary,$accent];

        $clean=[];
        foreach($stops as $s){
            $clean[]=$this->valid_hex($s);
            if(count($clean)>=5) break;
        }
        if(empty($clean)) $clean=[$primary,$accent];
        if(count($clean)<=2){
            usort($clean,function($a,$b){return $this->lum($a)<=>$this->lum($b);});
        }

        if($mode==='gradient'){
            $first=$clean[0]; [$fr,$fg,$fb]=$this->hex_to_rgb($first);
            $segments=["rgba($fr,$fg,$fb,0) 0%"];
            $step=100/(count($clean));
            foreach($clean as $i=>$c){
                $pos=min(100,round(($i+1)*$step));
                $segments[]=$c.' '.$pos.'%';
            }
            $grad='linear-gradient('.$angle.'deg,'.implode(',',$segments).')';
            $css.=':where(body) .dwic-logo,:where(body) .dwic-revisit-btn{background:'.$grad.' !important;}';
        } else {
            $css.=':where(body) .dwic-logo,:where(body) .dwic-revisit-btn{background:'.$clean[0].' !important;}';
        }
        $css.=':where(body) .dwic-revisit-btn:hover{box-shadow:0 4px 14px rgba(0,0,0,.3) !important;}';

        return $css."\n/* End runtime dynamic */\n";
    }

    public function enqueue(){
        // Check if banner is enabled
        $banner_enabled = get_option('dwic_banner_enabled', '1');
        if($banner_enabled !== '1') return;
        
        $style = get_option('dwic_style',[]);
        if(!is_array($style)) $style=[];
        $style_defaults=['position'=>'bottom','scheme'=>'light','font'=>'inherit'];
        $style = array_merge($style_defaults, array_intersect_key($style,$style_defaults));
        
        // Override with new banner settings if available
        $banner_position = get_option('dwic_banner_position', '');
        $banner_theme = get_option('dwic_banner_theme', '');
        if($banner_position) $style['position'] = $banner_position;
        if($banner_theme) $style['scheme'] = $banner_theme;
        
        $scalar_scheme = get_option('dwic_scheme','');
        if(!$scalar_scheme && !empty($style['scheme'])) $scalar_scheme=$style['scheme'];
        if(!$scalar_scheme) $scalar_scheme='light';
        $style['scheme']=apply_filters('dwic_active_scheme',$scalar_scheme);

        $base_css = @file_get_contents(DWIC_PATH.'assets/css/banner.css');
        if($base_css===false) $base_css='';

        $base_css = $this->maybe_harden_css($base_css);
        $base_css .= "\n".$this->runtime_dynamic_css($style['scheme']);

        wp_register_style('dwic-banner', false, [], $this->version);
        wp_add_inline_style('dwic-banner',$base_css);
        wp_enqueue_style('dwic-banner');

        wp_enqueue_script('dwic-banner', DWIC_URL.'assets/js/banner.js', [], $this->version, true);

        $i18n=$this->translations();
        $geo = new \DWIC\GeoTarget();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR']) ) : '';
        $country=$geo->get_country($ip);
        $law=\DWIC\GeoTarget::law_for_country($country);
        $is_ccpa = ($law==='ccpa' || strtoupper($country)==='US');

        $categories=[
            ['slug'=>'necessary','name'=>__('Necessary','cookiejar'),'desc'=>$i18n['necessary_desc'],'required'=>true],
            ['slug'=>'functional','name'=>__('Functional','cookiejar'),'desc'=>__('For enhanced features.','cookiejar')],
            ['slug'=>'analytics','name'=>__('Analytics','cookiejar'),'desc'=>__('To help us improve our site.','cookiejar')],
            ['slug'=>'ads','name'=>__('Advertising','cookiejar'),'desc'=>__('For personalized ads.','cookiejar')],
            ['slug'=>'chatbot','name'=>__('AI Chatbot','cookiejar'),'desc'=>__('Enable our AI-powered assistant. This may set cookies or process your data.','cookiejar')]
        ];
        if($is_ccpa){
            $categories[]=[
                'slug'=>'donotsell',
                'name'=>$i18n['do_not_sell'],
                'desc'=>$i18n['do_not_sell_desc'],
            ];
        }

        $features=get_option('dwic_features',[]);
        if(!is_array($features)) $features=$features?[$features]:[];
        sort($features,SORT_STRING);
        $raw_categories=array_map(function($c){return $c['slug'].'|'.(!empty($c['required'])?'1':'0');},$categories);
        sort($raw_categories,SORT_STRING);

        $duration=(int)get_option('dwic_days',180);
        $include_message = defined('DWIC_HASH_INCLUDE_MESSAGE') ? DWIC_HASH_INCLUDE_MESSAGE : true;
        $message=(string)get_option('dwic_message','');
        $fingerprint=[
            'categories'=>$raw_categories,
            'duration'=>$duration,
            'features'=>$features
        ];
        if($include_message){
            $fingerprint['message']=preg_replace('/\s+/',' ',$message);
        }
        $hash_lock=get_option('dwic_hash_lock','');
        $config_hash=$hash_lock?$hash_lock:md5(json_encode($fingerprint,JSON_UNESCAPED_UNICODE));

        wp_localize_script('dwic-banner','DWIC_I18N',$i18n);
        // Branding footer HTML (configurable)
        $branding_enabled = get_option('dwic_branding_enabled', 'no') === 'yes';
        $default_branding = '<div class="dwic-footer" aria-hidden="true">Powered by CookieJar — Web Solutions Made Simple by DemeWebsolutions.com © 2025 My Deme, LLC.</div>';
        $branding_html = '';
        if ($branding_enabled) {
            $branding_html = (string)get_option('dwic_branding_html', $default_branding);
        }

        wp_localize_script('dwic-banner','DWIC_CONFIG',[
            'ajaxurl'=>admin_url('admin-ajax.php'),
            'message'=>$message,
            'duration'=>$duration,
            'ga'=>get_option('dwic_ga',''),
            'enableConsentMode'=>(int)get_option('dwic_enable_consent_mode',0),
            'style'=>$style,
            'categories'=>$categories,
            'law'=>$law,
            'country'=>$country,
            'brand'=>'',
            'icon'=> (self::DWIC_COOKIE_ICON ?: (defined('DWIC_ICON_FALLBACK') ? DWIC_ICON_FALLBACK : '')),
            'lang'=>\DWIC\Localization::current_lang(),
            'features'=>$features,
            'pluginVersion'=>$this->version,
            'configVersion'=>$config_hash,
            'cookieName'=>'dwic_consent_v2',
            'showDNS'=>0,
            'hasDoNotSellCat'=>$is_ccpa?1:0,
            'perCategoryExpiry'=>[
                'necessary'=>365,'functional'=>365,'analytics'=>180,'ads'=>180,'chatbot'=>90,'donotsell'=>365
            ],
            'hardenCSS'=>1,
            'brandingHtml'=>$branding_html
        ]);
    }

    public function render_banner(){
        echo '<div id="dwic-consent-banner-root"></div>';
    }
    public function render_revisit(){ ?>
        <button id="dwic-revisit-btn" class="dwic-revisit-btn"
            aria-label="<?php esc_attr_e('Review Consent Preferences','cookiejar');?>"
            style="display:none;position:fixed;left:18px;bottom:18px;z-index:99999;border-radius:50%;width:46px;height:46px;border:0;cursor:pointer;padding:0;box-shadow:0 2px 8px rgba(0,0,0,0.18);display:flex;align-items:center;justify-content:center;transition:background .3s,box-shadow .2s;">
            <img src="<?php echo esc_url(self::DWIC_COOKIE_ICON ?: (defined('DWIC_ICON_FALLBACK') ? DWIC_ICON_FALLBACK : ''));?>" alt="<?php esc_attr_e('Cookie consent preferences icon','cookiejar');?>" style="width:36px;height:36px;display:block;">
        </button>
    <?php }

    public function inject_ga(){
        $enable=(int)get_option('dwic_enable_consent_mode',0);
        $ga=get_option('dwic_ga','');
        if($enable && $ga){ ?>
            <script>
            window.dataLayer=window.dataLayer||[];
            function gtag(){dataLayer.push(arguments);}
            gtag('consent','default',{
              ad_storage:'denied',
              ad_user_data:'denied',
              ad_personalization:'denied',
              analytics_storage:'denied',
              functionality_storage:'denied',
              personalization_storage:'denied',
              security_storage:'granted'
            });
            </script>
        <?php }
    }

    public function enqueue_ga(){
        $ga=get_option('dwic_ga','');
        if(!$ga) return;
        $ga_src = 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($ga);
        
        // Enqueue external gtag script
        wp_enqueue_script('google-gtag', esc_url($ga_src), [], $this->version, false);
        
        // Add inline gtag config
        $inline_script = "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js', new Date());gtag('config','" . esc_js($ga) . "');";
        wp_add_inline_script('google-gtag', $inline_script);
    }

    /**
     * Add async attribute to gtag script tag.
     */
    public function add_async_to_gtag($tag, $handle){
        if('google-gtag' === $handle){
            return str_replace(' src', ' async src', $tag);
        }
        return $tag;
    }

    public function inject_ga_after(){
        $enable=(int)get_option('dwic_enable_consent_mode',0);
        if($enable && isset($_COOKIE['dwic_consent_v2'])){
            $cookie_value = isset($_COOKIE['dwic_consent_v2']) ? sanitize_text_field( wp_unslash($_COOKIE['dwic_consent_v2']) ) : '';
            $p=json_decode(stripslashes($cookie_value),true);
            if(is_array($p)){
                $cats=$p['categories']??[];
                if(!isset($cats['donotsell']) && !empty($p['dns'])) $cats['donotsell']=true;
                $donotsell=!empty($cats['donotsell']);
                $analytics=!empty($cats['analytics']);
                $adsRaw=!empty($cats['ads']);
                $functional=!empty($cats['functional']);
                $ads=$adsRaw && !$donotsell; ?>
                <script>
                (function(){
                  function gtag(){dataLayer.push(arguments);}
                  gtag('consent','update',{
                    ad_storage: <?php echo $ads?'\'granted\'':'\'denied\'';?>,
                    analytics_storage: <?php echo $analytics?'\'granted\'':'\'denied\'';?>,
                    ad_user_data: <?php echo $ads?'\'granted\'':'\'denied\'';?>,
                    ad_personalization: <?php echo $ads?'\'granted\'':'\'denied\'';?>,
                    functionality_storage: <?php echo $functional?'\'granted\'':'\'denied\'';?>,
                    personalization_storage: <?php echo $functional?'\'granted\'':'\'denied\'';?>,
                    security_storage:'granted'
                  });
                })();
                </script>
            <?php }
        }
    }
}