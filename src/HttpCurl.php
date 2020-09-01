<?php
namespace mfunc;

class HttpCurl
{
    /**
     * 以get访问模拟访问
     * @param string $url 访问URL
     * @param array $query GET数
     * @param array $options
     * @return boolean|string
     * @throws InvalidArgumentException
     */
    public static function get($url, $query = [], $options = [])
    {
        $options['query'] = $query;
        return self::doRequest('get', $url, $options);
    }

    /**
     * 以post访问模拟访问
     * @param string $url 访问URL
     * @param array $data POST数据
     * @param array $options
     * @return boolean|string
     * @throws InvalidArgumentException
     */
    public static function post($url, $data = [], $options = [])
    {
        $options['data'] = $data;
        return self::doRequest('post', $url, $options);
    }

    /**
     * CURL模拟网络请求
     * @param string $method 请求方法
     * @param string $url 请求方法
     * @param array $options 请求参数[headers,data,ssl_cer,ssl_key]
     * @return boolean|string
     * @throws InvalidArgumentException
     */
    public static function doRequest($method, $url, $options = [])
    {
        $curl = curl_init();
        // GET参数设置
        if (!empty($options['query'])) {
            $url .= (stripos($url, '?') !== false ? '&' : '?') . http_build_query($options['query']);
        }
        // CURL头信息设置
        if (!empty($options['headers'])) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $options['headers']);
        }
        // POST数据设置
        if (strtolower($method) === 'post') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, self::_buildHttpData($options['data']));
        }
        // 证书文件设置
        if (!empty($options['ssl_cer'])) if (file_exists($options['ssl_cer'])) {
            curl_setopt($curl, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($curl, CURLOPT_SSLCERT, $options['ssl_cer']);
        } else throw new \InvalidArgumentException("Certificate files that do not exist. --- [ssl_cer]");
        // 证书文件设置
        if (!empty($options['ssl_key'])) if (file_exists($options['ssl_key'])) {
            curl_setopt($curl, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($curl, CURLOPT_SSLKEY, $options['ssl_key']);
        } else throw new \InvalidArgumentException("Certificate files that do not exist. --- [ssl_key]");
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        list($content) = [curl_exec($curl), curl_close($curl)];
        return $content;
    }

    /**
     * 创建CURL文件对象
     * @param $filename
     * @param string $mimetype
     * @param string $postname
     * @return \CURLFile|string
     * @throws InvalidArgumentException
     */
    public static function createCurlFile($filename, $mimetype = null, $postname = null)
    {
        if (is_string($filename) && file_exists($filename)) {
            if (is_null($postname)) $postname = basename($filename);
            if (is_null($mimetype)) $mimetype = self::getExtMine(pathinfo($filename, 4));
            if (function_exists('curl_file_create')) {
                return curl_file_create($filename, $mimetype, $postname);
            }
            return "@{$filename};filename={$postname};type={$mimetype}";
        }
        return $filename;
    }

    /**
     * 根据文件后缀获取文件类型
     * @param string|array $ext 文件后缀
     * @param array $mine 文件后缀MINE信息
     * @return string
     * @throws InvalidArgumentException
     */
    public static function getExtMine($ext, $mine = [])
    {
        $content = file_get_contents(__DIR__. '/mime.types');
        preg_match_all('#^([^\s]{2,}?)\s+(.+?)$#ism', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) foreach (explode(" ", $match[2]) as $ext) $mines[$ext] = $match[1];

        foreach (is_string($ext) ? explode(',', $ext) : $ext as $e) {
            $mine[] = isset($mines[strtolower($e)]) ? $mines[strtolower($e)] : 'application/octet-stream';
        }
        return join(',', array_unique($mine));
    }

    /**
     * POST数据过滤处理
     * @param array $data 需要处理的数据
     * @param boolean $build 是否编译数据
     * @return array|string
     * @throws InvalidArgumentException
     */
    private static function _buildHttpData($data, $build = true)
    {
        if (!is_array($data)) return $data;
        foreach ($data as $key => $value) if (is_object($value) && $value instanceof \CURLFile) {
            $build = false;
        } elseif (is_object($value) && isset($value->datatype) && $value->datatype === 'MY_CURL_FILE') {
            $build = false;
            $mycurl = new MyCurlFile((array)$value);
            $data[$key] = $mycurl->get();
            array_push(self::$cache_curl, $mycurl->tempname);
        } elseif (is_string($value) && class_exists('CURLFile', false) && stripos($value, '@') === 0) {
            if (($filename = realpath(trim($value, '@'))) && file_exists($filename)) {
                $build = false;
                $data[$key] = self::createCurlFile($filename);
            }
        }
        return $build ? http_build_query($data) : $data;
    }
}