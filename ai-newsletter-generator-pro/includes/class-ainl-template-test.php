<?php
/**
 * 이메일 템플릿 시스템 테스트 클래스
 * 템플릿 시스템의 모든 기능을 검증합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Template_Test {
    
    /**
     * 모든 템플릿 테스트 실행
     */
    public static function run_all_tests() {
        $results = [];
        
        $results['template_creation'] = self::test_template_creation();
        $results['template_rendering'] = self::test_template_rendering();
        $results['variable_substitution'] = self::test_variable_substitution();
        $results['posts_html_generation'] = self::test_posts_html_generation();
        $results['template_validation'] = self::test_template_validation();
        $results['preview_generation'] = self::test_preview_generation();
        $results['responsive_design'] = self::test_responsive_design();
        
        return $results;
    }
    
    /**
     * 템플릿 생성 테스트
     */
    private static function test_template_creation() {
        try {
            $template_manager = new AINL_Template_Manager();
            $templates = $template_manager->get_default_templates();
            
            // 3개의 기본 템플릿이 있는지 확인
            if (count($templates) !== 3) {
                return [
                    'status' => 'failed',
                    'message' => '기본 템플릿 개수가 올바르지 않습니다. 예상: 3개, 실제: ' . count($templates)
                ];
            }
            
            // 각 템플릿이 필요한 속성을 가지고 있는지 확인
            $required_keys = ['name', 'description', 'html', 'preview_image'];
            foreach ($templates as $key => $template) {
                foreach ($required_keys as $required_key) {
                    if (!isset($template[$required_key])) {
                        return [
                            'status' => 'failed',
                            'message' => "템플릿 '{$key}'에 필수 속성 '{$required_key}'가 누락되었습니다."
                        ];
                    }
                }
            }
            
            // HTML이 비어있지 않은지 확인
            foreach ($templates as $key => $template) {
                if (empty($template['html'])) {
                    return [
                        'status' => 'failed',
                        'message' => "템플릿 '{$key}'의 HTML이 비어있습니다."
                    ];
                }
            }
            
            return [
                'status' => 'passed',
                'message' => '모든 기본 템플릿이 올바르게 생성되었습니다.',
                'details' => [
                    'template_count' => count($templates),
                    'template_keys' => array_keys($templates)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'message' => '템플릿 생성 중 오류 발생: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 템플릿 렌더링 테스트
     */
    private static function test_template_rendering() {
        try {
            $template_manager = new AINL_Template_Manager();
            $templates = $template_manager->get_default_templates();
            
            $test_data = [
                'newsletter_title' => '테스트 뉴스레터',
                'footer_text' => '테스트 푸터 텍스트',
                'unsubscribe_url' => 'https://example.com/unsubscribe',
                'posts' => [
                    [
                        'title' => '테스트 게시물',
                        'excerpt' => '테스트 게시물 요약',
                        'url' => 'https://example.com/test-post'
                    ]
                ]
            ];
            
            foreach ($templates as $key => $template) {
                $rendered = $template_manager->render_template($template['html'], $test_data);
                
                if (empty($rendered)) {
                    return [
                        'status' => 'failed',
                        'message' => "템플릿 '{$key}' 렌더링 결과가 비어있습니다."
                    ];
                }
                
                // 기본 HTML 구조 확인
                if (strpos($rendered, '<html') === false || strpos($rendered, '</html>') === false) {
                    return [
                        'status' => 'failed',
                        'message' => "템플릿 '{$key}' 렌더링 결과가 올바른 HTML 구조를 가지지 않습니다."
                    ];
                }
            }
            
            return [
                'status' => 'passed',
                'message' => '모든 템플릿이 올바르게 렌더링되었습니다.',
                'details' => [
                    'tested_templates' => array_keys($templates)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'message' => '템플릿 렌더링 중 오류 발생: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 변수 치환 테스트
     */
    private static function test_variable_substitution() {
        try {
            $template_manager = new AINL_Template_Manager();
            
            $test_template = '<html><body><h1>{{newsletter_title}}</h1><p>{{footer_text}}</p></body></html>';
            $test_data = [
                'newsletter_title' => 'TEST_TITLE_123',
                'footer_text' => 'TEST_FOOTER_456'
            ];
            
            $rendered = $template_manager->render_template($test_template, $test_data);
            
            // 변수가 올바르게 치환되었는지 확인
            if (strpos($rendered, 'TEST_TITLE_123') === false) {
                return [
                    'status' => 'failed',
                    'message' => 'newsletter_title 변수가 올바르게 치환되지 않았습니다.'
                ];
            }
            
            if (strpos($rendered, 'TEST_FOOTER_456') === false) {
                return [
                    'status' => 'failed',
                    'message' => 'footer_text 변수가 올바르게 치환되지 않았습니다.'
                ];
            }
            
            // 치환되지 않은 변수가 남아있는지 확인
            if (strpos($rendered, '{{') !== false) {
                return [
                    'status' => 'failed',
                    'message' => '치환되지 않은 변수가 남아있습니다: ' . $rendered
                ];
            }
            
            return [
                'status' => 'passed',
                'message' => '모든 변수가 올바르게 치환되었습니다.',
                'details' => [
                    'rendered_html' => $rendered
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'message' => '변수 치환 테스트 중 오류 발생: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 게시물 HTML 생성 테스트
     */
    private static function test_posts_html_generation() {
        try {
            $template_manager = new AINL_Template_Manager();
            
            $test_posts = [
                [
                    'title' => '첫 번째 게시물',
                    'excerpt' => '첫 번째 게시물 요약',
                    'url' => 'https://example.com/post-1'
                ],
                [
                    'title' => '두 번째 게시물',
                    'excerpt' => '두 번째 게시물 요약',
                    'url' => 'https://example.com/post-2'
                ]
            ];
            
            $test_template = '<html><body>{{posts_content}}</body></html>';
            $test_data = ['posts' => $test_posts];
            
            $rendered = $template_manager->render_template($test_template, $test_data);
            
            // 게시물 제목이 포함되어 있는지 확인
            if (strpos($rendered, '첫 번째 게시물') === false) {
                return [
                    'status' => 'failed',
                    'message' => '첫 번째 게시물 제목이 렌더링되지 않았습니다.'
                ];
            }
            
            if (strpos($rendered, '두 번째 게시물') === false) {
                return [
                    'status' => 'failed',
                    'message' => '두 번째 게시물 제목이 렌더링되지 않았습니다.'
                ];
            }
            
            // 링크가 올바르게 생성되었는지 확인
            if (strpos($rendered, 'https://example.com/post-1') === false) {
                return [
                    'status' => 'failed',
                    'message' => '첫 번째 게시물 링크가 올바르게 생성되지 않았습니다.'
                ];
            }
            
            return [
                'status' => 'passed',
                'message' => '게시물 HTML이 올바르게 생성되었습니다.',
                'details' => [
                    'post_count' => count($test_posts),
                    'rendered_html' => $rendered
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'message' => '게시물 HTML 생성 테스트 중 오류 발생: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 템플릿 유효성 검사 테스트
     */
    private static function test_template_validation() {
        try {
            $template_manager = new AINL_Template_Manager();
            
            // 유효한 템플릿 테스트
            $valid_template = '<html><body><h1>{{newsletter_title}}</h1>{{posts_content}}<a href="{{unsubscribe_url}}">수신거부</a></body></html>';
            $validation_result = $template_manager->validate_template($valid_template);
            
            if ($validation_result !== true) {
                return [
                    'status' => 'failed',
                    'message' => '유효한 템플릿이 유효하지 않다고 판단되었습니다: ' . implode(', ', $validation_result)
                ];
            }
            
            // 무효한 템플릿 테스트 (필수 변수 누락)
            $invalid_template = '<html><body><h1>{{newsletter_title}}</h1></body></html>';
            $validation_result = $template_manager->validate_template($invalid_template);
            
            if ($validation_result === true) {
                return [
                    'status' => 'failed',
                    'message' => '무효한 템플릿이 유효하다고 판단되었습니다.'
                ];
            }
            
            return [
                'status' => 'passed',
                'message' => '템플릿 유효성 검사가 올바르게 작동합니다.',
                'details' => [
                    'valid_template_passed' => true,
                    'invalid_template_caught' => true
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'message' => '템플릿 유효성 검사 테스트 중 오류 발생: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 미리보기 생성 테스트
     */
    private static function test_preview_generation() {
        try {
            $template_manager = new AINL_Template_Manager();
            $templates = $template_manager->get_default_templates();
            
            foreach ($templates as $key => $template) {
                $preview = $template_manager->generate_preview($key);
                
                if ($preview === false) {
                    return [
                        'status' => 'failed',
                        'message' => "템플릿 '{$key}'의 미리보기 생성에 실패했습니다."
                    ];
                }
                
                if (empty($preview)) {
                    return [
                        'status' => 'failed',
                        'message' => "템플릿 '{$key}'의 미리보기가 비어있습니다."
                    ];
                }
                
                // 샘플 데이터가 포함되어 있는지 확인
                if (strpos($preview, '주간 뉴스레터') === false) {
                    return [
                        'status' => 'failed',
                        'message' => "템플릿 '{$key}'의 미리보기에 샘플 데이터가 포함되지 않았습니다."
                    ];
                }
            }
            
            return [
                'status' => 'passed',
                'message' => '모든 템플릿의 미리보기가 올바르게 생성되었습니다.',
                'details' => [
                    'tested_templates' => array_keys($templates)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'message' => '미리보기 생성 테스트 중 오류 발생: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 반응형 디자인 테스트
     */
    private static function test_responsive_design() {
        try {
            $template_manager = new AINL_Template_Manager();
            $templates = $template_manager->get_default_templates();
            
            foreach ($templates as $key => $template) {
                $html = $template['html'];
                
                // 뷰포트 메타 태그 확인
                if (strpos($html, 'viewport') === false) {
                    return [
                        'status' => 'failed',
                        'message' => "템플릿 '{$key}'에 뷰포트 메타 태그가 없습니다."
                    ];
                }
                
                // 미디어 쿼리 확인
                if (strpos($html, '@media') === false) {
                    return [
                        'status' => 'failed',
                        'message' => "템플릿 '{$key}'에 반응형 미디어 쿼리가 없습니다."
                    ];
                }
                
                // 최대 너비 설정 확인
                if (strpos($html, 'max-width') === false) {
                    return [
                        'status' => 'failed',
                        'message' => "템플릿 '{$key}'에 최대 너비 설정이 없습니다."
                    ];
                }
            }
            
            return [
                'status' => 'passed',
                'message' => '모든 템플릿이 반응형 디자인을 지원합니다.',
                'details' => [
                    'tested_templates' => array_keys($templates)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'message' => '반응형 디자인 테스트 중 오류 발생: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 테스트 결과 표시
     */
    public static function display_test_results($results) {
        echo '<div class="ainl-test-results">';
        echo '<h3>이메일 템플릿 시스템 테스트 결과</h3>';
        
        $total_tests = count($results);
        $passed_tests = 0;
        
        foreach ($results as $test_name => $result) {
            $status_class = $result['status'] === 'passed' ? 'success' : 'error';
            $status_text = $result['status'] === 'passed' ? '통과' : '실패';
            
            if ($result['status'] === 'passed') {
                $passed_tests++;
            }
            
            echo '<div class="notice notice-' . $status_class . '">';
            echo '<p><strong>' . self::get_test_display_name($test_name) . ':</strong> ' . $status_text . '</p>';
            echo '<p>' . esc_html($result['message']) . '</p>';
            
            if (isset($result['details'])) {
                echo '<details><summary>세부 정보</summary>';
                echo '<pre>' . esc_html(print_r($result['details'], true)) . '</pre>';
                echo '</details>';
            }
            echo '</div>';
        }
        
        echo '<div class="notice notice-info">';
        echo '<p><strong>전체 결과:</strong> ' . $passed_tests . '/' . $total_tests . ' 테스트 통과</p>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<style>
        .ainl-test-results { margin: 20px 0; }
        .ainl-test-results .notice { margin: 10px 0; padding: 10px; }
        .ainl-test-results details { margin-top: 10px; }
        .ainl-test-results pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        </style>';
    }
    
    /**
     * 테스트 이름을 한국어로 변환
     */
    private static function get_test_display_name($test_name) {
        $names = [
            'template_creation' => '템플릿 생성',
            'template_rendering' => '템플릿 렌더링',
            'variable_substitution' => '변수 치환',
            'posts_html_generation' => '게시물 HTML 생성',
            'template_validation' => '템플릿 유효성 검사',
            'preview_generation' => '미리보기 생성',
            'responsive_design' => '반응형 디자인'
        ];
        
        return isset($names[$test_name]) ? $names[$test_name] : $test_name;
    }
} 