<?php

namespace Meng\AsyncSoap\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function nonWsdlMode()
    {
        $factory = new Factory();
        $client = $factory->create(new Client(), null, ['uri'=>'', 'location'=>'']);

        self::assertInstanceOf(SoapClient::class, $client);
    }

    /**
     * @test
     */
    public function wsdlFromHttpUrl()
    {
        $handlerMock = new MockHandler([
            new Response('200', [], fopen(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'example.wsdl', 'r'))
        ]);
        $handler = new HandlerStack($handlerMock);
        $clientMock = new Client(['handler' => $handler]);

        $factory = new Factory();
        $client = $factory->create($clientMock, 'http://www.mysite.com/wsdl');

        self::assertInstanceOf(SoapClient::class, $client);
    }

    /**
     * @test
     */
    public function wsdlFromLocalFile()
    {
        $factory = new Factory();
        $client = $factory->create(new Client(), dirname(__FILE__) . DIRECTORY_SEPARATOR . 'example.wsdl');

        self::assertInstanceOf(SoapClient::class, $client);
    }

    /**
     * @test
     */
    public function wsdlFromDataUri()
    {
        $wsdlString = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'example.wsdl');
        $wsdl = 'data://text/plain;base64,' . base64_encode($wsdlString);

        $factory = new Factory();
        $client = $factory->create(new Client(), $wsdl);

        self::assertInstanceOf(SoapClient::class, $client);
    }
}
