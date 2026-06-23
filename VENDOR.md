# Third-Party Dependencies

## TinyMCE

- Package: `tinymce`
- Version: `7.8.0`
- Source: https://registry.npmjs.org/tinymce/-/tinymce-7.8.0.tgz
- Upstream repository: https://github.com/tinymce/tinymce
- Included files: `ncc_backend_4mc/js/vendor/tinymce/**`
- License: GPL-2.0-or-later (see upstream `LICENSE.md`)
- Usage in this backend:
  - Admin template editor for Share, password-mail, Talk, and email-signature templates
  - Loaded by `ncc_backend_4mc/templates/adminSettings.php`
  - Used by `ncc_backend_4mc/js/ncc_backend_4mc-adminSettings.js`

## DOMPurify

- Package: `dompurify`
- Version: `3.4.7`
- Source: https://registry.npmjs.org/dompurify/-/dompurify-3.4.7.tgz
- Upstream repository: https://github.com/cure53/DOMPurify
- Included file: `ncc_backend_4mc/js/vendor/dompurify/purify.js`
- License: Apache-2.0 OR MPL-2.0
- Usage in this backend:
  - Admin-side sanitization of rich template drafts before preview and save
  - Loaded by `ncc_backend_4mc/templates/adminSettings.php`
  - Used by `ncc_backend_4mc/js/templateSanitizer.js`
