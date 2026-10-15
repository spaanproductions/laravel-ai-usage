<?php

namespace SpaanProductions\LaravelAiUsage\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;
use SpaanProductions\LaravelAiUsage\Support\TraceAttachments;

/**
 * Downloads an attachment stored with a record's execution trace. Behind the same middleware
 * and gate as the pages.
 */
class AttachmentController
{
	public function __invoke(AiUsageRecord $record, int $index): StreamedResponse
	{
		$attachment = $record->trace['attachments'][$index] ?? null;
		$path = is_array($attachment) ? ($attachment['path'] ?? null) : null;
		$disk = Storage::disk(TraceAttachments::disk());

		abort_if( ! is_string($path) || ! str_starts_with($path, TraceAttachments::root() . '/') || ! $disk->exists($path), 404);

		return $disk->download($path, basename($path), array_filter(['Content-Type' => $attachment['mime'] ?? null]));
	}
}
