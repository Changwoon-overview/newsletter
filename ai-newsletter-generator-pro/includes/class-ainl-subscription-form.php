<?php
/**
 * 구독 폼 시스템 관리 클래스
 * 쇼트코드, AJAX 처리, 폼 렌더링 기능을 포함합니다.
 *
 * @package AI_Newsletter_Generator_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 구독 폼 시스템 클래스
 */
class AINL_Subscription_Form {
    
    /**
     * 클래스 인스턴스
     */
    private static $instance = null;
    
    /**
     * 구독자 관리자 인스턴스
     */
    private $subscriber_manager;
    
    /**
     * 이메일 매니저 인스턴스
     */
    private $email_manager;
    
    /**
     * 폼 스타일 옵션들
     */
    private $form_styles = array(
        'default' => '기본 스타일',
        'minimal' => '미니멀 스타일',
        'modern' => '모던 스타일'
    );
    
    /**
     * 생성자
     */
    public function __construct() {
        $this->subscriber_manager = new AINL_Subscriber_Manager();
        $this->email_manager = new AINL_Email_Manager();
        
        $this->init_hooks();
    }
    
    /**
     * 싱글톤 인스턴스 반환
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * WordPress 훅 초기화
     */
    private function init_hooks() {
        // 쇼트코드 등록
        add_action('init', array($this, 'register_shortcodes'));
        
        // 스타일 및 스크립트 로드
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // AJAX 핸들러
        add_action('wp_ajax_ainl_subscribe', array($this, 'ajax_subscribe'));
        add_action('wp_ajax_nopriv_ainl_subscribe', array($this, 'ajax_subscribe'));
        add_action('wp_ajax_ainl_confirm_subscription', array($this, 'ajax_confirm_subscription'));
        add_action('wp_ajax_nopriv_ainl_confirm_subscription', array($this, 'ajax_confirm_subscription'));
        
        // 이메일 확인 링크 처리
        add_action('init', array($this, 'handle_email_confirmation'));
        
        // 구독 취소 링크 처리
        add_action('init', array($this, 'handle_unsubscribe'));
    }
    
    /**
     * 쇼트코드 등록
     */
    public function register_shortcodes() {
        add_shortcode('ai_newsletter_form', array($this, 'render_subscription_form'));
        add_shortcode('ai_newsletter_popup', array($this, 'render_popup_form'));
    }
    
    /**
     * 프론트엔드 에셋 로드
     */
    public function enqueue_frontend_assets() {
        // CSS 로드
        wp_enqueue_style(
            'ainl-subscription-form',
            AINL_PLUGIN_URL . 'assets/css/subscription-form.css',
            array(),
            AINL_PLUGIN_VERSION
        );
        
        // JavaScript 로드
        wp_enqueue_script(
            'ainl-subscription-form',
            AINL_PLUGIN_URL . 'assets/js/subscription-form.js',
            array('jquery'),
            AINL_PLUGIN_VERSION,
            true
        );
        
        // AJAX URL 및 nonce 전달
        wp_localize_script('ainl-subscription-form', 'ainl_form_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ainl_subscription_nonce'),
            'messages' => array(
                'required_email' => __('이메일 주소를 입력해주세요.', 'ai-newsletter-generator-pro'),
                'invalid_email' => __('올바른 이메일 주소를 입력해주세요.', 'ai-newsletter-generator-pro'),
                'processing' => __('처리 중...', 'ai-newsletter-generator-pro'),
                'success' => __('구독이 완료되었습니다. 확인 이메일을 확인해주세요.', 'ai-newsletter-generator-pro'),
                'already_subscribed' => __('이미 구독된 이메일 주소입니다.', 'ai-newsletter-generator-pro'),
                'error' => __('오류가 발생했습니다. 다시 시도해주세요.', 'ai-newsletter-generator-pro'),
                'gdpr_required' => __('개인정보 처리에 동의해주세요.', 'ai-newsletter-generator-pro')
            )
        ));
    }
    
    /**
     * 구독 폼 렌더링 (쇼트코드)
     * 
     * @param array $atts 쇼트코드 속성
     * @return string 렌더링된 HTML
     */
    public function render_subscription_form($atts) {
        $atts = shortcode_atts(array(
            'style' => 'default',
            'title' => __('뉴스레터 구독', 'ai-newsletter-generator-pro'),
            'description' => __('최신 소식을 받아보세요.', 'ai-newsletter-generator-pro'),
            'button_text' => __('구독하기', 'ai-newsletter-generator-pro'),
            'show_name' => 'true',
            'show_categories' => 'false',
            'show_gdpr' => 'true',
            'redirect_url' => '',
            'class' => ''
        ), $atts, 'ai_newsletter_form');
        
        // 고유 ID 생성
        $form_id = 'ainl-form-' . uniqid();
        
        // 카테고리 목록 가져오기 (필요한 경우)
        $categories = array();
        if ($atts['show_categories'] === 'true') {
            $categories = $this->get_subscription_categories();
        }
        
        // 폼 HTML 생성
        ob_start();
        ?>
        <div class="ainl-subscription-form ainl-style-<?php echo esc_attr($atts['style']); ?> <?php echo esc_attr($atts['class']); ?>" id="<?php echo esc_attr($form_id); ?>">
            <?php if (!empty($atts['title'])): ?>
                <h3 class="ainl-form-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>
            
            <?php if (!empty($atts['description'])): ?>
                <p class="ainl-form-description"><?php echo esc_html($atts['description']); ?></p>
            <?php endif; ?>
            
            <form class="ainl-subscription-form-inner" data-form-id="<?php echo esc_attr($form_id); ?>" data-redirect="<?php echo esc_url($atts['redirect_url']); ?>">
                <div class="ainl-form-fields">
                    <?php if ($atts['show_name'] === 'true'): ?>
                        <div class="ainl-field-group">
                            <label for="<?php echo esc_attr($form_id); ?>_name" class="ainl-field-label">
                                <?php _e('이름', 'ai-newsletter-generator-pro'); ?>
                            </label>
                            <input 
                                type="text" 
                                id="<?php echo esc_attr($form_id); ?>_name" 
                                name="subscriber_name" 
                                class="ainl-form-input ainl-name-input"
                                placeholder="<?php esc_attr_e('이름을 입력하세요', 'ai-newsletter-generator-pro'); ?>"
                            />
                        </div>
                    <?php endif; ?>
                    
                    <div class="ainl-field-group">
                        <label for="<?php echo esc_attr($form_id); ?>_email" class="ainl-field-label">
                            <?php _e('이메일 주소', 'ai-newsletter-generator-pro'); ?> <span class="ainl-required">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="<?php echo esc_attr($form_id); ?>_email" 
                            name="subscriber_email" 
                            class="ainl-form-input ainl-email-input"
                            placeholder="<?php esc_attr_e('example@email.com', 'ai-newsletter-generator-pro'); ?>"
                            required
                        />
                    </div>
                    
                    <?php if ($atts['show_categories'] === 'true' && !empty($categories)): ?>
                        <div class="ainl-field-group">
                            <label class="ainl-field-label">
                                <?php _e('관심 카테고리', 'ai-newsletter-generator-pro'); ?>
                            </label>
                            <div class="ainl-categories-list">
                                <?php foreach ($categories as $category): ?>
                                    <label class="ainl-category-item">
                                        <input 
                                            type="checkbox" 
                                            name="subscriber_categories[]" 
                                            value="<?php echo esc_attr($category['id']); ?>"
                                            class="ainl-category-checkbox"
                                        />
                                        <span class="ainl-category-name"><?php echo esc_html($category['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_gdpr'] === 'true'): ?>
                        <div class="ainl-field-group ainl-gdpr-field">
                            <label class="ainl-gdpr-label">
                                <input 
                                    type="checkbox" 
                                    name="gdpr_consent" 
                                    class="ainl-gdpr-checkbox"
                                    required
                                />
                                <span class="ainl-gdpr-text">
                                    <?php 
                                    printf(
                                        __('개인정보 처리방침에 동의합니다. %s', 'ai-newsletter-generator-pro'),
                                        '<a href="' . esc_url(get_privacy_policy_url()) . '" target="_blank">' . __('자세히 보기', 'ai-newsletter-generator-pro') . '</a>'
                                    ); 
                                    ?>
                                </span>
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="ainl-form-actions">
                    <button type="submit" class="ainl-submit-btn">
                        <span class="ainl-btn-text"><?php echo esc_html($atts['button_text']); ?></span>
                        <span class="ainl-btn-loading" style="display: none;">
                            <span class="ainl-spinner"></span>
                            <?php _e('처리 중...', 'ai-newsletter-generator-pro'); ?>
                        </span>
                    </button>
                </div>
                
                <div class="ainl-form-messages">
                    <div class="ainl-success-message" style="display: none;"></div>
                    <div class="ainl-error-message" style="display: none;"></div>
                </div>
                
                <?php wp_nonce_field('ainl_subscription_nonce', 'ainl_nonce'); ?>
            </form>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 팝업 형태의 구독 폼 렌더링
     * 
     * @param array $atts 쇼트코드 속성
     * @return string 렌더링된 HTML
     */
    public function render_popup_form($atts) {
        $atts = shortcode_atts(array(
            'trigger' => 'time', // time, scroll, exit
            'delay' => '5000', // milliseconds
            'scroll_percent' => '50',
            'style' => 'modern'
        ), $atts, 'ai_newsletter_popup');
        
        // 팝업용 스크립트 추가
        wp_add_inline_script('ainl-subscription-form', "
            jQuery(document).ready(function($) {
                // 팝업 트리거 로직 구현
                var trigger = '{$atts['trigger']}';
                var delay = {$atts['delay']};
                var scrollPercent = {$atts['scroll_percent']};
                
                // 팝업 트리거 구현 (시간, 스크롤, 종료 시점)
                AINL_Form.initPopup({
                    trigger: trigger,
                    delay: delay,
                    scrollPercent: scrollPercent
                });
            });
        ");
        
        // 팝업 HTML 생성
        ob_start();
        ?>
        <div id="ainl-popup-overlay" class="ainl-popup-overlay" style="display: none;">
            <div class="ainl-popup-container ainl-style-<?php echo esc_attr($atts['style']); ?>">
                <div class="ainl-popup-close">&times;</div>
                <div class="ainl-popup-content">
                    <?php echo $this->render_subscription_form(array('style' => $atts['style'])); ?>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * AJAX 구독 처리
     */
    public function ajax_subscribe() {
        // Nonce 검증
        if (!wp_verify_nonce($_POST['ainl_nonce'], 'ainl_subscription_nonce')) {
            wp_send_json_error(array(
                'message' => __('보안 검증에 실패했습니다.', 'ai-newsletter-generator-pro')
            ));
        }
        
        // 입력 데이터 검증
        $email = sanitize_email($_POST['subscriber_email']);
        $name = isset($_POST['subscriber_name']) ? sanitize_text_field($_POST['subscriber_name']) : '';
        $categories = isset($_POST['subscriber_categories']) ? array_map('intval', $_POST['subscriber_categories']) : array();
        $gdpr_consent = isset($_POST['gdpr_consent']) && $_POST['gdpr_consent'] === 'on';
        
        // 필수 필드 검증
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array(
                'message' => __('올바른 이메일 주소를 입력해주세요.', 'ai-newsletter-generator-pro')
            ));
        }
        
        // GDPR 동의 확인
        if (!$gdpr_consent) {
            wp_send_json_error(array(
                'message' => __('개인정보 처리에 동의해주세요.', 'ai-newsletter-generator-pro')
            ));
        }
        
        // 중복 구독 확인
        if ($this->subscriber_manager->email_exists($email)) {
            wp_send_json_error(array(
                'message' => __('이미 구독된 이메일 주소입니다.', 'ai-newsletter-generator-pro')
            ));
        }
        
        // 구독자 데이터 준비
        $subscriber_data = array(
            'email' => $email,
            'name' => $name,
            'first_name' => $this->extract_first_name($name),
            'last_name' => $this->extract_last_name($name),
            'status' => 'pending', // 더블 옵트인 대기 상태
            'source' => 'subscription_form',
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'confirmation_token' => wp_generate_password(32, false),
            'unsubscribe_token' => wp_generate_password(32, false),
            'metadata' => wp_json_encode(array(
                'form_data' => $_POST,
                'referrer' => wp_get_referer(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ))
        );
        
        // 구독자 추가
        $subscriber_id = $this->subscriber_manager->add_subscriber($subscriber_data);
        
        if (!$subscriber_id) {
            wp_send_json_error(array(
                'message' => __('구독 처리 중 오류가 발생했습니다.', 'ai-newsletter-generator-pro')
            ));
        }
        
        // 카테고리 연결
        if (!empty($categories)) {
            foreach ($categories as $category_id) {
                $this->subscriber_manager->add_subscriber_to_category($subscriber_id, $category_id);
            }
        }
        
        // 확인 이메일 발송
        $this->send_confirmation_email($subscriber_data);
        
        // 성공 응답
        wp_send_json_success(array(
            'message' => __('구독이 완료되었습니다. 확인 이메일을 확인해주세요.', 'ai-newsletter-generator-pro'),
            'subscriber_id' => $subscriber_id
        ));
    }
    
    /**
     * 이메일 확인 링크 처리
     */
    public function handle_email_confirmation() {
        if (!isset($_GET['ainl_confirm']) || !isset($_GET['token'])) {
            return;
        }
        
        $token = sanitize_text_field($_GET['token']);
        
        // 토큰으로 구독자 찾기
        global $wpdb;
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ainl_subscribers WHERE confirmation_token = %s AND status = 'pending'",
            $token
        ));
        
        if (!$subscriber) {
            wp_die(__('잘못된 확인 링크입니다.', 'ai-newsletter-generator-pro'));
        }
        
        // 구독자 활성화
        $result = $wpdb->update(
            $wpdb->prefix . 'ainl_subscribers',
            array(
                'status' => 'active',
                'confirmed_at' => current_time('mysql'),
                'confirmation_token' => ''
            ),
            array('id' => $subscriber->id)
        );
        
        if ($result) {
            // 환영 이메일 발송 (옵션)
            $this->send_welcome_email($subscriber);
            
            // 확인 페이지로 리다이렉트
            wp_redirect(add_query_arg('confirmed', '1', home_url()));
            exit;
        } else {
            wp_die(__('확인 처리 중 오류가 발생했습니다.', 'ai-newsletter-generator-pro'));
        }
    }
    
    /**
     * 확인 이메일 발송
     * 
     * @param array $subscriber_data 구독자 데이터
     */
    private function send_confirmation_email($subscriber_data) {
        $confirmation_url = add_query_arg(array(
            'ainl_confirm' => '1',
            'token' => $subscriber_data['confirmation_token']
        ), home_url());
        
        $subject = __('이메일 주소를 확인해주세요', 'ai-newsletter-generator-pro');
        
        $message = sprintf(
            __('안녕하세요 %s님,

뉴스레터 구독을 완료하려면 아래 링크를 클릭해주세요:

%s

링크가 작동하지 않으면 브라우저에 직접 복사하여 붙여넣으세요.

감사합니다.', 'ai-newsletter-generator-pro'),
            $subscriber_data['name'] ?: $subscriber_data['email'],
            $confirmation_url
        );
        
        $this->email_manager->add_to_queue(
            $subscriber_data['email'],
            $subject,
            $message,
            array(),
            array(),
            'high'
        );
    }
    
    /**
     * 환영 이메일 발송
     * 
     * @param object $subscriber 구독자 객체
     */
    private function send_welcome_email($subscriber) {
        $subject = __('뉴스레터 구독을 환영합니다!', 'ai-newsletter-generator-pro');
        
        $message = sprintf(
            __('안녕하세요 %s님,

뉴스레터 구독을 환영합니다!

앞으로 유용한 정보와 최신 소식을 정기적으로 보내드리겠습니다.

구독을 취소하고 싶으시면 언제든지 아래 링크를 클릭하세요:
%s

감사합니다.', 'ai-newsletter-generator-pro'),
            $subscriber->name ?: $subscriber->email,
            $this->get_unsubscribe_url($subscriber->unsubscribe_token)
        );
        
        $this->email_manager->add_to_queue(
            $subscriber->email,
            $subject,
            $message
        );
    }
    
    /**
     * 구독 취소 링크 처리
     */
    public function handle_unsubscribe() {
        if (!isset($_GET['ainl_unsubscribe']) || !isset($_GET['token'])) {
            return;
        }
        
        $token = sanitize_text_field($_GET['token']);
        
        // 토큰으로 구독자 찾기
        global $wpdb;
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ainl_subscribers WHERE unsubscribe_token = %s",
            $token
        ));
        
        if (!$subscriber) {
            wp_die(__('잘못된 구독 취소 링크입니다.', 'ai-newsletter-generator-pro'));
        }
        
        // 구독자 비활성화
        $result = $wpdb->update(
            $wpdb->prefix . 'ainl_subscribers',
            array('status' => 'inactive'),
            array('id' => $subscriber->id)
        );
        
        if ($result) {
            wp_redirect(add_query_arg('unsubscribed', '1', home_url()));
            exit;
        } else {
            wp_die(__('구독 취소 처리 중 오류가 발생했습니다.', 'ai-newsletter-generator-pro'));
        }
    }
    
    /**
     * 구독 카테고리 목록 가져오기
     * 
     * @return array 카테고리 배열
     */
    private function get_subscription_categories() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT id, name, description FROM {$wpdb->prefix}ainl_categories WHERE is_default = 0 ORDER BY sort_order, name",
            ARRAY_A
        );
    }
    
    /**
     * 이름에서 성 추출
     * 
     * @param string $full_name 전체 이름
     * @return string 이름
     */
    private function extract_first_name($full_name) {
        $parts = explode(' ', trim($full_name));
        return isset($parts[0]) ? $parts[0] : '';
    }
    
    /**
     * 이름에서 성 추출
     * 
     * @param string $full_name 전체 이름
     * @return string 성
     */
    private function extract_last_name($full_name) {
        $parts = explode(' ', trim($full_name));
        if (count($parts) > 1) {
            array_shift($parts);
            return implode(' ', $parts);
        }
        return '';
    }
    
    /**
     * 클라이언트 IP 주소 가져오기
     * 
     * @return string IP 주소
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * 구독 취소 URL 생성
     * 
     * @param string $token 구독 취소 토큰
     * @return string 구독 취소 URL
     */
    private function get_unsubscribe_url($token) {
        return add_query_arg(array(
            'ainl_unsubscribe' => '1',
            'token' => $token
        ), home_url());
    }
} 