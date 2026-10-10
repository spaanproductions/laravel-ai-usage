<?php

namespace SpaanProductions\LaravelAiUsage\Enums;

enum Operation: string
{
	case Chat = 'chat';
	case Stream = 'stream';
	case Embeddings = 'embeddings';
	case Image = 'image';
	case Transcription = 'transcription';
	case Rerank = 'rerank';
	case Failover = 'failover';

	public function label(): string
	{
		return match ($this) {
			self::Chat => 'Chat',
			self::Stream => 'Stream',
			self::Embeddings => 'Embeddings',
			self::Image => 'Image',
			self::Transcription => 'Transcription',
			self::Rerank => 'Rerank',
			self::Failover => 'Failover',
		};
	}
}
