<?php

declare(strict_types=1);

namespace OCP\AppFramework\Db {
	if (!class_exists('OCP\AppFramework\Db\Entity')) {
		abstract class Entity {
			protected ?int $id = null;

			protected function addType(string $field, string $type): void {
			}

			public function getId(): ?int {
				return $this->id;
			}

			public function setId(int $id): void {
				$this->id = $id;
			}

			/**
			 * @param mixed[] $arguments
			 */
			public function __call(string $method, array $arguments): mixed {
				if (preg_match('/^(get|set)([A-Z].*)$/', $method, $matches) !== 1) {
					throw new \BadMethodCallException($method);
				}

				$property = lcfirst($matches[2]);
				if (!property_exists($this, $property)) {
					throw new \BadMethodCallException($method);
				}

				if ($matches[1] === 'get') {
					return $this->{$property};
				}

				$this->{$property} = $arguments[0] ?? null;
				return null;
			}
		}
	}

	if (!class_exists('OCP\AppFramework\Db\QBMapper')) {
		abstract class QBMapper {
			protected mixed $db;
			private string $tableName;

			public function __construct(mixed $db = null, string $tableName = '', string $entityClass = '') {
				$this->db = $db;
				$this->tableName = $tableName;
			}

			protected function getTableName(): string {
				return $this->tableName;
			}

			/**
			 * @return array<int, mixed>
			 */
			protected function findEntities(mixed $query): array {
				return [];
			}
		}
	}
}

namespace OCP\AppFramework {
	if (!class_exists('OCP\AppFramework\Http')) {
		final class Http {
			public const STATUS_OK = 200;
			public const STATUS_FORBIDDEN = 403;
			public const STATUS_NOT_FOUND = 404;
			public const STATUS_UNPROCESSABLE_ENTITY = 422;
			public const STATUS_INTERNAL_SERVER_ERROR = 500;
		}
	}

	if (!class_exists('OCP\AppFramework\Controller')) {
		abstract class Controller {
			protected \OCP\IRequest $request;

			public function __construct(
				protected string $appName,
				\OCP\IRequest $request,
			) {
				$this->request = $request;
			}
		}
	}
}

namespace OCP\AppFramework\Http {
	if (!class_exists('OCP\AppFramework\Http\Http')) {
		final class Http {
			public const STATUS_OK = \OCP\AppFramework\Http::STATUS_OK;
			public const STATUS_FORBIDDEN = \OCP\AppFramework\Http::STATUS_FORBIDDEN;
			public const STATUS_NOT_FOUND = \OCP\AppFramework\Http::STATUS_NOT_FOUND;
			public const STATUS_UNPROCESSABLE_ENTITY = \OCP\AppFramework\Http::STATUS_UNPROCESSABLE_ENTITY;
			public const STATUS_INTERNAL_SERVER_ERROR = \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR;
		}
	}

	if (!class_exists('OCP\AppFramework\Http\DataResponse')) {
		class DataResponse {
			/**
			 * @param mixed[] $headers
			 */
			public function __construct(
				private mixed $data = [],
				private int $status = Http::STATUS_OK,
				private array $headers = [],
			) {
			}

			public function getData(): mixed {
				return $this->data;
			}

			public function getStatus(): int {
				return $this->status;
			}

			/**
			 * @return mixed[]
			 */
			public function getHeaders(): array {
				return $this->headers;
			}
		}
	}
}

namespace OCP\AppFramework\Http\Attribute {
	if (!class_exists('OCP\AppFramework\Http\Attribute\NoAdminRequired')) {
		#[\Attribute(\Attribute::TARGET_METHOD)]
		final class NoAdminRequired {
		}
	}

	if (!class_exists('OCP\AppFramework\Http\Attribute\NoCSRFRequired')) {
		#[\Attribute(\Attribute::TARGET_METHOD)]
		final class NoCSRFRequired {
		}
	}

	if (!class_exists('OCP\AppFramework\Http\Attribute\FrontpageRoute')) {
		#[\Attribute(\Attribute::TARGET_METHOD)]
		final class FrontpageRoute {
			public function __construct(
				public string $verb = '',
				public string $url = '',
			) {
			}
		}
	}
}

namespace OCP {
	if (!interface_exists('OCP\IRequest')) {
		interface IRequest {
		}
	}

	if (!interface_exists('OCP\IDBConnection')) {
		interface IDBConnection {
		}
	}

	if (!interface_exists('OCP\IGroupManager')) {
		interface IGroupManager {
		}
	}

	if (!interface_exists('OCP\IGroup')) {
		interface IGroup {
		}
	}

	if (!interface_exists('OCP\IUser')) {
		interface IUser {
		}
	}

	if (!interface_exists('OCP\IUserManager')) {
		interface IUserManager {
		}
	}
}

namespace Psr\Log {
	if (!interface_exists('Psr\Log\LoggerInterface')) {
		interface LoggerInterface {
			/**
			 * @param mixed[] $context
			 */
			public function emergency(string|\Stringable $message, array $context = []): void;

			/**
			 * @param mixed[] $context
			 */
			public function alert(string|\Stringable $message, array $context = []): void;

			/**
			 * @param mixed[] $context
			 */
			public function critical(string|\Stringable $message, array $context = []): void;

			/**
			 * @param mixed[] $context
			 */
			public function error(string|\Stringable $message, array $context = []): void;

			/**
			 * @param mixed[] $context
			 */
			public function warning(string|\Stringable $message, array $context = []): void;

			/**
			 * @param mixed[] $context
			 */
			public function notice(string|\Stringable $message, array $context = []): void;

			/**
			 * @param mixed[] $context
			 */
			public function info(string|\Stringable $message, array $context = []): void;

			/**
			 * @param mixed[] $context
			 */
			public function debug(string|\Stringable $message, array $context = []): void;

			/**
			 * @param mixed[] $context
			 */
			public function log($level, string|\Stringable $message, array $context = []): void;
		}
	}
}

namespace OCP\DB\QueryBuilder {
	if (!interface_exists('OCP\DB\QueryBuilder\IQueryBuilder')) {
		interface IQueryBuilder {
			public const PARAM_NULL = 0;
			public const PARAM_INT = 1;
			public const PARAM_STR = 2;
		}
	}
}

namespace {
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
	require_once $autoload;
	return;
}

require_once dirname(__DIR__) . '/ncc_backend_4mc/lib/Service/TemplateSanitizerService.php';
}
