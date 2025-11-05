jQuery(function ($) {
    'use strict';

  const AJAX_URL = window.COOKIEJAR_WIZARD && COOKIEJAR_WIZARD.ajaxurl;
  const NONCE = window.COOKIEJAR_WIZARD && COOKIEJAR_WIZARD.nonce;
  const IS_PRO = !!(window.COOKIEJAR_WIZARD && COOKIEJAR_WIZARD.isPro);
  const I18N = (window.COOKIEJAR_WIZARD && COOKIEJAR_WIZARD.i18n) || {};
  const WP_PAGES = (window.COOKIEJAR_WIZARD && window.COOKIEJAR_WIZARD.pages) || [];
    
    let currentStep = 1;
    const totalSteps = 4;
  let isFinishing = false;
  let isSaving = false;
    
  if (!IS_PRO) $('body').addClass('cookiejar-tier-basic');
    initWizard();
  initQuickReview();
  
    function initWizard() {
    const $status = $('#wizard-status');
    if ($status.length) {
      $status.attr({ role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true' });
    }

        updateProgress();
        updateNavigation();
        
    $('#wizard-next').on('click.cookiejar-wizard', onNextStep);
    $('#wizard-prev').on('click.cookiejar-wizard', onPrevStep);
    $('#wizard-finish').on('click.cookiejar-wizard', finishWizard);
    $('#wizard-skip').on('click.cookiejar-wizard', skipWizard);

    $('#cookiejar-wizard-form').on('change.cookiejar-wizard', validateCurrentStep);

    $('.cookiejar-language-grid').on('change.cookiejar-wizard', 'input.wizard-language-checkbox', function () {
      if (IS_PRO) return;
      const $all = $('input.wizard-language-checkbox');
      const checked = $all.filter(':checked');
      if (checked.length > 2) {
        $(this).prop('checked', false);
        showStatus(I18N.basicPlanTwoLanguages, 'error');
      } else {
        hideStatus();
      }
    });

    $('#cookiejar-wizard-form').on('keydown.cookiejar-wizard', function (e) {
      if (e.key === 'Enter' && !$(e.target).is('textarea')) {
        e.preventDefault();
        if (currentStep < totalSteps) onNextStep();
        else finishWizard();
      }
    });

    const $firstLang = $('input.wizard-language-checkbox').first();
    if ($firstLang.length) $firstLang.trigger('focus');

    applyTierRestrictions();
    initTierDefaults();
  }

  function onNextStep() {
    if (!validateCurrentStep()) return;
    setButtonsDisabled(true);

    saveCurrentStep({ silent: true }).always(function () {
            if (currentStep < totalSteps) {
                currentStep++;
                showStep(currentStep);
                updateProgress();
                updateNavigation();
                manageFocus();
            }
      setButtonsDisabled(false);
    });
    }
    
  function onPrevStep() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
            updateProgress();
            updateNavigation();
            manageFocus();
        }
    }
    
    function showStep(step) {
    $('.wizard-step').attr('aria-hidden', 'true').removeClass('active').hide();
    $('#step-' + step).attr('aria-hidden', 'false').addClass('active').show();
        
    const $steps = $('.progress-steps .step').removeClass('active completed').removeAttr('aria-current');
        for (let i = 1; i <= step; i++) {
      const $s = $steps.filter('[data-step="' + i + '"]');
      $s.addClass(i < step ? 'completed' : 'active');
      if (i === step) $s.attr('aria-current', 'step');
        }
    }
    
    function updateProgress() {
        const percentage = (currentStep / totalSteps) * 100;
        $('#wizard-progress').css('width', percentage + '%');
    }
    
    function updateNavigation() {
        $('#wizard-prev').toggle(currentStep > 1);
        $('#wizard-next').toggle(currentStep < totalSteps);
        $('#wizard-finish').toggle(currentStep === totalSteps);
    }
    
    function validateCurrentStep() {
        let isValid = true;
        let errorMessage = '';
    switch (currentStep) {
      case 1: {
        const selected = $('input.wizard-language-checkbox:checked').length;
        if (selected < 1) {
                    isValid = false;
          errorMessage = I18N.selectAtLeastOneLanguage;
        } else if (!IS_PRO && selected > 2) {
                        isValid = false;
          errorMessage = I18N.basicPlanTwoLanguages;
                }
                break;
      }
      case 2: {
                const checkedCategories = $('input[name="categories[]"]:checked').length;
                if (checkedCategories === 0) {
                    isValid = false;
          errorMessage = I18N.selectAtLeastOneCategory;
                }
                break;
      }
      case 3: {
        // Privacy Policy Page may be left blank and updated later
        // NO ERROR if blank - allow user to proceed without selecting a policy page
        // const policyUrl = ($('#wizard-policy-page').val() || '').trim();
        // if (!policyUrl) {
        //   isValid = false;
        //   errorMessage = I18N.enterValidPolicyUrl;
        // }
                break;
      }
      case 4:
                break;
        }
    if (!isValid) showStatus(errorMessage, 'error');
    else hideStatus();
        return isValid;
    }
    
    function isValidUrl(string) {
        try {
      const u = new URL(string);
      return u.protocol === 'http:' || u.protocol === 'https:';
        } catch (_) {
            return false;
        }
    }
    
  function collectLanguages() {
    const langs = [];
    $('input.wizard-language-checkbox:checked').each(function () {
      langs.push($(this).val());
    });
    if (langs.length === 0) langs.push('en_US');
    return langs;
  }

  function collectCategories() {
    const cats = [];
    $('input[name="categories[]"]:checked').each(function () {
      cats.push($(this).val());
    });
    if (!cats.includes('necessary')) cats.unshift('necessary');
    return cats;
  }

  function getStepData(stepNum) {
    const s = stepNum || currentStep;
    switch (s) {
      case 1: return { languages: collectLanguages() };
      case 2: return { categories: collectCategories() };
      case 3: return {
        color: $('#wizard-color').val(),
        bg: $('#wizard-bg').val(),
        policy_url: ($('#wizard-policy-page').val() || '').trim(),
        prompt: ($('#wizard-prompt').val() || '').trim(),
      };
      case 4: return {
        geo_auto: $('input[name="geo_auto"]:checked').length > 0,
        logging_mode: $('#wizard-logging-mode').val(),
        gdpr_mode: $('input[name="gdpr_mode"]:checked').length > 0,
        ccpa_mode: $('input[name="ccpa_mode"]:checked').length > 0,
      };
      default: return {};
    }
  }

  function extractMessage(resp, fallback) {
    if (!resp) return fallback;
    if (typeof resp.data === 'string') return resp.data;
    if (resp.data && typeof resp.data.message === 'string') return resp.data.message;
    if (resp.message) return resp.message;
    return fallback;
  }

  function saveCurrentStep(opts) {
    const options = Object.assign({ silent: false }, opts);
    if (!AJAX_URL || !NONCE) return $.Deferred().resolve().promise();
    if (isSaving) return $.Deferred().resolve().promise();

    isSaving = true;

    const formData = new FormData();
    formData.append('action', 'cookiejar_save_wizard');
    formData.append('nonce', NONCE);
    formData.append('step', String(currentStep));
    const stepData = getStepData(currentStep);
        formData.append('data', JSON.stringify(stepData));
        
    return $.ajax({
      url: AJAX_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
      cache: false
    }).done(function (response) {
      if (!response || !response.success) {
        if (!options.silent) {
          const msg = extractMessage(response, I18N.unknownError);
          showStatus(I18N.saveStepFailed + ': ' + msg, 'error');
        }
                } else {
        hideStatus();
      }
    }).fail(function () {
      if (!options.silent) {
        showStatus(I18N.saveStepFailedTryAgain, 'error');
      }
    }).always(function () {
      isSaving = false;
        });
    }
    
    function finishWizard() {
    if (isFinishing) return;
        if (!validateCurrentStep()) return;
        
    isFinishing = true;
    setButtonsDisabled(true);
    showStatus(I18N.completeSetup, 'loading');

    saveCurrentStep({ silent: true }).always(function () {
      const wizardData = {
        action: 'cookiejar_complete_wizard',
        nonce: NONCE,
        languages: collectLanguages(),
        categories: collectCategories(),
        banner_color: $('#wizard-color').val(),
        banner_bg: $('#wizard-bg').val(),
        policy_url: ($('#wizard-policy-page').val() || '').trim(),
        prompt_text: ($('#wizard-prompt').val() || '').trim(),
        geo_auto: $('input[name="geo_auto"]:checked').length > 0 ? '1' : '0',
        logging_mode: $('#wizard-logging-mode').val(),
        gdpr_mode: $('input[name="gdpr_mode"]:checked').length > 0 ? '1' : '0',
        ccpa_mode: $('input[name="ccpa_mode"]:checked').length > 0 ? '1' : '0'
      };

      $.ajax({
        url: AJAX_URL,
        type: 'POST',
        data: wizardData,
        cache: false
      }).done(function (response) {
        if (response && response.success && response.data) {
          const msg = extractMessage(response, I18N.completeSetup);
          showStatus(msg, 'success');
          setTimeout(function () {
            window.location.href = response.data.redirect;
          }, 1200);
        } else {
          const msg = extractMessage(response, I18N.unknownError);
          showStatus(I18N.completeSetupFailed + ': ' + msg, 'error');
          setButtonsDisabled(false);
          isFinishing = false;
        }
      }).fail(function () {
        showStatus(I18N.completeSetupFailedTryAgain, 'error');
        setButtonsDisabled(false);
        isFinishing = false;
      });
    });
  }

  function skipWizard() {
    const confirmMessage = I18N.skipConfirm || 'Are you sure you want to skip the setup wizard? Default settings will be used.';
    if (!confirm(confirmMessage)) return;

    const loadingMessage = I18N.skippingWizard || 'Skipping wizard...';
    showStatus(loadingMessage, 'loading');

    $.ajax({
      url: AJAX_URL,
      type: 'POST',
      data: {
        action: 'cookiejar_skip_wizard',
        nonce: NONCE
      },
      cache: false
    }).done(function (response) {
      console.log('CookieJar: Skip wizard response:', response);
      if (response && response.success) {
        const msg = response.data && response.data.message ? response.data.message : (I18N.skippingWizard || 'Skipping wizard...');
        showStatus(msg, 'success');
        setTimeout(function () {
          // Use the redirect URL from response or fallback to control panel
          const redirectUrl = (response.data && response.data.redirect) ? response.data.redirect : 'admin.php?page=cookiejar-control';
          console.log('CookieJar: Redirecting to:', redirectUrl);
          window.location.href = redirectUrl;
        }, 1000);
      } else {
        const unknownError = I18N.unknownError || 'Unknown error';
        const msg = extractMessage(response, unknownError);
        const skipFailed = I18N.skipWizardFailed || 'Failed to skip wizard';
        showStatus(skipFailed + ': ' + msg, 'error');
      }
    }).fail(function (xhr, status, error) {
      console.error('CookieJar: Skip wizard AJAX failed:', {xhr, status, error});
      const skipFailed = I18N.skipWizardFailed || 'Failed to skip wizard';
      const tryAgain = I18N.completeSetupFailedTryAgain || 'Please try again';
      showStatus(skipFailed + ' ' + tryAgain, 'error');
    });
    }
    
    function showStatus(message, type) {
        const $status = $('#wizard-status');
        $status.removeClass('success error loading')
               .addClass(type)
      .attr('role', type === 'error' ? 'alert' : 'status')
               .text(message)
               .show();
        
        announceToScreenReader(message);
    }
    
    function hideStatus() {
    $('#wizard-status')
      .hide()
      .text('')
      .removeClass('success error loading')
      .attr('role', 'status');
    }
    
    function announceToScreenReader(message) {
        let $liveRegion = $('#wizard-live-region');
        if ($liveRegion.length === 0) {
      $liveRegion = $('<div id="wizard-live-region" aria-live="polite" aria-atomic="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"></div>');
            $('body').append($liveRegion);
        }
        $liveRegion.text(message);
    }
    
    function manageFocus() {
    const $currentStep = $('#step-' + currentStep);
    const $firstInput = $currentStep.find('input, textarea, select, button').filter(':visible').first();
    if ($firstInput.length) {
      setTimeout(function () { $firstInput.trigger('focus'); }, 100);
    }
  }

  function setButtonsDisabled(disabled) {
    $('#wizard-next, #wizard-prev, #wizard-finish, #wizard-skip').prop('disabled', !!disabled);
  }

  function applyTierRestrictions() {
    if (IS_PRO) return;
    const $proCats = $('input[name="categories[]"][value="chatbot"], input[name="categories[]"][value="donotsell"]');
    $proCats.prop('disabled', true).closest('label').addClass('pro-only').attr('title', 'Pro feature');
    const $logSelect = $('#wizard-logging-mode');
    $logSelect.find('option[value="live"]').prop('disabled', true);
    $logSelect.addClass('pro-only').attr('title', 'Pro feature');
  }

  function initTierDefaults() {
    if (IS_PRO) return;
    const $logSelect = $('#wizard-logging-mode');
    if ($logSelect.val() !== 'cached') $logSelect.val('cached');
  }

  // Color input (palette and text) sync for wizard step-by-step
  function normalizeHex(val) {
    val = val.trim().replace(/[^#0-9a-fA-F]/g, '');
    if (val && val.charAt(0) !== "#") val = "#" + val;
    if (val.length === 4 || val.length === 7) return val;
    return "";
  }

  function setupColorSyncWizard(paletteId, textId) {
    const $input = $('#' + paletteId);
    const $text = $('#' + textId);

    // Palette → text
    $input.on('input change', function () {
      let val = normalizeHex($(this).val());
      if (!val) return;
      $text.val(val);
    });

    // Text → palette
    $text.on('input change blur', function () {
      let val = normalizeHex($(this).val());
      if (!val) return;
      $input.val(val);
    });
  }

  // Run for both colors in step-by-step wizard
  setupColorSyncWizard('wizard-color', 'wizard-color-text');
  setupColorSyncWizard('wizard-bg', 'wizard-bg-text');

  // Color Picker Handlers for step-by-step wizard only
  function initColorPickerHandlers() {
    // Color synchronization for step-by-step wizard
    $('#wizard-color').on('input change', function () {
      const val = $(this).val();
      $('#wizard-color-text').val(val);
    });
    $('#wizard-color-text').on('input change blur', function () {
      const val = normalizeHex($(this).val());
      if (val) {
        $('#wizard-color').val(val);
      }
    });
    $('#wizard-bg').on('input change', function () {
      const val = $(this).val();
      $('#wizard-bg-text').val(val);
    });
    $('#wizard-bg-text').on('input change blur', function () {
      const val = normalizeHex($(this).val());
      if (val) {
        $('#wizard-bg').val(val);
      }
    });
  }

  function initQuickReview() {
    initColorPickerHandlers();

  const $details = $('#cookiejar-review-toggle');
    const $reviewList = $('#cookiejar-review-list');
    if (!$details.length || !$reviewList.length) return;
    const $tip = $('#cookiejar-quick-tip');

    $details.on('toggle', function () {
      const open = this.open;
      $tip.toggle(open);
      setEditable(open);
    });

    function setEditable(on) {
      $('#cookiejar-review-list .cookiejar-chip').toggleClass('is-editable', !!on);
    }


    $(document).on('click', '.cookiejar-chip.is-editable', onChipToggle);
    $(document).on('keydown', '.cookiejar-chip.is-editable', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onChipToggle.call(this, e); }
    });

    // Toggle dot handlers for boolean settings
    $(document).on('click', '.cookiejar-dot', onToggleDot);
    $(document).on('keydown', '.cookiejar-dot', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onToggleDot.call(this, e); }
    });

    function onToggleDot() {
      const $dot = $(this);
      const isOn = $dot.hasClass('on');
      
      // Prevent CCPA toggle for basic users
      if ($dot.attr('id') === 'cj-ccpa' && !IS_PRO) {
        // Show error message
        const $tip = $('#cookiejar-quick-tip');
        if ($tip.length) {
          $tip.text('This feature is not available in this version.').show();
          setTimeout(() => $tip.fadeOut(), 3000);
        }
        return;
      }
      
      // Toggle the state
      $dot.toggleClass('on off');
      
      // Update ARIA attributes
      const newState = $dot.hasClass('on');
      $dot.attr('aria-checked', newState ? 'true' : 'false');
      
      // Update title
      const title = newState 
        ? (I18N.enabledClickToDisable || 'Enabled (click to disable)')
        : (I18N.disabledClickToEnable || 'Disabled (click to enable)');
      $dot.attr('title', title);
    }

    function onChipToggle() {
      const type = $(this).data('type');
      const code = $(this).data('code');
      if (!type || !code) return;

      if (type === 'lang' || type === 'cat') {
        if (type === 'cat' && code === 'necessary') {
          $(this).addClass('is-active').attr('aria-pressed', 'true').attr('title', I18N.enabledClickToDisable || 'Enabled (click to disable)');
          return;
        }
        
        // Language limit enforcement for basic plan
        if (type === 'lang' && !IS_PRO) {
      const $allLangChips = $('#cookiejar-langs .cookiejar-chip[data-type="lang"]');
          const activeLangChips = $allLangChips.filter('.is-active');
          const currentlyActive = $(this).hasClass('is-active');
          
          // If trying to activate and already at limit, prevent it
          if (!currentlyActive && activeLangChips.length >= 2) {
            // Show error message
            const $tip = $('#cookiejar-quick-tip');
            if ($tip.length) {
              $tip.text(I18N.basicPlanTwoLanguages || 'Basic plan is limited to 2 languages.').show();
              setTimeout(() => $tip.fadeOut(), 3000);
            }
            return;
          }
        }
        
        const active = $(this).toggleClass('is-active').hasClass('is-active');
        $(this).attr('aria-pressed', active ? 'true' : 'false')
               .attr('title', active ? (I18N.enabledClickToDisable || 'Enabled (click to disable)') : (I18N.disabledClickToEnable || 'Disabled (click to enable)'));
      } else if (type === 'log') {
        // Prevent basic users from selecting live logging
        if (code === 'live' && !IS_PRO) {
          // Show error message
          const $tip = $('#cookiejar-quick-tip');
          if ($tip.length) {
            $tip.text('This feature is not available in this version.').show();
            setTimeout(() => $tip.fadeOut(), 3000);
          }
          return;
        }
        
    $('#cookiejar-log-cached, #cookiejar-log-live').removeClass('is-active').attr('aria-pressed', 'false');
        $(this).addClass('is-active').attr('aria-pressed', 'true');
      }
    }

    function collectQuickData() {
      const langs = [];
      $('#cookiejar-langs .cookiejar-chip[data-type="lang"]').each(function () {
        if ($(this).hasClass('is-active')) langs.push($(this).data('code'));
      });
      if (!langs.length) langs.push('en_US');

      const cats = [];
      $('#cookiejar-cats .cookiejar-chip[data-type="cat"]').each(function () {
        if ($(this).hasClass('is-active')) cats.push($(this).data('code'));
      });
      if (!cats.includes('necessary')) cats.unshift('necessary');

      const color = $('#cookiejar-color-trigger').attr('data-current-color') || '#008ed6';
      const bg    = $('#cookiejar-bg-trigger').attr('data-current-color') || '#ffffff';
      const policyUrl = $('#cookiejar-policy-page').val() || '';
      const prompt = $('#cookiejar-prompt-trigger').attr('data-current-text') || '';

      const logging = $('#cookiejar-log-live').hasClass('is-active') ? 'live' : 'cached';
      const geo  = $('#cookiejar-geo').hasClass('on') ? '1' : '0';
      const gdpr = $('#cookiejar-gdpr').hasClass('on') ? '1' : '0';
      const ccpa = (IS_PRO && $('#cookiejar-ccpa').hasClass('on')) ? '1' : '0';

      return {
        languages: langs,
        categories: cats,
        banner_color: color,
        banner_bg: bg,
        policy_url: policyUrl,
        prompt_text: prompt,
        logging_mode: logging,
        geo_auto: geo,
        gdpr_mode: gdpr,
        ccpa_mode: ccpa
      };
    }

    $('#cookiejar-wizard-update').on('click', function (e) {
      e.preventDefault();
      if (!confirm(I18N.confirmUpdate || 'Are you sure you\'d like to save changes?')) return;

      const payload = collectQuickData();
      const $btn = $(this);
      const label = $btn.text();
      $btn.prop('disabled', true).text(I18N.updating || 'Updating...');

      $.ajax({
        url: AJAX_URL,
        type: 'POST',
        data: Object.assign({
          action: 'cookiejar_complete_wizard',
          nonce: NONCE
        }, payload)
      }).done(function (resp) {
        if (resp && resp.success && resp.data) {
          // Show success message and refresh the page
          $btn.text(I18N.settingsUpdated || 'Settings Updated!').removeClass('button-primary').addClass('button-secondary');
          setTimeout(function() {
            window.location.reload();
          }, 1500);
        } else {
          alert(I18N.unknownError || 'Unknown error');
          $btn.prop('disabled', false).text(label);
        }
      }).fail(function () {
        alert(I18N.unknownError || 'Unknown error');
        $btn.prop('disabled', false).text(label);
      });
    });

    $('#cookiejar-wizard-reset').on('click', function (e) {
      e.preventDefault();
      
      // Show reset modal
      $('#cookiejar-reset-modal').show();
    });

    // Reset modal close button
    $('#cookiejar-reset-modal-close').on('click', function() {
      $('#cookiejar-reset-modal').hide();
    });

    // Reset modal button handlers
    $('#cookiejar-reset-confirm').on('click', function() {
      $('#cookiejar-reset-modal').hide();
      
      const $btn = $(this); // Use the clicked button itself
      const label = $btn.text();
      $btn.prop('disabled', true).text(I18N.working || 'Working...');
      
      // Confirm Reset - apply defaults and refresh page
      console.log('CookieJar: Starting apply defaults AJAX request');
      $.post(AJAX_URL, { action: 'cookiejar_apply_defaults', nonce: NONCE })
        .done(function (resp) {
          console.log('CookieJar: Apply defaults response:', resp);
          if (resp && resp.success) {
            // Show success message and refresh page
            alert(I18N.defaultsApplied || 'Defaults applied successfully! Refreshing page...');
            console.log('CookieJar: About to reload page');
            window.location.reload();
          } else { 
            console.error('CookieJar: Apply defaults failed:', resp);
            alert(I18N.unknownError || 'Unknown error'); 
            $btn.prop('disabled', false).text(label); 
          }
        })
        .fail(function (xhr, status, error) { 
          console.error('CookieJar: Apply defaults AJAX failed:', {xhr, status, error});
          alert(I18N.unknownError || 'Unknown error'); 
          $btn.prop('disabled', false).text(label); 
        });
    });

    $('#cookiejar-reset-restart').on('click', function() {
      $('#cookiejar-reset-modal').hide();
      
      const $btn = $(this); // Use the clicked button itself
      const label = $btn.text();
      $btn.prop('disabled', true).text(I18N.working || 'Working...');
      
      // Confirm and Restart Wizard - clear settings and restart wizard
      console.log('CookieJar: Starting reset wizard AJAX request');
      console.log('CookieJar: AJAX_URL:', AJAX_URL);
      console.log('CookieJar: NONCE:', NONCE);
      
      $.post(AJAX_URL, { action: 'cookiejar_reset_wizard', nonce: NONCE, clear_settings: 1 })
        .done(function (resp) {
          console.log('CookieJar: Reset wizard response:', resp);
          if (resp && resp.success) {
            // Show success message and refresh page
            alert(I18N.resetSuccess || 'Settings reset successfully! Restarting wizard...');
            console.log('CookieJar: About to reload page');
            window.location.reload();
          } else { 
            console.error('CookieJar: Reset wizard failed:', resp);
            alert(I18N.unknownError || 'Unknown error'); 
            $btn.prop('disabled', false).text(label); 
          }
        })
        .fail(function (xhr, status, error) { 
          console.error('CookieJar: Reset wizard AJAX failed:', {xhr, status, error});
          alert(I18N.unknownError || 'Unknown error'); 
          $btn.prop('disabled', false).text(label); 
        });
    });

    $('#cookiejar-reset-exit').on('click', function() {
      // Exit without update - just close the modal
      $('#cookiejar-reset-modal').hide();
    });

    // Policy modal functionality is now handled by cookiejar-policy-module.js
    // No duplicate handlers needed here

    // Color Picker Modal functionality
    let currentColorType = null;
    let currentColorElement = null;

    // Handle color trigger clicks
    $(document).on('click', '.cookiejar-color-trigger', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      currentColorType = $(this).data('color-type');
      currentColorElement = $(this);
      const currentColor = $(this).data('current-color');
      
      // Update modal title and label
      const title = currentColorType === 'primary' ? 'Edit Primary Color' : 'Edit Background Color';
      const label = currentColorType === 'primary' ? 'Primary Color' : 'Background Color';
      
      $('#cookiejar-color-modal-title').text(title);
      $('#cookiejar-color-modal-label').text(label);
      
      // Set current color in modal
      $('#cookiejar-color-modal-picker').val(currentColor);
      $('#cookiejar-color-modal-text').val(currentColor);
      
      // Show modal
      $('#cookiejar-color-modal').show();
      
      // Focus on color picker
      setTimeout(() => {
        $('#cookiejar-color-modal-picker').focus();
      }, 100);
    });

    // Handle keyboard navigation for color triggers
    $(document).on('keydown', '.cookiejar-color-trigger', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        $(this).trigger('click');
      }
    });

    // Modal close handlers
    $('#cookiejar-color-modal-close, #cookiejar-color-modal-cancel').on('click', function() {
      $('#cookiejar-color-modal').hide();
      currentColorType = null;
      currentColorElement = null;
    });

    // Close modal on backdrop click
    $('#cookiejar-color-modal').on('click', function(e) {
      if (e.target === this) {
        $(this).hide();
        currentColorType = null;
        currentColorElement = null;
      }
    });

    // Close modal on Escape key
    $(document).on('keydown', function(e) {
      if (e.key === 'Escape' && $('#cookiejar-color-modal').is(':visible')) {
        $('#cookiejar-color-modal').hide();
        currentColorType = null;
        currentColorElement = null;
      }
    });

    // Color synchronization in modal
    $('#cookiejar-color-modal-picker').on('input change', function() {
      const val = normalizeHex($(this).val());
      if (val) {
        $('#cookiejar-color-modal-text').val(val);
      }
    });

    $('#cookiejar-color-modal-text').on('input change blur', function() {
      const val = normalizeHex($(this).val());
      if (val) {
        $('#cookiejar-color-modal-picker').val(val);
      }
    });

    // Save color changes
    $('#cookiejar-color-modal-save').on('click', function() {
      if (!currentColorType || !currentColorElement) return;
      
      const newColor = normalizeHex($('#cookiejar-color-modal-text').val());
      if (!newColor) return;
      
      // Update the color swatch display
      currentColorElement.find('.cookiejar-color-dot').css('background', newColor);
      currentColorElement.find('code').text(newColor);
      currentColorElement.attr('data-current-color', newColor);
      
      // Close modal
      $('#cookiejar-color-modal').hide();
      currentColorType = null;
      currentColorElement = null;
    });

    // Enter key to save in modal
    $('#cookiejar-color-modal-text').on('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        $('#cookiejar-color-modal-save').trigger('click');
      }
    });

    // Privacy Policy Modal functionality
    let currentPolicyElement = null;

    // Handle policy trigger clicks
    $(document).on('click', '.cookiejar-policy-trigger', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      currentPolicyElement = $(this);
      const currentUrl = $(this).data('current-url');
      
      // Set current selection in modal
      $('#cookiejar-policy-modal-select').val(currentUrl || '');
      
      // Show modal
      $('#cookiejar-policy-modal').show();
      
      // Focus on select
      setTimeout(() => {
        $('#cookiejar-policy-modal-select').focus();
      }, 100);
    });

    // Handle keyboard navigation for policy triggers
    $(document).on('keydown', '.cookiejar-policy-trigger', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        $(this).trigger('click');
      }
    });

    // Policy modal close handlers
    $('#cookiejar-policy-modal-close, #cookiejar-policy-modal-cancel').on('click', function() {
      $('#cookiejar-policy-modal').hide();
      currentPolicyElement = null;
    });

    // Close policy modal on backdrop click
    $('#cookiejar-policy-modal').on('click', function(e) {
      if (e.target === this) {
        $(this).hide();
        currentPolicyElement = null;
      }
    });

    // Save policy changes
    $('#cookiejar-policy-modal-save').on('click', function() {
      if (!currentPolicyElement) return;
      
      const newUrl = $('#cookiejar-policy-modal-select').val();
      const displayText = newUrl || '—';
      
      // Update the policy trigger display
      currentPolicyElement.find('.cookiejar-policy-display').text(displayText);
      currentPolicyElement.attr('data-current-url', newUrl);
      
      // Close modal
      $('#cookiejar-policy-modal').hide();
      currentPolicyElement = null;
    });

    // Prompt Text Modal functionality
    let currentPromptElement = null;

    // Handle prompt trigger clicks
    $(document).on('click', '.cookiejar-prompt-trigger', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      currentPromptElement = $(this);
      const currentText = $(this).data('current-text');
      
      // Set current text in modal
      $('#cookiejar-prompt-modal-textarea').val(currentText || '');
      
      // Show modal
      $('#cookiejar-prompt-modal').show();
      
      // Focus on textarea
      setTimeout(() => {
        $('#cookiejar-prompt-modal-textarea').focus();
      }, 100);
    });

    // Handle keyboard navigation for prompt triggers
    $(document).on('keydown', '.cookiejar-prompt-trigger', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        $(this).trigger('click');
      }
    });

    // Prompt modal close handlers
    $('#cookiejar-prompt-modal-close, #cookiejar-prompt-modal-cancel').on('click', function() {
      $('#cookiejar-prompt-modal').hide();
      currentPromptElement = null;
    });

    // Close prompt modal on backdrop click
    $('#cookiejar-prompt-modal').on('click', function(e) {
      if (e.target === this) {
        $(this).hide();
        currentPromptElement = null;
      }
    });

    // Save prompt changes
    $('#cookiejar-prompt-modal-save').on('click', function() {
      if (!currentPromptElement) return;
      
      const newText = $('#cookiejar-prompt-modal-textarea').val().trim();
      const displayText = newText || '—';
      
      // Update the prompt trigger display
      currentPromptElement.find('.cookiejar-prompt-display').text(displayText);
      currentPromptElement.attr('data-current-text', newText);
      
      // Close modal
      $('#cookiejar-prompt-modal').hide();
      currentPromptElement = null;
    });

    // Escape key to close any modal
    $(document).on('keydown', function(e) {
      if (e.key === 'Escape') {
        if ($('#cookiejar-color-modal').is(':visible')) {
          $('#cookiejar-color-modal').hide();
          currentColorType = null;
          currentColorElement = null;
        } else if ($('#cookiejar-policy-modal').is(':visible')) {
          $('#cookiejar-policy-modal').hide();
          currentPolicyElement = null;
        } else if ($('#cookiejar-prompt-modal').is(':visible')) {
          $('#cookiejar-prompt-modal').hide();
          currentPromptElement = null;
        } else if ($('#cookiejar-reset-modal').is(':visible')) {
          $('#cookiejar-reset-modal').hide();
        } else if ($('#wizard-policy-modal').is(':visible')) {
          $('#wizard-policy-modal').hide();
        }
      }
    });
  }
});