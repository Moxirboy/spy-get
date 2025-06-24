<?php

require "spider_fetchers/spider_controller.php";
require "libraries/xpath_helper.php";
class spider_vak_tj extends spider_controller {
    function __construct($base_url)
    {
        parent::__construct($base_url);
    }


    public function harvest(string $url): array
    {
        $xph = new XPathHelper($url);
        $items = allNonEmpty($xph, [
            "//tbody//tr[contains(@class,'cat-list')]//a/@href",
            "//ul[contains(@class,'category')]/li//strong/a/@href",
        ]);
        return $items;
    }

    public function getPageUrl(string $url): string {
        $xph = new XpathHelper($url);
        $result = firstNonEmpty($xph, [
            "//div[contains(@itemprop,'articleBody')]//a/@href",
            "//div[contains(@class,'item-page')]/p//a/@href",
        ]);
        if (empty($result)) {
            echo "empty page url: \n" . $url ."\n".json_encode($result) . "\n";
            $result = "";
        }
        return $result;
    }

    public function getfileUrl(string $url): array {
        $xph = new XpathHelper($url);
        $urls = allNonEmpty($xph, [
            "//a[contains(.,'Автореферат')]/@href",
            "//h3[contains(.,'Автореферати')]//following-sibling::a/@href",
            "//td[contains(.,'Автореферат')]//following-sibling::td//a/@href",
            "//tr[contains(@class,'row-7')]//td[contains(text(),'13')]//a/@href",
            "//td[contains(text(),'Автореферати')]//following-sibling::td//a/@href",
            "//th[contains(.,'АВТОРЕФЕРАТ')]//following-sibling::td//a/@href",
            "//td[contains(.,'автореферата')]//following-sibling::td/a/@href",
        ]);

        if (empty($urls)) {
            echo "\033[31m\n$url\nwas not recognized \033[0m\n";  // Red text
            $urls = $xph->queryEvaluateItems(            "//a[contains(. ,'Автореферат') or contains(. ,'автореферат') or contains(. ,'Нашр') or contains(. ,'нашр') or contains(. ,'Опубликовано') or contains(. ,'Опубликован')]/@href",
            );
        }
        $name = $xph->queryEvaluate("//title");
        return [
            'name' =>$name,
            'base_url' => $url,
            'file_urls'=>$urls
        ];
    }

//    public function cleanUrl(string $url){
//        $xph = new XPathHelper($url);
//        $parsedUrl = parse_url($url);
//        $domain = $parsedUrl['host'];
//        $referatUrls = [
//            "shurodis-att.tj" => "//td[contains(text(),'Автореферати')]//following-sibling::td//a/@href",
//            ""
//        ]
//    }
//
//    public function getFileFromUrl(string $url) {
//        $err  = file_get_contents($url);
//        if ($err){
//            echo "error occured ".$err;
//        }
//    }

}