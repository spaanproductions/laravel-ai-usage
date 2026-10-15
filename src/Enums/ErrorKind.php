<?php

namespace SpaanProductions\LaravelAiUsage\Enums;

enum ErrorKind: string
{
	case RateLimited = 'rate_limited';
	case Overloaded = 'overloaded';
	case Connection = 'connection';
	case Credits = 'credits';
	case ToolError = 'tool_error';
	case Exception = 'exception';

	public function label(): string
	{
		return match ($this) {
			self::RateLimited => 'Rate limited',
			self::Overloaded => 'Provider overloaded',
			self::Connection => 'Connection error',
			self::Credits => 'Insufficient credits',
			self::ToolError => 'Tool error',
			self::Exception => 'Exception',
		};
	}
}
