<?php
/**
 * Email 클래스 파일
 * AI Newsletter Generator Pro - 이메일 발송 기능
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Email {
    
    public function __construct() {
        // 이메일 기능 초기화
        error_log('AINL: Email class loaded successfully');
    }
    
    // 추가 이메일 기능들은 향후 구현
} 