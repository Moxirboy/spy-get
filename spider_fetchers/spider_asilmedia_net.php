<?php
require 'spider_fetchers/spider_controller.php';

class spider_asilmedia_net extends spider_controller
{
    public function __construct(){
        $GLOBALS['base_url'] = 'http://www.asilmedia.org';
        $GLOBALS['movie_path'] = 'asilmedia.org';

    }
    public function harvest(string $url) : array {
        $xph = new XPathHelper($url);
        $res = $xph->queryEvaluateItems("//div[@id='dle-content']/article/a/@href");
        return $res;
    }


    public function getSeries(XPathHelper $xph) : array
    {
        $raw_urls = $xph->queryEvaluateItems("//div[contains(@class,'fullcol-right ')]//div[contains(@class,'film-content')]//option/@value");

        $cleaned_urls = [];
        for($i = 0; $i < count($raw_urls); $i++) {
            if (method_exists($this, 'urlCleaner')) {
                $cleaned_urls[$i] = $this->urlCleaner($raw_urls[$i]);
            } else {
                $cleaned_urls[$i] = trim($raw_urls[$i]);
            }
        }
        $selected_episode_urls = [];
        $other_non_episode_urls = [];
        $regex = '#https?://(?:[a-zA-Z0-9.-]+)/\d+/Seriallar/[^/]+/([^/]+? \d+-qism) (\d+p) \(.*?\.net\)\.mp4$#i';

        foreach ($cleaned_urls as $url) {
            if (preg_match($regex, $url, $matches)) {
                $episode_identifier = $matches[1];
                $quality = strtolower($matches[2]);

                if ($quality === '480p') {
                    $selected_episode_urls[$episode_identifier] = $url;
                } elseif ($quality === '1080p') {
                    if (!isset($selected_episode_urls[$episode_identifier]) ||
                        (isset($selected_episode_urls[$episode_identifier]) && !str_contains(strtolower($selected_episode_urls[$episode_identifier]), '480p'))) {
                        $selected_episode_urls[$episode_identifier] = $url;
                    }
                }
            } else {
                $other_non_episode_urls[] = $url;
            }
        }

        $final_urls = array_values($selected_episode_urls);

        if (!empty($other_non_episode_urls)) {
            $unique_other_urls = array_keys(array_flip($other_non_episode_urls));
            foreach ($unique_other_urls as $other_url) {
                $final_urls[] = $other_url;
            }
        }

        return $final_urls;
    }


    public function getMovieDetails(string $url,bool $sub=false) :array {
        $xph = new XPathHelper($url);
        if (!empty($xph->queryEvaluate("//div[@class='error']/b[contains(text(),'USHBU FILMNI TOMOSHA QILISH UCHUN SAYTIMIZGA VPN ORQALI KIRISHINGIZ KERAK!')]"))){
            $GLOBALS['curlopts'] =[
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Language: en-US,en;q=0.9',
                    'Cache-Control: max-age=0',
                    'Connection: keep-alive','Sec-Fetch-Dest: document',
                    'Sec-Fetch-Mode: navigate',
                    'Sec-Fetch-Site: none',
                    'Sec-Fetch-User: ?1',
                    'Upgrade-Insecure-Requests: 1',
                    'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
                    'sec-ch-ua: "Not.A/Brand";v="99", "Chromium";v="136"',
                    'sec-ch-ua-mobile: ?0',
                    'sec-ch-ua-platform: "Linux"',
                ],
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_RETURNTRANSFER=> true,
                CURLOPT_VERBOSE=>true,
                CURLOPT_PROXY => "127.0.0.1:9050",
                CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
            ];
            $xph = new XPathHelper($url);
        }
        $hasMany = preg_match('/serial/i',$xph->queryEvaluate("//h1[contains(@class,'title')]"));
        $item = [
            'movie.id'=>generateUuidV4(),
            'movie.url'=>$url,
            'movie.name'=> $xph->queryEvaluate("//h1[contains(@class,'title')]") ?? "",
            'movie.poster' =>$xph->queryEvaluate("//meta[@property='og:image']/@content") ?? "",
            'movie.description'=> $xph->queryEvaluate("//div[contains(@class,'full-body')]//p") ?? "",
            'movie.year'=>$xph->queryEvaluate("//span[@class='fullmeta-label' and text()='Год']/following-sibling::span[@class='fullmeta-seclabel']/a/text()") ?? "",
            'movie.length'=>$xph->queryEvaluate("//span[@class='fullmeta-label' and text()='Продолжительность']/following-sibling::span[@class='fullmeta-seclabel']/a/text()") ?? "",
            'movie.series' => (bool) $hasMany,
            'movie.videoUrl' => $this->urlCleaner($xph->queryEvaluate("//iframe[@id='notasix456']/@src") ?? ''),
            'movie.audioPath' => $xph->queryEvaluate("//span[@class='fullmeta-label' and text()='Год']/following-sibling::span[@class='fullmeta-seclabel']/a/text()")."/".$GLOBALS['movie_path']."/".str_replace(" ", "_", $xph->queryEvaluate("//h1[contains(@class,'title')]")) ?? "",
        ];

        if ($hasMany && !$sub) {
            $item['movie.series'] = $this->getSeries($xph);
        }
        return $item;
    }


}