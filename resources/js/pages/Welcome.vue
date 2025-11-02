<script setup lang="ts">
import { ref } from 'vue';

import axios from 'axios';

import { Head } from '@inertiajs/vue3';

const prompt = ref('');
const completion = ref('');
const messages = ref<{ role: string; content: string }[]>([]);
const isLoading = ref(false);

const sendMessage = async () => {
    if (!prompt.value || isLoading.value) return;

    messages.value.push({ role: 'user', content: prompt.value });
    isLoading.value = true;

    try {
        const response = await axios.post('/chat', { prompt: prompt.value });
        completion.value = response.data;
        messages.value.push({ role: 'assistant', content: completion.value });
    } catch (error) {
        console.error(error);
        messages.value.push({ role: 'assistant', content: 'Sorry, I had an error.' });
    } finally {
        prompt.value = '';
        isLoading.value = false;
    }
};
</script>

<template>

    <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div class="flex h-screen flex-col items-center bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a]">
        <header class="w-full border-b border-gray-200 dark:border-gray-800">
            <div class="container mx-auto flex items-center justify-between p-4">
                <h1 class="text-xl font-semibold">Chat with Gemini</h1>
            </div>
        </header>

        <main class="flex-grow w-full max-w-2xl flex flex-col">
            <div class="flex-grow p-6 space-y-6 overflow-y-auto">
                <div v-for="(message, index) in messages" :key="index" class="flex"
                    :class="{ 'justify-end': message.role === 'user' }">
                    <div class="max-w-lg px-4 py-2 rounded-2xl" :class="{
                        'bg-blue-600 text-white': message.role === 'user',
                        'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200': message.role === 'assistant'
                    }">
                        <p>{{ message.content }}</p>
                    </div>
                </div>
                <div v-if="isLoading" class="flex justify-start">
                    <div
                        class="max-w-lg px-4 py-2 rounded-2xl bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                        <p>Thinking...</p>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-gray-200 dark:border-gray-800">
                <div class="flex items-center">
                    <input v-model="prompt" @keyup.enter="sendMessage" type="text" placeholder="Type your message..."
                        class="flex-grow px-4 py-2 bg-transparent border-none rounded-lg focus:outline-none focus:ring-0 dark:text-white"
                        :disabled="isLoading">
                    <button @click="sendMessage" :disabled="isLoading || !prompt"
                        class="ml-4 px-4 py-2 bg-blue-600 text-white rounded-lg disabled:opacity-50">
                        Send
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>
