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
- Version: `3.4.17`
- Source: https://registry.npmjs.org/dompurify/-/dompurify-3.4.17.tgz
- Source integrity (SHA-512): `sha512-B/pSlYVHQ58kqISD/yDjuJ1yfzI34m1gF4lAod2bwSRhiN4K5Cl3H8Af7hqB2PmTL911tdEudnnXn5FnhRoz0w==`
- Upstream repository: https://github.com/cure53/DOMPurify
- Upstream release: https://github.com/cure53/DOMPurify/releases/tag/3.4.17
- Included file: `ncc_backend_4mc/js/vendor/dompurify/purify.js` (unchanged UMD browser distribution from `dist/purify.js`)
- SHA-256: `A7B6DC750251C79C95592D6ABF8908BCECB51319B0B5C4194B82222566ED29B9`
- License: Apache-2.0 OR MPL-2.0
- Usage in this backend:
  - Admin-side sanitization of rich template drafts before preview and save
  - Loaded by `ncc_backend_4mc/templates/adminSettings.php`
  - Used by `ncc_backend_4mc/js/templateSanitizer.js`
