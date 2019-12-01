<?php


namespace twinkle\apollo;


class Http
{

    const POST = 'POST';
    const GET = 'GET';

    protected $baseUri;

    /**
     * 请求头
     * 如 CLIENT-IP Hosts等
     * @var array
     */
    private $headers = [];

    /**
     * 请求参数
     *
     * @var array
     */
    private $options = [
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_HEADER => 0,
        CURLOPT_NOSIGNAL => 1,
        CURLOPT_FOLLOWLOCATION => 1,
    ];

    /**
     * 当前请求
     */
    protected $request;

    protected $format = 'form-data';

    public function __construct($config = [])
    {
        if (isset($config['options'])) {
            $this->options = $config['options'] + $this->options;
        }
        if (isset($config['headers'])) {
            $this->headers = $config['headers'] + $this->headers;
        }
    }

    /**
     * @param string $baseUri
     */
    public function setBaseUri($baseUri)
    {
        $this->baseUri = $baseUri;
    }

    /**
     * 设置options
     *
     * @param array $options
     * @return $this
     */
    public function setOptions($options = [])
    {
        $this->options = $options + $this->options;
        return $this;
    }

    /**
     * 设置Headers
     *
     * @param array $headers
     * @return $this
     */
    public function setHeaders($headers = [])
    {
        $this->headers = $headers + $this->headers;
        return $this;
    }

    /**
     * 设置post参数
     *
     * @param mixed $vars
     * @return  $this
     */
    public function setPostFields($vars)
    {
        if (!empty($vars)) {
            if (!is_string($vars)) {
                switch ($this->format) {
                    case 'json':
                        $vars = json_encode($vars);
                        break;
                    default:
                        $vars = http_build_query($vars, '', '&');
                }
            }
            $this->options[CURLOPT_POSTFIELDS] = $vars;
        }
        return $this;
    }

    public function request($method, $url, $vars = array())
    {
        $url = $this->baseUri . $url;
        $this->options[CURLOPT_URL] = $url;
        $this->request = curl_init();
        $this->setRequestHeaders();
        $this->setPostFields($vars);
        $this->setRequestMethod($method);
        $this->setRequestOptions();

        $backData = [
            'data' => curl_exec($this->request),
            'httpCode' => curl_getinfo($this->request, CURLINFO_HTTP_CODE)
        ];

        if (200 <> $backData['httpCode']) {
            $backData['error_no'] = curl_errno($this->request);
            $backData['error_msg'] = curl_error($this->request);
        }

        curl_close($this->request);

        return $backData;
    }

    /**
     * @param string $url
     * @param array | string $vars
     * @return mixed
     */
    public function post($url, $vars = [])
    {
        return $this->request(self::POST, $url, $vars);
    }

    public function get($url, $vars = [])
    {
        if (!empty($vars)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= (is_string($vars)) ? $vars : http_build_query($vars, '', '&');
        }
        return $this->request(self::GET, $url);
    }

    private function setRequestMethod($method)
    {
        switch (strtoupper($method)) {
            case 'HEAD' :
                curl_setopt($this->request, CURLOPT_NOBODY, true);
                break;
            case 'GET' :
                curl_setopt($this->request, CURLOPT_HTTPGET, true);
                break;
            case 'POST' :
                curl_setopt($this->request, CURLOPT_POST, true);
                break;
                break;
            default :
                curl_setopt($this->request, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    private function setRequestHeaders()
    {
        $headers = array();
        foreach ($this->headers as $key => $value) {
            if ('Content-Type' == $key) {
                if (strpos($value, 'json') !== false) {
                    $this->format = 'json';
                }
            }
            $headers[] = $key . ': ' . $value;
        }
        curl_setopt($this->request, CURLOPT_HTTPHEADER, $headers);
    }

    private function setRequestOptions()
    {
        foreach ($this->options as $key => $value) {
            curl_setopt($this->request, $key, $value);
        }
    }

}