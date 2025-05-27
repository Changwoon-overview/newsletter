<?php
/**
 * 이메일 템플릿 관리 클래스
 * 뉴스레터 이메일 템플릿의 생성, 관리, 렌더링을 담당합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Template_Manager {
    
    /**
     * 사용 가능한 템플릿 변수들
     */
    private $template_variables = [
        '{{site_name}}' => 'get_bloginfo("name")',
        '{{site_url}}' => 'home_url()',
        '{{newsletter_title}}' => 'newsletter_title',
        '{{newsletter_date}}' => 'current_date',
        '{{posts_content}}' => 'posts_content',
        '{{unsubscribe_url}}' => 'unsubscribe_url',
        '{{footer_text}}' => 'footer_text'
    ];
    
    /**
     * 생성자
     */
    public function __construct() {
        // 필요한 초기화 작업
    }
    
    /**
     * 기본 템플릿들을 반환
     * 3가지 기본 템플릿을 제공합니다.
     */
    public function get_default_templates() {
        return [
            'modern' => [
                'name' => '모던 템플릿',
                'description' => '깔끔하고 현대적인 디자인의 템플릿',
                'html' => $this->get_modern_template(),
                'preview_image' => AINL_PLUGIN_URL . 'assets/images/template-modern.png'
            ],
            'classic' => [
                'name' => '클래식 템플릿',
                'description' => '전통적이고 안정적인 디자인의 템플릿',
                'html' => $this->get_classic_template(),
                'preview_image' => AINL_PLUGIN_URL . 'assets/images/template-classic.png'
            ],
            'minimal' => [
                'name' => '미니멀 템플릿',
                'description' => '단순하고 깔끔한 디자인의 템플릿',
                'html' => $this->get_minimal_template(),
                'preview_image' => AINL_PLUGIN_URL . 'assets/images/template-minimal.png'
            ]
        ];
    }
    
    /**
     * 모던 템플릿 HTML 반환
     */
    private function get_modern_template() {
        return '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{newsletter_title}}</title>
    <style>
        body { margin: 0; padding: 0; font-family: "Helvetica Neue", Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 300; }
        .header .date { margin-top: 10px; opacity: 0.9; font-size: 14px; }
        .content { padding: 30px; }
        .post-item { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 25px; }
        .post-item:last-child { border-bottom: none; }
        .post-title { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 10px; }
        .post-title a { color: #667eea; text-decoration: none; }
        .post-excerpt { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .read-more { display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .footer { background-color: #333; color: white; padding: 30px; text-align: center; font-size: 14px; }
        .footer a { color: #667eea; }
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; }
            .header, .content, .footer { padding: 20px !important; }
            .header h1 { font-size: 24px !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{newsletter_title}}</h1>
            <div class="date">{{newsletter_date}}</div>
        </div>
        <div class="content">
            {{posts_content}}
        </div>
        <div class="footer">
            <p>{{footer_text}}</p>
            <p><a href="{{unsubscribe_url}}">수신거부</a> | <a href="{{site_url}}">{{site_name}}</a></p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * 클래식 템플릿 HTML 반환
     */
    private function get_classic_template() {
        return '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{newsletter_title}}</title>
    <style>
        body { margin: 0; padding: 0; font-family: Georgia, serif; background-color: #f9f9f9; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #ddd; }
        .header { background-color: #2c3e50; color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 26px; font-weight: normal; }
        .header .date { margin-top: 8px; font-size: 14px; opacity: 0.8; }
        .content { padding: 30px; }
        .post-item { margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #ecf0f1; }
        .post-item:last-child { border-bottom: none; }
        .post-title { font-size: 18px; font-weight: bold; color: #2c3e50; margin-bottom: 8px; }
        .post-title a { color: #2c3e50; text-decoration: none; }
        .post-excerpt { color: #555; line-height: 1.7; margin-bottom: 12px; }
        .read-more { color: #3498db; text-decoration: none; font-weight: bold; }
        .footer { background-color: #ecf0f1; padding: 25px; text-align: center; font-size: 13px; color: #7f8c8d; }
        .footer a { color: #3498db; }
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; border: none; }
            .header, .content, .footer { padding: 20px !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{newsletter_title}}</h1>
            <div class="date">{{newsletter_date}}</div>
        </div>
        <div class="content">
            {{posts_content}}
        </div>
        <div class="footer">
            <p>{{footer_text}}</p>
            <p><a href="{{unsubscribe_url}}">수신거부</a> | <a href="{{site_url}}">{{site_name}} 방문하기</a></p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * 미니멀 템플릿 HTML 반환
     */
    private function get_minimal_template() {
        return '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{newsletter_title}}</title>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #ffffff; }
        .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 1px solid #eee; padding-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 400; color: #333; }
        .header .date { margin-top: 10px; font-size: 14px; color: #888; }
        .content { }
        .post-item { margin-bottom: 35px; }
        .post-title { font-size: 18px; font-weight: 500; color: #333; margin-bottom: 8px; line-height: 1.3; }
        .post-title a { color: #333; text-decoration: none; }
        .post-excerpt { color: #666; line-height: 1.6; margin-bottom: 10px; }
        .read-more { color: #007cba; text-decoration: none; font-size: 14px; }
        .footer { margin-top: 50px; padding-top: 30px; border-top: 1px solid #eee; text-align: center; font-size: 12px; color: #999; }
        .footer a { color: #007cba; }
        @media only screen and (max-width: 600px) {
            .container { padding: 20px 15px !important; }
            .header { margin-bottom: 30px !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{newsletter_title}}</h1>
            <div class="date">{{newsletter_date}}</div>
        </div>
        <div class="content">
            {{posts_content}}
        </div>
        <div class="footer">
            <p>{{footer_text}}</p>
            <p><a href="{{unsubscribe_url}}">수신거부</a> | <a href="{{site_url}}">{{site_name}}</a></p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * 템플릿에 데이터를 적용하여 최종 HTML 생성
     */
    public function render_template($template_html, $data) {
        $html = $template_html;
        
        // 기본 사이트 정보 치환
        $html = str_replace('{{site_name}}', get_bloginfo('name'), $html);
        $html = str_replace('{{site_url}}', home_url(), $html);
        $html = str_replace('{{newsletter_date}}', date('Y년 m월 d일'), $html);
        
        // 전달받은 데이터로 치환
        foreach ($data as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }
        
        // 게시물 콘텐츠 생성
        if (isset($data['posts']) && is_array($data['posts'])) {
            $posts_html = $this->generate_posts_html($data['posts']);
            $html = str_replace('{{posts_content}}', $posts_html, $html);
        }
        
        // CSS 인라인 처리
        $html = $this->inline_css($html);
        
        return $html;
    }
    
    /**
     * 게시물 배열을 HTML로 변환
     */
    private function generate_posts_html($posts) {
        $html = '';
        
        foreach ($posts as $post) {
            $html .= '<div class="post-item">';
            $html .= '<h2 class="post-title"><a href="' . esc_url($post['url']) . '">' . esc_html($post['title']) . '</a></h2>';
            $html .= '<div class="post-excerpt">' . wp_kses_post($post['excerpt']) . '</div>';
            $html .= '<a href="' . esc_url($post['url']) . '" class="read-more">자세히 보기</a>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * CSS를 인라인으로 변환 (기본적인 처리)
     * 실제 프로덕션에서는 더 정교한 CSS 인라인 라이브러리 사용 권장
     */
    private function inline_css($html) {
        // 기본적인 CSS 인라인 처리
        // 여기서는 간단한 예시만 구현
        // 실제로는 Emogrifier 같은 라이브러리 사용 권장
        
        return $html;
    }
    
    /**
     * 템플릿 미리보기 HTML 생성
     */
    public function generate_preview($template_key, $sample_data = null) {
        $templates = $this->get_default_templates();
        
        if (!isset($templates[$template_key])) {
            return false;
        }
        
        // 샘플 데이터가 없으면 기본 샘플 데이터 사용
        if (!$sample_data) {
            $sample_data = $this->get_sample_data();
        }
        
        return $this->render_template($templates[$template_key]['html'], $sample_data);
    }
    
    /**
     * 미리보기용 샘플 데이터 생성
     */
    private function get_sample_data() {
        return [
            'newsletter_title' => '주간 뉴스레터',
            'footer_text' => '이 이메일은 ' . get_bloginfo('name') . '에서 발송되었습니다.',
            'unsubscribe_url' => home_url('/unsubscribe'),
            'posts' => [
                [
                    'title' => '첫 번째 게시물 제목',
                    'excerpt' => '이것은 첫 번째 게시물의 요약입니다. 흥미로운 내용이 포함되어 있습니다.',
                    'url' => home_url('/sample-post-1')
                ],
                [
                    'title' => '두 번째 게시물 제목',
                    'excerpt' => '이것은 두 번째 게시물의 요약입니다. 유용한 정보를 제공합니다.',
                    'url' => home_url('/sample-post-2')
                ],
                [
                    'title' => '세 번째 게시물 제목',
                    'excerpt' => '이것은 세 번째 게시물의 요약입니다. 최신 업데이트 내용입니다.',
                    'url' => home_url('/sample-post-3')
                ]
            ]
        ];
    }
    
    /**
     * 사용 가능한 템플릿 변수 목록 반환
     */
    public function get_available_variables() {
        return array_keys($this->template_variables);
    }
    
    /**
     * 템플릿 유효성 검사
     */
    public function validate_template($html) {
        $errors = [];
        
        // 필수 변수 체크
        $required_vars = ['{{newsletter_title}}', '{{posts_content}}', '{{unsubscribe_url}}'];
        foreach ($required_vars as $var) {
            if (strpos($html, $var) === false) {
                $errors[] = "필수 변수 {$var}가 누락되었습니다.";
            }
        }
        
        // 기본 HTML 구조 체크
        if (strpos($html, '<html') === false) {
            $errors[] = 'HTML 문서 구조가 올바르지 않습니다.';
        }
        
        return empty($errors) ? true : $errors;
    }
} 