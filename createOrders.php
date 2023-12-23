<?php
session_start();
include 'core/functions.php';

if(!$_SESSION["authenticated"]  || !(isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] === getDomainURL() . '/exportDataToDB.php')) {
    header("Location: /");
    exit();
}

global $goodsNumRange, $dayLimitSum, $timeRange;
$data = $_SESSION['fileData'];
$currentTimestamp = time(); // Получение текущего временного штампа
$errorMessage = '';
$ordersSum = 0;
$id = 1;
$breakOnQty = $breakOnSum = false;

// Сохраняем заказ в БД
try {
    // Создаем новое PDO-подключение
    $pdo = connectToDb();
    // Чистим таблицы в БД перед сохранением информации
    cleanDbTable('orders');
    cleanDbTable('order_goods');

    while(true) {
        $orderId = 'order_' . $id;
        $currentTimestamp += getRandom($timeRange[0], $timeRange[1]);
        $orderDate = date('Y-m-d H:i:s', $currentTimestamp);
        $goodsNumInOrder = getRandom($goodsNumRange[0], $goodsNumRange[1]);
        $orderGoods = createOrderGoods($orderId, $goodsNumInOrder);
        if($orderGoods == -1) { $breakOnQty = true; break; }
        if($ordersSum + $orderGoods[1] >= $dayLimitSum) { $breakOnSum = true; break; }
        // Сохр заказ и товары в БД
        saveOrderToDb($orderId, $orderDate, $orderGoods[1]);
        saveOrderGoodsToDB($orderGoods[0]);
        $ordersSum += $orderGoods[1];
        $id++;
    }
} catch (PDOException $e) {
    $errorMessage = "Ошибка: при добавлении данных в БД " . $e->getMessage();
} catch (TypeError $e) {
    $errorMessage = "Ошибка: Аргументы должны быть целыми числами. ". $e->getMessage();
} catch (Exception $e) {
    $errorMessage = "Ошибка: Минимальное значение больше максимального. ". $e->getMessage();
}

$pdo = null; // Закрываем соединение с базой данных

if($errorMessage) echo '<h3 style="color: red;">' . $errorMessage . '</h3>';
if($breakOnQty) echo '<h3 style="color: blue;"> Создано и добавлено в базу данных заказов: ' . ($id - 1) . ' <br> Достигнут лимит по количеству товаров в исходных данных</h3>';
if($breakOnSum) echo '<h3 style="color: green;"> Создано и добавлено в базу данных заказов: ' . ($id - 1) . ' <br> Достигнут лимит по сумме в ' . $dayLimitSum . ' грн</h3>';

session_destroy();
?>

<a href="index.php">На главную</a>

