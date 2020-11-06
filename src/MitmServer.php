<?php

namespace HttpProxy;

use Amp\ByteStream\ResourceOutputStream;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Loop;
use Amp\Socket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\EncryptableSocket;
use Monolog\Logger;
use function Amp\Socket\connect;

class MitmServer
{
    private static string $host = '127.0.0.1';
    private static int $port = 0;
    private static ?string $externalProxy = null;

    public static function start(string $host, int $port, ?string $proxy = null)
    {
        static::$host = $host;
        static::$port = $port;
        static::$externalProxy = $proxy;

        Loop::defer(static function()
        {
            $cert = new Socket\Certificate(__DIR__ . '/../cert/cert.pem', __DIR__ . '/../cert/key.pem');

            $context = (new Socket\BindContext)
                ->withTlsContext((new Socket\ServerTlsContext)->withDefaultCertificate($cert));

            $servers = [
                Socket\Server::listen(static::getUri(), $context),
            ];

            $logHandler = new StreamHandler(new ResourceOutputStream(STDOUT));
            $logHandler->setFormatter(new ConsoleFormatter);
            $logger = new Logger('server');
            $logger->pushHandler($logHandler);

            $server = new HttpServer(
                $servers, new CallableRequestHandler(
                    static function(Request $request) {
                        /** @var EncryptableSocket $socket */
                        $socket = yield from static::openRemoteSocket($request->getUri()->getHost());
                        yield $socket->write(yield from static::getHeadersString($request));
                        return new Response(...(yield from static::getResponseComponents($socket)));
                    }
                ), $logger
            );

            yield $server->start();

        });
    }

    public static function isEnabled(): bool
    {
        return static::$port !== 0;
    }

    public static function getUri(): string
    {
        return static::$host . ":" . static::$port;
    }

    /**
     * @param string $host
     *
     * @return EncryptableSocket|\Generator
     * @throws Socket\ConnectException
     * @throws Socket\SocketException
     * @throws \Amp\CancelledException
     */
    private static function openRemoteSocket(string $host)
    {
        $connectContext = (new ConnectContext)
            ->withTlsContext(new ClientTlsContext($host));

        if (static::$externalProxy) {
            $proxy = preg_replace('~http(s)?(://)?~', '', static::$externalProxy);
            $socket = yield connect($proxy, $connectContext);
            $socket->write(ProxyServer::$proxyConnectRequest);
            yield $socket->read();
        } else {
            // Currently there is a bug in Amp Http Server.
            // $request->getUri()->getPort() return http server port, not port from request.
            // But since we use MITM Server only for https, we can set 443.
            /** @var EncryptableSocket $socket */
            $socket = yield connect($host . ':' . 443, $connectContext);
        }

        yield $socket->setupTls();

        return $socket;
    }

    /**
     * @param Request $request
     *
     * @return \Generator|string
     */
    private static function getHeadersString(Request $request)
    {
        $headers = "{$request->getMethod()} {$request->getUri()->getPath()} HTTP/1.1\r\nHost: {$request->getUri()->getHost()}\r\nConnection: close\r\n";
        foreach ($request->getRawHeaders() as [$header,$value]) {
            $header = ucfirst(ucwords($header,'-'));
            $headers .= "$header: $value\r\n";
        }
        $headers .= "\r\n";
        $headers .= yield $request->getBody()->buffer();


        return $headers;
    }

    /**
     * @param EncryptableSocket $socket
     *
     * @return \Generator|array
     */
    private static function getResponseComponents(EncryptableSocket $socket)
    {
        //TODO: Send body to response as stream, to reduce memory and latency.
        $response = '';
        while (null !== $chunk = yield $socket->read()) {
            $response .= $chunk;
        }
        preg_match('~^HTTP/.+? (.+?) .*\r\n([\s\S]+)\r\n\r\n([\s\S]*)$~', $response, $matches);
        $status = (int) $matches[1];
        $headers = [];
        foreach (explode("\r\n", $matches[2]) as $header) {
            [$header,$value] = explode(': ', $header, 2);
            $headers[strtolower($header)] = $value;
        }

        $body = $matches[3];

        return [$status, $headers, $body];
    }
}