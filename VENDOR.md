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
- Version: `3.4.13`
- Source: https://registry.npmjs.org/dompurify/-/dompurify-3.4.13.tgz
- Source integrity (SHA-512): `sha512-2vmYIoqjze2d+kakP8S/nS5shfsl587kzwEjcGlTdiksUVgFHnFCsLYDVj/JNqJVOQZGSYBTmuycv0PodwmnMQ==`
- Upstream repository: https://github.com/cure53/DOMPurify
- Upstream release: https://github.com/cure53/DOMPurify/releases/tag/3.4.13
- Included file: `ncc_backend_4mc/js/vendor/dompurify/purify.js` (unchanged UMD browser distribution from `dist/purify.js`)
- SHA-256: `DD9516732E75EF096EBC8347F0D7F08C7B969C409ED050C85560F214A0F704F9`
- License: Apache-2.0 OR MPL-2.0
- Usage in this backend:
  - Admin-side sanitization of rich template drafts before preview and save
  - Loaded by `ncc_backend_4mc/templates/adminSettings.php`
  - Used by `ncc_backend_4mc/js/templateSanitizer.js`
