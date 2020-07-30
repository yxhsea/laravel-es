## Laravel-ES

该项目以一个商品的新增、更新、删除，并且将数据同步到 ElasticSearch 中为例子。
案例中有两种数据同步方式，一是：先操作数据库，然后操作 ElasticSearch，
二是：先操作数据库，然后通过异步的方式，将消息投递到 RabbitMQ 消息队列。
