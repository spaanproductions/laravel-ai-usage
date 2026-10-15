<?php

namespace SpaanProductions\LaravelAiUsage\Support;

use Throwable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Ai\Exceptions\RateLimitedException;
use SpaanProductions\LaravelAiUsage\Enums\ErrorKind;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;

/**
 * Sorts a failure into an ErrorKind by its exception class, HTTP status and message, so
 * rate limits and outages can be told apart from bugs. Older laravel/ai versions lack some
 * exception classes; `instanceof` a missing class is simply false.
 */
class ErrorClassifier
{
	public static function classify(Throwable|string $error): ErrorKind
	{
		if ($error instanceof Throwable) {
			$kind = self::byClass($error) ?? self::byStatus(self::status($error));

			if ($kind !== null) {
				return $kind;
			}

			$error = $error->getMessage();
		}

		return self::byMessage($error) ?? ErrorKind::Exception;
	}

	private static function byClass(Throwable $exception): ?ErrorKind
	{
		return match (true) {
			$exception instanceof RateLimitedException => ErrorKind::RateLimited,
			$exception instanceof ProviderOverloadedException => ErrorKind::Overloaded,
			$exception instanceof InsufficientCreditsException => ErrorKind::Credits,
			$exception instanceof ProviderConnectionException, $exception instanceof ConnectionException => ErrorKind::Connection,
			default => null,
		};
	}

	private static function status(Throwable $exception): ?int
	{
		if ($exception instanceof RequestException) {
			return $exception->response->status();
		}

		$code = $exception->getCode();

		return is_int($code) && $code >= 400 && $code < 600 ? $code : null;
	}

	private static function byStatus(?int $status): ?ErrorKind
	{
		return match ($status) {
			429 => ErrorKind::RateLimited,
			402 => ErrorKind::Credits,
			503, 529 => ErrorKind::Overloaded,
			default => null,
		};
	}

	private static function byMessage(string $message): ?ErrorKind
	{
		$message = strtolower($message);

		return match (true) {
			str_contains($message, 'rate limit'), str_contains($message, 'too many requests') => ErrorKind::RateLimited,
			str_contains($message, 'overloaded') => ErrorKind::Overloaded,
			str_contains($message, 'insufficient credit'), str_contains($message, 'insufficient_quota') => ErrorKind::Credits,
			str_contains($message, 'timed out'), str_contains($message, 'connection'), str_contains($message, 'could not resolve') => ErrorKind::Connection,
			default => null,
		};
	}
}
