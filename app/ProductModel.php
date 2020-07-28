<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    const TABLE_NAME = 'product';

    const ID = 'id';
    const PRODUCT_ID = 'product_id';
    const TITLE = 'title';
    const LONG_TITLE = 'long_title';
    const DESCRIPTION = 'description';
    const SKU = 'sku';
    const PRICE = 'price';
    const SALES = 'sales';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
