<?php

function cleanString($input) {
    // Remove spaces and periods
    $cleaned = preg_replace('/[\s\.]+/', '_', $input);
    return $cleaned;
}