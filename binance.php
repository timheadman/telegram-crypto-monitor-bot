<?php
// xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
// xxxxxxxxxxx BINANCE API FUNCTIONS xxxxxxxxxxx
// xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

/**
 * Возвращает текущую цену конкретной пары.
 * https://stackoverflow.com/questions/65864645/how-to-use-binance-api-simple-get-price-by-ticker
 * @param string $symbol Любой символ из существующих, функция пробует составить пар математически из нескольких запросов.
 * @return float
 */
function getCurrentPrice(string $symbol): float
{
    $symbol = strtoupper($symbol);
    $symbolPieces = explode("/", $symbol);
    switch (count($symbolPieces)) {
        case 1:
            return getExistingPrice($symbol);
        case 2:
            $price0 = getExistingPrice($symbolPieces[0] . "USDT");
            $price1 = getExistingPrice($symbolPieces[1] . "USDT");
            return $price0 / $price1;
        default:
            return 0;
    }
}

/**
 * Возвращает текущую цену конкретной существующей пары.
 * Вспомогательная функция к getCurrentPrice.
 * https://stackoverflow.com/questions/65864645/how-to-use-binance-api-simple-get-price-by-ticker
 * @param string $symbol Trade symbol (ex. "BTCUSDT")
 * @return float
 */
function getExistingPrice(string $symbol): float
{
    /* EXAMPLE 
   https://api.binance.com/api/v3/ticker/price?symbol=BTCUSDT
   */
    $symbol = strtoupper($symbol);
    $urlBinanceAPI = 'https://api.binance.com/api/v3/ticker/price?symbol=' . strtoupper($symbol);
    //sendServiceMessage($urlBinanceAPI);
    $connectionError = true;
    $price = -1;
    for ($i = 1; $i <= 3; $i++) {
        $postData = file_get_contents($urlBinanceAPI); // Разобраться с CURL !!! сервер выдает конкретную ошибку, а данный оператор выдает false в итоге при ошибочном запросе, как будто страница не доступна, нужно самому разбирать ответ
        //sendServiceMessage(var_export($postData, true));
        if ($postData) {
            $jsonPostData = json_decode($postData, true);
            //sendServiceMessage(var_export($jsonPostData, true));
            $price = (float)$jsonPostData['price'];
            $connectionError = false;
            break;
        }
        if ($i < 3) sleep(1);
    }
    if ($connectionError) {
        sendServiceMessage("binance.php\n" . $urlBinanceAPI . "\n" . error_get_last()["message"]);
    }
    return $price;
}

/**
 * Возвращает историческое значение конкретной пары по заданным условиям.
 * https://developers.binance.com/docs/binance-api/spot-detail/rest-api#klinecandlestick-data
 * @param string $symbol Trade symbol (ex. "BTCUSDT")
 * @param int $startTime UNIX time (0 - default)
 * @param int $level 1 - Open, 2 - High, 3 - Low, 4 - Close, 5 - Average (default)
 * @return float
 */
function getHistoryPrice(string $symbol, int $startTime = 0, int $level = 5): float
{
    /* EXAMPLE 
    https://api.binance.com/api/v3/klines?symbol=ADAUSDT&interval=1m&limit=1&startTime=1637826840000&endTime=1637826899999
    [
        1499040000000,      // Open time [0]
        "0.01634790",       // Open [1]
        "0.80000000",       // High [2]
        "0.01575800",       // Low [3]
        "0.01577100",       // Close [4]
        "148976.11427815",  // Volume
        1499644799999,      // Close time
        "2434.19055334",    // Quote asset volume
        308                // Number of trades
      ] 
*/
    $symbol = strtoupper($symbol);
    $interval = '1m';
    $limit = 1;
    $price = 0;
    if ($startTime == 0)
        $urlBinanceAPI = 'https://api.binance.com/api/v3/klines?symbol=' . $symbol . '&interval=' . $interval .
            '&limit=' . $limit;
    else {
        $startTime_ = intdiv($startTime, 60) * 60000;
        $endTime_ = $startTime_ + 59999;
        $urlBinanceAPI = 'https://api.binance.com/api/v3/klines?symbol=' . $symbol . '&interval=' . $interval .
            '&limit=' . $limit . '&startTime=' . $startTime_ . '&endTime=' . $endTime_;
    }
    //sendServiceMessage($urlBinanceAPI);
    $connectionError = true;
    for ($i = 1; $i <= 3; $i++) {
        $postData = file_get_contents($urlBinanceAPI);
        if ($postData) {
            $jsonPostData = json_decode($postData, true);
            if ($level <= 4 && $level > 0)
                $price = (float)$jsonPostData[0][$level];
            else $price = ((float)$jsonPostData[0][2] + (float)$jsonPostData[0][3]) / 2;
            $connectionError = false;
            break;
        }
        if ($i < 3) sleep(1);
    }
    if ($connectionError) {
        sendServiceMessage("binance.php\n" . $urlBinanceAPI . "\n" . error_get_last()["message"]);
        return false;
    } else {
        return $price;
    }
}

/**
 * Возвращает все пары биржи Binance в массив.
 * @return array
 */
function getAllCurrentPrice(): array
{
    $urlBinanceAPI = 'https://api.binance.com/api/v3/ticker/price';
    $arrData[] = 0;
    for ($i = 1; $i <= 3; $i++) {
        echo $i;
        $postData = file_get_contents($urlBinanceAPI);
        if ($postData) {
            $arrData = json_decode($postData, true);
            return $arrData;
        } elseif ($i == 3) {
            sendServiceMessage("binance.php\n" . $urlBinanceAPI . "\n" . error_get_last()["message"]);
        }
        sleep(1);
    }
    return array();
}

/**
 * Округляем цену по логике веса цены.
 * @param float $price
 * @return float
 */
function roundPrice(float $price): float
{
    return round($price, ($price > 1) ? 2 : 8);
}

/**
 * конвертируем в строку округленую цену.
 * @param float $price
 * @return string
 */
function printPrice(float $price): string
{
    return number_format($price, ($price > 1) ? 2 : 8);
}
