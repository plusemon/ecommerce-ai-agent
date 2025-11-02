<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Services\ConversationService;
use App\Services\MessageStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly MessageStreamService $messageStreamService
    ) {}

    public function welcome(?Conversation $conversation = null): InertiaResponse
    {
        $props = [
            'conversations' => $this->conversationService->getConversationsList(),
        ];

        if ($conversation) {
            $props['currentMessages'] = $this->conversationService->getFormattedMessages($conversation);
            $props['currentConversationId'] = $conversation->id;
        }

        return Inertia::render('Welcome', $props);
    }

    public function sendMessage(SendMessageRequest $request): StreamedResponse
    {
        $validated = $request->validated();

        $conversation = $this->conversationService->createOrUpdateConversation(
            $validated['prompt'],
            $validated['conversation_id'] ?? null
        );

        $userMessage = $this->conversationService->createUserMessage(
            $validated['prompt'],
            $request->file('image')
        );

        return $this->messageStreamService->streamResponse($conversation, $userMessage);
    }

    public function showConversation(Conversation $conversation): JsonResponse
    {
        $messages = $this->conversationService->getFormattedMessages($conversation);

        return response()->json($messages);
    }

    public function deleteConversation(Conversation $conversation): RedirectResponse
    {
        $this->conversationService->deleteConversation($conversation);

        return redirect()->route('home');
    }

    public function getConversationsList(): JsonResponse
    {
        $conversations = $this->conversationService->getConversationsList();

        return response()->json($conversations);
    }
}
