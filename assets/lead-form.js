/**
 * ============================================================
 *  lead-form.js — OpenFlow 官网表单 · 真实线索提交
 * ============================================================
 *  把 5 个正式页面（index / capability / courses /
 *  flow-community / about）的表单从"纯前端演示"改为真实提交：
 *  POST form-handler.php（同目录相对路径，服务器根目录部署），
 *  成功 / 失败均有明确反馈。
 *
 *  接入步骤（每页一次）：
 *    1. <form ...> 标签上加属性  data-lead-form
 *    2. </body> 前引入：<script src="assets/lead-form.js" defer></script>
 *    3. 删除旧"演示提交"处理器（site.js 或页面内联脚本里的
 *       preventDefault + 弹提示那段），避免与新脚本抢提交
 *
 *  行为说明：
 *    - 字段按 name 自动收集；别名归一与 form-handler.php 完全一致
 *    - honeypot（input[name=website]，人类不可见）自动注入，
 *      机器人填了就假装成功，不落库、不发信
 *    - page（来源页面）自动取当前路径
 *    - 提交期间按钮禁用并显示"提交中…"
 *    - 成功提示 6 秒后自动消失；失败提示保留到下次提交
 * ============================================================
 */
(function () {
  'use strict';

  /* 与 form-handler.php 同目录的相对路径：本地预览与线上行为一致 */
  var ENDPOINT = 'form-handler.php';

  /* 字段别名：与 form-handler.php 保持同一套映射 */
  var FIELD_ALIASES = {
    mobile: 'phone',
    tel: 'phone',
    contact: 'phone',
    username: 'name',
    real_name: 'name',
    company_name: 'company',
    content: 'message',
    notes: 'message',
    note: 'message',
    title: 'job',
    source: 'page',
    page_name: 'page'
  };

  /* 每个标准字段可能对应页面里的多个实际 name（用于校验与错误定位） */
  var FIELD_GROUPS = {
    name: ['name', 'username', 'real_name'],
    phone: ['phone', 'mobile', 'tel', 'contact'],
    email: ['email'],
    message: ['message', 'content', 'notes']
  };

  var I18N = {
    sending: '提交中…',
    success: '提交成功，我们会在 1 个工作日内与您联系。',
    invalid: '表单有项未通过校验，请检查后重新提交。',
    serverFail: '提交失败，请稍后重试。',
    networkFail: '网络异常，提交未成功。请检查网络后重试，或致电联系我们。',
    nameLen: '称呼长度需在 2–60 字之间。',
    requiredName: '请填写您的称呼。',
    requiredPhone: '请填写联系电话。',
    badPhone: '请填写有效的电话号码（手机或座机）。',
    badEmail: '请填写有效的邮箱地址。'
  };

  var PHONE_RE = /^[+]?[0-9][0-9\s\-()]{5,19}$/;
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var SUCCESS_HIDE_MS = 6000;

  /* ---------------- 样式（仅注入一次） ---------------- */
  var STATUS_CSS = [
    '.lead-form-status{display:none;margin-top:14px;padding:12px 14px;border-radius:10px;',
    'font-size:14px;line-height:1.6;text-align:left;}',
    '.lead-form-status.is-visible{display:block;}',
    '.lead-form-status.is-sending{background:oklch(97% 0.004 240);color:oklch(38% 0.02 240);border:1px solid oklch(90% 0.01 240);}',
    '.lead-form-status.is-success{background:oklch(96% 0.035 155);color:oklch(34% 0.09 155);border:1px solid oklch(87% 0.07 155);}',
    '.lead-form-status.is-error{background:oklch(96% 0.02 25);color:oklch(42% 0.13 25);border:1px solid oklch(88% 0.05 25);}',
    '.lead-form-field-error{display:block;margin-top:4px;font-size:12px;line-height:1.5;color:oklch(45% 0.13 25);}'
  ].join('');

  function ensureStyles() {
    if (document.getElementById('lead-form-status-css')) return;
    var style = document.createElement('style');
    style.id = 'lead-form-status-css';
    style.textContent = STATUS_CSS;
    document.head.appendChild(style);
  }

  /* ---------------- 工具 ---------------- */
  function findField(form, norm) {
    var names = FIELD_GROUPS[norm] || [norm];
    for (var i = 0; i < names.length; i++) {
      var el = form.querySelector('[name="' + names[i] + '"]');
      if (el) return el;
    }
    return null;
  }

  function firstValue(form, norm) {
    var el = findField(form, norm);
    if (el && el.value != null && String(el.value).trim() !== '') {
      return String(el.value).trim();
    }
    return '';
  }

  function showStatus(form, text, type) {
    if (form._leadTimer) { clearTimeout(form._leadTimer); form._leadTimer = null; }
    var box = form.querySelector('.lead-form-status');
    if (!box) return;
    box.textContent = text;
    box.className = 'lead-form-status is-visible is-' + (type || 'error');
  }

  function clearStatus(form) {
    if (form._leadTimer) { clearTimeout(form._leadTimer); form._leadTimer = null; }
    var box = form.querySelector('.lead-form-status');
    if (box) { box.className = 'lead-form-status'; box.textContent = ''; }
    var errs = form.querySelectorAll('.lead-form-field-error');
    for (var i = 0; i < errs.length; i++) errs[i].remove();
  }

  function markFieldError(form, norm, message) {
    var el = findField(form, norm);
    if (!el) return;
    var err = document.createElement('small');
    err.className = 'lead-form-field-error';
    err.textContent = message;
    el.parentNode.insertBefore(err, el.nextSibling);
  }

  /* ---------------- 校验（与 PHP 端规则一致） ---------------- */
  function validate(form) {
    var errors = [];
    var name = firstValue(form, 'name');
    var phone = firstValue(form, 'phone');
    var email = firstValue(form, 'email');

    if (name === '') { errors.push({ field: 'name', message: I18N.requiredName }); }
    else if (name.length < 2 || name.length > 60) { errors.push({ field: 'name', message: I18N.nameLen }); }

    if (phone === '') { errors.push({ field: 'phone', message: I18N.requiredPhone }); }
    else if (!PHONE_RE.test(phone)) { errors.push({ field: 'phone', message: I18N.badPhone }); }

    if (email !== '' && !EMAIL_RE.test(email)) { errors.push({ field: 'email', message: I18N.badEmail }); }

    return errors;
  }

  /* ---------------- honeypot / 状态条 ---------------- */
  function ensureHoneypot(form) {
    if (form.querySelector('input[name="website"]')) return;
    var hp = document.createElement('input');
    hp.type = 'text';
    hp.name = 'website';
    hp.tabIndex = -1;
    hp.autocomplete = 'off';
    hp.setAttribute('aria-hidden', 'true');
    hp.style.cssText = 'position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;overflow:hidden;';
    form.appendChild(hp);
  }

  function ensureStatusBox(form) {
    if (form.querySelector('.lead-form-status')) return;
    var box = document.createElement('div');
    box.className = 'lead-form-status';
    box.setAttribute('role', 'status');
    box.setAttribute('aria-live', 'polite');
    form.appendChild(box);
  }

  /* ---------------- 提交 ---------------- */
  function onSubmit(e) {
    e.preventDefault();
    var form = e.currentTarget;
    clearStatus(form);

    var errors = validate(form);
    if (errors.length) {
      for (var i = 0; i < errors.length; i++) {
        markFieldError(form, errors[i].field, errors[i].message);
      }
      showStatus(form, I18N.invalid, 'error');
      var firstBad = form.querySelector('.lead-form-field-error');
      var firstField = firstBad ? firstBad.previousElementSibling : null;
      if (firstField && typeof firstField.focus === 'function') firstField.focus();
      return;
    }

    var btn = form.querySelector('button[type="submit"], input[type="submit"]');
    var btnLabel = btn && btn.tagName === 'BUTTON' ? btn.innerHTML : null;

    function setBusy(busy) {
      if (!btn) return;
      btn.disabled = busy;
      if (busy && btn.tagName === 'BUTTON') btn.innerHTML = I18N.sending;
      else if (!busy && btn.tagName === 'BUTTON' && btnLabel) btn.innerHTML = btnLabel;
    }

    setBusy(true);
    showStatus(form, I18N.sending, 'sending');

    var fd = new FormData(form);
    if (!form.querySelector('[name="page"]')) {
      fd.append('page', location.pathname || 'index.html');
    }

    fetch(ENDPOINT, {
      method: 'POST',
      body: fd,
      headers: { 'Accept': 'application/json' }
    })
      .then(function (resp) {
        return resp.json().catch(function () {
          return {};
        }).then(function (data) {
          return { ok: resp.ok, data: data };
        });
      })
      .then(function (r) {
        setBusy(false);
        if (r.ok && r.data && r.data.ok) {
          showStatus(form, r.data.message || I18N.success, 'success');
          form.reset();
          // 跳转到感谢页
          var tyUrl = (r.data && r.data.thank_you_url) || '/thank-you';
          form._leadTimer = setTimeout(function () {
            window.location.href = tyUrl;
          }, 900);
        } else if (r.data && r.data.errors) {
          showStatus(form, r.data.message || I18N.invalid, 'error');
          Object.keys(r.data.errors).forEach(function (field) {
            markFieldError(form, field, r.data.errors[field]);
          });
        } else {
          showStatus(form, (r.data && r.data.message) || I18N.serverFail, 'error');
        }
      })
      .catch(function () {
        setBusy(false);
        showStatus(form, I18N.networkFail, 'error');
      });
  }

  /* ---------------- 初始化 ---------------- */
  function init() {
    ensureStyles();
    var forms = document.querySelectorAll('form[data-lead-form]');
    for (var i = 0; i < forms.length; i++) {
      var form = forms[i];
      form.setAttribute('novalidate', 'novalidate');
      ensureHoneypot(form);
      ensureStatusBox(form);
      form.addEventListener('submit', onSubmit);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
