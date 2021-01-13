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

2. Create the configuration file through any of the following options: <br><br>
   
    a. Manually copy the example file in the package root to the folder `config/packages/`. <br><br>
    b. Copy the `vendor_copy.php` file to the root of the project and configure the scripts in the `composer.json` file:
   ```
   "scripts": {
        "vendor-scripts": [
            "@php vendor_copy.php -s vendor/experteam/api-redis-bundle/experteam_api_redis.yaml.example -d config/packages/experteam_api_redis.yaml --not-overwrite --ignore-no-source"
        ],
        "post-install-cmd": [
            "@vendor-scripts"
        ],
        "post-update-cmd": [
            "@vendor-scripts"
        ]
    },
   ```

3. Edit the bundle configuration file `config/packages/experteam_api_redis.yaml`: <br>
```
experteam_api_redis:
    serialize_groups:
        save: read
        message: read
    elk_logger:
        save: true
        message: true
    entities:
        [Entity Namespace]:
            prefix: [prefix]
            save: false
            message: true
            message_class: App\Message\OperationMessage    #required only if message equal true
        [Entity Namespace]:
            prefix: [prefix]
            save: true
            save_method: getId     #optional (default getId)
            message: false
```

### Update

Run de following composer command: <br>
```
composer update experteam/api-redis-bundle
```


### License 
[MIT license](https://opensource.org/licenses/MIT).
