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
- Version: `3.4.16`
- Source: https://registry.npmjs.org/dompurify/-/dompurify-3.4.16.tgz
- Source integrity (SHA-512): `sha512-sqo+pNp3qRhCIpbgRi1y8Tgk27Bo2Ry7w0dC1NBeNTdZChWjz9Xb/KOoZbRP/R6pQZ80Qw8YhXw13hWWBbMRnQ==`
- Upstream repository: https://github.com/cure53/DOMPurify
- Upstream release: https://github.com/cure53/DOMPurify/releases/tag/3.4.16
- Included file: `ncc_backend_4mc/js/vendor/dompurify/purify.js` (unchanged UMD browser distribution from `dist/purify.js`)
- SHA-256: `ACEFBA6EBC9733869F60C80B519EAEFAD1691EA56022C54A3E6CBE74286D8D46`
- License: Apache-2.0 OR MPL-2.0
- Usage in this backend:
  - Admin-side sanitization of rich template drafts before preview and save
  - Loaded by `ncc_backend_4mc/templates/adminSettings.php`
  - Used by `ncc_backend_4mc/js/templateSanitizer.js`
