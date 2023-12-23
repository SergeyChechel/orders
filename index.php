<?php
// Инициализация сессии (если она еще не была инициализирована)
session_start();
include 'core/functions.php';

// Переменные для хранения сообщений об ошибках
$errorMessage = "";

// Проверка, была ли отправлена форма
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Получение введенных пользователем данных
    $username = sanitizeInput($_POST["username"]);
    $password = sanitizeInput($_POST["password"]);

    try {
        // Подключение к базе данных
        $pdo = connectToDb();
        // Получение пользователя из БД
        $row = getUserFromDb($username);
        // Проверяем, есть ли такой пользователь
        if ($row) {
            $storedEmail = $row['email'];
            $storedHashedPassword = $row['pass'];
            $isAllowedUpload = $row['isAllowedUpload'];
            if(!$isAllowedUpload)  {
                $errorMessage = "Вы не имеете разрешения на загрузку заказов в базу данных!";
                throw new PDOException($errorMessage);
            }
            // Теперь у вас есть $storedEmail и $storedHashedPassword для сравнения
            if ($storedEmail == $username && password_verify($password, $storedHashedPassword)) {
                // Вход выполнен успешно, устанавливаем флаг авторизации в сессии
                $_SESSION["authenticated"] = true;
                // Перенаправление на защищенную страницу
                header("Location: uploadFile.php");
                exit();
            } else {
                // Ошибка входа
                $errorMessage = "Неправильное имя пользователя или пароль.";
            }
        } else {
            $errorMessage = "Пользователь не найден.";
        }
    } catch (PDOException $e) {
        $errorMessage = "Ошибка: " . $e->getMessage();
    }

    $pdo = null; // Закрываем соединение с базой данных
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Форма авторизации</title>
</head>
<body>
<h3>Для загрузки файла с данными заказов авторизуйтесь</h3>
<form method="post" action="">
    <label for="username">Email:</label>
    <input type="text" id="username" name="username" required><br>

    <label for="password">Пароль:</label>
    <input type="password" id="password" name="password" required ><br>
    <br>
    <input type="submit" value="Войти">
    <br>
    <br>
    <a href="registration.php">Еще не зарегистрированы?</a>
</form>

<?php
if ($errorMessage) {
    echo '<h3 style="color: red;">' . $errorMessage . '</h3>';
}
?>
</body>
</html>
