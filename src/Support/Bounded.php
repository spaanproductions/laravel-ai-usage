<?php

namespace SpaanProductions\LaravelAiUsage\Support;

use Closure;

/**
 * Keeps per-call state of running calls bounded: entries older than the stale age are dropped
 * and at most `max - 1` remain, so a new entry fits. Calls that never complete (an exception
 * without a completion event) would otherwise stay in memory for the life of a worker.
 */
class Bounded
{
	/**
	 * @template TValue
	 * @param array<string, TValue> $entries
	 * @param Closure(TValue): int $startedAt hrtime (ns) the entry started at
	 * @return array<string, TValue>
	 */
	public static function trim(array $entries, Closure $startedAt, int $staleAfterSeconds, int $max): array
	{
		$staleBefore = hrtime(true) - $staleAfterSeconds * 1_000_000_000;

		$entries = array_filter($entries, fn (mixed $entry): bool => $startedAt($entry) >= $staleBefore);

		if (count($entries) >= $max) {
			$entries = array_slice($entries, -($max - 1), preserve_keys: true);
		}

		return $entries;
	}
}
