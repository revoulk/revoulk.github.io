```php
<?php

declare(strict_types=1);

const DATA_FILE = __DIR__ . '/callbacks.json';

header_remove('X-Powered-By');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

function loadCallbacks(): array
{
    if (!file_exists(DATA_FILE)) {
        return [];
    }

    $content = file_get_contents(DATA_FILE);

    if ($content === false || trim($content) === '') {
        return [];
    }

    $data = json_decode($content, true);

    return is_array($data) ? $data : [];
}

function saveCallbacks(array $callbacks): bool
{
    return file_put_contents(
        DATA_FILE,
        json_encode(
            $callbacks,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        ),
        LOCK_EX
    ) !== false;
}

function h(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| POST: Receive callback
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        jsonResponse([
            'error' => 'Empty request body'
        ], 400);
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        jsonResponse([
            'error' => 'Invalid JSON'
        ], 400);
    }

    $callback = [
        'time'       => date('c'),
        'source_ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
        'command'    => $data['command'] ?? null,
        'output'     => $data['output'] ?? null,
        'user-agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];

    $callbacks = loadCallbacks();

    array_unshift($callbacks, $callback);

    /*
     * Keep the latest 100 callbacks.
     */
    $callbacks = array_slice($callbacks, 0, 100);

    if (!saveCallbacks($callbacks)) {
        jsonResponse([
            'error' => 'Failed to save callback'
        ], 500);
    }

    jsonResponse([
        'status' => 'ok',
        'callback' => $callback
    ]);
}

/*
|--------------------------------------------------------------------------
| GET: Clear callbacks
|--------------------------------------------------------------------------
|
| Use:
| callback.php?action=clear
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    ($_GET['action'] ?? '') === 'clear'
) {

    /*
     * Simple protection against accidental clearing.
     * Add your own token before using this in a real environment.
     */
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        http_response_code(400);

        echo 'Missing confirmation.';
        exit;
    }

    saveCallbacks([]);

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/*
|--------------------------------------------------------------------------
| GET: Dashboard
|--------------------------------------------------------------------------
*/

$callbacks = loadCallbacks();

$totalCallbacks = count($callbacks);

$uniqueIPs = [];

foreach ($callbacks as $callback) {
    if (!empty($callback['source_ip'])) {
        $uniqueIPs[$callback['source_ip']] = true;
    }
}

$totalIPs = count($uniqueIPs);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        http-equiv="refresh"
        content="10"
    >

    <title>RCE Callback Dashboard</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #0b0f14;
            color: #e6edf3;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .container {
            max-width: 1500px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .title h1 {
            margin: 0;
            font-size: 25px;
        }

        .title p {
            margin: 6px 0 0;
            color: #8b949e;
            font-size: 14px;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .button {
            display: inline-block;
            padding: 9px 14px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #30363d;
            color: #e6edf3;
            background: #161b22;
        }

        .button:hover {
            background: #21262d;
        }

        .button.danger {
            color: #ff7b72;
        }

        .stats {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 15px;
            margin-bottom: 20px;
        }

        .stat {
            padding: 18px;
            background: #11161d;
            border: 1px solid #30363d;
            border-radius: 9px;
        }

        .stat-label {
            color: #8b949e;
            font-size: 12px;
            margin-bottom: 7px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
        }

        .table-wrapper {
            overflow-x: auto;
            background: #11161d;
            border: 1px solid #30363d;
            border-radius: 9px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            text-align: left;
            padding: 13px 15px;
            background: #161b22;
            color: #8b949e;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        td {
            padding: 14px 15px;
            border-top: 1px solid #21262d;
            vertical-align: top;
            font-size: 13px;
        }

        tr:hover td {
            background: #151a21;
        }

        .time {
            white-space: nowrap;
            color: #8b949e;
        }

        .ip {
            color: #79c0ff;
            font-family: monospace;
            white-space: nowrap;
        }

        .command {
            color: #7ee787;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-word;
            max-width: 250px;
        }

        .output {
            color: #e6edf3;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-word;
            min-width: 300px;
            max-width: 600px;
        }

        .ua {
            color: #8b949e;
            max-width: 250px;
            word-break: break-word;
        }

        .empty {
            padding: 60px 20px;
            text-align: center;
            color: #8b949e;
        }

        .footer {
            margin-top: 15px;
            color: #6e7681;
            font-size: 12px;
            text-align: right;
        }

        @media (max-width: 700px) {
            body {
                padding: 15px;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">

        <div class="title">
            <h1>RCE Callback Dashboard</h1>

            <p>
                Passive callback collector · Auto refresh: 10s
            </p>
        </div>

        <div class="actions">

            <a
                class="button"
                href=""
            >
                Refresh
            </a>

            <a
                class="button danger"
                href="?action=clear&confirm=yes"
                onclick="return confirm('Clear all callbacks?');"
            >
                Clear
            </a>

        </div>

    </div>

    <div class="stats">

        <div class="stat">

            <div class="stat-label">
                Total Callbacks
            </div>

            <div class="stat-value">
                <?= h($totalCallbacks) ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                Unique Source IPs
            </div>

            <div class="stat-value">
                <?= h($totalIPs) ?>
            </div>

        </div>

    </div>

    <div class="table-wrapper">

        <?php if (empty($callbacks)): ?>

            <div class="empty">
                No callbacks received yet.
            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>
                        <th>Time</th>
                        <th>Source IP</th>
                        <th>Command</th>
                        <th>Output</th>
                        <th>User-Agent</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($callbacks as $callback): ?>

                    <tr>

                        <td class="time">
                            <?= h($callback['time'] ?? '') ?>
                        </td>

                        <td class="ip">
                            <?= h($callback['source_ip'] ?? '') ?>
                        </td>

                        <td class="command">
                            <?= h($callback['command'] ?? '') ?>
                        </td>

                        <td class="output">
                            <?= h($callback['output'] ?? '') ?>
                        </td>

                        <td class="ua">
                            <?= h($callback['user-agent'] ?? '') ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

    <div class="footer">
        Last refresh:
        <?= h(date('Y-m-d H:i:s')) ?>
    </div>

</div>

</body>
</html>
```
