<?php

namespace SpaanProductions\LaravelAiUsage\Notifications;

use Illuminate\Support\Facades\Route;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use SpaanProductions\LaravelAiUsage\Events\BudgetThresholdReached;

class BudgetThresholdReachedNotification extends Notification
{
	public function __construct(public BudgetThresholdReached $event)
	{
	}

	/** @return array<int, string> */
	public function via(object $notifiable): array
	{
		return ['mail'];
	}

	public function toMail(object $notifiable): MailMessage
	{
		$currency = (string) config('ai-usage.currency', 'USD');
		$percentage = (int) round($this->event->threshold * 100);

		$message = (new MailMessage)
			->subject(sprintf('AI budget "%s" reached %d%%', $this->event->budget, $percentage))
			->line(sprintf(
				'The AI spend of budget "%s"%s is %s %s this %s, %d%% of the limit of %s %s.',
				$this->event->budget,
				$this->event->feature !== null ? ' (' . $this->event->feature . ')' : '',
				$currency,
				number_format($this->event->spent, 2),
				$this->event->period,
				(int) round($this->event->spent / $this->event->limit * 100),
				$currency,
				number_format($this->event->limit, 2),
			));

		if (Route::has('ai-usage.dashboard')) {
			$message->action('View AI usage', route('ai-usage.dashboard'));
		}

		return $message;
	}
}
