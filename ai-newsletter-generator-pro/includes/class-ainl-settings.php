<?php
/**
 * 설정 관리 클래스
 * 플러그인의 모든 설정을 관리하고 WordPress Settings API를 사용합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Settings {
    
    /**
     * 설정 옵션 이름
     */
    const OPTION_NAME = 'ainl_settings';
    
    /**
     * 생성자 - 설정 초기화
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * WordPress Settings API 등록
     */
    public function register_settings() {
        // 설정 그룹 등록
        register_setting(
            'ainl_settings_group',
            self::OPTION_NAME,
            array($this, 'sanitize_settings')
        );
        
        // 일반 설정 섹션
        add_settings_section(
            'ainl_general_section',
            '일반 설정',
            array($this, 'general_section_callback'),
            'ainl_settings'
        );
        
        // AI 설정 섹션
        add_settings_section(
            'ainl_ai_section',
            'AI 설정',
            array($this, 'ai_section_callback'),
            'ainl_settings'
        );
        
        // 이메일 설정 섹션
        add_settings_section(
            'ainl_email_section',
            '이메일 설정',
            array($this, 'email_section_callback'),
            'ainl_settings'
        );
        
        // 콘텐츠 설정 섹션
        add_settings_section(
            'ainl_content_section',
            '콘텐츠 설정',
            array($this, 'content_section_callback'),
            'ainl_settings'
        );
        
        // 일반 설정 필드들
        $this->register_general_fields();
        
        // AI 설정 필드들
        $this->register_ai_fields();
        
        // 이메일 설정 필드들
        $this->register_email_fields();
        
        // 콘텐츠 설정 필드들
        $this->register_content_fields();
    }
    
    /**
     * 일반 설정 필드 등록
     */
    private function register_general_fields() {
        // 플러그인 이름
        add_settings_field(
            'plugin_name',
            '플러그인 이름',
            array($this, 'text_field_callback'),
            'ainl_settings',
            'ainl_general_section',
            array(
                'field' => 'general.plugin_name',
                'description' => '뉴스레터에 표시될 플러그인 이름'
            )
        );
        
        // 발신자 이름
        add_settings_field(
            'sender_name',
            '발신자 이름',
            array($this, 'text_field_callback'),
            'ainl_settings',
            'ainl_general_section',
            array(
                'field' => 'general.sender_name',
                'description' => '이메일 발신자로 표시될 이름'
            )
        );
        
        // 발신자 이메일
        add_settings_field(
            'sender_email',
            '발신자 이메일',
            array($this, 'email_field_callback'),
            'ainl_settings',
            'ainl_general_section',
            array(
                'field' => 'general.sender_email',
                'description' => '이메일 발신자 주소'
            )
        );
        
        // 답장 이메일
        add_settings_field(
            'reply_to_email',
            '답장 이메일',
            array($this, 'email_field_callback'),
            'ainl_settings',
            'ainl_general_section',
            array(
                'field' => 'general.reply_to_email',
                'description' => '답장을 받을 이메일 주소'
            )
        );
    }
    
    /**
     * AI 설정 필드 등록
     */
    private function register_ai_fields() {
        // AI 제공업체
        add_settings_field(
            'ai_provider',
            'AI 제공업체',
            array($this, 'select_field_callback'),
            'ainl_settings',
            'ainl_ai_section',
            array(
                'field' => 'ai.provider',
                'options' => array(
                    'openai' => 'OpenAI',
                    'groq' => 'Groq (빠른 추론)',
                    'claude' => 'Anthropic Claude'
                ),
                'description' => '사용할 AI 서비스 제공업체'
            )
        );
        
        // OpenAI API 키
        add_settings_field(
            'openai_api_key',
            'OpenAI API 키',
            array($this, 'password_field_callback'),
            'ainl_settings',
            'ainl_ai_section',
            array(
                'field' => 'ai.openai_api_key',
                'description' => 'OpenAI API 키 (sk-로 시작)'
            )
        );
        
        // Groq API 키
        add_settings_field(
            'groq_api_key',
            'Groq API 키',
            array($this, 'password_field_callback'),
            'ainl_settings',
            'ainl_ai_section',
            array(
                'field' => 'ai.groq_api_key',
                'description' => 'Groq API 키 (gsk_로 시작, console.groq.com에서 발급)'
            )
        );
        
        // Claude API 키
        add_settings_field(
            'claude_api_key',
            'Claude API 키',
            array($this, 'password_field_callback'),
            'ainl_settings',
            'ainl_ai_section',
            array(
                'field' => 'ai.claude_api_key',
                'description' => 'Anthropic Claude API 키'
            )
        );
        
        // AI 모델
        add_settings_field(
            'ai_model',
            'AI 모델',
            array($this, 'select_field_callback'),
            'ainl_settings',
            'ainl_ai_section',
            array(
                'field' => 'ai.model',
                'options' => array(
                    // OpenAI 모델
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo (OpenAI)',
                    'gpt-4' => 'GPT-4 (OpenAI)',
                    'gpt-4-turbo' => 'GPT-4 Turbo (OpenAI)',
                    // Groq 모델
                    'llama-3.3-70b-versatile' => 'Llama 3.3 70B (Groq)',
                    'llama-3.1-70b-versatile' => 'Llama 3.1 70B (Groq)',
                    'llama-3.1-8b-instant' => 'Llama 3.1 8B Instant (Groq)',
                    'mixtral-8x7b-32768' => 'Mixtral 8x7B (Groq)',
                    'gemma2-9b-it' => 'Gemma 2 9B (Groq)',
                    // Claude 모델
                    'claude-3-haiku' => 'Claude 3 Haiku',
                    'claude-3-sonnet' => 'Claude 3 Sonnet'
                ),
                'description' => '사용할 AI 모델'
            )
        );
        
        // 최대 토큰 수
        add_settings_field(
            'max_tokens',
            '최대 토큰 수',
            array($this, 'number_field_callback'),
            'ainl_settings',
            'ainl_ai_section',
            array(
                'field' => 'ai.max_tokens',
                'min' => 100,
                'max' => 4000,
                'description' => 'AI 응답의 최대 토큰 수 (100-4000)'
            )
        );
        
        // Temperature
        add_settings_field(
            'temperature',
            'Temperature',
            array($this, 'number_field_callback'),
            'ainl_settings',
            'ainl_ai_section',
            array(
                'field' => 'ai.temperature',
                'min' => 0,
                'max' => 1,
                'step' => 0.1,
                'description' => 'AI 창의성 수준 (0.0-1.0)'
            )
        );
    }
    
    /**
     * 이메일 설정 필드 등록
     */
    private function register_email_fields() {
        // SMTP 호스트
        add_settings_field(
            'smtp_host',
            'SMTP 호스트',
            array($this, 'text_field_callback'),
            'ainl_settings',
            'ainl_email_section',
            array(
                'field' => 'email.smtp_host',
                'description' => 'SMTP 서버 주소 (예: smtp.gmail.com)'
            )
        );
        
        // SMTP 포트
        add_settings_field(
            'smtp_port',
            'SMTP 포트',
            array($this, 'number_field_callback'),
            'ainl_settings',
            'ainl_email_section',
            array(
                'field' => 'email.smtp_port',
                'min' => 1,
                'max' => 65535,
                'description' => 'SMTP 포트 번호 (일반적으로 587 또는 465)'
            )
        );
        
        // SMTP 사용자명
        add_settings_field(
            'smtp_username',
            'SMTP 사용자명',
            array($this, 'text_field_callback'),
            'ainl_settings',
            'ainl_email_section',
            array(
                'field' => 'email.smtp_username',
                'description' => 'SMTP 인증 사용자명'
            )
        );
        
        // SMTP 비밀번호
        add_settings_field(
            'smtp_password',
            'SMTP 비밀번호',
            array($this, 'password_field_callback'),
            'ainl_settings',
            'ainl_email_section',
            array(
                'field' => 'email.smtp_password',
                'description' => 'SMTP 인증 비밀번호'
            )
        );
        
        // SMTP 암호화
        add_settings_field(
            'smtp_encryption',
            'SMTP 암호화',
            array($this, 'select_field_callback'),
            'ainl_settings',
            'ainl_email_section',
            array(
                'field' => 'email.smtp_encryption',
                'options' => array(
                    'none' => '없음',
                    'tls' => 'TLS',
                    'ssl' => 'SSL'
                ),
                'description' => 'SMTP 연결 암호화 방식'
            )
        );
        
        // 배치 크기
        add_settings_field(
            'batch_size',
            '배치 크기',
            array($this, 'number_field_callback'),
            'ainl_settings',
            'ainl_email_section',
            array(
                'field' => 'email.batch_size',
                'min' => 1,
                'max' => 1000,
                'description' => '한 번에 발송할 이메일 수'
            )
        );
        
        // 발송 지연
        add_settings_field(
            'send_delay',
            '발송 지연 (초)',
            array($this, 'number_field_callback'),
            'ainl_settings',
            'ainl_email_section',
            array(
                'field' => 'email.send_delay',
                'min' => 0,
                'max' => 60,
                'description' => '배치 간 대기 시간 (초)'
            )
        );
    }
    
    /**
     * 콘텐츠 설정 필드 등록
     */
    private function register_content_fields() {
        // 게시물 타입
        add_settings_field(
            'post_types',
            '게시물 타입',
            array($this, 'checkbox_group_callback'),
            'ainl_settings',
            'ainl_content_section',
            array(
                'field' => 'content.post_types',
                'options' => $this->get_post_types(),
                'description' => '뉴스레터에 포함할 게시물 타입'
            )
        );
        
        // 날짜 범위
        add_settings_field(
            'date_range',
            '날짜 범위 (일)',
            array($this, 'number_field_callback'),
            'ainl_settings',
            'ainl_content_section',
            array(
                'field' => 'content.date_range',
                'min' => 1,
                'max' => 365,
                'description' => '최근 몇 일간의 게시물을 포함할지'
            )
        );
        
        // 최대 게시물 수
        add_settings_field(
            'max_posts',
            '최대 게시물 수',
            array($this, 'number_field_callback'),
            'ainl_settings',
            'ainl_content_section',
            array(
                'field' => 'content.max_posts',
                'min' => 1,
                'max' => 50,
                'description' => '뉴스레터에 포함할 최대 게시물 수'
            )
        );
        
        // 대표 이미지 포함
        add_settings_field(
            'include_featured_image',
            '대표 이미지 포함',
            array($this, 'checkbox_field_callback'),
            'ainl_settings',
            'ainl_content_section',
            array(
                'field' => 'content.include_featured_image',
                'description' => '뉴스레터에 게시물 대표 이미지 포함'
            )
        );
    }
    
    /**
     * 섹션 콜백 함수들
     */
    public function general_section_callback() {
        echo '<p>플러그인의 기본 설정을 구성합니다.</p>';
    }
    
    public function ai_section_callback() {
        echo '<p>AI 서비스 연동을 위한 설정입니다.</p>';
    }
    
    public function email_section_callback() {
        echo '<p>이메일 발송을 위한 SMTP 설정입니다.</p>';
    }
    
    public function content_section_callback() {
        echo '<p>뉴스레터에 포함될 콘텐츠 설정입니다.</p>';
    }
    
    /**
     * 필드 콜백 함수들
     */
    public function text_field_callback($args) {
        $value = $this->get_option_value($args['field']);
        $field_name = $this->get_field_name($args['field']);
        
        echo '<input type="text" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    public function email_field_callback($args) {
        $value = $this->get_option_value($args['field']);
        $field_name = $this->get_field_name($args['field']);
        
        echo '<input type="email" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    public function password_field_callback($args) {
        $value = $this->get_option_value($args['field']);
        $field_name = $this->get_field_name($args['field']);
        
        echo '<input type="password" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    public function number_field_callback($args) {
        $value = $this->get_option_value($args['field']);
        $field_name = $this->get_field_name($args['field']);
        
        $min = isset($args['min']) ? ' min="' . esc_attr($args['min']) . '"' : '';
        $max = isset($args['max']) ? ' max="' . esc_attr($args['max']) . '"' : '';
        $step = isset($args['step']) ? ' step="' . esc_attr($args['step']) . '"' : '';
        
        echo '<input type="number" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="small-text"' . $min . $max . $step . ' />';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    public function select_field_callback($args) {
        $value = $this->get_option_value($args['field']);
        $field_name = $this->get_field_name($args['field']);
        
        echo '<select name="' . esc_attr($field_name) . '">';
        foreach ($args['options'] as $option_value => $option_label) {
            $selected = selected($value, $option_value, false);
            echo '<option value="' . esc_attr($option_value) . '"' . $selected . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    public function checkbox_field_callback($args) {
        $value = $this->get_option_value($args['field']);
        $field_name = $this->get_field_name($args['field']);
        
        echo '<input type="checkbox" name="' . esc_attr($field_name) . '" value="1"' . checked($value, 1, false) . ' />';
        if (isset($args['description'])) {
            echo '<label for="' . esc_attr($field_name) . '"> ' . esc_html($args['description']) . '</label>';
        }
    }
    
    public function checkbox_group_callback($args) {
        $values = $this->get_option_value($args['field']);
        $field_name = $this->get_field_name($args['field']);
        
        if (!is_array($values)) {
            $values = array();
        }
        
        foreach ($args['options'] as $option_value => $option_label) {
            $checked = in_array($option_value, $values) ? 'checked="checked"' : '';
            echo '<label><input type="checkbox" name="' . esc_attr($field_name) . '[]" value="' . esc_attr($option_value) . '" ' . $checked . ' /> ' . esc_html($option_label) . '</label><br>';
        }
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * 설정값 검증 및 정리
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // 일반 설정 검증
        if (isset($input['general'])) {
            $sanitized['general'] = array(
                'plugin_name' => AINL_Security::sanitize_input($input['general']['plugin_name'], 'text'),
                'sender_name' => AINL_Security::sanitize_input($input['general']['sender_name'], 'text'),
                'sender_email' => AINL_Security::sanitize_input($input['general']['sender_email'], 'email'),
                'reply_to_email' => AINL_Security::sanitize_input($input['general']['reply_to_email'], 'email')
            );
        }
        
        // AI 설정 검증
        if (isset($input['ai'])) {
            $sanitized['ai'] = array(
                'provider' => AINL_Security::sanitize_input($input['ai']['provider'], 'text'),
                'openai_api_key' => $this->sanitize_api_key($input['ai']['openai_api_key']),
                'claude_api_key' => $this->sanitize_api_key($input['ai']['claude_api_key']),
                'model' => AINL_Security::sanitize_input($input['ai']['model'], 'text'),
                'max_tokens' => AINL_Security::sanitize_input($input['ai']['max_tokens'], 'int'),
                'temperature' => AINL_Security::sanitize_input($input['ai']['temperature'], 'float')
            );
            
            // API 키 검증
            if ($sanitized['ai']['provider'] === 'openai' && !empty($sanitized['ai']['openai_api_key'])) {
                if (!$this->validate_openai_api_key($sanitized['ai']['openai_api_key'])) {
                    add_settings_error('ainl_settings', 'invalid_openai_key', 'OpenAI API 키가 올바르지 않습니다.');
                }
            }
        }
        
        // 이메일 설정 검증
        if (isset($input['email'])) {
            $sanitized['email'] = array(
                'smtp_host' => AINL_Security::sanitize_input($input['email']['smtp_host'], 'text'),
                'smtp_port' => AINL_Security::sanitize_input($input['email']['smtp_port'], 'int'),
                'smtp_username' => AINL_Security::sanitize_input($input['email']['smtp_username'], 'text'),
                'smtp_password' => $this->sanitize_password($input['email']['smtp_password']),
                'smtp_encryption' => AINL_Security::sanitize_input($input['email']['smtp_encryption'], 'text'),
                'batch_size' => AINL_Security::sanitize_input($input['email']['batch_size'], 'int'),
                'send_delay' => AINL_Security::sanitize_input($input['email']['send_delay'], 'int')
            );
        }
        
        // 콘텐츠 설정 검증
        if (isset($input['content'])) {
            $sanitized['content'] = array(
                'post_types' => AINL_Security::sanitize_input($input['content']['post_types'], 'array'),
                'date_range' => AINL_Security::sanitize_input($input['content']['date_range'], 'int'),
                'max_posts' => AINL_Security::sanitize_input($input['content']['max_posts'], 'int'),
                'include_featured_image' => !empty($input['content']['include_featured_image'])
            );
        }
        
        return $sanitized;
    }
    
    /**
     * API 키 정리 및 암호화
     */
    private function sanitize_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        // 입력값 정리
        $clean_key = AINL_Security::sanitize_input($api_key, 'text');
        
        // 암호화하여 저장
        return AINL_Security::encrypt_api_key($clean_key);
    }
    
    /**
     * 비밀번호 정리 및 암호화
     */
    private function sanitize_password($password) {
        if (empty($password)) {
            return '';
        }
        
        // 입력값 정리
        $clean_password = AINL_Security::sanitize_input($password, 'text');
        
        // 간단한 암호화 (실제 환경에서는 더 강력한 암호화 사용)
        return base64_encode($clean_password);
    }
    
    /**
     * 헬퍼 메서드들
     */
    private function get_option_value($field) {
        $settings = get_option(self::OPTION_NAME, array());
        $keys = explode('.', $field);
        $value = $settings;
        
        foreach ($keys as $key) {
            if (isset($value[$key])) {
                $value = $value[$key];
            } else {
                return $this->get_default_value($field);
            }
        }
        
        return $value;
    }
    
    private function get_field_name($field) {
        $keys = explode('.', $field);
        $name = self::OPTION_NAME;
        
        foreach ($keys as $key) {
            $name .= '[' . $key . ']';
        }
        
        return $name;
    }
    
    private function get_default_value($field) {
        $defaults = array(
            'general.plugin_name' => 'AI Newsletter Generator Pro',
            'general.sender_name' => get_bloginfo('name'),
            'general.sender_email' => get_option('admin_email'),
            'general.reply_to_email' => get_option('admin_email'),
            'ai.provider' => 'openai',
            'ai.model' => 'gpt-3.5-turbo',
            'ai.max_tokens' => 1000,
            'ai.temperature' => 0.7,
            'email.smtp_port' => 587,
            'email.smtp_encryption' => 'tls',
            'email.batch_size' => 50,
            'email.send_delay' => 1,
            'content.post_types' => array('post'),
            'content.date_range' => 7,
            'content.max_posts' => 10,
            'content.include_featured_image' => true
        );
        
        return isset($defaults[$field]) ? $defaults[$field] : '';
    }
    
    private function get_post_types() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $options = array();
        
        foreach ($post_types as $post_type) {
            $options[$post_type->name] = $post_type->label;
        }
        
        return $options;
    }
    
    private function validate_openai_api_key($api_key) {
        // 기본적인 형식 검증 (sk-로 시작하고 적절한 길이)
        if (!preg_match('/^sk-[a-zA-Z0-9]{48}$/', $api_key)) {
            return false;
        }
        
        // 실제 API 호출로 검증 (선택사항)
        // 여기서는 형식만 검증
        return true;
    }
    
    /**
     * 설정값 가져오기 (정적 메서드)
     */
    public static function get_setting($key, $default = null) {
        $settings = get_option(self::OPTION_NAME, array());
        $keys = explode('.', $key);
        $value = $settings;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * 설정값 저장하기 (정적 메서드)
     */
    public static function update_setting($key, $value) {
        $settings = get_option(self::OPTION_NAME, array());
        $keys = explode('.', $key);
        $current = &$settings;
        
        for ($i = 0; $i < count($keys) - 1; $i++) {
            if (!isset($current[$keys[$i]])) {
                $current[$keys[$i]] = array();
            }
            $current = &$current[$keys[$i]];
        }
        
        $current[$keys[count($keys) - 1]] = $value;
        
        return update_option(self::OPTION_NAME, $settings);
    }
} 