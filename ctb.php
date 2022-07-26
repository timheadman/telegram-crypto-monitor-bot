<?php
// https://omarov.net/@CryptoTrackerChannel/ctb.php
echo "<pre>" . "@Crypto Tracker Bot. <br>START: " . time() . "<br>--------------------<br>";
require_once 'sql.php'; // Подключение к MySQL, функции.
require_once 'telegram.php'; // Подключение к Telegramm, функции.
require_once 'binance.php'; // Функции Binance API.

$priceArray = getAllCurrentPrice(); // Запрашиваем в массив все текущие цены с Binance
if (!$priceArray) {
    sendServiceMessage("\xE2\x81\x89 Проблема с получением всех ценовых пар Binance.");
    exit;
}

require_once 'ctbDailyNews.php'; // Блок ежедневная информации по рынку
require_once 'ctbWhaleFollow.php'; // Блок слежения за BTC Whale
// ***************************************************************
// ****** Блок информарования о резком изменении курса BTC *******
// ***************************************************************
const CRITICAL_PERCENTAGE = 10; // Разница в процентах в изменении курса BTC в сутки при превышении которого выводится оповещение
$key = array_search('BTCUSDT', array_column($priceArray, 'symbol'));
$btcExchangeRateNow = round($priceArray[$key]['price']);
$btcExchangeRateLast = round(getConfigData("ctbExchangeRateBTC"));
if ($btcExchangeRateNow <= 0) {
    sendServiceMessage("Ошибка.\nbtcExchangeRateLast=" . $btcExchangeRateLast .
        "\nbtcExchangeRateNow=" . $btcExchangeRateNow);
} else {
    $btcPercentChange = round(($btcExchangeRateNow - $btcExchangeRateLast) / ($btcExchangeRateLast * 0.01), 1);
    if (abs($btcPercentChange) > CRITICAL_PERCENTAGE) {
        $tgMessage = "\xF0\x9F\x86\x98 #warning \xF0\x9F\x86\x98\n";
        $tgMessage .= "Резкое изменение курса BTC\nc " .
            $btcExchangeRateLast . "$ до " . $btcExchangeRateNow . "$ (" .
            ($btcPercentChange > 0 ? "+" : "") . $btcPercentChange . "%)\nhttps://ru.tradingview.com/chart/";
        sendMessage(TG_CHAT_ID, $tgMessage);
        setConfigData("ctbExchangeRateBTC", $btcExchangeRateNow);
        $tgMessage = "";
    }
    // Обновляем курс раз в сутки в районе 00 часов
    if (time() > getConfigData("ctbExchangeRateTS") + 86400) { // 86400 секунд = сутки.
        setConfigData("ctbExchangeRateTS", strtotime(date("d.m.Y")));
        setConfigData("ctbExchangeRateBTC", $btcExchangeRateNow);
    }
}
// ***************************************************************
// ************ Блок уведомлений об изменениях курса  ************
// ***************************************************************
try { // Проверяем наличие записей для уведомлений
    global $pdo;
    $sql = "SELECT * FROM alerts";
    $alertsData = $pdo->query($sql)->fetchAll(); // Выполнение запроса SELECT
} catch (PDOException $e) {
    sendServiceMessage("\xE2\x9A\xA0 SELECT error (reminder.php):\n"
        . $sql . "\n---\n" . $e->getMessage());
    exit;
}
for ($i = 0; $i < count($alertsData); $i++) {
    $key = array_search($alertsData[$i]['symbol'], array_column($priceArray, 'symbol'));
    if ($key) {
        $currentPrice = roundPrice(floatval($priceArray[$key]['price']));
    } else {
        $symbol = strtoupper($alertsData[$i]['symbol']);
        $symbolPieces = explode("/", $symbol);
        if (count($symbolPieces) == 2) {
            $key0 = array_search($symbolPieces[0] . "USDT", array_column($priceArray, 'symbol'));
            $key1 = array_search($symbolPieces[1] . "USDT", array_column($priceArray, 'symbol'));
            $price0 = floatval($priceArray[$key0]['price']);
            $price1 = floatval($priceArray[$key1]['price']);
            $currentPrice =  roundPrice(floatval($price0 / $price1));
        } else continue;
    }
    $alertPrice = $alertsData[$i]['value'];
    if ($alertsData[$i]['direction']) { // true - в сторону повышения цены
        if ($alertsData[$i]['reloadmultiplier']) {
            // В зоне над ценой уведомления
            if ($currentPrice > $alertPrice * (1 + $alertsData[$i]['reloadmultiplier'] / 100)) {
                // Пересекли зону последнего multiplier, повышаем multiplier
                setReload($alertsData[$i]['id'], $alertsData[$i]['reloadmultiplier'] + 2);
                sendMessage($alertsData[$i]['user_id'], "\xE2\xAC\x86 Курс " . $alertsData[$i]['symbol'] .
                    " поднялся выше " .   printPrice($alertPrice) . " + " . $alertsData[$i]['reloadmultiplier'] .
                    "% до " . rtrim($currentPrice, "0") . "\nСледующий уровень срабатывания: " .
                    printPrice($alertPrice * (1 + ($alertsData[$i]['reloadmultiplier'] + 2) / 100)));
            } elseif ($currentPrice < $alertPrice * (1 + ($alertsData[$i]['reloadmultiplier'] - 4) / 100)) {
                // Понижаем multiplier пока не достигнет 0
                setReload($alertsData[$i]['id'], $alertsData[$i]['reloadmultiplier'] - 2);
            }
        } elseif ($currentPrice > $alertPrice) { // Первое пересечение зоны уведомления
            setReload($alertsData[$i]['id'], 2); // reload - true - 2%
            sendMessage($alertsData[$i]['user_id'], "\xE2\xAC\x86 Курс " . $alertsData[$i]['symbol'] .
                " поднялся выше " . $alertPrice . " до " . rtrim($currentPrice, "0"));
        }
    } else { // direction - false - в сторону понижения цены
        if ($alertsData[$i]['reloadmultiplier']) {
            // В зоне под ценой уведомления
            if ($currentPrice < $alertPrice * (1 - $alertsData[$i]['reloadmultiplier'] / 100)) {
                // Пересекли зону последнего multiplier, повышаем multiplier
                setReload($alertsData[$i]['id'], $alertsData[$i]['reloadmultiplier'] + 2);
                sendMessage($alertsData[$i]['user_id'], "\xE2\xAC\x87 Курс " . $alertsData[$i]['symbol'] .
                    " опустился ниже " .   printPrice($alertPrice) . " - " . $alertsData[$i]['reloadmultiplier'] .
                    "% до " . rtrim($currentPrice, "0") . "\nСледующий уровень срабатывания: " .
                    printPrice($alertPrice * (1 - ($alertsData[$i]['reloadmultiplier'] + 2) / 100)));
            } elseif ($currentPrice > $alertPrice * (1 - ($alertsData[$i]['reloadmultiplier'] - 4) / 100)) {
                // Понижаем multiplier пока не достигнет 0
                setReload($alertsData[$i]['id'], $alertsData[$i]['reloadmultiplier'] - 2);
            }
        } elseif ($currentPrice < $alertPrice) { // Первое пересечение зоны уведомления
            setReload($alertsData[$i]['id'], 2); // // reload - true - 2%
            sendMessage($alertsData[$i]['user_id'], "\xE2\xAC\x87 Курс " . $alertsData[$i]['symbol'] .
                " опустился ниже " . $alertPrice . " до " . rtrim($currentPrice, "0"));
        }
    }
}
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// +++++++++++++++++++++++ БЛОК ФУНКЦИЙ ++++++++++++++++++++++++++
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
/**
 * Обновление параметра умножителя заданного уведомления.
 * @param int $key Идентификатор уведомления в базе данных.
 * @param int $reloadmultiplier [optional] Заначение умножителя. default = 2.
 */
function setReload(int $key, int $reloadmultiplier): void
{
    global $pdo;
    $sql = "";
    try {
        $sql = "UPDATE alerts SET reloadmultiplier = " . $reloadmultiplier .
            ", reloadtime = " . time() . " WHERE id = " . $key;
        //sendServiceMessage($sql);
        $query = $pdo->prepare($sql);
        $query->execute();
    } catch (PDOException $e) {
        sendServiceMessage("\xE2\x9A\xA0 UPDATE error (ctb.php):\n" . $sql
            . "\n---\n" . $e->getMessage());
    }
}

// **************************************************
setConfigData("ctbLastRunTS", time()); // Время последнего запуска в timesatmp );
echo "<br>--------------------<br>END: " . time();
