# Amproxy
Http proxy server built with async amphp framework.

# Features
- Http proxy server
- Fast and async

# Requirements
- php 7.4
- composer
- cli / shell

# Setup
- `composer create-project xtrime-ru/amproxy amproxy`
- `cd amproxy`
- listen connections:
    - local: `php proxy --host=127.0.0.1 --port=9600`
    - all: `php proxy --host=0.0.0.0 --port=9600`

# Usage Examples
- Get https site: `curl -x "http://127.0.0.1:9600" https://2ip.ru -v`
- Get http site: `curl -x "http://127.0.0.1:9600" http://2ip.ru -v`

