<?php
include 'core/functions.php';

// Переменные для хранения сообщений об ошибках и успешной регистрации
$errorMessage = $successMessage = "";
$isUserCreated = false;

// Проверка, была ли отправлена форма
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Получение введенных пользователем данных
    $name = sanitizeInput($_POST["name"]);
    $email = sanitizeEmail($_POST["email"]);
    $password = sanitizeInput($_POST["password"]);

    // Проверка данных
    if (empty($name) || empty($email) || empty($password)) {
        $errorMessage = "Заполните все поля формы.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Неверный формат адреса электронной почты.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        try {
            // Создаем новое PDO-подключение
            $pdo = connectToDb();
            // Создаем нового пользователя в БД
            $isUserCreated = createUserInDb($name, $email, $hashedPassword);

        } catch (PDOException $e) {
            $errorMessage = "Ошибка: пользователь с таким email уже существует!";
        }

        $pdo = null; // Закрываем соединение с базой данных

        if($isUserCreated) $successMessage = "Вы успешно зарегистрированы!";
    }
}
// Очистка полей формы
$_POST["name"] = $_POST["email"] = $_POST["password"] = "";

?>

<!DOCTYPE html>
<html>
<head>
    <title>Регистрация</title>
</head>
<body>
<h2>Регистрация</h2>
<form method="post" action="">
    <label for="name">Имя:</label>
    <input type="text" id="name" name="name" required><br>

    <label for="email">Email:</label>
    <input type="email" id="email" name="email" required><br>

    <label for="password">Пароль:</label>
    <input type="password" id="password" name="password" required pattern="^(?=.*\d)(?=.*[A-ZА-ЯЁҐІЇЄ]).{8,}$"
           title="Пароль должен содержать хотя бы одну цифру и хотя бы одну букву в верхнем регистре, и иметь минимум 8 символов.">
    <br>
    <br>
    <input type="submit" value="Зарегистрироваться">
</form>

<?php
if ($errorMessage) {
    echo '<h3 style="color: red;">' . $errorMessage . '</h3>';
}
if ($successMessage) {
    echo '<h3 style="color: green;">' . $successMessage . '</h3><a href="index.php">Войдите со своими учетными данными</a> ';
}
?>
</body>
</html>

