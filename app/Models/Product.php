<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'description',
        'price_cents',
        'currency',
        'category',
        'tags',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'tags' => 'array',
    ];

    public function formattedPrice(): string
    {
        return strtoupper($this->currency).' '.number_format($this->price_cents / 100, 2);
    }

    public function toCatalogArray(): array
    {
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'price' => $this->formattedPrice(),
        ];
    }
}
