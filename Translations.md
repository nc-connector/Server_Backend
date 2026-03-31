# Translations

This app is localized via Nextcloud app l10n files (`nc_connector_backend/l10n/*.json` and `*.js`).

## Available locales

| Locale file | Language |
|---|---|
| `en` | English (default) |
| `de` | Deutsch |
| `fr` | Français |
| `cs` | Čeština |
| `es` | Español |
| `hu` | Magyar |
| `it` | Italiano |
| `ja` | 日本語 |
| `nl` | Nederlands |
| `pl` | Polski |
| `pt_BR` | Português (Brasil) |
| `pt_PT` | Português (Portugal) |
| `ru` | Русский |
| `zh_CN` | 简体中文 |
| `zh_TW` | 繁體中文 |

## UI translation audit (visible texts + tooltips)

- Scope: all visible UI strings used in `*.js`/`*.php` (including tooltip texts).
- Extracted visible keys: audited against the current UI sources.
- Coverage result: all locales above contain all visible UI keys (`missing=0`).
- File-level check: no empty translations for the audited visible UI keys.
- Important: this check validates coverage and empty values, not linguistic quality. Native-language review is still required for semantic correctness.
- Additional update: new template-related keys were translated for all locales (`en`, `de`, `fr`, `cs`, `es`, `hu`, `it`, `ja`, `nl`, `pl`, `pt_BR`, `pt_PT`, `ru`, `zh_CN`, `zh_TW`).
- Additional update: the visible seat/report texts that were still left in English were localized for every supported locale.

## Template editor language helper

- The editor modal includes a **Languages** dropdown.
- It translates the built-in template text fragments between all supported locales above.
- Variables such as `{URL}` and `{PASSWORD}` are preserved.
- Links and URLs are left untouched on purpose.
