<?php

namespace SpaanProductions\LaravelAiUsage\Livewire;

use Livewire\Component;
use Livewire\Attributes\Title;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use SpaanProductions\LaravelAiUsage\Support\TraceTree;
use SpaanProductions\LaravelAiUsage\Models\AiUsageRecord;

#[ConfiguredLayout]
#[Title('AI usage record')]
class RecordDetail extends Component
{
	public AiUsageRecord $record;

	public function mount(AiUsageRecord $record): void
	{
		$this->record = $record;
	}

	public function render(): View
	{
		$this->record->loadMissing(['subject', 'user', 'aiModel']);

		$conversation = $this->record->conversation_id === null ? collect() : AiUsageRecord::query()
			->where('conversation_id', $this->record->conversation_id)
			->whereKeyNot($this->record->getKey())
			->latest()
			->limit(20)
			->get();

		$subjectUrl = null;

		if ($this->record->subject !== null && method_exists($this->record->subject, 'aiUsageUrl')) {
			$subjectUrl = $this->record->subject->aiUsageUrl();
		}

		return ViewFactory::make('ai-usage::livewire.record', [
			'trace' => TraceTree::for($this->record),
			'conversation' => $conversation,
			'subjectUrl' => $subjectUrl,
			'currency' => $this->record->currency,
		]);
	}
}
