/**
 * Dynamic Content Engine — 前端规则执行器
 * 
 * 使用方式：
 *   <script src="/assets/dynamic-content.js" data-dynamic-page="index"></script>
 *   <script src="/assets/dynamic-content.js" data-dynamic-page="article" data-dynamic-page-id="article_xxx"></script>
 * 
 * 或手动调用：
 *   DynamicContent.apply('index');
 */
(function() {
  var DC = {
    rules: [],
    params: {},
    page: '',
    pageId: '',

    /**
     * 初始化：从服务器获取匹配的规则并执行
     */
    init: function(page, pageId) {
      this.page = page || 'global';
      this.pageId = pageId || '';
      this.params = this.getUrlParams();

      // 如果有 URL 参数，直接从 URL 获取（避免额外请求）
      if (Object.keys(this.params).length > 0) {
        this.fetchAndApply();
      } else {
        // 无参数时也检查是否有全局规则
        this.fetchAndApply();
      }
    },

    /**
     * 从服务器获取规则
     */
    fetchAndApply: function() {
      var self = this;
      var url = '/api/dynamic-content.php?action=rules&page=' + encodeURIComponent(this.page);
      if (this.pageId) url += '&page_id=' + encodeURIComponent(this.pageId);

      // 添加 URL 参数
      var qs = window.location.search.substring(1);
      if (qs) url += '&' + qs;

      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.onload = function() {
        if (xhr.status === 200) {
          try {
            var data = JSON.parse(xhr.responseText);
            if (data.ok && data.rules && data.rules.length > 0) {
              self.rules = data.rules;
              self.apply();
            }
          } catch(e) {}
        }
      };
      xhr.send();
    },

    /**
     * 应用所有匹配的规则
     */
    apply: function() {
      for (var i = 0; i < this.rules.length; i++) {
        this.applyRule(this.rules[i]);
      }
    },

    /**
     * 应用单条规则
     */
    applyRule: function(rule) {
      if (!rule.actions) return;
      for (var j = 0; j < rule.actions.length; j++) {
        var action = rule.actions[j];
        try {
          this.applyAction(action, rule.id);
        } catch(e) {}
      }
    },

    /**
     * 执行单个动作
     */
    applyAction: function(action, ruleId) {
      var selector = action.selector;
      if (!selector) return;

      var elements = document.querySelectorAll(selector);
      if (elements.length === 0) return;

      switch (action.type) {
        case 'show_card':
          for (var i = 0; i < elements.length; i++) {
            elements[i].style.display = '';
            elements[i].removeAttribute('hidden');
            elements[i].classList.remove('dc-hidden');
          }
          break;

        case 'hide_card':
          for (var i = 0; i < elements.length; i++) {
            elements[i].style.display = 'none';
            elements[i].classList.add('dc-hidden');
          }
          break;

        case 'replace_text':
          var find = action.text_find || '';
          var replace = action.text_replace || '';
          if (!find) break;
          for (var i = 0; i < elements.length; i++) {
            this.replaceTextInElement(elements[i], find, replace);
          }
          break;

        case 'add_class':
          for (var i = 0; i < elements.length; i++) {
            elements[i].classList.add('dc-highlight');
          }
          break;

        case 'change_bg':
          for (var i = 0; i < elements.length; i++) {
            elements[i].style.background = action.selector.includes('card') ? 
              'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : '#f0f0ff';
          }
          break;
      }

      // 追踪点击（可选）
      if (ruleId && action.type !== 'show_card') {
        this.trackClick(ruleId, action.type, selector);
      }
    },

    /**
     * 在元素内替换文字（保留 HTML 结构）
     */
    replaceTextInElement: function(el, find, replace) {
      if (el.nodeType === Node.TEXT_NODE) {
        if (el.textContent.indexOf(find) !== -1) {
          el.textContent = el.textContent.split(find).join(replace);
        }
      } else {
        for (var i = 0; i < el.childNodes.length; i++) {
          this.replaceTextInElement(el.childNodes[i], find, replace);
        }
      }
    },

    /**
     * 从 URL 解析参数
     */
    getUrlParams: function() {
      var params = {};
      var search = window.location.search.substring(1);
      if (!search) return params;
      var pairs = search.split('&');
      for (var i = 0; i < pairs.length; i++) {
        var pair = pairs[i].split('=');
        if (pair.length === 2) {
          params[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1].replace(/\+/g, ' '));
        }
      }
      return params;
    },

    /**
     * 追踪点击事件
     */
    trackClick: function(ruleId, actionType, selector) {
      var fd = new FormData();
      fd.append('action', 'track_click');
      fd.append('rule_id', ruleId);
      fd.append('action_type', actionType);
      fd.append('selector', selector);
      fetch('/api/dynamic-content.php', { method: 'POST', body: fd });
    },

    /**
     * 手动设置规则（用于页面内联 JS）
     */
    setRules: function(rules) {
      this.rules = rules;
      this.apply();
    },

    /**
     * 手动添加规则
     */
    addRule: function(rule) {
      this.rules.push(rule);
      this.applyRule(rule);
    }
  };

  // 导出到全局
  window.DynamicContent = DC;

  // 自动初始化
  document.addEventListener('DOMContentLoaded', function() {
    var script = document.querySelector('script[data-dynamic-page]');
    if (script) {
      var page = script.getAttribute('data-dynamic-page') || 'global';
      var pageId = script.getAttribute('data-dynamic-page-id') || '';
      DC.init(page, pageId);
    }
  });
})();
