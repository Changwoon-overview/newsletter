/**
 * AI Newsletter Generator Pro - 관리자 스크립트
 * 구독자 관리 및 기타 관리자 기능을 위한 JavaScript
 */

jQuery(document).ready(function($) {
    
    // 구독자 관리 기능 초기화
    initSubscriberManagement();
    
    // 템플릿 관리 기능 초기화
    initTemplateManagement();
    
    // SMTP 설정 기능 초기화
    initSMTPSettings();
    
    // 탭 기능 초기화
    initTabs();
    
    // 캠페인 마법사 초기화
    if ($('.ainl-campaign-wizard').length > 0) {
        initCampaignWizard();
        initEditor();
    }
    
    /**
     * 구독자 관리 기능 초기화
     */
    function initSubscriberManagement() {
        
        // 구독자 추가 버튼
        $('#add-subscriber-btn').click(function(e) {
            e.preventDefault();
            openSubscriberModal();
        });
        
        // 구독자 편집 버튼
        $(document).on('click', '.edit-subscriber', function(e) {
            e.preventDefault();
            const subscriberId = $(this).data('id');
            editSubscriber(subscriberId);
        });
        
        // 구독자 삭제 버튼
        $(document).on('click', '.delete-subscriber', function(e) {
            e.preventDefault();
            const subscriberId = $(this).data('id');
            deleteSubscriber(subscriberId);
        });
        
        // 전체 선택 체크박스
        $('#cb-select-all').change(function() {
            $('input[name="subscriber_ids[]"]').prop('checked', this.checked);
        });
        
        // 개별 체크박스 변경 시 전체 선택 상태 업데이트
        $(document).on('change', 'input[name="subscriber_ids[]"]', function() {
            const totalCheckboxes = $('input[name="subscriber_ids[]"]').length;
            const checkedCheckboxes = $('input[name="subscriber_ids[]"]:checked').length;
            $('#cb-select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
        });
        
        // 대량 삭제 버튼
        $('#bulk-delete-btn').click(function() {
            const selectedIds = getSelectedSubscriberIds();
            if (selectedIds.length === 0) {
                alert('삭제할 구독자를 선택해주세요.');
                return;
            }
            
            if (confirm(ainl_ajax.strings.bulk_confirm_delete)) {
                bulkDeleteSubscribers(selectedIds);
            }
        });
        
        // 대량 상태 변경 버튼
        $('#bulk-status-btn').click(function() {
            const selectedIds = getSelectedSubscriberIds();
            if (selectedIds.length === 0) {
                alert('상태를 변경할 구독자를 선택해주세요.');
                return;
            }
            
            const newStatus = prompt('새로운 상태를 입력하세요 (active, inactive, unsubscribed, bounced, blocked):');
            if (newStatus && ['active', 'inactive', 'unsubscribed', 'bounced', 'blocked'].includes(newStatus)) {
                bulkUpdateStatus(selectedIds, newStatus);
            } else if (newStatus) {
                alert('유효하지 않은 상태입니다.');
            }
        });
        
        // CSV 가져오기 버튼
        $('#import-subscribers-btn').click(function() {
            openImportModal();
        });
        
        // CSV 내보내기 버튼
        $('#export-subscribers-btn').click(function() {
            exportSubscribers();
        });
        
        // 구독자 모달 초기화
        if ($('#subscriber-modal').length) {
            $('#subscriber-modal').dialog({
                autoOpen: false,
                modal: true,
                width: 500,
                height: 400,
                resizable: false,
                buttons: {
                    '저장': function() {
                        saveSubscriber();
                    },
                    '취소': function() {
                        $(this).dialog('close');
                    }
                }
            });
        }
        
        // CSV 가져오기 모달 초기화
        if ($('#import-modal').length) {
            $('#import-modal').dialog({
                autoOpen: false,
                modal: true,
                width: 500,
                height: 300,
                resizable: false,
                buttons: {
                    '가져오기': function() {
                        importSubscribers();
                    },
                    '취소': function() {
                        $(this).dialog('close');
                    }
                }
            });
        }
    }
    
    /**
     * 템플릿 관리 기능 초기화
     */
    function initTemplateManagement() {
        
        // 템플릿 테스트 버튼
        $('#test-templates-btn').click(function() {
            const button = $(this);
            const resultsDiv = $('#test-results');
            
            button.prop('disabled', true).text('테스트 실행 중...');
            resultsDiv.html('<p>템플릿 시스템을 테스트하고 있습니다...</p>');
            
            $.post(ainl_ajax.ajax_url, {
                action: 'ainl_test_templates',
                nonce: ainl_ajax.nonce
            }, function(response) {
                button.prop('disabled', false).text('모든 템플릿 테스트 실행');
                
                if (response.success) {
                    let html = '<div class="notice notice-success"><p>모든 테스트가 성공적으로 완료되었습니다!</p></div>';
                    html += '<h3>테스트 결과:</h3><ul>';
                    
                    response.data.results.forEach(function(result) {
                        const status = result.passed ? '✅' : '❌';
                        html += `<li>${status} ${result.test}: ${result.message}</li>`;
                    });
                    
                    html += '</ul>';
                    resultsDiv.html(html);
                } else {
                    resultsDiv.html('<div class="notice notice-error"><p>테스트 실행 중 오류가 발생했습니다: ' + response.data + '</p></div>');
                }
            });
        });
    }
    
    /**
     * 구독자 모달 열기
     */
    function openSubscriberModal(subscriberId = null) {
        const modal = $('#subscriber-modal');
        const form = $('#subscriber-form');
        
        // 폼 초기화
        form[0].reset();
        $('#subscriber-id').val('');
        
        if (subscriberId) {
            // 편집 모드: 구독자 정보 로드
            modal.dialog('option', 'title', '구독자 편집');
            loadSubscriberData(subscriberId);
        } else {
            // 추가 모드
            modal.dialog('option', 'title', '새 구독자 추가');
        }
        
        modal.dialog('open');
    }
    
    /**
     * CSV 가져오기 모달 열기
     */
    function openImportModal() {
        const modal = $('#import-modal');
        const form = $('#import-form');
        
        // 폼 초기화
        form[0].reset();
        modal.dialog('open');
    }
    
    /**
     * 구독자 정보 로드
     */
    function loadSubscriberData(subscriberId) {
        // 테이블에서 구독자 정보 추출
        const row = $(`tr[data-subscriber-id="${subscriberId}"]`);
        if (row.length) {
            const email = row.find('td:nth-child(2) strong').text();
            const name = row.find('td:nth-child(3)').text().trim();
            const status = row.find('.ainl-status').attr('class').match(/ainl-status-(\w+)/)[1];
            const tags = row.find('td:nth-child(5)').text().trim();
            
            // 이름 분리 (간단한 방식)
            const nameParts = name !== '-' ? name.split(' ') : ['', ''];
            const firstName = nameParts[0] || '';
            const lastName = nameParts.slice(1).join(' ') || '';
            
            // 폼에 데이터 설정
            $('#subscriber-id').val(subscriberId);
            $('#subscriber-email').val(email);
            $('#subscriber-first-name').val(firstName);
            $('#subscriber-last-name').val(lastName);
            $('#subscriber-status').val(status);
            $('#subscriber-tags').val(tags !== '-' ? tags : '');
        }
    }
    
    /**
     * 구독자 저장
     */
    function saveSubscriber() {
        const form = $('#subscriber-form');
        const subscriberId = $('#subscriber-id').val();
        const isEdit = subscriberId !== '';
        
        const data = {
            action: isEdit ? 'ainl_update_subscriber' : 'ainl_add_subscriber',
            nonce: ainl_ajax.nonce,
            email: $('#subscriber-email').val(),
            first_name: $('#subscriber-first-name').val(),
            last_name: $('#subscriber-last-name').val(),
            status: $('#subscriber-status').val(),
            tags: $('#subscriber-tags').val()
        };
        
        if (isEdit) {
            data.subscriber_id = subscriberId;
        }
        
        // 이메일 유효성 검사
        if (!data.email || !isValidEmail(data.email)) {
            alert('유효한 이메일 주소를 입력해주세요.');
            return;
        }
        
        $.post(ainl_ajax.ajax_url, data, function(response) {
            if (response.success) {
                alert(response.data.message);
                $('#subscriber-modal').dialog('close');
                location.reload(); // 페이지 새로고침
            } else {
                alert('오류: ' + response.data);
            }
        });
    }
    
    /**
     * 구독자 편집
     */
    function editSubscriber(subscriberId) {
        openSubscriberModal(subscriberId);
    }
    
    /**
     * 구독자 삭제
     */
    function deleteSubscriber(subscriberId) {
        if (!confirm(ainl_ajax.strings.confirm_delete)) {
            return;
        }
        
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_delete_subscriber',
            nonce: ainl_ajax.nonce,
            subscriber_id: subscriberId
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                $(`tr[data-subscriber-id="${subscriberId}"]`).fadeOut(function() {
                    $(this).remove();
                });
            } else {
                alert('오류: ' + response.data);
            }
        });
    }
    
    /**
     * 선택된 구독자 ID 목록 반환
     */
    function getSelectedSubscriberIds() {
        const selectedIds = [];
        $('input[name="subscriber_ids[]"]:checked').each(function() {
            selectedIds.push($(this).val());
        });
        return selectedIds;
    }
    
    /**
     * 대량 구독자 삭제
     */
    function bulkDeleteSubscribers(subscriberIds) {
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_bulk_action_subscribers',
            nonce: ainl_ajax.nonce,
            bulk_action: 'delete',
            subscriber_ids: subscriberIds
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('오류: ' + response.data);
            }
        });
    }
    
    /**
     * 대량 상태 업데이트
     */
    function bulkUpdateStatus(subscriberIds, newStatus) {
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_bulk_action_subscribers',
            nonce: ainl_ajax.nonce,
            bulk_action: 'status_change',
            subscriber_ids: subscriberIds,
            new_status: newStatus
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('오류: ' + response.data);
            }
        });
    }
    
    /**
     * CSV 가져오기
     */
    function importSubscribers() {
        const formData = new FormData();
        const fileInput = $('#csv-file')[0];
        
        if (!fileInput.files[0]) {
            alert('CSV 파일을 선택해주세요.');
            return;
        }
        
        formData.append('action', 'ainl_import_subscribers');
        formData.append('nonce', ainl_ajax.nonce);
        formData.append('csv_file', fileInput.files[0]);
        formData.append('update_existing', $('#update-existing').is(':checked') ? '1' : '0');
        
        $.ajax({
            url: ainl_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    const result = response.data.result;
                    let message = `가져오기 완료!\n`;
                    message += `총 ${result.total_rows}행 처리\n`;
                    message += `가져온 구독자: ${result.imported}명\n`;
                    message += `업데이트된 구독자: ${result.updated}명\n`;
                    message += `건너뛴 구독자: ${result.skipped}명`;
                    
                    if (result.errors.length > 0) {
                        message += `\n\n오류:\n${result.errors.slice(0, 5).join('\n')}`;
                        if (result.errors.length > 5) {
                            message += `\n... 및 ${result.errors.length - 5}개 추가 오류`;
                        }
                    }
                    
                    alert(message);
                    $('#import-modal').dialog('close');
                    location.reload();
                } else {
                    alert('오류: ' + response.data);
                }
            },
            error: function() {
                alert('파일 업로드 중 오류가 발생했습니다.');
            }
        });
    }
    
    /**
     * CSV 내보내기
     */
    function exportSubscribers() {
        // 현재 필터 조건 가져오기
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status') || '';
        const search = urlParams.get('s') || '';
        
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_export_subscribers',
            nonce: ainl_ajax.nonce,
            status: status,
            search: search
        }, function(response) {
            if (response.success) {
                // 다운로드 링크 생성
                const link = document.createElement('a');
                link.href = response.data.download_url;
                link.download = '';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                alert(response.data.message);
            } else {
                alert('오류: ' + response.data);
            }
        });
    }
    
    /**
     * 이메일 유효성 검사
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    /**
     * 템플릿 미리보기 로드
     */
    window.loadTemplatePreview = function(templateId) {
        const iframe = document.querySelector(`iframe[data-template="${templateId}"]`);
        
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_get_template_preview',
            template_id: templateId,
            nonce: ainl_ajax.nonce
        }, function(response) {
            if (response.success) {
                iframe.srcdoc = response.data.html;
            } else {
                alert('미리보기를 로드할 수 없습니다: ' + response.data);
            }
        });
    };
    
    /**
     * 템플릿 사용
     */
    window.useTemplate = function(templateId) {
        const baseUrl = window.location.origin + window.location.pathname.replace(/\/wp-admin\/.*/, '/wp-admin/admin.php');
        window.location.href = `${baseUrl}?page=ainl-create-newsletter&template=${templateId}`;
    };
    
    /**
     * 탭 기능 초기화
     */
    function initTabs() {
        $('.ainl-tab-link').click(function(e) {
            e.preventDefault();
            
            const targetTab = $(this).attr('href');
            
            // 모든 탭 비활성화
            $('.ainl-tab-link').removeClass('active');
            $('.ainl-tab-content').removeClass('active');
            
            // 선택된 탭 활성화
            $(this).addClass('active');
            $(targetTab).addClass('active');
        });
    }
    
    /**
     * SMTP 설정 기능 초기화
     */
    function initSMTPSettings() {
        
        // SMTP 연결 테스트 버튼
        $('#test-smtp').click(function() {
            const button = $(this);
            const resultDiv = $('#smtp-test-result');
            
            button.prop('disabled', true).text('테스트 중...');
            resultDiv.removeClass('success error').hide();
            
            $.post(ainl_ajax.ajax_url, {
                action: 'ainl_test_smtp',
                nonce: ainl_ajax.nonce
            }, function(response) {
                button.prop('disabled', false).text('SMTP 연결 테스트');
                
                if (response.success) {
                    resultDiv.addClass('success').html('✅ ' + response.data.message).show();
                } else {
                    resultDiv.addClass('error').html('❌ ' + response.data).show();
                }
            }).fail(function() {
                button.prop('disabled', false).text('SMTP 연결 테스트');
                resultDiv.addClass('error').html('❌ 연결 테스트 중 오류가 발생했습니다.').show();
            });
        });
        
        // 테스트 이메일 발송 버튼
        $('#send-test-email').click(function() {
            const button = $(this);
            const emailInput = $('#test-email');
            const resultDiv = $('#smtp-test-result');
            const email = emailInput.val().trim();
            
            if (!email) {
                alert('테스트 이메일 주소를 입력해주세요.');
                emailInput.focus();
                return;
            }
            
            if (!isValidEmail(email)) {
                alert('유효한 이메일 주소를 입력해주세요.');
                emailInput.focus();
                return;
            }
            
            button.prop('disabled', true).text('발송 중...');
            resultDiv.removeClass('success error').hide();
            
            $.post(ainl_ajax.ajax_url, {
                action: 'ainl_send_test_email',
                email: email,
                nonce: ainl_ajax.nonce
            }, function(response) {
                button.prop('disabled', false).text('테스트 이메일 발송');
                
                if (response.success) {
                    resultDiv.addClass('success').html('✅ ' + response.data.message).show();
                    emailInput.val(''); // 성공 시 입력 필드 초기화
                } else {
                    resultDiv.addClass('error').html('❌ ' + response.data).show();
                }
            }).fail(function() {
                button.prop('disabled', false).text('테스트 이메일 발송');
                resultDiv.addClass('error').html('❌ 테스트 이메일 발송 중 오류가 발생했습니다.').show();
            });
        });
        
        // 큐 즉시 처리 버튼
        $('#process-queue').click(function() {
            const button = $(this);
            
            button.prop('disabled', true).text('처리 중...');
            
            $.post(ainl_ajax.ajax_url, {
                action: 'ainl_process_email_queue',
                nonce: ainl_ajax.nonce
            }, function(response) {
                button.prop('disabled', false).text('큐 즉시 처리');
                
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    refreshQueueStatus(); // 상태 새로고침
                } else {
                    alert('❌ ' + response.data);
                }
            }).fail(function() {
                button.prop('disabled', false).text('큐 즉시 처리');
                alert('❌ 큐 처리 중 오류가 발생했습니다.');
            });
        });
        
        // 큐 정리 버튼
        $('#clear-queue').click(function() {
            if (!confirm('완료된 이메일 항목을 정리하시겠습니까?')) {
                return;
            }
            
            const button = $(this);
            
            button.prop('disabled', true).text('정리 중...');
            
            $.post(ainl_ajax.ajax_url, {
                action: 'ainl_clear_email_queue',
                nonce: ainl_ajax.nonce
            }, function(response) {
                button.prop('disabled', false).text('완료된 항목 정리');
                
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    refreshQueueStatus(); // 상태 새로고침
                } else {
                    alert('❌ ' + response.data);
                }
            }).fail(function() {
                button.prop('disabled', false).text('완료된 항목 정리');
                alert('❌ 큐 정리 중 오류가 발생했습니다.');
            });
        });
        
        // 큐 상태 새로고침 버튼
        $('#refresh-queue').click(function() {
            refreshQueueStatus();
        });
    }
    
    /**
     * 큐 상태 새로고침
     */
    function refreshQueueStatus() {
        const button = $('#refresh-queue');
        
        button.prop('disabled', true).text('새로고침 중...');
        
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_refresh_queue_status',
            nonce: ainl_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).text('상태 새로고침');
            
            if (response.success) {
                // 큐 상태 업데이트
                const data = response.data;
                $('.ainl-stat-number.pending').text(data.pending);
                $('.ainl-stat-number.sending').text(data.sending);
                $('.ainl-stat-number.sent').text(data.sent);
                $('.ainl-stat-number.failed').text(data.failed);
                $('.ainl-stat-number.total').text(data.total);
            } else {
                alert('❌ 상태 새로고침 중 오류가 발생했습니다.');
            }
        }).fail(function() {
            button.prop('disabled', false).text('상태 새로고침');
            alert('❌ 상태 새로고침 중 오류가 발생했습니다.');
        });
    }
    
    /**
     * 캠페인 마법사 초기화
     */
    function initCampaignWizard() {
        let currentStep = 1;
        const totalSteps = 5;
        
        // 편집 모드인지 확인
        const campaignId = $('.ainl-campaign-wizard').data('campaign-id');
        const isEditMode = campaignId && campaignId > 0;
        
        // 편집 모드인 경우 기존 데이터 로드
        if (isEditMode) {
            loadCampaignData(campaignId);
        }
        
        // 단계 네비게이션 이벤트
        $('.ainl-step').on('click', function() {
            const targetStep = $(this).data('step');
            const stepNumber = getStepNumber(targetStep);
            
            if (stepNumber <= currentStep || validateStepsUpTo(stepNumber - 1)) {
                goToStep(stepNumber);
            }
        });
        
        // 다음/이전 버튼 이벤트
        $('#next-step').on('click', function() {
            if (validateCurrentStep()) {
                if (currentStep < totalSteps) {
                    goToStep(currentStep + 1);
                }
            }
        });
        
        $('#prev-step').on('click', function() {
            if (currentStep > 1) {
                goToStep(currentStep - 1);
            }
        });
        
        // 기타 이벤트 바인딩
        bindWizardEvents();
        
        // 초기 상태 설정
        updateNavigationButtons(currentStep);
        updateCampaignSummary();
    }
    
    /**
     * 기존 캠페인 데이터 로드
     */
    function loadCampaignData(campaignId) {
        const loadData = {
            action: 'ainl_load_campaign',
            nonce: ainlAdmin.nonce,
            campaign_id: campaignId
        };
        
        $.post(ajaxurl, loadData, function(response) {
            if (response.success && response.data) {
                const campaign = response.data;
                
                // 기본 정보 로드
                $('#campaign-name').val(campaign.name || '');
                $('#email-subject').val(campaign.subject || '');
                $('#from-name').val(campaign.from_name || '');
                $('#from-email').val(campaign.from_email || '');
                
                // 템플릿 선택
                if (campaign.template_id) {
                    $(`input[name="template_id"][value="${campaign.template_id}"]`).prop('checked', true);
                }
                
                // 콘텐츠 타입 및 설정
                if (campaign.content_type) {
                    // 탭 활성화
                    if (campaign.content_type === 'filter') {
                        $('.ainl-tab-button[data-tab="filter"]').click();
                        
                        // 필터 설정 로드
                        if (campaign.content_filters) {
                            const filters = campaign.content_filters;
                            $('#date-range').val(filters.date_range || 'last_week');
                            $('#date-from').val(filters.date_from || '');
                            $('#date-to').val(filters.date_to || '');
                            $('input[name="max_posts"]').val(filters.max_posts || 10);
                            
                            // 카테고리 선택
                            if (filters.categories && Array.isArray(filters.categories)) {
                                filters.categories.forEach(function(catId) {
                                    $(`input[name="categories[]"][value="${catId}"]`).prop('checked', true);
                                });
                            }
                        }
                    } else if (campaign.content_type === 'manual') {
                        $('.ainl-tab-button[data-tab="manual"]').click();
                        
                        // 선택된 게시물 로드
                        if (campaign.selected_posts && Array.isArray(campaign.selected_posts)) {
                            // 게시물 로드 및 표시 (AJAX 호출 필요)
                            loadSelectedPosts(campaign.selected_posts);
                        }
                    }
                }
                
                // 에디터 콘텐츠 로드
                if (campaign.content) {
                    setEditorContent(campaign.content);
                    $('#newsletter-preview').html(campaign.content);
                }
                
                // 요약 정보 업데이트
                updateCampaignSummary();
                
            } else {
                alert('캠페인 데이터를 불러오는 중 오류가 발생했습니다.');
            }
        }).fail(function() {
            alert('캠페인 데이터를 불러오는 중 오류가 발생했습니다.');
        });
    }
    
    /**
     * 선택된 게시물 로드 및 표시
     */
    function loadSelectedPosts(postIds) {
        if (!postIds || postIds.length === 0) {
            return;
        }
        
        const loadData = {
            action: 'ainl_load_selected_posts',
            nonce: ainlAdmin.nonce,
            post_ids: postIds
        };
        
        $.post(ajaxurl, loadData, function(response) {
            if (response.success && response.data) {
                const posts = response.data;
                const $container = $('#selected-posts-list');
                
                $container.empty();
                
                posts.forEach(function(post) {
                    const postHtml = `
                        <div class="selected-post" data-post-id="${post.ID}" data-permalink="${post.permalink}">
                            <div class="post-info">
                                <h4 class="post-title">${post.post_title}</h4>
                                <p class="post-excerpt">${post.excerpt}</p>
                                <div class="post-meta">
                                    <span class="post-date">${post.post_date}</span>
                                    <span class="post-category">${post.categories}</span>
                                </div>
                            </div>
                            <button type="button" class="remove-post" title="제거">×</button>
                        </div>
                    `;
                    $container.append(postHtml);
                });
                
                // 제거 이벤트 바인딩
                bindPostRemovalEvents();
                
                // 선택된 게시물 수 업데이트
                updateSelectedPostsCount();
            }
        });
    }
    
    /**
     * 단계 번호 가져오기
     */
    function getStepNumber(stepName) {
        const stepMap = {
            'basic': 1,
            'content': 2,
            'design': 3,
            'preview': 4,
            'send': 5
        };
        return stepMap[stepName] || 1;
    }
    
    /**
     * 지정된 단계까지 유효성 검사
     */
    function validateStepsUpTo(stepNumber) {
        for (let i = 1; i <= stepNumber; i++) {
            if (!validateStep(i)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * 특정 단계 유효성 검사
     */
    function validateStep(stepNumber) {
        switch (stepNumber) {
            case 1:
                return validateBasicInfo();
            case 2:
                return validateContentSelection();
            case 3:
                return validateDesignSelection();
            case 4:
                return validatePreview();
            case 5:
                return validateSendSettings();
            default:
                return true;
        }
    }
    
    /**
     * 선택된 게시물 수 업데이트
     */
    function updateSelectedPostsCount() {
        const count = $('#selected-posts-list .selected-post').length;
        $('#selected-posts-count').text(`${count}개 게시물 선택됨`);
    }
    
    /**
     * 마법사 이벤트 바인딩
     */
    function bindWizardEvents() {
        // 탭 전환
        $('.ainl-tab-button').on('click', function() {
            const tab = $(this).data('tab');
            
            $('.ainl-tab-button').removeClass('active');
            $(this).addClass('active');
            
            $('.ainl-tab-content').removeClass('active');
            $(`.ainl-tab-content[data-tab="${tab}"]`).addClass('active');
            
            updateCampaignSummary();
        });
        
        // 날짜 범위 변경
        $('#date-range').on('change', function() {
            const range = $(this).val();
            if (range === 'custom') {
                $('#custom-date-range').show();
            } else {
                $('#custom-date-range').hide();
            }
        });
        
        // 필터 미리보기
        $('#preview-filtered-posts').on('click', previewFilteredPosts);
        
        // 게시물 검색
        $('#search-posts-btn').on('click', searchPosts);
        $('#post-search').on('keypress', function(e) {
            if (e.which === 13) {
                searchPosts();
            }
        });
        
        // AI 콘텐츠 생성
        $('#generate-content').on('click', generateAIContent);
        
        // 에디터 토글
        $('#toggle-editor').on('click', toggleEditor);
        
        // 테스트 이메일
        $('#send-test-email').on('click', sendTestEmail);
        
        // 캠페인 저장
        $('#save-campaign').on('click', saveCampaign);
        
        // 캠페인 발송
        $('#launch-campaign').on('click', launchCampaign);
        
        // 발송 타입 변경
        $('input[name="send_type"]').on('change', function() {
            const sendType = $(this).val();
            if (sendType === 'scheduled') {
                $('#scheduled-options').show();
            } else {
                $('#scheduled-options').hide();
            }
            updateSendTypeSummary();
        });
        
        // 폼 필드 변경 시 요약 업데이트
        $('#campaign-name, #email-subject, #from-name, #from-email').on('input', updateCampaignSummary);
        $('input[name="template_id"]').on('change', updateTemplateSummary);
    }
    
    /**
     * 캠페인 저장
     */
    function saveCampaign() {
        const button = $('#save-campaign');
        const originalText = button.text();
        
        button.text('저장 중...').prop('disabled', true);
        
        const campaignData = collectCampaignData();
        campaignData.status = 'draft';
        
        const saveData = {
            action: 'ainl_save_campaign',
            nonce: ainlAdmin.nonce,
            campaign_data: campaignData
        };
        
        $.post(ajaxurl, saveData, function(response) {
            if (response.success) {
                alert('캠페인이 저장되었습니다.');
                
                // 캠페인 ID 업데이트
                $('.ainl-campaign-wizard').data('campaign-id', response.data.campaign_id);
            } else {
                alert('캠페인 저장 중 오류가 발생했습니다: ' + response.data);
            }
        }).always(function() {
            button.text(originalText).prop('disabled', false);
        });
    }
    
    /**
     * 캠페인 발송
     */
    function launchCampaign() {
        if (!confirm('정말로 캠페인을 발송하시겠습니까? 발송 후에는 취소할 수 없습니다.')) {
            return;
        }
        
        const button = $('#launch-campaign');
        const originalText = button.text();
        
        button.text('발송 중...').prop('disabled', true);
        
        const campaignData = collectCampaignData();
        const sendType = $('input[name="send_type"]:checked').val();
        
        if (sendType === 'scheduled') {
            campaignData.scheduled_at = $('#scheduled-at').val();
            campaignData.status = 'ready';
        } else {
            campaignData.status = 'sending';
        }
        
        const launchData = {
            action: 'ainl_launch_campaign',
            nonce: ainlAdmin.nonce,
            campaign_data: campaignData
        };
        
        $.post(ajaxurl, launchData, function(response) {
            if (response.success) {
                if (sendType === 'scheduled') {
                    alert('캠페인이 예약되었습니다.');
                } else {
                    alert('캠페인 발송이 시작되었습니다.');
                }
                
                // 캠페인 목록으로 이동
                window.location.href = ainlAdmin.campaignsUrl;
            } else {
                alert('캠페인 발송 중 오류가 발생했습니다: ' + response.data);
            }
        }).always(function() {
            button.text(originalText).prop('disabled', false);
        });
    }
    
    /**
     * 캠페인 데이터 수집
     */
    function collectCampaignData() {
        const data = {
            campaign_id: $('.ainl-campaign-wizard').data('campaign-id') || 0,
            name: $('#campaign-name').val(),
            subject: $('#email-subject').val(),
            from_name: $('#from-name').val(),
            from_email: $('#from-email').val(),
            template_id: $('input[name="template_id"]:checked').val(),
            content: getEditorContent()
        };
        
        // 콘텐츠 선택 데이터
        const activeTab = $('.ainl-tab-button.active').data('tab');
        if (activeTab === 'filter') {
            data.content_type = 'filter';
            data.filter_settings = {
                date_range: $('#date-range').val(),
                date_from: $('#date-from').val(),
                date_to: $('#date-to').val(),
                categories: $('input[name="categories[]"]:checked').map(function() {
                    return $(this).val();
                }).get(),
                max_posts: $('input[name="max_posts"]').val()
            };
        } else {
            data.content_type = 'manual';
            data.selected_posts = $('#selected-posts-list .selected-post').map(function() {
                return $(this).data('post-id');
            }).get();
        }
        
        return data;
    }
    
    /**
     * 캠페인 요약 업데이트
     */
    function updateCampaignSummary() {
        $('#summary-name').text($('#campaign-name').val() || '(미입력)');
        $('#summary-subject').text($('#email-subject').val() || '(미입력)');
        
        const fromName = $('#from-name').val() || '(미입력)';
        const fromEmail = $('#from-email').val() || '(미입력)';
        $('#summary-from').text(`${fromName} <${fromEmail}>`);
    }
    
    /**
     * 템플릿 요약 업데이트
     */
    function updateTemplateSummary() {
        const selectedTemplate = $('input[name="template_id"]:checked');
        if (selectedTemplate.length > 0) {
            const templateName = selectedTemplate.closest('.ainl-template-option').find('.ainl-template-name').text();
            $('#summary-template').text(templateName);
        } else {
            $('#summary-template').text('(미선택)');
        }
    }
    
    /**
     * 발송 타입 요약 업데이트
     */
    function updateSendTypeSummary() {
        const sendType = $('input[name="send_type"]:checked').val();
        if (sendType === 'scheduled') {
            const scheduledAt = $('#scheduled-at').val();
            if (scheduledAt) {
                const date = new Date(scheduledAt);
                $('#summary-send-type').text(`예약 발송 (${date.toLocaleString()})`);
            } else {
                $('#summary-send-type').text('예약 발송 (시간 미설정)');
            }
        } else {
            $('#summary-send-type').text('즉시 발송');
        }
    }
    
    /**
     * 에디터 초기화 (기존 함수 개선)
     */
    function initEditor() {
        initAdvancedEditor();
        addEditorToolbar();
        
        // 에디터 로드 완료 후 실행
        setTimeout(function() {
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('newsletter_content')) {
                // 에디터가 준비되면 실시간 미리보기 활성화
                updateLivePreview();
            }
        }, 1000);
    }
    
    /**
     * 고급 TinyMCE 에디터 초기화
     */
    function initAdvancedEditor() {
        if (typeof tinymce === 'undefined') {
            return;
        }
        
        // 기존 에디터 제거
        if (tinymce.get('newsletter_content')) {
            tinymce.get('newsletter_content').remove();
        }
        
        tinymce.init({
            selector: '#newsletter_content',
            height: 500,
            menubar: false,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'paste', 'help', 'wordcount',
                'textcolor', 'colorpicker', 'hr'
            ],
            toolbar: 'undo redo | formatselect | bold italic underline strikethrough | ' +
                    'forecolor backcolor | alignleft aligncenter alignright alignjustify | ' +
                    'bullist numlist outdent indent | removeformat | link image media | ' +
                    'table hr | code preview fullscreen | help',
            content_style: `
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; 
                    font-size: 14px; 
                    line-height: 1.6;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .newsletter-header { 
                    text-align: center; 
                    border-bottom: 2px solid #0073aa; 
                    padding-bottom: 20px; 
                    margin-bottom: 30px; 
                }
                .newsletter-post { 
                    margin-bottom: 30px; 
                    padding: 20px; 
                    border: 1px solid #e1e1e1; 
                    border-radius: 5px; 
                }
                .newsletter-footer { 
                    text-align: center; 
                    border-top: 1px solid #e1e1e1; 
                    padding-top: 20px; 
                    margin-top: 30px; 
                    font-size: 12px; 
                    color: #666; 
                }
            `,
            setup: function(editor) {
                editor.on('change', function() {
                    updateLivePreview();
                });
                
                // 이미지 업로드 핸들러
                editor.on('paste', function(e) {
                    handleImagePaste(e, editor);
                });
            },
            images_upload_handler: function(blobInfo, success, failure) {
                uploadImageToWordPress(blobInfo, success, failure);
            },
            automatic_uploads: true,
            file_picker_types: 'image',
            file_picker_callback: function(callback, value, meta) {
                if (meta.filetype === 'image') {
                    openWordPressMediaLibrary(callback);
                }
            }
        });
    }
    
    /**
     * 에디터 도구모음 추가
     */
    function addEditorToolbar() {
        const toolbar = `
            <div class="ainl-editor-toolbar">
                <div class="ainl-toolbar-section">
                    <h4>템플릿 삽입</h4>
                    <button type="button" class="button" onclick="insertTemplate('header')">헤더</button>
                    <button type="button" class="button" onclick="insertTemplate('intro')">인트로</button>
                    <button type="button" class="button" onclick="insertTemplate('post_section')">게시물 섹션</button>
                    <button type="button" class="button" onclick="insertTemplate('footer')">푸터</button>
                </div>
                <div class="ainl-toolbar-section">
                    <h4>게시물 삽입</h4>
                    <button type="button" class="button" onclick="insertSelectedPosts()">선택된 게시물 삽입</button>
                </div>
                <div class="ainl-toolbar-section">
                    <h4>미리보기</h4>
                    <label>
                        <input type="checkbox" id="live-preview-toggle" checked>
                        실시간 미리보기
                    </label>
                </div>
            </div>
        `;
        
        $('#newsletter-editor').before(toolbar);
        
        // 실시간 미리보기 토글
        $('#live-preview-toggle').on('change', function() {
            if ($(this).is(':checked')) {
                updateLivePreview();
                $('#newsletter-preview').show();
            } else {
                $('#newsletter-preview').hide();
            }
        });
    }
    
    /**
     * 템플릿 삽입
     */
    window.insertTemplate = function(templateType) {
        const templates = {
            header: `
                <div class="newsletter-header">
                    <h1>{{site_name}} 뉴스레터</h1>
                    <p>{{newsletter_date}}</p>
                </div>
            `,
            intro: `
                <div class="newsletter-intro">
                    <h2>안녕하세요!</h2>
                    <p>이번 주 흥미로운 소식들을 전해드립니다.</p>
                </div>
            `,
            post_section: `
                <div class="newsletter-post">
                    <h3>{{post_title}}</h3>
                    <p class="post-meta">{{post_date}} | {{post_category}}</p>
                    <p>{{post_excerpt}}</p>
                    <p><a href="{{post_url}}">자세히 보기 →</a></p>
                </div>
            `,
            footer: `
                <div class="newsletter-footer">
                    <p>이 이메일은 {{site_name}}에서 발송되었습니다.</p>
                    <p><a href="{{unsubscribe_url}}">구독 취소</a></p>
                </div>
            `
        };
        
        const template = templates[templateType];
        if (template && tinymce.get('newsletter_content')) {
            tinymce.get('newsletter_content').insertContent(template);
        }
    };
    
    /**
     * 선택된 게시물 삽입
     */
    window.insertSelectedPosts = function() {
        const selectedPosts = $('#selected-posts-list .selected-post');
        
        if (selectedPosts.length === 0) {
            alert('삽입할 게시물을 먼저 선택해주세요.');
            return;
        }
        
        let content = '';
        selectedPosts.each(function() {
            const $post = $(this);
            const title = $post.find('.post-title').text();
            const excerpt = $post.find('.post-excerpt').text();
            const permalink = $post.data('permalink');
            const date = $post.find('.post-date').text();
            const category = $post.find('.post-category').text();
            
            content += `
                <div class="newsletter-post">
                    <h3><a href="${permalink}">${title}</a></h3>
                    <p class="post-meta">${date}${category ? ' | ' + category : ''}</p>
                    <p>${excerpt}</p>
                    <p><a href="${permalink}">자세히 보기 →</a></p>
                </div>
            `;
        });
        
        if (tinymce.get('newsletter_content')) {
            tinymce.get('newsletter_content').insertContent(content);
        }
    };
    
    /**
     * 실시간 미리보기 업데이트
     */
    function updateLivePreview() {
        if (!$('#live-preview-toggle').is(':checked')) {
            return;
        }
        
        const editor = tinymce.get('newsletter_content');
        if (editor) {
            const content = editor.getContent();
            $('#newsletter-preview').html(content);
        }
    }
    
    /**
     * 에디터 콘텐츠 가져오기
     */
    function getEditorContent() {
        const editor = tinymce.get('newsletter_content');
        if (editor) {
            return editor.getContent();
        }
        return $('#newsletter_content').val();
    }
    
    /**
     * 에디터 콘텐츠 설정
     */
    function setEditorContent(content) {
        const editor = tinymce.get('newsletter_content');
        if (editor) {
            editor.setContent(content);
        } else {
            $('#newsletter_content').val(content);
        }
        updateLivePreview();
    }
    
    /**
     * WordPress 미디어 라이브러리 열기
     */
    function openWordPressMediaLibrary(callback) {
        if (typeof wp !== 'undefined' && wp.media) {
            const frame = wp.media({
                title: '이미지 선택',
                button: {
                    text: '선택'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                callback(attachment.url, {
                    alt: attachment.alt,
                    title: attachment.title
                });
            });
            
            frame.open();
        }
    }
    
    /**
     * 이미지를 WordPress에 업로드
     */
    function uploadImageToWordPress(blobInfo, success, failure) {
        const formData = new FormData();
        formData.append('action', 'ainl_upload_image');
        formData.append('nonce', ainl_ajax.nonce);
        formData.append('image', blobInfo.blob(), blobInfo.filename());
        
        $.ajax({
            url: ainl_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    success(response.data.url);
                } else {
                    failure('이미지 업로드 실패: ' + response.data);
                }
            },
            error: function() {
                failure('이미지 업로드 중 오류가 발생했습니다.');
            }
        });
    }
    
    /**
     * 이미지 붙여넣기 처리
     */
    function handleImagePaste(e, editor) {
        const items = e.clipboardData.items;
        
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                const blob = items[i].getAsFile();
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    const base64 = event.target.result;
                    
                    // 임시 이미지 삽입
                    const tempId = 'temp_' + Date.now();
                    editor.insertContent(`<img id="${tempId}" src="${base64}" style="max-width: 100%;">`);
                    
                    // 서버에 업로드
                    uploadImageToWordPress({
                        blob: () => blob,
                        filename: () => 'pasted_image_' + Date.now() + '.png'
                    }, function(url) {
                        // 임시 이미지를 실제 URL로 교체
                        const tempImg = editor.dom.get(tempId);
                        if (tempImg) {
                            tempImg.src = url;
                            tempImg.removeAttribute('id');
                        }
                    }, function(error) {
                        // 업로드 실패 시 임시 이미지 제거
                        const tempImg = editor.dom.get(tempId);
                        if (tempImg) {
                            editor.dom.remove(tempImg);
                        }
                        alert('이미지 업로드 실패: ' + error);
                    });
                };
                
                reader.readAsDataURL(blob);
                break;
            }
        }
    }
    
    /**
     * 테스트 이메일 발송
     */
    function sendTestEmail() {
        const testEmail = $('#test-email').val().trim();
        
        if (!testEmail) {
            alert('테스트 이메일 주소를 입력해주세요.');
            $('#test-email').focus();
            return;
        }
        
        if (!isValidEmail(testEmail)) {
            alert('유효한 이메일 주소를 입력해주세요.');
            $('#test-email').focus();
            return;
        }
        
        const button = $('#send-test-email');
        const originalText = button.text();
        
        button.text('발송 중...').prop('disabled', true);
        
        const campaignData = collectCampaignData();
        campaignData.test_email = testEmail;
        
        const testData = {
            action: 'ainl_send_test_campaign',
            nonce: ainl_ajax.nonce,
            campaign_data: campaignData
        };
        
        $.post(ainl_ajax.ajax_url, testData, function(response) {
            if (response.success) {
                alert('✅ 테스트 이메일이 성공적으로 발송되었습니다.');
                $('#test-email').val(''); // 성공 시 입력 필드 초기화
            } else {
                alert('❌ 테스트 이메일 발송 실패: ' + response.data);
            }
        }).fail(function() {
            alert('❌ 테스트 이메일 발송 중 오류가 발생했습니다.');
        }).always(function() {
            button.text(originalText).prop('disabled', false);
        });
    }
    
    /**
     * 필터링된 게시물 미리보기
     */
    function previewFilteredPosts() {
        const filterData = {
            action: 'ainl_preview_filtered_posts',
            nonce: ainl_ajax.nonce,
            date_range: $('#date-range').val(),
            date_from: $('#date-from').val(),
            date_to: $('#date-to').val(),
            categories: $('input[name="categories[]"]:checked').map(function() {
                return $(this).val();
            }).get(),
            max_posts: $('input[name="max_posts"]').val()
        };
        
        const button = $('#preview-filtered-posts');
        const originalText = button.text();
        
        button.text('로딩 중...').prop('disabled', true);
        
        $.post(ainl_ajax.ajax_url, filterData, function(response) {
            if (response.success) {
                $('#filtered-posts-preview').html(response.data.html);
            } else {
                $('#filtered-posts-preview').html('<p class="error">미리보기를 로드할 수 없습니다: ' + response.data + '</p>');
            }
        }).fail(function() {
            $('#filtered-posts-preview').html('<p class="error">미리보기 로딩 중 오류가 발생했습니다.</p>');
        }).always(function() {
            button.text(originalText).prop('disabled', false);
        });
    }
    
    /**
     * 게시물 검색
     */
    function searchPosts() {
        const searchTerm = $('#post-search').val().trim();
        
        if (!searchTerm) {
            alert('검색어를 입력해주세요.');
            return;
        }
        
        const searchData = {
            action: 'ainl_search_posts',
            nonce: ainl_ajax.nonce,
            search: searchTerm
        };
        
        const button = $('#search-posts-btn');
        const originalText = button.text();
        
        button.text('검색 중...').prop('disabled', true);
        
        $.post(ainl_ajax.ajax_url, searchData, function(response) {
            if (response.success) {
                $('#available-posts-list').html(response.data.html);
                bindPostSelectionEvents();
            } else {
                $('#available-posts-list').html('<p class="error">검색 실패: ' + response.data + '</p>');
            }
        }).fail(function() {
            $('#available-posts-list').html('<p class="error">검색 중 오류가 발생했습니다.</p>');
        }).always(function() {
            button.text(originalText).prop('disabled', false);
        });
    }
    
    /**
     * 게시물 선택 이벤트 바인딩
     */
    function bindPostSelectionEvents() {
        // 게시물 선택 버튼
        $(document).off('click', '.select-post').on('click', '.select-post', function() {
            const $postItem = $(this).closest('.ainl-post-item');
            const postId = $postItem.data('post-id');
            const title = $postItem.find('.post-title').text();
            const excerpt = $postItem.find('.post-excerpt').text();
            const meta = $postItem.find('.post-meta').html();
            
            // 이미 선택된 게시물인지 확인
            if ($(`#selected-posts-list .selected-post[data-post-id="${postId}"]`).length > 0) {
                alert('이미 선택된 게시물입니다.');
                return;
            }
            
            // 선택된 게시물 목록에 추가
            const selectedPostHtml = `
                <div class="selected-post" data-post-id="${postId}">
                    <div class="post-info">
                        <h4 class="post-title">${title}</h4>
                        <p class="post-excerpt">${excerpt}</p>
                        <div class="post-meta">${meta}</div>
                    </div>
                    <button type="button" class="remove-post" title="제거">×</button>
                </div>
            `;
            
            $('#selected-posts-list').append(selectedPostHtml);
            updateSelectedPostsCount();
            
            // 선택 버튼 비활성화
            $(this).prop('disabled', true).text('선택됨');
        });
        
        bindPostRemovalEvents();
    }
    
    /**
     * 게시물 제거 이벤트 바인딩
     */
    function bindPostRemovalEvents() {
        $(document).off('click', '.remove-post').on('click', '.remove-post', function() {
            const $selectedPost = $(this).closest('.selected-post');
            const postId = $selectedPost.data('post-id');
            
            // 선택된 게시물 제거
            $selectedPost.remove();
            updateSelectedPostsCount();
            
            // 사용 가능한 게시물 목록에서 선택 버튼 다시 활성화
            $(`.ainl-post-item[data-post-id="${postId}"] .select-post`)
                .prop('disabled', false).text('선택');
        });
    }
    
    /**
     * AI 콘텐츠 생성
     */
    function generateAIContent() {
        const button = $('#generate-content');
        const originalText = button.text();
        
        button.text('AI 생성 중...').prop('disabled', true);
        
        const campaignData = collectCampaignData();
        
        // AI 옵션 추가
        campaignData.ai_style = $('#ai-style').val();
        campaignData.ai_length = $('#ai-length').val();
        campaignData.generate_title = $('#generate-title').is(':checked');
        
        const aiData = {
            action: 'ainl_generate_ai_content',
            nonce: ainl_ajax.nonce,
            campaign_data: campaignData
        };
        
        $.post(ainl_ajax.ajax_url, aiData, function(response) {
            if (response.success) {
                const data = response.data;
                
                // 생성된 콘텐츠를 에디터에 설정
                setEditorContent(data.content);
                
                // AI 제목이 생성된 경우 업데이트
                if (data.title) {
                    $('#email-subject').val(data.title);
                }
                
                // 성공 메시지 표시
                let message = data.message;
                if (data.ai_used) {
                    message = '🤖 ' + message;
                } else {
                    message = '📝 ' + message;
                }
                
                showAIMessage(message, data.ai_used ? 'success' : 'warning');
                
                // 미리보기 업데이트
                updateLivePreview();
                updateCampaignSummary();
                
            } else {
                showAIMessage('❌ AI 콘텐츠 생성 실패: ' + response.data, 'error');
            }
        }).fail(function() {
            showAIMessage('❌ AI 콘텐츠 생성 중 오류가 발생했습니다.', 'error');
        }).always(function() {
            button.text(originalText).prop('disabled', false);
        });
    }
    
    /**
     * AI 메시지 표시
     */
    function showAIMessage(message, type) {
        const messageHtml = `
            <div class="notice notice-${type} ai-generation-message is-dismissible">
                <p>${message}</p>
            </div>
        `;
        
        // 기존 메시지 제거
        $('.ai-generation-message').remove();
        
        // 새 메시지 추가
        $('.ainl-preview-actions').after(messageHtml);
        
        // 3초 후 자동 제거
        setTimeout(function() {
            $('.ai-generation-message').fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    /**
     * 에디터 토글
     */
    function toggleEditor() {
        const $preview = $('#newsletter-preview');
        const $editor = $('#newsletter-editor');
        const button = $('#toggle-editor');
        
        if ($editor.is(':visible')) {
            // 에디터 숨기고 미리보기 표시
            $editor.hide();
            $preview.show();
            button.text('✏️ 편집 모드');
            updateLivePreview();
        } else {
            // 미리보기 숨기고 에디터 표시
            $preview.hide();
            $editor.show();
            button.text('👁️ 미리보기 모드');
            
            // 에디터 포커스
            const editor = tinymce.get('newsletter_content');
            if (editor) {
                editor.focus();
            }
        }
    }
    
    /**
     * 현재 단계 유효성 검사
     */
    function validateCurrentStep() {
        const currentStep = $('.ainl-wizard-step.active').attr('id').replace('step-', '');
        return validateStep(getStepNumber(currentStep));
    }
    
    /**
     * 기본 정보 단계 유효성 검사
     */
    function validateBasicInfo() {
        const name = $('#campaign-name').val().trim();
        const subject = $('#email-subject').val().trim();
        
        if (!name) {
            alert('캠페인 이름을 입력해주세요.');
            $('#campaign-name').focus();
            return false;
        }
        
        if (!subject) {
            alert('이메일 제목을 입력해주세요.');
            $('#email-subject').focus();
            return false;
        }
        
        return true;
    }
    
    /**
     * 콘텐츠 선택 단계 유효성 검사
     */
    function validateContentSelection() {
        const activeTab = $('.ainl-tab-button.active').data('tab');
        
        if (activeTab === 'manual') {
            const selectedPosts = $('#selected-posts-list .selected-post').length;
            if (selectedPosts === 0) {
                alert('최소 1개 이상의 게시물을 선택해주세요.');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 디자인 선택 단계 유효성 검사
     */
    function validateDesignSelection() {
        const selectedTemplate = $('input[name="template_id"]:checked').length;
        
        if (selectedTemplate === 0) {
            alert('템플릿을 선택해주세요.');
            return false;
        }
        
        return true;
    }
    
    /**
     * 미리보기 단계 유효성 검사
     */
    function validatePreview() {
        const content = getEditorContent();
        
        if (!content || content.trim() === '') {
            alert('뉴스레터 콘텐츠를 생성하거나 입력해주세요.');
            return false;
        }
        
        return true;
    }
    
    /**
     * 발송 설정 단계 유효성 검사
     */
    function validateSendSettings() {
        const sendType = $('input[name="send_type"]:checked').val();
        
        if (sendType === 'scheduled') {
            const scheduledAt = $('#scheduled-at').val();
            if (!scheduledAt) {
                alert('예약 발송 시간을 설정해주세요.');
                $('#scheduled-at').focus();
                return false;
            }
            
            const scheduledDate = new Date(scheduledAt);
            const now = new Date();
            
            if (scheduledDate <= now) {
                alert('예약 시간은 현재 시간보다 이후여야 합니다.');
                $('#scheduled-at').focus();
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 단계 이동
     */
    function goToStep(stepNumber) {
        const totalSteps = 5;
        
        if (stepNumber < 1 || stepNumber > totalSteps) {
            return;
        }
        
        // 현재 단계 비활성화
        $('.ainl-wizard-step').removeClass('active');
        $('.ainl-step').removeClass('active completed');
        
        // 새 단계 활성화
        const stepNames = ['basic', 'content', 'design', 'preview', 'send'];
        const stepName = stepNames[stepNumber - 1];
        
        $(`#step-${stepName}`).addClass('active');
        $(`.ainl-step[data-step="${stepName}"]`).addClass('active');
        
        // 완료된 단계 표시
        for (let i = 1; i < stepNumber; i++) {
            $(`.ainl-step[data-step="${stepNames[i - 1]}"]`).addClass('completed');
        }
        
        // 진행률 업데이트
        const progress = (stepNumber / totalSteps) * 100;
        $('.ainl-progress-fill').css('width', progress + '%');
        
        // 네비게이션 버튼 업데이트
        updateNavigationButtons(stepNumber);
        
        // 특정 단계별 초기화
        if (stepNumber === 4) {
            // 미리보기 단계: 구독자 수 로드
            loadSubscriberCount();
        } else if (stepNumber === 5) {
            // 발송 단계: 요약 정보 업데이트
            updateCampaignSummary();
            updateTemplateSummary();
            updateSendTypeSummary();
        }
        
        currentStep = stepNumber;
    }
    
    /**
     * 네비게이션 버튼 업데이트
     */
    function updateNavigationButtons(stepNumber) {
        const $prevBtn = $('#prev-step');
        const $nextBtn = $('#next-step');
        const $saveBtn = $('#save-campaign');
        const $launchBtn = $('#launch-campaign');
        
        // 이전 버튼
        if (stepNumber === 1) {
            $prevBtn.hide();
        } else {
            $prevBtn.show();
        }
        
        // 다음/저장/발송 버튼
        if (stepNumber === 5) {
            $nextBtn.hide();
            $saveBtn.show();
            $launchBtn.show();
        } else {
            $nextBtn.show();
            $saveBtn.hide();
            $launchBtn.hide();
        }
    }
    
    /**
     * 구독자 수 로드
     */
    function loadSubscriberCount() {
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_get_subscriber_count',
            nonce: ainl_ajax.nonce
        }, function(response) {
            if (response.success) {
                const count = response.data.count;
                $('#subscriber-count').html(`
                    <div class="ainl-subscriber-info">
                        <div class="subscriber-count">
                            <span class="count-number">${count}</span>
                            <span class="count-label">명의 활성 구독자</span>
                        </div>
                        <p class="subscriber-note">
                            ${count > 0 ? '위 구독자들에게 뉴스레터가 발송됩니다.' : '활성 구독자가 없습니다. 구독자를 먼저 추가해주세요.'}
                        </p>
                    </div>
                `);
            } else {
                $('#subscriber-count').html('<p class="error">구독자 수를 불러올 수 없습니다.</p>');
            }
        });
    }
}); 