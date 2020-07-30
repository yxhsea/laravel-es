<?php

namespace App\Http\Controllers;

use App\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Elasticsearch\ClientBuilder;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ProductController extends Controller
{
    const PRODUCT_CREATE = 'laravel.product.create';
    const PRODUCT_UPDATE = 'laravel.product.update';
    const PRODUCT_DELETE = 'laravel.product.delete';

    private $connection;
    private $channel;
    private $routingKey;
    private $exchange;

    const INDEX = "product";
    const TYPE = "_doc";

    private $client;

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->setHosts(['127.0.0.1:9200'])
            ->build();

        $connConf = [
            'host' => '127.0.0.1',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
            'vhost' => '/'
        ];

        $this->connection =  new AMQPStreamConnection(
            $connConf['host'],
            $connConf['port'],
            $connConf['user'],
            $connConf['password'],
            $connConf['vhost']
        );

        $this->exchange = "event-bus";
        $this->channel = $this->connection->channel();
        $this->channel->basic_qos(0, 1, false);
        $this->channel->exchange_declare($this->exchange, "topic", true, true, false);
    }

    public function __destruct()
    {
        // 关闭信道和链接
        $this->channel->close();
        $this->connection->close();
    }

    private function publishMsg($body)
    {
        $msg = new AMQPMessage(serialize($body), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $this->channel->basic_publish($msg, $this->exchange, $this->routingKey);
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
            ProductModel::PRODUCT_ID    => $productId,
            ProductModel::TITLE         => $title,
            ProductModel::LONG_TITLE    => $longTitle,
            ProductModel::DESCRIPTION   => $description,
            ProductModel::SKU           => $sku,
            ProductModel::PRICE         => $price,
            ProductModel::SALES         => $sales,
            ProductModel::CREATED_AT    => $nowTime,
            ProductModel::UPDATED_AT    => $nowTime
        ];

        // 将数据投递到 RabbitMQ
        $this->routingKey = self::PRODUCT_CREATE;
        $this->publishMsg($params);

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

        $params = [
            ProductModel::PRODUCT_ID => $productId,
        ];

        // 将数据投递到 RabbitMQ
        $this->routingKey = self::PRODUCT_DELETE;
        $this->publishMsg($params);

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
            ProductModel::PRODUCT_ID    => $productId,
            ProductModel::TITLE         => $title,
            ProductModel::LONG_TITLE    => $longTitle,
            ProductModel::DESCRIPTION   => $description,
            ProductModel::SKU           => $sku,
            ProductModel::PRICE         => $price,
            ProductModel::SALES         => $sales,
            ProductModel::UPDATED_AT    => $nowTime
        ];

        // 将数据投递到 RabbitMQ
        $this->routingKey = self::PRODUCT_UPDATE;
        $this->publishMsg($params);

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
