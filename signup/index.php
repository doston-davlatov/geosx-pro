<?php
session_start();

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: ../");
    exit;
}

include '../connection/config.php';
$query = new Database();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $first_name = $query->validate($_POST['first_name']);
    $last_name = $query->validate($_POST['last_name']);
    $email = $query->validate(strtolower($_POST['email']));
    $username = $query->validate(strtolower($_POST['username']));
    $password = $query->hashPassword($_POST['password']);

    $data = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'username' => $username,
        'password' => $password
    ];

    $result = $query->insert('users', $data);

    if (!empty($result)) {
        $user_id = $query->select('users', 'id', 'username = ?', [$username], 's')[0]['id'];

        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['user_id'] = $user_id;

        setcookie('username', $username, time() + (86400 * 30), "/", "", true, true);
        setcookie('session_token', session_id(), time() + (86400 * 30), "/", "", true, true);
        ?>
        <script>
            window.onload = function () {
                Swal.fire({
                    position: 'top-end',
                    icon: 'success',
                    title: 'Roʻyxatdan oʻtish muvaffaqiyatli',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    window.location.href = '../';
                });
            };
        </script>

        <?php
    } else {
        echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: 'Ro‘yxatdan o‘tish amalga oshmadi. Keyinroq qayta urinib ko‘ring.',
                    });
                </script>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <title>Ro'yxatdan o'tish</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../src/css/login_signup.css">
</head>

<body>
    <div class="form-container">
        <h1>Ro'yxatdan o'tish</h1>
        <form id="signupForm" method="post" action="">
            <div class="form-group">
                <label for="first_name">Ism</label>
                <input type="text" id="first_name" name="first_name" required maxlength="30">
            </div>
            <div class="form-group">
                <label for="last_name">Familiya</label>
                <input type="text" id="last_name" name="last_name" required maxlength="30">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="100">
                <p id="email-message"></p>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required maxlength="30">
                <p id="username-message"></p>
            </div>
            <div class="form-group">
                <label for="password">Parol</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required maxlength="255">
                    <button type="button" id="toggle-password" class="password-toggle"><i
                            class="fas fa-eye"></i></button>
                </div>
            </div>
            <div class="form-group">
                <button type="submit" id="submit">Ro'yxatdan o'tish</button>
            </div>
        </form>
        <div class="text-center">
            <p>Hisobingiz bormi? <a href="../login/">Tizimga kirish</a></p>
        </div>
    </div>

    <script src="../src/js/sweetalert2.js"></script>

    <script>
        let isEmailAvailable = false;
        let isUsernameAvailable = false;

        function validateUsernameFormat(username) {
            const usernamePattern = /^[a-zA-Z0-9_]+$/;
            return usernamePattern.test(username);
        }

        document.getElementById('email').addEventListener('input', function () {
            let email = this.value;
            if (email.length > 0) {
                fetch('check_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `email=${encodeURIComponent(email)}`
                })
                    .then(response => response.json())
                    .then(data => {
                        const messageElement = document.getElementById('email-message');
                        if (data.exists) {
                            messageElement.textContent = 'Bu email mavjud!';
                            isEmailAvailable = false;
                        } else {
                            messageElement.textContent = '';
                            isEmailAvailable = true;
                        }
                    });
            }
        });

        document.getElementById('username').addEventListener('input', function () {
            let username = this.value;
            const messageElement = document.getElementById('username-message');

            if (!validateUsernameFormat(username)) {
                messageElement.textContent = 'Foydalanuvchi nomi faqat harflar, raqamlar va pastki chiziqdan iborat bo\'lishi mumkin!';
                isUsernameAvailable = false;
                return;
            }

            if (username.length > 0) {
                fetch('check_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `username=${encodeURIComponent(username)}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            messageElement.textContent = 'Bu foydalanuvchi nomi mavjud!';
                            isUsernameAvailable = false;
                        } else {
                            messageElement.textContent = '';
                            isUsernameAvailable = true;
                        }
                    });
            } else {
                messageElement.textContent = '';
            }
        });

        function validateEmailFormat(email) {
            const emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
            return emailPattern.test(email);
        }

        document.getElementById('signupForm').addEventListener('submit', function (event) {
            let email = document.getElementById('email').value;
            const emailMessageElement = document.getElementById('email-message');
            let username = document.getElementById('username').value;
            const usernameMessageElement = document.getElementById('username-message');

            if (!validateEmailFormat(email)) {
                emailMessageElement.textContent = 'Elektron pochta formati noto\'g\'ri!';
                event.preventDefault();
                return;
            }

            if (!validateUsernameFormat(username)) {
                usernameMessageElement.textContent = 'Foydalanuvchi nomi faqat harflar, raqamlar va pastki chiziqdan iborat bo\'lishi mumkin!';
                event.preventDefault();
                return;
            }

            if (isEmailAvailable === false) {
                emailMessageElement.textContent = 'Bu elektron pochta mavjud!';
                event.preventDefault();
            }

            if (isUsernameAvailable === false) {
                usernameMessageElement.textContent = 'Bu foydalanuvchi nomi mavjud!';
                event.preventDefault();
            }
        });

        document.getElementById('toggle-password').addEventListener('click', function () {
            const passwordField = document.getElementById('password');
            const toggleIcon = this.querySelector('i');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
    </script>

</body>

</html>