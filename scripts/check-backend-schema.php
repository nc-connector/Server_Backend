#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$appRoot = $root . DIRECTORY_SEPARATOR . 'ncc_backend_4mc';
$installSchemaPath = $appRoot . '/lib/Setup/InstallSchema.php';
$dbRoot = $appRoot . '/lib/Db';
$failures = [];

function schemaReadTextFile(string $path): string {
	$content = file_get_contents($path);
	if ($content === false) {
		throw new RuntimeException('Could not read ' . $path);
	}
	return $content;
}

function schemaRelativePath(string $root, string $path): string {
	$prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	if (str_starts_with($path, $prefix)) {
		return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($prefix)));
	}
	return str_replace(DIRECTORY_SEPARATOR, '/', $path);
}

function schemaCamelToSnake(string $value): string {
	return strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
}

/**
 * @return array<string, string>
 */
function schemaInstallTableConstants(string $installSchema): array {
	preg_match_all('/private\s+const\s+(TABLE_[A-Z_]+)\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $installSchema, $matches, PREG_SET_ORDER);
	$constants = [];
	foreach ($matches as $match) {
		$constants[$match[1]] = $match[2];
	}
	return $constants;
}

/**
 * @return array<string, string[]>
 */
function schemaInstallColumns(string $installSchema, array $tableConstants): array {
	$columnsByTable = [];
	preg_match_all('/private\s+function\s+ensure[A-Za-z0-9_]+Table\s*\([^)]*\)\s*:\s*bool\s*\{/', $installSchema, $matches, PREG_OFFSET_CAPTURE);
	$methodOffsets = $matches[0] ?? [];
	foreach ($methodOffsets as $index => $methodMatch) {
		$start = (int)$methodMatch[1];
		$end = isset($methodOffsets[$index + 1])
			? (int)$methodOffsets[$index + 1][1]
			: strlen($installSchema);
		$body = substr($installSchema, $start, $end - $start);
		if (preg_match('/tableName\(self::(TABLE_[A-Z_]+)\)/', $body, $tableMatch) !== 1) {
			continue;
		}
		$table = $tableConstants[$tableMatch[1]] ?? '';
		if ($table === '') {
			continue;
		}
		preg_match_all('/->addColumn\(\s*[\'"]([^\'"]+)[\'"]/', $body, $columnMatches);
		$columnsByTable[$table] = array_values(array_unique($columnMatches[1] ?? []));
	}
	return $columnsByTable;
}

/**
 * @return array<int, array{path:string, table:string, entity:string}>
 */
function schemaMapperDefinitions(string $dbRoot): array {
	$definitions = [];
	foreach (glob($dbRoot . DIRECTORY_SEPARATOR . '*Mapper.php') ?: [] as $path) {
		$content = schemaReadTextFile($path);
		if (preg_match('/parent::__construct\(\s*\$db\s*,\s*[\'"]([^\'"]+)[\'"]\s*,\s*([A-Za-z0-9_]+)::class\s*\)/', $content, $match) !== 1) {
			continue;
		}
		$definitions[] = [
			'path' => $path,
			'table' => $match[1],
			'entity' => $match[2],
		];
	}
	usort($definitions, static fn (array $left, array $right): int => strcmp($left['table'], $right['table']));
	return $definitions;
}

/**
 * @return string[]
 */
function schemaEntityColumns(string $entityPath): array {
	$content = schemaReadTextFile($entityPath);
	preg_match_all('/protected\s+\??[A-Za-z_\\\\][A-Za-z0-9_\\\\]*(?:\|[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)*\s+\$([A-Za-z0-9_]+)\s*=/', $content, $matches);
	$columns = ['id'];
	foreach ($matches[1] ?? [] as $property) {
		$columns[] = schemaCamelToSnake($property);
	}
	return array_values(array_unique($columns));
}

$installSchema = schemaReadTextFile($installSchemaPath);
$tableConstants = schemaInstallTableConstants($installSchema);
$installColumns = schemaInstallColumns($installSchema, $tableConstants);
$mapperDefinitions = schemaMapperDefinitions($dbRoot);
$mappedTables = [];

foreach ($mapperDefinitions as $definition) {
	$table = $definition['table'];
	$mappedTables[$table] = true;
	if (!isset($installColumns[$table])) {
		$failures[] = schemaRelativePath($root, $definition['path']) . ' maps table "' . $table . '" but InstallSchema does not create it';
		continue;
	}

	$entityPath = $dbRoot . DIRECTORY_SEPARATOR . $definition['entity'] . '.php';
	if (!is_file($entityPath)) {
		$failures[] = schemaRelativePath($root, $definition['path']) . ' maps missing entity "' . $definition['entity'] . '"';
		continue;
	}

	$actualColumns = array_fill_keys($installColumns[$table], true);
	foreach (schemaEntityColumns($entityPath) as $expectedColumn) {
		if (!isset($actualColumns[$expectedColumn])) {
			$failures[] = schemaRelativePath($root, $entityPath) . ' property column "' . $expectedColumn . '" is missing in InstallSchema table "' . $table . '"';
		}
	}
}

foreach ($tableConstants as $constant => $table) {
	if (!isset($mappedTables[$table])) {
		$failures[] = schemaRelativePath($root, $installSchemaPath) . ' defines ' . $constant . ' ("' . $table . '") without a matching mapper';
	}
}

if ($failures !== []) {
	fwrite(STDERR, "Backend schema checks failed:\n");
	foreach ($failures as $failure) {
		fwrite(STDERR, '- ' . $failure . "\n");
	}
	exit(1);
}

echo "Backend schema checks passed.\n";
