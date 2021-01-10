<?php

namespace Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Meng\AsyncSoap\Guzzle\SoapClient;
use PHPUnit\Framework\TestCase;
use Meng\AsyncSoap\Guzzle\Factory;

class FactoryTest extends TestCase
{
    /** @test */
    public function nonWsdlMode(): void
    {
        $factory = new Factory();
        $client = $factory->create(new Client(), null, ['uri'=>'', 'location'=>'']);

        self::assertInstanceOf(SoapClient::class, $client);
    }

    /** @test */
    public function wsdlFromHttpUrl(): void
    {
        $handlerMock = new MockHandler([
            new Response('200', [], fopen(__DIR__ . DIRECTORY_SEPARATOR . 'example.wsdl', 'rb'))
        ]);
        $handler = new HandlerStack($handlerMock);
        $clientMock = new Client(['handler' => $handler]);

        $factory = new Factory();
        $client = $factory->create($clientMock, 'http://www.mysite.com/wsdl');

        self::assertInstanceOf(SoapClient::class, $client);
    }

    /** @test */
    public function wsdlFromLocalFile(): void
    {
        $factory = new Factory();
        $client = $factory->create(new Client(), __DIR__ . DIRECTORY_SEPARATOR . 'example.wsdl');

        self::assertInstanceOf(SoapClient::class, $client);
    }

    /** @test */
    public function wsdlFromDataUri(): void
    {
        $wsdlString = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'example.wsdl');
        $wsdl = 'data://text/plain;base64,' . base64_encode($wsdlString);

        $factory = new Factory();
        $client = $factory->create(new Client(), $wsdl);

        self::assertInstanceOf(SoapClient::class, $client);
    }
}
