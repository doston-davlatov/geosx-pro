<?php
define("DB_SERVER", "localhost");
define("DB_USERNAME", "root");
define("DB_PASSWORD", "");
define("DB_NAME", "uzb_gis");

class Database
{
    private $conn;

    public function __construct()
    {
        $this->conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if ($this->conn->connect_error) {
            die("Database connection error: " . $this->conn->connect_error);
        }
    }

    public function __destruct()
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }

    public function executeQuery($sql, $params = [])
    {
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            error_log("SQL Prepare Error: " . $this->conn->error . " | SQL: " . $sql);
            return false;
        }

        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param))     $types .= 'i';
                elseif (is_float($param)) $types .= 'd';
                elseif (is_string($param)) $types .= 's';
                else                     $types .= 's';
            }

            $bind_params = array_merge([$types], $params);
            call_user_func_array([$stmt, 'bind_param'], $this->refValues($bind_params));
        }

        if (!$stmt->execute()) {
            error_log("Execute Error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        return $stmt;
    }

    private function refValues($arr)
    {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->executeQuery($sql, $params);

        if (!$stmt) {
            return [];
        }

        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function queryRow($sql, $params = [])
    {
        $data = $this->query($sql, $params);
        return $data[0] ?? null;
    }

    function validate($value)
    {
        return htmlspecialchars(trim(stripslashes($value)), ENT_QUOTES, 'UTF-8');
    }

    public function select($table, $columns = "*", $condition = "", $params = [])
    {
        $sql = "SELECT $columns FROM $table" . ($condition ? " WHERE $condition" : "");
        $result = $this->executeQuery($sql, $params);

        if (is_string($result)) {
            return $result; // xato xabarini qaytaradi
        }

        return $result->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function selecta($table, $columns = "*", $condition = "", $params = [], $types = "")
    {
        $sql = "SELECT $columns FROM $table" . ($condition ? " WHERE $condition" : "");
        $result = $this->executeQuery($sql, $params, $types);

        if (is_string($result)) {
            return $result;
        }

        return $result->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    public function insert($table, $data)
    {
        $keys = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($keys) VALUES ($placeholders)";
        $types = str_repeat('s', count($data));

        $result = $this->executeQuery($sql, array_values($data), $types);
        if (is_string($result)) {
            return $result;
        }

        return $this->conn->insert_id;
    }

    public function update($table, $data, $condition = "", $params = [], $types = "")
    {
        $set = implode(", ", array_map(function ($k) {
            return "$k = ?";
        }, array_keys($data)));
        $sql = "UPDATE $table SET $set" . ($condition ? " WHERE $condition" : "");
        $types = str_repeat('s', count($data)) . $types;

        $result = $this->executeQuery($sql, array_merge(array_values($data), $params), $types);
        if (is_string($result)) {
            return $result;
        }

        return $this->conn->affected_rows;
    }

    public function delete($table, $condition = "", $params = [], $types = "")
    {
        $sql = "DELETE FROM $table" . ($condition ? " WHERE $condition" : "");

        $result = $this->executeQuery($sql, $params, $types);
        if (is_string($result)) {
            return $result;
        }

        return $this->conn->affected_rows;
    }

    public function hashPassword($password)
    {
        return hash_hmac('sha256', $password, 'iqbolshoh');
    }
    // Session check
    public function check_session($role = '')
    {
        session_start();
        if (!isset($_SESSION['user'])) {
            header('Location: login.php');
            exit;
        }
        if ($role && $_SESSION['user']['role'] !== $role) {
            die('Access denied');
        }
    }

    // CSRF token
    public function generate_csrf_token()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}
