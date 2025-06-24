<?php
require 'libraries/XPathHelper.php';
require 'libraries/uuid.php';
require 'libraries/helper.php';

class spider_lex_uz
{
    public function __construct(){
    }

    //
    //div[contains(@class,'const-order__item-inside')]//div[contains(@class,'lx_link')]/@onclick

    public function harvest(string $url, string $path) : array
    {
        $xph = new XPathHelper($url);
        return $xph->queryEvaluateItems(
            $path
        );
    }

    public function getFile(array $item): bool
    {

        $fileUrls = $item['file_urls'];
        $outputDir = 'output/lex.uz/'.$GLOBALS['id'];

        if (!is_dir($outputDir)) {
            if (!@mkdir($outputDir, 0775, true)) {
                if (!is_dir($outputDir) || !is_writable($outputDir)) {
                    echo 'Error: Failed to create or access output directory: ' . $outputDir . '. Check permissions.' . PHP_EOL;
                    return false;
                }
            }
        } elseif (!is_writable($outputDir)) {
            echo 'Error: Output directory is not writable: ' . $outputDir . '. Check permissions.' . PHP_EOL;
            return false;
        }


        $allSuccess = true;

        foreach ($fileUrls as $fileUrl) {
            $path = parse_url($fileUrl, PHP_URL_PATH);

// 2. Get the last segment → "-831827"
            $idWithDash = basename($path);

// 3. Strip leading “-”     → "831827"
            $id = ltrim($idWithDash, '-');

            echo $id;  // 831827

            $outputFilename = $outputDir . '/' . $id.".doc";
            $domain = parse_url($fileUrl, PHP_URL_HOST);
            if (empty($domain)) {
                $domain = parse_url($GLOBALS['base_url'], PHP_URL_HOST);
                $fileUrl = "https://".$domain."/".$fileUrl."?type=doc";
                echo "domain is empty";
            }
            $fileContent = @file_get_contents($fileUrl);

            if ($fileContent === false) {
                echo "Error: Failed to download file from URL: $fileUrl" . PHP_EOL;
                $allSuccess = false;
                continue;
            }

            $bytesWritten = @file_put_contents($outputFilename, $fileContent);
            if ($bytesWritten === false) {
                echo "Error: Failed to save file to $outputFilename" . PHP_EOL;
                $allSuccess = false;
                continue;
            }

            echo "File downloaded and saved successfully as '" . $outputFilename . "' (" . $bytesWritten . " bytes written)." . PHP_EOL;
        }

        return $allSuccess;
    }

}