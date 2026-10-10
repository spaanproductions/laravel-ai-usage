<?php

namespace SpaanProductions\LaravelAiUsage\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use SpaanProductions\LaravelAiUsage\Concerns\HasAiUsage;

/**
 * A model AI calls are made for.
 *
 * @property int $id
 * @property string $title
 */
class Document extends Model
{
	use HasAiUsage;

	protected $table = 'documents';

	protected $guarded = [];

	public function aiUsageUrl(): string
	{
		return 'https://example.com/documents/' . $this->id;
	}
}
