<?php
/**
 * AI Newsletter Generator Pro - WordPress 위젯 클래스
 * 구독 폼을 WordPress 위젯으로 사용할 수 있도록 하는 클래스입니다.
 *
 * @package AI_Newsletter_Generator_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 구독 폼 위젯 클래스
 */
class AINL_Widget extends WP_Widget {
    
    /**
     * 구독 폼 시스템 인스턴스
     */
    private $subscription_form;
    
    /**
     * 생성자
     */
    public function __construct() {
        // 위젯 설정
        $widget_opts = array(
            'classname'   => 'ainl_subscription_widget',
            'description' => __('AI Newsletter Generator Pro 구독 폼을 표시합니다.', 'ai-newsletter-generator-pro'),
            'customize_selective_refresh' => true
        );
        
        // 부모 클래스 초기화
        parent::__construct(
            'ainl_subscription_form', // Widget ID
            __('뉴스레터 구독 폼', 'ai-newsletter-generator-pro'), // Widget name
            $widget_opts
        );
        
        // 구독 폼 시스템 인스턴스 가져오기
        $this->subscription_form = AINL_Subscription_Form::get_instance();
        
        // 위젯 등록 훅
        add_action('widgets_init', array($this, 'register_widget'));
        
        // 관리자에서 위젯 스타일 로드
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    /**
     * 위젯 등록
     */
    public function register_widget() {
        register_widget('AINL_Widget');
    }
    
    /**
     * 위젯 출력 (프론트엔드)
     * 
     * @param array $args 위젯 인수
     * @param array $instance 위젯 인스턴스 설정
     */
    public function widget($args, $instance) {
        // 기본값 설정
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $description = !empty($instance['description']) ? $instance['description'] : '';
        $style = !empty($instance['style']) ? $instance['style'] : 'default';
        $show_name = !empty($instance['show_name']) ? 'true' : 'false';
        $show_categories = !empty($instance['show_categories']) ? 'true' : 'false';
        $show_gdpr = !empty($instance['show_gdpr']) ? 'true' : 'false';
        $button_text = !empty($instance['button_text']) ? $instance['button_text'] : __('구독하기', 'ai-newsletter-generator-pro');
        $redirect_url = !empty($instance['redirect_url']) ? $instance['redirect_url'] : '';
        
        // 위젯 제목 필터 적용
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);
        
        // 위젯 시작
        echo $args['before_widget'];
        
        // 제목 출력 (위젯 제목과 폼 제목을 구분)
        if (!empty($title)) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
            // 폼에서는 제목을 숨기기 위해 빈 문자열로 설정
            $form_title = '';
        } else {
            $form_title = $title;
        }
        
        // 구독 폼 렌더링
        $shortcode_atts = array(
            'style' => $style,
            'title' => $form_title,
            'description' => $description,
            'button_text' => $button_text,
            'show_name' => $show_name,
            'show_categories' => $show_categories,
            'show_gdpr' => $show_gdpr,
            'redirect_url' => $redirect_url,
            'class' => 'ainl-widget-form'
        );
        
        echo $this->subscription_form->render_subscription_form($shortcode_atts);
        
        // 위젯 끝
        echo $args['after_widget'];
    }
    
    /**
     * 위젯 설정 폼 (관리자)
     * 
     * @param array $instance 현재 위젯 인스턴스 설정
     */
    public function form($instance) {
        // 기본값 설정
        $defaults = array(
            'title' => __('뉴스레터 구독', 'ai-newsletter-generator-pro'),
            'description' => __('최신 소식을 받아보세요.', 'ai-newsletter-generator-pro'),
            'style' => 'default',
            'show_name' => true,
            'show_categories' => false,
            'show_gdpr' => true,
            'button_text' => __('구독하기', 'ai-newsletter-generator-pro'),
            'redirect_url' => ''
        );
        
        $instance = wp_parse_args((array) $instance, $defaults);
        ?>
        
        <div class="ainl-widget-form">
            <!-- 위젯 제목 -->
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                    <?php _e('위젯 제목:', 'ai-newsletter-generator-pro'); ?>
                </label>
                <input 
                    class="widefat" 
                    id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                    type="text" 
                    value="<?php echo esc_attr($instance['title']); ?>"
                />
                <small class="description">
                    <?php _e('위젯 영역에 표시될 제목입니다.', 'ai-newsletter-generator-pro'); ?>
                </small>
            </p>
            
            <!-- 폼 설명 -->
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('description')); ?>">
                    <?php _e('폼 설명:', 'ai-newsletter-generator-pro'); ?>
                </label>
                <textarea 
                    class="widefat" 
                    rows="3"
                    id="<?php echo esc_attr($this->get_field_id('description')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('description')); ?>"
                ><?php echo esc_textarea($instance['description']); ?></textarea>
                <small class="description">
                    <?php _e('구독 폼 아래에 표시될 설명 문구입니다.', 'ai-newsletter-generator-pro'); ?>
                </small>
            </p>
            
            <!-- 폼 스타일 -->
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('style')); ?>">
                    <?php _e('폼 스타일:', 'ai-newsletter-generator-pro'); ?>
                </label>
                <select 
                    class="widefat" 
                    id="<?php echo esc_attr($this->get_field_id('style')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('style')); ?>"
                >
                    <option value="default" <?php selected($instance['style'], 'default'); ?>>
                        <?php _e('기본 스타일', 'ai-newsletter-generator-pro'); ?>
                    </option>
                    <option value="minimal" <?php selected($instance['style'], 'minimal'); ?>>
                        <?php _e('미니멀 스타일', 'ai-newsletter-generator-pro'); ?>
                    </option>
                    <option value="modern" <?php selected($instance['style'], 'modern'); ?>>
                        <?php _e('모던 스타일', 'ai-newsletter-generator-pro'); ?>
                    </option>
                </select>
            </p>
            
            <!-- 버튼 텍스트 -->
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('button_text')); ?>">
                    <?php _e('버튼 텍스트:', 'ai-newsletter-generator-pro'); ?>
                </label>
                <input 
                    class="widefat" 
                    id="<?php echo esc_attr($this->get_field_id('button_text')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('button_text')); ?>" 
                    type="text" 
                    value="<?php echo esc_attr($instance['button_text']); ?>"
                />
            </p>
            
            <!-- 리다이렉트 URL -->
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('redirect_url')); ?>">
                    <?php _e('구독 완료 후 이동할 페이지 (선택사항):', 'ai-newsletter-generator-pro'); ?>
                </label>
                <input 
                    class="widefat" 
                    id="<?php echo esc_attr($this->get_field_id('redirect_url')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('redirect_url')); ?>" 
                    type="url" 
                    value="<?php echo esc_url($instance['redirect_url']); ?>"
                    placeholder="https://example.com/thank-you"
                />
                <small class="description">
                    <?php _e('비워두면 현재 페이지에서 성공 메시지를 표시합니다.', 'ai-newsletter-generator-pro'); ?>
                </small>
            </p>
            
            <!-- 필드 표시 옵션 -->
            <h4><?php _e('표시할 필드 선택', 'ai-newsletter-generator-pro'); ?></h4>
            
            <p>
                <input 
                    class="checkbox" 
                    type="checkbox" 
                    <?php checked($instance['show_name']); ?> 
                    id="<?php echo esc_attr($this->get_field_id('show_name')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('show_name')); ?>"
                />
                <label for="<?php echo esc_attr($this->get_field_id('show_name')); ?>">
                    <?php _e('이름 필드 표시', 'ai-newsletter-generator-pro'); ?>
                </label>
            </p>
            
            <p>
                <input 
                    class="checkbox" 
                    type="checkbox" 
                    <?php checked($instance['show_categories']); ?> 
                    id="<?php echo esc_attr($this->get_field_id('show_categories')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('show_categories')); ?>"
                />
                <label for="<?php echo esc_attr($this->get_field_id('show_categories')); ?>">
                    <?php _e('카테고리 선택 필드 표시', 'ai-newsletter-generator-pro'); ?>
                </label>
            </p>
            
            <p>
                <input 
                    class="checkbox" 
                    type="checkbox" 
                    <?php checked($instance['show_gdpr']); ?> 
                    id="<?php echo esc_attr($this->get_field_id('show_gdpr')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('show_gdpr')); ?>"
                />
                <label for="<?php echo esc_attr($this->get_field_id('show_gdpr')); ?>">
                    <?php _e('GDPR 동의 체크박스 표시', 'ai-newsletter-generator-pro'); ?>
                </label>
            </p>
            
            <!-- 미리보기 -->
            <div class="ainl-widget-preview">
                <h4><?php _e('미리보기', 'ai-newsletter-generator-pro'); ?></h4>
                <div class="ainl-preview-container" style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9; margin-top: 10px;">
                    <p><em><?php _e('실제 미리보기는 프론트엔드에서 확인하세요.', 'ai-newsletter-generator-pro'); ?></em></p>
                    <div style="border: 1px solid #ccc; padding: 10px; background: white; border-radius: 4px;">
                        <strong><?php echo esc_html($instance['description']); ?></strong><br>
                        <input type="email" placeholder="<?php esc_attr_e('이메일 주소', 'ai-newsletter-generator-pro'); ?>" style="width: 100%; margin: 5px 0; padding: 8px;" disabled>
                        <?php if ($instance['show_name']): ?>
                            <input type="text" placeholder="<?php esc_attr_e('이름', 'ai-newsletter-generator-pro'); ?>" style="width: 100%; margin: 5px 0; padding: 8px;" disabled>
                        <?php endif; ?>
                        <button style="width: 100%; padding: 10px; background: #007cba; color: white; border: none; border-radius: 4px;" disabled>
                            <?php echo esc_html($instance['button_text']); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .ainl-widget-form .description {
            color: #666;
            font-style: italic;
            margin-top: 3px;
        }
        .ainl-widget-preview {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        </style>
        
        <?php
    }
    
    /**
     * 위젯 설정 업데이트
     * 
     * @param array $new_instance 새로운 설정
     * @param array $old_instance 이전 설정
     * @return array 정제된 설정
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        
        // 텍스트 필드 정제
        $instance['title'] = (!empty($new_instance['title'])) ? 
            sanitize_text_field($new_instance['title']) : '';
        $instance['description'] = (!empty($new_instance['description'])) ? 
            sanitize_textarea_field($new_instance['description']) : '';
        $instance['button_text'] = (!empty($new_instance['button_text'])) ? 
            sanitize_text_field($new_instance['button_text']) : __('구독하기', 'ai-newsletter-generator-pro');
        
        // URL 정제
        $instance['redirect_url'] = (!empty($new_instance['redirect_url'])) ? 
            esc_url_raw($new_instance['redirect_url']) : '';
        
        // 스타일 선택 정제
        $allowed_styles = array('default', 'minimal', 'modern');
        $instance['style'] = (!empty($new_instance['style']) && in_array($new_instance['style'], $allowed_styles)) ? 
            $new_instance['style'] : 'default';
        
        // 체크박스 정제
        $instance['show_name'] = !empty($new_instance['show_name']);
        $instance['show_categories'] = !empty($new_instance['show_categories']);
        $instance['show_gdpr'] = !empty($new_instance['show_gdpr']);
        
        return $instance;
    }
    
    /**
     * 관리자 스크립트 로드
     * 
     * @param string $hook_suffix 현재 관리자 페이지
     */
    public function admin_enqueue_scripts($hook_suffix) {
        // 위젯 페이지에서만 로드
        if ('widgets.php' === $hook_suffix || 'customize.php' === $hook_suffix) {
            wp_enqueue_script(
                'ainl-widget-admin',
                AINL_PLUGIN_URL . 'assets/js/widget-admin.js',
                array('jquery'),
                AINL_PLUGIN_VERSION,
                true
            );
            
            wp_enqueue_style(
                'ainl-widget-admin',
                AINL_PLUGIN_URL . 'assets/css/widget-admin.css',
                array(),
                AINL_PLUGIN_VERSION
            );
        }
    }
}

/**
 * 위젯 초기화 함수
 */
function ainl_register_widget() {
    new AINL_Widget();
}

// 위젯 등록 훅
add_action('widgets_init', 'ainl_register_widget'); 