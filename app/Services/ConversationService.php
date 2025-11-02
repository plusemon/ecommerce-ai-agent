<?php

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use NeuronAI\Chat\Attachments\Image;
use NeuronAI\Chat\Enums\AttachmentContentType;
use NeuronAI\Chat\Messages\UserMessage;

class ConversationService
{
    private const TITLE_MAX_LENGTH = 25;

    public function getConversationsList(): Collection
    {
        return Conversation::orderByDesc('updated_at')
            ->get(['id', 'title as name']);
    }

    public function getFormattedMessages(Conversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->toArray();
    }

    public function createOrUpdateConversation(string $prompt, ?string $conversationId = null): Conversation
    {
        return DB::transaction(function () use ($prompt, $conversationId) {
            $conversation = Conversation::firstOrCreate(
                ['id' => $conversationId],
                ['title' => $this->generateTitle($prompt)]
            );

            $this->saveUserMessage($conversation, $prompt);

            return $conversation;
        });
    }

    public function saveAssistantMessage(Conversation $conversation, string $content): void
    {
        if (empty(trim($content))) {
            return;
        }

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
        ]);
    }

    public function createUserMessage(string $prompt, ?UploadedFile $image = null): UserMessage
    {
        $message = new UserMessage($prompt);

        if ($image) {
            $attachment = $this->createImageAttachment($image);
            $message->addAttachment($attachment);
        }

        return $message;
    }

    public function deleteConversation(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation) {
            $conversation->messages()->delete();
            $conversation->delete();
        });
    }

    private function generateTitle(string $prompt): string
    {
        return str($prompt)->limit(self::TITLE_MAX_LENGTH)->toString();
    }

    private function saveUserMessage(Conversation $conversation, string $content): void
    {
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $content,
        ]);
    }

    private function createImageAttachment(UploadedFile $image): Image
    {
        $imageContents = $image->getContent();
        $base64Image = base64_encode($imageContents);

        return new Image(
            $base64Image,
            AttachmentContentType::BASE64,
            'image/jpeg'
        );
    }
}
