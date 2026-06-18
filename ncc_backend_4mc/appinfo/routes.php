<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

return [
	'routes' => [
		[
			'name' => 'admin_delegation#getCurrentAdmin',
			'url' => '/api/v1/admin/me',
			'verb' => 'GET',
		],
		[
			'name' => 'admin_delegation#listDelegations',
			'url' => '/api/v1/admin/delegations',
			'verb' => 'GET',
		],
		[
			'name' => 'admin_delegation#saveDelegation',
			'url' => '/api/v1/admin/delegations/{targetUserId}',
			'verb' => 'PUT',
		],
		[
			'name' => 'admin_delegation#deleteDelegation',
			'url' => '/api/v1/admin/delegations/{targetUserId}',
			'verb' => 'DELETE',
		],
		[
			'name' => 'admin_client_settings#getGroupSettings',
			'url' => '/api/v1/admin/client-settings/groups',
			'verb' => 'GET',
		],
		[
			'name' => 'admin_client_settings#setGroupSettings',
			'url' => '/api/v1/admin/client-settings/groups',
			'verb' => 'PUT',
		],
	],
];
