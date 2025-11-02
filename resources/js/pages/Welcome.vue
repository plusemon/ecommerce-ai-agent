<script setup lang="ts">
import {
    onMounted,
    ref,
} from 'vue';

import axios from 'axios';

import {
    Head,
    router,
} from '@inertiajs/vue3';

const props = defineProps<{
    conversations: { id: number; name: string }[];
    currentMessages?: { role: string; content: string }[];
    currentConversationId?: string;
}>();

const prompt = ref('');
const messages = ref<{ role: string; content: string }[]>([]);
const isLoading = ref(false);
const image = ref<File | null>(null);
const imageUrl = ref<string | null>(null);
const conversation_id = ref<string | null>(null);
const localConversations = ref<{ id: string; name: string }[]>([]);
const streamingMessage = ref('');
const fileInput = ref<HTMLInputElement | null>(null);

onMounted(() => {
    // Initialize local ref with prop, converting numeric ids to strings for compatibility
    localConversations.value = props.conversations.map(c => ({ id: String(c.id), name: c.name }));

    if (props.currentMessages && props.currentConversationId) {
        messages.value = props.currentMessages;
        conversation_id.value = String(props.currentConversationId);
    } else {
        loadConversations(); // Load conversations if no specific conversation is in the URL
    }
});

const loadConversations = async () => {
    try {
        const response = await axios.get('/conversations');
        localConversations.value = response.data;
    } catch (error) {
        console.error('Error loading conversations:', error);
    }
};

const newConversation = () => {
    conversation_id.value = null;
    messages.value = [];
    window.history.pushState({}, '', '/');
    loadConversations();
};

const switchConversation = (conversationId: string) => {
    router.visit(`/conversations/${conversationId}`);
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        image.value = target.files[0];
        const reader = new FileReader();
        reader.onload = (e) => {
            imageUrl.value = e.target?.result as string;
        };
        reader.readAsDataURL(image.value);
    }
};

const removeImage = () => {
    image.value = null;
    imageUrl.value = null;
};

const sendMessage = async () => {
    if ((!prompt.value && !image.value) || isLoading.value) return;

    // Add user message
    messages.value.push({ role: 'user', content: prompt.value });

    const userPrompt = prompt.value;
    const userImage = image.value;

    // Clear input immediately
    prompt.value = '';
    removeImage();

    isLoading.value = true;
    streamingMessage.value = '';

    try {
        const formData = new FormData();
        formData.append('prompt', userPrompt);
        if (userImage) {
            formData.append('image', userImage);
        }
        if (conversation_id.value) {
            formData.append('conversation_id', conversation_id.value);
        }

        const response = await fetch('/send-message', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const reader = response.body?.getReader();
        if (!reader) throw new Error('No reader available');

        const decoder = new TextDecoder();

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value);
            const lines = chunk.split('\n');

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    try {
                        const data = JSON.parse(line.slice(6));

                        if (data.error) {
                            console.error('Stream error:', data.error);
                            alert('Error: ' + data.error);
                            break;
                        }

                        if (data.done) {
                            // Add complete message to messages array
                            if (streamingMessage.value) {
                                messages.value.push({
                                    role: 'assistant',
                                    content: streamingMessage.value
                                });
                            }
                            streamingMessage.value = '';

                            // Reload conversations to update the list
                            await loadConversations();
                            break;
                        }

                        if (data.text) {
                            streamingMessage.value += data.text;
                        }
                    } catch (e) {
                        console.error('Parse error:', e);
                    }
                }
            }
        }
    } catch (error) {
        console.error('Fetch error:', error);
        messages.value.push({
            role: 'assistant',
            content: 'Sorry, there was an error processing your request.'
        });
    } finally {
        isLoading.value = false;
        streamingMessage.value = '';
    }
};

const deleteConversation = async (conversationId: string) => {
    try {
        await axios.delete(`/conversations/${conversationId}`);
        await loadConversations();

        // If we deleted the current conversation, redirect to home
        if (conversationId === conversation_id.value) {
            newConversation();
        }
    } catch (error) {
        console.error('Error deleting conversation:', error);
    }
}
</script>

<template>

    <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div class="flex h-screen flex-col items-center bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a]">
        <div class="flex w-full h-full">
            <div class="w-64 border-r border-gray-200 dark:border-gray-800 flex flex-col">
                <div class="p-4">
                    <button @click="newConversation" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg">New
                        Chat</button>
                </div>
                <div class="grow p-4 space-y-2 overflow-y-auto">
                    <div v-for="conversation in localConversations" :key="conversation.id"
                        class="flex items-center justify-between cursor-pointer p-2 rounded-lg"
                        :class="{ 'bg-gray-200 dark:bg-gray-700': conversation.id === conversation_id }">
                        <p @click="switchConversation(conversation.id)" class="truncate dark:text-gray-200 grow">{{
                            conversation.name }}</p>
                        <button @click.stop="deleteConversation(conversation.id)"
                            class="ml-2 text-red-500 hover:text-red-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="grow flex flex-col">
                <header class="w-full border-b border-gray-200 dark:border-gray-800 dark:bg-[#0a0a0a]">
                    <div class="container mx-auto flex items-center justify-between p-4">
                        <h1 class="text-xl font-semibold dark:text-white">Chat with Gemini</h1>
                    </div>
                </header>

                <main class="grow w-full max-w-2xl flex flex-col mx-auto">
                    <div class="grow p-6 space-y-6 overflow-y-auto">
                        <div v-for="(message, index) in messages" :key="index" class="flex"
                            :class="{ 'justify-end': message.role === 'user' }">
                            <div class="max-w-lg px-4 py-2 rounded-2xl whitespace-pre-wrap" :class="{
                                'bg-blue-600 text-white': message.role === 'user',
                                'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200': message.role === 'assistant'
                            }">
                                <p>{{ message.content }}</p>
                            </div>
                        </div>

                        <!-- Streaming message -->
                        <div v-if="streamingMessage" class="flex justify-start">
                            <div
                                class="max-w-lg px-4 py-2 rounded-2xl bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 whitespace-pre-wrap">
                                <p>{{ streamingMessage }}<span class="streaming-cursor">▋</span></p>
                            </div>
                        </div>

                        <!-- Loading indicator -->
                        <div v-else-if="isLoading" class="flex justify-start">
                            <div
                                class="max-w-lg px-4 py-2 rounded-2xl bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200 typing-animation">
                                <p><span>.</span><span>.</span><span>.</span></p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 border-t border-gray-200 dark:border-gray-800">
                        <div v-if="imageUrl" class="relative mb-4">
                            <img :src="imageUrl" class="rounded-lg max-h-40" />
                            <button @click="removeImage"
                                class="absolute top-2 right-2 bg-gray-800 text-white rounded-full p-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="flex items-center">
                            <input type="file" @change="handleFileChange" accept="image/*" class="hidden"
                                ref="fileInput" />
                            <button @click="fileInput?.click()"
                                class="mr-4 p-2 bg-gray-200 dark:bg-gray-700 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l-1.586-1.586a2 2 0 00-2.828 0L6 14m6-6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                            </button>
                            <input v-model="prompt" @keyup.enter="sendMessage" type="text"
                                placeholder="Type your message..."
                                class="grow px-4 py-2 bg-transparent border-none rounded-lg focus:outline-none focus:ring-0 dark:text-white"
                                :disabled="isLoading">
                            <button @click="sendMessage" :disabled="isLoading || (!prompt && !image)"
                                class="ml-4 px-4 py-2 bg-blue-600 text-white rounded-lg disabled:opacity-50">
                                Send
                            </button>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
.typing-animation span {
    animation: blink 1s infinite;
}

.typing-animation span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-animation span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes blink {
    0% {
        opacity: 0;
    }

    50% {
        opacity: 1;
    }

    100% {
        opacity: 0;
    }
}

.streaming-cursor {
    animation: cursor-blink 1s infinite;
    margin-left: 2px;
}

@keyframes cursor-blink {

    0%,
    50% {
        opacity: 1;
    }

    51%,
    100% {
        opacity: 0;
    }
}
</style>