#!/usr/bin/env node

import { readFileSync, readdirSync } from 'node:fs';
import { join, relative, sep } from 'node:path';

const root = process.cwd();
const appRoot = join(root, 'ncc_backend_4mc');
const l10nRoot = join(appRoot, 'l10n');
const failures = [];

function readJson(path) {
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch (error) {
    failures.push(`${relative(root, path)} invalid JSON: ${error.message}`);
    return null;
  }
}

function sortedKeys(value) {
  return Object.keys(value || {}).sort((left, right) => left.localeCompare(right));
}

function sameKeys(left, right) {
  const leftKeys = sortedKeys(left);
  const rightKeys = sortedKeys(right);
  return leftKeys.length === rightKeys.length && leftKeys.every((key, index) => key === rightKeys[index]);
}

function parseL10nJs(path) {
  const content = readFileSync(path, 'utf8');
  const match = content.match(/OC\.L10N\.register\(\s*['"]ncc_backend_4mc['"]\s*,\s*(\{[\s\S]*?\})\s*,\s*['"]nplurals=/);
  if (!match) {
    failures.push(`${relative(root, path)} does not contain an OC.L10N.register object`);
    return null;
  }
  try {
    return JSON.parse(match[1]);
  } catch (error) {
    failures.push(`${relative(root, path)} invalid embedded translation object: ${error.message}`);
    return null;
  }
}

function collectFiles(directory, predicate) {
  const files = [];
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);
    if (path.includes(`${sep}vendor${sep}`) || path.includes(`${sep}l10n${sep}`)) {
      continue;
    }
    if (entry.isDirectory()) {
      files.push(...collectFiles(path, predicate));
    } else if (entry.isFile() && predicate(path)) {
      files.push(path);
    }
  }
  return files.sort();
}

function decodeJsString(value) {
  return value
    .replace(/\\'/g, "'")
    .replace(/\\"/g, '"')
    .replace(/\\\\/g, '\\')
    .replace(/\\n/g, '\n')
    .replace(/\\r/g, '\r')
    .replace(/\\t/g, '\t');
}

function collectStaticTranslationKeys() {
  const keys = new Map();
  const files = collectFiles(appRoot, (path) => path.endsWith('.js') || path.endsWith('.php'));
  const patterns = [
    /\btr\(\s*(['"])((?:\\.|(?!\1).)*)\1\s*[),]/g,
    /->t\(\s*(['"])((?:\\.|(?!\1).)*)\1\s*[),]/g,
  ];

  for (const file of files) {
    const content = readFileSync(file, 'utf8');
    for (const pattern of patterns) {
      let match = pattern.exec(content);
      while (match) {
        const key = decodeJsString(match[2]);
        if (key !== '') {
          const refs = keys.get(key) || [];
          refs.push(relative(root, file));
          keys.set(key, refs);
        }
        match = pattern.exec(content);
      }
    }
  }
  return keys;
}

const enPath = join(l10nRoot, 'en.json');
const enJson = readJson(enPath);
const enTranslations = enJson?.translations || {};
const expectedKeys = sortedKeys(enTranslations);

for (const entry of readdirSync(l10nRoot, { withFileTypes: true }).filter((item) => item.isFile() && item.name.endsWith('.json'))) {
  const locale = entry.name.replace(/\.json$/, '');
  const jsonPath = join(l10nRoot, entry.name);
  const jsPath = join(l10nRoot, `${locale}.js`);
  const json = readJson(jsonPath);
  const translations = json?.translations || {};

  if (!sameKeys(enTranslations, translations)) {
    const keys = new Set(sortedKeys(translations));
    const missing = expectedKeys.filter((key) => !keys.has(key));
    const extra = sortedKeys(translations).filter((key) => !Object.prototype.hasOwnProperty.call(enTranslations, key));
    failures.push(`${relative(root, jsonPath)} key drift: missing=${missing.join(', ') || 'none'} extra=${extra.join(', ') || 'none'}`);
  }

  const jsTranslations = parseL10nJs(jsPath);
  if (jsTranslations && !sameKeys(translations, jsTranslations)) {
    failures.push(`${relative(root, jsPath)} keys differ from ${entry.name}`);
  }
  if (jsTranslations) {
    for (const key of sortedKeys(translations)) {
      if (translations[key] !== jsTranslations[key]) {
        failures.push(`${relative(root, jsPath)} value differs from ${entry.name} for "${key}"`);
        break;
      }
    }
  }
}

const staticKeys = collectStaticTranslationKeys();
for (const [key, refs] of staticKeys.entries()) {
  if (!Object.prototype.hasOwnProperty.call(enTranslations, key)) {
    failures.push(`Missing en.json translation key "${key}" used in ${[...new Set(refs)].join(', ')}`);
  }
}

if (failures.length > 0) {
  console.error('Backend l10n checks failed:');
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log(`Backend l10n checks passed (${expectedKeys.length} keys, ${staticKeys.size} static usages).`);
