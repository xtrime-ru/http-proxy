# Http-Proxy
Http proxy server built with async amphp framework.

# Features
- Http proxy server
- Fast and async
- Additional upstream proxy support 

# Requirements
- php 7.4
- composer
- cli / shell

# Setup
- `composer create-project xtrime-ru/http-proxy http-proxy`
- `cd http-proxy`
- listen connections:
    - local: `php proxy.php --host=127.0.0.1 --port=9600`
    - all: `php proxy.php --host=0.0.0.0 --port=9600`
- use additional proxy:  
    `php proxy.php --host=0.0.0.0 --port=9600 --proxy=proxy.domain.com:999`

# Usage Examples
- Get https site: `curl -x "http://127.0.0.1:9600" https://2ip.ru -v`
- Get http site: `curl -x "http://127.0.0.1:9600" http://2ip.ru -v`
- Get http site with additional proxy.  
    Authorization headers will be forwarded to additional proxy:   
    `curl -x "http://user:password@127.0.0.1:9600" http://2ip.ru -v`
