<?php

/**
 * Writes an array of PHP associative arrays (or objects) to a JSONL file.
 *
 * @param string $filePath The path to the JSONL file.
 * @param array $data An array of items to write. Each item should be
 * an associative array or an object that can be json_encoded.
 * @param string $mode 'w' to overwrite the file, 'a' to append.
 * @return bool True on success, false on failure to open the file.
 */
function writeToJsonl(string $filePath, array $data, string $mode = 'w'): bool
{
    if ($mode !== 'w' && $mode !== 'a') {
        error_log("Invalid mode specified for writeToJsonl. Use 'w' or 'a'.");
        return false;
    }

    $fileHandle = @fopen($filePath, $mode);

    if ($fileHandle === false) {
        error_log("Failed to open file for writing: " . $filePath);
        return false;
    }

        $jsonString = json_encode($data);

        if ($jsonString === false) {
            error_log("Failed to encode item to JSON: " . json_last_error_msg());
        }

        fwrite($fileHandle, $jsonString . PHP_EOL);

    fclose($fileHandle);
    return true;
}