# Http-Proxy
Http proxy server built with [async Amphp framework](https://github.com/amphp/amp).

## Features
- Simple php http proxy server
- Additional upstream proxy support 
- Minimal latency, memory, overhead.
- HTTPS interception (MITM)

## TODO:
- User define rules for requests interception
- Caching
- Docker support
- Https upstream proxy support

## Requirements
- php 7.4
- composer
- cli / shell

## Setup
- `composer create-project xtrime-ru/http-proxy http-proxy`
- `cd http-proxy`
- listen connections:
    - local: `php proxy.php --host=127.0.0.1 --port=9600`
    - all: `php proxy.php --host=0.0.0.0 --port=9600`
- use additional proxy:  
    `php proxy.php --host=0.0.0.0 --port=9600 --proxy=http://proxy.domain.com:999`
- use MITM server to intercept https requests
    - Generate the https certificate (one-time action): `openssl req -x509 -newkey rsa:4096 -keyout cert/key.pem -out cert/cert.pem -days 365 -nodes`
    - press enter multiple times to fill default values
    - `php proxy.php --host=0.0.0.0 --port=9600 --mitm-port=9601`

## Usage Examples
- Get https site: `curl -x "http://127.0.0.1:9600" https://2ip.ru -v`
- Get http site: `curl -x "http://127.0.0.1:9600" http://2ip.ru -v`
- Get https site with MITM https interception: `curl -k -x "http://127.0.0.1:9600" https://2ip.ru`
- Get http site with additional proxy.  
    Authorization headers will be forwarded to additional proxy:   
    `curl -x "http://user:password@127.0.0.1:9600" http://2ip.ru -v`
