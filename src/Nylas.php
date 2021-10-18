<?php

namespace Nylas;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Nylas\Models\Account;
use Nylas\Models\Calendar;
use Nylas\Models\Contact;
use Nylas\Models\Delta;
use Nylas\Models\Draft;
use Nylas\Models\Event;
use Nylas\Models\File;
use Nylas\Models\Folder;
use Nylas\Models\Label;
use Nylas\Models\Message;
use Nylas\Models\Send;
use Nylas\Models\Thread;

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

        if ($apiServer) {
            $this->apiServer = $apiServer;
        }
    }

    protected function createHeaders()
    {
        $token = 'Basic '.base64_encode($this->apiToken.':');

        return [
            'headers' => [
                'Authorization' => $token,
                'X-Nylas-API-Wrapper' => 'php'
            ]
        ];
    }

    protected function createAdminHeaders()
    {
        $token = 'Basic '.base64_encode($this->appSecret.':');

        return [
            'headers' => [
                'Authorization' => $token,
                'X-Nylas-API-Wrapper' => 'php'
            ]
        ];
    }

    private function _createApiClient()
    {
        $handlerStack = HandlerStack::create(new CurlHandler());
        $handlerStack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));

        return new Client([
            'base_uri' => $this->apiServer,
            'connect_timeout' => 60,
            'timeout' => 150,
            'handler' => $handlerStack
        ]);
    }

    private function retryDecider()
    {
        return function($retries, Request $request, Response $response = null, RequestException|ConnectException $exception = null) {
            // Limit the number of retries to 10
            if ($retries >= 10) {
                return false;
            }

            // Retry connection exceptions
            if ($exception instanceof ConnectException) {
                return true;
            }

            if ($response) {
                // Retry on server errors
                if ($response->getStatusCode() >= 500) {
                    return true;
                }
            }

            return false;
        };
    }

    private function retryDelay()
    {
        return function($numberOfRetries) {
            return 60 * $numberOfRetries;
        };
    }

    public function createAuthURL($redirect_uri, $login_hint = NULL)
    {
        $args = [
            'client_id' => $this->appID,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'email',
            'login_hint' => $login_hint,
            'state' => $this->_generateId()
        ];

        return $this->apiServer.'/oauth/authorize?'.http_build_query($args);
    }

    public function getAuthToken($code)
    {
        $args = [
            'client_id' => $this->appID,
            'client_secret' => $this->appSecret,
            'grant_type' => 'authorization_code',
            'code' => $code
        ];

        $url = $this->apiServer.'/oauth/token';
        $payload = [];
        $payload['headers']['Accept'] = 'text/plain';
        $payload['form_params'] = $args;

        $response = json_decode($this->apiClient->post($url, $payload)->getBody()->getContents(), true);

        if (array_key_exists('access_token', $response)) {
            $this->apiToken = $response['access_token'];
        }

        return $this->apiToken;
    }

    public function account()
    {
        $apiObj = new NylasAPIObject();
        $nsObj = new Account();
        $accountData = $this->getResource('', $nsObj, '', []);

        return $apiObj->_createObject($accountData->klass, NULL, $accountData->data);
    }

    public function deactivateAccount($accountId)
    {
        $url = "{$this->apiServer}/a/{$this->appID}/accounts/{$accountId}/downgrade";

        $response = $this->apiClient->post($url, $this->createAdminHeaders())->getBody()->getContents();

        return json_decode($response, true);
    }

    public function reactivateAccount($accountId)
    {
        $url = "{$this->apiServer}/a/{$this->appID}/accounts/{$accountId}/upgrade";

        $response = $this->apiClient->post($url, $this->createAdminHeaders())->getBody()->getContents();

        return json_decode($response, true);
    }

    public function threads()
    {
        $msgObj = new Thread($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function messages()
    {
        $msgObj = new Message($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function drafts()
    {
        $msgObj = new Draft($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function labels()
    {
        $msgObj = new Label($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function files()
    {
        $msgObj = new File($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function contacts()
    {
        $msgObj = new Contact($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function calendars()
    {
        $msgObj = new Calendar($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function events()
    {
        $msgObj = new Event($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function folders()
    {
        $msgObj = new Folder($this);
        return new NylasModelCollection($msgObj, $this, NULL, [], 0, []);
    }

    public function sendMessage($data)
    {
        $sendObject = new Send($this, null);
        return $sendObject->send($data);
    }

    public function deltas($cursor = NULL, $filters = [])
    {
        if (!empty($cursor)) {
            $filters['cursor'] = $cursor;
            $method = 'get';
        } else {
            $filters = ['extra' => 'latest_cursor'];
            $method = 'post';
        }

        $apiObj = new NylasAPIObject();
        $nsObj = new Delta();
        $deltasData = $this->getResource('', $nsObj, '', $filters, $method);

        return $apiObj->_createObject($deltasData->klass, NULL, $deltasData->data);
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
        if (array_key_exists('extra', $filters)) {
            $extra = $filters['extra'];
            unset($filters['extra']);
        }

        if (!empty($id)) {
            $id = '/'.$id;
        }

        $prefix = ($namespace) ? '/'.$klass->apiRoot.'/'.$namespace : '';
        $postfix = ($extra) ? '/'.$extra : '';
        $url = $this->apiServer.$prefix.'/'.$klass->collectionName.$id.$postfix;
        $url = $url.'?'.http_build_query($filters);

        $response = $this->apiClient->{$method}($url, $this->createHeaders())->getBody()->getContents();

        return json_decode($response, true);
    }

    public function getResourceData($namespace, $klass, $id, $filters)
    {
        $extra = '';
        $customHeaders = [];

        if (array_key_exists('extra', $filters)) {
            $extra = $filters['extra'];
            unset($filters['extra']);
        }

        if (array_key_exists('headers', $filters)) {
            $customHeaders = $filters['headers'];
            unset($filters['headers']);
        }

        if (!empty($id)) {
            $id = '/'.$id;
        }

        $prefix = ($namespace) ? '/'.$klass->apiRoot.'/'.$namespace : '';
        $postfix = ($extra) ? '/'.$extra : '';
        $url = $this->apiServer.$prefix.'/'.$klass->collectionName.$id.$postfix;
        $url = $url.'?'.http_build_query($filters);
        $customHeaders = array_merge($this->createHeaders()['headers'], $customHeaders);
        $headers = array('headers' => $customHeaders);

        return $this->apiClient->get($url, $headers)->getBody();
    }

    public function createResource($namespace, $klass, $data)
    {
        $prefix = ($namespace) ? '/'.$klass->apiRoot.'/'.$namespace : '';
        $url = $this->apiServer.$prefix.'/'.$klass->collectionName;
        $payload = $this->createHeaders();

        if ($klass->collectionName == 'files') {
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

        if ($klass->collectionName == 'files') {
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
        $response = $this->apiClient->delete($url, $payload)->getBody()->getContents();

        return json_decode($response, true);
    }

    private function _generateId()
    {
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
