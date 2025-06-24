<?php
require_once 'spider_fetchers/spider_sahifa_tj.php';
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
        $word = $spider->readSpecificLines("output/look-".$langsset[$lang].".txt", $i,1);
        $words = $spider->getTranslations($url,$word[0]);
        $spider->saveTranslations($lang,$word[0],$words);
    }
}