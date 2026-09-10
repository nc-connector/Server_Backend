<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Service;

use OCA\NcConnector\Service\ClientSettingsDefinitionService;
use OCA\NcConnector\Service\ClientSettingsService;
use OCA\NcConnector\Service\TemplateAssetService;
use OCA\NcConnector\Service\TemplateSanitizerService;
use PHPUnit\Framework\TestCase;

final class ClientSettingsServiceTemplateAssetTest extends TestCase {
	public function testPreviewBuildsOnlyTheSelectedTemplateAssets(): void {
		$calls = [];
		$templateAssets = $this->createMock(TemplateAssetService::class);
		$templateAssets->method('buildAssetResult')
			->willReturnCallback(static function (string $contextKey, string $template) use (&$calls): array {
				$calls[] = [$contextKey, $template];
				return ['assets' => [], 'warnings' => []];
			});
		$service = $this->createService($templateAssets);

		$result = $service->getEditorTemplateAssetDataForDefaults([], [
			'email_signature_template' => '<p>Preview</p>',
		]);

		self::assertSame(['email_signature_template'], array_keys($result['assets']));
		self::assertSame([['default-email_signature_template', '<p>Preview</p>']], $calls);
	}

	public function testSchemaAssetsCanBeLimitedToTheSelectedTemplate(): void {
		$contexts = [];
		$templateAssets = $this->createMock(TemplateAssetService::class);
		$templateAssets->method('buildAssetResult')
			->willReturnCallback(static function (string $contextKey) use (&$contexts): array {
				$contexts[] = $contextKey;
				return ['assets' => [], 'warnings' => []];
			});
		$service = $this->createService($templateAssets);

		$result = $service->getEditorTemplateAssetDataForSchemaDefaults(['email_signature_template']);

		self::assertSame(['email_signature_template'], array_keys($result['assets']));
		self::assertSame(['schema-email_signature_template'], $contexts);
	}

	private function createService(TemplateAssetService $templateAssets): ClientSettingsService {
		$service = (new \ReflectionClass(ClientSettingsService::class))->newInstanceWithoutConstructor();
		$this->setProperty(
			$service,
			'settingDefinitions',
			new ClientSettingsDefinitionService(new TemplateSanitizerService())
		);
		$this->setProperty($service, 'templateAssets', $templateAssets);
		return $service;
	}

	private function setProperty(ClientSettingsService $service, string $propertyName, object $value): void {
		$property = new \ReflectionProperty(ClientSettingsService::class, $propertyName);
		$property->setValue($service, $value);
	}
}
