<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_admin
 */
class User extends Authenticatable
{
	protected $table = 'users';

	protected $guarded = [];

	protected function casts(): array
	{
		return ['is_admin' => 'boolean'];
	}
}
