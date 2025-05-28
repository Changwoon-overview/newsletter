<?php
/**
 * AI Newsletter Generator Pro 디버깅 도구
 * 플러그인 로딩 상태를 확인합니다.
 */

// WordPress 환경 로드
require_once('wp-config.php');
require_once('wp-load.php');

// 관리자 권한 체크
if (!current_user_can('manage_options')) {
    die('권한이 없습니다. 관리자로 로그인해주세요.');
}

echo "<h1>AI Newsletter Generator Pro - 디버깅 정보</h1>";

// 1. 플러그인 활성화 상태 확인
$active_plugins = get_option('active_plugins');
$plugin_file = 'ai-newsletter-generator-pro/ai-newsletter-generator-pro.php';
$is_active = in_array($plugin_file, $active_plugins);

echo "<h2>1. 플러그인 활성화 상태</h2>";
echo "<p><strong>활성화 상태:</strong> " . ($is_active ? '<span style="color: green;">활성화됨</span>' : '<span style="color: red;">비활성화됨</span>') . "</p>";

// 2. 플러그인 파일 존재 확인
$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
$file_exists = file_exists($plugin_path);

echo "<h2>2. 플러그인 파일 존재</h2>";
echo "<p><strong>파일 경로:</strong> $plugin_path</p>";
echo "<p><strong>파일 존재:</strong> " . ($file_exists ? '<span style="color: green;">존재함</span>' : '<span style="color: red;">없음</span>') . "</p>";

// 3. 클래스 로딩 상태 확인
$classes_to_check = array(
    'AI_Newsletter_Generator_Pro',
    'AINL_Admin',
    'AINL_Database',
    'AINL_Security',
    'AINL_Statistics'
);

echo "<h2>3. 클래스 로딩 상태</h2>";
foreach ($classes_to_check as $class) {
    $exists = class_exists($class);
    echo "<p><strong>$class:</strong> " . ($exists ? '<span style="color: green;">로드됨</span>' : '<span style="color: red;">로드 안됨</span>') . "</p>";
}

// 4. WordPress 관리자 메뉴 확인
echo "<h2>4. WordPress 관리자 메뉴</h2>";
global $menu, $submenu;
$found_menu = false;

if (isset($menu)) {
    foreach ($menu as $menu_item) {
        if (isset($menu_item[2]) && strpos($menu_item[2], 'ai-newsletter') !== false) {
            $found_menu = true;
            echo "<p><strong>발견된 메뉴:</strong> " . $menu_item[0] . " (슬러그: " . $menu_item[2] . ")</p>";
        }
    }
}

if (!$found_menu) {
    echo "<p style='color: red;'><strong>AI Newsletter 메뉴를 찾을 수 없습니다.</strong></p>";
}

// 5. 플러그인 옵션 확인
echo "<h2>5. 플러그인 옵션</h2>";
$plugin_activated = get_option('ainl_plugin_activated');
$plugin_version = get_option('ainl_plugin_version');

echo "<p><strong>ainl_plugin_activated:</strong> " . ($plugin_activated ? 'true' : 'false') . "</p>";
echo "<p><strong>ainl_plugin_version:</strong> " . ($plugin_version ? $plugin_version : '설정되지 않음') . "</p>";

// 6. 오류 로그 확인
echo "<h2>6. WordPress 오류 로그</h2>";
$error_log_path = WP_CONTENT_DIR . '/debug.log';
if (file_exists($error_log_path)) {
    $log_content = file_get_contents($error_log_path);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -20); // 최근 20줄
    
    echo "<p><strong>최근 오류 로그 (최근 20줄):</strong></p>";
    echo "<pre style='background: #f0f0f0; padding: 10px; font-size: 12px; max-height: 300px; overflow-y: auto;'>";
    foreach ($recent_lines as $line) {
        if (strpos($line, 'AI Newsletter') !== false || strpos($line, 'AINL') !== false) {
            echo "<span style='color: red;'>" . esc_html($line) . "</span>\n";
        } else {
            echo esc_html($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "<p>오류 로그 파일이 없습니다. WordPress 디버깅이 활성화되지 않았습니다.</p>";
}

// 7. 추천 해결 방법
echo "<h2>7. 추천 해결 방법</h2>";
echo "<ol>";
echo "<li><strong>WordPress 디버깅 활성화:</strong> wp-config.php에 <code>define('WP_DEBUG', true);</code> 추가</li>";
echo "<li><strong>플러그인 재활성화:</strong> 플러그인 목록에서 비활성화 후 다시 활성화</li>";
echo "<li><strong>권한 확인:</strong> 현재 사용자가 관리자 권한을 가지고 있는지 확인</li>";
echo "<li><strong>파일 권한 확인:</strong> 플러그인 파일들의 읽기 권한 확인</li>";
echo "</ol>";

echo "<p style='color: red;'><strong>중요:</strong> 작업 완료 후 이 파일(debug-plugin.php)을 반드시 삭제하세요!</p>";
?> 