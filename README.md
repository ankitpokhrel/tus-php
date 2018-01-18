# Tus PHP
[![PHP Version](https://img.shields.io/badge/php-7.1.3%2B-brightgreen.svg?style=flat-square)](https://packagist.org/packages/ankitpokhrel/tus-php)
[![Build](https://img.shields.io/travis/ankitpokhrel/tus-php/master.svg?style=flat-square)](https://travis-ci.org/ankitpokhrel/tus-php)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/ankitpokhrel/tus-php.svg?style=flat-square)](https://scrutinizer-ci.com/g/ankitpokhrel/tus-php/)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/ankitpokhrel/tus-php.svg?style=flat-square)](https://scrutinizer-ci.com/g/ankitpokhrel/tus-php/)
[![Download](https://img.shields.io/packagist/dt/ankitpokhrel/tus-php.svg?style=flat-square)](https://packagist.org/packages/ankitpokhrel/tus-php)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://github.com/ankitpokhrel/tus-php/blob/master/LICENSE)

_A pure PHP server and client for the [tus resumable upload protocol v1.0.0](https://tus.io)_.

### Overview
tus is a HTTP based protocol for resumable file uploads. Resumable means you can carry on where you left off without 
re-uploading whole data again in case of any interruptions. An interruption may happen willingly, if the user wants 
to pause, or by accident in case of a network issue or server outage.

### Installation

Pull the package via composer.
```shell
$ composer require ankitpokhrel/tus-php:dev-master
```

## Usage

#### Server
This is how a simple server looks like.

```php
$server = new \TusPhp\Tus\Server('redis'); // Leave empty for file based cache

$server->serve();
```

You need to configure your server to listen to specific endpoint. If you are using nginx, you can use similar rule as below to listen 
to the specific api path.

```nginx
location /files {
    try_files $uri $uri/ /server.php?$query_string;
}
```

#### Client
Client can be used for creating, resuming and/or deleting uploads.


```php
$client = new \TusPhp\Tus\Client($baseUrl, 'redis'); // Leave second parameter empty for file based cache

$client->file('/path/to/file', 'filename.ext');

// Create and upload a chunk of 1mb
$bytesUploaded = $client->upload(1000000); 

// Resume, $bytesUploaded = 2mb
$bytesUploaded = $client->upload(1000000); 

// To upload whole file, skip length param
$client->file('/path/to/file', 'filename.ext')->upload();
```

To check if the file was partially uploaded before, you can use `getOffset` method. It returns false if the upload 
isn't there or invalid, returns total bytes uploaded otherwise.

```php 
$offset = $client->getOffset(); // 2000000 bytes or 2mb
```

Delete partial upload from cache.

```php
$checksum = $client->getChecksum();

$client->delete($checksum);
```

By default the client uses `/files` as a api path. You can change it with `setApiPath` method.

```php
$client->setApiPath('/api');
```

By default the server will use `sha256` algorithm to verify the integrity of the upload. If you want to use different hash algorithm, you can do so by 
using `setChecksumAlgorithm` method. To get the list of supported hash algorithms, you can send `OPTIONS` request to the server. 

```php
$client->setChecksumAlgorithm('crc32');
```

### Extension support
- [x] The Creation extension is mostly implemented and is used for creating the upload. Deferring the upload's length is not possible at the moment.
- [x] The Termination extension is implemented which is used to terminate completed and unfinished uploads allowing the Server to free up used resources.
- [x] ~~Todo: Checksum extension~~ The Checksum extension is implemented, the server will use `sha256` algorithm by default to verify the upload.
- [x] ~~Todo: Expiration extension~~ The Expiration extension is implemented, details below.
- [ ] Todo: Concatenation extension

### Expiration
The Server is capable of removing expired but unfinished uploads. You can use following command manually or in a cron job to remove them.

```shell
$ bin/tus tus:expired --help

Usage:
  tus:expired [<cache-adapter>]

Arguments:
  cache-adapter     Cache adapter to use, redis or file. Optional, defaults to file based cache.
  
eg:

$ bin/tus tus:expired redis
```

### Setting up dev environment and/or running example locally
An ajax based example for this implementation can be found in `examples/` folder. You can either build and run it using docker or use kubernetes locally with minikube.

![Tus PHP demo](https://github.com/ankitpokhrel/tus-php/blob/master/example/example.png "")
 
#### Docker
Make sure that [docker](https://docs.docker.com/engine/installation/) and [docker-compose](https://docs.docker.com/compose/install/) 
are installed in your system. Then, run docker script from project root.
```shell
$ bin/docker.sh
```

Now, the client can be accessed at http://0.0.0.0:8080 and server can be accessed at http://0.0.0.0:8081. Default api endpoint is set to`/files` 
and uploaded files can be found inside `uploads` folder. All docker configs can be found in `docker/` folder.

#### Kubernetes with minikube
Make sure you have [minikube](https://github.com/kubernetes/minikube) and [kubectl](https://kubernetes.io/docs/tasks/tools/install-kubectl/) 
are installed in your system. Then, build and spin up containers using k8s script from project root.
```shell
$ bin/k8s.sh
```

The script will set minikube docker env, build all required docker images locally, create kubernetes objects and serve client at port `30020`. After successful build, 
the client can be accessed at http://192.168.99.100:30020 and server can be accessed at http://192.168.99.100:30021. 

The script will create 1 client replica and 3 server replicas by default. All kubernetes configs can be found inside `k8s/` folder, you can tweak it as required.

You can use another helper script while using minikube to list all uploaded files, login to redis and clear redis cache.
```shell
# List all uploads
$ bin/minikube.sh uploads

# Login to redis
$ bin/minikube.sh redis

# Clear redis cache
$ bin/minikube.sh clear-cache
```

Since the server supports tus expiration extension, a cron job is set to run once a day at midnight to free server resources. You can adjust it as required in `k8s/cron.yml`. 

### Contributing
1. Install [PHPUnit](https://phpunit.de/) and [composer](https://getcomposer.org/) if you haven't already.
2. Install dependencies
     ```shell
     $ composer install
     ```
3. Run tests with phpunit
    ```shell
    $ ./vendor/bin/phpunit
    ```
4. Validate changes against [PSR2 Coding Standards](http://www.php-fig.org/psr/psr-2/)
    ```shell
    $ ./vendor/bin/php-cs-fixer fix <changes> --rules=@PSR2,not_operator_with_space,single_quote
    ```

### Questions about this project?
Please feel free to report any bug found. Pull requests, issues, and plugin recommendations are more than welcome!
