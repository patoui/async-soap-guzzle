<?php

namespace Tests\Unit;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Meng\Soap\HttpBinding\HttpBinding;
use Meng\Soap\HttpBinding\RequestException;
use Meng\AsyncSoap\Guzzle\SoapClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SoapFault;

class SoapClientTest extends TestCase
{
    /** @var  MockHandler */
    private $handlerMock;

    /** @var  ClientInterface */
    private $client;

    /** @var HttpBinding|MockObject */
    private $httpBindingMock;

    /** @var  PromiseInterface */
    private $httpBindingPromise;

    protected function setUp(): void
    {
        $this->handlerMock = new MockHandler();
        $handler = new HandlerStack($this->handlerMock);
        $this->client = new Client(['handler' => $handler]);

        $this->httpBindingMock = $this->getMockBuilder(HttpBinding::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /** @test */
    public function magicCallDeferredHttpBindingRejected(): void
    {
        $this->expectException(Exception::class);
        $this->httpBindingPromise = new RejectedPromise(new Exception());
        $this->httpBindingMock->expects(self::never())->method('request');

        $client = new SoapClient($this->client, $this->httpBindingPromise);
        $client->someSoapMethod(['some-key' => 'some-value'])->wait();
    }

    /** @test */
    public function magicCallHttpBindingFailed(): void
    {
        $this->expectException(RequestException::class);
        $this->httpBindingPromise = new FulfilledPromise($this->httpBindingMock);

        $this->httpBindingMock->method('request')
            ->will(self::throwException(new RequestException()))
            ->with('someSoapMethod', [['some-key' => 'some-value']]);

        $this->httpBindingMock->expects(self::never())->method('response');

        $client = new SoapClient($this->client, $this->httpBindingPromise);
        $client->someSoapMethod(['some-key' => 'some-value'])->wait();
    }

    /** @test */
    public function magicCall500Response(): void
    {
        $this->httpBindingPromise = new FulfilledPromise($this->httpBindingMock);

        $this->httpBindingMock->method('request')
            ->willReturn(new Request('POST', 'www.endpoint.com'))
            ->with('someSoapMethod', [['some-key' => 'some-value']]);

        $response = new Response('500');
        $this->httpBindingMock->method('response')
            ->willReturn('SoapResult')
            ->with($response, 'someSoapMethod', null);

        $this->handlerMock->append(GuzzleRequestException::create(new Request('POST', 'www.endpoint.com'), $response));

        $client = new SoapClient($this->client, $this->httpBindingPromise);
        self::assertEquals('SoapResult', $client->someSoapMethod(['some-key' => 'some-value'])->wait());
    }

    /** @test */
    public function magicCallResponseNotReceived(): void
    {
        $this->expectException(GuzzleRequestException::class);
        $this->httpBindingPromise = new FulfilledPromise($this->httpBindingMock);

        $this->httpBindingMock->method('request')
            ->willReturn(new Request('POST', 'www.endpoint.com'))
            ->with('someSoapMethod', [['some-key' => 'some-value']]);

        $this->httpBindingMock->expects(self::never())->method('response');

        $this->handlerMock->append(GuzzleRequestException::create(new Request('POST', 'www.endpoint.com')));

        $client = new SoapClient($this->client, $this->httpBindingPromise);
        $client->someSoapMethod(['some-key' => 'some-value'])->wait();
    }

    /** @test */
    public function magicCallUndefinedResponse(): void
    {
        $this->expectException(Exception::class);
        $this->httpBindingPromise = new FulfilledPromise($this->httpBindingMock);

        $this->httpBindingMock->method('request')
            ->willReturn(new Request('POST', 'www.endpoint.com'))
            ->with('someSoapMethod', [['some-key' => 'some-value']]);

        $this->httpBindingMock->expects(self::never())->method('response');

        $this->handlerMock->append(new Exception());

        $client = new SoapClient($this->client, $this->httpBindingPromise);
        $client->someSoapMethod(['some-key' => 'some-value'])->wait();
    }

    /** @test */
    public function magicCallClientReturnSoapFault(): void
    {
        $this->expectException(SoapFault::class);
        $this->httpBindingPromise = new FulfilledPromise($this->httpBindingMock);

        $this->httpBindingMock->method('request')
            ->willReturn(new Request('POST', 'www.endpoint.com'))
            ->with('someSoapMethod', [['some-key' => 'some-value']]);

        $response = new Response('200', [], 'body');
        $this->httpBindingMock->method('response')
            ->will(self::throwException(new SoapFault('soap fault', 'soap fault')))
            ->with($response, 'someSoapMethod', null);

        $this->handlerMock->append($response);

        $client = new SoapClient($this->client, $this->httpBindingPromise);
        $client->someSoapMethod(['some-key' => 'some-value'])->wait();
    }

    /** @test */
    public function magicCallSuccess(): void
    {
        $this->httpBindingPromise = new FulfilledPromise($this->httpBindingMock);

        $this->httpBindingMock->method('request')
            ->willReturn(new Request('POST', 'www.endpoint.com'))
            ->with('someSoapMethod', [['some-key' => 'some-value']]);

        $response = new Response('200', [], 'body');
        $this->httpBindingMock->method('response')
            ->willReturn('SoapResult')
            ->with($response, 'someSoapMethod', null);

        $this->handlerMock->append($response);

        $client = new SoapClient($this->client, $this->httpBindingPromise);
        self::assertEquals('SoapResult', $client->someSoapMethod(['some-key' => 'some-value'])->wait());
    }

    /** @test */
    public function resultsAreEquivalent(): void
    {
        $this->httpBindingPromise = new FulfilledPromise($this->httpBindingMock);

        $this->httpBindingMock->method('request')
            ->willReturn(new Request('POST', 'www.endpoint.com'))
            ->with('someSoapMethod', [['some-key' => 'some-value']]);

        $response = new Response('200', [], 'body');
        $this->httpBindingMock->method('response')->willReturn('SoapResult');

        $this->handlerMock->append($response);
        $this->handlerMock->append($response);
        $this->handlerMock->append($response);

        $client = new SoapClient($this->client, $this->httpBindingPromise);
        $magicResult = $client->someSoapMethod(['some-key' => 'some-value'])->wait();
        $syncResult = $client->call('someSoapMethod', [['some-key' => 'some-value']]);
        $asyncResult = $client->callAsync('someSoapMethod', [['some-key' => 'some-value']])->wait();
        self::assertEquals($magicResult, $asyncResult);
        self::assertEquals($syncResult, $asyncResult);
    }
}
