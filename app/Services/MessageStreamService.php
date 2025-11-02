<?php

namespace App\Services;

use App\Models\Conversation;
use App\Neuron\SupportAgent;
use Illuminate\Support\Facades\Log;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\ToolInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageStreamService
{
    private const WORD_DELAY_MICROSECONDS = 50000;

    public function __construct(
        private readonly ConversationService $conversationService
    ) {}

    public function streamResponse(Conversation $conversation, UserMessage $message): StreamedResponse
    {
        $agent = SupportAgent::make($conversation->id);
        $stream = $agent->stream($message);

        return response()->stream(
            fn () => $this->handleStream($stream, $conversation),
            200,
            $this->getStreamHeaders()
        );
    }

    private function handleStream($stream, Conversation $conversation): void
    {
        $fullResponse = '';
        $buffer = '';

        try {
            foreach ($stream as $chunk) {
                $text = $this->processChunk($chunk);
                $fullResponse .= $text;
                $buffer .= $text;

                $buffer = $this->sendBufferedWords($buffer);
            }

            $this->sendRemainingBuffer($buffer);
            $this->sendCompletionSignal();
            $this->conversationService->saveAssistantMessage($conversation, $fullResponse);

        } catch (\Exception $e) {
            $this->handleStreamError($e);
        }
    }

    private function processChunk($chunk): string
    {
        if ($chunk instanceof ToolCallMessage) {
            return $this->formatToolCallMessage($chunk);
        }

        // if the chunk is not a tool call message, and not start with [
        if (str_starts_with($chunk, '[')) {
            return '';
        }

        return $chunk;

    }

    private function formatToolCallMessage(ToolCallMessage $message): string
    {
        $toolNames = array_reduce(
            $message->getTools(),
            fn (string $carry, ToolInterface $tool) => $carry.' - Calling tool: '.$tool->getName().PHP_EOL,
            ''
        );

        return PHP_EOL.$toolNames;
    }

    private function sendBufferedWords(string $buffer): string
    {
        $words = preg_split('/(\s+)/u', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($words) <= 1) {
            return $buffer;
        }

        $remainingBuffer = array_pop($words);

        foreach ($words as $word) {
            if (! empty(trim($word))) {
                $this->sendStreamData(['text' => $word.' ', 'done' => false]);
                $this->flushOutput();
                usleep(self::WORD_DELAY_MICROSECONDS);
            }
        }

        return $remainingBuffer;
    }

    private function sendRemainingBuffer(string $buffer): void
    {
        if (empty(trim($buffer))) {
            return;
        }

        $this->sendStreamData(['text' => $buffer, 'done' => false]);
        $this->flushOutput();
    }

    private function sendCompletionSignal(): void
    {
        $this->sendStreamData(['text' => '', 'done' => true]);
        $this->flushOutput();
    }

    private function sendStreamData(array $data): void
    {
        echo 'data: '.json_encode($data)."\n\n";
    }

    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    private function handleStreamError(\Exception $e): void
    {
        $this->sendStreamData([
            'error' => $e->getMessage(),
            'done' => true,
        ]);

        $this->flushOutput();
        Log::error('Streaming error: '.$e->getMessage(), [
            'exception' => $e,
        ]);
    }

    private function getStreamHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }
}
