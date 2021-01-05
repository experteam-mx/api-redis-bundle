API Redis Bundle
=

Redis Services for APIs in Symfony 5.1+ <br>
It includes:
   - <b>Redis Client:</b> Predis client wrapper for writing and reading in redis.
   - <b>Post Change:</b> Service to save entities in redis and dispatch message queues.

### Install

1. Configure required package on `composer.json`: <br>
```
"require": {
    "experteam/api-redis-bundle": "dev-master#[commit-hash]"
}
```

2. Run the composer command to install or update the package: <br>
```
composer update experteam/api-redis-bundle
```




### License 
[MIT license](https://opensource.org/licenses/MIT).
