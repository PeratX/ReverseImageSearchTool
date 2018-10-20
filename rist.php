<?php

/**
 * ReverseImageSearchTool
 * 
 * Copyright (C) 2018 PeratX
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

include "sf/autoload.php";

use iTXTech\SimpleFramework\Console\Logger;
use iTXTech\SimpleFramework\Util\Config;
use iTXTech\SimpleFramework\Util\Curl;

const VERSION = "1.0.0";

Initializer::initTerminal(true);

Logger::info("ReverseImageSearchTool " . VERSION);

if (!extension_loaded("pHash")) {
    Logger::error("Cannot find pHash extension");
    Logger::info("You can get it on https://github.com/aodzip/php-phash");
    exit(1);
}

$config = new Config("config.json", Config::JSON, [
    "api_server" => "",
    "interval" => 500,
    "timeout" => 5000,
]);
$apiServer = $config->get("api_server");
Logger::info("RiseFront server: " . $apiServer);


$file = $argv[1];
if(!file_exists($file)){
    Logger::error("File not exist");
    exit(1);
}
$phash = phash($file);
Logger::info("pHash for $file is $phash");

Logger::info("Submitting search task to RiseFront server");
$curl = new Curl();
$response = $curl->setUrl($apiServer . "/search")
    ->setGet(["hash" => $phash])
    ->returnHeader(false)
    ->exec();
if ($response === false) {
    Logger::error("Cannot connect to RiseFront server");
    exit(1);
}
$result = json_decode($response, true);
if ($result["status"] !== "ok") {
    Logger::error("Task submition failed: " . $result["msg"]);
    exit(1);
}

$interval = $config->get("interval");
$timeout = $config->get("timeout");
Logger::info("Check every {$interval}ms with {$timeout}ms timeout");
$until = microtime(true) + $timeout;

while (time() < $until) {
    $curl = new Curl();
    $response = $curl->setUrl($apiServer . "/result")
        ->setGet(["hash" => $phash])
        ->returnHeader(false)
        ->exec();
    if ($response === false) {
        Logger::error("Cannot connect to RiseFront server");
        exit(1);
    }
    $result = json_decode($response, true);
    if ($result["status"] === "ok") {
        var_dump($result);
        break;
    }

    Logger::info("Checking");

    usleep($interval * 1000);
}
