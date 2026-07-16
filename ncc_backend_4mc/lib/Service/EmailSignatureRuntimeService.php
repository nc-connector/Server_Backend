<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

use OCA\NcConnector\Db\ClientOverride;
use OCA\NcConnector\Db\ClientOverrideMapper;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\PropertyDoesNotExistException;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class EmailSignatureRuntimeService {
	public const EMAIL_ADDRESS_KEY = 'email_signature_email_address';
	public const PHONE_MOBILE_KEY = 'email_signature_phone_mobile';
	public const CUSTOM1_KEY = 'email_signature_custom1';
	public const CUSTOM2_KEY = 'email_signature_custom2';

	private const MODE_FORCED = 'forced';

	/**
	 * @var array<string, string>
	 */
	private const EMPTY_VARIABLES = [
		'NAME' => '',
		'EMAIL' => '',
		'PHONE' => '',
		'PHONE_MOBILE' => '',
		'ABOUT' => '',
		'FUNCTION' => '',
		'ORGANISATION' => '',
		'CUSTOM1' => '',
		'CUSTOM2' => '',
	];

	public function __construct(
		private ClientOverrideMapper $overrides,
		private IUserManager $userManager,
		private IAccountManager $accountManager,
		private LoggerInterface $logger,
		private TemplateSanitizerService $templateSanitizer,
	) {
	}

	public function renderTemplateForPolicy(string $template, string $userId): string {
		$variables = $this->getTemplateVariables($userId);
		$replacements = [];
		$template = $this->removeEmptyVariableLines($template, $variables);

		foreach ($variables as $name => $value) {
			$replacements['{' . $name . '}'] = $name === 'ABOUT'
				? $this->escapeMultilineValue($value)
				: $this->escapeValue($value);
		}

		// Profile and override values enter after storage sanitizing, so validate the rendered links once more.
		return $this->templateSanitizer->sanitizeHtml(strtr($template, $replacements));
	}

	public function getUserEmail(string $userId): string {
		$variables = $this->getTemplateVariables($userId);
		return (string)($variables['EMAIL'] ?? '');
	}

	public function getUserOnlyFallbackValue(string $key, string $userId): string {
		if ($key !== self::EMAIL_ADDRESS_KEY) {
			return '';
		}

		return $this->getUserEmail($userId);
	}

	/**
	 * @return array<string, string>
	 */
	private function getTemplateVariables(string $userId): array {
		$variables = self::EMPTY_VARIABLES;
		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser) {
			return $this->applyUserOverrides($variables, $userId);
		}

		$variables['NAME'] = (string)$user->getDisplayName();
		$variables['EMAIL'] = (string)($user->getEMailAddress() ?? '');

		try {
			$account = $this->accountManager->getAccount($user);
		} catch (\Throwable $exception) {
			$this->logger->error('Email signature profile lookup failed.', [
				'userId' => $userId,
				'exception' => $exception,
			]);
			return $this->applyUserOverrides($variables, $userId);
		}

		if ($variables['EMAIL'] === '') {
			$variables['EMAIL'] = $this->getAccountPropertyValue($account, IAccountManager::PROPERTY_EMAIL);
		}

		$variables['PHONE'] = $this->getAccountPropertyValue($account, IAccountManager::PROPERTY_PHONE);
		$variables['ABOUT'] = $this->getAccountPropertyValue($account, IAccountManager::PROPERTY_BIOGRAPHY);
		$variables['FUNCTION'] = $this->getAccountPropertyValue($account, IAccountManager::PROPERTY_ROLE);
		$variables['ORGANISATION'] = $this->getAccountPropertyValue($account, IAccountManager::PROPERTY_ORGANISATION);

		return $this->applyUserOverrides($variables, $userId);
	}

	/**
	 * @param array<string, string> $variables
	 * @return array<string, string>
	 */
	private function applyUserOverrides(array $variables, string $userId): array {
		$overrideMap = $this->overrides->getForUser($userId);

		$emailAddress = $this->getForcedUserOverrideString($overrideMap, self::EMAIL_ADDRESS_KEY);
		if ($emailAddress !== null) {
			$variables['EMAIL'] = $emailAddress;
		}

		$phoneMobile = $this->getForcedUserOverrideString($overrideMap, self::PHONE_MOBILE_KEY);
		if ($phoneMobile !== null) {
			$variables['PHONE_MOBILE'] = $phoneMobile;
		}

		$custom1 = $this->getForcedUserOverrideString($overrideMap, self::CUSTOM1_KEY);
		if ($custom1 !== null) {
			$variables['CUSTOM1'] = $custom1;
		}

		$custom2 = $this->getForcedUserOverrideString($overrideMap, self::CUSTOM2_KEY);
		if ($custom2 !== null) {
			$variables['CUSTOM2'] = $custom2;
		}

		return $variables;
	}

	/**
	 * @param array<string, ClientOverride> $overrideMap
	 */
	private function getForcedUserOverrideString(array $overrideMap, string $key): ?string {
		$override = $overrideMap[$key] ?? null;
		if (!$override instanceof ClientOverride || $override->getMode() !== self::MODE_FORCED) {
			return null;
		}

		return (string)$override->getSettingValue();
	}

	/**
	 * @param array<string, string> $variables
	 */
	private function removeEmptyVariableLines(string $template, array $variables): string {
		// Empty values remove their visual line so signatures do not leave blank contact rows.
		$emptyNames = array_keys(array_filter(
			$variables,
			static fn (string $value): bool => trim($value) === ''
		));
		if ($emptyNames === []) {
			return $template;
		}

		$variablePattern = $this->buildVariablePattern($emptyNames);
		$template = preg_replace_callback(
			'~<tr\b[^>]*>.*?</tr>~is',
			function (array $match) use ($variablePattern): string {
				if (preg_match($variablePattern, $match[0]) !== 1) {
					return $match[0];
				}

				$row = preg_replace_callback(
					'~(<t[dh]\b[^>]*>)(.*?)(</t[dh]>)~is',
					function (array $cellMatch) use ($variablePattern): string {
						$inner = $this->removeEmptyBrLines($cellMatch[2], $variablePattern);
						return $cellMatch[1] . $inner . $cellMatch[3];
					},
					$match[0]
				) ?? $match[0];
				return trim(strip_tags($row)) === '' ? '' : $row;
			},
			$template
		) ?? $template;
		$template = preg_replace_callback(
			'~<li\b[^>]*>.*?</li>~is',
			static fn (array $match): string => preg_match($variablePattern, $match[0]) === 1 ? '' : $match[0],
			$template
		) ?? $template;

		$template = preg_replace_callback(
			'~(<(p|div)\b[^>]*>)(.*?)(</\2>)~is',
			function (array $match) use ($variablePattern): string {
				if (preg_match($variablePattern, $match[3]) !== 1) {
					return $match[0];
				}

				$inner = $this->removeEmptyBrLines($match[3], $variablePattern);
				if (trim(strip_tags($inner)) === '') {
					return '';
				}
				return $match[1] . $inner . $match[4];
			},
			$template
		) ?? $template;

		$lines = preg_split('/\n/', $template);
		if (!is_array($lines)) {
			return $template;
		}

		$lines = array_values(array_filter(
			$lines,
			static fn (string $line): bool => preg_match($variablePattern, $line) !== 1
		));
		return implode("\n", $lines);
	}

	/**
	 * @param string[] $variableNames
	 */
	private function buildVariablePattern(array $variableNames): string {
		$escaped = array_map(
			static fn (string $name): string => preg_quote($name, '~'),
			$variableNames
		);
		return '~\{(?:' . implode('|', $escaped) . ')\}~';
	}

	private function removeEmptyBrLines(string $html, string $variablePattern): string {
		$parts = preg_split('~(<br\b[^>]*\/?>)~i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
		if (!is_array($parts) || count($parts) <= 1) {
			return preg_match($variablePattern, $html) === 1 ? '' : $html;
		}

		$result = [];
		$partCount = count($parts);
		for ($index = 0; $index < $partCount; $index++) {
			$part = $parts[$index];
			if ($index % 2 === 1) {
				$result[] = $part;
				continue;
			}

			if (preg_match($variablePattern, $part) !== 1) {
				$result[] = $part;
				continue;
			}

			if ($result !== [] && preg_match('~^<br\b~i', (string)end($result)) === 1) {
				array_pop($result);
				continue;
			}
			if ($index + 1 < $partCount && preg_match('~^<br\b~i', $parts[$index + 1]) === 1) {
				$index++;
			}
		}

		return implode('', $result);
	}

	private function getAccountPropertyValue(IAccount $account, string $property): string {
		try {
			return (string)$account->getProperty($property)->getValue();
		} catch (PropertyDoesNotExistException) {
			return '';
		}
	}

	private function escapeValue(string $value): string {
		$normalized = trim((string)preg_replace('/\s+/u', ' ', $value));
		return htmlspecialchars($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function escapeMultilineValue(string $value): string {
		$normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));
		return str_replace(
			"\n",
			'<br>',
			htmlspecialchars($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
		);
	}
}
