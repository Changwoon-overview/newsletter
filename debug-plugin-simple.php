<?php
/**
 * AI Newsletter Generator Pro 간단 디버깅 도구
 * 기본적인 정보만 확인합니다.
 */

// 오류 표시 활성화
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>AI Newsletter Generator Pro - 간단 디버깅</h1>";

// WordPress 경로 확인
$possible_wp_paths = array(
    $_SERVER['DOCUMENT_ROOT'] . '/wp-config.php',
    dirname($_SERVER['DOCUMENT_ROOT']) . '/wp-config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../wp-config.php',
    '/opt/bitnami/wordpress/wp-config.php',
    '/var/www/html/wp-config.php'
);

$wp_config_found = false;
$wp_config_path = '';

foreach ($possible_wp_paths as $path) {
    if (file_exists($path)) {
        $wp_config_found = true;
        $wp_config_path = $path;
        break;
    }
}

echo "<h2>1. WordPress 설치 확인</h2>";
echo "<p><strong>현재 경로:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>wp-config.php 발견:</strong> " . ($wp_config_found ? '<span style="color: green;">예 (' . $wp_config_path . ')</span>' : '<span style="color: red;">아니오</span>') . "</p>";

if (!$wp_config_found) {
    echo "<p style='color: red;'><strong>WordPress 설치를 찾을 수 없습니다. 올바른 디렉토리에 파일을 업로드했는지 확인하세요.</strong></p>";
    echo "<h3>추천 조치:</h3>";
    echo "<ol>";
    echo "<li>파일을 WordPress 루트 디렉토리로 이동</li>";
    echo "<li>SSH로 접속하여: <code>find / -name 'wp-config.php' 2>/dev/null</code> 실행</li>";
    echo "</ol>";
    exit;
}

// WordPress 로드 시도
try {
    // WordPress 환경 로드
    $wp_load_path = dirname($wp_config_path) . '/wp-load.php';
    
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
        echo "<p><strong>WordPress 로드:</strong> <span style='color: green;'>성공</span></p>";
    } else {
        echo "<p><strong>WordPress 로드:</strong> <span style='color: red;'>wp-load.php 파일을 찾을 수 없음</span></p>";
        exit;
    }
    
} catch (Exception $e) {
    echo "<p><strong>WordPress 로드:</strong> <span style='color: red;'>실패 - " . $e->getMessage() . "</span></p>";
    exit;
}

// 사용자 권한 확인
echo "<h2>2. 사용자 권한</h2>";
if (function_exists('is_user_logged_in') && is_user_logged_in()) {
    echo "<p><strong>로그인 상태:</strong> <span style='color: green;'>로그인됨</span></p>";
    
    if (function_exists('current_user_can')) {
        $is_admin = current_user_can('manage_options');
        echo "<p><strong>관리자 권한:</strong> " . ($is_admin ? '<span style="color: green;">있음</span>' : '<span style="color: red;">없음</span>') . "</p>";
        
        if (!$is_admin) {
            echo "<p style='color: red;'><strong>관리자 권한이 필요합니다. 관리자 계정으로 로그인해주세요.</strong></p>";
            echo "<p><a href='" . wp_login_url() . "'>WordPress 로그인 페이지로 이동</a></p>";
            exit;
        }
    }
} else {
    echo "<p><strong>로그인 상태:</strong> <span style='color: red;'>로그아웃</span></p>";
    echo "<p style='color: red;'><strong>WordPress에 로그인이 필요합니다.</strong></p>";
    echo "<p><a href='" . (function_exists('wp_login_url') ? wp_login_url() : '/wp-admin/') . "'>WordPress 로그인 페이지로 이동</a></p>";
    exit;
}

// 플러그인 확인
echo "<h2>3. 플러그인 상태</h2>";
if (function_exists('get_option')) {
    $active_plugins = get_option('active_plugins', array());
    $plugin_file = 'ai-newsletter-generator-pro/ai-newsletter-generator-pro.php';
    $is_active = in_array($plugin_file, $active_plugins);
    
    echo "<p><strong>플러그인 활성화:</strong> " . ($is_active ? '<span style="color: green;">활성화됨</span>' : '<span style="color: red;">비활성화됨</span>') . "</p>";
    
    if (!$is_active) {
        echo "<p style='color: orange;'><strong>플러그인이 비활성화되어 있습니다.</strong></p>";
        echo "<p><a href='" . admin_url('plugins.php') . "'>플러그인 페이지로 이동하여 활성화</a></p>";
    }
} else {
    echo "<p style='color: red;'>WordPress 함수를 사용할 수 없습니다.</p>";
}

// 클래스 확인
echo "<h2>4. 클래스 로딩</h2>";
$classes = array('AI_Newsletter_Generator_Pro', 'AINL_Admin', 'AINL_Database');
foreach ($classes as $class) {
    $exists = class_exists($class);
    echo "<p><strong>$class:</strong> " . ($exists ? '<span style="color: green;">로드됨</span>' : '<span style="color: red;">로드 안됨</span>') . "</p>";
}

// 다음 단계 안내
echo "<h2>5. 다음 단계</h2>";
echo "<p>이 정보가 정상적으로 표시되면 상세 디버깅 도구를 실행할 수 있습니다.</p>";
echo "<p><a href='debug-plugin.php'>상세 디버깅 도구 실행</a></p>";

echo "<p style='color: red; margin-top: 20px;'><strong>중요:</strong> 디버깅 완료 후 이 파일들을 삭제하세요!</p>";
?> 