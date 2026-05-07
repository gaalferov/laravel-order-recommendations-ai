<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ProductRecommender
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a product recommendation engine for an online store.

You will receive:
- A list of items the customer just purchased (with name, category, tags).
- A catalog of other available products (with SKU, name, category, tags, description).
- A target number of recommendations.

Pick the best complementary products from the catalog based on what the customer bought. Prefer cross-category pairings (e.g. if they bought coffee beans, suggest brewing equipment or accessories), and avoid duplicating what they already have.

Return json with a single "recommendations" key, containing an array of objects with:
- "sku": product SKU, must exactly match one from the provided catalog
- "reason": a short, friendly reason tied to their purchase (max 15 words)

Only recommend products whose SKUs appear in the provided catalog. Never invent SKUs.
PROMPT;

    /**
     * @param  array<int, array{sku: string, name: string, category: string, tags: array<int, string>}>  $purchasedItems
     * @return array<int, array{sku: string, name: string, description: string, price: string, reason: string}>
     */
    public function recommend(array $purchasedItems, int $count = 3): array
    {
        if ($count < 1 || empty($purchasedItems)) {
            return [];
        }

        $purchasedSkus = array_column($purchasedItems, 'sku');

        $catalog = Product::query()
            ->when($purchasedSkus, fn ($query) => $query->whereNotIn('sku', $purchasedSkus))
            ->get();

        if ($catalog->isEmpty()) {
            return [];
        }

        $catalogArray = $catalog->map(fn (Product $p) => $p->toCatalogArray())->all();

        $userInput = json_encode([
            'purchased' => $purchasedItems,
            'catalog' => $catalogArray,
            'count' => $count,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = OpenAI::chat()->create([
            'model' => config('recommendations.ai_model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $userInput],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.4,
            'max_tokens' => 500,
        ]);

        $decoded = json_decode($response->choices[0]->message->content, true);

        if (! is_array($decoded) || ! isset($decoded['recommendations']) || ! is_array($decoded['recommendations'])) {
            Log::warning('AI recommendations returned unexpected format', [
                'raw' => $response->choices[0]->message->content,
            ]);

            return [];
        }

        $catalogBySku = $catalog->keyBy('sku');
        $recommendations = [];

        foreach ($decoded['recommendations'] as $rec) {
            if (! isset($rec['sku']) || ! $catalogBySku->has($rec['sku'])) {
                continue;
            }

            $product = $catalogBySku->get($rec['sku']);

            $recommendations[] = [
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->formattedPrice(),
                'reason' => isset($rec['reason']) && is_string($rec['reason'])
                    ? trim($rec['reason'])
                    : '',
            ];

            if (count($recommendations) >= $count) {
                break;
            }
        }

        return $recommendations;
    }
}
