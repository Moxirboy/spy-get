<?php
require 'spider_fetchers/spider_controller.php';

$allWords = [];
$processedPrefixes = [];
class spider_sahifa_tj extends spider_controller
{
    private $MAX_DEPTH;
    private $REQUEST_DELAY_SECONDS;
    private $OUTPUT_FILENAME;
    public function __construct($base_path = '',$MAX_DEPTH,$REQUEST_DELAY_SECONDS,$OUTPUT_FILENAME){
        parent::__construct('https://sahifa_tj', $base_path);
         $this->MAX_DEPTH = $MAX_DEPTH;
         $this->REQUEST_DELAY_SECONDS = $REQUEST_DELAY_SECONDS;
         $this->OUTPUT_FILENAME = $OUTPUT_FILENAME;
    }



    private function getTokens(XPathHelper $xph) : array {
        $tokens = [
            "__VIEWSTATE" => $xph->queryEvaluate("//input[contains(@id,'__VIEWSTATE')]/@value"),
            "__VIEWSTATEGENERATOR" => $xph->queryEvaluate("//input[contains(@id,'__VIEWSTATEGENERATOR')]/@value"),
            "__EVENTVALIDATION" => $xph->queryEvaluate("//input[contains(@id,'__EVENTVALIDATION')]/@value"),
        ];

        return $tokens;
    }

    public function lookUpTableUrl($lang): string
    {
        $langTable = [
            'uz' =>"https://sahifa.tj/EmployeeList/EmployeeList5.asmx/FetchEmailList",
            'rus'=>"https://sahifa.tj/EmployeeList/EmployeeList.asmx/FetchEmailList",
            'tj' =>"https://sahifa.tj/EmployeeList/EmployeeList2.asmx/FetchEmailList",
            'kg' =>"https://sahifa.tj/EmployeeList/EmployeeList7.asmx/FetchEmailList",
            'kz' =>"https://sahifa.tj/EmployeeList/EmployeeList9.asmx/FetchEmailList",
            'tt' =>"https://sahifa.tj/EmployeeList/EmployeeList11.asmx/FetchEmailList",
            'en' =>""
        ];
        return $langTable[$lang];
    }

    public function getWords($lang, $offset, $length) {
        $langs = [
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
        return file_get_contents("output/look-".$langs[$lang].".txt",offset: $offset, length: $length);
    }


    public function translationTableUrl($lang): string
    {
        $langTable = [
            'uz-rus' =>"https://sahifa.tj/uzbeksko_russkij.aspx",
            'rus-uz' =>"https://sahifa.tj/russko_uzbekskij.aspx",
            'tj-rus' =>"https://sahifa.tj/tadzhiksko_russkij.aspx",
            'rus-tj' =>"https://sahifa.tj/russko_tadzhikskij.aspx",
            'kg-rus' =>"https://sahifa.tj/kirgizsko_russkij.aspx",
            'rus-kg' =>"https://sahifa.tj/russko_kirgizskij.aspx",
            'kz-rus' =>"https://sahifa.tj/kazakhsko_russkij.aspx",
            'rus-kz' =>"https://sahifa.tj/russko_kazakhskij.aspx",
            'tt-rus' =>"https://sahifa.tj/tatarsko_russkij.aspx",
            'rus-tt' =>"https://sahifa.tj/russko_tatarskij.aspx",
        ];
        return $langTable[$lang];
    }

    public function lookUp($lang)
    {
        global $allWords; // Use the global $allWords array

        // Ensure $allWords is initialized as an array if it's not already.
        // The global declaration $allWords = []; outside the class should handle initial setup.
        if (!is_array($allWords)) {
            $allWords = [];
            echo "Notice: Global \$allWords was not an array. Re-initialized as empty array.\n";
        } else {
            // If the function is called multiple times for different languages,
            // ensure $allWords is clear at the beginning of a new language processing.
            $allWords = [];
            echo "Notice: Global \$allWords cleared at the beginning of lookUp for language '{$lang}'.\n";
        }


        $initialChars = $this->getLangAlphabet($lang);

        if (empty($initialChars)) {
            echo "No initial characters to process for language: '$lang'.\n";
            if (!empty($this->OUTPUT_FILENAME)) {
                if (file_put_contents($this->OUTPUT_FILENAME, "") !== false) {
                    echo "Initialized empty output file (or cleared existing): " . $this->OUTPUT_FILENAME . "\n";
                } else {
                    echo "Error: Could not initialize/clear output file: " . $this->OUTPUT_FILENAME . "\n";
                }
            }
            return;
        }

        echo "--- Starting scrape for language: $lang ---\n";
        $totalWordsSavedThisRun = 0; // To keep track of words if combining files later is desired.

        foreach ($initialChars as $char) {
            // $allWords should be empty at the start of this iteration due to clearing in the previous iteration
            // or at the beginning of the function for the first character.
            echo "\n--- Processing initial character: '$char' ---\n";

            $this->recursiveScrape($char, 1, $lang);

            echo "--- Processing for character '$char' complete. Saving current results. ---\n";

            if (!empty($allWords)) {
                $sortedWords = array_keys($allWords);
                sort($sortedWords);
                $fileContent = implode("\n", $sortedWords);
                $currentBatchCount = count($sortedWords);

                 if (file_put_contents($this->OUTPUT_FILENAME, $fileContent . "\n", FILE_APPEND) !== false) {
                     echo "Successfully appended " . $currentBatchCount . " unique words/phrases (for char '$char') to " . $this->OUTPUT_FILENAME . "\n";
                 } else {
                     echo "Error: Could not append to " . $this->OUTPUT_FILENAME . " after processing character '$char'.\n";
                 }

            } else {
                echo "No words found for character '$char' in this iteration.\n";
                // If overwriting, you might want to clear the file if no words are found for this char.
                // Or leave it as is (containing words from the previous char).
                // Current logic: if $allWords is empty, no file operation happens here, so file retains last content.
                // To ensure it's overwritten with empty if no words for this char:
                // file_put_contents($this->OUTPUT_FILENAME, "");
                // echo "Output file " . $this->OUTPUT_FILENAME . " now reflects no words for char '$char' (or is empty).\n";
            }

            // Clear $allWords after saving (or attempting to save) words for the current character.
            echo "Clearing \$allWords array to free memory.\n";
            $allWords = [];

            echo "Sleeping for " . $this->REQUEST_DELAY_SECONDS . " second(s)...\n";
            sleep($this->REQUEST_DELAY_SECONDS);
        }

        echo "\n--- All initial characters processed. Scraping complete. ---\n";

        // Final status message
        // Since $allWords is cleared in each iteration, its count will be 0 here.
        // The file $this->OUTPUT_FILENAME will contain words only from the *last* processed initial character.
        if (file_exists($this->OUTPUT_FILENAME)) {
            $fileContentCheck = file_get_contents($this->OUTPUT_FILENAME);
            $linesInFile = ($fileContentCheck === false || empty(trim($fileContentCheck))) ? 0 : count(explode("\n", trim($fileContentCheck)));
            echo "Process finished. Output file " . $this->OUTPUT_FILENAME . " contains " . $linesInFile . " words/phrases from the last processed initial character.\n";
            echo "Note: Due to memory optimization, \$allWords array is cleared after each character, so it's empty now.\n";
            // echo "Total words processed and saved in batches (sum of counts per batch): " . $totalWordsSavedThisRun . "\n";
        } else {
            echo "Process finished. Output file " . $this->OUTPUT_FILENAME . " was not created or an error occurred.\n";
        }
        // $allWords will be empty here, so count(array_keys($allWords)) would be 0.
    }




    private function fetchSnippets(string $query,string $lang): array
    {
        global $allWords, $processedPrefixes;
        $HEADERS = [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Accept-Language: en-US,en;q=0.9',
            'Content-Type: application/json; charset=UTF-8',
            'Origin: https://sahifa.tj',
            'Referer: https://sahifa.tj/uzbeksko_russkij.aspx',
            'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
            'X-Requested-With: XMLHttpRequest',
        ];
        $url = $this->lookUpTableUrl($lang);
        $processedPrefixes[$query] = true;

        $payload = json_encode(['mail' => $query]);
        $GLOBALS['curlopts'] = [
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_POSTFIELDS=>$payload,
            CURLOPT_HTTPHEADER=>$HEADERS,
            CURLOPT_POST=>true,
        ];
        $xph = new XPathHelper($url);

        $data = json_decode($xph->html, true);

        $snippets = [];
        if (isset($data['d']) && is_array($data['d'])) {
            foreach ($data['d'] as $item) {
                if (isset($item['Email'])) {
                    $snippets[] = $item['Email'];
                    $wordsInSnippet = array_map('trim', explode(',', $item['Email']));
                    foreach ($wordsInSnippet as $word) {
                        $allWords[$word] = true;
                    }
                }
            }
        }
        return $snippets;
    }



    private function recursiveScrape(string $currentPrefix, int $currentDepth,string $lang)
    {
        global $allWords, $processedPrefixes;

        if ($currentDepth > $this->MAX_DEPTH) {
            return;
        }

        if (isset($processedPrefixes[$currentPrefix])) {
            return;
        }

        echo "Fetching for prefix: '{$currentPrefix}' (Depth: {$currentDepth})\n";
        $snippets = $this->fetchSnippets($currentPrefix,$lang);

        $prefixesToExplore = [];

        foreach ($snippets as $snippet) {
            if (strlen($snippet) > strlen($currentPrefix)) {
                $nextCharIndex = strlen($currentPrefix);
                if (isset($snippet[$nextCharIndex])) {
                    $newPrefix = $currentPrefix . $snippet[$nextCharIndex];
                    if (!isset($processedPrefixes[$newPrefix])) {
                        $prefixesToExplore[$newPrefix] = true;
                    }
                }
            }
        }

        if ($currentDepth < $this->MAX_DEPTH) {
            echo "  Adding alphabetical combinations for '{$currentPrefix}'...\n";
            foreach ($this->getLangAlphabet($lang) as $char) {
                $newPrefix = $currentPrefix . $char;
                if (!isset($processedPrefixes[$newPrefix])) {
                    $prefixesToExplore[$newPrefix] = true;
                }
            }
        }

        if ($currentDepth < $this->MAX_DEPTH && substr($currentPrefix, -1) !== ' ') {
            $spacePrefix = $currentPrefix . ' ';
            if (!isset($processedPrefixes[$spacePrefix])) {
                echo "  Adding space combination for '{$currentPrefix}'...\n";
                $prefixesToExplore[$spacePrefix] = true;
            }
        }

        $sortedPrefixes = array_keys($prefixesToExplore);
        sort($sortedPrefixes);

        foreach ($sortedPrefixes as $nextPrefix) {
            sleep($this->REQUEST_DELAY_SECONDS);
            $this->recursiveScrape($nextPrefix, $currentDepth + 1,$lang);
        }
    }


    public function getTranslations($url, $word) : array{
        $xph = new XPathHelper($url);
        $tokens = $this->getTokens($xph);
        $PostFields = $tokens;
        $PostFields['ctl00$ContentPlaceHolder1$tbAuto']= $word;
        $PostFields['ctl00$ContentPlaceHolder1$Button2'] =  "Перевод";
        $headers = [
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'accept-language: en-US,en;q=0.9',
            'cache-control: max-age=0',
            'content-type: application/x-www-form-urlencoded',
            'origin: https://sahifa.tj',
            'priority: u=0, i',
            'referer: https://sahifa.tj/uzbeksko_russkij.aspx',
            'sec-ch-ua: "Not.A/Brand";v="99", "Chromium";v="136"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Linux"',
            'sec-fetch-dest: document',
            'sec-fetch-mode: navigate',
            'sec-fetch-site: same-origin',
            'sec-fetch-user: ?1',
            'upgrade-insecure-requests: 1',
            'user-agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
        ];
        $GLOBALS['curlopts'] = [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST =>1,
            CURLOPT_POSTFIELDS =>http_build_query($PostFields),
        ];
        $xph = new XPathHelper($url);
        $words = $xph->queryEvaluateItems("//span[contains(@id,'ContentPlaceHolder1_Label1')]/p");
        return $words;
    }

    public function saveTranslations($lang,$target, $words) {
        if (empty($words)){
            echo "empty words";
            return ;
        }
        $item = [
            'lang'=>$lang,
            'source_word' =>$target,
            'target_word' =>$words,
        ];
        $json = json_encode($item)."\n";
        if (!file_put_contents($this->OUTPUT_FILENAME, $json, FILE_APPEND | LOCK_EX) !== false) {
            echo "UnSuccessfully appended strings  " . $target . "\n";
        }
    }

}