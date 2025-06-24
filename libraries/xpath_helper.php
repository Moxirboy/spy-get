<?php


/**
 * This function takes a list of selectors and execute each of them until it finds the first non Empty value
 * @param XPathHelper $xph
 * @param array $selectors (xpath selectors list)
 * @param bool $nonZero should the result be also tested for non-zero values?
 * @return mixed (null if no value is found; string otherwise)
 */
function firstNonEmpty($xph, array $selectors, $nonZero = false)
{
    foreach ($selectors as $selector) {
        $rules = is_string($selector) ? [$selector] : $selector;
        $results = [];
        foreach ($rules as $rule) {
            $aux = trim($xph->queryEvaluate($rule));
            if (!empty($aux)) {
                if ($nonZero) {
                    if (!function_exists('extract_first_number')) {
                        require_once 'sniffer_helper.php';
                    }
                    if (!extract_first_number($aux)) {
                        continue;
                    }
                }
                $results[] = $aux;
            }
        }
        if (sizeof($results) == sizeof($rules)) {
            return implode(' ', $results);
        }
    }

    return null;
}

/**
 * This function takes a list of selectors,execute each of them and returns all valid results
 * @param XPathHelper $xph
 * @param array $selectors (xpath selectors list)
 * @param bool $nonZero should the result be also tested for non-zero values?
 * @return mixed (null if no value is found; string otherwise)
 */
function allNonEmpty($xph, array $selectors, $nonZero = false)
{
    $results = [];
    foreach ($selectors as $selector) {
        $rules = is_string($selector) ? [$selector] : $selector;
        foreach ($rules as $rule) {
            $aux = $xph->queryEvaluateItems($rule);
            if (!empty($aux)) {
                if ($nonZero) {
                    if (!function_exists('extract_first_number')) {
                        require_once 'sniffer_helper.php';
                    }
                    if (!extract_first_number($aux)) {
                        continue;
                    }
                }
                $results = $results + $aux;
            }
        }
    }

    return array_unique($results);
}
