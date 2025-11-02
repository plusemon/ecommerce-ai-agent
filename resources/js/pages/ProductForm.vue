<template>
    <div class="container mx-auto px-4 py-8">
        <Card class="max-w-md mx-auto shadow-lg">
            <CardHeader>
                <CardTitle class="text-2xl font-bold text-gray-800">{{ product ? 'Edit Product' : 'Add New Product' }}</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submitForm">
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="title">Title</Label>
                            <Input id="title" v-model="form.title" placeholder="Enter product title" required />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="category">Category</Label>
                            <Input id="category" v-model="form.category" placeholder="Enter product category" required />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="thumbnail">Thumbnail URL</Label>
                            <Input id="thumbnail" v-model="form.thumbnail" placeholder="e.g., https://example.com/image.jpg" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="price">Price</Label>
                            <Input id="price" v-model="form.price" type="number" step="0.01" placeholder="e.g., 99.99" required />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-6">
                        <Button type="button" variant="outline" @click="router.get('/products')">
                            Cancel
                        </Button>
                        <Button type="submit">
                            {{ product ? 'Update Product' : 'Add Product' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Product {
    id?: number;
    title: string;
    category: string;
    thumbnail: string;
    price: number;
}

const props = defineProps<{
    product?: Product;
}>();

const form = ref<Product>(props.product ? { ...props.product } : {
    title: '',
    category: '',
    thumbnail: '',
    price: 0,
});

watch(() => props.product, (newProduct) => {
    if (newProduct) {
        form.value = { ...newProduct };
    } else {
        form.value = {
            title: '',
            category: '',
            thumbnail: '',
            price: 0,
        };
    }
});

const submitForm = () => {
    if (form.value.id) {
        router.put(`/products/${form.value.id}`, form.value);
    } else {
        router.post('/products', form.value);
    }
};
</script>

