/**
 * AI Newsletter Generator Pro - 구독 폼 JavaScript
 * AJAX 폼 제출, 유효성 검사, 팝업 기능을 제공합니다.
 */

(function($) {
    'use strict';
    
    /**
     * 구독 폼 관리 객체
     */
    var AINL_Form = {
        
        /**
         * 초기화
         */
        init: function() {
            this.bindEvents();
            this.initExistingForms();
        },
        
        /**
         * 이벤트 바인딩
         */
        bindEvents: function() {
            // 폼 제출 이벤트
            $(document).on('submit', '.ainl-subscription-form-inner', this.handleFormSubmit.bind(this));
            
            // 실시간 이메일 유효성 검사
            $(document).on('input', '.ainl-email-input', this.validateEmailInput.bind(this));
            
            // GDPR 체크박스 변경
            $(document).on('change', '.ainl-gdpr-checkbox', this.handleGdprChange.bind(this));
            
            // 팝업 닫기
            $(document).on('click', '.ainl-popup-close, .ainl-popup-overlay', this.closePopup.bind(this));
            $(document).on('click', '.ainl-popup-container', function(e) {
                e.stopPropagation();
            });
            
            // ESC 키로 팝업 닫기
            $(document).on('keydown', this.handleKeyDown.bind(this));
        },
        
        /**
         * 기존 폼 초기화
         */
        initExistingForms: function() {
            $('.ainl-subscription-form').each(function() {
                AINL_Form.setupForm($(this));
            });
        },
        
        /**
         * 개별 폼 설정
         */
        setupForm: function($form) {
            var $emailInput = $form.find('.ainl-email-input');
            var $submitBtn = $form.find('.ainl-submit-btn');
            
            // 이메일 입력 시 버튼 활성화/비활성화
            $emailInput.on('input', function() {
                var email = $(this).val();
                if (AINL_Form.isValidEmail(email)) {
                    $submitBtn.prop('disabled', false);
                } else {
                    $submitBtn.prop('disabled', true);
                }
            });
            
            // 초기 상태 설정
            if (!AINL_Form.isValidEmail($emailInput.val())) {
                $submitBtn.prop('disabled', true);
            }
        },
        
        /**
         * 폼 제출 처리
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var $container = $form.closest('.ainl-subscription-form');
            
            // 이미 제출 중인 경우 중단
            if ($form.hasClass('ainl-submitting')) {
                return false;
            }
            
            // 유효성 검사
            if (!this.validateForm($form)) {
                return false;
            }
            
            // 제출 상태로 변경
            this.setSubmittingState($form, true);
            
            // AJAX 요청
            var formData = new FormData($form[0]);
            formData.append('action', 'ainl_subscribe');
            
            $.ajax({
                url: ainl_form_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: this.handleSubmitSuccess.bind(this, $form),
                error: this.handleSubmitError.bind(this, $form),
                complete: function() {
                    AINL_Form.setSubmittingState($form, false);
                }
            });
            
            return false;
        },
        
        /**
         * 폼 유효성 검사
         */
        validateForm: function($form) {
            var isValid = true;
            
            // 이메일 검사
            var $emailInput = $form.find('.ainl-email-input');
            var email = $emailInput.val().trim();
            
            if (!email) {
                this.showFieldError($emailInput, ainl_form_ajax.messages.required_email);
                isValid = false;
            } else if (!this.isValidEmail(email)) {
                this.showFieldError($emailInput, ainl_form_ajax.messages.invalid_email);
                isValid = false;
            } else {
                this.clearFieldError($emailInput);
            }
            
            // GDPR 동의 검사
            var $gdprCheckbox = $form.find('.ainl-gdpr-checkbox');
            if ($gdprCheckbox.length && !$gdprCheckbox.is(':checked')) {
                this.showFormMessage($form, ainl_form_ajax.messages.gdpr_required, 'error');
                isValid = false;
            }
            
            return isValid;
        },
        
        /**
         * 이메일 입력 실시간 검증
         */
        validateEmailInput: function(e) {
            var $input = $(e.target);
            var email = $input.val().trim();
            
            if (email && !this.isValidEmail(email)) {
                this.showFieldError($input, ainl_form_ajax.messages.invalid_email);
            } else {
                this.clearFieldError($input);
            }
        },
        
        /**
         * GDPR 체크박스 변경 처리
         */
        handleGdprChange: function(e) {
            var $checkbox = $(e.target);
            var $form = $checkbox.closest('.ainl-subscription-form-inner');
            
            if ($checkbox.is(':checked')) {
                this.clearFormMessage($form);
            }
        },
        
        /**
         * 제출 성공 처리
         */
        handleSubmitSuccess: function($form, response) {
            if (response.success) {
                this.showFormMessage($form, response.data.message, 'success');
                this.resetForm($form);
                
                // 리다이렉트 URL이 있는 경우
                var redirectUrl = $form.data('redirect');
                if (redirectUrl) {
                    setTimeout(function() {
                        window.location.href = redirectUrl;
                    }, 2000);
                }
                
                // 팝업인 경우 닫기
                if ($form.closest('.ainl-popup-overlay').length) {
                    setTimeout(function() {
                        AINL_Form.closePopup();
                    }, 3000);
                }
                
                // 성공 이벤트 발생
                $(document).trigger('ainl:subscription_success', {
                    form: $form,
                    response: response
                });
                
            } else {
                this.showFormMessage($form, response.data.message, 'error');
            }
        },
        
        /**
         * 제출 오류 처리
         */
        handleSubmitError: function($form, xhr, status, error) {
            var message = ainl_form_ajax.messages.error;
            
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                message = xhr.responseJSON.data.message;
            }
            
            this.showFormMessage($form, message, 'error');
            
            // 오류 이벤트 발생
            $(document).trigger('ainl:subscription_error', {
                form: $form,
                error: error,
                xhr: xhr
            });
        },
        
        /**
         * 제출 상태 설정
         */
        setSubmittingState: function($form, isSubmitting) {
            var $submitBtn = $form.find('.ainl-submit-btn');
            var $btnText = $submitBtn.find('.ainl-btn-text');
            var $btnLoading = $submitBtn.find('.ainl-btn-loading');
            
            if (isSubmitting) {
                $form.addClass('ainl-submitting');
                $submitBtn.prop('disabled', true);
                $btnText.hide();
                $btnLoading.show();
                this.clearFormMessage($form);
            } else {
                $form.removeClass('ainl-submitting');
                $submitBtn.prop('disabled', false);
                $btnText.show();
                $btnLoading.hide();
            }
        },
        
        /**
         * 폼 초기화
         */
        resetForm: function($form) {
            $form[0].reset();
            this.clearAllErrors($form);
        },
        
        /**
         * 폼 메시지 표시
         */
        showFormMessage: function($form, message, type) {
            var $container = $form.closest('.ainl-subscription-form');
            var $successMsg = $container.find('.ainl-success-message');
            var $errorMsg = $container.find('.ainl-error-message');
            
            // 기존 메시지 숨기기
            $successMsg.hide();
            $errorMsg.hide();
            
            // 새 메시지 표시
            if (type === 'success') {
                $successMsg.html(message).show();
                setTimeout(function() {
                    $successMsg.fadeOut();
                }, 10000);
            } else {
                $errorMsg.html(message).show();
                setTimeout(function() {
                    $errorMsg.fadeOut();
                }, 8000);
            }
            
            // 메시지로 스크롤
            $('html, body').animate({
                scrollTop: $container.offset().top - 20
            }, 300);
        },
        
        /**
         * 폼 메시지 지우기
         */
        clearFormMessage: function($form) {
            var $container = $form.closest('.ainl-subscription-form');
            $container.find('.ainl-success-message, .ainl-error-message').hide();
        },
        
        /**
         * 필드 오류 표시
         */
        showFieldError: function($input, message) {
            $input.addClass('ainl-error');
            
            // 기존 오류 메시지 제거
            $input.siblings('.ainl-field-error').remove();
            
            // 새 오류 메시지 추가
            $input.after('<div class="ainl-field-error" style="color: #e74c3c; font-size: 12px; margin-top: 5px;">' + message + '</div>');
        },
        
        /**
         * 필드 오류 지우기
         */
        clearFieldError: function($input) {
            $input.removeClass('ainl-error');
            $input.siblings('.ainl-field-error').remove();
        },
        
        /**
         * 모든 오류 지우기
         */
        clearAllErrors: function($form) {
            $form.find('.ainl-error').removeClass('ainl-error');
            $form.find('.ainl-field-error').remove();
            this.clearFormMessage($form);
        },
        
        /**
         * 이메일 유효성 검사
         */
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },
        
        /**
         * 키보드 이벤트 처리
         */
        handleKeyDown: function(e) {
            // ESC 키로 팝업 닫기
            if (e.keyCode === 27) {
                this.closePopup();
            }
        },
        
        /**
         * 팝업 초기화
         */
        initPopup: function(options) {
            var settings = $.extend({
                trigger: 'time',
                delay: 5000,
                scrollPercent: 50,
                exitIntent: true
            }, options);
            
            // 이미 표시된 경우 또는 닫힌 경우 체크
            if (localStorage.getItem('ainl_popup_closed') || sessionStorage.getItem('ainl_popup_shown')) {
                return;
            }
            
            switch (settings.trigger) {
                case 'time':
                    setTimeout(function() {
                        AINL_Form.showPopup();
                    }, settings.delay);
                    break;
                    
                case 'scroll':
                    this.initScrollTrigger(settings.scrollPercent);
                    break;
                    
                case 'exit':
                    this.initExitIntentTrigger();
                    break;
            }
        },
        
        /**
         * 스크롤 트리거 초기화
         */
        initScrollTrigger: function(percent) {
            var triggered = false;
            
            $(window).on('scroll', function() {
                if (triggered) return;
                
                var scrollTop = $(window).scrollTop();
                var docHeight = $(document).height();
                var winHeight = $(window).height();
                var scrollPercent = (scrollTop / (docHeight - winHeight)) * 100;
                
                if (scrollPercent >= percent) {
                    triggered = true;
                    AINL_Form.showPopup();
                }
            });
        },
        
        /**
         * 종료 의도 트리거 초기화
         */
        initExitIntentTrigger: function() {
            var triggered = false;
            
            $(document).on('mouseleave', function(e) {
                if (triggered || e.clientY > 0) return;
                
                triggered = true;
                AINL_Form.showPopup();
            });
        },
        
        /**
         * 팝업 표시
         */
        showPopup: function() {
            var $popup = $('#ainl-popup-overlay');
            
            if ($popup.length) {
                $popup.fadeIn(300);
                sessionStorage.setItem('ainl_popup_shown', '1');
                
                // 팝업 표시 이벤트
                $(document).trigger('ainl:popup_shown');
            }
        },
        
        /**
         * 팝업 닫기
         */
        closePopup: function(e) {
            if (e && $(e.target).hasClass('ainl-popup-container')) {
                return;
            }
            
            var $popup = $('#ainl-popup-overlay');
            
            if ($popup.length) {
                $popup.fadeOut(300);
                localStorage.setItem('ainl_popup_closed', '1');
                
                // 팝업 닫기 이벤트
                $(document).trigger('ainl:popup_closed');
            }
        },
        
        /**
         * 쿠키 설정
         */
        setCookie: function(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/";
        },
        
        /**
         * 쿠키 가져오기
         */
        getCookie: function(name) {
            var nameEQ = name + "=";
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        },
        
        /**
         * 쿠키 삭제
         */
        deleteCookie: function(name) {
            document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        }
    };
    
    /**
     * 전역 객체로 노출
     */
    window.AINL_Form = AINL_Form;
    
    /**
     * DOM 준비 시 초기화
     */
    $(document).ready(function() {
        AINL_Form.init();
    });
    
    /**
     * 사용자 정의 이벤트 예제
     */
    $(document).on('ainl:subscription_success', function(e, data) {
        // Google Analytics 추적 (예시)
        if (typeof gtag !== 'undefined') {
            gtag('event', 'newsletter_signup', {
                'event_category': 'engagement',
                'event_label': 'subscription_form'
            });
        }
        
        // Facebook Pixel 추적 (예시)
        if (typeof fbq !== 'undefined') {
            fbq('track', 'Lead');
        }
    });
    
    /**
     * 접근성 개선
     */
    $(document).on('keydown', '.ainl-subscription-form input, .ainl-subscription-form button', function(e) {
        // Tab 키 네비게이션 개선
        if (e.keyCode === 9) { // Tab
            var $form = $(this).closest('.ainl-subscription-form');
            var $focusable = $form.find('input, button, checkbox').filter(':visible');
            var index = $focusable.index(this);
            
            if (e.shiftKey) {
                // Shift + Tab (이전 요소)
                if (index === 0) {
                    e.preventDefault();
                    $focusable.last().focus();
                }
            } else {
                // Tab (다음 요소)
                if (index === $focusable.length - 1) {
                    e.preventDefault();
                    $focusable.first().focus();
                }
            }
        }
    });
    
    /**
     * 모바일 최적화
     */
    if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        // 모바일에서 팝업 지연 시간 증가
        $(document).on('ainl:popup_init', function(e, options) {
            if (options.trigger === 'time') {
                options.delay = Math.max(options.delay, 8000);
            }
        });
        
        // iOS에서 폼 제출 시 줌 방지
        $('meta[name=viewport]').attr('content', 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no');
    }
    
})(jQuery); 