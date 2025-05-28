<?php
/**
 * AI Newsletter Generator Pro - 구텐베르그 블록 클래스
 * 구독 폼을 구텐베르그 블록으로 사용할 수 있도록 하는 클래스입니다.
 *
 * @package AI_Newsletter_Generator_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 구독 폼 구텐베르그 블록 클래스
 */
class AINL_Gutenberg_Block {
    
    /**
     * 구독 폼 시스템 인스턴스
     */
    private $subscription_form;
    
    /**
     * 생성자
     */
    public function __construct() {
        // 구독 폼 시스템 인스턴스 가져오기
        $this->subscription_form = AINL_Subscription_Form::get_instance();
        
        // 블록 등록 훅
        add_action('init', array($this, 'register_block'));
        
        // 관리자 에셋 로드
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        
        // 블록 카테고리 추가
        add_filter('block_categories_all', array($this, 'add_block_category'), 10, 2);
    }
    
    /**
     * 블록 등록
     */
    public function register_block() {
        // WordPress 5.0 이상에서만 동작
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // 블록 등록
        register_block_type('ainl/subscription-form', array(
            'editor_script' => 'ainl-subscription-block-editor',
            'editor_style'  => 'ainl-subscription-block-editor',
            'style'         => 'ainl-subscription-form', // 프론트엔드 스타일
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'style' => array(
                    'type' => 'string',
                    'default' => 'default'
                ),
                'title' => array(
                    'type' => 'string',
                    'default' => '뉴스레터 구독'
                ),
                'description' => array(
                    'type' => 'string',
                    'default' => '최신 소식을 받아보세요.'
                ),
                'buttonText' => array(
                    'type' => 'string',
                    'default' => '구독하기'
                ),
                'showName' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'showCategories' => array(
                    'type' => 'boolean',
                    'default' => false
                ),
                'showGdpr' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'redirectUrl' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'className' => array(
                    'type' => 'string',
                    'default' => ''
                )
            )
        ));
    }
    
    /**
     * 블록 렌더링 콜백
     * 
     * @param array $attributes 블록 속성
     * @param string $content 블록 내용
     * @return string 렌더링된 HTML
     */
    public function render_block($attributes, $content = '') {
        // 기본값 설정
        $attributes = wp_parse_args($attributes, array(
            'style' => 'default',
            'title' => '뉴스레터 구독',
            'description' => '최신 소식을 받아보세요.',
            'buttonText' => '구독하기',
            'showName' => true,
            'showCategories' => false,
            'showGdpr' => true,
            'redirectUrl' => '',
            'className' => ''
        ));
        
        // 쇼트코드 속성 변환
        $shortcode_atts = array(
            'style' => $attributes['style'],
            'title' => $attributes['title'],
            'description' => $attributes['description'],
            'button_text' => $attributes['buttonText'],
            'show_name' => $attributes['showName'] ? 'true' : 'false',
            'show_categories' => $attributes['showCategories'] ? 'true' : 'false',
            'show_gdpr' => $attributes['showGdpr'] ? 'true' : 'false',
            'redirect_url' => $attributes['redirectUrl'],
            'class' => 'ainl-block-form ' . $attributes['className']
        );
        
        // 구독 폼 렌더링
        return $this->subscription_form->render_subscription_form($shortcode_atts);
    }
    
    /**
     * 블록 에디터 에셋 로드
     */
    public function enqueue_block_editor_assets() {
        // 블록 에디터 JavaScript
        wp_enqueue_script(
            'ainl-subscription-block-editor',
            AINL_PLUGIN_URL . 'assets/js/subscription-block.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            AINL_PLUGIN_VERSION,
            true
        );
        
        // 블록 에디터 스타일
        wp_enqueue_style(
            'ainl-subscription-block-editor',
            AINL_PLUGIN_URL . 'assets/css/subscription-block-editor.css',
            array('wp-edit-blocks'),
            AINL_PLUGIN_VERSION
        );
        
        // 다국어화
        wp_set_script_translations(
            'ainl-subscription-block-editor',
            'ai-newsletter-generator-pro',
            AINL_PLUGIN_DIR . 'languages'
        );
        
        // 블록에 전달할 데이터
        wp_localize_script('ainl-subscription-block-editor', 'ainlBlockData', array(
            'pluginUrl' => AINL_PLUGIN_URL,
            'styles' => array(
                'default' => __('기본 스타일', 'ai-newsletter-generator-pro'),
                'minimal' => __('미니멀 스타일', 'ai-newsletter-generator-pro'),
                'modern' => __('모던 스타일', 'ai-newsletter-generator-pro')
            ),
            'previewUrl' => admin_url('admin-ajax.php?action=ainl_block_preview')
        ));
    }
    
    /**
     * 블록 카테고리 추가
     * 
     * @param array $categories 기존 카테고리
     * @param object $post 현재 포스트
     * @return array 수정된 카테고리
     */
    public function add_block_category($categories, $post) {
        return array_merge(
            array(
                array(
                    'slug'  => 'ainl-blocks',
                    'title' => __('AI Newsletter Generator Pro', 'ai-newsletter-generator-pro'),
                    'icon'  => 'email-alt'
                )
            ),
            $categories
        );
    }
}

/**
 * 구텐베르그 블록 초기화
 */
function ainl_init_gutenberg_block() {
    new AINL_Gutenberg_Block();
}

// 블록 초기화 훅
add_action('init', 'ainl_init_gutenberg_block'); 