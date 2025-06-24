<?php
require 'spider_fetchers/spider_controller.php';
require('vendor/setasign/fpdf/fpdf.php');

class spider_kazneb_kz extends spider_controller{
    public function __construct(){
        parent::__construct();
    }

    private function fetchUrl($url){
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
            CURLOPT_RETURNTRANSFER=> true,
            CURLOPT_VERBOSE=>true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_PROXY => "127.0.0.1:9050",
            CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
        ];
        $xph = new XPathHelper($url);
        $item = [
            'url'=>$xph->queryEvaluate("//div[contains(@class,'arrival-links')]/a/@href"),
            'name'=> $xph->queryEvaluate("//h4[contains(@class,'arrival-title')]"),
            'author' =>$xph->queryEvaluate("//p[contains(@class,'arrival-info-author')]"),
        ];
        return $item;
    }

    private function fetchImageUrls($url){
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
        $imagePages = $xph->queryEvaluate("//script[contains(text(),'pages')]");
        $pattern = '/pages\.push\("([^"]+)"\);/';

        $matches = [];
        $extractedUrls ="";
        if (preg_match_all($pattern, $imagePages, $matches)) {
            $extractedUrls = $matches[1];
            echo "Image Urls extracted \r";
        } else {
            echo "No URLs found matching the pattern.";
        }
        return $extractedUrls;
    }


    function correctAndDecodeUrl($url) {
        global $baseDomainForImages; // Assuming you might set a global base domain if needed

        // Decode HTML entities like &amp;
        $decodedUrl = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Check if the URL is missing the scheme/domain (e.g., starts with /FileStore/)
        // This is a simple check; you might need a more robust one depending on your URL sources
        if (strpos($decodedUrl, 'http://') !== 0 && strpos($decodedUrl, 'https://') !== 0) {
            if (defined('BASE_IMAGE_URL') && strpos($decodedUrl, '/') === 0) {
                // Prepend base URL if it's a root-relative path
                $decodedUrl = BASE_IMAGE_URL . $decodedUrl;
            } else {
                // Log an error or handle incomplete URLs that aren't root-relative
                error_log("URL is not absolute and no base domain is defined or not root-relative: " . $decodedUrl);
                // Optionally, return the original decoded URL or null/false to indicate an error
            }
        }
        return $decodedUrl;
    }
    function fetchImageData($url) {
        $url = $this->correctAndDecodeUrl("https://kazneb.kz".$url);

        $imageData = @file_get_contents($url);
        if ($imageData === false) {
            error_log("Failed to fetch image: " . $url);
            return null;
        }
        return $imageData;
    }

    private function fetchImages($url){
        $xph = new XPathHelper($url);
        return $xph->html;
    }


    public function run($url){
        echo "Fetching $url...\r";
        $item = $this->fetchUrl($url);
        $pdf = new FPDF();
        $pdf->SetAuthor($item['name']);
        $pdf->SetTitle($item['author']);
        $pdf->SetCreator('FPDF PHP Library');
        $urls = $this->fetchImageUrls("https://kazneb.kz".$item['url']);
        if (empty($urls)) {
            return;
        }
        $counted = count($urls);
        echo "$counted images found\n";
        foreach ($urls as $index => $url) {
            echo "Processing URL: " . $url . "\r";

            $imageType = '';
            $urlLower = strtolower($url);
            if (strpos($urlLower, '.png') !== false) {
                $imageType = 'PNG';
            } elseif (strpos($urlLower, '.jpg') !== false || strpos($urlLower, '.jpeg') !== false) {
                $imageType = 'JPEG';
            } elseif (strpos($urlLower, '.gif') !== false) {
                $imageType = 'GIF';
            } else {
                $tempImageDataForTypeCheck = @file_get_contents($url);
                if ($tempImageDataForTypeCheck) {
                    $info = getimagesizefromstring($tempImageDataForTypeCheck);
                    unset($tempImageDataForTypeCheck);
                    if ($info && isset($info['mime'])) {
                        if ($info['mime'] == 'image/png') $imageType = 'PNG';
                        elseif ($info['mime'] == 'image/jpeg') $imageType = 'JPEG';
                        elseif ($info['mime'] == 'image/gif') $imageType = 'GIF';
                    }
                }
            }

            if (empty($imageType)) {
                echo "Could not determine image type for URL: " . $url . ". Skipping.\r";
                continue;
            }

            $pdf->AddPage();

            $imageDataForSize = $this->fetchImageData($url);
            if ($imageDataForSize) {
                $size = @getimagesizefromstring($imageDataForSize);
                unset($imageDataForSize);

                if ($size) {
                    list($width, $height) = $size;

                    // ** 여기가 수정된 부분입니다 (This is the corrected part) **
                    $pageWidth = $pdf->GetPageWidth();
                    $pageHeight = $pdf->GetPageHeight() ;
                    $wRatio = $pageWidth / $width;
                    $hRatio = $pageHeight / $height;
                    $ratio = min($wRatio, $hRatio);

                    $newWidth = $width * $ratio;
                    $newHeight = $height * $ratio;

                    // ** 여기가 수정된 부분입니다 (This is the corrected part) **
                    $x = ($pageWidth - $newWidth) / 2 ;
                    // For vertical centering, ensure $y considers the top margin correctly
                    // If you want it centered within the usable page area:
                    $y = ($pageHeight - $newHeight) / 2 ;
                    // Or if you just want it placed after the top margin (not necessarily centered vertically on page):
                    // $y = $pdf->GetTMargin();

                    $url = $this->correctAndDecodeUrl("https://kazneb.kz".$url);
                    $pdf->Image($url, $x, $y, $newWidth, $newHeight, $imageType);
                    echo "Image from " . $url . " (type: ".$imageType.") added to PDF.\r";
                } else {
                    echo "Could not get image size for: " . $url . ". Trying to add without scaling.\r";
                    $pdf->Image($url, null, null, 0, 0, $imageType);
                }
            } else {
                echo "Failed to fetch image data for size calculation for URL: " . $url . "\r";
            }
        }

        $outputFilePath = "output/".str_replace(" ", "_", $item['name']);
        $pdf->Output('F', $outputFilePath);

        echo "All images processed. PDF saved to: " . $outputFilePath."\r\n";

    }
}