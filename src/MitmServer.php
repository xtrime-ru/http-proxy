<?php

namespace HttpProxy;

use Amp\Loop;
use Amp\Socket;
use Amp\Socket\BindContext;
use Amp\Socket\EncryptableSocket;
use function Amp\call;

class MitmServer extends AbstractSocketServer
{
    private static string $host = '127.0.0.1';
    private static int $port = 0;

    public static function start(string $host, int $port, ?string $proxy = null)
    {
        static::$host = $host;
        static::$port = $port;
        static::$externalProxy = static::sanitizeProxyUri($proxy);

        Loop::defer(static function()
        {
            $cert = new Socket\Certificate(__DIR__ . '/../cert/cert.pem', __DIR__ . '/../cert/key.pem');

            $tlsContext = (new BindContext())
                ->withTlsContext(
                    (new Socket\ServerTlsContext)->withDefaultCertificate($cert)
                )
            ;

            $server = Socket\Server::listen(static::getUri(), $tlsContext);
            print "MITM Server listening on: https://" . $server->getAddress() . " ..." . PHP_EOL;

            /** @var EncryptableSocket $socket */
            while ($socket = yield $server->accept()) {
                call(static function() use($socket){
                    try {
                        /** @var EncryptableSocket $remoteTunnel */
                        $remoteSocket = yield from static::openRemoteTunnel($socket, true);
                        yield [
                            $socket->setupTls(),
                            $remoteSocket->setupTls()
                        ];
                        yield from static::transferData($socket, $remoteSocket);
                    } catch (\Throwable $e) {
                        yield $socket->write("HTTP/1.1 400 Bad Request\r\n\r\n");
                        yield $socket->end($e->getMessage());
                    }
                });
            }

        });
    }

    public static function isEnabled(): bool
    {
        return static::$port !== 0;
    }

    public static function getUri(): string
    {
        return sprintf("tcp://%s:%d", static::$host, static::$port);
    }
}