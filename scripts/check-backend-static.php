#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$appRoot = $root . DIRECTORY_SEPARATOR . 'ncc_backend_4mc';
$failures = [];

function normalizePath(string $path): string {
	return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
}

function relativePath(string $root, string $path): string {
	$prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	if (str_starts_with($path, $prefix)) {
		return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($prefix)));
	}
	return str_replace(DIRECTORY_SEPARATOR, '/', $path);
}

function addFailure(array &$failures, string $message): void {
	$failures[] = $message;
}

function readTextFile(string $path): string {
	$content = file_get_contents($path);
	if ($content === false) {
		throw new RuntimeException('Could not read ' . $path);
	}
	return $content;
}

/**
 * @return list<string>
 */
function collectFiles(string $directory, array $extensions, array $excludedParts = []): array {
	if (!is_dir($directory)) {
		return [];
	}
	$files = [];
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $file) {
		if (!$file instanceof SplFileInfo || !$file->isFile()) {
			continue;
		}
		$path = $file->getPathname();
		$normalized = normalizePath($path);
		foreach ($excludedParts as $part) {
			if (str_contains($normalized, normalizePath($part))) {
				continue 2;
			}
		}
		if (in_array(strtolower($file->getExtension()), $extensions, true)) {
			$files[] = $path;
		}
	}
	sort($files);
	return $files;
}

function firstLineForPattern(string $content, string $pattern): int {
	if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
		return 0;
	}
	return substr_count(substr($content, 0, (int)$match[0][1]), "\n") + 1;
}

function failOnPattern(array &$failures, string $root, string $path, string $pattern, string $message): void {
	$content = readTextFile($path);
	$line = firstLineForPattern($content, $pattern);
	if ($line > 0) {
		addFailure($failures, relativePath($root, $path) . ':' . $line . ' ' . $message);
	}
}

function requireSubstring(array &$failures, string $root, string $path, string $needle, string $message): void {
	$content = readTextFile($path);
	if (!str_contains($content, $needle)) {
		addFailure($failures, relativePath($root, $path) . ' ' . $message);
	}
}

$phpFiles = collectFiles($appRoot, ['php'], [
	DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
]);
$jsFiles = collectFiles($appRoot . DIRECTORY_SEPARATOR . 'js', ['js'], [
	DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
	DIRECTORY_SEPARATOR . 'l10n' . DIRECTORY_SEPARATOR,
]);

foreach (array_merge($phpFiles, $jsFiles) as $path) {
	failOnPattern($failures, $root, $path, "~img-src\\s+[^\"'\\n;]*\\*~i", 'must not use img-src *');
	failOnPattern($failures, $root, $path, "~img-src\\s+[^\"'\\n;]*https?:~i", 'must not allow direct http(s) images in template CSP');
	failOnPattern($failures, $root, $path, '/addAllowedImageDomain\s*\(\s*[\'"]\*[\'"]\s*\)/', 'must not allow wildcard image domains');
	failOnPattern($failures, $root, $path, '/@\s*(?:unlink|rmdir|mkdir|file|copy|rename|file_get_contents|file_put_contents)\b/', 'must not suppress filesystem errors');
	failOnPattern($failures, $root, $path, '/catch\s*\{/', 'must name caught exceptions or errors');
}

requireSubstring(
	$failures,
	$root,
	$appRoot . '/lib/Service/TemplateSanitizerService.php',
	'url\\(',
	'must block CSS url() in style attributes'
);
requireSubstring(
	$failures,
	$root,
	$appRoot . '/js/templateSanitizer.js',
	'url\\(',
	'must block CSS url() in style attributes'
);

$infoXml = $appRoot . '/appinfo/info.xml';
$previousUseInternalErrors = libxml_use_internal_errors(true);
$info = simplexml_load_file($infoXml);
$xmlErrors = libxml_get_errors();
libxml_clear_errors();
libxml_use_internal_errors($previousUseInternalErrors);
if ($info === false) {
	$detail = $xmlErrors !== [] ? trim($xmlErrors[0]->message) : 'parse failed';
	addFailure($failures, relativePath($root, $infoXml) . ' invalid XML: ' . $detail);
}

foreach (collectFiles($appRoot . DIRECTORY_SEPARATOR . 'l10n', ['json']) as $path) {
	json_decode(readTextFile($path), true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		addFailure($failures, relativePath($root, $path) . ' invalid JSON: ' . json_last_error_msg());
	}
}

$purifyPath = $appRoot . '/js/vendor/dompurify/purify.js';
$vendorPath = $root . '/VENDOR.md';
$purify = readTextFile($purifyPath);
$vendor = readTextFile($vendorPath);
if (preg_match('/DOMPurify\.version\s*=\s*[\'"]([^\'"]+)[\'"]/', $purify, $versionMatch) !== 1) {
	addFailure($failures, relativePath($root, $purifyPath) . ' DOMPurify version not found');
} else {
	$version = $versionMatch[1];
	if (!str_contains($vendor, 'Version: `' . $version . '`')) {
		addFailure($failures, relativePath($root, $vendorPath) . ' DOMPurify version mismatch');
	}
	if (!str_contains($vendor, 'dompurify-' . $version . '.tgz')) {
		addFailure($failures, relativePath($root, $vendorPath) . ' DOMPurify source URL mismatch');
	}
}

if ($failures !== []) {
	fwrite(STDERR, "Backend static checks failed:\n");
	foreach ($failures as $failure) {
		fwrite(STDERR, '- ' . $failure . "\n");
	}
	exit(1);
}

echo "Backend static checks passed.\n";
