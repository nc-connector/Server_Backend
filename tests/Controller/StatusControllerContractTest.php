<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Controller;

use OCA\NcConnector\Controller\StatusController;
use OCA\NcConnector\Db\Seat;
use OCA\NcConnector\Db\SeatMapper;
use OCA\NcConnector\Db\SettingMapper;
use OCA\NcConnector\Service\AccessService;
use OCA\NcConnector\Service\LicenseService;
use OCA\NcConnector\Service\SeatService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ControllerTestDoubles.php';

final class StatusControllerContractTest extends TestCase {
	public function testCapacityChangesPreservePoliciesForActiveSeatsInBothModes(): void {
		$mapper = $this->assignedSeatMapper();
		$mapper->expects(self::never())->method('assign');
		$mapper->expects(self::never())->method('unassign');
		$previousPolicy = null;
		foreach ([['pro', 12], ['pro', 10], ['community', 1], ['pro', 12]] as [$mode, $capacity]) {
			$license = $this->createMock(LicenseService::class);
			$license->method('getTotalSeats')->willReturn($capacity);
			$license->method('isLicenseValid')->willReturn(true);
			$license->method('getSnapshot')->willReturn(['mode' => $mode]);
			$seats = new SeatService($mapper, $this->createMock(SettingMapper::class), $license);
			$access = new AccessService(new TestGroupManager(), $seats, $license, new TestAdminDelegationService());
			for ($index = 1; $index <= 12; $index++) {
				$user = 'user-' . $index;
				$data = (new StatusController('ncc_backend_4mc', new TestRequest(), $access, $seats,
					$license, new TestClientSettingsService(effectiveSettings: [
						'vfs_external_providers_enabled' => true, 'share_send_password_mode' => 'secrets',
						'talk_lobby_enabled' => true, 'email_signature_on_compose' => true,
					], effectiveEditable: ['share_send_password_mode' => true]), $user))->status()->getData();
				$active = $index <= $capacity;
				self::assertTrue($data['status']['seat_assigned']);
				self::assertSame($active ? 'active' : 'suspended_overlimit', $data['status']['seat_state']);
				self::assertSame($capacity < 12, $data['status']['overlicensed']);
				self::assertSame($mode, $data['status']['mode']);
				self::assertSame($active, $access->isSeatUserWithValidLicense($user));
				foreach (['share', 'talk', 'email_signature'] as $area) {
					self::assertSame($active, is_array($data['policy'][$area]));
					self::assertSame($active, is_array($data['policy_editable'][$area]));
				}
				if ($active) {
					self::assertTrue($data['policy']['share']['vfs_external_providers_enabled']);
					if ($previousPolicy !== null) {
						self::assertSame($previousPolicy, $data['policy']);
					}
					$previousPolicy = $data['policy'];
				}
			}
			self::assertSame(12, $seats->getAssignedSeats());
			self::assertSame($capacity, $seats->getSeatUsage()['active_assigned']);
			self::assertSame(12 - $capacity, $seats->getSeatUsage()['suspended_assigned']);
		}
	}

	public function testPersonalAccessAndAdminInspectionStaySeparateAcrossLicenseStates(): void {
		foreach (['COMMUNITY', 'ACTIVE', 'GRACE', 'EXPIRED', 'INACTIVE', 'INVALID', 'ACTIVATION_REQUIRED', 'OFFLINE_EXPIRED', 'UNKNOWN'] as $state) {
			$valid = in_array($state, ['COMMUNITY', 'ACTIVE', 'GRACE'], true);
			foreach ([false, true] as $admin) {
				$license = $this->createMock(LicenseService::class);
				$license->method('getTotalSeats')->willReturn($state === 'COMMUNITY' ? 1 : 10);
				$license->method('isLicenseValid')->willReturn($valid);
				$license->method('getSnapshot')->willReturn([
					'mode' => $state === 'COMMUNITY' ? 'community' : 'pro',
					'status_effective' => $state,
					'license_status_effective' => $state,
				]);
				$seats = new SeatService($this->assignedSeatMapper(), $this->createMock(SettingMapper::class), $license);
				$access = new AccessService(new TestGroupManager($admin ? ['admin'] : []), $seats, $license, new TestAdminDelegationService());
				foreach (['user-1' => 'active', 'user-12' => 'suspended_overlimit', 'seatless' => 'none'] as $target => $seatState) {
					$data = (new StatusController('ncc_backend_4mc', new TestRequest(['user_id' => $target]), $access,
						$seats, $license, new TestClientSettingsService(), $admin ? 'admin' : $target))->status()->getData();
					self::assertSame($target, $data['status']['user_id']);
					self::assertSame($seatState, $data['status']['seat_state']);
					self::assertSame($admin, $data['status']['can_manage_license']);
					self::assertSame($valid, $data['status']['is_valid']);
					self::assertSame($state, $data['status']['access_status']);
					self::assertSame($valid && $seatState === 'active', $access->isSeatUserWithValidLicense($target));
					foreach (['share', 'talk', 'email_signature'] as $area) {
						self::assertSame($seatState === 'active' && ($admin || $valid), is_array($data['policy'][$area]));
						self::assertSame($seatState === 'active' && ($admin || $valid), is_array($data['policy_editable'][$area]));
					}
				}
			}
		}
	}

	private function assignedSeatMapper(): SeatMapper {
		$rows = [];
		// Match the mapper's newest-first assignment order.
		for ($index = 12; $index >= 1; $index--) {
			$seat = new Seat();
			$seat->setId($index);
			$seat->setUserId('user-' . $index);
			$seat->setAssignedAt($index);
			$rows[$seat->getUserId()] = $seat;
		}
		$mapper = $this->createMock(SeatMapper::class);
		$mapper->method('listAllAssigned')->willReturn(array_values($rows));
		$mapper->method('getSeatForUser')->willReturnCallback(static fn(string $user): ?Seat => $rows[$user] ?? null);
		return $mapper;
	}

	public function testLicenseDetailsExplainDenialWithoutRemovingSeatAssignments(): void {
		foreach (['EXPIRED', 'INACTIVE', 'INVALID', 'ACTIVATION_REQUIRED', 'OFFLINE_EXPIRED'] as $reason) {
			$controller = new StatusController(
				'ncc_backend_4mc', new TestRequest(), new TestAccessService(), new TestSeatService(['alice']),
				new TestLicenseService(false, [
					'mode' => 'pro', 'status_effective' => $reason, 'license_status_effective' => 'EXPIRED',
					'last_error' => 'private connection detail', 'email' => 'private@example.test',
					'last_sync_at_iso' => '2026-09-16T00:00:00+00:00',
				]),
				new TestClientSettingsService(), 'alice'
			);
			$data = $controller->status()->getData();
			self::assertFalse($data['status']['is_valid']);
			self::assertTrue($data['status']['seat_assigned']);
			self::assertSame('active', $data['status']['seat_state']);
			self::assertSame($reason, $data['status']['access_status']);
			self::assertSame('EXPIRED', $data['status']['license_status']);
			self::assertFalse($data['status']['can_manage_license']);
			self::assertTrue($data['status']['license_connection_error']);
			self::assertNull($data['policy']['share']);
			self::assertNull($data['status']['license_activation']);
			self::assertStringNotContainsString('private', json_encode($data));
		}
	}

	public function testLicenseManagementFlagBelongsToAuthenticatedAdminWithoutRequiringASeat(): void {
		foreach (['admin' => true, 'delegated' => false, 'seatless' => false] as $actor => $expected) {
			$controller = new StatusController(
				'ncc_backend_4mc', new TestRequest(), new TestAccessService(['admin']), new TestSeatService(),
				new TestLicenseService(true, ['mode' => 'pro', 'status_effective' => 'GRACE', 'license_status_effective' => 'GRACE']),
				new TestClientSettingsService(), $actor
			);
			$status = $controller->status()->getData()['status'];
			self::assertSame($expected, $status['can_manage_license']);
			self::assertFalse($status['seat_assigned']);
			self::assertTrue($status['is_valid']);
			self::assertSame('GRACE', $status['license_status']);
		}
	}

	public function testStatusApiGroupsEffectivePolicyForSeatUser(): void {
		$versionedShareTemplate = '<div lang="de"><p>{LINK_INTRO}</p><p>{LINK_LABEL}: <a href="{URL}">{URL}</a></p></div>';
		$controller = new StatusController(
			'ncc_backend_4mc',
			new TestRequest(['user_id' => 'target']),
			new TestAccessService(['admin'], ['target']),
			new TestSeatService(['target']),
			new TestLicenseService(true, [
				'mode' => 'pro',
				'expires_at_iso' => null,
				'grace_until_iso' => null,
			]),
			new TestClientSettingsService(
				effectiveSettings: [
					'share_default_expire_days' => 14,
					'attachment_link_target' => 'zip_download',
					'vfs_provider_enabled' => true,
					'vfs_external_providers_enabled' => false,
					'share_html_block_template' => $versionedShareTemplate,
					'language_share_html_block' => 'custom',
					'share_send_password_mode' => 'secrets',
					'talk_lobby_enabled' => true,
					'talk_invitation_template_format' => 'html',
					'email_signature_on_compose' => true,
				],
				effectiveEditable: [
					'share_default_expire_days' => false,
					'attachment_link_target' => true,
					'vfs_provider_enabled' => false,
					'vfs_external_providers_enabled' => true,
					'share_html_block_template' => false,
					'share_send_password_mode' => true,
					'talk_lobby_enabled' => false,
					'talk_invitation_template_format' => false,
					'email_signature_on_compose' => false,
				],
				signatureEmail: 'target@example.test'
			),
			'admin'
		);

		$response = $controller->status();
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertSame('target', $data['status']['user_id']);
		self::assertTrue($data['status']['can_manage_license']);
		self::assertTrue($data['status']['seat_assigned']);
		self::assertSame('active', $data['status']['seat_state']);
		self::assertFalse($data['status']['overlicensed']);
		self::assertSame('pro', $data['status']['mode']);
		self::assertTrue($data['status']['is_valid']);

		self::assertSame('secrets', $data['policy']['share']['share_send_password_mode']);
		self::assertSame(14, $data['policy']['share']['share_default_expire_days']);
		self::assertSame('zip_download', $data['policy']['share']['attachment_link_target']);
		self::assertTrue($data['policy']['share']['vfs_provider_enabled']);
		self::assertFalse($data['policy']['share']['vfs_external_providers_enabled']);
		self::assertSame($versionedShareTemplate, $data['policy']['share']['share_html_block_template_v2']);
		self::assertSame('de', $data['policy']['share']['share_html_block_effective_language']);
		self::assertStringNotContainsString('{LINK_INTRO}', $data['policy']['share']['share_html_block_template']);
		self::assertStringNotContainsString('{LINK_LABEL}', $data['policy']['share']['share_html_block_template']);
		self::assertStringContainsString('The files have been provided securely', $data['policy']['share']['share_html_block_template']);
		self::assertStringContainsString('Download link', $data['policy']['share']['share_html_block_template']);
		self::assertTrue($data['policy']['talk']['talk_lobby_enabled']);
		self::assertSame('html', $data['policy']['talk']['event_description_type']);
		self::assertTrue($data['policy']['email_signature']['email_signature_on_compose']);
		self::assertSame('target@example.test', $data['policy']['email_signature']['user_email']);

		self::assertTrue($data['policy_editable']['share']['share_send_password_mode']);
		self::assertTrue($data['policy_editable']['share']['attachment_link_target']);
		self::assertFalse($data['policy_editable']['share']['vfs_provider_enabled']);
		self::assertTrue($data['policy_editable']['share']['vfs_external_providers_enabled']);
		self::assertArrayNotHasKey('share_html_block_template_v2', $data['policy_editable']['share']);
		self::assertArrayNotHasKey('share_html_block_effective_language', $data['policy_editable']['share']);
		self::assertFalse($data['policy_editable']['talk']['talk_lobby_enabled']);
		self::assertFalse($data['policy_editable']['email_signature']['email_signature_on_compose']);
	}

	public function testStatusApiKeepsExistingTemplatesWithoutVersionedVariablesUnchanged(): void {
		$legacyTemplate = '<p>Existing customer link: <a href="{URL}">{URL}</a></p>';
		$controller = new StatusController(
			'ncc_backend_4mc',
			new TestRequest(),
			new TestAccessService(validSeatUsers: ['customer']),
			new TestSeatService(['customer']),
			new TestLicenseService(),
			new TestClientSettingsService(
				effectiveSettings: [
					'share_html_block_template' => $legacyTemplate,
					'language_share_html_block' => 'custom',
				],
				effectiveEditable: ['share_html_block_template' => false]
			),
			'customer'
		);

		$policy = $controller->status()->getData()['policy']['share'];

		self::assertSame($legacyTemplate, $policy['share_html_block_template']);
		self::assertSame($legacyTemplate, $policy['share_html_block_template_v2']);
		self::assertSame('custom', $policy['share_html_block_effective_language']);
	}

	public function testStatusApiKeepsSignatureDependenciesWhenDisabledComposeIsEditable(): void {
		$controller = new StatusController(
			'ncc_backend_4mc',
			new TestRequest(),
			new TestAccessService(validSeatUsers: ['alice']),
			new TestSeatService(['alice']),
			new TestLicenseService(),
			new TestClientSettingsService(
				effectiveSettings: [
					'email_signature_on_compose' => false,
					'email_signature_on_reply' => true,
					'email_signature_on_forward' => false,
					'email_signature_template' => '<p>Alice</p>',
				],
				effectiveEditable: [
					'email_signature_on_compose' => true,
					'email_signature_on_reply' => true,
					'email_signature_on_forward' => true,
					'email_signature_template' => false,
				],
				signatureEmail: 'alice@example.test'
			),
			'alice'
		);

		$data = $controller->status()->getData();

		self::assertFalse($data['policy']['email_signature']['email_signature_on_compose']);
		self::assertTrue($data['policy_editable']['email_signature']['email_signature_on_compose']);
		self::assertTrue($data['policy']['email_signature']['email_signature_on_reply']);
		self::assertFalse($data['policy']['email_signature']['email_signature_on_forward']);
		self::assertSame('<p>Alice</p>', $data['policy']['email_signature']['email_signature_template']);
	}

	public function testStatusApiUsesTranslatedCompatibilityCopyForOlderClients(): void {
		$template = '<div lang="de"'
			. ' data-nccb-legacy-link-intro="Die Dateien wurden sicher und datenschutzkonform über Nextcloud bereitgestellt. Der Download ist über den untenstehenden Link möglich."'
			. ' data-nccb-legacy-link-label="Download-Link">'
			. '<p>{LINK_INTRO}</p><p>{LINK_LABEL}: {URL}</p></div>';
		$controller = new StatusController(
			'ncc_backend_4mc',
			new TestRequest(),
			new TestAccessService(validSeatUsers: ['customer']),
			new TestSeatService(['customer']),
			new TestLicenseService(),
			new TestClientSettingsService(
				effectiveSettings: [
					'share_html_block_template' => $template,
					'language_share_html_block' => 'custom',
				],
				effectiveEditable: ['share_html_block_template' => false]
			),
			'customer'
		);

		$policy = $controller->status()->getData()['policy']['share'];

		self::assertStringContainsString('{LINK_INTRO}', $policy['share_html_block_template_v2']);
		self::assertStringContainsString('{LINK_LABEL}', $policy['share_html_block_template_v2']);
		self::assertStringContainsString('Die Dateien wurden sicher und datenschutzkonform', $policy['share_html_block_template']);
		self::assertStringContainsString('Download-Link', $policy['share_html_block_template']);
		self::assertStringNotContainsString('{LINK_INTRO}', $policy['share_html_block_template']);
		self::assertStringNotContainsString('{LINK_LABEL}', $policy['share_html_block_template']);
		self::assertStringNotContainsString('data-nccb-legacy-link-', $policy['share_html_block_template']);
		self::assertStringNotContainsString('data-nccb-legacy-link-', $policy['share_html_block_template_v2']);
		self::assertSame('de', $policy['share_html_block_effective_language']);
	}

	public function testStatusApiProjectsNullTemplateForNonCustomLanguage(): void {
		$controller = new StatusController(
			'ncc_backend_4mc',
			new TestRequest(),
			new TestAccessService(validSeatUsers: ['customer']),
			new TestSeatService(['customer']),
			new TestLicenseService(),
			new TestClientSettingsService(
				effectiveSettings: [
					'share_html_block_template' => null,
					'language_share_html_block' => 'de',
				],
				effectiveEditable: ['share_html_block_template' => false]
			),
			'customer'
		);

		$policy = $controller->status()->getData()['policy']['share'];

		self::assertNull($policy['share_html_block_template']);
		self::assertNull($policy['share_html_block_template_v2']);
		self::assertSame('de', $policy['share_html_block_effective_language']);
	}

	public function testStatusApiDoesNotExposePolicyWithoutSeat(): void {
		$controller = new StatusController(
			'ncc_backend_4mc',
			new TestRequest(),
			new TestAccessService(validSeatUsers: []),
			new TestSeatService(),
			new TestLicenseService(),
			new TestClientSettingsService(
				effectiveSettings: ['share_send_password_mode' => 'secrets'],
				effectiveEditable: ['share_send_password_mode' => true]
			),
			'seatless'
		);

		$response = $controller->status();
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertSame('seatless', $data['status']['user_id']);
		self::assertFalse($data['status']['seat_assigned']);
		self::assertSame('none', $data['status']['seat_state']);
		self::assertNull($data['policy']['share']);
		self::assertNull($data['policy']['talk']);
		self::assertNull($data['policy']['email_signature']);
		self::assertNull($data['policy_editable']['share']);
	}
}
