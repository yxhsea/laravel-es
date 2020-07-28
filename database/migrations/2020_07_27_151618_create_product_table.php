<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 200)->default('')->comment('商品标题');
            $table->string('long_title', 255)->default('商品长标题');
            $table->text('description')->default('商品描述');
            $table->string('sku')->comment('')->comment('商品 SKU');
            $table->decimal('price', 10, 2)->comment('商品价格');
            $table->integer('sales')->default(0)->comment('销售数量');
            $table->date('created_at')->comment('创建时间');
            $table->date('updated_at')->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product');
    }
}
