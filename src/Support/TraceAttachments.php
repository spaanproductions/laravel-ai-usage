<?php

namespace SpaanProductions\LaravelAiUsage\Support;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Contracts\Files\StorableFile;
use Laravel\Ai\Files\Concerns\HasRemoteContent;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;

/**
 * Describes the attachments of an agent prompt for its trace and, when
 * `ai-usage.trace.attachments` is on, copies their contents to the configured disk under
 * `{path}/{Y-m-d}/{invocation id}/`, so retention can delete whole days. Remote and provider
 * files are only described, never downloaded.
 */
class TraceAttachments
{
	/**
	 * @param iterable<mixed> $attachments
	 * @return list<array<string, mixed>>
	 */
	public static function describe(iterable $attachments, string $invocationId): array
	{
		$described = [];

		foreach ($attachments as $index => $file) {
			if ( ! is_object($file)) {
				continue;
			}

			$attachment = self::descriptor($file);

			try {
				if (self::storing() && ($content = self::content($file)) !== null) {
					$attachment['path'] = self::store($content, $invocationId, (int) $index, $attachment['name'] ?? null);
					$attachment['size'] = strlen($content);
				}
			} catch (Throwable $exception) {
				report($exception);
			}

			$described[] = $attachment;
		}

		return $described;
	}

	public static function storing(): bool
	{
		return (bool) config('ai-usage.trace.attachments', false);
	}

	public static function disk(): string
	{
		return (string) config('ai-usage.trace.disk', 'local');
	}

	public static function root(): string
	{
		return trim((string) config('ai-usage.trace.path', 'ai-usage/attachments'), '/');
	}

	/**
	 * Days the files are kept: the shortest of the attachment, trace and row retention, as a
	 * cleared trace or a deleted row no longer links to its files. Null keeps them.
	 */
	public static function retentionDays(): ?int
	{
		$days = array_filter([
			AiUsageRecord::retentionDays('attachment_retention_days'),
			AiUsageRecord::retentionDays('trace_retention_days'),
			AiUsageRecord::retentionDays('retention_days'),
		], fn (?int $days): bool => $days !== null);

		return $days === [] ? null : min($days);
	}

	/** Whether the file at this path belongs to a day the retention has deleted. */
	public static function pruned(string $path): bool
	{
		$days = self::retentionDays();

		return $days !== null
			&& preg_match('#^' . preg_quote(self::root(), '#') . '/(\d{4}-\d{2}-\d{2})/#', $path, $match) === 1
			&& $match[1] < now()->subDays($days)->format('Y-m-d');
	}

	/** Delete the attachment folders of days older than the retention period. */
	public static function prune(int $days): void
	{
		$disk = Storage::disk(self::disk());
		$before = now()->subDays($days)->format('Y-m-d');

		foreach ($disk->directories(self::root()) as $directory) {
			$day = basename($directory);

			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 && $day < $before) {
				$disk->deleteDirectory($directory);
			}
		}
	}

	/** @return array<string, mixed> */
	private static function descriptor(object $file): array
	{
		if ($file instanceof UploadedFile) {
			return ['type' => 'upload', 'name' => $file->getClientOriginalName(), 'mime' => $file->getClientMimeType()];
		}

		$data = method_exists($file, 'toArray') ? (array) $file->toArray() : [];
		unset($data['base64'], $data['content']);

		// The name and MIME type of a remote file are read by downloading it.
		$local = ! self::remote($file);

		return array_filter([
			...$data,
			'type' => $data['type'] ?? Str::kebab(class_basename($file)),
			'name' => $local && method_exists($file, 'name') ? $file->name() : ($data['name'] ?? null),
			'mime' => $local && method_exists($file, 'mimeType') ? $file->mimeType() : ($data['mime'] ?? null),
		], fn (mixed $value): bool => $value !== null);
	}

	private static function content(object $file): ?string
	{
		if ($file instanceof UploadedFile) {
			return $file->getContent();
		}

		if ( ! $file instanceof StorableFile || self::remote($file)) {
			return null;
		}

		return $file->content();
	}

	private static function remote(object $file): bool
	{
		return in_array(HasRemoteContent::class, class_uses_recursive($file), true);
	}

	private static function store(string $content, string $invocationId, int $index, ?string $name): string
	{
		$name = $name !== null && $name !== '' ? Str::slug(pathinfo($name, PATHINFO_FILENAME)) . (($extension = pathinfo($name, PATHINFO_EXTENSION)) !== '' ? '.' . Str::slug($extension) : '') : 'attachment';
		$path = sprintf('%s/%s/%s/%d-%s', self::root(), now()->format('Y-m-d'), $invocationId, $index + 1, $name);

		Storage::disk(self::disk())->put($path, $content);

		return $path;
	}
}
