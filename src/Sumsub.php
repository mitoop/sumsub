<?php

namespace Mitoop\Sumsub;

use GuzzleHttp;
use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class Sumsub
{
    protected const BASE_URL = 'https://api.sumsub.com';

    protected Client $guzzleClient;

    protected string $appToken;

    protected string $secretKey;

    public static function create($appToken, $secretKey): Sumsub
    {
        return new self($appToken, $secretKey);
    }

    private function __construct(string $appToken, string $secretKey)
    {
        $this->appToken = $appToken;
        $this->secretKey = $secretKey;
        $this->guzzleClient = new Client(['base_uri' => self::BASE_URL, 'verify' => false]);
    }

    /**
     * https://developers.sumsub.com/api-reference/#creating-an-applicant
     *
     * @return string Applicant ID
     *
     * @throws RuntimeException
     */
    public function createApplicant(string $externalUserId, string $levelName): string
    {
        $requestBody = [
            'externalUserId' => $externalUserId,
        ];

        $url = '/resources/applicants?'.http_build_query(['levelName' => $levelName]);

        /** @var RequestInterface $request */
        $request = (new GuzzleHttp\Psr7\Request('POST', $url))
            ->withHeader('Content-Type', 'application/json')
            ->withBody(GuzzleHttp\Psr7\Utils::streamFor(json_encode($requestBody)));

        $response = $this->sendRequest($request);
        $body = $this->parseBody($response);

        return $body['id'];
    }

    /**
     * https://developers.sumsub.com/api-reference/#adding-an-id-document
     *
     * @return string Image ID
     *
     * @throws RuntimeException
     */
    public function addDocument(
        string $applicantId,
        string $filePath,
        array $metadata
    ): string {
        $multipart = new GuzzleHttp\Psr7\MultipartStream([
            [
                'name' => 'metadata',
                'contents' => json_encode($metadata),
            ],
            [
                'name' => 'content',
                'contents' => fopen($filePath, 'r'),
            ],
        ]);

        $url = '/resources/applicants/'.urlencode($applicantId).'/info/idDoc';
        /** @var RequestInterface $request */
        $request = (new GuzzleHttp\Psr7\Request('POST', $url))
            ->withBody($multipart);

        $response = $this->sendRequest($request);

        return $response->getHeader('X-Image-Id')[0] ?? '';
    }

    /**
     * https://developers.sumsub.com/api-reference/#getting-applicant-status-api
     *
     * @throws RuntimeException
     */
    public function getApplicantStatus(string $applicantId): array
    {
        $url = '/resources/applicants/'.urlencode($applicantId).'/requiredIdDocsStatus';
        $request = new GuzzleHttp\Psr7\Request('GET', $url);

        $response = $this->sendRequest($request);

        return $this->parseBody($response);
    }

    /**
     * https://developers.sumsub.com/api-reference/#access-tokens-for-sdks
     *
     * @throws RuntimeException
     */
    public function getAccessToken(string $externalUserId, string $levelName): array
    {
        $url = '/resources/accessTokens?'.http_build_query(['userId' => $externalUserId, 'levelName' => $levelName]);
        $request = new GuzzleHttp\Psr7\Request('POST', $url);

        $response = $this->sendRequest($request);

        return $this->parseBody($response);
    }

    public function reset(string $applicantId): array
    {
        $url = '/resources/applicants/'.urlencode($applicantId).'/reset';
        $request = new GuzzleHttp\Psr7\Request('POST', $url);

        $response = $this->sendRequest($request);

        return $this->parseBody($response);
    }

    public function getApplicantData(string $applicantId): array
    {
        $url = '/resources/applicants/'.urlencode($applicantId).'/one';
        $request = new GuzzleHttp\Psr7\Request('GET', $url);

        $response = $this->sendRequest($request);

        return $this->parseBody($response);
    }

    public function getApplicantDataByExternalUserId(string $externalUserId): array
    {
        $url = '/resources/applicants/-;externalUserId='.urlencode($externalUserId).'/one';
        $request = new GuzzleHttp\Psr7\Request('GET', $url);

        $response = $this->sendRequest($request);

        return $this->parseBody($response);
    }

    /**
     * @throws RuntimeException
     */
    protected function sendRequest(RequestInterface $request): ResponseInterface
    {
        $now = time();
        $request = $request->withHeader('X-App-Token', $this->appToken)
            ->withHeader('X-App-Access-Sig', $this->createSignature($request, $now))
            ->withHeader('X-App-Access-Ts', $now);

        try {
            $response = $this->guzzleClient->send($request);
            if ($response->getStatusCode() != 200 && $response->getStatusCode() != 201) {
                // https://developers.sumsub.com/api-reference/#errors
                // If an unsuccessful answer is received, please log the value of the `correlationId` parameter.

                throw new RuntimeException('Invalid status code received: '.$response->getStatusCode().'. Body: '.$response->getBody());
            }

            return $response;
        } catch (GuzzleHttp\Exception\GuzzleException $e) {
            throw new RuntimeException('Error occurred during the request', 0, $e);
        }
    }

    protected function createSignature(RequestInterface $request, int $ts): string
    {
        return hash_hmac('sha256', $ts.strtoupper($request->getMethod()).$request->getUri().$request->getBody(), $this->secretKey);
    }

    protected function parseBody(ResponseInterface $response): array
    {
        $data = (string) $response->getBody();
        $json = json_decode($data, true, JSON_THROW_ON_ERROR);
        if (! is_array($json)) {
            throw new RuntimeException('Invalid response received: '.$data);
        }

        return $json;
    }
}
