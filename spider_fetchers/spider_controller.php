<?php
require 'libraries/XPathHelper.php';
require 'libraries/uuid.php';
require 'libraries/helper.php';

class spider_controller
{
    public function __construct($base_url = 'http://www.uzmovie.uz',$base_path = 'uzmovie.uz'){
        $GLOBALS['base_url'] = $base_url;
        $GLOBALS['movie_path'] = $base_path;

    }

    public function countLineFile($filepath) {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return "File not found or not readable.";
        }
        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Or without flags to count all
        if ($lines === false) {
            return "Could not read file into array.";
        }
        return count($lines);
    }

    public function readSpecificLines($filepath, $start_line, $num_lines_to_read) {
        $lines_read = [];
        if ($start_line < 1 || $num_lines_to_read < 0) {
            return "Error: Start line must be 1 or greater, and num_lines_to_read must be non-negative.";
        }
        if ($num_lines_to_read == 0) {
            return [];
        }

        if (!file_exists($filepath) || !is_readable($filepath)) {
            return "Error: File not found or not readable.";
        }

        $handle = fopen($filepath, "r");
        if ($handle) {
            $current_line_number = 0;
            while (($line_content = fgets($handle)) !== false) {
                $current_line_number++;
                if ($current_line_number >= $start_line) {
                    if (count($lines_read) < $num_lines_to_read) {
                        $lines_read[] = rtrim($line_content, "\r\n"); // rtrim to remove newline chars
                    } else {
                        break; // We have read the required number of lines
                    }
                }
            }
            fclose($handle);
            return $lines_read;
        } else {
            return "Error: Could not open the file.";
        }
    }

    public function getNumberOfPages(string $url) : int {
        $xph = new XPathHelper($url);
        $numberOfPages = $xph->queryEvaluate("//span[@class='nav_ext']/following-sibling::a[1]/text()");
        $numberInt = intval($numberOfPages);
        return $numberInt;
    }
    public function harvest(string $url) : array {
        $xph = new XPathHelper($url);
        $res = $xph->queryEvaluateMany("//div[contains(@class,'shortstory-in')]//div[contains(@class,'short-content')]//a/@href");
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
        return $xph->queryEvaluateMany("//div[@class='kontaiher']/a/@href");
    }

    public function getMovieDetails(string $url,bool $sub=false) :array {
        $xph = new XPathHelper($url);
        $hasMany = $this->hasMany($xph);
        $item = [
            'movie.id'=>generateUuidV4(),
            'movie.url'=>$url,
            'movie.name'=> $xph->queryEvaluate("//div[contains(@class,'finfo-text')]/b")??"",
            'movie.year'=>$xph->queryEvaluate("//span[contains(@class,'film-rip')]") ?? "",
            'movie.length'=>$xph->queryEvaluate("//div[@class='finfo-title' and normalize-space(text())='Davomiyligi']/following-sibling::div[@class='finfo-text']") ?? "",
            'movie.series'=>$hasMany,
            'movie.videoUrl' => $xph->queryEvaluate("//div[contains(@id,'online3')]//a/@href") ?? '',
            'movie.audioPath' => $GLOBALS['movie_path']."/".$xph->queryEvaluate("//span[contains(@class,'film-rip')]")."/".str_replace(" ", "_", $xph->queryEvaluate("//div[contains(@class,'finfo-text')]/b")) ?? "",
        ];
        if ($hasMany && !$sub) {
            $urls = $this->getSeries($xph);
            $series = [];
            foreach ($urls as $url) {
                $serie = $this->getMovieDetails($url, true);
                $series[] = $serie;
            }
            $item['movie.series'] = $series;
        }
        return $item;
    }
    protected function urlCleaner(string $url) : string {
        $marker = "https://";
        $position = strpos($url, $marker);
        if ($position !== false) {
            $url = substr($url, $position);
        }
        return $url;
    }

    private function mb_str_split($string, $split_length = 1, $encoding = null) {
        if (null === $string) {
            return false;
        }
        if ($split_length < 1) {
            return false;
        }
        if (null === $encoding) {
            $encoding = mb_internal_encoding();
        }
        $result = [];
        $length = mb_strlen($string, $encoding);
        for ($i = 0; $i < $length; $i += $split_length) {
            $result[] = mb_substr($string, $i, $split_length, $encoding);
        }
        return $result;
    }
    protected function getLangAlphabet($lang): array{
        $result = [];
        switch ($lang) {
            case 'en':
                $result = range('a', 'z');
                break;

            case 'rus':
                // Russian alphabet (33 letters, including ё)
                $result = $this->mb_str_split('абвгдеёжзийклмнопрстуфхцчшщъыьэюя', 1, 'UTF-8');
                break;

            case 'uz':
                // Uzbek Cyrillic alphabet (35 letters)
                // Includes Russian alphabet + ў, қ, ғ, ҳ
                $result = $this->mb_str_split('абвгдеёжзийклмнопрстуфхцчшщъыьэюяўқғҳ', 1, 'UTF-8');
                // If you need Uzbek Latin:
                // $languageAlphabets[$langCode] = array_merge(range('a', 'z'), ['oʻ', 'gʻ', 'sh', 'ch']); // Simplified, order might need adjustment and more chars
                break;

            case 'tj':
                // Tajik Cyrillic alphabet (35 letters)
                // Includes Russian alphabet (excluding щ, ь) + ғ, ӣ, қ, ӯ, ҳ, ҷ
                $result = $this->mb_str_split('абвгғдеёжзиӣйкқлмнопрстуӯфхҳчҷшъэюя', 1, 'UTF-8');
                // Note: Tajik alphabet has specific exclusions and additions compared to Russian.
                // Common full list: а б в г ғ д е ё ж з и ӣ й к қ л м н о п р с т у ӯ ф х ҳ ч ҷ ш щ ъ ы ь э ю я
                // Re-evaluating based on common representation:
                $result = ['а', 'б', 'в', 'г', 'ғ', 'д', 'е', 'ё', 'ж', 'з', 'и', 'ӣ', 'й', 'к', 'қ', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ӯ', 'ф', 'х', 'ҳ', 'ч', 'ҷ', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я'];
                break;

            case 'kg':
                // Kyrgyz Cyrillic alphabet (36 letters)
                // Includes Russian alphabet + ң, ү, ө
                $result = $this->mb_str_split('абвгдеёжзийклмнңоөпрстуүфхцчшщъыьэюя', 1, 'UTF-8');
                break;

            case 'kz':
                // Kazakh Cyrillic alphabet (42 letters)
                // Includes Russian alphabet + ә, і, ң, ғ, ү, ұ, қ, ө, һ
                $result = $this->mb_str_split('аәбвгғдеёжзийкқлмнңоөпрстуұүфхһцчшщъыіьэюя', 1, 'UTF-8');
                break;

            case 'tt':
                // Tatar Cyrillic alphabet (39 letters)
                // Includes Russian alphabet + ә, ө, ү, җ, ң, һ
                $result = $this->mb_str_split('аәбвгдеёжҗзийклмнңоөпрстуүфхһцчшщъыьэюя', 1, 'UTF-8');
                break;

            default:
                // Fallback for any other language codes not explicitly defined
                $result = []; // Or null, or you could default to English 'a'-'z'
                break;
        }
        return $result;
    }

    public function getFile(array $item): bool
    {
        $item['file_url'] = $item['file_urls'];
        if (!isset($item['file_url']) || (is_array($item['file_url']) && empty($item['file_url'])) || (is_string($item['file_url']) && trim($item['file_url']) === '')) {
            echo 'Error: file_url is missing or invalid in the input item.' . PHP_EOL;
            return false;
        }

        $fileUrls = is_array($item['file_url']) ? $item['file_url'] : [$item['file_url']];
        $outputDir = 'output/'.$GLOBALS['base_url'];

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
            if (!is_string($fileUrl) || trim($fileUrl) === '') {
                echo "Skipping invalid file URL: " . var_export($fileUrl, true) . PHP_EOL;
                $allSuccess = false;
                continue;
            }
            $baseFilename=cleanString($item['name']);
            $baseUrls = $this->processUrlParts($item['base_url'],$fileUrl);
            $baseFilename = $baseFilename."_".$baseUrls["final_string"];
            $baseFilename = strtok($baseFilename, '?#');


            if (empty($baseFilename) || $baseFilename === '.' || $baseFilename === '..' || trim($baseFilename) === '') {
                $titleSanitized = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $item['title'] ?? 'untitled');
                $titleSanitized = trim($titleSanitized, '_');
                $baseFilename = !empty($titleSanitized) ? $titleSanitized : 'downloaded_file_' . time();
            }

            $outputFilename = $outputDir . '/' . $baseFilename;
            $domain = parse_url($fileUrl, PHP_URL_HOST);
            if (empty($domain)) {
                $domain = parse_url($item['base_url'], PHP_URL_HOST);
                $fileUrl = "https://".$domain."/".$fileUrl;
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

    public function processUrlParts(string $baseUrl, string $filePath): array
    {
        // 1. Construct the Full URL (handles extra slashes)
        $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($filePath, '/');

        // 2. Get the last two segments of the path
        $pathParts = explode('/', $filePath);
        $lastTwoSegmentsArray = array_slice($pathParts, -2);
        $extractedPart = implode('/', $lastTwoSegmentsArray);

        // 3. Replace '/' with '-' in the extracted part
        $finalString = str_replace('/', '-', $extractedPart);

        // Return all results in an associative array
        return [
            'full_url'       => $fullUrl,
            'extracted_part' => $extractedPart,
            'final_string'   => $finalString,
        ];
    }
}