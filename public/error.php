<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/bootstrap.php';

$code = (int)($_GET['code'] ?? 404);
$code = in_array($code, [403, 404], true) ? $code : 404;

render_restricted_page(
    $code,
    $code === 403 ? 'Access needed' : 'This link may be broken',
    $code === 403
        ? 'This area needs the right account access. Please log in with the correct account, go home, or open your dashboard.'
        : 'The link you opened may be broken or the page may have moved. We have sent this to our technical team to review.',
    ['reason' => 'web_server_error_document', 'audit' => false]
);
