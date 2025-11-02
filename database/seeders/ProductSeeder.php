<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::create([
            'title' => 'Laptop Pro',
            'category' => 'Electronics',
            'thumbnail' => 'https://images.pexels.com/photos/1006293/pexels-photo-1006293.jpeg',
            'price' => 1200.00,
        ]);

        Product::create([
            'title' => 'Mechanical Keyboard',
            'category' => 'Accessories',
            'thumbnail' => 'https://images.pexels.com/photos/4551315/pexels-photo-4551315.jpeg',
            'price' => 150.00,
        ]);

        Product::create([
            'title' => 'Wireless Mouse',
            'category' => 'Accessories',
            'thumbnail' => 'https://images.pexels.com/photos/2115256/pexels-photo-2115256.jpeg',
            'price' => 75.00,
        ]);

        Product::create([
            'title' => '4K Monitor',
            'category' => 'Electronics',
            'thumbnail' => 'https://images.pexels.com/photos/6023619/pexels-photo-6023619.jpeg',
            'price' => 450.00,
        ]);

        Product::create([
            'title' => 'Gaming Headset',
            'category' => 'Accessories',
            'thumbnail' => 'https://images.pexels.com/photos/1649771/pexels-photo-1649771.jpeg',
            'price' => 100.00,
        ]);
    }
}
