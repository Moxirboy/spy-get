<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once 'url_helper.php';
require_once 'title_matcher_helper.php';

/**
 * Determines if the html string passed is empty
 *
 * @param mixed $html
 * @return bool
 */
#test git
function html_has_no_text($html)
{
    if (! $html) {
        //echo "html is false\n";
        return true; // has no text
    }
    $html = strip_tags($html, '<a>');
    if (empty($html)) {
        //echo "html is empty\n";
        return true; // has no text
    }

    return false; // has text
}

/**
 * It will iterate into a array and return the first not null value or null, if all values are null
 *
 * @return string
 */
function first_not_null(array $options)
{
    foreach ($options as $value) {
        if ($value) {
            return $value;
        }
    }

    return null;
}

/**
 * It will search for the first regexp match in the haystack and
 * returns string empty, if no match is possible
 *
 * @param string regexp $matchPattern
 * @param string $haystack
 * @return string
 */
function extract_first_match($matchPattern, $haystack)
{
    $result = [];
    preg_match($matchPattern, $haystack, $result);
    if (count($result) > 0) {
        return $result[0];
    }

    return '';

}

/**
 * Extract the first sequence of numbers found on the string
 *
 * @param string $haystack
 * @return string
 */
function extract_first_number($haystack)
{
    if (empty($haystack)) {
        return null;
    }
    $match = preg_replace(['/[^\d., ]/','/[.,] /','/[.,]+$/'], ['','',''], $haystack);
    $match = extract_first_match('/[\d.,]+/', $match);
    if (empty($match)) {
        return null;
    }

    // let's sanize numbers with comma
    $commaPos = strrpos($match, ',');
    $periodPos = strrpos($match, '.');

    // if it's not USD, then the comma could be the decimal point!
    if ($periodPos !== false) { // it has period
        if ($commaPos !== false) { // and also comma
            if ($commaPos > $periodPos) { // non-US standard, let's invert comma and period
                $match = str_replace(['.', ','], ['', '.'], $match);
            } else {
                $match = str_replace(',', '', $match);
            }
        }
    } else { // doesn't have period
        if ($commaPos !== false) { // but has comma
            if ($commaPos === strlen($match) - 4) { // we have something like 1,000 which shoul be 1000
                $match = str_replace(',', '', $match);
            } else {
                $match = str_replace(',', '.', $match);
            }
        }
    }

    return floatval($match);
}

function extract_only_letters_and_numbers($text)
{
    $finalText = trim(preg_replace('/[^a-z0-9]/', '', strtolower($text)));

    return $finalText;
}

function contains_size($sizeText, $sizeToMatch)
{
    $result = [];
    preg_match('/[0-9.,]+/', $sizeText, $result);
    $numbersText = array_map(function ($item) {
        return floatval($item);
    }, $result);
    preg_match('/[0-9.,]+/', $sizeToMatch, $result);
    $numbersToMatch = array_map(function ($item) {
        return floatval($item);
    }, $result);

    return count(array_intersect($numbersText, $numbersToMatch)) == count($numbersToMatch);
}

/**
 * It will search for the first regexp match in the haystack and
 * returns string empty, if no match is possible
 *
 * @param string $bucket
 * @param xpath_wrangler $xpath
 * @param web_client $webclient
 *
 * @return IVerifier
 */
function load_verifier($bucket, $xpath, $webclient)
{
    $candidates = [];
    if(empty($xpath->url) && ! empty($webclient->url) && ! empty($webclient->response_body)) {
        require_once  'libraries/spider_stickybusiness/spider_lib_ag/XPathHelper.php';
        $xpath = XPathHelper::mount($webclient->url, $webclient->response_body);
    }

    if (preg_match('/\s+/', $bucket)) {
        $candidates[] = preg_replace("/\s+/", '_', $bucket);
        $candidates[] = preg_replace("/\s+/", '', $bucket);
    } elseif (preg_match('/-/', $bucket)) {
        $candidates[] = preg_replace('/-/', '', $bucket);
    } else {
        $candidates[] = $bucket;
    }

    $already_loaded = get_included_files();

    $paths = [
         'libraries/spider_stickybusiness/spider_lib_ag/buckets/XXX/XXX.php',
         'libraries/crawly/marketplaces/bucketVerifier/XXX.php',
    ];

    $loaded = false;
    foreach($candidates as $candidate) {
        if (in_array($candidate, $already_loaded)) {
            $loaded = true;

            break;
        }

        foreach($paths as $path) {
            $filename = str_replace('XXX', $candidate, $path);
            if (file_exists($filename)) {
                require_once $filename;
                $loaded = true;

                break 2;
            }
        }
    }

    if ($loaded) {
        if (preg_match('/^(\d.*)$/', $candidate)) { // 3D Cart
            $candidate = '_' . $candidate;
        }

        $class = new ReflectionClass($candidate);
        if ((in_array('IVerifier', $already_loaded) && ! $class->implementsInterface('IVerifier')) || (in_array('Verifier2', $already_loaded) && ! $class->isSubclassOf('Verifier2'))) {
            return null; // verifier doesn't implement what needs to implemented
        }

        return new $candidate($xpath, $webclient);
    }

    return null;
}

/**
 * It will filter a javascript like json string into an actual json.
 *
 * @param string $jsonStr
 * @return IVerifier
 */
function filter_json($jsonStr)
{
    $jsonStr = str_replace("'", '"', $jsonStr);
    $jsonStr = str_replace("\t", '', $jsonStr);
    $jsonStr = str_replace("\n", '', $jsonStr);
    $jsonStr = str_replace("\r", '', $jsonStr);
    $jsonStr = preg_replace('/\s+:/s', ':', $jsonStr);
    $jsonStr = preg_replace("/\s+,/s", ',', $jsonStr);
    $jsonStr = preg_replace('/:\s+/s', ':', $jsonStr);
    $jsonStr = preg_replace("/,\s+/s", ',', $jsonStr);
    $jsonStr = str_replace(':,', ':null,', $jsonStr);
    $jsonStr = str_replace(':}', ':null}', $jsonStr);
    $jsonStr = str_replace(':""', ':null', $jsonStr);

    $jsonStr = preg_replace_callback(
        '/(?<=[{,]).+?(?=:)/',
        function ($matches) {
            $match = $matches[0];
            if (preg_match('/{\s/', $match)) {
                $match = preg_replace('/\s/', '', $match);
                $match = str_replace('{', '{"', $match);

                return "{$match}\"";
            }

            return "\"{$match}\"";

        },
        $jsonStr
    );
    $jsonStr = str_replace('""', '"', $jsonStr);

    return $jsonStr;
}

/**
 * it will search in the string for the opening and closing brackets, and extract the one more open.
 * Example: in the string " var x = new bata({"id":1,products:{"1":"a","2":"a","3":"a","4":"a"}}, {"a","test"}),
 * it will return {"id":1,products:{"1":"a","2":"a","3":"a","4":"a"}}
 * @param string $str
 * @return $json like string, or empty string
 */
function extract_json_from_string($str)
{
    $lengthStr = strlen($str);
    $startIndex = 0;
    $invalid = true;
    //remove the start
    for ($key = 0; $key < $lengthStr; $key++) {
        $value = $str[$key];
        if ($value == '{') {
            $startIndex = $key;

            break;
        }
    }

    $counter = 0;
    $endIndex = $lengthStr - 1;
    for ($key = $startIndex; $key < $lengthStr; $key++) {
        $value = $str[$key];
        if ($value == '"') {
            $key = jump_string_in_string($key + 1, $str);

            continue;
        }

        if ($value == '}') {
            $counter--;
        }

        if ($value == '{') {
            $counter++;
        }

        if ($counter == 0) {
            $endIndex = $key;
            $invalid = false;

            break;
        }
    }

    $json_str = substr($str, $startIndex, ($endIndex - $startIndex) + 1);
    if ($invalid) {
        return '';
    }

    return $json_str;

}

/**
 * The function is meant to try to find the enclosing quotation mark
 *                       123456789
 * Example in the string "a\"bla\"", the $indexStartString should be set to 5
 * and the return will be 9
 * @param int $indexStartString
 * @param string $array_str
 * @return the index of the charecter after the character " not preceded by \
 */
function jump_string_in_string($indexStartString, $array_str)
{
    $lastValue = '';
    $lengthStr = strlen($array_str);
    $keyString = $indexStartString;
    for (; $keyString < $lengthStr; $keyString++) {
        $value = $array_str[$keyString];
        if (($lastValue != '\\') && ($value == '"')) {
            return $keyString;
        }
        $lastValue = $value;
    }

    return $lengthStr - 2;
}

/**
 * It will go foreach of the $linesOfCode array, and try to find the $neddle
 * @param string $neddle
 * @param int $offset
 * @return array, first element index of the string, second element is the actual line
 */
function get_line_of_code_with_string(array $linesOfCode, $neddle, $offset)
{
    if ($offset == -1) {
        return [-1, ''];
    }
    $index = $offset;
    $lengthLines = count($linesOfCode);
    $neddleClean = strtolower(preg_replace('/\s+/', '', $neddle));
    $linesOfCodeClean = preg_replace('/\s+/', '', $linesOfCode);
    while (strpos(strtolower($linesOfCodeClean[$index]), $neddleClean) === false) {
        $index++;
        if ($index >= $lengthLines) {
            return [-1, ''];
        }
    }

    return [$index, $linesOfCode[$index]];
}

/**
 * It will go foreach of the $linesOfCode array, and try to find the $neddle
 * @param string $regex
 * @param int $offset
 * @return array, first element index of the string, second element is the actual line
 */
function get_line_of_code_with_regex(array $linesOfCode, $regex, $offset)
{
    if ($offset == -1) {
        return [-1, ''];
    }
    $index = $offset;
    $lengthLines = count($linesOfCode);
    if ($index >= $lengthLines) {
        return [-1, ''];
    }
    while (preg_match($regex, $linesOfCode[$index]) == false) {
        $index++;
        if ($index >= $lengthLines) {
            return [-1, ''];
        }
    }

    return [$index, $linesOfCode[$index]];
}

/**
 * It will return the left side of a assignment, for example from the string "var x = sdfsf + 2323",
 * it will return "sdfsf + 2323"
 * @param string $lineAssign
 * @return string
 */
function extract_value_assign($lineAssign)
{
    if (strpos($lineAssign, '=') !== false) {
        $result = explode('=', $lineAssign);

        return trim($result[1]);
    }

    return '';
}
if (! function_exists('json_last_error_msg')) {

    function json_last_error_msg()
    {
        static $ERRORS = [
            JSON_ERROR_NONE => 'No error',
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'State mismatch (invalid or malformed JSON)',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX => 'Syntax error',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
        ];

        $error = json_last_error();

        return $ERRORS[$error] ?? 'Unknown error';
    }
}

/**
 * Giving a set of $attributes, generates all possible combinations among them.
 * If $filtering is passed, then attributes not matching that set will be automatically
 * removed from the resulting combination set
 *
 * @param array $attributes List of attributes
 * @param array $options Attribute name/index were all options are stored
 * @param array $filtering Set of attributes which will filter out all non-matching values
 * @return mixed array of combinations if any, FALSE if no combination could be done after
 * filtering being applied
 */
function generate_cartesian_product($attributes, $optionAttribute = 'options', $filtering = null)
{
    if (! array_key_exists('attributes_options', $GLOBALS)) {
        $GLOBALS['attributes_options'] = [];
    }
    if (is_object($filtering) && property_exists($filtering, 'attributes') && property_exists($filtering, 'upc_code')) { // yeah, that the product object
        $product = $filtering;
        $filtering = $filtering->attributes;
    } else {
        $product = null;
    }
    //if number of combinations pass this safety-threshold,
    //we'll return an empty array (we either get all of possible combinations or none of them)
    $threshold = 50000;

    $filteredOutOptions = 0;
    $combinations = [];
    $shouldExit = false;
    $unique = [];
    foreach ($attributes as $attr) {
        $attribute = is_object($attr) ? clone $attr : $attr;

        $content = md5(json_encode($attribute));
        if (in_array($content, $unique)) { // we should not have to identical attribute sets
            continue;
        }
        $unique[] = $content;

        if (is_object($attribute) && ! empty($attribute->{$optionAttribute})) {
            $options = array_values($attribute->{$optionAttribute});
            unset($attribute->{$optionAttribute}); // there's no need to replicate this list on every tuple
        } elseif (is_array($attribute) && ! empty($attribute[$optionAttribute])) {
            $options = array_values($attribute[$optionAttribute]);
            unset($attribute[$optionAttribute]); // there's no need to replicate this list on every tuple
        } elseif (is_string($attribute)) {
            $options = $attribute;
        } else {
            continue;
        }

        $keys = [];
        // label
        if (is_object($attribute) && property_exists($attribute, 'label') && ! empty($attribute->label)) {
            $keys[] = $attribute->label;
        } elseif (is_array($attribute) && isset($attribute['label']) && ! empty($attribute['label'])) {
            $keys[] = $attribute['label'];
        }
        // name
        if (is_object($attribute) && property_exists($attribute, 'name') && ! empty($attribute->name)) {
            $keys[] = $attribute->name;
        } elseif (is_array($attribute) && isset($attribute['name']) && ! empty($attribute['name'])) {
            $keys[] = $attribute['name'];
        }
        // text
        if (is_object($attribute) && property_exists($attribute, 'text') && ! empty($attribute->text)) {
            $keys[] = $attribute->text;
        } elseif (is_array($attribute) && isset($attribute['text']) && ! empty($attribute['text'])) {
            $keys[] = $attribute['text'];
        }

        $values = [];

        foreach($keys as $key) {
            $key = strtolower($key);
            if (! array_key_exists($key, $GLOBALS['attributes_options'])) {
                $GLOBALS['attributes_options'][strtolower($key)] = array_map(function ($item) {
                    return (is_string($item) || is_numeric($item)) ? strtolower($item) : (is_array($item) && isset($item['text]']) ? strtolower($item['text']) : (isset($item->text) ? strtolower($item->text) : ''));
                }, $options);
            }
        }
        if ($shouldExit) { // we're still running just for fulfilling  $GLOBALS['attributes_options']
            continue;
        }

        foreach($keys as $key) {
            if (empty($filtering)) {
                break;
            }
            if (is_object($filtering)) {
                if (property_exists($filtering, $key)) {
                    $values = array_merge($values, explode('|', $filtering->{$key}));
                    if (preg_match('/\s\|\s/', $filtering->{$key})) { // perhaps the | are actually part of the string contents, so let's have the whole thing added as a candidate (CBS-8321)
                        $values[] = $filtering->{$key};
                    }

                    continue;
                }
                if ($keys = @preg_grep('/^' . preg_replace('/[ _]/', '[ _]?', $key) . '$/i', array_keys((array) $filtering))) {
                    $key = array_shift($keys);
                    if (! empty($filtering->{$key})) {
                        $values = array_merge($values, explode('|', $filtering->{$key}));

                        continue;
                    }
                }
            } elseif (is_array($filtering)) {
                if (isset($filtering[$key])) {
                    $values = array_merge($values, explode('|', $filtering[$key]));

                    continue;
                }
                if ($keys = @preg_grep('/^' . preg_replace('/[ _]/', '[ _]?', $key) . '$/i', array_keys($filtering))) {
                    $key = array_shift($keys);
                    if (! empty($filtering[$key])) {
                        $values = array_merge($values, explode('|', $filtering[$key]));

                        continue;
                    }
                }
            } else {
                break;
            }
        }

        if (! empty($values)) {
            $values = array_unique($values);
            for($i = 0;$i < sizeof($options);$i++) {
                $content = is_string($options[$i]) ? $options[$i] : (is_object($options[$i]) && property_exists($options[$i], 'text') ? $options[$i]->text : (is_array($options[$i]) && ! empty($options[$i]['text']) ? $options[$i]['text'] : ''));
                if (empty($content)) {
                    continue;
                }
                $found = 0;
                foreach($values as $value) {
                    if (is_numeric($value)) {
                        if (floatval($value) === floatval($content)) {
                            $found++;

                            break;
                        }
                    } elseif (is_string($value)) {
                        if (trim(cleanWords(strval($value))) === trim(cleanWords(strval($content)))) {
                            $found++;

                            break;
                        }
                    }
                }
                if (! $found) {
                    array_splice($options, $i, 1);
                    $filteredOutOptions++;
                    $i--;
                    if (empty($options)) { // we've removed all options so there's no matchings for this attribute
                        $shouldExit = true;

                        continue 2;
                    }
                }
            }
        }

        unset($option);
        if (! sizeof($combinations) && ! empty($options)) { // it's the first attribute to be inserted
            foreach ($options as $option) {
                if (empty($option)) {
                    continue;
                }
                $tuple = (object) ['attribute' => $attribute, 'option' => $option];
                $combination = (object) ['tuples' => [$tuple]];
                $combinations[] = $combination;
            }
        } elseif (! empty($options)) {
            $nElements = sizeof($combinations);
            $safeCheck = 0;

            if (is_array($options)) {
                if (empty($options)) {
                    continue;
                }

                foreach ($options as $option) {
                    if (empty($option)) {
                        continue;
                    }
                    for($i = 0;$i < $nElements;$i++) {
                        $element = clone $combinations[$i];
                        $element->tuples[] = (object) ['attribute' => $attribute, 'option' => $option];
                        $combinations[] = $element;
                        if (++$safeCheck >= $threshold) {
                            return [];
                        }
                        // break 2;
                    }
                }
            } else {
                for($i = 0;$i < $nElements;$i++) {
                    $element = clone $combinations[$i];
                    $element->tuples[] = (object) ['attribute' => $attribute, 'option' => $options];
                    $combinations[] = $element;
                    if (++$safeCheck >= $threshold) {
                        return [];
                    }
                    // break;
                }
            }
            array_splice($combinations, 0, $nElements); // remove the first nElements of the list
        }
    }
    if ($shouldExit || (empty($combinations) && $filteredOutOptions)) { // there is no matching combination for the given filter
        return false;
    }

    if (sizeof($combinations) > 1024) {
        if (! is_null($product) && ! empty($product->upc_code)) { // this needs attributes in order to short down its cost of execution
            // $id = preg_match('/runTask\/\d+\/([^\/]+)/',$GLOBALS['_SERVER']['REQUEST_URI'],$match) ? $match[1]."::".$product->upc_code : ( preg_match('/run_cron_job\/([^\/]+)/', $GLOBALS['_SERVER']['REQUEST_URI'], $match) ? $match[1]."::".$product->upc_code : false );
            // if ($id) {
            //     send2slack($id, "Product *".$product->upc_code."* lacks attributes and it's causing execution of `".$match[1]."` to take longer than it could take.", 'crawler-live-logs', 'monthly');
            // }
        } else {
            $id = preg_match('/runTask\/\d+\/([^\/]+)/', $GLOBALS['_SERVER']['REQUEST_URI'], $match) ? $match[1] . '-generations' : false;
            if ($id) {
                send2slack($id, 'Command `' . $match[1] . '` is generating too many combinations.', 'crawler-live-logs', 'monthly');
            }
        }

        // if we let this amount of combinations to pass through,
        // that would probably crash the system so it's better to just
        // throw this away
        return [];
    }

    return $combinations;
}
//</editor-fold>

// try this for really fucked up JSON strings.  It works pretty often ... *shrug*
function loose_json_decode($json)
{
    $rgxjson = '%((?:\{[^\{\}\[\]]*\})|(?:\[[^\{\}\[\]]*\]))%';
    $rgxstr = '%("(?:[^"\\\\]*|\\\\\\\\|\\\\"|\\\\)*"|\'(?:[^\'\\\\]*|\\\\\\\\|\\\\\'|\\\\)*\')%';
    $rgxnum = '%^\s*([+-]?(\d+(\.\d*)?|\d*\.\d+)(e[+-]?\d+)?|0x[0-9a-f]+)\s*$%i';
    $rgxchr1 = '%^' . chr(1) . '\\d+' . chr(1) . '$%';
    $rgxchr2 = '%^' . chr(2) . '\\d+' . chr(2) . '$%';
    $chrs = [chr(2),chr(1)];
    $escs = [chr(2) . chr(2),chr(2) . chr(1)];
    $nodes = [];
    $strings = [];

    # escape use of chr(1)
    $json = str_replace($chrs, $escs, $json);

    # parse out existing strings
    $pieces = preg_split($rgxstr, $json, -1, PREG_SPLIT_DELIM_CAPTURE);
    for($i = 1;$i < count($pieces);$i += 2) {
        $strings[] = str_replace($escs, $chrs, str_replace(['\\\\','\\\'','\\"'], ['\\','\'','"'], substr($pieces[$i], 1, -1)));
        $pieces[$i] = chr(2) . (count($strings) - 1) . chr(2);
    }
    $json = implode($pieces);

    # parse json
    while(1) {
        $pieces = preg_split($rgxjson, $json, -1, PREG_SPLIT_DELIM_CAPTURE);
        for($i = 1;$i < count($pieces);$i += 2) {
            $nodes[] = $pieces[$i];
            $pieces[$i] = chr(1) . (count($nodes) - 1) . chr(1);
        }
        $json = implode($pieces);
        if(! preg_match($rgxjson, $json)) {
            break;
        }
    }

    # build associative array
    for($i = 0,$l = count($nodes);$i < $l;$i++) {
        $obj = explode(',', substr($nodes[$i], 1, -1));
        $arr = $nodes[$i][0] == '[';

        if($arr) {
            for($j = 0;$j < count($obj);$j++) {
                if(preg_match($rgxchr1, $obj[$j])) {
                    $obj[$j] = $nodes[+substr($obj[$j], 1, -1)];
                } elseif(preg_match($rgxchr2, $obj[$j])) {
                    $obj[$j] = $strings[+substr($obj[$j], 1, -1)];
                } elseif(preg_match($rgxnum, $obj[$j])) {
                    $obj[$j] = +trim($obj[$j]);
                } else {
                    $obj[$j] = trim(str_replace($escs, $chrs, $obj[$j]));
                }
            }
            $nodes[$i] = $obj;
        } else {
            $data = [];
            for($j = 0;$j < count($obj);$j++) {
                $kv = explode(':', $obj[$j], 2);
                if(preg_match($rgxchr1, $kv[0])) {
                    $kv[0] = $nodes[+substr($kv[0], 1, -1)];
                } elseif(preg_match($rgxchr2, $kv[0])) {
                    $kv[0] = $strings[+substr($kv[0], 1, -1)];
                } elseif(preg_match($rgxnum, $kv[0])) {
                    $kv[0] = +trim($kv[0]);
                } else {
                    $kv[0] = trim(str_replace($escs, $chrs, $kv[0]));
                }
                if(@preg_match($rgxchr1, $kv[1])) {
                    $kv[1] = $nodes[+substr($kv[1], 1, -1)];
                } elseif(@preg_match($rgxchr2, $kv[1])) {
                    $kv[1] = $strings[+substr($kv[1], 1, -1)];
                } elseif(@preg_match($rgxnum, $kv[1])) {
                    $kv[1] = +trim($kv[1]);
                } else {
                    $kv[1] = trim(@str_replace($escs, $chrs, $kv[1]));
                }
                $data[$kv[0]] = $kv[1];
            }
            $nodes[$i] = $data;
        }
    }

    return $nodes[count($nodes) - 1];
}
