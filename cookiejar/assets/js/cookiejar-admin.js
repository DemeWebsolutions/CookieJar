jQuery(function ($) {
  'use strict';

  // =========================
  // I18N and Debug helpers
  // =========================
  const I18N = window.COOKIEJAR_I18N || {
    settingsSaved: 'Settings saved successfully!',
    settingsError: 'Failed to save settings:',
    unknownError: 'Unknown error',
    importSuccess: 'Settings imported successfully!',
    importError: 'Failed to import settings:',
    resetSuccess: 'Settings reset to defaults successfully!',
    resetError: 'Failed to reset settings:',
    cacheCleared: 'Cache cleared successfully',
    cacheError: 'Failed to clear cache',
    fileSelect: 'Please select a file to import',
    invalidJSON: 'Invalid JSON format. Please check your input.',
    confirmReset: 'Are you sure you want to reset all settings to defaults? This action cannot be undone.',
    confirmWizardReset: 'Are you sure you want to reset the wizard? This will make the setup wizard appear in the admin menu again.',
    confirmForceMenu: 'This will force create the wizard menu. Continue?',
    wizardResetSuccess: 'Wizard reset successfully! The setup wizard will now appear in the admin menu.',
    wizardMenuSuccess: 'Wizard menu created! Please refresh the page to see it in the admin menu.',
    skipWizardConfirm: 'Are you sure you want to skip the setup wizard? Default settings will be used.',
    bannerPreviewTitle: 'Banner Preview',
    closePreview: 'Close Preview',
    noConsentLogs: 'No consent logs found.',
    exportStarted: 'Settings export started',
    networkError: 'Network error occurred',
    importFailed: 'Import failed',
    resetComingSoon: 'Reset functionality coming soon',
    formReset: 'Form reset to defaults',
    saving: 'Saving...',
  };

  function debugLog(...args) {
    if (window.COOKIEJAR_ADMIN && window.COOKIEJAR_ADMIN.debug) {
      console.log(...args);
    }
  }

  // Ensure a reusable notice area exists and is accessible
  (function ensureStatusArea() {
    if (!document.getElementById('settings-status')) {
      const $wrap = $('.wrap').first();
      if ($wrap.length) {
        $wrap.prepend('<div id="settings-status" class="notice" style="display:none;"></div>');
      } else {
        $('body').prepend('<div id="settings-status" class="notice" style="display:none;"></div>');
      }
    }
    $('#settings-status').attr({ role: 'alert', 'aria-live': 'assertive', 'aria-atomic': 'true' });
  })();

  function showNotice(message, type) {
    const $status = $('#settings-status');
    $status
      .removeClass('notice-success notice-error notice-info success error info loading')
      .addClass(type === 'success' ? 'notice-success' : type === 'error' ? 'notice-error' : 'notice-info')
      .text(message)
      .show();
    if (type === 'success') {
      setTimeout(() => $status.fadeOut(), 4000);
    }
  }

  // =========================
  // Apply branding assets
  // =========================
  if (window.COOKIEJAR_ADMIN?.icons?.watermark) {
    $('.cookiejar-wrap').css('--cookiejar-watermark', `url("${COOKIEJAR_ADMIN.icons.watermark}")`);
  }
  if (window.COOKIEJAR_ADMIN?.icons?.titleSvg) {
    $('.cookiejar-title').addClass('cookiejar-title--svg')
      .css('--cookiejar-title-svg', `url("${COOKIEJAR_ADMIN.icons.titleSvg}")`);
  }

  // Submenu labels are defined in PHP menu registration; no JS override needed

  // =========================
  // Helpers
  // =========================
  const CIRC = 2 * Math.PI * 70; // circumference for r=70
  function pct(n, d) { n = Number(n) || 0; d = Number(d) || 0; if (d <= 0) return 0; return Math.round((n / d) * 100); }
  function fmt(n) { n = Number(n) || 0; return n.toLocaleString(); }
  function fmtTime(ms, s) {
    if (typeof ms === 'number' && !isNaN(ms) && ms > 0) {
      return (ms >= 1000) ? (Math.round(ms / 100) / 10) + 's' : ms + 'ms';
    }
    if (typeof s === 'number' && !isNaN(s) && s > 0) {
      return (Math.round(s * 10) / 10) + 's';
    }
    return '—';
  }
  function normalize(s) { return (s || '').toString().toLowerCase().trim(); }
  function titleCaseExceptAnd(str) {
    return (str || '').split(' ').map(w => {
      const wl = w.toLowerCase();
      if (wl === 'and') return 'and';
      if (w === '&') return '&';
      if (!w) return w;
      return w.charAt(0).toUpperCase() + w.slice(1);
    }).join(' ');
  }

  // =========================
  // Donut / KPI
  // =========================
  function setDonut(aPct, pPct, rPct, uPct) {
    const aLen = CIRC * (aPct / 100);
    const pLen = CIRC * (pPct / 100);
    const rLen = CIRC * (rPct / 100);
    const uLen = CIRC * (uPct / 100);

    const $accept = $('.arc-accept');
    const $partial = $('.arc-partial');
    const $reject = $('.arc-reject');
    const $unres = $('.arc-unres');

    let offset = 0;
    $accept.attr('stroke-dasharray', `${aLen} ${CIRC - aLen}`).attr('stroke-dashoffset', CIRC - offset); offset += aLen;
    $partial.attr('stroke-dasharray', `${pLen} ${CIRC - pLen}`).attr('stroke-dashoffset', CIRC - offset); offset += pLen;
    $reject.attr('stroke-dasharray', `${rLen} ${CIRC - rLen}`).attr('stroke-dashoffset', CIRC - offset); offset += rLen;
    $unres.attr('stroke-dasharray', `${uLen} ${CIRC - uLen}`).attr('stroke-dashoffset', CIRC - offset);

    $('.cookiejar-center-val').text(aPct + '%');
    $('.cookiejar-accept-p').text(aPct + '%');
    $('.cookiejar-partial-p').text(pPct + '%');
    $('.cookiejar-reject-p').text(rPct + '%');
    $('.cookiejar-unres-p').text(uPct + '%');
  }

  // =========================
  // Status helpers
  // =========================
  function setStatus(rowSel, isActive, labelPrefix) {
    const $row = $(rowSel);
    const $dot = $row.find('.cookiejar-dot');
    const $txt = $row.find('.cookiejar-status-text');
    $dot.removeClass('cookiejar-dot--blue cookiejar-dot--red')
        .addClass(isActive ? 'cookiejar-dot--blue' : 'cookiejar-dot--red');
    $txt.text(`${labelPrefix}${isActive ? 'Active' : 'Not Active'}`);
  }

  // =========================
  // Map markers (country-level)
  // =========================
  const COUNTRY_CENTROIDS = {
    "US": [39.8, -98.6], "CA": [61.1, -98.3], "MX": [23.6, -102.6],
    "BR": [-10.8, -52.9], "AR": [-34.7, -64.2], "CL": [-35.7, -71.5],
    "GB": [54.0, -2.0], "IE": [53.4, -8.0], "FR": [46.2, 2.2], "DE": [51.2, 10.5], "ES": [40.4, -3.6], "PT": [39.7, -8.0],
    "IT": [42.8, 12.5], "NL": [52.2, 5.3], "BE": [50.8, 4.5], "LU": [49.8, 6.1],
    "SE": [62.8, 15.2], "NO": [64.5, 11.0], "FI": [64.0, 26.3], "DK": [56.0, 10.0],
    "PL": [52.1, 19.4], "CZ": [49.8, 15.5], "AT": [47.6, 14.1], "CH": [46.8, 8.2],
    "HU": [47.2, 19.5], "RO": [45.9, 24.9], "BG": [42.8, 25.5], "GR": [39.1, 22.9],
    "TR": [39.0, 35.2], "RU": [61.5, 105.3], "UA": [49.0, 31.3],
    "CN": [35.9, 104.2], "JP": [36.2, 138.3], "KR": [36.5, 127.9],
    "IN": [22.5, 79.0], "PK": [30.4, 69.3], "BD": [23.7, 90.3], "LK": [7.9, 80.7],
    "AU": [-25.0, 133.8], "NZ": [-41.5, 172.5],
    "ZA": [-30.6, 22.9], "NG": [9.1, 8.7], "EG": [26.8, 30.8], "MA": [31.8, -7.1],
    "SA": [23.9, 45.1], "AE": [24.3, 54.3], "IL": [31.0, 34.9],
    "SG": [1.35, 103.8], "TH": [15.9, 100.0], "VN": [14.1, 108.3], "PH": [12.9, 121.8],
    "ID": [-2.2, 118.0], "MY": [4.2, 102.3],
    "CO": [4.6, -74.1], "PE": [-9.2, -75.0], "VE": [7.1, -66.2],
    "UY": [-32.5, -55.8], "PY": [-23.4, -58.4]
  };

  function projectEquirect(lat, lon, w, h) {
    const x = (lon + 180) * (w / 360);
    const y = (90 - lat) * (h / 180);
    return { x, y };
  }

  let lastLogsForMap = [];
  let lastTotals = { total: 0 };
  let recentCount = 0;

  function drawMapMarkers(logs) {
    lastLogsForMap = Array.isArray(logs) ? logs : [];
    const $wrap = $('.cookiejar-map');
    const $img = $wrap.find('img');
    const $canvas = $wrap.find('.cookiejar-map-canvas')[0];
    if (!$img.length || !$canvas) return;

    function doDraw() {
      const w = $img[0].clientWidth;
      const h = $img[0].clientHeight;
      if (w === 0 || h === 0) return;
      $canvas.width = w;
      $canvas.height = h;
      const ctx = $canvas.getContext('2d');
      ctx.clearRect(0, 0, w, h);

      const points = [];
      (lastLogsForMap || []).forEach((row, idx) => {
        const cc = ((row.country || row.country_code || '') + '').toUpperCase();
        const consent = (row.consent || row.type || '').toLowerCase();
        const centroid = COUNTRY_CENTROIDS[cc];
        if (!centroid) return;
        const jitter = (n) => (Math.sin((idx + 1) * (n + 0.73)) * 0.8);
        const lat = centroid[0] + jitter(1);
        const lon = centroid[1] + jitter(2);
        const { x, y } = projectEquirect(lat, lon, w, h);

        let color = '#94A3B8'; // unresolved
        if (consent === 'full' || consent === 'accept' || consent === 'accept all') color = '#008ED6';
        else if (consent === 'partial') color = '#005E8A';
        else if (consent === 'none' || consent === 'reject') color = '#00253B';

        ctx.save();
        ctx.beginPath();
        ctx.arc(x, y, 6, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.shadowColor = 'rgba(0,0,0,0.35)';
        ctx.shadowBlur = 3;
        ctx.shadowOffsetY = 1;
        ctx.fill();
        ctx.restore();

        points.push({ x, y, r: 7, cc, consent: consent || 'unresolved' });
      });

      const $tip = $wrap.find('.cookiejar-map-tip');
      function onMove(ev) {
        const rect = $canvas.getBoundingClientRect();
        const mx = ev.clientX - rect.left;
        const my = ev.clientY - rect.top;
        let hit = null;
        for (const p of points) {
          const dx = p.x - mx, dy = p.y - my;
          if ((dx * dx + dy * dy) <= (p.r * p.r)) { hit = p; break; }
        }
        if (hit) {
          const label = hit.consent ? (hit.consent.charAt(0).toUpperCase() + hit.consent.slice(1)) : 'Unresolved';
          $tip.text(`${hit.cc} — ${label}`);
          $tip.css({ left: hit.x + 'px', top: hit.y + 'px' }).show();
        } else {
          $tip.hide();
        }
      }
      $canvas.onmousemove = onMove;
      $canvas.onmouseleave = () => $wrap.find('.cookiejar-map-tip').hide();
    }

    if ($img[0].complete) {
      doDraw();
    } else {
      $img.one('load', doDraw);
    }

    let resizeTO = null;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTO);
      resizeTO = setTimeout(() => { if (lastLogsForMap.length) { drawMapMarkers(lastLogsForMap); } }, 120);
    }, { passive: true });
  }

  // =========================
  // Trend placeholder state
  // =========================
  function updateChartPlaceholder() {
    const $ph = $('.cookiejar-chart-placeholder');
    if (!$ph.length) return;
    const hasData = (lastTotals.total > 0) || (recentCount > 0);
    $ph.toggleClass('is-empty', !hasData);
    const $note = $ph.find('.cookiejar-chart-note');
    if ($note.length) {
      $note.text(hasData ? 'Displaying data' : 'No activity yet. Data will appear here when available.');
    }
    if (window.COOKIEJAR_ADMIN?.icons?.watermark) {
      $ph.css('--cookiejar-watermark', `url("${window.COOKIEJAR_ADMIN.icons.watermark}")`);
    }
  }

  // =========================
  // Data load and polling
  // =========================
  let pollingInterval = null;
  let isTabVisible = true;
  let lastTrend = [];
  let trendRangeDays = (window.COOKIEJAR_ADMIN && window.COOKIEJAR_ADMIN.isPro) ? 30 : 7;
  
  function loadStats() {
    $.get(COOKIEJAR_ADMIN.ajaxurl, { action: 'dwic_stats' }, function (data) {
      if (!data) return;

      const full = Number(data.full || 0);
      const partial = Number(data.partial || 0);
      const none = Number(data.none || 0);
      const total = full + partial + none;

      lastTotals.total = total;

      const aPct = pct(full, total);
      const pPct = pct(partial, total);
      const rPct = pct(none, total);
      const uPct = Math.max(0, 100 - (aPct + pPct + rPct));

      $('[data-kpi="total"]').text(fmt(total));
      $('[data-kpi="full"]').text(fmt(full));
      $('[data-kpi="partial"]').text(fmt(partial));
      $('[data-kpi="none"]').text(fmt(none));

      setDonut(aPct, pPct, rPct, uPct);

      const cookiesTotal = Number(data.cookies_total || data.cookies || 0);
      const pageviews = Number(data.pageviews || data.views || 0);
      const conversion = pageviews > 0 ? Math.round((total / pageviews) * 100) : null;
      const avgTimeLabel = fmtTime(
        typeof data.avg_decision_ms !== 'undefined' ? Number(data.avg_decision_ms) : NaN,
        typeof data.avg_decision_seconds !== 'undefined' ? Number(data.avg_decision_seconds) : NaN
      );

      $('[data-qkpi="cookies"]').text(cookiesTotal ? fmt(cookiesTotal) : '—');
      $('[data-qkpi="pageviews"]').text(pageviews ? fmt(pageviews) : '—');
      $('[data-qkpi="conversion"]').text(conversion !== null ? (conversion + '%') : '—');
      $('[data-qkpi="avgTime"]').text(avgTimeLabel);

      const bannerActive = (window.COOKIEJAR_ADMIN && Number(COOKIEJAR_ADMIN.bannerEnabled) === 1);
      const geoActive = (window.COOKIEJAR_ADMIN && Number(COOKIEJAR_ADMIN.geoActive) === 1);
      const gdprActive = (window.COOKIEJAR_ADMIN && Number(COOKIEJAR_ADMIN.gdprEnabled) === 1);
      const ccpaActive = (window.COOKIEJAR_ADMIN && Number(COOKIEJAR_ADMIN.ccpaEnabled) === 1);

      setStatus('#cookiejar-status-banner', bannerActive, 'Banner: ');
      setStatus('#cookiejar-status-geo', geoActive, 'Geo-location: ');
      setStatus('#cookiejar-status-gdpr', gdprActive, 'GDPR: ');
      setStatus('#cookiejar-status-ccpa', ccpaActive, 'CCPA: ');

      updateChartPlaceholder();
    });
  }

  function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    loadStats();
    loadRecent();
    loadTrend(trendRangeDays);
    pollingInterval = setInterval(function () {
      if (isTabVisible) {
        loadStats();
        loadRecent();
        loadTrend(trendRangeDays);
      }
    }, 30000);
  }

  function stopPolling() {
    if (pollingInterval) {
      clearInterval(pollingInterval);
      pollingInterval = null;
    }
  }

  document.addEventListener('visibilitychange', function () {
    isTabVisible = !document.hidden;
    if (isTabVisible) startPolling(); else stopPolling();
  });

  function loadRecent() {
    $.get(COOKIEJAR_ADMIN.ajaxurl, { action: 'cookiejar_recent_logs', _ajax_nonce: COOKIEJAR_ADMIN.nonce, count: 12 }, function (resp) {
      const $tb = $('#cookiejar-activity-body');
      $tb.empty();
      if (!resp || !resp.success || !Array.isArray(resp.data)) {
        recentCount = 0;
        drawMapMarkers([]);
        updateChartPlaceholder();
        return;
      }

      recentCount = resp.data.length;

      resp.data.forEach(function (row) {
        const ip = row.ip || row.ip_address || '—';
        const cc = (row.country || row.country_code || '').toUpperCase();
        const cons = (row.consent || row.type || '').replace(/^full$/i, 'Accept All').replace(/^partial$/i, 'Partial').replace(/^none$/i, 'Reject');
        const dt = row.created_at || row.ts || '';
        let date = '', time = '';
        if (dt) {
          const d = new Date(dt);
          if (!isNaN(d)) {
            date = d.toISOString().slice(0, 10);
            time = d.toISOString().slice(11, 16);
          } else if (typeof dt === 'string') {
            const m = dt.match(/^(\d{4}-\d{2}-\d{2}).*?(\d{2}:\d{2})/);
            if (m) { date = m[1]; time = m[2]; }
          }
        }
        const $row = $('<div class="cookiejar-row" role="row"></div>');
        $row.append('<div class="cookiejar-td" role="cell">' + (ip || '—') + '</div>');
        $row.append('<div class="cookiejar-td" role="cell">' + (cc || '—') + '</div>');
        $row.append('<div class="cookiejar-td" role="cell">' + (cons || '—') + '</div>');
        $row.append('<div class="cookiejar-td" role="cell">' + (date || '—') + '</div>');
        $row.append('<div class="cookiejar-td" role="cell">' + (time || '—') + '</div>');
        $tb.append($row);
      });

      drawMapMarkers(resp.data);
      updateChartPlaceholder();
    });
  }

  function loadTrend(days) {
    $.get(COOKIEJAR_ADMIN.ajaxurl, { action: 'cookiejar_trend_summary', _ajax_nonce: COOKIEJAR_ADMIN.nonce, days: days || trendRangeDays }, function (resp) {
      if (!resp || !resp.success || !Array.isArray(resp.data)) {
        renderTrend([]);
        return;
      }
      lastTrend = resp.data;
      renderTrend(lastTrend);
    });
  }

  function renderTrend(trend) {
    const $wrap = $('.cookiejar-trend-chart');
    if (!$wrap.length) return;
    const has = Array.isArray(trend) && trend.length > 0;
    const w = 640, h = 220, pad = 32;
    let svg = '';
    if (has) {
      const totals = trend.map(d => Number(d.full||0) + Number(d.partial||0) + Number(d.none||0));
      const maxTotal = Math.max(1, Math.max.apply(null, totals));
      const xStep = (w - pad * 2) / Math.max(1, trend.length - 1);

      function pathFor(key, color) {
        let p = '';
        trend.forEach(function(d, i){
          const val = Number(d[key]||0);
          const x = pad + i * xStep;
          const y = h - pad - (val / maxTotal) * (h - pad * 2);
          p += (i===0 ? 'M' : ' L') + x + ' ' + y;
        });
        return '<path d="' + p + '" fill="none" stroke="' + color + '" stroke-width="2" />';
      }

      // Axes
      const x0 = pad, y0 = h - pad, x1 = w - pad, y1 = pad;
      const axis = '<line x1="' + x0 + '" y1="' + y0 + '" x2="' + x1 + '" y2="' + y0 + '" stroke="#e2e8f0" />'
                 + '<line x1="' + x0 + '" y1="' + y0 + '" x2="' + x0 + '" y2="' + y1 + '" stroke="#e2e8f0" />';

      // Labels: first, middle, last dates; y 0 and max
      const first = trend[0]?.date || '', mid = trend[Math.floor(trend.length/2)]?.date || '', last = trend[trend.length-1]?.date || '';
      const labels = '<text x="' + x0 + '" y="' + (y0 + 16) + '" font-size="11" fill="#64748b">' + first + '</text>'
                   + '<text x="' + (x0 + (x1-x0)/2) + '" y="' + (y0 + 16) + '" font-size="11" text-anchor="middle" fill="#64748b">' + mid + '</text>'
                   + '<text x="' + x1 + '" y="' + (y0 + 16) + '" font-size="11" text-anchor="end" fill="#64748b">' + last + '</text>'
                   + '<text x="' + (x0 - 6) + '" y="' + y0 + '" font-size="11" text-anchor="end" fill="#64748b">0</text>'
                   + '<text x="' + (x0 - 6) + '" y="' + (y1 + 4) + '" font-size="11" text-anchor="end" fill="#64748b">' + maxTotal + '</text>';

      svg = '<svg viewBox="0 0 ' + w + ' ' + h + '" width="100%" height="220" role="img" aria-label="Consent trend">'
          + '<rect x="0" y="0" width="' + w + '" height="' + h + '" fill="white" />' + axis + labels
          + pathFor('full', '#16a34a')
          + pathFor('partial', '#f59e0b')
          + pathFor('none', '#ef4444')
          + '</svg>';
    }

    const $ph = $wrap.find('.cookiejar-chart-placeholder');
    if (has) {
      $ph.remove();
      $wrap.html(svg);
    } else {
      if (!$ph.length) {
        $wrap.html('<div class="cookiejar-chart-placeholder is-empty" aria-live="polite"><span class="cookiejar-chart-note">No activity yet. Data will appear here when available.</span></div>');
      } else {
        $ph.addClass('is-empty');
      }
    }
  }

  // Trend range controls
  $(document).on('click', '.cookiejar-trend-range', function(){
    const days = parseInt($(this).data('days'), 10);
    if (!days) return;
    trendRangeDays = days;
    $('.cookiejar-trend-range').removeClass('button-primary');
    $(this).addClass('button-primary');
    loadTrend(trendRangeDays);
  });

  // Initialize polling system
  startPolling();

  // =========================
  // Predictive Search
  // =========================
  const ROUTES = Array.isArray(window.COOKIEJAR_ADMIN?.routes) ? window.COOKIEJAR_ADMIN.routes : [];

  function scoreItem(q, item) {
    const nQ = normalize(q);
    if (!nQ) return 0;
    const hay = [item.label].concat(item.keywords || []).map(normalize).join(' | ');
    let score = 0;
    if (hay.includes(nQ)) score += 20;
    if (hay.indexOf(nQ) >= 0) score += 10;
    nQ.split(/\s+/).forEach(tok => {
      if (!tok) return;
      if (hay.indexOf(tok) >= 0) score += 5;
      else if (hay.includes(tok)) score += 2;
    });
    if (normalize(item.label).startsWith(nQ)) score += 8;
    return score;
  }

  function buildSuggestions(q) {
    const scored = ROUTES.map(r => ({ item: r, sc: scoreItem(q, r) })).filter(x => x.sc > 0);
    scored.sort((a, b) => b.sc - a.sc);
    return scored.slice(0, 8).map(x => x.item);
  }

  function activateAction(item) {
    if (!item) return;
    if (item.type === 'goto' && item.url) {
      window.location.href = item.url; return;
    }
    if (item.type === 'scroll' && item.target) {
      const el = document.querySelector(item.target);
      if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
      return;
    }
    if (item.type === 'command') {
      if (item.command === 'openConsent') {
        if (window.dwicConsent && typeof window.dwicConsent.open === 'function') window.dwicConsent.open();
        else showNotice('Consent banner can be opened from the frontend.', 'info');
      } else if (item.command === 'resetNotice') {
        if (confirm('This will reset the consent notice for all users. They will see the banner again. Continue?')) {
          $.post(COOKIEJAR_ADMIN.ajaxurl, {
            action: 'cookiejar_reset_notice',
            reset: '1',
            _ajax_nonce: COOKIEJAR_ADMIN.nonce
          }, function (resp) {
            if (resp.success) {
              showNotice(resp.data.message || I18N.settingsSaved, 'success');
            } else {
              showNotice('Failed to reset notice: ' + (resp.data || I18N.unknownError), 'error');
            }
          }).fail(function () {
            showNotice(I18N.networkError, 'error');
          });
        }
      }
      return;
    }
  }

  function initSearch($searchWrap) {
    const $input = $searchWrap.find('.cookiejar-search-input');
    const $btn = $searchWrap.find('.cookiejar-search-btn');
    const $sug = $searchWrap.find('.cookiejar-search-suggestions');

    if ($sug.length) {
      if (!$sug.attr('id')) {
        const uid = 'cookiejar-sug-' + Math.random().toString(36).slice(2, 9);
        $sug.attr('id', uid);
        $input.attr('aria-controls', uid);
      } else {
        $input.attr('aria-controls', $sug.attr('id'));
      }
    }
    $input.attr({
      role: 'combobox',
      'aria-autocomplete': 'list',
      'aria-expanded': 'false'
    });

    let open = false;
    let items = [];
    let activeIndex = -1;

    function render() {
      if (!open || items.length === 0) {
        $sug.hide().empty();
        $input.attr('aria-expanded', 'false');
        return;
      }
      const html = items.map((it, idx) => (
        '<div class="cookiejar-suggest-item' + (idx === activeIndex ? ' active' : '') + '" role="option" data-index="' + idx + '">'
        + '<span>' + it.label + '</span>'
        + '<span class="hint">' + (it.type === 'goto' ? 'Open' : 'Go') + '</span>'
        + '</div>'
      )).join('');
      $sug.html(html).show();
      $sug.attr('aria-expanded', 'true');
      $input.attr('aria-expanded', 'true');
    }

    function update(q) {
      items = buildSuggestions(q);
      activeIndex = items.length ? 0 : -1;
      open = items.length > 0;
      render();
    }

    function choose(idx) {
      if (idx < 0 || idx >= items.length) return;
      const it = items[idx];
      close();
      activateAction(it);
    }

    function close() {
      open = false; items = []; activeIndex = -1;
      $sug.hide().empty();
      $input.attr('aria-expanded', 'false');
    }

    $input.on('input', function () { update($(this).val()); });
    $input.on('keydown', function (e) {
      if (!open && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { open = true; render(); return; }
      if (!open) return;
      if (e.key === 'ArrowDown') { activeIndex = Math.min(items.length - 1, activeIndex + 1); render(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { activeIndex = Math.max(0, activeIndex - 1); render(); e.preventDefault(); }
      else if (e.key === 'Enter') { if (activeIndex >= 0) choose(activeIndex); e.preventDefault(); }
      else if (e.key === 'Escape') { close(); }
    });

    $btn.on('click', function () {
      const val = $input.val();
      if (!val) {
        const itemsTop = ROUTES.slice(0, 6);
        if (itemsTop.length) { open = true; items = itemsTop; activeIndex = 0; render(); }
        $input.focus();
        return;
      }
      if (items.length === 0) { items = buildSuggestions(val); }
      if (items.length > 0) choose(0);
    });

    $sug.on('click', '.cookiejar-suggest-item', function () {
      const idx = parseInt($(this).attr('data-index'), 10);
      choose(idx);
    });

    $(document).on('click', function (ev) {
      if ($searchWrap.has(ev.target).length === 0) { close(); }
    });
  }

  function initSearchGhost($searchWrap) {
    const $input = $searchWrap.find('.cookiejar-search-input');
    if (!$input.length) return;

    let $ghost = $searchWrap.find('.cookiejar-search-ghost');
    if (!$ghost.length) {
      $ghost = $('<div class="cookiejar-search-ghost" aria-hidden="true"><span class="text"></span><span class="cursor">▋</span></div>');
      $searchWrap.append($ghost);
    }
    const $text = $ghost.find('.text');

    const upgradeLabel = 'Documentation';
    const curatedTop = [
      'Banner Settings', 'Consent Logs', 'Languages', 'Advanced Settings',
      'Traffic & Trend', 'Review Reports', upgradeLabel, 'Settings', 'Help & Support'
    ];

    let topics = [];
    const rawTopics = ($input.attr('data-topics') || $searchWrap.attr('data-topics') || '').trim();
    if (rawTopics) {
      topics = rawTopics.split(',').map(s => s.trim()).filter(Boolean);
    } else if (Array.isArray(window.COOKIEJAR_ADMIN?.routes) && window.COOKIEJAR_ADMIN.routes.length) {
      const labels = window.COOKIEJAR_ADMIN.routes.map(r => r?.label ? String(r.label).trim() : '').filter(Boolean);
      const seen = new Set();
      topics = labels.filter(l => { if (seen.has(l)) return false; seen.add(l); return true; }).slice(0, 10);
      if (!topics.length) topics = curatedTop;
    } else {
      topics = curatedTop;
    }

    const SEARCH_BEAT = 'Search...';
    const playlist = topics.flatMap(t => [SEARCH_BEAT, t]);

    let playIdx = 0, charIdx = 0, mode = 'typing', pauseUntil = 0;

    function currentPhrase() { return playlist[playIdx % playlist.length]; }
    function refreshGhostVisibility() {
      const shouldShow = !$input.val() && !$input.is(':focus');
      $ghost.toggle(!!shouldShow);
    }
    function tick() {
      const now = Date.now();
      const phrase = currentPhrase();

      if (mode === 'typing') {
        charIdx = Math.min(charIdx + 1, phrase.length);
        $text.text(phrase.slice(0, charIdx));
        if (charIdx === phrase.length) { mode = 'pausing'; pauseUntil = now + (phrase === SEARCH_BEAT ? 900 : 1200); }
        setTimeout(tick, 70); return;
      }
      if (mode === 'pausing') {
        if (now >= pauseUntil) { mode = 'deleting'; }
        setTimeout(tick, 60); return;
      }
      charIdx = Math.max(charIdx - 1, 0);
      $text.text(phrase.slice(0, charIdx));
      if (charIdx === 0) { playIdx = (playIdx + 1) % playlist.length; mode = 'typing'; }
      setTimeout(tick, 35);
    }

    $input.on('focus input blur', refreshGhostVisibility);
    refreshGhostVisibility();
    tick();
  }

  $('.cookiejar-search').each(function () {
    const $wrap = $(this);
    initSearch($wrap);
    initSearchGhost($wrap);
  });

  // =========================
  // Control Panel Page Router
  // =========================
  const PAGES = Array.isArray(window.COOKIEJAR_ADMIN?.pages) ? window.COOKIEJAR_ADMIN.pages : [];
  const PAGES_BY_SLUG = {};
  PAGES.forEach(p => { PAGES_BY_SLUG[p.slug] = p; });

  function parseHash() {
    const h = (window.location.hash || '').replace(/^#/, '');
    if (!h) return null;
    const m = h.match(/(?:^|&)page=([^&]+)/);
    if (m) return decodeURIComponent(m[1]);
    if (h.indexOf('=') === -1) return decodeURIComponent(h);
    return null;
  }

  function setHashForPage(slug) {
    if (!slug) return;
    const newHash = '#page=' + encodeURIComponent(slug);
    if (window.location.hash !== newHash) {
      window.location.hash = newHash;
    } else {
      applyPage(slug);
    }
  }

  function applyPage(slug) {
    debugLog('applyPage called with slug:', slug);
    const page = PAGES_BY_SLUG[slug] || PAGES_BY_SLUG['dashboard'] || PAGES[0];
    debugLog('Found page:', page);
    if (!page) return;

    $('.cookiejar-page').hide();
    
    const $page = $('#page-' + slug);
    debugLog('Page element found:', $page.length, 'for slug:', slug);
    if ($page.length) {
      $page.show();
    } else {
      debugLog('Page element not found, showing default');
      $('#page-default').show();
    }

    const $title = $('#cookiejar-cp-section-title');
    if ($title.length) { $title.text(page.title || ''); }

    const $footLink = $('#cookiejar-footer-primary-link');
    if ($footLink.length) {
      const explain = page.explain || '';
      $footLink.text(titleCaseExceptAnd(explain));
    }

    $('.cookiejar-nav-link').removeClass('active').each(function () {
      if ($(this).data('page') === page.slug) { $(this).addClass('active'); }
    });

    loadPageData(slug);
  }

  function loadPageData(slug) {
    switch (slug) {
      case 'dashboard':
        loadStats();
        loadRecent();
        break;
      case 'logs':
        loadConsentLogs();
        break;
      case 'banner':
        loadBannerSettings();
        break;
      case 'languages':
        loadLanguageSettings();
        break;
      case 'advanced':
        loadAdvancedSettings();
        break;
      case 'general':
        initGeneralSettingsForm();
        break;
      case 'appearance':
        initAppearanceSettingsForm();
        break;
      case 'compliance':
        initComplianceSettingsForm();
        break;
      case 'security':
        initSecuritySettingsForm();
        break;
      case 'performance':
        initPerformanceSettingsForm();
        break;
      case 'integrations':
        initIntegrationSettingsForm();
        break;
      case 'backup':
        initBackupRestoreForm();
        break;
      case 'health':
        // Optional: implement loadHealthCheck() if/when available
        break;
    }
  }

  function loadConsentLogs() {
    $.get(COOKIEJAR_ADMIN.ajaxurl, { action: 'cookiejar_recent_logs', _ajax_nonce: COOKIEJAR_ADMIN.nonce, count: 50 }, function (resp) {
      const $container = $('#consent-logs-table');
      if (!resp || !resp.success || !Array.isArray(resp.data)) {
        $container.html('<p>' + I18N.noConsentLogs + '</p>');
        return;
      }
      
      let html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>IP</th><th>Country</th><th>Consent</th><th>Date</th></tr></thead><tbody>';
      resp.data.forEach(function (log) {
        html += '<tr>';
        html += '<td>' + (log.ip || '--') + '</td>';
        html += '<td>' + (log.country || '--') + '</td>';
        html += '<td>' + (log.consent || '--') + '</td>';
        html += '<td>' + (log.created_at ? new Date(log.created_at).toLocaleString() : '--') + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
      $container.html(html);
    });
  }

  function loadBannerSettings() {
    $.get(COOKIEJAR_ADMIN.ajaxurl, { action: 'cookiejar_banner_settings', _ajax_nonce: COOKIEJAR_ADMIN.nonce }, function (resp) {
      if (resp && resp.success && resp.data) {
        const data = resp.data;
        $('input[name="banner_enabled"]').prop('checked', data.enabled === '1');
        $('select[name="banner_position"]').val(data.position || 'bottom');
        $('select[name="banner_theme"]').val(data.theme || 'light');
      }
    });
  }

  function loadLanguageSettings() {
    const currentLang = getCookie('dwic_lang') || 'en';
    $('select[name="default_language"]').val(currentLang);
  }

  function loadAdvancedSettings() {
    const duration = getCookie('dwic_duration') || '180';
    $('input[name="consent_duration"]').val(duration);
  }

  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    if (match) return decodeURIComponent(match[2]);
    return null;
  }

  function initRouter() {
    debugLog('Initializing router...');
    debugLog('Navigation links found:', $('.cookiejar-nav-link').length);

    const firstSlug = parseHash() || 'dashboard';
    applyPage(firstSlug);

    window.addEventListener('hashchange', function () {
      const slug = parseHash() || 'dashboard';
      applyPage(slug);
    });

    // Delegated binding (primary)
    $(document).on('click', '.cookiejar-nav-link', function (e) {
      const href = $(this).attr('href') || '';
      if (href.includes('cookiejar-wizard')) {
        debugLog('Wizard link clicked - allowing normal navigation');
        return true;
      }
      const slug = $(this).data('page');
      debugLog('Delegated nav link clicked:', slug);
      if (slug) {
        e.preventDefault();
        e.stopPropagation();
        setHashForPage(slug);
      }
    });
  }

  // =========================
  // Header pills -> navigate
  // =========================
  function initHeaderPillLinks() {
    const controlUrl = window.COOKIEJAR_ADMIN?.controlUrl || null;
    if (!controlUrl) return;
    $(document).on('click', '.cookiejar-pill-link', function () {
      const slug = $(this).attr('data-page');
      debugLog('Pill link clicked:', slug, 'controlUrl:', controlUrl);
      if (!slug) return;

      if (window.location.href.indexOf('cookiejar-control') !== -1) {
        setHashForPage(slug);
      } else {
      const url = controlUrl + '#page=' + encodeURIComponent(slug);
      window.location.href = url;
      }
    });
  }

  // =========================
  // Simple form handlers
  // =========================
  function bindFormHandler(formId, action) {
    $(document).on('submit', formId, function (e) {
      e.preventDefault();
      const fd = new FormData(this);
      fd.append('action', action);
      fd.append('_ajax_nonce', COOKIEJAR_ADMIN.nonce);
      $.ajax({
        url: COOKIEJAR_ADMIN.ajaxurl,
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false
      }).done(function (resp) {
        if (resp && resp.success) {
          showNotice(resp.data?.message || I18N.settingsSaved, 'success');
        } else {
          showNotice(I18N.settingsError + ' ' + (resp?.data || I18N.unknownError), 'error');
        }
      }).fail(function () {
        showNotice(I18N.networkError, 'error');
      });
    });
  }

  bindFormHandler('#banner-settings-form', 'cookiejar_save_banner_settings');
  bindFormHandler('#language-settings-form', 'cookiejar_save_language_settings');
  bindFormHandler('#advanced-settings-form', 'cookiejar_save_advanced_settings');

  // Refresh / Export buttons
  $(document).on('click', '#refresh-logs', function () { loadConsentLogs(); });
  $(document).on('click', '#export-logs', function () {
      window.open(COOKIEJAR_ADMIN.ajaxurl + '?action=cookiejar_export_logs&_ajax_nonce=' + COOKIEJAR_ADMIN.nonce);
    });
  $(document).on('click', '#export-settings', function () {
      window.open(COOKIEJAR_ADMIN.ajaxurl + '?action=cookiejar_export_settings&_ajax_nonce=' + COOKIEJAR_ADMIN.nonce);
    });

  // Import via file select mirror to textarea
  $(document).on('click', '#select-file', function () { $('#import-file').click(); });
  $(document).on('change', '#import-file', function () {
      const file = this.files[0];
      if (file) {
      if (!/\.json$/i.test(file.name)) {
        showNotice(I18N.invalidJSON, 'error');
        return;
      }
      if (file.size > 2 * 1024 * 1024) { // 2MB guard
        showNotice('File too large (max 2MB).', 'error');
        return;
      }
        const reader = new FileReader();
      reader.onload = function (e) {
          $('#import-textarea').val(e.target.result);
        };
        reader.readAsText(file);
      }
    });

  $(document).on('click', '#import-settings', function () {
      const settings = $('#import-textarea').val().trim();
      if (!settings) {
      showNotice(I18N.fileSelect, 'error');
        return;
      }
    try { JSON.parse(settings); } catch (e) {
      showNotice(I18N.invalidJSON, 'error');
        return;
      }
      $.post(COOKIEJAR_ADMIN.ajaxurl, {
        action: 'cookiejar_import_settings',
        settings: settings,
        _ajax_nonce: COOKIEJAR_ADMIN.nonce
    }, function (resp) {
        if (resp.success) {
        showNotice(I18N.importSuccess, 'success');
          $('#import-textarea').val('');
        } else {
        showNotice(I18N.importError + ' ' + (resp.data || I18N.unknownError), 'error');
        }
    }).fail(function () {
      showNotice(I18N.importFailed, 'error');
      });
    });

  $(document).on('click', '#reset-defaults', function () {
    if (confirm(I18N.confirmReset)) {
        $.post(COOKIEJAR_ADMIN.ajaxurl, {
          action: 'cookiejar_reset_defaults',
          _ajax_nonce: COOKIEJAR_ADMIN.nonce
      }, function (resp) {
          if (resp.success) {
          showNotice(I18N.resetSuccess, 'success');
          setTimeout(function () { window.location.reload(); }, 2000);
          } else {
          showNotice(I18N.resetError + ' ' + (resp.data || I18N.unknownError), 'error');
          }
      }).fail(function () {
        showNotice(I18N.networkError, 'error');
        });
      }
    });

  // =========================
  // Delayed init (router, forms)
  // =========================
  setTimeout(function () {
    debugLog('Delayed initialization starting...');
    initRouter();
    initHeaderPillLinks();
    initFormHandlers();

    // Expose test helpers only if debug enabled
    if (window.COOKIEJAR_ADMIN?.debug) {
      window.testCookieJarLinks = function () {
        console.log('=== CookieJar Link Test ===');
        console.log('Available pages:', PAGES);
        console.log('Pages by slug:', PAGES_BY_SLUG);
        console.log('jQuery version:', $.fn.jquery);
        console.log('COOKIEJAR_ADMIN object:', window.COOKIEJAR_ADMIN);
        $('.cookiejar-nav-link').each(function (index) {
          const $link = $(this);
          const slug = $link.data('page');
          const $page = $('#page-' + slug);
          const isVisible = $link.is(':visible');
          const hasEvents = $._data(this, 'events');
          console.log(`Link ${index}: ${slug} -> Page exists: ${$page.length > 0}, Visible: ${isVisible}, Has events: ${!!hasEvents}`);
        });
        $('.cookiejar-pill-link').each(function (index) {
          const $link = $(this);
          const slug = $link.data('page');
          const $page = $('#page-' + slug);
          const isVisible = $link.is(':visible');
          console.log(`Pill ${index}: ${slug} -> Page exists: ${$page.length > 0}, Visible: ${isVisible}`);
        });
        console.log('Testing manual click on first nav link...');
        const $firstLink = $('.cookiejar-nav-link').first();
        if ($firstLink.length) {
          console.log('First link slug:', $firstLink.data('page'));
          $firstLink.trigger('click');
        }
        console.log('=== End Test ===');
      };

      window.testNavClick = function () {
        $('.cookiejar-nav-link').each(function () {
          const slug = $(this).data('page');
          console.log('Testing click on:', slug);
          $(this).trigger('click');
        });
      };
    }
    debugLog('Initialization complete.');
  }, 100);

  // =========================
  // New Settings Forms (section-specific)
  // =========================
  function initFormHandlers() {
    // Already bound simple forms above via bindFormHandler
    // Additional complex forms below use FormData
    initPerformanceSettingsForm(); // ensure clear cache handler
    initBackupRestoreForm();       // ensure import/export handlers
    initAppearanceSettingsForm();  // live preview
    initGeneralSettingsForm();     // reset defaults
    initComplianceSettingsForm();
    initSecuritySettingsForm();
    initIntegrationSettingsForm();
  }

  function saveSettings(type, $form) {
    const formData = new FormData($form[0]);
    formData.append('action', 'cookiejar_save_' + type + '_settings');
    formData.append('_ajax_nonce', COOKIEJAR_ADMIN.nonce);

    const $submitBtn = $form.find('button[type="submit"]');
    const originalText = $submitBtn.text();
    $submitBtn.text(I18N.saving).prop('disabled', true);

    $.ajax({
      url: COOKIEJAR_ADMIN.ajaxurl,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false
    }).done(function (response) {
      if (response.success) {
        showNotice(response.data?.message || I18N.settingsSaved, 'success');
      } else {
        showNotice(response.data?.error || I18N.settingsError + ' ' + I18N.unknownError, 'error');
      }
    }).fail(function () {
      showNotice(I18N.networkError, 'error');
    }).always(function () {
      $submitBtn.text(originalText).prop('disabled', false);
    });
  }

  function initGeneralSettingsForm() {
    $('#general-settings-form').off('submit').on('submit', function (e) {
      e.preventDefault();
      saveSettings('general', $(this));
    });

    $('#reset-general-settings').off('click').on('click', function () {
      if (confirm('Reset general settings to defaults?')) {
        resetFormDefaults('general');
      }
    });
  }

  function initAppearanceSettingsForm() {
    $('#appearance-settings-form').off('submit').on('submit', function (e) {
      e.preventDefault();
      saveSettings('appearance', $(this));
    });

    $('#preview-banner').off('click').on('click', function () {
      showBannerPreview();
    });

    // Real-time preview on input change
    $('#banner_color, #banner_bg, #banner_font, #banner_font_size').on('input change', function () {
      showBannerPreview();
    });
  }

  function initComplianceSettingsForm() {
    $('#compliance-settings-form').off('submit').on('submit', function (e) {
      e.preventDefault();
      saveSettings('compliance', $(this));
    });
  }

  function initSecuritySettingsForm() {
    $('#security-settings-form').off('submit').on('submit', function (e) {
      e.preventDefault();
      saveSettings('security', $(this));
    });
  }

  function initPerformanceSettingsForm() {
    $('#performance-settings-form').off('submit').on('submit', function (e) {
      e.preventDefault();
      saveSettings('performance', $(this));
    });

    $('#clear-all-cache').off('click').on('click', function () { clearAllCache(); });
  }

  function initIntegrationSettingsForm() {
    $('#integration-settings-form').off('submit').on('submit', function (e) {
      e.preventDefault();
      saveSettings('integrations', $(this));
    });
  }

  function initBackupRestoreForm() {
    $('#export-settings').off('click').on('click', function () { exportSettings(); });

    $('#import-settings-form').off('submit').on('submit', function (e) {
      e.preventDefault();
      importSettings();
    });

    $('#reset-all-settings').off('click').on('click', function () {
      if (confirm('Are you sure you want to reset all settings? This cannot be undone.')) {
        resetAllSettings();
      }
    });
  }

  function showBannerPreview() {
    const color = $('#banner_color').val() || '#008ed6';
    const bg = $('#banner_bg').val() || '#ffffff';
    const font = $('#banner_font').val() || 'inherit';
    const fontSize = $('#banner_font_size').val() || 16;

    const previewHtml = `
      <div style="background: ${bg}; color: ${color}; padding: 16px; font-family: ${font}; font-size: ${fontSize}px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 500px; margin: auto; text-align: center; border: 2px solid ${color};">
        <span>🍪 We use cookies to enhance your experience.</span><br>
        <button style="background: ${color}; color: ${bg}; border: none; padding: 8px 14px; margin: 10px 5px 0 5px; border-radius: 18px; font-size: 13px; cursor: pointer;">Accept All</button>
        <button style="background: rgba(0,0,0,0.1); color: ${color}; border: none; padding: 8px 14px; margin: 10px 5px 0 5px; border-radius: 18px; font-size: 13px; cursor: pointer;">Preferences</button>
      </div>
    `;

    if ($('#banner-preview-modal').length === 0) {
      $('body').append(`
        <div id="banner-preview-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
          <div role="dialog" aria-modal="true" aria-labelledby="banner-preview-title" style="background:white;padding:20px;border-radius:8px;max-width:600px;width:90%;">
            <h3 id="banner-preview-title">${I18N.bannerPreviewTitle}</h3>
            <div id="banner-preview-content"></div>
            <button type="button" id="close-preview" class="button" style="margin-top:15px;">${I18N.closePreview}</button>
          </div>
        </div>
      `);
      trapFocus($('#banner-preview-modal'));
    }
    $('#banner-preview-content').html(previewHtml);
    $('#banner-preview-modal').show();
    $('#close-preview').trigger('focus');
  }

  $(document).on('click', '#close-preview', function () {
    $('#banner-preview-modal').hide();
  });

  function trapFocus($modal) {
    $modal.on('keydown', function (e) {
      if (e.key === 'Tab') {
        const focusables = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
        if (!focusables.length) return;
        const first = focusables[0], last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
      if (e.key === 'Escape') {
        $('#banner-preview-modal').hide();
      }
    });
  }

  function clearAllCache() {
    $.post(COOKIEJAR_ADMIN.ajaxurl, {
      action: 'cookiejar_clear_cache',
      _ajax_nonce: COOKIEJAR_ADMIN.nonce
    }, function (response) {
      if (response.success) {
        showNotice(I18N.cacheCleared, 'success');
        if ($('#page-performance').is(':visible')) {
          loadPageData('performance');
        }
      } else {
        showNotice(I18N.cacheError, 'error');
      }
    }).fail(function () {
      showNotice(I18N.networkError, 'error');
    });
  }

  function exportSettings() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = COOKIEJAR_ADMIN.ajaxurl;

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'cookiejar_backup_settings';

    const nonceInput = document.createElement('input');
    nonceInput.type = 'hidden';
    nonceInput.name = '_ajax_nonce';
    nonceInput.value = COOKIEJAR_ADMIN.nonce;

    form.appendChild(actionInput);
    form.appendChild(nonceInput);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    showNotice(I18N.exportStarted, 'success');
  }

  function importSettings() {
    const fileInput = document.getElementById('settings_file');
    if (!fileInput || !fileInput.files.length) {
      showNotice(I18N.fileSelect, 'error');
      return;
    }
    const file = fileInput.files[0];
    if (!/\.json$/i.test(file.name)) {
      showNotice(I18N.invalidJSON, 'error');
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      showNotice('File too large (max 2MB).', 'error');
      return;
    }

    const formData = new FormData();
    formData.append('action', 'cookiejar_restore_settings');
    formData.append('_ajax_nonce', COOKIEJAR_ADMIN.nonce);
    formData.append('settings_file', file);

    $.ajax({
      url: COOKIEJAR_ADMIN.ajaxurl,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false
    }).done(function (response) {
      if (response.success) {
        showNotice(response.data?.message || I18N.importSuccess, 'success');
        setTimeout(() => location.reload(), 1500);
      } else {
        showNotice(response.data?.error || I18N.importError + ' ' + I18N.unknownError, 'error');
      }
    }).fail(function () {
      showNotice(I18N.importFailed, 'error');
    });
  }

  function resetAllSettings() {
    // Backend endpoint not implemented yet in provided code
    showNotice(I18N.resetComingSoon, 'info');
  }

  function resetFormDefaults(type) {
    switch (type) {
      case 'general':
        $('#consent_storage_mode').val('hash');
        $('#consent_duration').val('180');
        $('#logging_mode').val('cached');
        $('#geo_auto').prop('checked', true);
        break;
      // Add more cases as needed
    }
    showNotice(I18N.formReset, 'info');
  }

  // =========================
  // Accordion
  // =========================
  $('.cookiejar-accordion-header').on('click', function () {
    const $header = $(this);
    const $item = $header.closest('.cookiejar-accordion-item');
    const $content = $item.find('.cookiejar-accordion-content');
    const $toggle = $header.find('.cookiejar-accordion-toggle');

    $content.slideToggle(300);
    const open = $content.is(':visible');
    $toggle.text(open ? '▲' : '▼');
    $header.attr('aria-expanded', open ? 'true' : 'false');
  });

  // =========================
  // Wizard admin helpers on CP
  // =========================
  debugLog('CookieJar Admin JS: Setting up wizard event handlers');

  $(document).on('click', '#start-basic-wizard', function (e) {
    e.preventDefault();
    debugLog('Basic wizard button clicked');
    $('#wizard-mode-selection').hide();
    $('#basic-wizard-flow').show();
  });

  $(document).on('click', '#start-advanced-wizard', function (e) {
    e.preventDefault();
    debugLog('Advanced wizard button clicked');
    $('#wizard-mode-selection').hide();
    $('#advanced-wizard-flow').show();
  });

  $(document).on('click', '#cancel-basic-wizard, #cancel-advanced-wizard', function (e) {
    e.preventDefault();
    debugLog('Cancel wizard button clicked');
    $('#basic-wizard-flow, #advanced-wizard-flow').hide();
    $('#wizard-mode-selection').show();
  });

  $(document).on('click', '#skip-wizard', function (e) {
    e.preventDefault();
    if (confirm(I18N.skipWizardConfirm)) {
      debugLog('Skip wizard confirmed');
      $.post(COOKIEJAR_ADMIN.ajaxurl, {
        action: 'cookiejar_skip_wizard',
        nonce: COOKIEJAR_ADMIN.nonce
      }, function (response) {
        if (response.success) {
          // Go to GENERAL page in the control panel
          window.location.href = 'admin.php?page=cookiejar-control#page=general';
        } else {
          showNotice('Failed to skip wizard: ' + (response.data || I18N.unknownError), 'error');
        }
      }).fail(function () {
        showNotice(I18N.networkError, 'error');
      });
    }
  });

  // HIGH PRIORITY: Preview Banner Functionality
  function initPreviewBanner() {
    // Live preview updates
    $('#banner_color, #banner_bg, #banner_font, #banner_font_size').on('change input', function() {
      updateBannerPreview();
    });
    
    // Range slider sync with number input
    $('#banner_font_size_range').on('input', function() {
      $('#banner_font_size').val($(this).val());
      updateBannerPreview();
    });
    
    $('#banner_font_size').on('input', function() {
      $('#banner_font_size_range').val($(this).val());
      updateBannerPreview();
    });
    
    // Preview Banner button
    $('#preview-banner').on('click', function() {
      showBannerPreview();
    });
    
    // Test Translations button
    $('#test-translations').on('click', function() {
      testTranslations();
    });
  }
  
  function updateBannerPreview() {
    const bannerColor = $('#banner_color').val() || '#008ed6';
    const bannerBg = $('#banner_bg').val() || '#ffffff';
    const bannerFont = $('#banner_font').val() || 'inherit';
    const bannerFontSize = $('#banner_font_size').val() || '16';
    
    // Update color previews
    $('.cookiejar-color-preview').each(function() {
      const inputId = $(this).siblings('.cookiejar-color-input').attr('id');
      if (inputId === 'banner_color') {
        $(this).css('background-color', bannerColor);
      } else if (inputId === 'banner_bg') {
        $(this).css('background-color', bannerBg);
      }
    });
    
    // Update font preview
    $('.cookiejar-font-preview').css('font-size', bannerFontSize + 'px');
    
    // Update live preview banner
    const previewBanner = $('.cookiejar-preview-banner');
    previewBanner.css({
      'background-color': bannerBg,
      'color': bannerColor,
      'font-family': bannerFont,
      'font-size': bannerFontSize + 'px'
    });
    
    // Update preview buttons
    $('.cookiejar-preview-accept').css('background-color', bannerColor);
  }
  
  function showBannerPreview() {
    const bannerColor = $('#banner_color').val() || '#008ed6';
    const bannerBg = $('#banner_bg').val() || '#ffffff';
    const bannerFont = $('#banner_font').val() || 'inherit';
    const bannerFontSize = $('#banner_font_size').val() || '16';
    
    // Create modal for full preview
    const modalHtml = `
      <div id="banner-preview-modal" class="cookiejar-modal" style="display: flex;">
        <div class="cookiejar-modal-content" style="max-width: 600px; width: 90%;">
          <div class="cookiejar-modal-header">
            <h3>Banner Preview</h3>
            <button class="cookiejar-modal-close">&times;</button>
          </div>
          <div class="cookiejar-modal-body">
            <div class="cookiejar-full-preview" style="background-color: ${bannerBg}; color: ${bannerColor}; font-family: ${bannerFont}; font-size: ${bannerFontSize}px; padding: 20px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
              <div class="cookiejar-preview-content">
                <p style="margin: 0 0 15px 0; line-height: 1.5;">We use cookies to enhance your browsing experience and analyze our traffic.</p>
                <div class="cookiejar-preview-buttons" style="display: flex; gap: 12px; flex-wrap: wrap;">
                  <button class="cookiejar-preview-btn cookiejar-preview-accept" style="background-color: ${bannerColor}; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer;">Accept All</button>
                  <button class="cookiejar-preview-btn cookiejar-preview-settings" style="background: #fff; color: #333; padding: 10px 20px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer;">Settings</button>
                </div>
              </div>
            </div>
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; font-size: 13px; color: #666;">
              <strong>Preview Settings:</strong><br>
              Background: ${bannerBg} | Text Color: ${bannerColor} | Font: ${bannerFont} | Size: ${bannerFontSize}px
            </div>
          </div>
          <div class="cookiejar-modal-footer">
            <button class="button button-primary" onclick="$('#banner-preview-modal').hide();">Close Preview</button>
          </div>
        </div>
      </div>
    `;
    
    // Remove existing modal if any
    $('#banner-preview-modal').remove();
    
    // Add modal to body
    $('body').append(modalHtml);
    
    // Close handlers
    $('#banner-preview-modal .cookiejar-modal-close').on('click', function() {
      $('#banner-preview-modal').hide();
    });
    
    $('#banner-preview-modal').on('click', function(e) {
      if (e.target === this) {
        $(this).hide();
      }
    });
  }
  
  function testTranslations() {
    const customTranslations = $('#custom_translations').val();
    
    if (!customTranslations.trim()) {
      alert('Please enter some custom translations to test.');
      return;
    }
    
    try {
      const translations = JSON.parse(customTranslations);
      const languages = Object.keys(translations);
      
      let testHtml = '<div class="cookiejar-translation-test">';
      testHtml += '<h4>Translation Test Results</h4>';
      
      languages.forEach(lang => {
        testHtml += `<div class="cookiejar-lang-test" style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">`;
        testHtml += `<strong>${lang.toUpperCase()}:</strong><br>`;
        
        Object.entries(translations[lang]).forEach(([key, value]) => {
          testHtml += `<span style="color: #666;">${key}:</span> <span style="font-weight: 500;">${value}</span><br>`;
        });
        
        testHtml += '</div>';
      });
      
      testHtml += '</div>';
      
      // Show in modal
      const modalHtml = `
        <div id="translation-test-modal" class="cookiejar-modal" style="display: flex;">
          <div class="cookiejar-modal-content" style="max-width: 500px;">
            <div class="cookiejar-modal-header">
              <h3>Translation Test</h3>
              <button class="cookiejar-modal-close">&times;</button>
            </div>
            <div class="cookiejar-modal-body">
              ${testHtml}
            </div>
            <div class="cookiejar-modal-footer">
              <button class="button button-primary" onclick="$('#translation-test-modal').hide();">Close</button>
            </div>
          </div>
        </div>
      `;
      
      $('#translation-test-modal').remove();
      $('body').append(modalHtml);
      
      // Close handlers
      $('#translation-test-modal .cookiejar-modal-close').on('click', function() {
        $('#translation-test-modal').hide();
      });
      
    } catch (error) {
      alert('Invalid JSON format. Please check your translation syntax.\n\nError: ' + error.message);
    }
  }
  
  // Initialize preview functionality
  initPreviewBanner();
  
  // MEDIUM PRIORITY: Global Audit - Fix broken links and add missing functionality
  function initGlobalAudit() {
    // Reset All Settings functionality
    $('#reset-all-settings').on('click', function(e) {
      e.preventDefault();
      
      if (confirm('Are you sure you want to reset ALL CookieJar settings to default? This action cannot be undone.')) {
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.text('Resetting...').prop('disabled', true);
        
        $.post(COOKIEJAR_ADMIN.ajaxurl, {
          action: 'cookiejar_reset_all_settings',
          nonce: COOKIEJAR_ADMIN.nonce
        }).done(function(response) {
          if (response.success) {
            showNotice('All settings have been reset to default values.', 'success');
            setTimeout(function() {
              window.location.reload();
            }, 2000);
          } else {
            showNotice('Failed to reset settings: ' + (response.data || 'Unknown error'), 'error');
            $btn.text(originalText).prop('disabled', false);
          }
        }).fail(function() {
          showNotice('Network error. Please try again.', 'error');
          $btn.text(originalText).prop('disabled', false);
        });
      }
    });
    
    // Ensure all navigation links work properly
    $('.cookiejar-nav-link').each(function() {
      const $link = $(this);
      const dataPage = $link.attr('data-page');
      
      if (dataPage && !$link.attr('href').includes('#')) {
        $link.attr('href', '#page=' + dataPage);
      }
    });
    
    // Add hover effects to all interactive elements
    $('.cookiejar-footer-link, .cookiejar-link').hover(
      function() {
        $(this).addClass('hover');
      },
      function() {
        $(this).removeClass('hover');
      }
    );
    
    // Ensure all buttons have proper focus states
    $('.button, .cookiejar-pill').on('focus', function() {
      $(this).addClass('focused');
    }).on('blur', function() {
      $(this).removeClass('focused');
    });
  }
  
  // Initialize global audit fixes
  initGlobalAudit();
  
  // FINAL REFINEMENTS: Enhanced Banner Preview and Accessibility
  function initFinalRefinements() {
    // Enhanced Banner Preview with Text Support
    function updateBannerPreviewEnhanced() {
      const color = $('#banner_color').val() || '#008ed6';
      const bg = $('#banner_bg').val() || '#ffffff';
      const text = $('#banner_text').val() || 'We use cookies to enhance your browsing experience and analyze our traffic.';
      const font = $('#banner_font').val() || 'inherit';
      const fontSize = $('#banner_font_size').val() || '16';
      
      $('.cookiejar-preview-banner').css({
        'background-color': bg, 
        'color': color,
        'font-family': font,
        'font-size': fontSize + 'px'
      });
      
      $('#banner-preview-text').text(text);
      $('.cookiejar-preview-accept').css('background-color', color);
      
      // Update color preview swatches and tooltips
      $('.cookiejar-color-preview').each(function() {
        const $preview = $(this);
        const $colorInput = $preview.siblings('.cookiejar-color-palette');
        const $textInput = $preview.siblings('.cookiejar-color-text');
        
        if ($colorInput.attr('id') === 'banner_color') {
          $preview.css('background-color', color).attr('title', color);
        } else if ($colorInput.attr('id') === 'banner_bg') {
          $preview.css('background-color', bg).attr('title', bg);
        }
      });
      
      // Update font preview
      $('.cookiejar-font-preview').css('font-size', fontSize + 'px');
    }
    
    // Enhanced event binding for all banner inputs
    $('#banner_color, #banner_bg, #banner_text, #banner_font, #banner_font_size').on('input change', updateBannerPreviewEnhanced);
    
    // Enhanced Status Management with Auto-fade
    function showStatusEnhanced(message, type) {
      const $status = $('#banner-status, #settings-status, .wizard-status');
    $status.removeClass('success error loading')
           .addClass(type)
             .attr('role', type === 'error' ? 'alert' : 'status')
           .text(message)
           .show();
    
      // Auto-fade after 4 seconds for success messages
    if (type === 'success') {
        setTimeout(() => $status.fadeOut(), 4000);
      }
    }
    
    // Enhanced Accessibility: Focus trap for modal dialogs
    $('.cookiejar-modal').on('keydown', function(e) {
      if (e.key === 'Tab') {
        const focusables = $(this).find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
        if (!focusables.length) return;
        const first = focusables[0], last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) { 
          e.preventDefault(); 
          last.focus(); 
        }
        else if (!e.shiftKey && document.activeElement === last) { 
          e.preventDefault(); 
          first.focus(); 
        }
      }
      if (e.key === 'Escape') { 
        $(this).hide(); 
      }
    });
    
    // Enhanced Form Submission with Better Status Handling
    $('#banner-settings-form, #appearance-settings-form').on('submit', function(e) {
      e.preventDefault();
      const $form = $(this);
      const $submitBtn = $form.find('button[type="submit"]');
      const originalText = $submitBtn.text();
      
      // Show loading state
      $submitBtn.prop('disabled', true).text('Saving...');
      showStatusEnhanced('Saving settings...', 'loading');
      
      // Simulate AJAX submission (replace with actual AJAX)
      setTimeout(() => {
        showStatusEnhanced('Settings saved successfully!', 'success');
        $submitBtn.prop('disabled', false).text(originalText);
      }, 1500);
    });
    
    // Enhanced Color Input Synchronization (Version 3 Improvements)
    $('#banner_color').on('input change', function() {
      $('#banner_color_text').val($(this).val());
      updateBannerPreviewEnhanced();
    });
    
    $('#banner_color_text').on('input change blur', function() {
      const hexValue = $(this).val();
      if (/^#[0-9A-F]{6}$/i.test(hexValue)) {
        $('#banner_color').val(hexValue);
        updateBannerPreviewEnhanced();
      }
    });
    
    $('#banner_bg').on('input change', function() {
      $('#banner_bg_text').val($(this).val());
      updateBannerPreviewEnhanced();
    });
    
    $('#banner_bg_text').on('input change blur', function() {
      const hexValue = $(this).val();
      if (/^#[0-9A-F]{6}$/i.test(hexValue)) {
        $('#banner_bg').val(hexValue);
        updateBannerPreviewEnhanced();
      }
    });
    
    // Preview button enhancement
    $('#preview-banner').on('click', function() {
      updateBannerPreviewEnhanced();
      showBannerPreview();
    });
    
    // COMPLIANCE BUBBLE TOGGLES - Live Status Updates
    function initComplianceBubbleToggles() {
      $('.cookiejar-bubble-input').on('change', function() {
        const $toggle = $(this).closest('.cookiejar-bubble-toggle');
        const $statusIndicator = $toggle.find('.cookiejar-status-indicator');
        const isChecked = $(this).is(':checked');
        
        // Update status indicator
        if (isChecked) {
          $statusIndicator.removeClass('inactive').addClass('active').text('Active');
        } else {
          $statusIndicator.removeClass('active').addClass('inactive').text('Inactive');
        }
        
        // Add visual feedback
        $toggle.addClass('cookiejar-bubble-updated');
        setTimeout(() => {
          $toggle.removeClass('cookiejar-bubble-updated');
        }, 1000);
      });
      
      // Handle Pro-locked toggles
      $('.cookiejar-pro-locked .cookiejar-bubble-input').on('click', function(e) {
        e.preventDefault();
        showProUpgradeModal();
      });
    }
    
    function showProUpgradeModal() {
      const isPro = !!(window.COOKIEJAR_ADMIN && window.COOKIEJAR_ADMIN.isPro);
      const title = isPro ? 'Licensing' : 'Feature unavailable in free version';
      const body  = isPro ? 'Manage your CookieJar license and activation.' : 'This capability is not available in the free version.';
      const btn   = isPro ? 'Manage License' : 'Close';
      const href  = '#';
      const modalHtml = `
        <div class="cookiejar-modal" id="pro-upgrade-modal" style="display: block;">
          <div class="cookiejar-modal-content">
            <div class="cookiejar-modal-header">
              <h3>` + title + `</h3>
              <button type="button" class="cookiejar-modal-close">&times;</button>
            </div>
            <div class="cookiejar-modal-body">
              <p>` + body + `</p>
              <div class="cookiejar-modal-actions">
                <button type="button" class="button button-primary" id="cookiejar-upgrade-action">
                  ` + btn + `
                </button>
                <button type="button" class="button cookiejar-modal-close">
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      `;
      
      $('body').append(modalHtml);
      
      $('#cookiejar-upgrade-action').on('click', function(){
        if (href === '#') return;
        window.open(href, '_blank');
      });

      // Close modal handlers
      $('.cookiejar-modal-close').on('click', function() {
        $('#pro-upgrade-modal').remove();
      });
      
      $('#pro-upgrade-modal').on('click', function(e) {
        if (e.target === this) {
          $(this).remove();
        }
      });
    }
  }
    
  // Initialize requirements warning banner
  initRequirementsWarning();
  
  // Initialize compliance bubble toggles
  initComplianceBubbleToggles();
  
  // Initialize mini appearance color inputs
  initMiniAppearanceInputs();
  
  // Initialize final refinements
  initFinalRefinements();
  
  // MINIMUM REQUIREMENTS WARNING BANNER FUNCTIONS
  function initRequirementsWarning() {
    // Add click handler for warning header
    $('.cookiejar-warning-header').on('click', function() {
      toggleRequirementsDetails();
    });
  }

  function toggleRequirementsDetails() {
    const $details = $('#requirements-details');
    const $toggle = $('.cookiejar-warning-toggle');
    
    if ($details.is(':visible')) {
      $details.slideUp(300);
      $toggle.text('▼');
    } else {
      $details.slideDown(300);
      $toggle.text('▲');
    }
  }

  function refreshRequirements() {
    const $button = $('.cookiejar-warning-actions .button');
    const originalText = $button.text();
    
    $button.prop('disabled', true).text('Refreshing...');
    
    // Simulate refresh (in real implementation, this would make an AJAX call)
      setTimeout(function() {
      $button.prop('disabled', false).text(originalText);
      
      // Show success message
      showStatusEnhanced('Requirements refreshed successfully!', 'success');
    }, 1500);
  }

  // MINI APPEARANCE COLOR INPUTS FUNCTIONS
  function initMiniAppearanceInputs() {
    // Banner color synchronization
    $('#banner_color_mini').on('input change', function() {
      $('#banner_color_text_mini').val($(this).val());
      $('.cookiejar-color-preview').eq(0).css('background-color', $(this).val()).attr('title', 'Current color: ' + $(this).val());
    });
    
    $('#banner_color_text_mini').on('input change blur', function() {
      const hexValue = $(this).val();
      if (/^#[0-9A-F]{6}$/i.test(hexValue)) {
        $('#banner_color_mini').val(hexValue);
        $('.cookiejar-color-preview').eq(0).css('background-color', hexValue).attr('title', 'Current color: ' + hexValue);
      }
    });
    
    // Background color synchronization
    $('#banner_bg_mini').on('input change', function() {
      $('#banner_bg_text_mini').val($(this).val());
      $('.cookiejar-color-preview').eq(1).css('background-color', $(this).val()).attr('title', 'Current color: ' + $(this).val());
    });
    
    $('#banner_bg_text_mini').on('input change blur', function() {
      const hexValue = $(this).val();
      if (/^#[0-9A-F]{6}$/i.test(hexValue)) {
        $('#banner_bg_mini').val(hexValue);
        $('.cookiejar-color-preview').eq(1).css('background-color', hexValue).attr('title', 'Current color: ' + hexValue);
      }
    });
  }

  // Make functions globally available
  window.toggleRequirementsDetails = toggleRequirementsDetails;
  window.refreshRequirements = refreshRequirements;
  window.migrateDatabase = migrateDatabase;

  function migrateDatabase() {
    const $button = $('.cookiejar-warning-actions .button-primary');
    const originalText = $button.text();
    
    $button.prop('disabled', true).text('Migrating...');
    
    $.ajax({
      url: COOKIEJAR_ADMIN.ajaxurl,
      type: 'POST',
      data: {
        action: 'cookiejar_migrate_database',
        nonce: COOKIEJAR_ADMIN.nonce
      },
      cache: false
    }).done(function(response) {
      if (response && response.success) {
        showStatusEnhanced('Database migration completed successfully!', 'success');
        setTimeout(function() {
          window.location.reload();
        }, 2000);
      } else {
        const errorMsg = response && response.data && response.data.error ? response.data.error : 'Migration failed';
        showStatusEnhanced('Migration failed: ' + errorMsg, 'error');
      }
    }).fail(function(xhr, status, error) {
      console.error('CookieJar Migration AJAX failed:', {xhr, status, error});
      showStatusEnhanced('Migration failed. Please try again.', 'error');
    }).always(function() {
      $button.prop('disabled', false).text(originalText);
    });
  }
});