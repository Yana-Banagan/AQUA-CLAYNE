<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasUuids;

    protected $table = 'inventory';

    const UPDATED_AT = 'updated_at';
    const CREATED_AT = null;

    protected $fillable = [
        'product_id',
        'quantity_on_hand',
        'reorder_level',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
