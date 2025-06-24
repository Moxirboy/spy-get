<?php

$Classes = [
    'asilmedia' => "asilmedia_net",
    'uzmovie' => "uzmovie_com",
    'sahifa' => "sahifa_tj",
    'stepen' => "stepen_vak_kg",
    'vak' => "vak_tj",
];
if (php_sapi_name() === 'cli') {
    if ($argc>1){
        require_once 'spider_fetchers/spider_'.$Classes[$argv[1]].'.php';

        switch ($argv[1]) {
            case'asilmedia':

                require 'libraries/json.php';
                $baseUrl = "http://asilmedia.org/films/tarjima_kinolar/";
                $asilmedia = new spider_asilmedia_net();
                $filePath = "./output/".$GLOBALS['movie_path']."-output.jsonl";
                $numberOfPages = $asilmedia->getNumberOfPages($baseUrl);
                for ($i = 1; $i <= $numberOfPages; $i++) {
                    $moviesUrl = $baseUrl."page/".$i."/";
                    $moviesUrls = $asilmedia->harvest($moviesUrl);
                    foreach ($moviesUrls as $movieUrl) {
                        $movieDetails = $asilmedia->getMovieDetails($movieUrl);
                        $success = writeToJsonl($filePath, $movieDetails,'a');
                        if ($success) {
                            echo "success";
                        } else {
                            echo "error";
                        }

                    }
                }
                break;

            case 'uzmovie':
                require 'libraries/json.php';
                $baseUrl = "https://uzmovi.bot/tarjima-kinolar/";
                $uzmovie = new spider_uzmovie_com();
                $filePath = "./output/".$GLOBALS['movie_path']."-output.jsonl";
                $numberOfPages = $uzmovie->getNumberOfPages($baseUrl);
                for ($i = 1; $i <= $numberOfPages; $i++) {
                    $moviesUrl = $baseUrl."page/".$i."/";
                    $moviesUrls = $uzmovie->harvest($moviesUrl);
                    foreach ($moviesUrls as $movieUrl) {
                        $movieDetails = $uzmovie->getMovieDetails($movieUrl);
                        $success = writeToJsonl($filePath, $movieDetails,'a');
                        if ($success) {
                            echo "success";
                        } else {
                            echo "error";
                        }

                    }
                }
                break;
            case 'sahifa':
                if ($argv[2]==0){
                    $langs = [
                        'uz',
                        'rus',
                        'tj',
                        'kg',
                        'kz',
                        'tt'
                    ];
                    foreach($langs as $lang) {
                        $spider = new spider_sahifa_tj("trans/".$lang."-rus", 1, 1,"output/look-".$lang.".txt");
                        $spider->lookUp($lang);
                    }
                }else{
                    $langs = [
                        'uz-rus',
                        'rus-uz',
                        'tj-rus',
                        'rus-tj',
                        'kg-rus',
                        'rus-kg',
                        'kz-rus',
                        'rus-kz',
                        'tt-rus',
                        'rus-tt',
                    ];
                    foreach($langs as $lang) {
                        $spider = new spider_sahifa_tj("trans/".$lang."-rus", 1, 1,"output/translation-".$lang.".txt");
                        $url = $spider->translationTableUrl($lang);
                        $langsset = [
                            'uz-rus' => 'uz',
                            'rus-uz' => 'rus',
                            'tj-rus' => 'tj',
                            'rus-tj' => 'rus',
                            'kg-rus' => 'kg',
                            'rus-kg' => 'rus',
                            'kz-rus' => 'kz',
                            'rus-kz' => 'rus',
                            'tt-rus' => 'tt',
                            'rus-tt' => 'rus',
                        ];
                        $lines = $spider->countLineFile("output/look-".$langsset[$lang].".txt");
                        for($i = 1; $i <= $lines; $i++) {
                            $filename = "output/look-".$langsset[$lang].".txt";
                            $word = $spider->readSpecificLines($filename, $i,1);

                            echo "\r\033[Kreading ".$filename." ".json_encode($word)."....";
                            $words = $spider->getTranslations($url,$word);
                            echo "\r\033[Ksaving....";
                            $spider->saveTranslations($lang,$word[0],$words);
                            sleep(1.5);
                            echo "\r\033[Ksleeping....";
                        }
                    }
                }
                break;
            case 'stepen':
                $pageurl = "https://stepen.vak.kg/avtoreferaty/";
                $spider = new spider_stepen_vak_kg();
                $numberOfPages = $spider->getNumberOfPages($pageurl);
                for ($i = 1; $i <= $numberOfPages; $i++) {
                    $pageUrl = $pageurl."page/".$i."/";
                    $urls = $spider->harvest($pageUrl);
                    foreach ($urls as $url) {
                        $item = $spider->getPageDetails($url);
                        $success = $spider->getFile($item);
                        if (!$success) {
                            echo "failed " . json_encode($item) . "\n";
                        }
                    }
                }
                break;
            case 'vak':
                if ($argv[2]==0){
                    require "libraries/json.php";
                    $pageurl = "https://vak.tj/hac_new/index.php/ru/novosti/ob-yavleniya-o-zashchite-dissertatsij";
                     $filePath = "output/vak_tj.jsonl";
                     $spider = new spider_vak_tj("vak_tj");
                     for ($i = 0; $i<=61; $i++) {
                          $pageUrl = $pageurl."?start=".$i."0";
                        $urls = $spider->harvest($pageUrl);
                         foreach ($urls as $url) {
                              $itemUrl = $spider->getPageUrl("https://vak.tj".$url);
                             if (empty($itemUrl)) {
                                 echo "empty " . $url ." ".json_encode($itemUrl) . "\n";
                                 continue;
                             }
                             $item = $spider->getfileUrl($itemUrl);
                             if (empty($item)) {
                                 echo "failed " . $url ." ".json_encode($item) . "\n";
                                 continue;
                             }
                             if (count($item['file_urls'])!=1){
                                 if(count($item['file_urls'])>1){
                                     foreach ($item['file_urls'] as $fileUrl) {
                                         if(!preg_match("/autoref/i", $fileUrl)) {
                                             echo "empty file_url".json_encode($item['base_url']) . "\n";
                                         }
                                     }
                                 }
                            }
                            $success = $spider->getFile($item);
                            if (!$success) {
                                echo "failed " . json_encode($item) . "\n";
                            }
                            $error = writeToJsonl($filePath, $item,'a');

                            if (!$error) {
                                echo "failed " . $url ." ".json_encode($item) . "\n";
                            }
                        }
                    }
                }else{
                    require "libraries/json.php";
                    $pageurl = "http://vak.tj/old/index.php/ru/novosti/ob-yavleniya-o-zashchite-dissertatsij";
                    $filePath = "output/vak_tj.jsonl";
                    $spider = new spider_vak_tj();
                    $urls = $spider->harvest($pageurl);
                    foreach ($urls as $url) {
                        $itemUrl = $spider->getPageUrl("https://vak.tj".$url);
                        if (empty($itemUrl)) {
                            echo "empty " . $url ." ".json_encode($itemUrl) . "\n";
                            continue;
                        }
                        $item = $spider->getfileUrl($itemUrl);
                        if (empty($item)) {
                            echo "failed " . $url ." ".json_encode($item) . "\n";
                            continue;
                        }
                        $error = writeToJsonl($filePath, $item,'a');
                        if (!$error) {
                            echo "failed " . $url ." ".json_encode($item) . "\n";
                        }
                    }
                }
                break;
            default:
                require "spider_fetchers/spider_kazneb_kz.php";
                $spider = new spider_kazneb_kz();
                $spider->run($argv[1]);

        }
    }else{
        echo "please give arguments";
    }

}
