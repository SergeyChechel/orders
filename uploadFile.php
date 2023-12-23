<?php
session_start();
include 'core/functions.php';

if(!$_SESSION["authenticated"] ||
    !(isset($_SERVER['HTTP_REFERER']) &&
        ($_SERVER['HTTP_REFERER'] === getDomainURL() . "/") ||
        ($_SERVER['HTTP_REFERER'] === getDomainURL() . "/index.php") ||
        ($_SERVER['HTTP_REFERER'] === getDomainURL() . "/exportDataToDB.php") ||
        $_SERVER['HTTP_REFERER'] === getDomainURL() . "/uploadFile.php")) {
    header("Location: /");
    exit();
}

// Переменная для хранения сообщения об успешной загрузке
$uploadMessage = "";

// Обработка загрузки файла
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excelFile"])) {
    // Сохраняем указанные пользователем номера столбцов в excel файле в сессию
    $orderColunmsInExcel = [];
    foreach ($_POST['orderColunmsInExcel'] as $item => $value) $orderColunmsInExcel[] = sanitizeInput($value);
    $_SESSION['orderColunmsInExcel'] = $orderColunmsInExcel;
    $_SESSION['titleRowNum'] = sanitizeInput($_POST['titleRowNum']);
    $_SESSION['firstCellNumber'] = sanitizeInput($_POST['firstCellNumber']);

    $targetDirectory = "uploads/"; // Директория для сохранения загруженных файлов
    if (!is_dir($targetDirectory)) mkdir($targetDirectory, 0755, true);
    $targetFile = $targetDirectory . basename($_FILES["excelFile"]["name"]);
    $fileType = pathinfo($targetFile, PATHINFO_EXTENSION);

    // Проверка, что файл имеет расширение .xlsx
    if ($fileType == "xlsx") {
        if (move_uploaded_file($_FILES["excelFile"]["tmp_name"], $targetFile)) {
            $uploadMessage = "Файл " . basename($_FILES["excelFile"]["name"]) . " успешно загружен.";
            $_SESSION["fileName"] = basename($_FILES["excelFile"]["name"]);
            header("Location: exportDataToDB.php");
            exit();
        } else {
            $uploadMessage = "Ошибка при загрузке файла.";
        }
    } else {
        $uploadMessage = "Пожалуйста, загрузите файл с расширением .xlsx";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="css/uploadFile.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.3/xlsx.full.min.js"></script>
    <title>Загрузка файла Excel (.xlsx)</title>
</head>
<body>
<h2>Загрузка файла Excel (.xlsx)</h2>
<form id="fileForm" method="post" enctype="multipart/form-data">
    <input type="file" id="excelFile" name="excelFile" accept=".xlsx" required>
    <br><br>
    <span id="secondElement">
        <label>Номер строки заголовков</label>
        <input type="text" id="titleRowNum" name="titleRowNum" value="4" required><br>
        <p>Номера указанных столбцов в файле</p>
         <b>Код</b> <select name="orderColunmsInExcel['Код']">
                <?php getSelectOptions(10, 2); ?>
            </select>
        <b>Номенк-ра</b> <select name="orderColunmsInExcel['Номенклатура']">
                <?php getSelectOptions(10, 3); ?>
            </select>
        <b>Хар-ка</b> <select name="orderColunmsInExcel['Хар-ка']">
                <?php getSelectOptions(10, 5); ?>
            </select>
        <b>Кол-во</b> <select name="orderColunmsInExcel['Кол-во']">
                <?php getSelectOptions(10, 6); ?>
            </select>
        <b>Цена</b> <select name="orderColunmsInExcel['Цена']">
                <?php getSelectOptions(10, 7); ?>
        </select>
        <b>Сумма</b> <select name="orderColunmsInExcel['Сумма']">
                <?php getSelectOptions(10, 8); ?>
            </select>
        <br><br><br>
        <div id="down">
            <input id="load" type="submit" value="Загрузить">
            <div id="spinner" class="spinner hidden"></div>
        </div>

    </span>
</form>

<?php
if ($uploadMessage) {
    echo '<h3 style="color: red;">' . $uploadMessage . '</h3>';
}
?>

<script>let models = <?php global $models;  echo json_encode($models); ?></script>
<script src="js/uploadFile.js"></script>
</body>
</html>





