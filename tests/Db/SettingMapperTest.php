<?php

declare(strict_types=1);

namespace OCP\DB {
	if (!class_exists(Exception::class)) {
		class Exception extends \RuntimeException {
			public const REASON_CONSTRAINT_VIOLATION = 1;
			public const REASON_UNIQUE_CONSTRAINT_VIOLATION = 2;
			public function getReason(): int { return $this->getCode(); }
		}
	}
}

namespace OCA\NcConnector\Tests\Db {

use OCA\NcConnector\Db\SettingMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class SettingMapperTest extends TestCase {
	public function testProofCreationKeepsTheValueStoredByTheFirstCaller(): void {
		$db = new SettingTestConnection();
		$mapper = new SettingMapper($db);
		self::assertSame('first', $mapper->getOrCreateValue('proof', 'first', 1));
		self::assertSame('first', $mapper->getOrCreateValue('proof', 'second', 2));
		self::assertSame('first', $db->values['proof']);
	}

	public function testInsertFailureOtherThanDuplicateKeyIsNotIgnored(): void {
		$db = new SettingTestConnection();
		$db->insertFailure = new Exception('Storage unavailable', 99);
		$this->expectExceptionMessage('Storage unavailable');
		(new SettingMapper($db))->getOrCreateValue('proof', 'secret', 1);
	}

	public function testUnchangedValueDoesNotAttemptADuplicateInsert(): void {
		$db = new SettingTestConnection();
		$mapper = new SettingMapper($db);
		$mapper->setValue('status', 'active', 1);
		$mapper->setValue('status', 'active', 1);
		self::assertSame(['status' => 'active'], $db->values);
		self::assertSame(1, $db->inserts);
	}

	public function testChangedCredentialsRejectTheWholeResponse(): void {
		$db = new SettingTestConnection(['key' => 'new', 'status' => 'unknown']);
		$result = (new SettingMapper($db))->setValuesIfUnchanged(['status' => 'active'], 1, 'key', 'old');
		self::assertFalse($result);
		self::assertSame(['key' => 'new', 'status' => 'unknown'], $db->values);
		self::assertSame(['begin', 'lock', 'rollback'], $db->transactions);
	}

	public function testRelatedValuesAreWrittenUnderTheCredentialLock(): void {
		$db = new SettingTestConnection(['key' => 'current']);
		$result = (new SettingMapper($db))->setValuesIfUnchanged(['status' => 'active', 'seats' => '20'], 1, 'key', 'current');
		self::assertTrue($result);
		self::assertSame(['begin', 'lock', 'commit'], $db->transactions);
		self::assertSame(['key' => 'current', 'status' => 'active', 'seats' => '20'], $db->values);
	}

	public function testFailedStateWriteRollsBackEarlierValues(): void {
		$db = new SettingTestConnection(['key' => 'current', 'status' => 'unknown']);
		$db->failAtWrite = 2;
		try {
			(new SettingMapper($db))->setValuesIfUnchanged(['status' => 'active', 'seats' => '20'], 1, 'key', 'current');
			self::fail('Expected storage failure');
		} catch (\RuntimeException) {
			self::assertSame(['key' => 'current', 'status' => 'unknown'], $db->values);
			self::assertSame(['begin', 'lock', 'rollback'], $db->transactions);
		}
	}
}

final class SettingTestConnection implements IDBConnection {
	public array $transactions = [];
	public int $writes = 0;
	public int $inserts = 0;
	public ?int $failAtWrite = null;
	public ?Exception $insertFailure = null;
	private array $before = [];
	public function __construct(public array $values = []) {}
	public function beginTransaction(): void {
		$this->transactions[] = 'begin';
		$this->before = $this->values;
	}
	public function commit(): void { $this->transactions[] = 'commit'; }
	public function rollBack(): void {
		$this->transactions[] = 'rollback';
		$this->values = $this->before;
	}
	public function getQueryBuilder(): object {
		return new class($this) {
			private bool $insert = false;
			private array $values = [];
			private bool $lock = false;
			private string $key = '';
			public function __construct(private SettingTestConnection $db) {}
			public function __call(string $name, array $args): mixed {
				if ($name === 'insert') $this->insert = true;
				if ($name === 'values') $this->values = $args[0];
				if ($name === 'set') $this->values[$args[0]] = $args[1];
				if ($name === 'createFunction') $this->lock = true;
				if ($name === 'eq') $this->key = $args[1];
				if (in_array($name, ['createNamedParameter', 'createFunction', 'getColumnName'], true)) return $args[0];
				return $this;
			}
			public function executeStatement(): int {
				if (!$this->insert) {
					if ($this->lock) {
						$this->db->transactions[] = 'lock';
						return 1;
					}
					if (!isset($this->db->values[$this->key])) return 0;
					if (++$this->db->writes === $this->db->failAtWrite) throw new \RuntimeException('Write failed');
					$changed = $this->db->values[$this->key] !== $this->values['config_value'];
					$this->db->values[$this->key] = $this->values['config_value'];
					return $changed ? 1 : 0;
				}
				$this->db->inserts++;
				if ($this->db->insertFailure) throw $this->db->insertFailure;
				$key = $this->values['config_key'];
				if (isset($this->db->values[$key])) throw new Exception('Duplicate key', Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION);
				if (++$this->db->writes === $this->db->failAtWrite) throw new \RuntimeException('Write failed');
				$this->db->values[$key] = $this->values['config_value'];
				return 1;
			}
			public function executeQuery(): object {
				return new class($this->db->values[$this->key] ?? false) {
					public function __construct(private mixed $value) {}
					public function fetchOne(): mixed { return $this->value; }
					public function closeCursor(): void {}
				};
			}
		};
	}
}
}
