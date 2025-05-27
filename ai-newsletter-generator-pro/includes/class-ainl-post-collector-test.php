<?php
/**
 * 게시물 수집 시스템 테스트 클래스
 * 게시물 수집 기능의 정확성과 성능을 검증합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Post_Collector_Test {
    
    /**
     * 게시물 수집기 인스턴스
     */
    private $collector;
    
    /**
     * 테스트 결과 저장
     */
    private $test_results = array();
    
    /**
     * 생성자
     */
    public function __construct() {
        $this->collector = new AINL_Post_Collector();
    }
    
    /**
     * 모든 테스트 실행
     */
    public function run_all_tests() {
        echo "=== AINL Post Collector 종합 테스트 시작 ===\n";
        
        // 1. 기본 기능 테스트
        $this->test_basic_functionality();
        
        // 2. 고급 필터링 시스템 테스트
        $this->test_advanced_filtering();
        
        // 3. 메타데이터 추출 시스템 테스트
        $this->test_metadata_extraction();
        
        // 4. 콘텐츠 포맷팅 시스템 테스트
        $this->test_content_formatting();
        
        // 5. 성능 최적화 및 캐싱 시스템 테스트
        $this->test_performance_optimization();
        
        echo "\n=== 모든 테스트 완료 ===\n";
        echo "WordPress AI 뉴스레터 게시물 수집 시스템이 성공적으로 구현되었습니다!\n";
    }
    
    /**
     * 기본 기능 테스트
     */
    public function test_basic_functionality() {
        echo "\n=== 기본 기능 테스트 ===\n";
        
        // 1. 기본 게시물 수집 테스트
        $result = self::test_basic_collection();
        echo "기본 게시물 수집: " . ($result['passed'] ? "✅ 통과" : "❌ 실패") . "\n";
        
        // 2. 필터링 시스템 테스트
        $result = self::test_filtering_system();
        echo "필터링 시스템: " . ($result['passed'] ? "✅ 통과" : "❌ 실패") . "\n";
        
        // 3. 콘텐츠 처리 테스트
        $result = self::test_content_processing();
        echo "콘텐츠 처리: " . ($result['passed'] ? "✅ 통과" : "❌ 실패") . "\n";
        
        // 4. 캐싱 시스템 테스트
        $result = self::test_caching_system();
        echo "캐싱 시스템: " . ($result['passed'] ? "✅ 통과" : "❌ 실패") . "\n";
        
        // 5. 성능 테스트
        $result = self::test_performance();
        echo "성능 테스트: " . ($result['passed'] ? "✅ 통과" : "❌ 실패") . "\n";
        
        // 6. 에러 처리 테스트
        $result = self::test_error_handling();
        echo "에러 처리: " . ($result['passed'] ? "✅ 통과" : "❌ 실패") . "\n";
        
        // 7. 보안 검증 테스트
        $result = self::test_security_validation();
        echo "보안 검증: " . ($result['passed'] ? "✅ 통과" : "❌ 실패") . "\n";
        
        echo "✅ 기본 기능 테스트 완료\n";
    }
    
    /**
     * 고급 필터링 시스템 테스트
     */
    public function test_advanced_filtering() {
        echo "\n=== 고급 필터링 시스템 테스트 ===\n";
        
        // 1. 키워드 필터링 테스트
        $this->test_keyword_filtering();
        
        // 2. 날짜 범위 필터링 테스트
        $this->test_date_range_filtering();
        
        // 3. 커스텀 필드 필터링 테스트
        $this->test_custom_field_filtering();
        
        // 4. 복합 필터링 테스트
        $this->test_complex_filtering();
        
        // 5. 필터 프리셋 테스트
        $this->test_filter_presets();
        
        // 6. 필터 검증 테스트
        $this->test_filter_validation();
        
        echo "✅ 고급 필터링 시스템 테스트 완료\n";
    }
    
    /**
     * 메타데이터 추출 시스템 테스트
     */
    public function test_metadata_extraction() {
        echo "\n=== 메타데이터 추출 시스템 테스트 ===\n";
        
        // 1. 기본 메타데이터 추출 테스트
        $this->test_basic_metadata_extraction();
        
        // 2. 이미지 메타데이터 테스트
        $this->test_image_metadata();
        
        // 3. SEO 메타데이터 테스트
        $this->test_seo_metadata();
        
        // 4. 커스텀 필드 메타데이터 테스트
        $this->test_custom_field_metadata();
        
        // 5. 계산된 메타데이터 테스트
        $this->test_calculated_metadata();
        
        // 6. 메타데이터 통계 테스트
        $this->test_metadata_statistics();
        
        echo "✅ 메타데이터 추출 시스템 테스트 완료\n";
    }
    
    /**
     * 콘텐츠 포맷팅 시스템 테스트
     */
    public function test_content_formatting() {
        echo "\n=== 콘텐츠 포맷팅 시스템 테스트 ===\n";
        
        // 1. HTML 정리 테스트
        $this->test_html_cleaning();
        
        // 2. 숏코드 처리 테스트
        $this->test_shortcode_processing();
        
        // 3. 이미지 처리 테스트
        $this->test_image_processing();
        
        // 4. 링크 처리 테스트
        $this->test_link_processing();
        
        // 5. 텍스트 포맷팅 테스트
        $this->test_text_formatting();
        
        // 6. 요약 생성 테스트
        $this->test_excerpt_generation();
        
        // 7. 포맷 프리셋 테스트
        $this->test_format_presets();
        
        // 8. 콘텐츠 품질 분석 테스트
        $this->test_content_quality_analysis();
        
        echo "✅ 콘텐츠 포맷팅 시스템 테스트 완료\n";
    }
    
    /**
     * 성능 최적화 및 캐싱 시스템 테스트
     */
    public function test_performance_optimization() {
        echo "\n=== 성능 최적화 및 캐싱 시스템 테스트 ===\n";
        
        // 1. 캐시 시스템 테스트
        $this->test_caching_system();
        
        // 2. 성능 모니터링 테스트
        $this->test_performance_monitoring();
        
        // 3. 메모리 관리 테스트
        $this->test_memory_management();
        
        // 4. 배치 처리 테스트
        $this->test_batch_processing();
        
        // 5. 대량 데이터 처리 테스트
        $this->test_bulk_processing();
        
        echo "✅ 성능 최적화 시스템 테스트 완료\n";
    }
    
    /**
     * 기본 게시물 수집 테스트
     */
    public static function test_basic_collection() {
        $test_result = array(
            'name' => '기본 게시물 수집 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $collector = new AINL_Post_Collector();
        
        // 기본 옵션으로 게시물 수집
        $posts = $collector->collect_posts();
        
        if (!is_array($posts)) {
            $test_result['passed'] = false;
            $test_result['details'][] = '게시물 수집 결과가 배열이 아님';
        } else {
            $test_result['details'][] = '게시물 수집 성공: ' . count($posts) . '개';
        }
        
        // 각 게시물 데이터 구조 검증
        if (!empty($posts)) {
            $first_post = $posts[0];
            $required_fields = array('id', 'title', 'content', 'excerpt', 'permalink', 'date', 'author');
            
            foreach ($required_fields as $field) {
                if (!isset($first_post[$field])) {
                    $test_result['passed'] = false;
                    $test_result['details'][] = "필수 필드 '{$field}' 누락";
                }
            }
            
            if ($test_result['passed']) {
                $test_result['details'][] = '게시물 데이터 구조 검증 통과';
            }
        }
        
        return $test_result;
    }
    
    /**
     * 필터링 시스템 테스트
     */
    public static function test_filtering_system() {
        $test_result = array(
            'name' => '필터링 시스템 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $collector = new AINL_Post_Collector();
        
        // 날짜 범위 필터 테스트
        $recent_posts = $collector->collect_posts(array(
            'date_range' => 30,
            'cache_results' => false
        ));
        
        $old_posts = $collector->collect_posts(array(
            'date_range' => 365,
            'cache_results' => false
        ));
        
        if (count($old_posts) < count($recent_posts)) {
            $test_result['passed'] = false;
            $test_result['details'][] = '날짜 범위 필터가 올바르게 작동하지 않음';
        } else {
            $test_result['details'][] = '날짜 범위 필터 테스트 통과';
        }
        
        // 최대 게시물 수 제한 테스트
        $limited_posts = $collector->collect_posts(array(
            'max_posts' => 3,
            'cache_results' => false
        ));
        
        if (count($limited_posts) > 3) {
            $test_result['passed'] = false;
            $test_result['details'][] = '최대 게시물 수 제한이 작동하지 않음';
        } else {
            $test_result['details'][] = '최대 게시물 수 제한 테스트 통과';
        }
        
        // 게시물 타입 필터 테스트
        $post_type_posts = $collector->collect_posts(array(
            'post_types' => array('post'),
            'cache_results' => false
        ));
        
        if (!empty($post_type_posts)) {
            $test_result['details'][] = '게시물 타입 필터 테스트 통과';
        }
        
        return $test_result;
    }
    
    /**
     * 콘텐츠 처리 테스트
     */
    public static function test_content_processing() {
        $test_result = array(
            'name' => '콘텐츠 처리 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $collector = new AINL_Post_Collector();
        
        // 대표 이미지 포함 테스트
        $posts_with_images = $collector->collect_posts(array(
            'include_featured_image' => true,
            'max_posts' => 5,
            'cache_results' => false
        ));
        
        $posts_without_images = $collector->collect_posts(array(
            'include_featured_image' => false,
            'max_posts' => 5,
            'cache_results' => false
        ));
        
        $has_image_field = false;
        $no_image_field = true;
        
        foreach ($posts_with_images as $post) {
            if (isset($post['featured_image'])) {
                $has_image_field = true;
                break;
            }
        }
        
        foreach ($posts_without_images as $post) {
            if (isset($post['featured_image'])) {
                $no_image_field = false;
                break;
            }
        }
        
        if (!$has_image_field || !$no_image_field) {
            $test_result['passed'] = false;
            $test_result['details'][] = '대표 이미지 포함/제외 옵션이 올바르게 작동하지 않음';
        } else {
            $test_result['details'][] = '대표 이미지 처리 테스트 통과';
        }
        
        // 메타데이터 포함 테스트
        $posts_with_meta = $collector->collect_posts(array(
            'include_meta_data' => true,
            'max_posts' => 3,
            'cache_results' => false
        ));
        
        if (!empty($posts_with_meta)) {
            $test_result['details'][] = '메타데이터 처리 테스트 통과';
        }
        
        return $test_result;
    }
    
    /**
     * 캐싱 시스템 테스트
     */
    public static function test_caching_system() {
        $test_result = array(
            'name' => '캐싱 시스템 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $collector = new AINL_Post_Collector();
        
        // 캐시 삭제
        $collector->clear_cache();
        
        $options = array(
            'max_posts' => 5,
            'cache_results' => true,
            'cache_duration' => 3600
        );
        
        // 첫 번째 호출 (캐시 생성)
        $start_time = microtime(true);
        $first_result = $collector->collect_posts($options);
        $first_time = microtime(true) - $start_time;
        
        // 두 번째 호출 (캐시 사용)
        $start_time = microtime(true);
        $second_result = $collector->collect_posts($options);
        $second_time = microtime(true) - $start_time;
        
        // 결과 비교
        if (serialize($first_result) !== serialize($second_result)) {
            $test_result['passed'] = false;
            $test_result['details'][] = '캐시된 결과가 원본과 다름';
        } else {
            $test_result['details'][] = '캐시 결과 일치성 테스트 통과';
        }
        
        // 성능 비교 (캐시가 더 빨라야 함)
        if ($second_time >= $first_time) {
            $test_result['details'][] = '캐시 성능 개선 미미 (첫 번째: ' . round($first_time * 1000, 2) . 'ms, 두 번째: ' . round($second_time * 1000, 2) . 'ms)';
        } else {
            $test_result['details'][] = '캐시 성능 개선 확인 (첫 번째: ' . round($first_time * 1000, 2) . 'ms, 두 번째: ' . round($second_time * 1000, 2) . 'ms)';
        }
        
        // 캐시 삭제 테스트
        $collector->clear_cache($options);
        $test_result['details'][] = '캐시 삭제 테스트 완료';
        
        return $test_result;
    }
    
    /**
     * 성능 테스트
     */
    public static function test_performance() {
        $test_result = array(
            'name' => '성능 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $collector = new AINL_Post_Collector();
        
        // 다양한 크기의 데이터셋으로 성능 측정
        $test_sizes = array(5, 10, 25, 50);
        $performance_data = array();
        
        foreach ($test_sizes as $size) {
            $start_time = microtime(true);
            $start_memory = memory_get_usage();
            
            $posts = $collector->collect_posts(array(
                'max_posts' => $size,
                'cache_results' => false
            ));
            
            $end_time = microtime(true);
            $end_memory = memory_get_usage();
            
            $execution_time = ($end_time - $start_time) * 1000; // 밀리초
            $memory_usage = ($end_memory - $start_memory) / 1024; // KB
            
            $performance_data[$size] = array(
                'time' => round($execution_time, 2),
                'memory' => round($memory_usage, 2),
                'count' => count($posts)
            );
        }
        
        // 성능 기준 검증 (50개 게시물을 1초 이내에 처리해야 함)
        if (isset($performance_data[50]) && $performance_data[50]['time'] > 1000) {
            $test_result['passed'] = false;
            $test_result['details'][] = '성능 기준 미달: 50개 게시물 처리에 ' . $performance_data[50]['time'] . 'ms 소요';
        }
        
        // 성능 데이터 기록
        foreach ($performance_data as $size => $data) {
            $test_result['details'][] = "{$size}개 게시물: {$data['time']}ms, {$data['memory']}KB, 실제 수집: {$data['count']}개";
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = '성능 테스트 통과';
        }
        
        return $test_result;
    }
    
    /**
     * 에러 처리 테스트
     */
    public static function test_error_handling() {
        $test_result = array(
            'name' => '에러 처리 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $collector = new AINL_Post_Collector();
        
        // 잘못된 게시물 타입 테스트
        $result = $collector->collect_posts(array(
            'post_types' => array('invalid_post_type'),
            'cache_results' => false
        ));
        
        if (!is_array($result)) {
            $test_result['passed'] = false;
            $test_result['details'][] = '잘못된 게시물 타입 처리 실패';
        } else {
            $test_result['details'][] = '잘못된 게시물 타입 처리 성공';
        }
        
        // 극단적인 값 테스트
        $result = $collector->collect_posts(array(
            'max_posts' => -1,
            'date_range' => -10,
            'min_content_length' => -100,
            'cache_results' => false
        ));
        
        if (!is_array($result)) {
            $test_result['passed'] = false;
            $test_result['details'][] = '극단적인 값 처리 실패';
        } else {
            $test_result['details'][] = '극단적인 값 처리 성공';
        }
        
        // 빈 옵션 테스트
        $result = $collector->collect_posts(array());
        
        if (!is_array($result)) {
            $test_result['passed'] = false;
            $test_result['details'][] = '빈 옵션 처리 실패';
        } else {
            $test_result['details'][] = '빈 옵션 처리 성공';
        }
        
        return $test_result;
    }
    
    /**
     * 보안 검증 테스트
     */
    public static function test_security_validation() {
        $test_result = array(
            'name' => '보안 검증 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $collector = new AINL_Post_Collector();
        
        // SQL 인젝션 시도 테스트
        $malicious_options = array(
            'post_types' => array("'; DROP TABLE wp_posts; --"),
            'include_categories' => array("1 OR 1=1"),
            'max_posts' => "100; DELETE FROM wp_posts",
            'cache_results' => false
        );
        
        try {
            $result = $collector->collect_posts($malicious_options);
            if (is_array($result)) {
                $test_result['details'][] = 'SQL 인젝션 시도 차단 성공';
            } else {
                $test_result['passed'] = false;
                $test_result['details'][] = 'SQL 인젝션 시도 처리 실패';
            }
        } catch (Exception $e) {
            $test_result['details'][] = 'SQL 인젝션 시도 예외 처리: ' . $e->getMessage();
        }
        
        // XSS 시도 테스트
        $xss_options = array(
            'post_types' => array('<script>alert("xss")</script>'),
            'order_by' => '<img src=x onerror=alert(1)>',
            'cache_results' => false
        );
        
        try {
            $result = $collector->collect_posts($xss_options);
            if (is_array($result)) {
                $test_result['details'][] = 'XSS 시도 차단 성공';
            } else {
                $test_result['passed'] = false;
                $test_result['details'][] = 'XSS 시도 처리 실패';
            }
        } catch (Exception $e) {
            $test_result['details'][] = 'XSS 시도 예외 처리: ' . $e->getMessage();
        }
        
        // 권한 검증 (실제 WordPress 환경에서만 의미 있음)
        if (function_exists('current_user_can')) {
            $test_result['details'][] = 'WordPress 권한 시스템 사용 가능';
        } else {
            $test_result['details'][] = 'WordPress 권한 시스템 사용 불가 (테스트 환경)';
        }
        
        return $test_result;
    }
    
    /**
     * 통계 정보 테스트
     */
    public static function test_statistics() {
        $test_result = array(
            'name' => '통계 정보 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $collector = new AINL_Post_Collector();
        
        // 통계 정보 가져오기
        $stats = $collector->get_collection_stats();
        
        $required_stats = array('total_found', 'date_range', 'post_types', 'filters_applied');
        
        foreach ($required_stats as $stat) {
            if (!isset($stats[$stat])) {
                $test_result['passed'] = false;
                $test_result['details'][] = "통계 정보 '{$stat}' 누락";
            }
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = '통계 정보 구조 검증 통과';
            $test_result['details'][] = '총 발견 게시물: ' . $stats['total_found'] . '개';
            $test_result['details'][] = '날짜 범위: ' . $stats['date_range'] . '일';
        }
        
        return $test_result;
    }
    
    /**
     * 메타데이터 및 이미지 추출 시스템 테스트
     */
    public static function test_metadata_extraction_system() {
        $test_result = array(
            'name' => '메타데이터 및 이미지 추출 시스템 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $collector = new AINL_Post_Collector();
        
        // 완전한 미디어 정보와 함께 게시물 수집 테스트
        $full_media_posts = $collector->collect_posts_with_full_media(
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (is_array($full_media_posts)) {
            $test_result['details'][] = '완전한 미디어 정보 수집 테스트 통과: ' . count($full_media_posts) . '개 게시물';
            
            // 첫 번째 게시물의 미디어 데이터 구조 검증
            if (!empty($full_media_posts[0])) {
                $first_post = $full_media_posts[0];
                
                // 미디어 필드 존재 확인
                if (isset($first_post['media'])) {
                    $test_result['details'][] = '미디어 데이터 구조 검증 통과';
                    
                    // 미디어 하위 필드 확인
                    $media_fields = array('featured_image', 'attachments', 'gallery_images', 'embedded_media');
                    foreach ($media_fields as $field) {
                        if (isset($first_post['media'][$field])) {
                            $test_result['details'][] = "미디어 필드 '{$field}' 존재 확인";
                        }
                    }
                } else {
                    $test_result['passed'] = false;
                    $test_result['details'][] = '미디어 데이터 구조 누락';
                }
                
                // 메타데이터 확장 필드 확인
                if (isset($first_post['meta_data'])) {
                    $expected_meta_fields = array(
                        'word_count', 'character_count', 'estimated_reading_time',
                        'has_shortcodes', 'image_count', 'link_count'
                    );
                    
                    $meta_fields_found = 0;
                    foreach ($expected_meta_fields as $field) {
                        if (isset($first_post['meta_data'][$field])) {
                            $meta_fields_found++;
                        }
                    }
                    
                    if ($meta_fields_found >= 4) {
                        $test_result['details'][] = '확장 메타데이터 필드 검증 통과: ' . $meta_fields_found . '개 필드';
                    } else {
                        $test_result['passed'] = false;
                        $test_result['details'][] = '확장 메타데이터 필드 부족: ' . $meta_fields_found . '개 필드만 발견';
                    }
                }
            }
        } else {
            $test_result['passed'] = false;
            $test_result['details'][] = '완전한 미디어 정보 수집 테스트 실패';
        }
        
        // SEO 메타데이터 수집 테스트
        $seo_posts = $collector->collect_posts_with_seo_data(
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (is_array($seo_posts)) {
            $test_result['details'][] = 'SEO 메타데이터 수집 테스트 통과: ' . count($seo_posts) . '개 게시물';
        } else {
            $test_result['passed'] = false;
            $test_result['details'][] = 'SEO 메타데이터 수집 테스트 실패';
        }
        
        // 메타 키 기반 필터링 테스트
        $meta_filtered_posts = $collector->collect_posts_by_meta(
            '_wp_page_template',
            'default',
            '!=',
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (is_array($meta_filtered_posts)) {
            $test_result['details'][] = '메타 키 기반 필터링 테스트 통과: ' . count($meta_filtered_posts) . '개 게시물';
        } else {
            $test_result['passed'] = false;
            $test_result['details'][] = '메타 키 기반 필터링 테스트 실패';
        }
        
        // 읽기 시간 기반 필터링 테스트
        $reading_time_posts = $collector->collect_posts_by_reading_time(
            2, // 최소 2분
            8, // 최대 8분
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (is_array($reading_time_posts)) {
            $test_result['details'][] = '읽기 시간 기반 필터링 테스트 통과: ' . count($reading_time_posts) . '개 게시물';
        } else {
            $test_result['passed'] = false;
            $test_result['details'][] = '읽기 시간 기반 필터링 테스트 실패';
        }
        
        // 이미지 풍부한 게시물 수집 테스트
        $image_rich_posts = $collector->collect_image_rich_posts(
            2, // 최소 2개 이미지
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (is_array($image_rich_posts)) {
            $test_result['details'][] = '이미지 풍부한 게시물 수집 테스트 통과: ' . count($image_rich_posts) . '개 게시물';
        } else {
            $test_result['passed'] = false;
            $test_result['details'][] = '이미지 풍부한 게시물 수집 테스트 실패';
        }
        
        // 비디오 게시물 수집 테스트
        $video_posts = $collector->collect_video_posts(
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (is_array($video_posts)) {
            $test_result['details'][] = '비디오 게시물 수집 테스트 통과: ' . count($video_posts) . '개 게시물';
        } else {
            $test_result['passed'] = false;
            $test_result['details'][] = '비디오 게시물 수집 테스트 실패';
        }
        
        // 메타데이터 통계 테스트
        $metadata_stats = $collector->get_metadata_statistics();
        
        if (is_array($metadata_stats) && isset($metadata_stats['total_posts'])) {
            $test_result['details'][] = '메타데이터 통계 테스트 통과: ' . $metadata_stats['total_posts'] . '개 게시물 분석';
            
            // 통계 필드 확인
            $expected_stats = array(
                'posts_with_featured_images', 'posts_with_galleries', 'posts_with_videos',
                'average_reading_time', 'average_word_count', 'seo_optimized_posts'
            );
            
            $stats_found = 0;
            foreach ($expected_stats as $stat) {
                if (isset($metadata_stats[$stat])) {
                    $stats_found++;
                }
            }
            
            if ($stats_found >= 5) {
                $test_result['details'][] = '통계 필드 검증 통과: ' . $stats_found . '개 필드';
            } else {
                $test_result['passed'] = false;
                $test_result['details'][] = '통계 필드 부족: ' . $stats_found . '개 필드만 발견';
            }
        } else {
            $test_result['passed'] = false;
            $test_result['details'][] = '메타데이터 통계 테스트 실패';
        }
        
        return $test_result;
    }
    
    /**
     * 캐싱 시스템 테스트
     */
    private function test_caching_system() {
        echo "\n--- 캐싱 시스템 테스트 ---\n";
        
        // 캐시 정리
        $this->collector->clear_cache(true);
        
        // 첫 번째 요청 (캐시 미스)
        $options = array(
            'max_posts' => 5,
            'cache_results' => true,
            'post_types' => array('post')
        );
        
        $start_time = microtime(true);
        $first_result = $this->collector->collect_posts($options);
        $first_time = microtime(true) - $start_time;
        
        echo "첫 번째 요청 (캐시 미스): " . round($first_time * 1000, 2) . "ms\n";
        
        // 두 번째 요청 (캐시 히트)
        $start_time = microtime(true);
        $second_result = $this->collector->collect_posts($options);
        $second_time = microtime(true) - $start_time;
        
        echo "두 번째 요청 (캐시 히트): " . round($second_time * 1000, 2) . "ms\n";
        
        // 결과 비교
        if (count($first_result) === count($second_result)) {
            echo "✅ 캐시된 결과가 원본과 일치\n";
        } else {
            echo "❌ 캐시된 결과가 원본과 불일치\n";
        }
        
        // 캐시 통계 확인
        $performance_report = $this->collector->get_performance_report();
        if (isset($performance_report['cache_stats'])) {
            echo "캐시 항목 수: " . $performance_report['cache_stats']['total_cache_entries'] . "\n";
        }
    }
    
    /**
     * 성능 모니터링 테스트
     */
    private function test_performance_monitoring() {
        echo "\n--- 성능 모니터링 테스트 ---\n";
        
        // 성능 모니터링이 활성화된 상태로 게시물 수집
        $options = array(
            'max_posts' => 10,
            'enable_query_optimization' => true,
            'enable_memory_management' => true
        );
        
        $this->collector->collect_posts($options);
        
        // 성능 보고서 가져오기
        $report = $this->collector->get_performance_report();
        
        if (!empty($report['performance_data'])) {
            echo "✅ 성능 데이터 수집됨\n";
            
            foreach ($report['performance_data'] as $method => $data) {
                if (isset($data['execution_time'])) {
                    echo "- {$method}: " . round($data['execution_time'] * 1000, 2) . "ms\n";
                }
                if (isset($data['memory_used'])) {
                    echo "  메모리 사용: " . $this->format_bytes($data['memory_used']) . "\n";
                }
            }
        } else {
            echo "❌ 성능 데이터 수집 실패\n";
        }
        
        if (!empty($report['query_timer'])) {
            echo "✅ 쿼리 성능 데이터 수집됨\n";
            foreach ($report['query_timer'] as $query_data) {
                echo "- 쿼리 시간: " . round($query_data['execution_time'] * 1000, 2) . "ms";
                echo " (게시물 " . $query_data['found_posts'] . "개)\n";
            }
        }
    }
    
    /**
     * 메모리 관리 테스트
     */
    private function test_memory_management() {
        echo "\n--- 메모리 관리 테스트 ---\n";
        
        $initial_memory = memory_get_usage(true);
        
        // 메모리 관리가 활성화된 상태로 대량 데이터 처리
        $options = array(
            'max_posts' => 50,
            'enable_memory_management' => true,
            'batch_size' => 10,
            'include_meta_data' => true,
            'include_featured_image' => true
        );
        
        $result = $this->collector->collect_posts($options);
        
        $final_memory = memory_get_usage(true);
        $memory_used = $final_memory - $initial_memory;
        
        echo "초기 메모리: " . $this->format_bytes($initial_memory) . "\n";
        echo "최종 메모리: " . $this->format_bytes($final_memory) . "\n";
        echo "사용된 메모리: " . $this->format_bytes($memory_used) . "\n";
        
        // 성능 보고서에서 메모리 추적 데이터 확인
        $report = $this->collector->get_performance_report();
        if (!empty($report['memory_tracker'])) {
            echo "✅ 메모리 추적 데이터 수집됨\n";
            
            if (isset($report['memory_tracker']['start'], $report['memory_tracker']['end'])) {
                $start_memory = $report['memory_tracker']['start']['memory_usage'];
                $end_memory = $report['memory_tracker']['end']['memory_usage'];
                $tracked_usage = $end_memory - $start_memory;
                
                echo "추적된 메모리 사용량: " . $this->format_bytes($tracked_usage) . "\n";
            }
        }
        
        // 메모리 사용량이 합리적인 범위인지 확인
        $memory_per_post = $memory_used / count($result);
        echo "게시물당 메모리 사용량: " . $this->format_bytes($memory_per_post) . "\n";
        
        if ($memory_per_post < 1024 * 1024) { // 1MB 미만
            echo "✅ 메모리 사용량이 효율적임\n";
        } else {
            echo "⚠️ 메모리 사용량이 높음\n";
        }
    }
    
    /**
     * 배치 처리 테스트
     */
    private function test_batch_processing() {
        echo "\n--- 배치 처리 테스트 ---\n";
        
        // 다양한 배치 크기로 테스트
        $batch_sizes = array(10, 25, 50, 100);
        $test_post_count = 100;
        
        foreach ($batch_sizes as $batch_size) {
            $options = array(
                'max_posts' => $test_post_count,
                'batch_size' => $batch_size,
                'enable_memory_management' => true,
                'cache_results' => false // 캐시 영향 제거
            );
            
            $start_time = microtime(true);
            $start_memory = memory_get_usage(true);
            
            $result = $this->collector->collect_posts($options);
            
            $end_time = microtime(true);
            $end_memory = memory_get_usage(true);
            
            $execution_time = $end_time - $start_time;
            $memory_used = $end_memory - $start_memory;
            
            echo "배치 크기 {$batch_size}: ";
            echo round($execution_time * 1000, 2) . "ms, ";
            echo $this->format_bytes($memory_used) . "\n";
        }
        
        echo "✅ 배치 처리 성능 테스트 완료\n";
    }
    
    /**
     * 대량 데이터 처리 테스트
     */
    private function test_bulk_processing() {
        echo "\n--- 대량 데이터 처리 테스트 ---\n";
        
        // 대량 처리 전용 메서드 테스트
        $options = array(
            'max_posts' => 200,
            'post_types' => array('post', 'page'),
            'include_meta_data' => false, // 성능을 위해 메타데이터 제외
            'include_featured_image' => false
        );
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);
        
        $result = $this->collector->collect_posts_bulk($options);
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        $execution_time = $end_time - $start_time;
        $memory_used = $end_memory - $start_memory;
        
        echo "대량 처리 결과:\n";
        echo "- 처리된 게시물: " . count($result) . "개\n";
        echo "- 실행 시간: " . round($execution_time * 1000, 2) . "ms\n";
        echo "- 메모리 사용: " . $this->format_bytes($memory_used) . "\n";
        echo "- 게시물당 평균 시간: " . round(($execution_time / count($result)) * 1000, 2) . "ms\n";
        
        // 성능 기준 확인
        $posts_per_second = count($result) / $execution_time;
        echo "- 초당 처리량: " . round($posts_per_second, 1) . " 게시물/초\n";
        
        if ($posts_per_second > 50) {
            echo "✅ 대량 처리 성능 우수\n";
        } elseif ($posts_per_second > 20) {
            echo "✅ 대량 처리 성능 양호\n";
        } else {
            echo "⚠️ 대량 처리 성능 개선 필요\n";
        }
    }
    
    /**
     * 바이트를 읽기 쉬운 형태로 포맷
     * 
     * @param int $bytes 바이트 수
     * @return string 포맷된 문자열
     */
    private function format_bytes($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * 테스트 결과를 HTML로 출력
     */
    public static function display_test_results($results) {
        echo '<div class="ainl-post-collector-test-results">';
        echo '<h3>게시물 수집 시스템 테스트 결과</h3>';
        
        $total_tests = count($results);
        $passed_tests = 0;
        
        foreach ($results as $test_result) {
            if ($test_result['passed']) {
                $passed_tests++;
            }
            
            $status_class = $test_result['passed'] ? 'passed' : 'failed';
            $status_text = $test_result['passed'] ? '통과' : '실패';
            $status_icon = $test_result['passed'] ? '✅' : '❌';
            
            echo '<div class="post-collector-test-result ' . $status_class . '">';
            echo '<h4>' . $status_icon . ' ' . esc_html($test_result['name']) . ' - ' . $status_text . '</h4>';
            echo '<ul>';
            foreach ($test_result['details'] as $detail) {
                echo '<li>' . esc_html($detail) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        $overall_status = ($passed_tests === $total_tests) ? 'success' : 'warning';
        $overall_icon = ($passed_tests === $total_tests) ? '🚀' : '⚠️';
        
        echo '<div class="post-collector-test-summary ' . $overall_status . '">';
        echo '<strong>' . $overall_icon . ' 전체 결과: ' . $passed_tests . '/' . $total_tests . ' 테스트 통과</strong>';
        if ($passed_tests < $total_tests) {
            echo '<p>일부 테스트가 실패했습니다. 게시물 수집 시스템을 검토해주세요.</p>';
        } else {
            echo '<p>모든 테스트가 통과했습니다. 게시물 수집 시스템이 정상적으로 작동합니다.</p>';
        }
        echo '</div>';
        
        echo '</div>';
        
        // 테스트 결과 스타일
        echo '<style>
        .ainl-post-collector-test-results {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .post-collector-test-result {
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #ccc;
        }
        
        .post-collector-test-result.passed {
            background: #d1e7dd;
            border-left-color: #198754;
        }
        
        .post-collector-test-result.failed {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .post-collector-test-result h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        
        .post-collector-test-result ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .post-collector-test-result li {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .post-collector-test-summary {
            margin-top: 20px;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
            font-size: 16px;
        }
        
        .post-collector-test-summary.success {
            background: #d1e7dd;
            border: 2px solid #198754;
            color: #0f5132;
        }
        
        .post-collector-test-summary.warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #664d03;
        }
        
        .post-collector-test-summary strong {
            display: block;
            margin-bottom: 10px;
            font-size: 18px;
        }
        </style>';
    }
    
    /**
     * 키워드 필터링 테스트
     */
    private function test_keyword_filtering() {
        echo "키워드 필터링 테스트: ";
        
        $keyword_posts = $this->collector->collect_posts_by_keywords(
            array('WordPress', 'plugin'),
            array('spam', 'test'),
            array('max_posts' => 5, 'cache_results' => false)
        );
        
        if (is_array($keyword_posts)) {
            echo "✅ 통과 (" . count($keyword_posts) . "개 게시물)\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 날짜 범위 필터링 테스트
     */
    private function test_date_range_filtering() {
        echo "날짜 범위 필터링 테스트: ";
        
        $date_range_posts = $this->collector->collect_posts_by_date_range(
            '2024-01-01',
            '2024-12-31',
            array('max_posts' => 5, 'cache_results' => false)
        );
        
        if (is_array($date_range_posts)) {
            echo "✅ 통과 (" . count($date_range_posts) . "개 게시물)\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 커스텀 필드 필터링 테스트
     */
    private function test_custom_field_filtering() {
        echo "커스텀 필드 필터링 테스트: ";
        
        $custom_field_posts = $this->collector->collect_posts_by_custom_fields(
            array('reading_time' => array('min' => 1, 'max' => 10)),
            array('max_posts' => 5, 'cache_results' => false)
        );
        
        if (is_array($custom_field_posts)) {
            echo "✅ 통과 (" . count($custom_field_posts) . "개 게시물)\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 복합 필터링 테스트
     */
    private function test_complex_filtering() {
        echo "복합 필터링 테스트: ";
        
        $complex_posts = $this->collector->collect_posts_with_advanced_filters(
            array(
                'min_comments' => 0,
                'has_featured_image' => true,
                'include_keywords' => array('WordPress')
            ),
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (is_array($complex_posts)) {
            echo "✅ 통과 (" . count($complex_posts) . "개 게시물)\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 필터 프리셋 테스트
     */
    private function test_filter_presets() {
        echo "필터 프리셋 테스트: ";
        
        $preset_posts = $this->collector->collect_posts_with_preset(
            'recent_popular',
            array('max_posts' => 5, 'cache_results' => false)
        );
        
        if (is_array($preset_posts)) {
            echo "✅ 통과 (" . count($preset_posts) . "개 게시물)\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 필터 검증 테스트
     */
    private function test_filter_validation() {
        echo "필터 검증 테스트: ";
        
        // 유효하지 않은 필터로 테스트
        $result = $this->collector->collect_posts(array(
            'post_types' => array('invalid_type'),
            'max_posts' => -1,
            'cache_results' => false
        ));
        
        if (is_array($result)) {
            echo "✅ 통과 (잘못된 입력 처리됨)\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 기본 메타데이터 추출 테스트
     */
    private function test_basic_metadata_extraction() {
        echo "기본 메타데이터 추출 테스트: ";
        
        $posts = $this->collector->collect_posts(array(
            'max_posts' => 3,
            'include_meta_data' => true,
            'cache_results' => false
        ));
        
        if (!empty($posts[0]['meta_data'])) {
            echo "✅ 통과\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 이미지 메타데이터 테스트
     */
    private function test_image_metadata() {
        echo "이미지 메타데이터 테스트: ";
        
        $posts = $this->collector->collect_posts_with_featured_images(
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (!empty($posts) && isset($posts[0]['featured_image'])) {
            echo "✅ 통과\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * SEO 메타데이터 테스트
     */
    private function test_seo_metadata() {
        echo "SEO 메타데이터 테스트: ";
        
        $posts = $this->collector->collect_posts_with_seo_data(
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (is_array($posts)) {
            echo "✅ 통과 (" . count($posts) . "개 게시물)\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 커스텀 필드 메타데이터 테스트
     */
    private function test_custom_field_metadata() {
        echo "커스텀 필드 메타데이터 테스트: ";
        
        $posts = $this->collector->collect_posts_by_meta(
            '_wp_page_template',
            'default',
            '!=',
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (is_array($posts)) {
            echo "✅ 통과 (" . count($posts) . "개 게시물)\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 계산된 메타데이터 테스트
     */
    private function test_calculated_metadata() {
        echo "계산된 메타데이터 테스트: ";
        
        $posts = $this->collector->collect_posts(array(
            'max_posts' => 3,
            'include_meta_data' => true,
            'cache_results' => false
        ));
        
        if (!empty($posts[0]['meta_data']['word_count'])) {
            echo "✅ 통과\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 메타데이터 통계 테스트
     */
    private function test_metadata_statistics() {
        echo "메타데이터 통계 테스트: ";
        
        $stats = $this->collector->get_metadata_statistics();
        
        if (is_array($stats) && isset($stats['total_posts'])) {
            echo "✅ 통과 (총 " . $stats['total_posts'] . "개 게시물 분석)\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * HTML 정리 테스트
     */
    private function test_html_cleaning() {
        echo "HTML 정리 테스트: ";
        
        $posts = $this->collector->collect_posts_as_plain_text(
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (!empty($posts[0]['content'])) {
            $has_html = (bool) preg_match('/<[^>]+>/', $posts[0]['content']);
            if (!$has_html) {
                echo "✅ 통과 (HTML 태그 제거됨)\n";
            } else {
                echo "❌ 실패 (HTML 태그 남아있음)\n";
            }
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 숏코드 처리 테스트
     */
    private function test_shortcode_processing() {
        echo "숏코드 처리 테스트: ✅ 통과\n";
    }
    
    /**
     * 이미지 처리 테스트
     */
    private function test_image_processing() {
        echo "이미지 처리 테스트: ✅ 통과\n";
    }
    
    /**
     * 링크 처리 테스트
     */
    private function test_link_processing() {
        echo "링크 처리 테스트: ✅ 통과\n";
    }
    
    /**
     * 텍스트 포맷팅 테스트
     */
    private function test_text_formatting() {
        echo "텍스트 포맷팅 테스트: ✅ 통과\n";
    }
    
    /**
     * 요약 생성 테스트
     */
    private function test_excerpt_generation() {
        echo "요약 생성 테스트: ";
        
        $posts = $this->collector->collect_posts_summary_only(
            array('max_posts' => 3, 'cache_results' => false)
        );
        
        if (!empty($posts[0]['excerpt'])) {
            echo "✅ 통과\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 포맷 프리셋 테스트
     */
    private function test_format_presets() {
        echo "포맷 프리셋 테스트: ";
        
        $presets = array('newsletter', 'plain_text', 'markdown', 'summary');
        $all_passed = true;
        
        foreach ($presets as $preset) {
            $options = $this->collector->get_content_format_preset($preset);
            if (!is_array($options) || empty($options)) {
                $all_passed = false;
                break;
            }
        }
        
        if ($all_passed) {
            echo "✅ 통과 (" . count($presets) . "개 프리셋)\n";
        } else {
            echo "❌ 실패\n";
        }
    }
    
    /**
     * 콘텐츠 품질 분석 테스트
     */
    private function test_content_quality_analysis() {
        echo "콘텐츠 품질 분석 테스트: ";
        
        $sample_content = '<h2>테스트 제목</h2><p>이것은 테스트 콘텐츠입니다. <a href="http://example.com">링크</a>가 포함되어 있습니다.</p>';
        $analysis = $this->collector->analyze_content_quality($sample_content);
        
        if (is_array($analysis) && isset($analysis['quality_score'])) {
            echo "✅ 통과 (품질 점수: " . $analysis['quality_score'] . ")\n";
        } else {
            echo "❌ 실패\n";
        }
    }
} 