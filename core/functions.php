<?php
include 'config.php';

// Соединяемся с БД
function connectToDb(): PDO
{
    $dsn = 'mysql:host=' . SERVERNAME . ';dbname=' . DBNAME;
    $pdo = new PDO($dsn, USERNAME, PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function getDomainURL(): string
{
    // Получаем протокол (http или https)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    // Получаем имя хоста (доменное имя)
    $host = $_SERVER['HTTP_HOST'];
    // Собираем URL домена
    $domain_url = $protocol . '://' . $host;
    // Выводим URL домена
    return $domain_url;
}

// Функция для санитизации входных данных
function sanitizeInput($data): string
{
    $data = trim($data);            // Удаление лишних пробелов
    $data = stripslashes($data);    // Удаление экранированных символов
    $data = htmlspecialchars($data); // Преобразование специальных символов в HTML-сущности
    return $data;
}

// Функция для санитизации адреса электронной почты
function sanitizeEmail($email) {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return $email;
}

// Функция распечатывает опции эл-та селект на странице загрузки файла
function getSelectOptions($optQty, $selectedOption) {
    for ($i = 1; $i <= $optQty; $i++) {
        $selected = ($i === $selectedOption) ? 'selected' : '';
        echo "<option value=\"$i\" $selected>$i</option>";
    }
}

// Получаем случ число из заданного диапазона
function getRandom($min, $max): int
{
    try {
        return random_int($min, $max);
    } catch (TypeError | Exception $e) {
        throw $e;
    }
}

//Выбираем строку из файла
function getPosition(): int
{
    global $data;
    $pos = getRandom(0, count($data) - 1);
    while ($data[$pos][3] == 0 || $data[$pos][3] == null) {
        array_splice($data, $pos, 1);
        if(count($data) == 0) return -1;
        $pos = getRandom(0, count($data) - 1);
    }
    return $pos;
}

// Получаем кол-во товара в заказе
function getGoodQty($totalAmount, $pos): int
{
    global $goodLimitQty, $orderLimitSum, $data;
    $qty = getRandom(1, $totalAmount < $goodLimitQty ? $totalAmount : $goodLimitQty);
    $price = $data[$pos][4];
    while($qty * $price >= $orderLimitSum) {
        $qty -= 1;
    }
    $data[$pos][3] = $totalAmount - $qty;
    return $qty;
}

// Получаем товары для заказа
function createOrderGoods($orderId, $goodsNumInOrder) {
    global $data, $orderLimitSum;
    $orderGoods = [];
    $orderGoodsSum = 0;
    for($i = 0; $i < $goodsNumInOrder; $i++) {
        $orderGood = [];
        $pos = getPosition();
        if($pos == -1) return -1;
        $orderGood['order_id'] = $orderId;
        $orderGood['code'] = $data[$pos][0];
        $orderGood['product_name'] = $data[$pos][1];
        $orderGood['spec'] = $data[$pos][2];
        $orderGood['qty'] = getGoodQty($data[$pos][3], $pos);
        $orderGood['price'] = $data[$pos][4];
        $orderGood['sum'] = $orderGood['qty'] * $orderGood['price'];
        if(($orderGoodsSum + $orderGood['sum']) <= $orderLimitSum) {
            $orderGoods[] = $orderGood;
            $orderGoodsSum += $orderGood['sum'];
        } else break;
    }
    return [$orderGoods, $orderGoodsSum];
}

// Читает указанные ячейки из указанной строки файла excel
function readCellsFromRow($worksheet, $rowNumber, $cellNumbers, $highestRow): array
{
    try {
        // Массив для хранения прочитанных значений
        $cellValues = [];
        // Проходим по номерам ячеек и читаем их значения
        foreach ($cellNumbers as $index => $columnNumber) {
            // Получаем значение ячейки
            $cellValue = $worksheet->getCellByColumnAndRow($columnNumber - 1, $rowNumber)->getCalculatedValue();
            if($index == 0 && $cellValue && (strlen($cellValue) <= 9)) {
                $cellValue = str_pad(strval($cellValue), 9, '0', STR_PAD_LEFT);
            }
            if(!$cellValue && $rowNumber < $highestRow - 2) throw new PDOException();
            // Добавляем значение в массив
            $cellValues[] = $cellValue;
        }
    } catch (PHPExcel_Exception | PDOException $e) {
        throw $e;
    }

    return $cellValues;
}

// Проверим данные в колонках на соотвествие типам
function validateColumnsData($rowData) {
    try {
        for ($i = 0; $i < count($rowData); $i++) {
            $isNumber = is_numeric($rowData[$i]);
            if ($i == 0 && (!preg_match('/^\d+-?\d+$/', $rowData[$i]) || preg_match('/^0+$/', $rowData[$i]))) throw new PDOException();
            if (($i == 1 || $i == 2) && ($isNumber || !preg_match('/^[а-яА-Яa-zA-ZҐґЄєІіЇї]/iu', $rowData[$i]))) throw new PDOException();
            if ($i > 2 && !$isNumber) throw new PDOException();
        }
        if($rowData[3] * $rowData[4] != $rowData[5]) throw new Exception();
    } catch (PDOException | Exception $e) {
        throw $e;
    }
}

// Сохраняем в БД данные из файла
function saveExcelDataToDb($rowData)
{
    global $pdo;
    try {
        // SQL-запрос с подстановкой параметров
        $sql = "INSERT INTO `excel_data`(`code`, `name`, `descr`, `amount`, `price`, `summ`) VALUES (:value1, :value2, :value3, :value4, :value5, :value6)";
        // Подготавливаем запрос
        $ch = $stmt = $pdo->prepare($sql);
        // Привязываем значения к параметрам
        for ($i = 0; $i < count($rowData); $i++) {
            $stmt->bindParam(':value' . ($i + 1), $rowData[$i]);
        }
        // Выполняем запрос
        $stmt->execute();
    } catch(PDOException $e) {
        throw $e;
    }
}

// Сохраняем в БД товары заказа
function saveOrderGoodsToDB($orderGoods) {
    global $pdo;
    try {
        foreach ($orderGoods as $orderGood) {
            // Запись в базу данных. SQL-запрос с подстановкой параметров
            $sql = "INSERT INTO `order_goods`(`order_id`, `code`, `product_name`, `spec`, `qty`, `price`) VALUES (:value1, :value2, :value3, :value4, :value5, :value6)";
            // Подготавливаем запрос
            $stmt = $pdo->prepare($sql);
            // Привязываем значения к параметрам
            $stmt->bindParam(':value1', $orderGood['order_id'], PDO::PARAM_STR);
            $stmt->bindParam(':value2', $orderGood['code'], PDO::PARAM_STR);
            $stmt->bindParam(':value3', $orderGood['product_name'], PDO::PARAM_STR);
            $stmt->bindParam(':value4', $orderGood['spec'], PDO::PARAM_STR);
            $stmt->bindParam(':value5', $orderGood['qty'], PDO::PARAM_STR);
            $stmt->bindParam(':value6', $orderGood['price'], PDO::PARAM_STR);
            // Выполняем запрос
            $stmt->execute();
        }
    } catch(PDOException $e) {
        throw $e;
    }
}

// Сохраняем заказ в БД
function saveOrderToDb($orderId, $orderDate, $orderGoodsSum) {
    global $pdo;
    try {
        // Запись в базу данных. SQL-запрос с подстановкой параметров
        $sql = "INSERT INTO `orders`(`id`,`date_create`, `sum`) VALUES (:value1, :value2, :value3)";
        // Подготавливаем запрос
        $stmt = $pdo->prepare($sql);
        // Привязываем значения к параметрам
        $stmt->bindParam(':value1', $orderId, PDO::PARAM_STR);
        $stmt->bindParam(':value2', $orderDate, PDO::PARAM_STR);
        $stmt->bindParam(':value3', $orderGoodsSum, PDO::PARAM_STR);
        // Выполняем запрос
        $stmt->execute();
    } catch(PDOException $e) {
        throw $e;
    }
}

// Сохраняем пользователя в БД
function createUserInDb($name, $email, $hashedPassword): bool
{
    global $pdo;
    try {
        // Запись в базу данных. SQL-запрос с подстановкой параметров
        $sql = "INSERT INTO `users`(`name`, `email`, `pass`) VALUES (:value1, :value2, :value3)";
        // Подготавливаем запрос
        $stmt = $pdo->prepare($sql);
        // Привязываем значения к параметрам
        $stmt->bindParam(':value1', $name, PDO::PARAM_STR);
        $stmt->bindParam(':value2', $email, PDO::PARAM_STR);
        $stmt->bindParam(':value3', $hashedPassword, PDO::PARAM_STR); // Здесь используем хешированный пароль
        // Выполняем запрос
        return $stmt->execute();

    } catch(PDOException $e) {
        throw $e;
    }
}

// Получаем пользователя из БД
function getUserFromDb($email) {
    global $pdo;
    try {
        // Запрос для выбора email и password из базы данных
        $sql = "SELECT `email`, `pass`, `isAllowedUpload` FROM `users` WHERE `email` = :username";
        // Подготавливаем запрос
        $stmt = $pdo->prepare($sql);
        // Привязываем значение к параметру
        $stmt->bindParam(':username', $email, PDO::PARAM_STR);
        // Выполняем запрос
        $stmt->execute();
        // Получаем результат запроса
        return $stmt->fetch(PDO::FETCH_ASSOC);

    } catch(PDOException $e) {
        throw $e;
    }
}

// Чистим таблицу в БД
function cleanDbTable($tableName) {
    global $pdo;
    try {
        // SQL-запрос очистки таблицы
        $sql = "TRUNCATE TABLE `$tableName`";
        // Подготавливаем и выполняем запрос
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

    } catch(PDOException $e) {
        throw $e;
    }
}