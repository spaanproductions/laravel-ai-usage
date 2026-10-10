<?php

namespace SpaanProductions\LaravelAiUsage\Enums;

enum UsageStatus: string
{
	case Success = 'success';
	case Failed = 'failed';

	public function label(): string
	{
		return match ($this) {
			self::Success => 'Success',
			self::Failed => 'Failed',
		};
	}
}
