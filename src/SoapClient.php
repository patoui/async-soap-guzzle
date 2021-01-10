<?php

namespace Meng\AsyncSoap\Guzzle;

use GuzzleHttp\Promise\Coroutine;
use Meng\AsyncSoap\SoapClientInterface;
use Meng\Soap\HttpBinding\HttpBinding;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use SoapFault;

/**
 * Class SoapClient
 * @package Meng\AsyncSoap\Guzzle
 */
class SoapClient implements SoapClientInterface
{
    /** @var PromiseInterface */
    private $httpBindingPromise;

    /** @var ClientInterface */
    private $client;

    /**
     * SoapClient constructor.
     * @param ClientInterface  $client
     * @param PromiseInterface $httpBindingPromise
     */
    public function __construct(ClientInterface $client, PromiseInterface $httpBindingPromise)
    {
        $this->httpBindingPromise = $httpBindingPromise;
        $this->client = $client;
    }

    /**
     * @param string $name
     * @param array  $arguments
     * @return PromiseInterface
     */
    public function __call($name, $arguments): PromiseInterface
    {
        return $this->callAsync($name, $arguments);
    }

    /**
     * @param string     $name
     * @param array      $arguments
     * @param array|null $options
     * @param null       $inputHeaders
     * @param array|null $outputHeaders
     * @return mixed
     */
    public function call($name, array $arguments, array $options = null, $inputHeaders = null, array &$outputHeaders = null)
    {
        $callPromise = $this->callAsync($name, $arguments, $options, $inputHeaders, $outputHeaders);
        return $callPromise->wait();
    }

    /**
     * @param string     $name
     * @param array      $arguments
     * @param array|null $options
     * @param null       $inputHeaders
     * @param array|null $outputHeaders
     * @return PromiseInterface
     */
    public function callAsync($name, array $arguments, array $options = null, $inputHeaders = null, array &$outputHeaders = null): PromiseInterface
    {
        return Coroutine::of(
            function () use ($name, $arguments, $options, $inputHeaders, &$outputHeaders) {
                /** @var HttpBinding $httpBinding */
                $httpBinding = (yield $this->httpBindingPromise);
                $request = $httpBinding->request($name, $arguments, $options, $inputHeaders);
                $requestOptions = $options['request_options'] ?? [];

                try {
                    $response = (yield $this->client->sendAsync($request, $requestOptions));
                    yield $this->interpretResponse($httpBinding, $response, $name, $outputHeaders);
                } catch (RequestException $exception) {
                    if ($exception->hasResponse()) {
                        $response = $exception->getResponse();
                        yield $this->interpretResponse($httpBinding, $response, $name, $outputHeaders);
                    } else {
                        throw $exception;
                    }
                } finally {
                    $request->getBody()->close();
                }
            }
        );
    }

    /**
     * @param HttpBinding       $httpBinding
     * @param ResponseInterface $response
     * @param                   $name
     * @param                   $outputHeaders
     * @return mixed
     * @throws SoapFault
     */
    private function interpretResponse(HttpBinding $httpBinding, ResponseInterface $response, $name, &$outputHeaders)
    {
        try {
            return $httpBinding->response($response, $name, $outputHeaders);
        } finally {
            $response->getBody()->close();
        }
    }
}
