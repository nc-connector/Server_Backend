#!/usr/bin/env node

import { readdirSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { spawnSync } from 'node:child_process';

const root = process.cwd();
const jsRoot = join(root, 'ncc_backend_4mc', 'js');
const excludedParts = [
  `${sep}vendor${sep}`,
  `${sep}l10n${sep}`,
];

function collectJsFiles(directory) {
  const files = [];
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);
    if (excludedParts.some((part) => path.includes(part))) {
      continue;
    }
    if (entry.isDirectory()) {
      files.push(...collectJsFiles(path));
      continue;
    }
    if (entry.isFile() && entry.name.endsWith('.js')) {
      files.push(path);
    }
  }
  return files.sort();
}

const failures = [];
for (const file of collectJsFiles(jsRoot)) {
  const result = spawnSync(process.execPath, ['--check', file], {
    encoding: 'utf8',
  });
  if (result.status !== 0) {
    const output = `${result.stdout || ''}${result.stderr || ''}`.trim();
    failures.push(`${relative(root, file)}\n${output}`);
  }
}

if (failures.length > 0) {
  console.error('Backend JS syntax checks failed:');
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log('Backend JS syntax checks passed.');
