<?php

namespace App\Http\Controllers;

use App\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Elasticsearch\ClientBuilder;

class ProductController extends Controller
{
    const INDEX = "product";
    const TYPE = "_doc";

    private $client;

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->setHosts(['127.0.0.1:9200'])
            ->build();
    }

    /**
     * 创建商品数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createProduct(Request $request)
    {
        $title = $request->request->get(ProductModel::TITLE);
        $longTitle = $request->request->get(ProductModel::LONG_TITLE);
        $description = $request->request->get(ProductModel::DESCRIPTION);
        $sku = $request->request->get(ProductModel::SKU);
        $price = $request->request->get(ProductModel::PRICE);
        $sales = $request->request->get(ProductModel::SALES);

        $nowTime = date("Y-m-d H:i:s");
        // 商品数据写入 DB
        $productId = DB::table(ProductModel::TABLE_NAME)->insertGetId([
            ProductModel::TITLE         => $title,
            ProductModel::LONG_TITLE    => $longTitle,
            ProductModel::DESCRIPTION   => $description,
            ProductModel::SKU           => $sku,
            ProductModel::PRICE         => $price,
            ProductModel::SALES         => $sales,
            ProductModel::CREATED_AT    => $nowTime,
            ProductModel::UPDATED_AT    => $nowTime
        ]);


        $params = [
            'body' => [
                ProductModel::PRODUCT_ID    => $productId,
                ProductModel::TITLE         => $title,
                ProductModel::LONG_TITLE    => $longTitle,
                ProductModel::DESCRIPTION   => $description,
                ProductModel::SKU           => $sku,
                ProductModel::PRICE         => $price,
                ProductModel::SALES         => $sales,
                ProductModel::CREATED_AT    => $nowTime,
                ProductModel::UPDATED_AT    => $nowTime
            ],
            'id'    => $productId,
            'index' => self::INDEX,
            'type'  => self::TYPE,
        ];

        // 商品数据写入 ES
        $this->client->create($params);

        return Response()->json(['code' => 0, 'msg' => 'success']);
    }

    /**
     * 删除商品数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteProduct(Request $request)
    {
        $productId = $request->request->get(ProductModel::PRODUCT_ID);

        // 删除 DB 中的商品数据
        DB::table(ProductModel::TABLE_NAME)->where(ProductModel::PRODUCT_ID, $productId)->delete();


        // 删除 ES 中的商品数据
        $params = [
            'id'    => $productId,
            'index' => self::INDEX,
            'type'  => self::TYPE,
        ];
        $this->client->delete($params);

        return Response()->json(['code' => 0, 'msg' => 'success']);
    }

    /**
     * 更新商品数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProduct(Request $request)
    {
        $productId = $request->request->get(ProductModel::PRODUCT_ID);
        $title = $request->request->get(ProductModel::TITLE);
        $longTitle = $request->request->get(ProductModel::LONG_TITLE);
        $description = $request->request->get(ProductModel::DESCRIPTION);
        $sku = $request->request->get(ProductModel::SKU);
        $price = $request->request->get(ProductModel::PRICE);
        $sales = $request->request->get(ProductModel::SALES);

        $nowTime = date("Y-m-d H:i:s");
        // 商品数据更新到 DB
        DB::table(ProductModel::TABLE_NAME)
            ->where(ProductModel::PRODUCT_ID, $productId)
            ->update([
                ProductModel::TITLE         => $title,
                ProductModel::LONG_TITLE    => $longTitle,
                ProductModel::DESCRIPTION   => $description,
                ProductModel::SKU           => $sku,
                ProductModel::PRICE         => $price,
                ProductModel::SALES         => $sales,
                ProductModel::UPDATED_AT    => $nowTime
            ]);


        $params = [
            'body' => [
                ProductModel::PRODUCT_ID    => $productId,
                ProductModel::TITLE         => $title,
                ProductModel::LONG_TITLE    => $longTitle,
                ProductModel::DESCRIPTION   => $description,
                ProductModel::SKU           => $sku,
                ProductModel::PRICE         => $price,
                ProductModel::SALES         => $sales,
                ProductModel::CREATED_AT    => $nowTime,
                ProductModel::UPDATED_AT    => $nowTime
            ],
            'id'    => $productId,
            'index' => self::INDEX,
            'type'  => self::TYPE,
        ];

        // 商品数据更新到 ES
        $this->client->update($params);

        return Response()->json(['code' => 0, 'msg' => 'success']);
    }

    /**
     * 获取单个商品数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductInfo(Request $request)
    {
        $productId = $request->request->get(ProductModel::PRODUCT_ID);

        $params = [
            'id'    => $productId,
            'index' => self::INDEX,
            'type'  => self::TYPE,
        ];
        $this->client->get($params);

        return Response()->json(['code' => 0, 'msg' => 'success']);
    }

    /**
     * 搜索商品数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductList(Request $request)
    {
        $params = [
            'index' => self::INDEX,
            'type'  => self::TYPE,
        ];
        $this->client->search($params);

        return Response()->json(['code' => 0, 'msg' => 'success']);
    }
}
