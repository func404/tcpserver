<?php
namespace lib;

/**
 * RestfulClient library
 *
 * 使用发起http请求
 *
 * @author duxin
 * @copyright weilaixiansen.com 2017
 */
class RestfulClient
{

    private $uri;

    private $method;

    private $headers = [];

    private $requestBody;

    private $conntentType = 'form';

    private $charset = 'utf-8';

    private $requestType = 'curl';

    private $responseFormat = 'json';

    private $responseBody = '';

    private $responseHeader = [
        'http_code' => 200,
        'http_message' => 'OK',
        'content_type' => 'text/html; charset=UTF-8',
        'cookies' => [],
        'cookie_domain' => '',
        'cookie_path' => '/'
    
    ];

    private $responseStatus = 200;

    private $hasHttpAuth = false;

    private $httpAuth = [];

    private $hasProxy = false;

    private $proxy = [
        'host' => '127.0.0.1',
        'port' => 8888,
        'user' => 'root',
        'password' => 'root'
    ];

    private $hasCookie = false;

    private $cookies = [];

    private $options = [
        'user_agent' => "PHP RestClient DUXIN/0.0.1",
        'timeout' => 10,
        'connect_timeout' => 5
    ];

    private const contentTypes = [
        'jpg' => 'image/jpeg',
        'json' => 'application/json',
        'text' => 'text/xml',
        'xml' => 'text/xml',
        'form' => 'application/x-www-form-urlencoded'
    
    ];

    private const requestMethods = [
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'HEAD',
        'PATCH'
    ];

    private static $instance;

    public $response = false;

    public function __construct($options = [])
    {
        $this->options = array_merge($this->options, $options);
        if (array_key_exists('uri', $this->options)) {
            $this->uri = $this->options['uri'];
        }
    }

    public static function getInstance($options = [])
    {
        if (! self::$instance) {
            self::$instance = new self($options);
        }
        return self::$instance;
    }

    public function setUri(string $uri)
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * 设置请求头
     *
     * @param array $headers            
     * @return \Vendors\RestfulClient
     */
    public function setHeaders(array $headers)
    {
        $this->headers = array_merge($this->headers, $headers);
        
        return $this;
    }

    /**
     * 设置代理
     *
     * @param string $host            
     * @param int $port            
     * @param string $user            
     * @param string $password            
     * @return \Vendors\RestfulClient
     */
    public function setProxy(string $host, int $port, string $user = null, string $password = null)
    {
        $this->hasProxy = true;
        $this->proxy = [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password
        ];
        return $this;
    }

    /**
     * 设置请求头 COOKIE
     *
     * @param array $cookies            
     * @return \Vendors\RestfulClient
     */
    public function setCookies(array $cookies)
    {
        $this->hasCookie = true;
        $this->cookies = $cookies;
        return $this;
    }

    /**
     * 设置请求头HTTPAUTH
     *
     * @param string $user            
     * @param string $password            
     * @return \Vendors\RestfulClient
     */
    public function setHttpAuth(string $user = '', string $password = '')
    {
        $this->hasHttpAuth = true;
        $this->httpAuth = [
            'user' => $user,
            'password' => $password
        ];
        return $this;
    }

    /**
     * 设置请求contxt-type
     *
     * @param string $type
     *            json|text|form
     * @return \Vendors\RestfulClient
     */
    public function setContentType(string $type)
    {
        if (array_key_exists(strtolower($type), self::contentTypes)) {
            $this->conntentType = self::contentTypes[strtolower($type)];
        } else {
            $this->conntentType = $type;
        }
        return $this;
    }

    /**
     * 设置请求类型
     *
     * @param string $type
     *            curl|fscockt|fstream
     * @return \Vendors\RestfulClient
     */
    public function setRequestType(string $type = 'curl')
    {
        $this->requestType = $type;
        return $this;
    }

    public function getResponse()
    {
        return [
            'header' => $this->responseHeader,
            'header' => $this->responseBody
        ];
    }

    public function getResponseBody()
    {
        return $this->responseBody;
    }

    public function getResponseHeader()
    {
        return $this->responseHeader;
    }

    /**
     * POST 请求
     *
     * @param string|array $parameters
     *            请求参数
     * @param string $uri
     *            请求地址
     */
    public function post($parameters = '', $uri = '')
    {
        if ($uri) {
            $this->uri = $uri;
        }
        $this->method = 'POST';
        
        return $this->execute($parameters);
    }

    /**
     * GET 请求
     *
     * @param string|array $parameters
     *            请求参数
     * @param string $uri
     *            请求地址
     */
    public function get($parameters = '', $uri = '')
    {
        if ($uri) {
            $this->uri = $uri;
        }
        $this->method = 'GET';
        
        return $this->execute();
    }

    /**
     * PUT 请求
     *
     * @param string|array $parameters
     *            请求参数
     * @param string $uri
     *            请求地址
     */
    public function put($parameters = '', $uri = '')
    {
        if ($uri) {
            $this->uri = $uri;
        }
        $this->method = 'PUT';
        
        return $this->execute();
    }

    /**
     * PATCH 请求
     *
     * @param string|array $parameters
     *            请求参数
     * @param string $uri
     *            请求地址
     */
    public function patch($parameters = '', $uri = '')
    {
        if ($uri) {
            $this->uri = $uri;
        }
        $this->method = 'PATCH';
        
        return $this->execute($parameters);
    }

    /**
     * DELETE 请求
     *
     * @param string|array $parameters
     *            请求参数
     * @param string $uri
     *            请求地址
     */
    public function delete($parameters = '', $uri = '')
    {
        if ($uri) {
            $this->uri = $uri;
        }
        $this->method = 'PATCH';
        $this->execute($parameters);
    }

    /**
     * HEAD 请求
     *
     * @param string|array $parameters
     *            请求参数
     * @param string $uri
     *            请求地址
     */
    public function head($parameters = '', $uri = '')
    {
        if ($uri) {
            $this->uri = $uri;
        }
        $this->method = 'HEAD';
        $this->execute($parameters);
    }

    private function execute($parameters = '')
    {
        if ($this->requestType == 'curl') {
            if (! function_exists('curl_init')) {
                $this->requestType = 'fsocket';
            }
        }
        if ($this->requestType == 'fsocket') {
            if (! function_exists('fsockopen')) {
                $this->requestType = 'fstream';
            }
        }
        
        if ($this->requestType == 'fstream') {
            if (! ini_get('allow_url_fopen')) {
                throw new \Exception('Please check configure file ' . php_ini_loaded_file() . ", set allow_url_fopen = 1 \n");
            }
        }
        
        $url = parse_url($this->uri);
        if (! isset($url['path'])) {
            $url['path'] = '/';
        }
        
        if (! isset($url['port'])) {
            if ($url['scheme'] == 'https') {
                $url['port'] = 443;
            } else {
                $url['port'] = 80;
            }
        }
        
        if (is_array($parameters)) {
            $this->requestBody = http_build_query($parameters);
        } else {
            $this->requestBody = $parameters;
        }
        if (php_sapi_name() != 'cli') {
            $referer = $_SERVER['REQUEST_URI'];
        } else {
            $referer = 'Duxin';
        }
        
        $httpHeaders = [
            'Content-type: ' . self::contentTypes[$this->conntentType] . ';charset="' . $this->charset . '"',
            'Accept: ' . self::contentTypes[$this->conntentType],
            'Cache-Control: no-cache',
            'Content-length:  ' . strlen($this->requestBody),
            'Referer :' . $referer,
            'User-Agent: ' . $this->options['user_agent']
        ];
        switch ($this->requestType) {
            case 'curl':
                $options = [
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HEADER => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_USERAGENT => $this->options['user_agent']
                ];
                if ($this->hasCookie && $this->cookies) {
                    foreach ($this->cookies as $key => $value) {
                        $options[CURLOPT_COOKIE] .= $key . '=' . $value . ';';
                    }
                    $options[CURLOPT_COOKIE] = rtrim($options[CURLOPT_COOKIE], ';');
                }
                
                if ($this->hasHttpAuth) {
                    $options[CURLOPT_USERPWD] = implode(':', $this->httpAuth);
                }
                if ($this->hasProxy) {
                    $options[CURLOPT_PROXY] = $this->proxy['host'];
                    $options[CURLOPT_PROXYPORT] = $this->proxy['port'];
                    if ($this->proxy['user'] && $this->proxy['password']) {
                        $options[CURLOPT_PROXYUSERPWD] = $this->proxy['user'] . ':' . $this->proxy['password'];
                    }
                }
                $response = $this->curl($this->method, $this->uri, $this->requestBody, $httpHeaders, $options);
                break;
            case 'fsocket':
                $host = $url['host'];
                $port = $url['port'];
                
                if ($this->hasCookie && $this->cookies) {
                    $cookies = 'Cookie: ';
                    foreach ($this->cookies as $key => $value) {
                        $cookies = $key . '=' . $value . ';';
                    }
                    $httpHeaders[] = $cookies;
                }
                if ($this->hasHttpAuth) {
                    $httpAuth = 'Authorization:Basic ' . base64_encode(implode(':', $this->httpAuth));
                    $httpHeaders[] = $httpAuth;
                }
                
                if ($this->hasProxy) {
                    $host = $this->proxy['host'];
                    $port = $this->proxy['port'];
                    
                    $httpHeaders = array_merge([
                        $this->method . ' ' . $url['path'] . ' HTTP/1.1',
                        'Host: ' . $this->proxy['host']
                    ], $httpHeaders, 'Proxy-Authorization: Basic ' . base64_encode($this->proxy['user'] . ':' . $this->proxy['password']));
                } else {
                    $httpHeaders = array_merge([
                        $this->method . ' ' . $url['path'] . ' HTTP/1.1',
                        'Host: ' . $url['host']
                    ], $httpHeaders);
                }
                
                if ($url['scheme'] == 'https') {
                    $host = 'ssl://' . $host;
                }
                $response = $this->fsocket($host, $port, $this->requestBody, $httpHeaders);
                break;
            
            case 'fstream':
                $httpHeaders = array_merge([
                    $this->method . ' ' . $url['path'] . 'HTTP/1.1'
                ], $httpHeaders);
                if ($this->hasCookie && $this->cookies) {
                    $cookies = 'Cookie: ';
                    foreach ($this->cookies as $key => $value) {
                        $cookies = $key . '=' . $value . ';';
                    }
                    $httpHeaders[] = $cookies;
                }
                if ($this->hasHttpAuth) {
                    $httpAuth = 'Authorization: Basic ' . base64_encode(implode(':', $this->httpAuth));
                    $httpHeaders[] = $httpAuth;
                }
                
                if ($this->hasProxy) {
                    $host = $this->proxy['host'];
                    $port = $this->proxy['port'];
                    $httpHeaders[0] = $this->method . ' ' . $url['path'] . " HTTP/1.1 \r\nHost:{$this->proxy['host']}";
                    $httpHeaders[] = 'Proxy-Authorization: Basic ' . base64_encode($this->proxy['user'] . ':' . $this->proxy['password']);
                }
                $response = $this->fstream($this->method, $this->uri, $httpHeaders, $parameters);
            
            default:
                break;
        }
        if ($response) {
            switch ($this->responseFormat) {
                case 'json':
                    $this->responseBody = $this->jsonResponse($response['body']);
                    break;
                case 'xml':
                    $this->responseBody = $this->xmlResponse($response['body']);
                    break;
                default:
                    $this->responseBody = $response['body'];
            }
            $this->responseHeader = $this->parseResponseHeader($response['header']);
        } else {
            $this->responseBody = false;
        }
        return $this;
    }

    /**
     * curl 请求数据
     *
     * @param string $method            
     * @param string $uri            
     * @param string $parameters            
     * @param array $headers            
     * @param array $options            
     * @return mixed
     */
    private function curl(string $method, string $uri, string $parameters = '', $headers = [], $options = [])
    {
        $ch = curl_init();
        $options[CURLOPT_HTTPHEADER] = $headers;
        // curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (strtoupper($method) == 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $parameters;
        } elseif (strtoupper($method) != 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            $options[CURLOPT_POSTFIELDS] = $parameters;
        } elseif ($parameters) {
            $this->uri .= strpos($uri, '?') ? '&' : '?';
            $this->uri .= $parameters;
        }
        $options[CURLOPT_URL] = $uri;
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);
        if (! $response) {
            return false;
        }
        list ($responseHeaders, $responseBody) = explode("\r\n\r\n", $response, 2);
        $responseHeaders = explode("\r\n", $responseHeaders);
        return [
            'header' => $responseHeaders,
            'body' => $responseBody
        ];
    }

    /**
     * fscoket 提交数据
     *
     * @param unknown $method            
     * @param string $referer            
     */
    private function fsocket(string $host, int $port = 80, string $parameters = '', array $headers = [])
    {
        $request = implode("\r\n", $headers);
        $request .= "\r\n";
        $request .= "Connection: close\r\n";
        $request .= "\r\n";
        $request .= $parameters . "\n";
        
        $fp = fsockopen($host, $port);
        
        fputs($fp, $request);
        
        $response = '';
        while (! feof($fp)) {
            $response .= fgets($fp, 1024);
        }
        fclose($fp);
        
        $response = preg_replace_callback('/(?:(?:\r\n|\n)|^)([0-9A-F]+)(?:\r\n|\n){1,2}(.*?)((?:\r\n|\n)(?:[0-9A-F]+(?:\r\n|\n))|$)/si', function ($matches) {
            return hexdec($matches[1]) == strlen($matches[2]) ? "\r\n" . $matches[2] : "\r\n" . $matches[0];
        }, $response);
        list ($responseHeaders, $responseBody) = explode("\r\n\r\n", $response, 2);
        
        $responseHeaders = explode("\r\n", $responseHeaders);
        return [
            'header' => $responseHeaders,
            'body' => $responseBody
        ];
    }

    /**
     * fstream 提交数据
     *
     * @param string $method            
     */
    private function fstream(string $method, string $uri, array $headers = [], string $parameters, $timeout = 20)
    {
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $parameters,
                'timeout' => $timeout
            ]
        ]);
        $response = file_get_contents($uri, false, $context);
        return [
            'header' => $http_response_header,
            'body' => $response
        ];
    }

    private function response()
    {
        $type = strtolower($this->responseFormat);
        switch ($type) {
            case 'json':
                return $this->jsonResponse($this->responseBody);
                break;
            case 'xml':
                return $this->xmlResponse($this->responseBody);
                break;
            case 'text':
                return $this->responseBody;
                break;
            default:
                return $this->responseBody;
                break;
        }
    }

    private function jsonResponse($string)
    {
        $json = json_decode($string, true);
        if ($json) {
            return $json;
        } else {
            return $string;
        }
    }

    private function xmlResponse($string)
    {
        $string = str_replace([
            '<![CDATA[',
            ']]>'
        ], [
            '<![CDATA[ ',
            ' ]]>'
        ], $string);
        $xml = simplexml_load_string($string);
        $xmljson = json_encode($xml);
        return json_decode($xmljson, true);
    }

    private function binResponse($string)
    {
        $length = strlen($string);
        return unpack('C' . $length, $string);
    }

    private function parseResponseHeader(array $headers)
    {
        foreach ($headers as $head) {
            if (preg_match('/^HTTP\/1.1\s([2345][0-9]{2})\s([a-zA-Z0-9_]+)/', $head, $rst)) {
                $responseHeader['http_code'] = $rst[1];
                $responseHeader['http_message'] = $rst[2];
            }
            
            if (preg_match('/^Content-Type:\s(.+)/', $head, $rst)) {
                $responseHeader['content_type'] = $rst[1];
            }
            
            if (preg_match('/^Set-Cookie:\s(.+)/', $head, $rst)) {
                $arr = explode(';', $rst[1]);
                
                foreach ($arr as $value) {
                    list ($k, $v) = explode('=', $value);
                    $k = trim($k);
                    if (in_array($k, [
                        'domain',
                        'path'
                    ])) {
                        $responseHeader['cookie_' . $k] = $v;
                    } else {
                        $responseHeader['cookies'][$k] = $v;
                    }
                }
            }
        }
        return $responseHeader;
    }
}