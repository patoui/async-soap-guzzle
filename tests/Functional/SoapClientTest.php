<?php

namespace Tests\Functional;

use GuzzleHttp\Client;
use Meng\AsyncSoap\Guzzle\Factory;
use PHPUnit\Framework\TestCase;

class SoapClientTest extends TestCase
{
    /** @var  Factory */
    private $factory;

    /** @inheritDoc */
    protected function setUp(): void
    {
        $this->factory = new Factory();
    }

    /** @test */
    public function call(): void
    {
        $client   = $this->factory->create(
            new Client(),
            'https://www.crcind.com/csp/samples/SOAP.Demo.CLS?WSDL=1'
        );
        $response = $client->call('AddInteger', [['Arg1' => 1, 'Arg2' => 2]]);
        self::assertNotEmpty($response);
        self::assertSame(3, $response->AddIntegerResult);
    }

    /**
     * @test
     * @dataProvider webServicesProvider
     * @param $wsdl
     * @param $options
     * @param $function
     * @param $args
     * @param $contains
     */
    public function callAsync(
        string $wsdl,
        array $options,
        string $function,
        array $args,
        array $contains
    ): void {
        $client   = $this->factory->create(new Client(), $wsdl, $options);
        $response = $client->callAsync($function, $args)->wait();
        self::assertNotEmpty($response);
        $response = (array) $response;
        foreach ($contains as $key => $value) {
            if (is_array($value)) {
                $iresult = (array) $response[$key];
                foreach ($value as $ikey => $ival) {
                    if (is_string($ikey)) {
                        self::assertArrayHasKey($ikey, $iresult);
                    }
                    self::assertSame($ival, $iresult[$ikey]);
                }
            } elseif (is_string($key)) {
                self::assertArrayHasKey($key, $response);
                self::assertSame($value, $response[$key]);
            } else {
                self::assertArrayHasKey($value, $response);
            }
        }
    }

    /**
     * Data provider to test various Soap actions
     * @return array[]
     */
    public function webServicesProvider(): array
    {
        return [
            [
                'wsdl'     => 'https://www.crcind.com/csp/samples/SOAP.Demo.CLS?WSDL=1',
                'options'  => [],
                'function' => 'AddInteger',
                'args'     => [['Arg1' => 1, 'Arg2' => 2]],
                'contains' => [
                    'AddIntegerResult' => 3,
                ],
            ],
            [
                'wsdl'     => 'https://www.crcind.com/csp/samples/SOAP.Demo.CLS?WSDL=1',
                'options'  => [],
                'function' => 'LookupCity',
                'args'     => [['zip' => 90210]],
                'contains' => [
                    'LookupCityResult' => [
                        'City'  => 'Beverly Hills',
                        'State' => 'CA',
                        'Zip'   => '90210',
                    ],
                ],
            ],
        ];
    }
}