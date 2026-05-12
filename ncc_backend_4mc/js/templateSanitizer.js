/**
 * Sanitizes admin template drafts with the same DOMPurify rules as the mail add-ons.
 */
(function(window) {
  'use strict';

  const FORBID_TAGS = [
    'script',
    'style',
    'iframe',
    'object',
    'embed',
    'link',
    'meta',
    'form',
    'input',
    'button',
    'textarea',
    'select',
    'option',
    'svg',
    'math',
  ];

  const ADD_ATTR = [
    'style',
    'target',
    'rel',
    'role',
    'width',
    'height',
    'colspan',
    'rowspan',
    'cellpadding',
    'cellspacing',
    'align',
    'valign',
  ];

  const ADD_TAGS = [
    'section',
    'article',
    'header',
    'footer',
  ];

  function getPurify() {
    if (window.DOMPurify && typeof window.DOMPurify.sanitize === 'function') {
      return window.DOMPurify;
    }
    throw new Error('template_sanitizer_unavailable');
  }

  function addRelToken(anchor, token) {
    const rel = String(anchor.getAttribute('rel') || '')
      .toLowerCase()
      .split(/\s+/)
      .filter(Boolean);
    if (!rel.includes(token)) {
      rel.push(token);
    }
    anchor.setAttribute('rel', rel.join(' '));
  }

  function normalizeLinks(html) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(String(html || ''), 'text/html');
    doc.querySelectorAll('a[target="_blank"]').forEach((anchor) => {
      addRelToken(anchor, 'noopener');
      addRelToken(anchor, 'noreferrer');
    });
    return doc.body ? doc.body.innerHTML : String(html || '');
  }

  function sanitizeHtml(value) {
    const dirty = String(value || '').trim();
    if (dirty === '') {
      return '';
    }
    const clean = getPurify().sanitize(dirty, {
      USE_PROFILES: { html: true },
      ALLOW_DATA_ATTR: false,
      FORBID_TAGS,
      ADD_ATTR,
      ADD_TAGS,
    });
    return normalizeLinks(String(clean || ''));
  }

  window.NCCBTemplateSanitizer = {
    sanitizeHtml,
  };
})(window);
