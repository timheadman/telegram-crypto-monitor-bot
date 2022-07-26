<?php
/**
 * Получить уведомления из базы данных в виде массива.
 * @param int $chat_id Номер чата или false для получения alerts всех пользователей
 * @return array
 */
function getAlerts(int $chat_id): array
{
    global $pdo;
    try { // Проверяем наличие записей для уведомлений
        if ($chat_id) $sql = "SELECT * FROM alerts WHERE user_id=" . $chat_id . " ORDER BY symbol, direction DESC";
        else $sql = "SELECT * FROM alerts ORDER BY symbol, direction DESC";
        return $pdo->query($sql)->fetchAll(); // Выполнение запроса SELECT
        //sendServiceMessage($sql . "\n" . var_export($alertsData, true));
    } catch (PDOException $e) {
        sendMessage($chat_id, "\xE2\x9A\xA0 SELECT error (webhook.php):\n" . $sql . "\n---\n" . $e->getMessage());
        exit;
    }
}

function test(string $symbol): float
{
    global $priceArray;
    $key = array_search($symbol, array_column($priceArray, 'symbol'));
    if ($key) {
        return roundPrice(floatval($priceArray[$key]['price']));
    } else {
        $symbol = strtoupper($symbol);
        $symbolPieces = explode("/", $symbol);
        if (count($symbolPieces) == 2) {
            $key0 = array_search($symbolPieces[0] . "USDT", array_column($priceArray, 'symbol'));
            $key1 = array_search($symbolPieces[1] . "USDT", array_column($priceArray, 'symbol'));
            $price0 = floatval($priceArray[$key0]['price']);
            $price1 = floatval($priceArray[$key1]['price']);
            return roundPrice(floatval($price0 / $price1));
        } else return false;
    }
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
