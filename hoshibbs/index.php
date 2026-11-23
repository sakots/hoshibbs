<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';
$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    $config = array_replace($config, require $localConfig);
}

function respond(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($message);
}

function field(array $input, string $key): string
{
    return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
}

function openDatabase(string $path): PDO
{
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create the database directory.');
    }
    $db = new PDO('sqlite:' . $path, options: [PDO::ATTR_TIMEOUT => 5]);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('CREATE TABLE IF NOT EXISTS threads (
        id INTEGER PRIMARY KEY,
        board TEXT NOT NULL,
        title TEXT NOT NULL,
        body TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS threads_board ON threads(board);
    CREATE TABLE IF NOT EXISTS replies (
        id INTEGER PRIMARY KEY,
        thread_id INTEGER NOT NULL REFERENCES threads(id),
        body TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS replies_thread ON replies(thread_id);');
    return $db;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
        header('Allow: GET, HEAD, POST');
        respond(405, '許可されていないメソッドです。');
    }
    $boards = $config['boards'];
    $board = $_GET['board'] ?? null;
    $thread = $_GET['thread'] ?? null;
    $twig = new Environment(new FilesystemLoader(__DIR__ . '/templates'));
    header('Content-Type: text/html; charset=UTF-8');

    if ($board === null && $thread === null && $method !== 'POST') {
        echo $twig->render('board.html.twig', ['boards' => $boards]);
        return;
    }
    if (!is_string($board) || !preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $board) || !isset($boards[$board])) {
        respond(404, '掲示板が見つかりません。');
    }
    if ($thread !== null) {
        $thread = filter_var($thread, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($thread === false) {
            respond(404, 'スレッドが見つかりません。');
        }
    }

    if (!session_start(['use_strict_mode' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'])) {
        throw new RuntimeException('Cannot start the session.');
    }
    $csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    session_write_close();
    if ($method === 'POST' && !hash_equals($csrf, field($_POST, 'csrf'))) {
        respond(403, '投稿フォームの有効期限が切れました。ページを開き直してください。');
    }

    $db = openDatabase($config['database']);
    $threadRow = null;
    if ($thread !== null) {
        $query = $db->prepare('SELECT title, body FROM threads WHERE id = ? AND board = ?');
        $query->execute([$thread, $board]);
        $threadRow = $query->fetch(PDO::FETCH_ASSOC);
        if ($threadRow === false) {
            respond(404, 'スレッドが見つかりません。');
        }
    }
    $boardUrl = '?board=' . rawurlencode($board);
    if ($method === 'POST') {
        $body = field($_POST, 'body');
        $title = $thread === null ? field($_POST, 'title') : '';
        if ($body === '' || ($thread === null && $title === '')) {
            respond(422, '必須項目を入力してください。');
        }
        if ($thread === null) {
            $db->prepare('INSERT INTO threads (board, title, body) VALUES (?, ?, ?)')->execute([$board, $title, $body]);
            $thread = (int) $db->lastInsertId();
        } else {
            $db->prepare('INSERT INTO replies (thread_id, body) VALUES (?, ?)')->execute([$thread, $body]);
        }
        header('Location: ' . $boardUrl . '&thread=' . $thread, true, 303);
        return;
    }

    $context = ['boardName' => $boards[$board], 'boardUrl' => $boardUrl, 'csrf' => $csrf];
    if ($thread === null) {
        $query = $db->prepare('SELECT id, title FROM threads WHERE board = ? ORDER BY id DESC');
        $query->execute([$board]);
        $context['threads'] = $query->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $query = $db->prepare('SELECT body FROM replies WHERE thread_id = ? ORDER BY id');
        $query->execute([$thread]);
        $context += ['thread' => $threadRow, 'threadUrl' => $boardUrl . '&thread=' . $thread,
            'replies' => $query->fetchAll(PDO::FETCH_COLUMN)];
    }
    echo $twig->render('board.html.twig', $context);
} catch (Throwable $exception) {
    error_log((string) $exception);
    respond(500, '掲示板を表示できませんでした。');
}
