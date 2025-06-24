<?php
require 'spider_fetchers/spider_controller.php';

class spider_uzmovie_com extends spider_controller
{
    public function __construct(){
        $GLOBALS['base_url'] = 'http://www.uzmovie.uz';
        $GLOBALS['movie_path'] = 'uzmovie.uz';

    }

    public function getNumberOfPages(string $url) : int {
        $xph = new XPathHelper($url);
        $numberOfPages = $xph->queryEvaluate("//span[@class='nav_ext']/following-sibling::a[1]/text()");
        $numberInt = intval($numberOfPages);
        return $numberInt;
    }
    public function harvest(string $url) : array {
        $xph = new XPathHelper($url);
        $res = $xph->queryEvaluateItems("//div[contains(@class,'shortstory-in')]//div[contains(@class,'short-content')]//a/@href");
        return $res;
    }

    public function hasMany(XPathHelper $xph) : bool {
        $numberOfSeries = $xph->queryEvaluate("count(//div[@class='kontaiher']/a)");
        if ($numberOfSeries > 0) {
            return true;
        }
        return false;
    }

    public function getSeries(XPathHelper $xph) : array
    {
        return $xph->queryEvaluateItems("//div[@class='kontaiher']/a/@href");
    }
    public function getVideoUrls($url) : string {
        $xph = new XPathHelper($url);
        return $xph->queryEvaluate("//iframe/@src") ?? '';
    }

    public function getMovieDetails(string $url,bool $sub=false) :array {
        $xph = new XPathHelper($url);
        $hasMany = $this->hasMany($xph);
        $item = [
            'movie.id'=>generateUuidV4(),
            'movie.url'=>$url,
            'movie.name'=> $xph->queryEvaluate("//div[contains(@class,'finfo-text')]/b")??"",
            'movie.poster' =>$xph->queryEvaluate("//meta[@property='og:image']/@content") ?? "",
            'movie.year'=>$xph->queryEvaluate("//span[contains(@class,'film-rip')]") ?? "",
            'movie.length'=>$xph->queryEvaluate("//div[@class='finfo-title' and normalize-space(text())='Davomiyligi']/following-sibling::div[@class='finfo-text']") ?? "",
            'movie.series'=>$hasMany,
            'movie.videoUrl' => $xph->queryEvaluate("//iframe/@src") ?? '',
            'movie.audioPath' => $xph->queryEvaluate("//span[contains(@class,'film-rip')]")."/".$GLOBALS['movie_path']."/".str_replace(" ", "_", $xph->queryEvaluate("//div[contains(@class,'finfo-text')]/b")) ?? "",
        ];
        if ($hasMany && !$sub) {
            $urls = $this->getSeries($xph);
            $series = [];
            foreach ($urls as $url) {

                $serie = $this->getVideoUrls($url);
                $series[] = $serie;
            }
            $item['movie.series'] = $series;
        }
        return $item;
    }
}