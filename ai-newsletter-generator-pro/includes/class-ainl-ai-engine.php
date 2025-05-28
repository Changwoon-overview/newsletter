<?php
/**
 * AI 엔진 클래스
 * OpenAI API를 사용하여 뉴스레터 콘텐츠를 생성합니다.
 *
 * @package AI_Newsletter_Generator_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI 엔진 클래스
 */
class AINL_AI_Engine {
    
    /**
     * 클래스 인스턴스
     */
    private static $instance = null;
    
    /**
     * OpenAI API 키
     */
    private $api_key;
    
    /**
     * OpenAI API 엔드포인트
     */
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * 기본 모델
     */
    private $default_model = 'gpt-3.5-turbo';
    
    /**
     * 생성자
     */
    public function __construct() {
        $settings = get_option('ainl_settings', array());
        $this->api_key = $settings['openai_api_key'] ?? '';
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
     * API 키 설정 확인
     * 
     * @return bool API 키가 설정되어 있는지 여부
     */
    public function is_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * 뉴스레터 콘텐츠 생성
     * 
     * @param array $posts 게시물 배열
     * @param array $options 생성 옵션
     * @return string|WP_Error 생성된 콘텐츠 또는 에러
     */
    public function generate_newsletter_content($posts, $options = array()) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', 'OpenAI API 키가 설정되지 않았습니다.');
        }
        
        if (empty($posts)) {
            return new WP_Error('no_posts', '콘텐츠를 생성할 게시물이 없습니다.');
        }
        
        // 기본 옵션 설정
        $defaults = array(
            'style' => 'professional', // professional, casual, friendly
            'length' => 'medium', // short, medium, long
            'include_summary' => true,
            'include_excerpts' => true,
            'max_posts' => 10,
            'language' => 'korean'
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // 게시물 데이터 준비
        $posts_data = $this->prepare_posts_data($posts, $options);
        
        // AI 프롬프트 생성
        $prompt = $this->build_content_generation_prompt($posts_data, $options);
        
        // OpenAI API 호출
        $response = $this->call_openai_api($prompt, $options);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // 응답 처리 및 HTML 변환
        $content = $this->process_ai_response($response, $options);
        
        return $content;
    }
    
    /**
     * 게시물 요약 생성
     * 
     * @param WP_Post $post 게시물 객체
     * @param int $max_words 최대 단어 수
     * @return string|WP_Error 요약 또는 에러
     */
    public function generate_post_summary($post, $max_words = 50) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', 'OpenAI API 키가 설정되지 않았습니다.');
        }
        
        $content = wp_strip_all_tags($post->post_content);
        $content = wp_trim_words($content, 500); // API 호출 비용 절약을 위해 제한
        
        $prompt = "다음 블로그 게시물을 {$max_words}단어 이내로 요약해주세요. 핵심 내용을 간결하고 명확하게 전달해주세요.\n\n";
        $prompt .= "제목: " . $post->post_title . "\n";
        $prompt .= "내용: " . $content . "\n\n";
        $prompt .= "요약:";
        
        $response = $this->call_openai_api($prompt, array(
            'max_tokens' => 150,
            'temperature' => 0.7
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return trim($response);
    }
    
    /**
     * 뉴스레터 제목 생성
     * 
     * @param array $posts 게시물 배열
     * @param array $options 옵션
     * @return string|WP_Error 제목 또는 에러
     */
    public function generate_newsletter_title($posts, $options = array()) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', 'OpenAI API 키가 설정되지 않았습니다.');
        }
        
        $defaults = array(
            'style' => 'engaging',
            'include_date' => true,
            'max_length' => 60
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // 게시물 제목들 수집
        $post_titles = array();
        foreach ($posts as $post) {
            $post_titles[] = $post->post_title;
        }
        
        $titles_text = implode("\n- ", $post_titles);
        
        $prompt = "다음 블로그 게시물들을 포함하는 뉴스레터의 매력적인 제목을 3개 생성해주세요. ";
        $prompt .= "제목은 {$options['max_length']}자 이내로 작성하고, 독자의 관심을 끌 수 있도록 해주세요.\n\n";
        $prompt .= "게시물 제목들:\n- " . $titles_text . "\n\n";
        $prompt .= "뉴스레터 제목 후보 (번호와 함께):\n";
        
        $response = $this->call_openai_api($prompt, array(
            'max_tokens' => 200,
            'temperature' => 0.8
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // 첫 번째 제목 추출
        $lines = explode("\n", trim($response));
        foreach ($lines as $line) {
            if (preg_match('/^1\.\s*(.+)/', $line, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return trim($lines[0]); // 첫 번째 줄 반환
    }
    
    /**
     * 게시물 데이터 준비
     * 
     * @param array $posts 게시물 배열
     * @param array $options 옵션
     * @return array 준비된 데이터
     */
    private function prepare_posts_data($posts, $options) {
        $posts_data = array();
        $count = 0;
        
        foreach ($posts as $post) {
            if ($count >= $options['max_posts']) {
                break;
            }
            
            $post_data = array(
                'title' => $post->post_title,
                'excerpt' => wp_trim_words(wp_strip_all_tags($post->post_content), 50),
                'date' => get_the_date('Y-m-d', $post->ID),
                'categories' => wp_get_post_categories($post->ID, array('fields' => 'names')),
                'permalink' => get_permalink($post->ID)
            );
            
            // 전체 내용 포함 (옵션에 따라)
            if ($options['include_excerpts']) {
                $post_data['content'] = wp_trim_words(wp_strip_all_tags($post->post_content), 100);
            }
            
            $posts_data[] = $post_data;
            $count++;
        }
        
        return $posts_data;
    }
    
    /**
     * 콘텐츠 생성 프롬프트 구성
     * 
     * @param array $posts_data 게시물 데이터
     * @param array $options 옵션
     * @return string 프롬프트
     */
    private function build_content_generation_prompt($posts_data, $options) {
        $prompt = "당신은 전문적인 뉴스레터 작성자입니다. 다음 블로그 게시물들을 바탕으로 매력적이고 읽기 쉬운 뉴스레터 콘텐츠를 생성해주세요.\n\n";
        
        // 스타일 지침
        switch ($options['style']) {
            case 'professional':
                $prompt .= "스타일: 전문적이고 신뢰할 수 있는 톤으로 작성해주세요.\n";
                break;
            case 'casual':
                $prompt .= "스타일: 친근하고 편안한 톤으로 작성해주세요.\n";
                break;
            case 'friendly':
                $prompt .= "스타일: 따뜻하고 개인적인 톤으로 작성해주세요.\n";
                break;
        }
        
        // 길이 지침
        switch ($options['length']) {
            case 'short':
                $prompt .= "길이: 간결하고 핵심적인 내용으로 작성해주세요.\n";
                break;
            case 'medium':
                $prompt .= "길이: 적당한 길이로 상세한 설명을 포함해주세요.\n";
                break;
            case 'long':
                $prompt .= "길이: 자세하고 포괄적인 내용으로 작성해주세요.\n";
                break;
        }
        
        $prompt .= "언어: 한국어로 작성해주세요.\n\n";
        
        // 요구사항
        $prompt .= "요구사항:\n";
        $prompt .= "1. 매력적인 인사말로 시작해주세요\n";
        $prompt .= "2. 각 게시물을 소개하고 핵심 내용을 요약해주세요\n";
        $prompt .= "3. 독자가 전체 글을 읽고 싶어하도록 흥미를 유발해주세요\n";
        $prompt .= "4. 각 게시물에 대해 '자세히 보기' 링크를 포함해주세요\n";
        $prompt .= "5. 따뜻한 마무리 인사로 끝내주세요\n";
        $prompt .= "6. HTML 형식으로 작성해주세요 (제목은 h2, 단락은 p 태그 사용)\n\n";
        
        // 게시물 데이터
        $prompt .= "게시물 정보:\n";
        foreach ($posts_data as $index => $post) {
            $prompt .= "\n" . ($index + 1) . ". 제목: " . $post['title'] . "\n";
            $prompt .= "   날짜: " . $post['date'] . "\n";
            $prompt .= "   카테고리: " . implode(', ', $post['categories']) . "\n";
            $prompt .= "   링크: " . $post['permalink'] . "\n";
            if (!empty($post['content'])) {
                $prompt .= "   내용 요약: " . $post['content'] . "\n";
            }
        }
        
        $prompt .= "\n뉴스레터 콘텐츠를 HTML 형식으로 생성해주세요:";
        
        return $prompt;
    }
    
    /**
     * OpenAI API 호출
     * 
     * @param string $prompt 프롬프트
     * @param array $options 옵션
     * @return string|WP_Error 응답 또는 에러
     */
    private function call_openai_api($prompt, $options = array()) {
        $defaults = array(
            'model' => $this->default_model,
            'max_tokens' => 2000,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $body = array(
            'model' => $options['model'],
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'top_p' => $options['top_p'],
            'frequency_penalty' => $options['frequency_penalty'],
            'presence_penalty' => $options['presence_penalty']
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($body),
            'timeout' => 60
        );
        
        $response = wp_remote_request($this->api_endpoint, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'OpenAI API 호출 중 오류가 발생했습니다: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['error']['message'] ?? 'Unknown error';
            return new WP_Error('api_error', 'OpenAI API 오류 (' . $response_code . '): ' . $error_message);
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', 'OpenAI API 응답이 올바르지 않습니다.');
        }
        
        return $data['choices'][0]['message']['content'];
    }
    
    /**
     * AI 응답 처리
     * 
     * @param string $response AI 응답
     * @param array $options 옵션
     * @return string 처리된 콘텐츠
     */
    private function process_ai_response($response, $options) {
        // HTML 태그 정리
        $content = trim($response);
        
        // 기본 HTML 구조 확인 및 보완
        if (strpos($content, '<div') === false) {
            $content = '<div class="newsletter-content">' . $content . '</div>';
        }
        
        // 링크 처리 - 실제 링크로 변환
        $content = preg_replace_callback(
            '/\[자세히 보기\]\(([^)]+)\)/',
            function($matches) {
                return '<a href="' . esc_url($matches[1]) . '" class="read-more-link">자세히 보기 →</a>';
            },
            $content
        );
        
        // 기본 CSS 클래스 추가
        $content = str_replace('<h2>', '<h2 class="newsletter-post-title">', $content);
        $content = str_replace('<p>', '<p class="newsletter-paragraph">', $content);
        
        return $content;
    }
    
    /**
     * API 연결 테스트
     * 
     * @return array 테스트 결과
     */
    public function test_api_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'message' => 'OpenAI API 키가 설정되지 않았습니다.'
            );
        }
        
        $test_prompt = "Hello, this is a test message. Please respond with 'API connection successful'.";
        
        $response = $this->call_openai_api($test_prompt, array(
            'max_tokens' => 50,
            'temperature' => 0
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'message' => 'OpenAI API 연결이 성공적으로 테스트되었습니다.',
            'response' => $response
        );
    }
    
    /**
     * 사용량 통계 조회
     * 
     * @return array|WP_Error 사용량 정보 또는 에러
     */
    public function get_usage_stats() {
        // 이 기능은 OpenAI API의 사용량 엔드포인트를 사용하여 구현할 수 있습니다
        // 현재는 기본 구조만 제공
        return array(
            'total_requests' => get_option('ainl_ai_total_requests', 0),
            'total_tokens' => get_option('ainl_ai_total_tokens', 0),
            'last_request' => get_option('ainl_ai_last_request', '')
        );
    }
    
    /**
     * 사용량 업데이트
     * 
     * @param int $tokens 사용된 토큰 수
     */
    public function update_usage_stats($tokens) {
        $total_requests = get_option('ainl_ai_total_requests', 0);
        $total_tokens = get_option('ainl_ai_total_tokens', 0);
        
        update_option('ainl_ai_total_requests', $total_requests + 1);
        update_option('ainl_ai_total_tokens', $total_tokens + $tokens);
        update_option('ainl_ai_last_request', current_time('mysql'));
    }
} 