<?php

namespace HttpProxy;

use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\EncryptableSocket;
use function Amp\call;
use function Amp\Socket\connect;

abstract class AbstractSocketServer
{
    protected static ?string $externalProxy = null;

    abstract public static function start(string $host, int $port, ?string $proxy = null);

    /**
     * Moves data between sockets.
     *
     * @param EncryptableSocket $socket
     * @param $remoteTunnel
     *
     * @return \Generator
     */
    protected static function transferData(EncryptableSocket $socket, EncryptableSocket $remoteTunnel): \Generator
    {
        $promises[] = call(static function() use($socket, $remoteTunnel) {
            while (null !== $data = yield $socket->read()) {
                $remoteTunnel->write($data);
            }
        });

        $promises[] = call(static function() use($socket, $remoteTunnel) {
            while (null !== $data = yield $remoteTunnel->read()) {
                $socket->write($data);
            }
        });

        yield $promises;
    }

    /**
     * @param EncryptableSocket $socket
     *
     * @param bool $isMITMRequest
     *
     * @return \Generator<EncryptableSocket>
     * @throws \Amp\ByteStream\ClosedException
     * @throws \Amp\ByteStream\StreamException
     * @throws \Amp\CancelledException
     * @throws \Amp\Socket\ConnectException
     */
    protected static function openRemoteTunnel(EncryptableSocket $socket, bool $isMITMRequest = false): \Generator
    {
        $request = yield $socket->read();

        if (preg_match('/^CONNECT ([^\s]+)/u', $request, $matches)) {
            [$host, $port] = explode(':',$matches[1]);

            $connectContext = (new ConnectContext)
                ->withTlsContext(new ClientTlsContext($host));

            if (!$isMITMRequest && $port === '443' && MitmServer::isEnabled()) {
                $remoteSocket = yield connect(MitmServer::getUri(), $connectContext);
                $remoteSocket->write($request);
                yield $socket->write(yield $remoteSocket->read());
            } else {
                if (static::$externalProxy) {
                    /** @var EncryptableSocket $remoteSocket */
                    $remoteSocket = yield connect(static::$externalProxy, $connectContext);
                    $remoteSocket->write($request);
                    yield $socket->write(yield $remoteSocket->read());
                } else {
                    $remoteSocket = yield connect("tcp://$host:$port", $connectContext);
                    $socket->write("HTTP/1.1 200 OK\r\n\r\n");
                }
            }
        } elseif (preg_match('~Host: ([\S]+)~u', $request, $matches)) {
            $host = $matches[1];
            $port = 80;

            preg_match_all('/Proxy-.+\r\n/', $request, $matches);
            $proxyHeaders = implode('',$matches[0]);
            $request = preg_replace('/Proxy-.+\r\n/', '', $request);
            $proxyConnectRequest = "CONNECT $host:$port HTTP/1.1\r\n{$proxyHeaders}\r\n";
            if (static::$externalProxy) {
                /** @var EncryptableSocket $remoteSocket */
                $remoteSocket = yield connect(static::$externalProxy);
                $remoteSocket->write($proxyConnectRequest);
                yield $remoteSocket->read();
            } else {
                $remoteSocket = yield connect("tcp://$host:$port");
            }

            $remoteSocket->write($request);
        } else {
            throw new \UnexpectedValueException("Unknown request format: $request");
        }

        return $remoteSocket;
    }

    protected static function sanitizeProxyUri(?string $uri): string
    {
        if (null === $uri) {
            return $uri;
        }

        if (strpos($uri, 'http') === false) {
            throw new \UnexpectedValueException('Only http upstream proxy supported');
        }

        if (strpos($uri, 'https://') === 0) {
            throw new \UnexpectedValueException('https upstream proxy not supported');
        }

        $proxy = preg_replace('~^http://~', '', $uri);
        return "tcp://$proxy";
    }
}