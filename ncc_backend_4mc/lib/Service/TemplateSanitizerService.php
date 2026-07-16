<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

class TemplateSanitizerService {
	/**
	 * @var array<string, true>
	 */
	private const ALLOWED_TAGS = [
		'a' => true,
		'abbr' => true,
		'acronym' => true,
		'address' => true,
		'article' => true,
		'aside' => true,
		'b' => true,
		'bdi' => true,
		'bdo' => true,
		'big' => true,
		'blockquote' => true,
		'br' => true,
		'caption' => true,
		'center' => true,
		'cite' => true,
		'code' => true,
		'col' => true,
		'colgroup' => true,
		'dd' => true,
		'del' => true,
		'details' => true,
		'dfn' => true,
		'div' => true,
		'dl' => true,
		'dt' => true,
		'em' => true,
		'figcaption' => true,
		'figure' => true,
		'font' => true,
		'footer' => true,
		'h1' => true,
		'h2' => true,
		'h3' => true,
		'h4' => true,
		'h5' => true,
		'h6' => true,
		'header' => true,
		'hr' => true,
		'i' => true,
		'img' => true,
		'ins' => true,
		'kbd' => true,
		'li' => true,
		'main' => true,
		'mark' => true,
		'ol' => true,
		'p' => true,
		'pre' => true,
		'q' => true,
		'rp' => true,
		'rt' => true,
		'ruby' => true,
		's' => true,
		'samp' => true,
		'section' => true,
		'small' => true,
		'span' => true,
		'strike' => true,
		'strong' => true,
		'sub' => true,
		'summary' => true,
		'sup' => true,
		'table' => true,
		'tbody' => true,
		'td' => true,
		'tfoot' => true,
		'th' => true,
		'thead' => true,
		'time' => true,
		'tr' => true,
		'tt' => true,
		'u' => true,
		'ul' => true,
		'var' => true,
		'wbr' => true,
	];

	/**
	 * @var array<string, true>
	 */
	private const FORBIDDEN_TAGS = [
		'button' => true,
		'embed' => true,
		'form' => true,
		'iframe' => true,
		'input' => true,
		'link' => true,
		'math' => true,
		'meta' => true,
		'object' => true,
		'option' => true,
		'script' => true,
		'select' => true,
		'style' => true,
		'svg' => true,
		'textarea' => true,
	];

	/**
	 * @var array<string, true>
	 */
	private const ALLOWED_ATTRIBUTES = [
		'abbr' => true,
		'align' => true,
		'alt' => true,
		'bgcolor' => true,
		'border' => true,
		'cellpadding' => true,
		'cellspacing' => true,
		'class' => true,
		'colspan' => true,
		'color' => true,
		'dir' => true,
		'data-nccb-legacy-link-intro' => true,
		'data-nccb-legacy-link-label' => true,
		'face' => true,
		'height' => true,
		'href' => true,
		'id' => true,
		'lang' => true,
		'name' => true,
		'rel' => true,
		'role' => true,
		'rowspan' => true,
		'scope' => true,
		'size' => true,
		'src' => true,
		'style' => true,
		'target' => true,
		'title' => true,
		'valign' => true,
		'width' => true,
	];

	public function sanitizeHtml(string $value): string {
		$dirty = trim($value);
		if ($dirty === '') {
			return '';
		}
		$dirty = $this->normalizeDocumentFragment($dirty);
		[$dirty, $protectedVariables] = $this->protectTemplateVariables($dirty);

		$previousUseInternalErrors = libxml_use_internal_errors(true);
		$document = new \DOMDocument('1.0', 'UTF-8');
		$loaded = $document->loadHTML(
			'<?xml encoding="utf-8" ?><div id="nccb-template-sanitize-root">' . $dirty . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors($previousUseInternalErrors);

		if ($loaded === false) {
			return '';
		}

		$root = $this->findSanitizeRoot($document);
		if (!$root instanceof \DOMElement) {
			return '';
		}

		$this->sanitizeChildren($root);
		return strtr($this->innerHtml($root), $protectedVariables);
	}

	/**
	 * @return array{0:string, 1:array<string, string>}
	 */
	private function protectTemplateVariables(string $html): array {
		// DOMDocument URL-encodes braces in attributes, which would make stored variables impossible to resolve later.
		$prefix = 'NCCBTEMPLATE' . hash('sha256', $html);
		while (str_contains($html, $prefix)) {
			$prefix .= 'X';
		}

		$restoreMap = [];
		$protected = preg_replace_callback(
			'/\{[A-Z][A-Z0-9_]*\}/',
			static function (array $match) use (&$restoreMap, $prefix): string {
				$token = $prefix . count($restoreMap);
				$restoreMap[$token] = $match[0];
				return $token;
			},
			$html
		);

		return [$protected ?? $html, $restoreMap];
	}

	private function normalizeDocumentFragment(string $html): string {
		if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $matches) === 1) {
			return (string)$matches[1];
		}

		$html = preg_replace('/<!doctype\b[^>]*>/i', '', $html) ?? $html;
		$html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $html) ?? $html;
		$html = preg_replace('/<\/?html\b[^>]*>/i', '', $html) ?? $html;
		return preg_replace('/<\/?body\b[^>]*>/i', '', $html) ?? $html;
	}

	private function findSanitizeRoot(\DOMDocument $document): ?\DOMElement {
		foreach ($document->getElementsByTagName('div') as $element) {
			if ($element->getAttribute('id') === 'nccb-template-sanitize-root') {
				return $element;
			}
		}
		return null;
	}

	private function sanitizeChildren(\DOMNode $parent): void {
		$children = [];
		foreach ($parent->childNodes as $child) {
			$children[] = $child;
		}

		foreach ($children as $child) {
			if ($child instanceof \DOMElement) {
				$this->sanitizeElement($child);
				continue;
			}

			if ($child instanceof \DOMComment || $child instanceof \DOMDocumentType || $child instanceof \DOMProcessingInstruction) {
				$parent->removeChild($child);
			}
		}
	}

	private function sanitizeElement(\DOMElement $element): void {
		$tagName = strtolower($element->tagName);
		if (isset(self::FORBIDDEN_TAGS[$tagName])) {
			$element->parentNode?->removeChild($element);
			return;
		}

		if (!isset(self::ALLOWED_TAGS[$tagName])) {
			$this->sanitizeChildren($element);
			$this->unwrapElement($element);
			return;
		}

		$this->sanitizeAttributes($element);
		$this->sanitizeChildren($element);

		if ($tagName === 'a') {
			$this->normalizeAnchorRel($element);
		}
	}

	private function sanitizeAttributes(\DOMElement $element): void {
		$attributes = [];
		foreach ($element->attributes as $attribute) {
			$attributes[] = $attribute;
		}

		foreach ($attributes as $attribute) {
			$name = strtolower($attribute->name);
			$value = $attribute->value;
			if ($this->shouldRemoveAttribute($name)) {
				$element->removeAttributeNode($attribute);
				continue;
			}

			if ($name === 'style') {
				$style = $this->sanitizeStyle($value);
				if ($style === '') {
					$element->removeAttributeNode($attribute);
					continue;
				}
				$element->setAttribute($attribute->name, $style);
				continue;
			}

			if ($name === 'href' && !$this->isSafeUrl($value, true)) {
				$element->removeAttributeNode($attribute);
				continue;
			}

			if ($name === 'src' && !$this->isSafeUrl($value, false)) {
				$element->removeAttributeNode($attribute);
				continue;
			}

			if ($name === 'target' && !$this->isAllowedTarget($value)) {
				$element->removeAttributeNode($attribute);
			}
		}
	}

	private function shouldRemoveAttribute(string $name): bool {
		if ($name === '' || str_starts_with($name, 'on') || str_starts_with($name, 'xmlns')) {
			return true;
		}
		if (str_starts_with($name, 'data-') && !isset(self::ALLOWED_ATTRIBUTES[$name])) {
			return true;
		}

		if (str_starts_with($name, 'aria-')) {
			return false;
		}

		return !isset(self::ALLOWED_ATTRIBUTES[$name]);
	}

	private function sanitizeStyle(string $value): string {
		$declarations = preg_split('/;/', $value) ?: [];
		$clean = [];
		foreach ($declarations as $declaration) {
			$declaration = trim($declaration);
			if ($declaration === '' || !str_contains($declaration, ':')) {
				continue;
			}

			[$property, $propertyValue] = array_map('trim', explode(':', $declaration, 2));
			if (!preg_match('/^-?[a-z][a-z0-9-]*$/i', $property)) {
				continue;
			}

			$decodedValue = strtolower(html_entity_decode($propertyValue, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			$schemeCheckValue = preg_replace('/[\x00-\x20]+/', '', $decodedValue) ?? $decodedValue;
			// Real template images must use <img>; CSS URLs bypass the local image cache.
			if (preg_match('/(?:expression\(|behavior:|-moz-binding:|javascript:|vbscript:|url\()/i', $schemeCheckValue)) {
				continue;
			}

			$clean[] = $property . ': ' . $propertyValue;
		}

		return implode('; ', $clean);
	}

	private function isSafeUrl(string $value, bool $allowMailLinks): bool {
		$url = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		if ($url === '' || preg_match('/^\{[A-Z0-9_]+\}$/', $url) === 1) {
			return true;
		}

		if (str_starts_with($url, '#') || str_starts_with($url, '/') || str_starts_with($url, './') || str_starts_with($url, '../')) {
			return true;
		}

		if (preg_match('/^data:image\/(?:png|jpe?g|gif|webp);base64,[a-z0-9+\/=\s]+$/i', $url) === 1) {
			return true;
		}

		$schemeCheckUrl = preg_replace('/[\x00-\x20]+/', '', $url) ?? $url;
		$scheme = strtolower((string)(parse_url($schemeCheckUrl, PHP_URL_SCHEME) ?? ''));
		if ($scheme === '') {
			return true;
		}

		if ($scheme === 'http' || $scheme === 'https') {
			return true;
		}

		return $allowMailLinks && ($scheme === 'mailto' || $scheme === 'tel');
	}

	private function isAllowedTarget(string $value): bool {
		return in_array(strtolower(trim($value)), ['_blank', '_self', '_parent', '_top'], true);
	}

	private function normalizeAnchorRel(\DOMElement $anchor): void {
		if (strtolower(trim($anchor->getAttribute('target'))) !== '_blank') {
			return;
		}

		$tokens = preg_split('/\s+/', strtolower($anchor->getAttribute('rel'))) ?: [];
		$tokens = array_values(array_filter($tokens, static fn(string $token): bool => $token !== ''));
		foreach (['noopener', 'noreferrer'] as $requiredToken) {
			if (!in_array($requiredToken, $tokens, true)) {
				$tokens[] = $requiredToken;
			}
		}
		$anchor->setAttribute('rel', implode(' ', $tokens));
	}

	private function unwrapElement(\DOMElement $element): void {
		$parent = $element->parentNode;
		if ($parent === null) {
			return;
		}

		while ($element->firstChild !== null) {
			$parent->insertBefore($element->firstChild, $element);
		}
		$parent->removeChild($element);
	}

	private function innerHtml(\DOMElement $element): string {
		$result = '';
		foreach ($element->childNodes as $child) {
			$result .= $element->ownerDocument?->saveHTML($child) ?: '';
		}
		return trim($result);
	}
}
