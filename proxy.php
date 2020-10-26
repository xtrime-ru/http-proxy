<?php

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Start in CLI');
}

$shortopts = 'h::p::';
$longopts = [
    'host::', // ip адресс сервера
    'port::',  // порт сервера
    'proxy::',  // адрес дополнительной прокси
    'help', //нужна ли справка?
];
$options = getopt($shortopts, $longopts);
$options = [
    'host' => (string) ($options['address'] ?? $options['a'] ?? '127.0.0.1'),
    'port' => (int) ($options['port'] ?? $options['p'] ?? 9600),
    'proxy' => (string) ($options['proxy'] ?? '') ?: null,
    'help' => isset($options['help']),
];

if ($options['help']) {
    $help = 'Forward caching proxy server built with async amphp framework.

usage: php server.php [--help] [-a=|--address=127.0.0.1] [-p=|--port=9600]

Options:
        --help      Show this message
        
    -h  --host      Server ip (optional) (default: 127.0.0.1)
                    To listen external connections use 0.0.0.0
                    
    -p  --port      Server port (optional) (default: 9600)
        
        --proxy     Additional proxy. All data, including authorization headers, will be forwarded there.
    
';
    echo $help;
    exit;
}

require_once __DIR__ . '/bootstrap.php';

\Amproxy\ProxyServer::start($options['host'], $options['port'], $options['proxy']);