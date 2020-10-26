<?php

namespace Amproxy;

use Amp;
use Amp\Socket;
use function Amp\call;

final class ProxyServer
{

    private static ?string $externalProxy = null;

    public static function start(string $host, int $port, ?string $proxy = null): void
    {
        static::$externalProxy = $proxy;
        Amp\Loop::run(
            function() use ($host, $port) {
                $server = Amp\Socket\Server::listen("tcp://{$host}:{$port}");
                print "Listening on http://" . $server->getAddress() . " ..." . PHP_EOL;

                while ($socket = yield $server->accept()) {
                    call(fn()=>yield from self::handleClient($socket));
                }

                self::registerShutdown($server);
            }
        );
    }

    /**
     * Stop the server gracefully when SIGINT is received.
     * This is technically optional, but it is best to call Server::stop().
     *
     * @param Socket\Server $server
     *
     * @throws Amp\Loop\UnsupportedFeatureException
     */
    private static function registerShutdown(Socket\Server $server)
    {
        if (defined('SIGINT')) {
            Amp\Loop::onSignal(SIGINT, static function (string $watcherId) use ($server) {
                Amp\Loop::cancel($watcherId);
                $server->close();
                exit;
            });
        }
    }

    private static function handleClient(Socket\Socket $socket): \Generator
    {
        try {
            /** @var Socket\Socket $remoteTunnel */
            $remoteTunnel = yield from self::openRemoteTunnel($socket);

            $promises[] = call(function() use($socket, $remoteTunnel) {
                while (null !== $data = yield $socket->read()) {
                    $remoteTunnel->write($data);
                }
            });

            $promises[] = call(function() use($socket, $remoteTunnel) {
                while (null !== $data = yield $remoteTunnel->read()) {
                    $socket->write($data);
                }
            });

            yield $promises;
        } catch (\Throwable $e) {
            yield $socket->write("HTTP/1.1 400 Bad Request\r\n\r\n");
            yield $socket->end($e->getMessage());
        }

    }

    /**
     * @param Socket\Socket $socket
     *
     * @return \Generator<Socket\Socket>
     * @throws Amp\CancelledException
     * @throws Socket\ConnectException
     */
    private static function openRemoteTunnel(Socket\Socket $socket): \Generator
    {
        $request = yield $socket->read();

        if (preg_match('/^CONNECT ([^\s]+)/u', $request, $matches)) {
            if (static::$externalProxy) {
                /** @var Socket\Socket $remoteSocket */
                $remoteSocket = yield Socket\connect(static::$externalProxy);
                $remoteSocket->write($request);
                yield $socket->write(yield $remoteSocket->read());
            } else {
                $remoteSocket = yield Socket\connect($matches[1]);
                yield $socket->write("HTTP/1.1 200 OK\r\n\r\n");
            }
        } elseif (preg_match('~Host: ([^\s]+)~u', $request, $matches)) {
            $host = $matches[1];
            $port = 80;

            preg_match_all('/Proxy-.+\r\n/', $request, $matches);
            $proxyHeaders = implode('',$matches[0]);
            $request = preg_replace('/Proxy-.+\r\n/', '', $request);

            if (static::$externalProxy) {
                /** @var Socket\Socket $remoteSocket */
                $remoteSocket = yield Socket\connect(static::$externalProxy);
                $remoteSocket->write("CONNECT $host:$port HTTP/1.1\r\n{$proxyHeaders}\r\n");
                yield $remoteSocket->read();
            } else {
                $remoteSocket = yield Socket\connect("$host:$port");
            }

            yield $remoteSocket->write($request);
        } else {
            throw new \UnexpectedValueException("Unknown request format: $request");
        }

        return $remoteSocket;
    }

}