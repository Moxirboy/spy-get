<?php


require 'spider_fetchers/spider_controller.php';

class spider_stepen_vak_kg  extends spider_controller {
    function __construct() {
        XPathHelper::$_debug;
        parent::__construct();
    }

    public function getNumberOfPages(string $url): int
    {
        $xph = new XPathHelper($url);

        return $xph->queryEvaluate("//span[contains(@class,'dots')]/following-sibling::a[2]");
    }

    public function harvest(string $url): array
    {
        $xph = new XpathHelper($url);

        return $xph->queryEvaluateItems("//tr//td[1]//a/@href");
    }

    public function getPageDetails(string $url): array {
        $xph = new XPathHelper($url);
        $item = [
            'url'       => $url,
            'name'      => $xph->queryEvaluate("//tr//th[contains(text(),'Ф.И.О.')]/following-sibling::th") ?? "",
            'title'     => $xph->queryEvaluate("//tr//td[contains(text(),'Тема')]/following-sibling::td") ?? "",
            'file_url'  => $xph->queryEvaluate("//tr//td[contains(text(),'Ссылка на автореферат')]/following-sibling::td/a/@href") ?? "",
            'council'   => $xph->queryEvaluate("//tr//td[contains(text(),'Диссертационный совет')]/following-sibling::td") ?? "",
        ];
        return $item;
    }

}