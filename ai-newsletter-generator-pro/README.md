# AI Newsletter Generator Pro

WordPress 게시물을 AI가 분석하여 자동으로 뉴스레터를 생성하고 발송하는 통합 솔루션입니다.

## 기능

- **AI 기반 콘텐츠 생성**: OpenAI API를 활용한 자동 뉴스레터 생성
- **구독자 관리**: 완전한 구독자 데이터베이스 및 관리 시스템
- **이메일 발송**: SMTP 기반 대량 이메일 발송 시스템
- **템플릿 시스템**: 사용자 정의 이메일 템플릿
- **통계 및 분석**: 발송 성과 추적 및 분석
- **스케줄링**: 자동 뉴스레터 발송 스케줄링

## 시스템 요구사항

- WordPress 5.0 이상
- PHP 7.4 이상
- MySQL 5.7 이상 또는 MariaDB 10.2 이상
- 필수 PHP 확장: curl, json, mbstring

## 설치

1. 플러그인 파일을 `/wp-content/plugins/ai-newsletter-generator-pro/` 디렉토리에 업로드
2. WordPress 관리자에서 플러그인 활성화
3. AI Newsletter 메뉴에서 기본 설정 완료

## 기본 설정

1. **AI 설정**: OpenAI API 키 입력
2. **이메일 설정**: SMTP 서버 정보 입력
3. **발신자 정보**: 뉴스레터 발신자 이름 및 이메일 설정

## 개발 정보

### 디렉토리 구조

```
ai-newsletter-generator-pro/
├── ai-newsletter-generator-pro.php  # 메인 플러그인 파일
├── includes/                        # 핵심 클래스 파일들
│   ├── class-ainl-activator.php    # 활성화 처리
│   └── class-ainl-deactivator.php  # 비활성화 처리
├── admin/                          # 관리자 인터페이스
├── assets/                         # CSS, JS, 이미지 파일
├── templates/                      # 이메일 템플릿
└── README.md                       # 이 파일
```

### 클래스 네이밍 규칙

- 모든 클래스는 `AINL_` 접두사 사용
- 파일명은 `class-ainl-{클래스명}.php` 형식
- 오토로더를 통한 자동 클래스 로딩

### 데이터베이스 테이블

- `wp_ainl_subscribers`: 구독자 정보
- `wp_ainl_categories`: 구독자 카테고리
- `wp_ainl_templates`: 이메일 템플릿
- `wp_ainl_campaigns`: 발송 캠페인
- `wp_ainl_statistics`: 통계 데이터

## 라이선스

GPL v2 or later

## 지원

개발 관련 문의나 버그 리포트는 GitHub Issues를 통해 제출해 주세요.

## 버전 히스토리

### 1.0.0
- 초기 릴리스
- 기본 플러그인 구조 구현
- 활성화/비활성화 시스템 구현 