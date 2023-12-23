<?php
session_start();
include 'core/functions.php';

if(!$_SESSION["authenticated"] || !(isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] === getDomainURL() . '/uploadFile.php')) {
    header("Location: /");
    exit();
}
// Подключаем автозагрузчик Composer
require 'vendor/autoload.php';

$errorMessage = "";

try {
    // Создание объекта для чтения файла Excel
    $excelFileName = 'uploads/' . $_SESSION["fileName"];
    $objPHPExcel = PHPExcel_IOFactory::load($excelFileName);
    // Выбор активного листа (первого листа)
    $worksheet = $objPHPExcel->getActiveSheet();
    // Получение количества строк и столбцов
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();

    $fileData = []; // двумерный массив обработанных данных из импортируемого файла
    $rowData = []; // строка из двумерного массива ориг. данных импортируемого файла

    // Создаем новое PDO-подключение к БД
    $pdo = connectToDb();

    // Чистим таблицу в БД
    cleanDbTable('excel_data');

    $orderColunmsInExcel = $_SESSION['orderColunmsInExcel'];
    $titleRowNum = $_SESSION['titleRowNum'];

    for ($row = $titleRowNum + 1; $row <= $highestRow; $row++) {
        $rowData = readCellsFromRow($worksheet, $row, $orderColunmsInExcel, $highestRow);

        // Проверям соответствие типов данных считанных столбцов
        if($row == $titleRowNum + 1) validateColumnsData($rowData);

        saveExcelDataToDb($rowData); // Запись строки из импортируемого файла в БД
        if($rowData[3]) $fileData[] = $rowData; // Добавляем массив строки данных из файла в массив $fileData[] и далее в приложении работаем с ним
    }

    $_SESSION['fileData'] = $fileData;

} catch (PHPExcel_Exception $e) {
    $errorMessage = 'Ошибка чтения загруженного файла ' . $_SESSION["fileName"] . ' : ' . $e->getMessage();
} catch (PDOException | Exception $e) {
    $errorMessage = "Ошибка: Проверьте соответствие номеров столбцов в файле <br>" . $_SESSION["fileName"] . " указанным на странице загрузки файла <br>";
    $_FILES = [];
}

// Закрываем соединение с базой данных
$pdo = null;

?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="css/exportDataToDB.css">
    <title>Создание заказов</title>
</head>
<body>

<div id="down">
    <?php
        if(!$errorMessage) {
            echo '<h3 style="color: green;">Данные из файла ' . $_SESSION["fileName"] . ' добавлены в базу данных.</h3><a href="createOrders.php"><button id="create">Создать заказы</button></a> ';
        } else {
            echo '<h3 style="color: red;">' . $errorMessage . '</h3><br><a href="uploadFile.php">Назад</a>';
        }
    ?>
    <div id="spinner" class="spinner hidden"></div>
</div>
</body>
</html>


<script>
    function showSpinner() {
        spinner.classList.remove("hidden");
    }
    const button = document.getElementById("create");
    const spinner = document.getElementById("spinner");
    if(button) button.addEventListener("click", showSpinner);
</script>



