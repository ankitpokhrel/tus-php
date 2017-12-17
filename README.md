# Tus PHP
[![Build](https://img.shields.io/travis/ankitpokhrel/tus-php.svg?style=flat-square)](https://travis-ci.org/ankitpokhrel/tus-php/)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/ankitpokhrel/tus-php.svg?style=flat-square)](https://scrutinizer-ci.com/g/ankitpokhrel/tus-php/)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/ankitpokhrel/tus-php.svg?style=flat-square)](https://scrutinizer-ci.com/g/ankitpokhrel/tus-php/)
[![Download](https://img.shields.io/packagist/dm/ankitpokhrel/tus-php.svg?style=flat-square)](https://packagist.org/packages/ankitpokhrel/tus-php)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://github.com/ankitpokhrel/tus-php/blob/master/LICENSE)

_A pure PHP server and client for the [tus resumable upload protocol v1.0.0](https://tus.io)_.

### Overview
tus is a HTTP based protocol for resumable file uploads. Resumable means you can carry on where you left off without 
re-uploading whole data again in case of any interruptions. An interruption may happen willingly, if the user wants 
to pause, or by accident in case of a network issue or server outage.

### Installation

Pull the package via composer.
```shell
$ composer require ankitpokhrel/tus-php
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

### Extension support
- [x] The Creation extension is mostly implemented and is used for creating the upload. Deferring the upload's length is not possible at the moment.
- [x] The Termination extension is implemented which is used to terminate completed and unfinished uploads allowing the Server to free up used resources.
- [x] The Checksum extension is not implemented at the moment but the server verifies the upload internally using `sha256` algorithm.
- [ ] Todo: Checksum extension
- [ ] Todo: Expiration extension
- [ ] Todo: Concatenation extension

### Example
An ajax based example for this implementation can be found in `examples/` folder. To run it, create a virtual host called `tus.local` 
and add proper nginx/apache conf to serve the request. 

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
    $ vendor/bin/php-cs-fixer fix <changes> --rules=@PSR2
    ```

### Questions about this project?
Please feel free to report any bug found. Pull requests, issues, and plugin recommendations are more than welcome!
