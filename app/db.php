<?php
declare(strict_types=1);

/** Đường dẫn file dữ liệu SQLite */
function db_path(): string
{
    return (string)($GLOBALS['CONFIG']['db_path'] ?? APP_ROOT . '/data/zungza.sqlite');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . db_path(), null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 10, // chờ tối đa 10s nếu đang có người ghi
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 10000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    return q($sql, $params)->fetchColumn();
}

function last_id(): int
{
    return (int)db()->lastInsertId();
}

/** Tạo chuỗi "?,?,?" cho mệnh đề IN */
function in_list(array $items): string
{
    return implode(',', array_fill(0, max(1, count($items)), '?'));
}

/** Thời gian hiện tại theo giờ Việt Nam, dạng lưu trong DB */
function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Lỗi trùng khóa (UNIQUE) */
function is_unique_violation(PDOException $e): bool
{
    return $e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE constraint failed');
}
