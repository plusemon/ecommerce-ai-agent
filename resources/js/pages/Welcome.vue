<script setup lang="ts">
import {
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    Transition,
    watch,
} from 'vue';

import axios from 'axios';
import hljs from 'highlight.js';
import MarkdownIt from 'markdown-it';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Head,
    router,
} from '@inertiajs/vue3';

let md: MarkdownIt;

const props = defineProps<{
    conversations: { id: number; name: string }[];
    currentMessages?: { role: string; content: string; imageUrl?: string }[];
    currentConversationId?: number;
}>();

const prompt = ref('');
const messages = ref<{ role: string; content: string; imageUrl?: string }[]>([]);
const isLoading = ref(false);
const image = ref<File | null>(null);
const imageUrl = ref<string | null>(null);
const conversation_id = ref<number | null>(null);
const localConversations = ref<{ id: number; name: string }[]>([]);
const streamingMessage = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const chatContainer = ref<HTMLDivElement | null>(null);
const showLatestMessagesButton = ref(false);
const promptTextarea = ref<HTMLTextAreaElement | null>(null);

const adjustTextareaHeight = () => {
    if (promptTextarea.value) {
        promptTextarea.value.style.height = 'auto';
        promptTextarea.value.style.height = promptTextarea.value.scrollHeight + 'px';
    }
};

const sendMessageOnEnter = (event: KeyboardEvent) => {
    if (!event.shiftKey) {
        sendMessage();
    }
};

const scrollToBottom = () => {
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
};

const handleScroll = () => {
    if (chatContainer.value) {
        const { scrollTop, scrollHeight, clientHeight } = chatContainer.value;
        const scrollThreshold = 100; // Pixels from bottom
        showLatestMessagesButton.value = scrollHeight - clientHeight - scrollTop > scrollThreshold;
    }
};

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

    const userPrompt = prompt.value;
    const userImage = image.value;

    // Add user message
    messages.value.push({
        role: 'user',
        content: userPrompt,
        ...(userImage && { imageUrl: imageUrl.value || undefined })
    });

    // Clear input immediately
    prompt.value = '';
    adjustTextareaHeight();
    removeImage();
    nextTick(() => {
        promptTextarea.value?.focus();
    });

    isLoading.value = true;
    streamingMessage.value = '';

    try {
        const formData = new FormData();
        formData.append('prompt', userPrompt);
        if (userImage) {
            formData.append('image', userImage);
        }
        if (conversation_id.value) {
            formData.append('conversation_id', String(conversation_id.value));
        }

        const response = await fetch('/send-message', {
            method: 'POST',
            body: formData
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
                            nextTick(() => {
                                scrollToBottom();
                            });

                            // Reload conversations to update the list
                            await loadConversations();
                            break;
                        }

                        if (data.text) {
                            streamingMessage.value += data.text;
                            nextTick(() => {
                                scrollToBottom();
                                promptTextarea.value?.focus();
                            });
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

const switchConversation = (conversationId: number) => {
    router.visit(`/conversations/${conversationId}`, {
        onFinish: () => {
            nextTick(() => {
                scrollToBottom();
            });
        }
    });
};

const deleteConversation = async (conversationId: number) => {
    router.delete(`/conversations/${conversationId}`);
    // remove the conversation from the local list
    localConversations.value = localConversations.value.filter(c => c.id !== conversationId);
}

const renderMarkdown = (markdown: string) => {
    return md.render(markdown);
};

const isDarkMode = ref(localStorage.getItem('theme') === 'dark');

const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value;
    if (isDarkMode.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

onMounted(() => {
    md = new MarkdownIt({
        highlight: function (str, lang) {
            if (lang && hljs.getLanguage(lang)) {
                try {
                    return hljs.highlight(str, { language: lang }).value;
                } catch (__) { }
            }

            return ''; // use external default escaping
        }
    });

    // Initialize local ref with prop, converting numeric ids to strings for compatibility
    localConversations.value = props.conversations.map(c => ({ id: c.id, name: c.name }));

    if (props.currentMessages && props.currentConversationId) {
        messages.value = props.currentMessages;
        conversation_id.value = props.currentConversationId;
        nextTick(() => {
            scrollToBottom();
        });
    } else {
        loadConversations(); // Load conversations if no specific conversation is in the URL
    }

    if (isDarkMode.value) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }

    if (chatContainer.value) {
        chatContainer.value.addEventListener('scroll', handleScroll);
    }

    adjustTextareaHeight(); // Adjust initial height
});

watch(prompt, () => {
    adjustTextareaHeight();
});

onUnmounted(() => {
    if (chatContainer.value) {
        chatContainer.value.removeEventListener('scroll', handleScroll);
    }
});
</script>

<template>

    <Head title="Welcome" />
    <div class="flex h-screen flex-col bg-background text-foreground">
        <!-- Header -->
        <header class="border-b bg-background p-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold">Customer Support Agent</h1>
            <div class="flex items-center gap-4">
                <a href="/products" class="text-primary hover:underline">Products</a>
                <Button @click="toggleDarkMode" variant="outline" size="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                        <path
                            d="M12 2.25a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-1.5 0V3a.75.75 0 0 1 .75-.75ZM7.5 12a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM18.894 6.166a.75.75 0 0 0-1.06-1.06l-1.591 1.59a.75.75 0 1 0 1.06 1.06l1.591-1.59ZM12 18a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-1.5 0V18a.75.75 0 0 1 .75-.75ZM5.007 17.234a.75.75 0 1 0-1.06-1.06l-1.59 1.59a.75.75 0 1 0 1.06 1.06l1.59-1.59ZM21.75 12a.75.75 0 0 1-.75.75h-2.25a.75.75 0 0 1 0-1.5H21a.75.75 0 0 1 .75.75ZM5.007 6.766a.75.75 0 0 0-1.06-1.06L2.356 7.297a.75.75 0 1 0 1.06 1.06l1.59-1.59ZM18.894 17.234a.75.75 0 1 0 1.06 1.06l1.591-1.59a.75.75 0 0 0-1.06-1.06l-1.591 1.59ZM3 12a.75.75 0 0 1 .75-.75h2.25a.75.75 0 0 1 0 1.5H3a.75.75 0 0 1-.75-.75Z"
                            clip-rule="evenodd" />
                    </svg>
                </Button>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar -->
            <aside class="w-64 border-r bg-muted/40 flex flex-col">
                <div class="p-4 border-b">
                    <Button @click="newConversation" class="w-full">
                        New Chat
                    </Button>
                </div>
                <div class="flex-1 overflow-y-auto p-2 space-y-1">
                    <Button v-for="conversation in localConversations" :key="conversation.id" variant="ghost"
                        class="w-full justify-start text-left h-auto py-2 px-3 flex items-center group hover:bg-muted/60 transition-colors duration-200"
                        :class="{ 'bg-muted hover:bg-muted': conversation.id === conversation_id }">
                        <span @click="switchConversation(conversation.id)" class="truncate flex-grow min-w-0">{{
                            conversation.name }}</span>
                        <Button variant="ghost" size="icon"
                            class="h-6 w-6 text-red-500 hover:text-red-700 ml-2 opacity-0 group-hover:opacity-100 transition-opacity"
                            @click.stop="deleteConversation(conversation.id)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </Button>
                    </Button>
                </div>
            </aside>

            <!-- Main Chat Area -->
            <main class="flex flex-col flex-1">
                <div ref="chatContainer" class="flex-1 overflow-y-auto p-6 space-y-6">
                    <div v-for="(message, index) in messages" :key="index" class="flex items-start gap-3"
                        :class="{ 'justify-end': message.role === 'user' }">
                        <div v-if="message.role === 'assistant'"
                            class="flex h-8 w-8 shrink-0 select-none items-center justify-center rounded-md border bg-background">
                            AI
                        </div>
                        <div class="max-w-[70%] rounded-xl p-4" :class="{
                            'bg-primary text-primary-foreground': message.role === 'user',
                            'bg-muted': message.role === 'assistant'
                        }">
                            <div v-html="renderMarkdown(message.content)"></div>
                            <img v-if="message.imageUrl" :src="message.imageUrl"
                                class="mt-2 max-h-48 rounded-lg object-cover" />
                        </div>
                        <div v-if="message.role === 'user'"
                            class="flex h-8 w-8 shrink-0 select-none items-center justify-center rounded-md border bg-background">
                            You
                        </div>
                    </div>

                    <!-- Streaming message -->
                    <div v-if="streamingMessage" class="flex items-start gap-3">
                        <div
                            class="flex h-8 w-8 shrink-0 select-none items-center justify-center rounded-md border bg-background">
                            AI
                        </div>
                        <div class="max-w-[70%] rounded-xl p-4 bg-muted">
                            <div v-html="renderMarkdown(streamingMessage)"></div><span class="streaming-cursor">▋</span>
                        </div>
                    </div>

                    <!-- Loading indicator -->
                    <div v-else-if="isLoading" class="flex items-start gap-3">
                        <div
                            class="flex h-8 w-8 shrink-0 select-none items-center justify-center rounded-md border bg-background">
                            AI
                        </div>
                        <div class="max-w-lg rounded-xl p-4 bg-muted typing-animation">
                            <p><span>.</span><span>.</span><span>.</span></p>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="border-t bg-background p-4 flex flex-col gap-2 relative">
                    <Transition name="fade">
                        <Button v-if="showLatestMessagesButton" @click="scrollToBottom"
                            class="absolute -top-12 left-1/2 -translate-x-1/2 shadow-md">
                            Scroll to Bottom
                        </Button>
                    </Transition>
                    <div v-if="imageUrl" class="relative w-32 h-32 rounded-lg overflow-hidden border">
                        <img :src="imageUrl" class="w-full h-full object-cover" />
                        <Button variant="destructive" size="icon" class="absolute -top-2 -right-2 h-6 w-6 rounded-full"
                            @click="removeImage">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </Button>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="file" @change="handleFileChange" accept="image/*" class="hidden" ref="fileInput" />
                        <Button variant="outline" size="icon" @click="fileInput?.click()" class="shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l-1.586-1.586a2 2 0 00-2.828 0L6 14m6-6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </Button>
                        <textarea v-model="prompt" @keydown.enter.prevent="sendMessageOnEnter"
                            placeholder="Type your message..."
                            class="flex-1 p-2.5 text-sm rounded-lg border border-input bg-background focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none overflow-hidden"
                            :disabled="isLoading" rows="1" ref="promptTextarea"></textarea>
                        <Button @click="sendMessage" :disabled="isLoading || (!prompt && !image)" class="shrink-0">
                            Send
                        </Button>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
.streaming-cursor {
    animation: blink 1s steps(2, start) infinite;
}

@keyframes blink {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0;
    }
}

.typing-animation span {
    display: inline-block;
    animation: typing 1.5s infinite;
}

.typing-animation span:nth-child(1) {
    animation-delay: 0s;
}

.typing-animation span:nth-child(2) {
    animation-delay: 0.3s;
}

.typing-animation span:nth-child(3) {
    animation-delay: 0.6s;
}

@keyframes typing {

    0%,
    20% {
        opacity: 0;
    }

    50% {
        opacity: 1;
    }

    100% {
        opacity: 0;
    }
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
