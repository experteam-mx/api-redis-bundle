API Redis Bundle
=

Redis Services for APIs in Symfony 5.1+ <br>
It includes:
   - <b>Redis Client:</b> Predis client wrapper for writing and reading in redis.
   - <b>Post Change:</b> Service to save entities in redis and dispatch message queues.

### Install

1. Configure the repository in the `composer.json` file: <br>
```
...

"repositories": [
   {
      "type": "vcs",
      "url":  "https://github.com/experteam-mx/api-redis-bundle.git"
   }
]  
```

2. Configure the required package in the `composer.json` file: <br>
```
"require": {
   "experteam/api-redis-bundle": "dev-master"
}
```

3. Execute the following command: <br>
```
composer update experteam/api-redis-bundle
```




### License 
[MIT license](https://opensource.org/licenses/MIT).
