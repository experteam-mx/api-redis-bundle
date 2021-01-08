API Redis Bundle
=

Redis Services for APIs in Symfony 5.1+ <br>
It includes:
   - <b>Redis Client:</b> Predis client wrapper for writing and reading in redis.
   - <b>Redis Transport:</b> Service to save entities in redis and dispatch message queues.

### Install

1. Run de following composer command: <br>
```
composer require experteam/api-redis-bundle
```

2. Create the configuration file or copy example file to: <br>

`config/packages/experteam_api_redis.yaml`

3. Set the bundle configuration: <br>
```
experteam_api_redis:
    serialize_groups:
        save: read
        message: read
    logger:
        save: true
        message: true
    entities:
        [Entity Namespace]:
            prefix: [prefix]
            save: false
            message: true
        [Entity Namespace]:
            prefix: [prefix]
            save: true
            message: false
```

### Update

Run de following composer command: <br>
```
composer update experteam/api-redis-bundle
```


### License 
[MIT license](https://opensource.org/licenses/MIT).
