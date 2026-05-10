<?php
$ch = curl_init('https://www.google.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
if($result) {
    echo "Connection to the internet is WORKING.";
} else {
    echo "Connection FAILED: " . curl_error($ch);
}
curl_close($ch);
?>