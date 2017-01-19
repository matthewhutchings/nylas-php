<?php

namespace Nylas;

use Nylas\Models;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Send;

class Nylas
{
    protected $apiServer = 'https://api.nylas.com';
    protected $apiClient;
    protected $apiToken;
    protected $appSecret;
    public $apiRoot = '';

    public function __construct($appID, $appSecret, $token = NULL, $apiServer = NULL)
    {
        $this->appID     = $appID;
        $this->appSecret = $appSecret;
        $this->apiToken  = $token;
        $this->apiClient = $this->_createApiClient();

        if($apiServer) {
            $this->apiServer = $apiServer;
        }
    }

    protected function createHeaders()
    {
        $token = 'Basic '.base64_encode($this->apiToken.':');
        $headers = [
            'headers' => [
                'Authorization' => $token,
                'X-Nylas-API-Wrapper' => 'php'
            ]
        ];

        return $headers;
    }

    protected function createAdminHeaders()
    {
        $token = 'Basic '.base64_encode($this->appSecret.':');
        $headers = [
            'headers' => [
                'Authorization' => $token,
                'X-Nylas-API-Wrapper' => 'php'
            ]
        ];

        return $headers;
    }

    private function _createApiClient()
    {
        $handlerStack = HandlerStack::create(new CurlHandler());
        $handlerStack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));
        $client = new Client([
            'base_uri' => $this->apiServer,
            'timeout' => 150,
            'handler' => $handlerStack
        ]);

        return $client;
    }

    private function retryDecider()
    {
        return function($retries, Request $request, Response $response = null, RequestException $exception = null) {
            // Limit the number of retries to 10
            if ($retries >= 10) {
                return false;
            }

            // Retry connection exceptions
            if($exception instanceof ConnectException) {
                return true;
            }

            if($response) {
                // Retry on server errors
                if($response->getStatusCode() >= 500) {
                    return true;
                }
            }

            return false;
        };
    }

    private function retryDelay()
    {
        return function($numberOfRetries) {
            return 2000 * $numberOfRetries;
        };
    }

    public function createAuthURL($redirect_uri, $login_hint = NULL) {
        $args = [
            "client_id" => $this->appID,
            "redirect_uri" => $redirect_uri,
            "response_type" => "code",
            "scope" => "email",
            "login_hint" => $login_hint,
            "state" => $this->_generateId()
        ];

        return $this->apiServer.'/oauth/authorize?'.http_build_query($args);
    }

    public function getAuthToken($code)
    {
        $args = [
            "client_id" => $this->appID,
            "client_secret" => $this->appSecret,
            "grant_type" => "authorization_code",
            "code" => $code
        ];

        $url = $this->apiServer.'/oauth/token';
        $payload = [];
        $payload['headers']['Accept'] = 'text/plain';
        $payload['form_params'] = $args;

        $response = json_decode($this->apiClient->post($url, $payload)->getBody()->getContents(), true);

        if(array_key_exists('access_token', $response)) {
            $this->apiToken = $response['access_token'];
        }

        return $this->apiToken;
    }

    public function account()
    {
        $apiObj = new NylasAPIObject();
        $nsObj = new Models\Account();
        $accountData = $this->getResource('', $nsObj, '', []);
        $account = $apiObj->_createObject($accountData->klass, NULL, $accountData->data);

        return $account;
    }

    public function deactivateAccount($accountId)
    {
        $url = "{$this->apiServer}/a/{$this->appID}/accounts/{$accountId}/downgrade";

        $response = json_decode($this->apiClient->post($url, $this->createAdminHeaders())->getBody()->getContents(), true);

        return $response;
    }

    public function reactivateAccount($accountId)
    {

        $url = "{$this->apiServer}/a/{$this->appID}/accounts/{$accountId}/upgrade";

        $response = json_decode($this->apiClient->post($url, $this->createAdminHeaders())->getBody()->getContents(), true);

        return $response;
    }

    public function threads()
    {
        $msgObj = new Models\Thread($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function messages()
    {
        $msgObj = new Models\Message($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function drafts()
    {
        $msgObj = new Models\Draft($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function labels()
    {
        $msgObj = new Models\Label($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function files()
    {
        $msgObj = new Models\File($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function contacts()
    {
        $msgObj = new Models\Contact($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function calendars()
    {
        $msgObj = new Models\Calendar($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function events()
    {
        $msgObj = new Models\Event($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function folders()
    {
        $msgObj = new Models\Folder($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function sendMessage($data)
    {
        $sendObject = new Models\Send($this, null);
        $sendResult = $sendObject->send($data);

        return $sendResult;
    }

    public function deltas($cursor = NULL, $filters = [])
    {
        if(!empty($cursor)) {
            $filters['cursor'] = $cursor;
            $method = 'get';
        } else {
            $filters = ['extra' => 'latest_cursor'];
            $method = 'post';
        }

        $apiObj = new NylasAPIObject();
        $nsObj = new Models\Delta();
        $deltasData = $this->getResource('', $nsObj, '', $filters, $method);
        $deltas = $apiObj->_createObject($deltasData->klass, NULL, $deltasData->data);

        return $deltas;
    }

    public function getResources($namespace, $klass, $filter)
    {
        $suffix = ($namespace) ? '/'.$klass->apiRoot.'/'.$namespace : '';
        $url = $this->apiServer.$suffix.'/'.$klass->collectionName;
        $url = $url.'?'.http_build_query($filter);
        $data = json_decode($this->apiClient->get($url, $this->createHeaders())->getBody()->getContents(), true);

        $mapped = [];

        foreach ($data as $i) {
            $mapped[] = clone $klass->_createObject($this, $namespace, $i);
        }

        return $mapped;
    }

    public function getResource($namespace, $klass, $id, $filters, $method = 'get')
    {
        $response = $this->getResourceRaw($namespace, $klass, $id, $filters, $method);

        return $klass->_createObject($this, $namespace, $response);
    }

    public function getResourceRaw($namespace, $klass, $id, $filters, $method)
    {
        $extra = '';
        if(array_key_exists('extra', $filters)) {
            $extra = $filters['extra'];
            unset($filters['extra']);
        }

        if(!empty($id)) {
            $id = '/'.$id;
        }

        $prefix = ($namespace) ? '/'.$klass->apiRoot.'/'.$namespace : '';
        $postfix = ($extra) ? '/'.$extra : '';
        $url = $this->apiServer.$prefix.'/'.$klass->collectionName.$id.$postfix;
        $url = $url.'?'.http_build_query($filters);

        $data = json_decode($this->apiClient->{$method}($url, $this->createHeaders())->getBody()->getContents(), true);

        return $data;
    }

    public function getResourceData($namespace, $klass, $id, $filters)
    {
        $extra = '';
        $customHeaders = [];

        if(array_key_exists('extra', $filters)) {
            $extra = $filters['extra'];
            unset($filters['extra']);
        }

        if(array_key_exists('headers', $filters)) {
            $customHeaders = $filters['headers'];
            unset($filters['headers']);
        }

        if(!empty($id)) {
            $id = '/'.$id;
        }

        $prefix = ($namespace) ? '/'.$klass->apiRoot.'/'.$namespace : '';
        $postfix = ($extra) ? '/'.$extra : '';
        $url = $this->apiServer.$prefix.'/'.$klass->collectionName.$id.$postfix;
        $url = $url.'?'.http_build_query($filters);
        $customHeaders = array_merge($this->createHeaders()['headers'], $customHeaders);
        $headers = array('headers' => $customHeaders);
        $data = $this->apiClient->get($url, $headers)->getBody();

        return $data;
    }

    public function createResource($namespace, $klass, $data)
    {
        $prefix = ($namespace) ? '/'.$klass->apiRoot.'/'.$namespace : '';
        $url = $this->apiServer.$prefix.'/'.$klass->collectionName;
        $payload = $this->createHeaders();

        if($klass->collectionName == 'files') {
            $payload['multipart'] = $data;
        } else {
            $payload['json'] = $data;
        }

        $response = json_decode($this->apiClient->post($url, $payload)->getBody()->getContents(), true);

        return $klass->_createObject($this, $namespace, $response);
    }

    public function updateResource($namespace, $klass, $id, $data)
    {
        $prefix = ($namespace) ? '/'.$klass->apiRoot.'/'.$namespace : '';
        $url = $this->apiServer.$prefix.'/'.$klass->collectionName.'/'.$id;

        if($klass->collectionName == 'files') {
            $payload['multipart'] = [$data];
        } else {
            $payload = $this->createHeaders();
            $payload['json'] = $data;
            $response = json_decode($this->apiClient->put($url, $payload)->getBody()->getContents(), true);
            return $klass->_createObject($this, $namespace, $response);
        }
    }

    public function deleteResource($namespace, $klass, $id, $data)
    {
        $prefix = ($namespace) ? '/'.$klass->apiRoot.'/'.$namespace : '';
        $url = $this->apiServer.$prefix.'/'.$klass->collectionName.'/'.$id;
        $payload = $this->createHeaders();
        $payload['json'] = $data;
        $response = json_decode($this->apiClient->delete($url, $payload)->getBody()->getContents(), true);

        return $response;
    }

    private function _generateId() {
        // Generates unique UUID
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
