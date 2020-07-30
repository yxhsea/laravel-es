<?php

namespace App\Console\Commands;

use App\ProductModel;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Elasticsearch\ClientBuilder;

class SubscribeProductCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscribe:product';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe product data to elasticSearch';

    const INDEX = "product";
    const TYPE = "_doc";

    private $client;

    private $connection;
    private $channel;
    private $routingKey;
    private $exchange;
    private $queue;

    private $mapping = [
        'laravel.product.create' => 'createProduct',
        'laravel.product.update' => 'updateProduct',
        'laravel.product.delete' => 'deleteProduct'
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
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

        $this->routingKey = "laravel.product.#";
        $this->exchange = "event-bus";
        $this->queue = "laravel.product";

        $this->channel = $this->connection->channel();
        $this->channel->basic_qos(0, 1, false);
        $this->channel->queue_declare($this->queue);
        $this->channel->queue_bind($this->queue, $this->exchange, $this->routingKey);

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->listen(function($msg) {
            $routingKey = $msg->delivery_info['routing_key'];
            $body = unserialize($msg->body);
            $this->mapping[$routingKey]($body);

            #当no_ack=false时， 需要写下行代码，否则可能出现内存不足情况#$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        });
    }

    /**
     * 商品数据写入 ES
     * @param $body
     */
    protected function createProduct($body)
    {
        $params = [
            'body' => [
                ProductModel::PRODUCT_ID    => $body[ProductModel::PRODUCT_ID],
                ProductModel::TITLE         => $body[ProductModel::TITLE],
                ProductModel::LONG_TITLE    => $body[ProductModel::LONG_TITLE],
                ProductModel::DESCRIPTION   => $body[ProductModel::DESCRIPTION],
                ProductModel::SKU           => $body[ProductModel::SKU],
                ProductModel::PRICE         => $body[ProductModel::PRICE],
                ProductModel::SALES         => $body[ProductModel::SALES],
                ProductModel::CREATED_AT    => $body[ProductModel::CREATED_AT],
                ProductModel::UPDATED_AT    => $body[ProductModel::UPDATED_AT]
            ],
            'id'    => $body[ProductModel::PRODUCT_ID],
            'index' => self::INDEX,
            'type'  => self::TYPE,
        ];

        // 商品数据写入 ES
        $this->client->create($params);
    }

    /**
     * 更新 ES 中的商品数据
     * @param $body
     */
    protected function updateProduct($body)
    {
        $params = [
            'body' => [
                ProductModel::PRODUCT_ID    => $body[ProductModel::PRODUCT_ID],
                ProductModel::TITLE         => $body[ProductModel::TITLE],
                ProductModel::LONG_TITLE    => $body[ProductModel::LONG_TITLE],
                ProductModel::DESCRIPTION   => $body[ProductModel::DESCRIPTION],
                ProductModel::SKU           => $body[ProductModel::SKU],
                ProductModel::PRICE         => $body[ProductModel::PRICE],
                ProductModel::SALES         => $body[ProductModel::SALES],
                ProductModel::CREATED_AT    => $body[ProductModel::CREATED_AT],
                ProductModel::UPDATED_AT    => $body[ProductModel::UPDATED_AT]
            ],
            'id'    => $body[ProductModel::PRODUCT_ID],
            'index' => self::INDEX,
            'type'  => self::TYPE,
        ];

        // 商品数据更新到 ES
        $this->client->update($params);

    }

    /**
     * 删除 ES 中的商品数据
     * @param $body
     */
    protected function deleteProduct($body)
    {
        // 删除 ES 中的商品数据
        $params = [
            'id'    => $body[ProductModel::PRODUCT_ID],
            'index' => self::INDEX,
            'type'  => self::TYPE,
        ];
        $this->client->delete($params);
    }

    private function listen(callable $callback)
    {
        try {
            $this->channel->basic_consume($this->queue,'',false,false,false,false, $callback);
            while(count($this->channel->callbacks)) {
                $this->channel->wait();
            }
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }
}
