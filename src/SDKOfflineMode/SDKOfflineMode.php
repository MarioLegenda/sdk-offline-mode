<?php

namespace SDKOfflineMode;

use SDKOfflineMode\Exception\SDKOfflineModeException;
use SDKBuilder\AbstractSDK;
use GuzzleHttp\Client;

class SDKOfflineMode
{
    /**
     * @var AbstractSDK\ $ebayApiObject
     */
    private $apiObject;
    /**
     * @var resource $requestHandle
     */
    private $requestHandle;
    /**
     * EbayOfflineMode constructor.
     * @param AbstractSDK\ $api
     */
    public function __construct(AbstractSDK $api)
    {
        $this->apiObject = $api;

        $this->requestHandle = fopen(__DIR__.'/requests.csv', 'a+');

        if (!file_exists(__DIR__.'/responses')) {
            mkdir(__DIR__.'/responses');
        }
    }
    /**
     * @return \FindingAPI\Core\Response\ResponseInterface
     */
    public function getResponse()
    {
        $request = $this->apiObject->getProcessedRequestString();
        if (!$this->isResponseStored($request)) {
            $requests = file(__DIR__.'/requests.csv');

            // if requests.csv is empty, fill it with first request
            if (empty($requests)) {
                // add a request to requests.csv
                fputcsv($this->requestHandle, array(1, $request), ';');
                $responseFile = __DIR__.'/responses/1.txt';
                fclose(fopen(__DIR__.'/responses/1.txt', 'a+'));

                // makes a request and adds the response to newly created response file
                $client = new Client();

                $guzzleResponse = $client->request($this->apiObject->getRequest()->getMethod(), $request);
                $stringResponse = (string) $guzzleResponse->getBody();
                file_put_contents($responseFile, $stringResponse);

                fclose($this->requestHandle);

                return $this->apiObject->getResponse($stringResponse);
            }

            $lastRequest = preg_split('#;#', array_pop($requests));

            $nextResponse = (int) ++$lastRequest[0];

            fputcsv($this->requestHandle, array($nextResponse, $request), ';');

            $responseFile = __DIR__.'/responses/'.$nextResponse.'.txt';
            fclose(fopen($responseFile, 'a+'));

            $client = new Client();

            $guzzleResponse = $client->request($this->apiObject->getRequest()->getMethod(), $request);
            $stringResponse = (string) $guzzleResponse->getBody();
            file_put_contents($responseFile, $stringResponse);

            fclose($this->requestHandle);

            return $this->apiObject->getResponse($stringResponse);
        }

        if ($this->isResponseStored($request) === true) {
            $requests = file(__DIR__.'/requests.csv');

            foreach ($requests as $line) {
                $requestLine = preg_split('#;#', $line);

                if (trim($requestLine[1]) === $request) {
                    $responseFile = __DIR__.'/responses/'.$requestLine[0].'.txt';

                    $stringResponse = file_get_contents($responseFile);

                    fclose($this->requestHandle);

                    return $this->apiObject->getResponse($stringResponse);
                }
            }
        }

        throw new SDKOfflineModeException('There is a possible bug in EbayOfflineMode. Please, fix it');
    }

    public function isResponseStored(string $request) : bool
    {
        $requests = file(__DIR__.'/requests.csv');

        foreach ($requests as $line) {
            $requestLine = preg_split('#;#', $line);

            if (trim($requestLine[1]) === $request) {
                return true;
            }
        }

        return false;
    }
}