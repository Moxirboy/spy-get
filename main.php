<?php
require "spider_fetchers/spider_lex_uz.php";

$baseUrl = $GLOBALS['base_url'] = "https://lex.uz";
$spider = new spider_lex_uz();
$mainUrl = "https://lex.uz/uz/";
$mainUrls = $spider->harvest($mainUrl,  "//a[contains(@class,'law__card-item')]/@href");

foreach ($mainUrls as $url) {
    $path = parse_url($url, PHP_URL_PATH);

    $GLOBALS['id']   = basename($path);

    $subUrls = $spider->harvest($baseUrl.$url,"//div[contains(@class,'const-order__item-inside')]//div[contains(@class,'lx_link')]/@onclick");
    $cleanSubUrls = [];
    foreach ($subUrls as $subUrl) {
        $clean = "";
        if (preg_match("/lxOpenUrl\('([^']+)'\)/", $subUrl, $m)) {
            $clean = html_entity_decode($m[1]);
            $cleanSubUrls[] = $baseUrl.$clean;
        }
    }
    foreach ($cleanSubUrls as $cleanSubUrl) {
        $finalFileUrls["file_urls"] = $spider->harvest($cleanSubUrl,"//a[contains(@class,'lx_link')]/@href");
        $spider->getFile($finalFileUrls);

    }

}