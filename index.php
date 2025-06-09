<?php
// temp_inbox.php - Full mbox-compatible temporary inbox viewer

date_default_timezone_set('UTC');
$domain = 'wcsd.io';
$mboxFile = '/var/mail/web';
$perPage = 10;
$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';
$download = isset($_GET['download']);
$truncate = isset($_GET['truncate']) && $_GET['truncate'] === '1';

if (!isset($_GET['user']) || !preg_match('/^[a-z0-9]{8}$/', $_GET['user'])) {
    $randomUser = strtolower(substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 8));
    $redirect = "?user=$randomUser" . ($debugMode ? '&debug=1' : '');
    header("Location: $redirect");
    exit;
}

$user = $_GET['user'];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

function parseMbox($filePath, $user, $domain, $debug) {
    if (!file_exists($filePath)) return [];

    $messages = [];
    $lines = file($filePath);
    $msg = '';
    foreach ($lines as $line) {
        if (substr($line, 0, 5) === 'From ') {
            if ($msg) $messages[] = $msg;
            $msg = $line;
        } else {
            $msg .= $line;
        }
    }
    if ($msg) $messages[] = $msg;

    $filtered = [];
    foreach ($messages as $idx => $raw) {
        $ts = extractMboxTimestamp($raw);
        if (time() - $ts > 86400) continue; // older than 24h

        if (!$debug) {
            if (!preg_match('/^To:\s*(.+?)$/mi', $raw, $toMatch)) continue;
            if (stripos($toMatch[1], "$user@$domain") === false) continue;
        }

        $filtered[] = [
            'id' => $idx,
            'timestamp' => $ts,
            'contents' => $raw,
        ];
    }

    usort($filtered, fn($a, $b) => $b['timestamp'] - $a['timestamp']);
    return $filtered;
}

function extractMboxTimestamp($msg) {
    if (preg_match('/^From .* ([A-Za-z]{3} [A-Za-z]{3}  ?\d{1,2} \d{2}:\d{2}:\d{2} \d{4})$/m', $msg, $match)) {
        return strtotime($match[1]) ?: filemtime($GLOBALS['mboxFile']);
    }
    return filemtime($GLOBALS['mboxFile']);
}

if ($truncate) {
    file_put_contents($mboxFile, '');
    header("Location: ?user=$user" . ($debugMode ? '&debug=1' : ''));
    exit;
}

$allMessages = parseMbox($mboxFile, $user, $domain, $debugMode);
if ($download && isset($_GET['id'])) {
    $msgId = intval($_GET['id']);
    if (isset($allMessages[$msgId])) {
        header("Content-Type: message/rfc822");
        header("Content-Disposition: attachment; filename=message_$msgId.eml");
        echo $allMessages[$msgId]['contents'];
        exit;
    }
}

$total = count($allMessages);
$totalPages = max(1, ceil($total / $perPage));
$start = ($page - 1) * $perPage;
$messagesToShow = array_slice($allMessages, $start, $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inbox <?php echo $debugMode ? '[DEBUG]' : ''; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta http-equiv="refresh" content="30">
    <style> body { padding: 2rem; } pre { white-space: pre-wrap; word-wrap: break-word; } </style>
</head>
<body>
<div class="container">
    <h1>Temporary Inbox <?php echo $debugMode ? '<span class="badge bg-danger">DEBUG MODE</span>' : ''; ?></h1>
    <p><code><?php echo $user . '@' . $domain; ?></code></p>
    <p>
        <a href="?user=<?php echo $user; ?>">Refresh</a> |
        <a href="?user=<?php echo $user; ?>&debug=1">Debug</a> |
        <a href="?user=<?php echo $user; ?>&truncate=1" onclick="return confirm('Clear all messages?');">Clear Mailbox</a>
    </p>

<?php if (empty($messagesToShow)): ?>
    <div class="alert alert-info">No messages yet.</div>
<?php else: ?>
    <?php foreach ($messagesToShow as $idx => $email):
        $body = htmlspecialchars($email['contents']);
        preg_match('/^From:\s*(.+?)$/mi', $email['contents'], $fromMatch);
        preg_match('/^To:\s*(.+?)$/mi', $email['contents'], $toMatch);
        preg_match('/^Subject:\s*(.+?)$/mi', $email['contents'], $subjectMatch);
        $collapseId = "msg$idx";
    ?>
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($subjectMatch[1] ?? '(No Subject)'); ?></h5>
                <h6 class="card-subtitle mb-2 text-muted">
                    From: <?php echo htmlspecialchars($fromMatch[1] ?? '(Unknown)'); ?><br>
                    To: <?php echo htmlspecialchars($toMatch[1] ?? '(Unknown)'); ?><br>
                    Time: <?php echo date('Y-m-d H:i:s', $email['timestamp']); ?>
                </h6>
                <a href="?user=<?php echo $user; ?>&id=<?php echo $email['id']; ?>&download=1" class="btn btn-sm btn-outline-secondary">Download .eml</a>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>">Show Body</button>
                <div id="<?php echo $collapseId; ?>" class="collapse mt-3">
                    <pre><?php echo $body; ?></pre>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <nav><ul class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++):
            $link = "?user=$user&page=$i" . ($debugMode ? "&debug=1" : '');
            echo "<li class='page-item ".($i==$page?'active':'')."'><a class='page-link' href='$link'>$i</a></li>";
        endfor; ?>
    </ul></nav>
<?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
