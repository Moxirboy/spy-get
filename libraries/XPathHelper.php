<?php

class XPathHelper {
    public $html;

    public static $_debug = false; //activate debug logs to standard ouput

    public $url;

    public $last_url;
    public $accept_refresh = false;

    public $_xpath;
    public $use_proxy = false;

    public function __construct($url, $proxy = true) {
        $this->use_proxy = $proxy;
        $this->html = $this->curl_fetch($url);
        $this->_xpath = self::html2xpath($this->html);
    }

    public static function html2xpath($html)
    {
        if (empty($html) || ! is_string($html)) {
            return null;
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_use_internal_errors(false);
        $xpath = new DOMXPath($doc);
        $xpath->html = $html;
        if (XPathHelper::$_debug >= 1) {
            echo "loading...\n";
        }

        return $xpath;
    }

    public function html_deescaper($url, $start_snip, $end_snip)
    {
        $html = $this->curl_fetch($url);
        if (strpos($html, $start_snip) === false) {
            return $html;
        }
        @list($junk, $html) = explode($start_snip, $html);
        @list($html, $junk) = explode($end_snip, $html);
        $html = str_replace('\\', '', $html);
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        $html = preg_replace('#<noscript(.*?)>(.*?)</noscript>#is', '', $html);

        return $html;
    }


    function setScope($index, $value = null)
    {
        if (! array_key_exists('SCOPE', $GLOBALS)) {
            $GLOBALS['SCOPE'] = [];
        }
        if (is_null($value)) {
            unset($GLOBALS['SCOPE'][$index]);
        } else {
            $GLOBALS['SCOPE'][$index] = $value;
        }
    }

    function urljoin($baseUrl, $partialUrl)
    {
        $baseUrl = is_string($baseUrl) ? trim($baseUrl) : '';
        $partialUrl = is_string($partialUrl) ? trim($partialUrl) : '';
        if (empty($baseUrl) && empty($partialUrl)) {
            return 'http://';
        }

        if (preg_match('/^https?:\/\//i', $partialUrl)) { // nothing to do here, partial URL is not partial
            return $partialUrl;
        }
        if (preg_match('/^\/\/\w/', $partialUrl)) { // partial is just missing the scheme
            return preg_match('/^(\w+):\/\//', $baseUrl, $match) ? "{$match[1]}:{$partialUrl}" : "http:{$partialUrl}";
        }
        $parts = parse_url($baseUrl);
        if (! array_key_exists('scheme', $parts)) {
            $parts['scheme'] = 'http';
        }
        $root = "{$parts['scheme']}://";
        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            $root .= (empty($parts['user']) ? '' : $parts['user']) . ':' . (empty($parts['pass']) ? '' : $parts['pass']) . '@';
        }
        $root .= $parts['host'];
        if (! empty($parts['port'])) {
            $root .= ":{$parts['port']}";
        }
        if (! array_key_exists('path', $parts)) {
            $parts['path'] = '/';
        }
        if (preg_match('/^\//', $partialUrl)) { // partial starts with /, so let's just append it to the root
            return "{$root}{$partialUrl}";
        }
        if ($parts['path'] == '/') { // base path is simple, so let's just append partial to its end
            return "{$root}/{$partialUrl}";
        }
        $path = explode('/', $parts['path']);
        if (strlen($path[sizeof($path) - 1])) { // if last part isn't a /, let's chop it off
            array_pop($path);
            $path[] = '';
        }

        return $root . implode('/', $path) . "{$partialUrl}";
    }

    public function curl_fetch($url) {
        $ch = curl_init();
        $base = [
            CURLOPT_URL => $url, // <-- This line is required!
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 9,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_PROXYAUTH => CURLAUTH_ANY,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_TCP_NODELAY => 1,
            // , CURLOPT_VERBOSE => 1 // debugging purposes
        ];
        $curlopts = empty($GLOBALS['curlopts']) ? $base : self::merge_headers($GLOBALS['curlopts'], $base);
        if ($this->use_proxy && !empty($GLOBALS['proxy'])) {
            $curlopts[CURLOPT_PROXYTYPE] =  $GLOBALS['proxy_type'];
            $curlopts[CURLOPT_PROXY] = $GLOBALS['proxy'];
        }

        curl_setopt_array($ch, $curlopts);
        $html = curl_exec($ch);

        $matches = [];
        if (($this->accept_refresh) && (preg_match('/(?<=meta\shttp-equiv=\"refresh\"\scontent=\").*?(?=\")/', $html, $matches) || preg_match('/(?<=meta\shttp-equiv=\\\"refresh\\\"\scontent=\\\").*?(?=\\\")/', $html, $matches))) {

            $parametersRefresh = $matches[0];
            $this->last_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $urlRefresh = $this->last_url;
            if (preg_match('/^[0-9]+/', $parametersRefresh, $matches)) {
                $delay = floatval(trim($matches[0]));
                sleep($delay);
            }

            if (preg_match('/(?<=url=).*/', $parametersRefresh, $matches)) {
                $relativeUrl = html_entity_decode($matches[0]);
                $urlRefresh = $this->urljoin($this->last_url, $relativeUrl);
            }
            curl_setopt($ch, CURLOPT_URL, $urlRefresh);
            $html = curl_exec($ch);
        }

        $header = curl_getinfo($ch);
        $this->setScope('HTTP_CODE', $header['http_code']);
        $this->setScope('HTTP_PAYLOAD', $header['size_download']);

        /* check for execution errors */
        $err = curl_errno($ch);
        if ($err) {

            if (XPathHelper::$_debug) {
                $this->error = "## CURLERROR http_code {$header['http_code']} " . curl_error($ch)
                    . "\n## CURL_GETINFO " . str_replace("\n", ' | ', print_r(curl_getinfo($ch), true));
            }
            // simple escalation policy: use a better proxy provider only if there is a failure
            //$this->ci->ProxyIps->set_better_proxy_provider();
        } else {

            $this->last_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

            // Exception - Character "#"  truncates data - fix incomplete last_url
            if (preg_match("/\#/", $url)) {
                @$this->last_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            }
        }

        curl_close($ch);
        $filePath = 'last.html'; // Define the path and name of the file

        if ($html === false) {
            echo 'cURL Error: ' . curl_error($ch);
        } else {
            if (XPathHelper::$_debug == 3) {

            if (file_put_contents($filePath, $html) !== false) {
                echo "Successfully wrote content to " . $filePath . PHP_EOL;
            } else {
                echo "Error writing to file " . $filePath . PHP_EOL;
            }
            }

        }
        return $html;
    }

    public function queryEvaluate($query)
    {
        //echo "inside queryEvaluate()\n";
        if (empty($query)) {
            return null;
        }
        if (! is_object($this->_xpath)) {
            return null;
        }

        $nodelist = @$this->_xpath->evaluate($query);
        if (empty($nodelist)) {
            return null;
        }

        if (is_object($nodelist)) {
            if (get_class($nodelist) === 'DOMNodeList') {
                //$res= array();
                foreach ($nodelist as $node) {
                    if (is_subclass_of($node, 'DOMNode')) {
                        $nodeval = trim($node->nodeValue);

                        return $nodeval; // return first one
                    }
                    //$res[]=$node->nodeValue;
                }
                //print_r($res);
            }
        } else {
            $nodelist = trim($nodelist);

            return $nodelist;
        }
    }

    public static function merge_headers($preferred, $base)
    {
        $output = $base;
        $overwrite = [];
        foreach ($preferred as $type => $obj) {
            if ($type == CURLOPT_HTTPHEADER) {
                if (! is_array($obj)) {
                    continue;
                }
                foreach ($obj as $header) {
                    if (preg_match('/^([^:]+):\s*(.+)/', $header, $match)) {
                        if (preg_match('/user-agent/i', $match[1])) {
                            $overwrite[CURLOPT_USERAGENT] = $match[2];

                            continue;
                        }
                        if (preg_match('/accept-encoding/i', $match[1])) {
                            $overwrite[CURLOPT_ENCODING] = $match[2];

                            continue;
                        }
                        if (array_key_exists(CURLOPT_HTTPHEADER, $output)) {
                            $existing = preg_grep("/{$match[1]}\s*:/i", $output[CURLOPT_HTTPHEADER]);
                            if (! sizeof($existing)) {
                                $output[CURLOPT_HTTPHEADER][] = $header;
                            } elseif (sizeof($existing) == 1) {
                                foreach ($existing as $index => $value) {
                                    array_splice($output[CURLOPT_HTTPHEADER], $index, 1, $header);
                                }
                            } else {
                                foreach ($existing as $index => $value) {
                                    array_splice($output[CURLOPT_HTTPHEADER], $index, 1);
                                }
                                $output[CURLOPT_HTTPHEADER][] = $header;
                            }
                        } else {
                            $output[CURLOPT_HTTPHEADER] = [$header];
                        }
                    }
                }
            } else {
                $output[$type] = $obj;
            }
        }
        foreach ($overwrite as $type => $obj) {
            $output[$type] = $obj;
        }

        return $output;
    }

    public function makeJsonRequest($url, $data) {
        // Set up the headers for the request
        $headers = [
            'accept: application/json',
            'accept-language: en-US,en;q=0.9',
            'content-type: application/json; charset=UTF-8',
            'language: ru',
            'origin: https://etender.uzex.uz',
            'priority: u=1, i',
            'referer: https://etender.uzex.uz/',
            'sec-ch-ua: "Not.A/Brand";v="99", "Chromium";v="136"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Linux"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-site',
            'user-agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
            'validation: TXByrCrVBkRK8eNKQLbIqU0TvYuit4YI68XjBdS/fJRsWUnq7l4Zr8LnDV5DITA76t/As55NgEguhU/e3HPiWMlNj2Jsx4i4AgZXk6IUhW/JZfyRcOBwOeKwhy8h4J3KDGlhG1tPYhVofmaqBT+I3XZlHvBDRzZsJV4l2bfopFc='
        ];

        // Set up the cURL options
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 9,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_PROXYAUTH => CURLAUTH_ANY,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_TCP_NODELAY => 1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers
        ]);

        // Execute the request
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            if (self::$_debug) {
                $this->error = "## CURLERROR http_code " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . " " . curl_error($ch)
                    . "\n## CURL_GETINFO " . str_replace("\n", ' | ', print_r(curl_getinfo($ch), true));
                echo $this->error;
            }
        }

        // Close the connection
        curl_close($ch);

        return $response;
    }


    public function queryEvaluateItems($query)
    {
        //if ($this->debug) echo "inside queryEvaluate()\n";
        if (empty($query)) {
            return null;
        }
        if (! is_object($this->_xpath)) {
            return null;
        }
        $nodelist = $this->_xpath->evaluate($query);
        $items = [];
        if (is_object($nodelist)) {
            if (get_class($nodelist) === 'DOMNodeList') {
                foreach ($nodelist as $node) {
                    if (is_subclass_of($node, 'DOMNode')) {
                        $items[] = trim($node->nodeValue);
                    }
                }
            }
        } else {
            $items[] = trim($nodelist);
        }

        return $items;
    }
}