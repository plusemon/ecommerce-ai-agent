<?php

namespace App\Neuron\Tools;

use App\Models\Product;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class ProductSearchTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'product_search',
            'Searches for products in the database based on a query.',
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                'query',
                PropertyType::STRING,
                'The search query for products (e.g., "laptops", "accessories", "gaming headset").',
                true
            ),
        ];
    }

    /**
     * Implementing the tool logic
     */
    public function __invoke(string $query): string
    {
        $keywords = explode(' ', $query);

        $results = Product::query()
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('title', 'like', "%{$keyword}%");
                }
            })
            ->get(['id', 'title', 'category', 'thumbnail', 'price'])
            ->toArray();

        return collect($results)->map(function ($result) {
            return [
                'id' => $result['id'],
                'title' => $result['title'],
                'category' => $result['category'],
                'thumbnail' => $result['thumbnail'],
                'price' => $result['price'],
                'url' => url("/products/{$result['id']}"),
            ];
        });
    }
}
