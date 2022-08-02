<?php
echo "<pre>" . "ctbDailyNews (" . time()  . "): <br>";
require_once 'sql.php'; // Подключение к MySQL, функции.
// ***************************************************************
// ***************** Блок ежедневной информации ******************
// ***************************************************************
$dayNow = strtotime(date("d.m.Y")); // Текущая дата с временем 00:00 в timesatmp
$hourNow = intval(date("G")); // Час запуска
const UPDATE_HOUR = 7; // Сбор информации и оповещение ежедневно в указанный час.
if (getConfigData("ctbDNLastRunTS") < $dayNow && $hourNow >= UPDATE_HOUR) {
    // Переделать: Функции должны возвращать массивы данных с конкретными числами
    // Собирать сообщение в текст нужно тут.
    sendMessage(TG_CHAT_ID,
        getFGI() .
        getBTCD() .
        getCurrencyRates());
    setConfigData("ctbDNLastRunTS", time()); // Время последнего запуска в timesatmp );
} else {
    return "";
}
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// +++++++++++++++++++++++ БЛОК ФУНКЦИЙ ++++++++++++++++++++++++++
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
/**
 * @return string
 */
function getFGI(): string
{
    $tmpString = "";
    $connectionError = true;
    for ($i = 1; $i <= 3; $i++) {
        $postData = file_get_contents('https://api.alternative.me/fng/?limit=1');
        if ($postData) {
            $connectionError = false;
            $dataAPIAlternative = json_decode($postData, true);
            $tmpString = "\xF0\x9F\x92\xA1 #dailynews \xF0\x9F\x92\xA1\nFear and Greed Index: " .
                $dataAPIAlternative["data"]["0"]["value"] . " ("
                . $dataAPIAlternative["data"]["0"]["value_classification"] . ")"
                . "\n";
            break;
        }
        sleep(5);
    }
    if ($connectionError) sendServiceMessage("Alternative.me API error (Daily News: https://api.alternative.me/fng/?limit=1):\n"
        . error_get_last()["message"]);
    return $tmpString;
}

/**
 * @return string
 */
function getBTCD(): string
{
    $tmpString = "";
    $connectionError = true;
    $url = 'https://pro-api.coinmarketcap.com/v1/global-metrics/quotes/latest';
    $parameters = [];
    $headers = ['Accept: application/json', 'X-CMC_PRO_API_KEY: ' . COINMARKETCAP_APIKEY];
    echo var_export($headers, true);
    $qs = http_build_query($parameters); // query string encode the parameters
    $request = "$url?$qs"; // create the request URL $request = "{$url}?{$qs}";
    for ($i = 1; $i <= 3; $i++) {
        $curl = curl_init(); // Get cURL resource
        // Set cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => $request,            // set the request URL
            CURLOPT_HTTPHEADER => $headers,     // set the headers
            CURLOPT_RETURNTRANSFER => 1         // ask for raw response instead of bool
        ));
        $response = curl_exec($curl); // Send the request, save the response
        if ($response) {
            $connectionError = false;
            $responseJSON = json_decode($response, true);
            $btcD = $responseJSON['data']['btc_dominance'];
            $difBtcD = $btcD - $responseJSON['data']['btc_dominance_yesterday'];
            $totalMCNow = $responseJSON['data']['quote']['USD']['total_market_cap'];
            $totalMCPercentChange = $responseJSON['data']['quote']['USD']['total_market_cap_yesterday_percentage_change'];
            $mcALTNow = $responseJSON['data']['quote']['USD']['altcoin_market_cap'];
            $mcBTCNow = $totalMCNow - $mcALTNow;
            $mcALTPercentChange = ($mcALTNow * $totalMCPercentChange) / $totalMCNow;
            $mcBTCPercentChange = $totalMCPercentChange - $mcALTPercentChange;
            $tmpString = "\nИндекс доминации: " . number_format($btcD, 2, ',', ' ') . "% ("
                . ($difBtcD > 0 ? "+" : "") . number_format($difBtcD, 1, ',', ' ') . "%)"
                . "\nОбщая капитализация: " . number_format($totalMCNow / 1000000000000, 2, ',', ' ')
                . " трлн.$ (" . ($totalMCPercentChange > 0 ? "+" : "") . number_format($totalMCPercentChange, 1, ',', ' ') . "%)"
                . "\n- BTC: " . number_format($mcBTCNow / 1000000000000, 2, ',', ' ')
                . " трлн.$ (" . ($mcBTCPercentChange > 0 ? "+" : "") . number_format($mcBTCPercentChange, 1, ',', ' ') . "%)"
                . "\n- ALT: " . number_format($mcALTNow / 1000000000000, 2, ',', ' ')
                . " трлн.$ (" . ($mcALTPercentChange > 0 ? "+" : "") . number_format($mcALTPercentChange, 1, ',', ' ') . "%)"
                . "\n";
            break;
        }
        curl_close($curl); // Close request
        sleep(5);
    }
    if ($connectionError) sendServiceMessage("Coinmarketcap API error. (Daily News):\n" . error_get_last()["message"]);
    return $tmpString;
}

/**
 * @return string
 */
function getCurrencyRates(): string
{
    $tmpString = "";
    $connectionError = true;
    for ($i = 1; $i <= 3; $i++) {
        $postData = file_get_contents('https://api.alternative.me/v2/ticker/?limit=2');  // limit = 2 первые две валюты по капитализации BTC и ETH
        if ($postData) {
            $connectionError = false;
            $data = json_decode($postData, true);
            $difBTC24 = $data["data"]["1"]["quotes"]["USD"]["percentage_change_24h"];
            $difBTC7d = $data["data"]["1"]["quotes"]["USD"]["percentage_change_7d"];
            $difETH24 = $data["data"]["1027"]["quotes"]["USD"]["percentage_change_24h"];
            $difETH7d = $data["data"]["1027"]["quotes"]["USD"]["percentage_change_7d"];
            $tmpString = "\nКурс BTCUSD: " . number_format($data["data"]["1"]["quotes"]["USD"]["price"], 0, ',', ' ') . "$"
                . "\nИзменения за 24 часа: " . ($difBTC24 > 0 ? "+" : "")
                . number_format($difBTC24, 1, ',', ' ') . "%"
                . "\nИзменения за 7 дней: " . ($difBTC7d > 0 ? "+" : "")
                . number_format($difBTC7d, 1, ',', ' ') . "%"
                . "\nКурс ETHUSD: " . number_format($data["data"]["1027"]["quotes"]["USD"]["price"], 0, ',', ' ') . "$"
                . "\nИзменения за 24 часа: " . ($difETH24 > 0 ? "+" : "")
                . number_format($difETH24, 1, ',', ' ') . "%"
                . "\nИзменения за 7 дней: " . ($difETH7d > 0 ? "+" : "")
                . number_format($difETH7d, 1, ',', ' ') . "%"
                . "\n";
            break;
        } else {
            sleep(5);
        }
    }
    if ($connectionError) sendServiceMessage("Alternative.me API Error (partDailyNews: https://api.alternative.me/v2/ticker/?limit=2): " . error_get_last()["message"]);
    return $tmpString;
}