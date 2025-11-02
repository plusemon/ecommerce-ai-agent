<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use NeuronAI\Agent;
use NeuronAI\Chat\Attachments\Image;
use NeuronAI\Chat\Enums\AttachmentContentType;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Gemini\Gemini;

class ChatController extends Controller
{
    public function welcome()
    {
        return Inertia::render('Welcome');
    }

    public function chat(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
        ]);

        $prompt = $request->input('prompt');
        $agent = new Agent;
        $geminiProvider = new Gemini(env('GEMINI_API_KEY'), 'gemini-2.5-flash');
        $message = new UserMessage($request->input('prompt'));
        $imageUrl = null;

        // example prompt: describe the image: https://images.pexels.com/photos/848573/pexels-photo-848573.jpeg

        if (str($prompt)->contains('image:')) {
            $imageUrl = str($prompt)->after('image:')->trim()->toString();
            $base64Image = base64_encode(file_get_contents($imageUrl));
            $attachment = new Image($base64Image, AttachmentContentType::BASE64, 'image/jpeg'
            );
            $message->addAttachment($attachment);
        }

        $agent->setAiProvider($geminiProvider);
        $response = $agent->chat($message);

        return $response->getContent();
    }
}
