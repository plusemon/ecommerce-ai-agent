<template>

    <Head title="Products" />
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Our Products</h1>
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <div class="flex items-center gap-4 w-full">
                <Input v-model="search" placeholder="Search products by title" class="w-full sm:w-2/3 lg:w-1/2"
                    @keyup.enter="applySearch" />
                <Button @click="applySearch" class="w-full sm:w-auto">
                    Search
                </Button>
            </div>
            <div class="flex items-center gap-4 w-full sm:w-auto">
                <Button @click="router.get('/')" class="w-full sm:w-auto">
                    Chat
                </Button>
                <Button @click="router.get('/products/create')" class="w-full sm:w-auto">
                    Add New Product
                </Button>
            </div>

        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <Card v-for="product in products" :key="product.id"
                class="flex flex-col shadow-lg hover:shadow-xl transition-shadow duration-300">
                <CardHeader class="p-0">
                    <img :src="product.thumbnail" alt="Product Thumbnail"
                        class="w-full aspect-video object-cover rounded-t-lg" />
                </CardHeader>
                <CardContent class="p-4 flex-grow">
                    <CardTitle class="text-xl font-semibold mb-2">{{ product.title }}</CardTitle>
                    <p class="text-sm text-gray-500 mb-1">{{ product.category }}</p>
                    <p class="text-2xl font-bold text-blue-600 mt-3">${{ product.price }}</p>
                </CardContent>
                <CardFooter class="flex justify-between p-4 border-t">
                    <Button variant="outline" size="sm" @click="router.get(`/products/${product.id}/edit`)">
                        Edit
                    </Button>
                    <Button variant="destructive" size="sm" @click="deleteProduct(product.id)">
                        Delete
                    </Button>
                </CardFooter>
            </Card>
        </div>
    </div>
</template>

<script setup lang="ts">
import {
    ref,
    watch,
} from 'vue';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Head,
    router,
} from '@inertiajs/vue3';

interface Product {
    id: number;
    title: string;
    category: string;
    thumbnail: string;
    price: number;
}

const props = defineProps<{
    products: Product[];
    filters: { search?: string };
}>();

const search = ref(props.filters.search || '');

const applySearch = () => {
    router.get('/products', { search: search.value }, { preserveState: true, replace: true });
};

const deleteProduct = (id: number) => {
    if (confirm('Are you sure you want to delete this product?')) {
        router.delete(`/products/${id}`);
    }
};

watch(search, (newValue, oldValue) => {
    if (newValue === '' && oldValue !== '') {
        applySearch();
    }
});
</script>
