<?php
header('Content-Type: text/plain');

echo "PHP Version: " . PHP_VERSION . PHP_EOL;
echo "cURL Loaded: " . (extension_loaded('curl') ? 'YES' : 'NO') . PHP_EOL;
echo "curl_init Available: " . (function_exists('curl_init') ? 'YES' : 'NO') . PHP_EOL;
