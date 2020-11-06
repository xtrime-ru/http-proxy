<?php

use Amp\Loop;
use HttpProxy\MitmServer;
use HttpProxy\ProxyServer;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Start in CLI');
}

$shortopts = 'h::p::m::';
$longopts = [
    'host::', // ip адресс сервера
    'port::',  // порт сервера
    'mitm-port::', //порт MITM сервера
    'proxy::',  // адрес дополнительной прокси
    'help', //нужна ли справка?
];
$options = getopt($shortopts, $longopts);
$options = [
    'host' => (string) ($options['host'] ?? $options['h'] ?? '127.0.0.1'),
    'port' => (int) ($options['port'] ?? $options['p'] ?? 9600),
    'mitm-port' => (int) ($options['mitm-port'] ?? $options['m'] ?? 0),
    'proxy' => (string) ($options['proxy'] ?? '') ?: null,
    'help' => isset($options['help']),
];

if ($options['help']) {
    $help = 'Forward caching proxy server built with async amphp framework.

usage: php server.php [--help] [-h=|--host=127.0.0.1] [-p=|--port=9600] [-m=|--mitm-port=0]

Options:
        --help      Show this message
        
    -h  --host      Server ip (optional) (default: 127.0.0.1)
                    To listen external connections use 0.0.0.0
                    
    -p  --port      Server port (optional) (default: 9600)
    
    -m  --mitm-port Man In The Middle server port (optional) (default: 0)
                    This is internal port for https request interseption.
                    If defined, then MITM server will be started and all https requests will be intercepted.
                    Disabled by default.
                    Note: included https certificate is selfsigned, so client/browsers should be configured to ignore https errors.
        
        --proxy     Additional proxy. All data, including authorization headers, will be forwarded there.
    
';
    echo $help;
    exit;
}

require_once __DIR__ . '/bootstrap.php';

ProxyServer::start($options['host'], $options['port'], $options['proxy']);
if ($options['mitm-port'] !== 0) {
    MitmServer::start($options['host'], $options['mitm-port'], $options['proxy']);
}
Loop::run();
