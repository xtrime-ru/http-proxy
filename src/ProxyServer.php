<?php

namespace HttpProxy;

use Amp;
use Amp\Socket;
use Amp\Socket\EncryptableSocket;
use function Amp\call;

final class ProxyServer extends AbstractSocketServer
{
    public static function start(string $host, int $port, ?string $proxy = null): void
    {
        static::$externalProxy = static::sanitizeProxyUri($proxy);
        Amp\Loop::defer(
            function() use ($host, $port) {
                $server = Amp\Socket\Server::listen("tcp://{$host}:{$port}");
                print "Http Proxy Server listening on: http://" . $server->getAddress() . " ..." . PHP_EOL;

                /** @var Socket\ResourceSocket $socket */
                while ($socket = yield $server->accept()) {
                    call(static function() use($socket) {
                        try {
                            /** @var EncryptableSocket $remoteTunnel */
                            $remoteSocket = yield from static::openRemoteTunnel($socket);
                            yield from static::transferData($socket, $remoteSocket);
                        } catch (\Throwable $e) {
                            yield $socket->write("HTTP/1.1 400 Bad Request\r\n\r\n");
                            yield $socket->end($e->getMessage() . "\n");
                        }
                    });
                }
            }
        );
    }

}