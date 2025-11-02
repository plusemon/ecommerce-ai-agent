<?php

namespace App\Neuron;

use App\Neuron\Tools\ProductSearchTool;
use Illuminate\Support\Facades\DB;
use NeuronAI\Agent;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\History\SQLChatHistory;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\SystemPrompt;

class SupportAgent extends Agent
{
    public function __construct(
        private int $conversationId
    ) {}

    public function provider(): AIProviderInterface
    {
        return new Gemini(
            env('GEMINI_API_KEY'),
            env('GEMINI_MODEL', 'gemini-2.5-flash')
        );
    }

    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                'You are an advanced AI assistant designed to help users with a variety of tasks.',
                'You can process both text and image inputs to provide comprehensive responses.',
                'You have access to a product search tool to find products in the database.',
            ],
            steps: [
                "Analyze the user's input carefully.",
                'If an image is provided and the user is asking about products, interpret its content to identify product names or categories.',
                'If a product name or category is identified from text or image, use the `product_search` tool to find relevant products.',
                'If products are found, provide details like title, url, price etc.',
                'If no products are found, inform the user.',
                'Generate a helpful and relevant reply based on the information available.',
            ],
            output: [
                'Respond in a friendly and professional manner.',
                'Keep answers concise and to the point.',
                'Respond to the user in a natural and engaging manner.',
            ],
            toolsUsage: [
                'Utilize image analysis to identify products when an image is provided.',
                'Use the `product_search` tool when the user asks about products or provides an image of a product.',
                'Avoid unnecessary use of external tools.',
            ]);
    }

    public function tools(): array
    {
        return [ProductSearchTool::make()];
    }

    public function chatHistory(): ChatHistoryInterface
    {
        return new SQLChatHistory(
            $this->conversationId,
            DB::connection()->getPdo(),
            'chat_history',
            50000
        );
    }
}
