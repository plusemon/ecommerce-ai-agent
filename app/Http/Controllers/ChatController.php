<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use NeuronAI\Agent;
use NeuronAI\Chat\Attachments\Image;
use NeuronAI\Chat\Enums\AttachmentContentType;
use NeuronAI\Chat\History\SQLChatHistory;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\SystemPrompt;

class ChatController extends Controller
{
    public function welcome(?Conversation $conversation = null)
    {
        $props = [
            'conversations' => Conversation::orderByDesc('updated_at')->get(['id', 'title as name']),
        ];

        if ($conversation) {
            $messages = $conversation->messages()->orderBy('created_at')->get();
            $formattedMessages = [];
            foreach ($messages as $message) {
                $formattedMessages[] = [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            }
            $props['currentMessages'] = $formattedMessages;
            $props['currentConversationId'] = $conversation->id;
        }

        return Inertia::render('Welcome', $props);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
            'image' => 'nullable|image',
            'conversation_id' => 'nullable|string',
        ]);

        $prompt = $request->input('prompt');
        $conversationId = $request->input('conversation_id');

        $conversation = Conversation::firstOrCreate([
            'id' => $conversationId,
            'title' => str($request->input('prompt'))->limit(25)->toString(),
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $prompt,
        ]);

        $geminiProvider = new Gemini(env('GEMINI_API_KEY'), env('GEMINI_MODEL', 'gemini-2.5-flash'));

        $chatHistory = new SQLChatHistory(
            $conversation->id,
            DB::connection()->getPdo(),
            'chat_history',
            50000
        );

        $instructions = new SystemPrompt(
            background: [
                'You are an advanced AI assistant designed to help users with a variety of tasks.',
                'You can process both text and image inputs to provide comprehensive responses.',
            ],
            steps: [
                "Analyze the user's input carefully.",
                'If an image is provided, interpret its content to enhance your response.',
                'Generate a helpful and relevant reply based on the information available.',
            ],
            output: [
                'Respond in a friendly and professional manner.',
                'Keep answers concise and to the point.',
            ],
            toolsUsage: [
                'Utilize image analysis only when an image is provided.',
                'Avoid unnecessary use of external tools.',
            ]
        );

        $agent = (new Agent)
            ->setAiProvider($geminiProvider)
            ->setInstructions($instructions)
            ->withChatHistory($chatHistory);

        $message = new UserMessage($prompt);

        if ($request->hasFile('image')) {
            $imageContents = $request->file('image')->getContent();
            $base64Image = base64_encode($imageContents);
            $attachment = new Image($base64Image, AttachmentContentType::BASE64, 'image/jpeg');
            $message->addAttachment($attachment);
        }

        // Stream the response
        $stream = $agent->stream($message);

        // Return streaming response
        return response()->stream(function () use ($stream, $conversation) {
            $fullResponse = '';
            $buffer = '';

            try {
                // Iterate through the generator
                foreach ($stream as $chunk) {
                    // Extract text content from the chunk
                    $text = $chunk->content ?? $chunk->text ?? (string) $chunk;

                    // Accumulate the full response
                    $fullResponse .= $text;
                    $buffer .= $text;

                    // Split buffer into words and send them one by one
                    $words = preg_split('/(\s+)/u', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);

                    // Keep the last word in buffer (it might be incomplete)
                    if (count($words) > 1) {
                        $buffer = array_pop($words);

                        foreach ($words as $word) {
                            if (! empty(trim($word))) {
                                echo 'data: '.json_encode([
                                    'text' => $word.' ',
                                    'done' => false,
                                ])."\n\n";

                                if (ob_get_level() > 0) {
                                    ob_flush();
                                }
                                flush();

                                // Add delay between words (50ms = 0.05 seconds)
                                usleep(50000); // Adjust this for faster/slower typing
                            }
                        }
                    }
                }

                // Send any remaining text in buffer
                if (! empty(trim($buffer))) {
                    echo 'data: '.json_encode([
                        'text' => $buffer,
                        'done' => false,
                    ])."\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                // Send completion signal
                echo 'data: '.json_encode([
                    'text' => '',
                    'done' => true,
                ])."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                // Save the complete AI response to the database
                if (! empty($fullResponse)) {
                    $conversation->messages()->create([
                        'role' => 'assistant',
                        'content' => $fullResponse,
                    ]);
                }

            } catch (\Exception $e) {
                // Send error to client
                echo 'data: '.json_encode([
                    'error' => $e->getMessage(),
                    'done' => true,
                ])."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                Log::error('Streaming error: '.$e->getMessage());
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    public function showConversation(Conversation $conversation)
    {
        $messages = $conversation->messages()->orderBy('created_at')->get();

        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        return response()->json($formattedMessages);
    }

    public function deleteConversation(Conversation $conversation)
    {
        $conversation->messages()->delete();
        $conversation->delete();

        return redirect()->route('home');
    }

    public function getConversationsList()
    {
        return response()->json(Conversation::orderByDesc('updated_at')->get(['id', 'title as name']));
    }
}
