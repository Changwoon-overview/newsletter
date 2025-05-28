<?php
// PHP 메모리 및 실행 시간 증가
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
ini_set('max_input_vars', 3000);

echo "<h1>PHP 설정 확인</h1>";
echo "<p><strong>현재 메모리 제한:</strong> " . ini_get('memory_limit') . "</p>";
echo "<p><strong>최대 실행 시간:</strong> " . ini_get('max_execution_time') . "초</p>";
echo "<p><strong>최대 입력 변수:</strong> " . ini_get('max_input_vars') . "</p>";

echo "<h2>WordPress 환경 테스트</h2>";

// WordPress 경로 찾기
$wp_paths = array(
    '/opt/bitnami/wordpress/wp-config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-config.php',
    dirname($_SERVER['DOCUMENT_ROOT']) . '/wp-config.php'
);

$wp_found = false;
foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        echo "<p><strong>WordPress 발견:</strong> $path</p>";
        $wp_found = true;
        
        // WordPress 로드 시도
        try {
            require_once(dirname($path) . '/wp-load.php');
            echo "<p style='color: green;'><strong>WordPress 로드 성공!</strong></p>";
            
            // 플러그인 상태 확인
            if (function_exists('get_option')) {
                $active_plugins = get_option('active_plugins', array());
                echo "<p><strong>활성 플러그인 수:</strong> " . count($active_plugins) . "</p>";
                
                $our_plugin = 'ai-newsletter-generator-pro/ai-newsletter-generator-pro.php';
                $is_active = in_array($our_plugin, $active_plugins);
                echo "<p><strong>AI Newsletter 플러그인:</strong> " . ($is_active ? '<span style="color: red;">활성화됨</span>' : '<span style="color: green;">비활성화됨</span>') . "</p>";
            }
            
        } catch (Error $e) {
            echo "<p style='color: red;'><strong>WordPress 로드 실패:</strong> " . $e->getMessage() . "</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'><strong>WordPress 로드 실패:</strong> " . $e->getMessage() . "</p>";
        }
        break;
    }
}

if (!$wp_found) {
    echo "<p style='color: red;'>WordPress 설치를 찾을 수 없습니다.</p>";
}

echo "<h2>다음 단계</h2>";
echo "<ol>";
echo "<li>이 페이지가 정상 작동하면 PHP는 문제없음</li>";
echo "<li>WordPress 로드가 실패하면 플러그인 문제일 가능성 높음</li>";
echo "<li>모든 것이 정상이면 .htaccess 파일 확인 필요</li>";
echo "</ol>";

echo "<p style='color: red; margin-top: 20px;'><strong>확인 후 이 파일을 삭제하세요!</strong></p>";
?> 