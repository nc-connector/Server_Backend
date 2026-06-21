<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

class TalkTemplateRuntimeService {
	public const FORMAT_HTML = 'html';
	public const FORMAT_PLAIN_TEXT = 'plain_text';

	public function normalizeFormat(string $format): string {
		return strtolower(trim($format)) === self::FORMAT_HTML
			? self::FORMAT_HTML
			: self::FORMAT_PLAIN_TEXT;
	}

	public function renderForPolicy(string $template, string $format): string {
		$normalizedFormat = $this->normalizeFormat($format);
		if ($normalizedFormat === self::FORMAT_HTML) {
			return $template;
		}

		return $this->convertHtmlToPlainText($template);
	}

	private function convertHtmlToPlainText(string $template): string {
		if (trim($template) === '') {
			return '';
		}

		$previousUseInternalErrors = libxml_use_internal_errors(true);
		$document = new \DOMDocument();
		$loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		libxml_use_internal_errors($previousUseInternalErrors);

		if ($loaded === false || !$document->documentElement) {
			return $this->fallbackHtmlToPlainText($template);
		}

		$plain = $this->renderDocumentAsPlainText($document);
		return $this->normalizePlainText($plain);
	}

	private function renderDocumentAsPlainText(\DOMDocument $document): string {
		$result = '';
		foreach ($document->childNodes as $node) {
			if ($node instanceof \DOMDocumentType || $node instanceof \DOMProcessingInstruction) {
				continue;
			}

			if ($node instanceof \DOMElement && strtolower($node->tagName) === 'html') {
				$result .= $this->renderHtmlElementAsPlainText($node);
				continue;
			}

			$result .= $this->renderNodeAsPlainText($node);
		}

		return $result;
	}

	private function renderHtmlElementAsPlainText(\DOMElement $htmlElement): string {
		$result = '';
		foreach ($htmlElement->childNodes as $node) {
			if ($node instanceof \DOMElement && strtolower($node->tagName) === 'head') {
				continue;
			}

			if ($node instanceof \DOMElement && strtolower($node->tagName) === 'body') {
				$result .= $this->renderNodesAsPlainText($node->childNodes);
				continue;
			}

			$result .= $this->renderNodeAsPlainText($node);
		}

		return $result;
	}

	private function fallbackHtmlToPlainText(string $template): string {
		$withPreservedLinks = preg_replace_callback(
			'/<a\b[^>]*(?:href|data-mce-href)=(["\'])(.*?)\\1[^>]*>(.*?)<\/a>/is',
			function (array $matches): string {
				$href = html_entity_decode(trim((string)($matches[2] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$linkText = $this->normalizePlainText(strip_tags((string)($matches[3] ?? '')));
				if ($href === '') {
					return $linkText;
				}

				if ($linkText === '' || $linkText === $href) {
					return $href;
				}

				return sprintf('%s: %s', $linkText, $href);
			},
			$template
		) ?? $template;

		return $this->normalizePlainText(strip_tags($withPreservedLinks));
	}

	private function renderNodesAsPlainText(\DOMNodeList $nodes): string {
		$result = '';
		foreach ($nodes as $node) {
			$result .= $this->renderNodeAsPlainText($node);
		}
		return $result;
	}

	private function renderNodeAsPlainText(\DOMNode $node): string {
		if ($node instanceof \DOMText) {
			return $node->nodeValue ?? '';
		}

		if ($node instanceof \DOMElement) {
			$tagName = strtolower($node->tagName);
			if ($tagName === 'br') {
				return "\n";
			}

			if ($tagName === 'a') {
				$linkText = $this->normalizePlainText($this->renderNodesAsPlainText($node->childNodes));
				$href = trim((string)($node->getAttribute('href') ?: $node->getAttribute('data-mce-href')));
				if ($href === '') {
					return $linkText;
				}
				if ($linkText === '' || $linkText === $href) {
					return $href;
				}
				return sprintf('%s: %s', $linkText, $href);
			}

			$content = $this->renderNodesAsPlainText($node->childNodes);
			if (in_array($tagName, ['p', 'div', 'section', 'article', 'header', 'footer', 'aside', 'blockquote', 'pre', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
				$content = $this->normalizePlainText($content);
				if ($content === '') {
					return '';
				}
				if ($tagName === 'li') {
					return '- ' . $content . "\n";
				}
				return $content . "\n\n";
			}

			return $content;
		}

		return '';
	}

	private function normalizePlainText(string $value): string {
		$normalized = str_replace(["\r\n", "\r"], "\n", $value);
		$normalized = preg_replace("/[ \t]+\n/u", "\n", $normalized) ?? $normalized;
		$normalized = preg_replace("/\n[ \t]+/u", "\n", $normalized) ?? $normalized;
		$normalized = preg_replace("/[ \t]{2,}/u", ' ', $normalized) ?? $normalized;
		$normalized = preg_replace("/\n{3,}/u", "\n\n", $normalized) ?? $normalized;
		return trim($normalized);
	}
}
