<?php

declare(strict_types=1);

function compatText(string $text, bool $multiline = false): string
{
  return str_replace(["\r\n", "\r", "\n"], $multiline ? ' <br> ' : ' ',
    htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

function compatResponse(string $type, string $body): void
{
  header('Content-Type: ' . $type);
  if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    return;
  }
  if ($type !== 'application/json') {
    mb_substitute_character('entity');
    $body = mb_convert_encoding($body, 'CP932', 'UTF-8');
  }
  echo $body;
}

function compatMenu(string $path, array $config, string $configFile): void
{
  if ($config['public_url'] === null) {
    respond(503, '公開 URL が設定されていません。');
  }
  $base = rtrim($config['public_url'], '/') . '/';
  $html = $path === '/bbsmenu.html';
  $body = '<!doctype html><html lang="ja"><head><meta charset="Shift_JIS">'
    . '<title>BBS MENU</title></head><body><b>hoshibbs</b><br>' . "\n";
  $entries = [];
  foreach ($config['boards'] as $id => $name) {
    $url = $base . rawurlencode((string) $id) . '/';
    if ($html) {
      $body .= '<a href="' . compatText($url) . '">' . compatText($name) . '</a><br>' . "\n";
    } else {
      $entries[] = ['category' => 1, 'category_name' => 'hoshibbs', 'category_order' => count($entries) + 1,
        'url' => $url, 'directory_name' => (string) $id, 'board_name' => $name];
    }
  }
  if ($html) {
    compatResponse('text/html; charset=Shift_JIS', $body . "</body></html>\n");
    return;
  }
  compatResponse('application/json', json_encode([
    'last_modify' => filemtime($configFile),
    'menu_list' => [['category_number' => '1', 'category_name' => 'hoshibbs',
      'category_total' => count($entries), 'category_content' => $entries]],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
}

function compatDatLine(array $post, string $title = ''): string
{
  $date = '';
  if ($post['posted_at'] !== null) {
    $time = (new DateTimeImmutable('@' . $post['posted_at']))->setTimezone(new DateTimeZone('Asia/Tokyo'));
    $weekday = ['日', '月', '火', '水', '木', '金', '土'][(int) $time->format('w')];
    $date = $time->format('Y/m/d') . '(' . $weekday . ') ' . $time->format('H:i:s') . '.00';
  }
  return '名無しさん<><>' . $date . '<> ' . compatText($post['body'], true) . ' <>' . $title . "\n";
}

function serveReadCompatibility(string $method, string $path, string $prefix, array $config, string $configFile): void
{
  if ($method === 'POST') {
    header('Allow: GET, HEAD');
    respond(405, '許可されていないメソッドです。');
  }
  if ($path === '/bbsmenu.html' || $path === '/bbsmenu.json') {
    compatMenu($path, $config, $configFile);
    return;
  }
  $pattern = '~\A/(?:(?<board>[a-z0-9][a-z0-9-]*)/(?:(?<subject>subject\.txt)|dat/(?<dat>[0-9]{10})\.dat)?'
    . '|test/read\.cgi/(?<webboard>[a-z0-9][a-z0-9-]*)/(?<webkey>[0-9]{10})/)\z~';
  if (!preg_match($pattern, $path, $route, PREG_UNMATCHED_AS_NULL)) {
    respond(404, 'ページが見つかりません。');
  }
  $board = $route['board'] ?? $route['webboard'];
  if (!isset($config['boards'][$board])) {
    respond(404, '掲示板が見つかりません。');
  }
  $key = $route['dat'] ?? $route['webkey'];
  $webUrl = $prefix . '/index.php?board=' . rawurlencode($board);
  if ($key === null && $route['subject'] === null) {
    header('Location: ' . $webUrl);
    return;
  }
  $db = openDatabase($config['database']);
  if ($route['subject'] !== null) {
    $query = $db->prepare('SELECT public_key, title,
      1 + (SELECT COUNT(*) FROM replies WHERE thread_id = threads.id) AS posts
      FROM threads WHERE board = ? ORDER BY public_key DESC');
    $query->execute([$board]);
    $body = '';
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
      $body .= $row['public_key'] . '.dat<>' . compatText($row['title']) . ' (' . $row['posts'] . ")\n";
    }
    compatResponse('text/plain; charset=Shift_JIS', $body);
    return;
  }
  $query = $db->prepare('SELECT id, title, body, posted_at FROM threads WHERE board = ? AND public_key = ?');
  $query->execute([$board, $key]);
  $thread = $query->fetch(PDO::FETCH_ASSOC);
  if ($thread === false) {
    respond(404, 'スレッドが見つかりません。');
  }
  if ($route['webkey'] !== null) {
    header('Location: ' . $webUrl . '&thread=' . $thread['id']);
    return;
  }
  $body = compatDatLine($thread, compatText($thread['title']));
  $query = $db->prepare('SELECT body, posted_at FROM replies WHERE thread_id = ? ORDER BY id');
  $query->execute([$thread['id']]);
  while ($reply = $query->fetch(PDO::FETCH_ASSOC)) {
    $body .= compatDatLine($reply);
  }
  compatResponse('text/plain; charset=Shift_JIS', $body);
}
