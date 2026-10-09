<?php

// 只使用临时配置和合成凭据，通过真实 HTTP 请求验证 Dashboard 认证。

/**
 * 请求 Dashboard 并读取状态、响应头和正文。
 *
 * @param string $url 请求地址。
 * @param string|null $credentials 合成的 Basic Auth 凭据，未认证时为 null。
 * @return array 包含 status、headers 和 body 的响应。
 */
function requestDashboard($url, $credentials = null)
{
    $headers = $credentials === null ? [] : ['Authorization: Basic ' . base64_encode($credentials)];
    $context = stream_context_create(['http' => [
        'ignore_errors' => true,
        'timeout' => 2,
        'header' => implode("\r\n", $headers),
    ]]);
    $stream = fopen($url, 'r', false, $context);
    if ($stream === false) {
        throw new RuntimeException('Dashboard request failed');
    }
    $metadata = stream_get_meta_data($stream);
    $body = stream_get_contents($stream);
    fclose($stream);
    preg_match('/^HTTP\/\S+ (\d+)/', $metadata['wrapper_data'][0], $matches);
    return ['status' => (int) $matches[1], 'headers' => $metadata['wrapper_data'], 'body' => $body];
}

/**
 * 检查响应状态及内容，拒绝访问时确认页面和数据库错误均未泄露。
 *
 * @param array $response 实际 HTTP 响应。
 * @param array $expected 用例名称、预期状态和正文。
 * @return void
 */
function checkDashboardResponse($response, $expected)
{
    if ($response['status'] !== $expected['status'] || strpos($response['body'], $expected['body']) === false) {
        throw new RuntimeException($expected['name'] . ': unexpected status or body (HTTP ' . $response['status'] . ')');
    }
    if ($expected['status'] !== 200 && $response['body'] !== $expected['body']) {
        throw new RuntimeException($expected['name'] . ': denied response contains unexpected output');
    }
    $challenge = preg_grep('/^WWW-Authenticate: Basic /i', $response['headers']);
    if (($expected['status'] === 401) !== (count($challenge) > 0)) {
        throw new RuntimeException($expected['name'] . ': incorrect authentication challenge');
    }
    if (!preg_grep('/^Cache-Control: no-store$/i', $response['headers'])) {
        throw new RuntimeException($expected['name'] . ': response can be cached');
    }
    echo 'PASS: ' . $expected['name'] . "\n";
}

$directory = sys_get_temp_dir() . '/mtr-dashboard-auth-' . uniqid();
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('Cannot create test directory');
}
$process = null;
$pipes = [];
$exitCode = 0;

try {
    if (!copy(dirname(__DIR__) . '/index.php', $directory . '/index.php')) {
        throw new RuntimeException('Cannot copy Dashboard entry point');
    }
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($listener === false) {
        throw new RuntimeException('Cannot allocate test port');
    }
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    $process = proc_open(
        escapeshellarg(PHP_BINARY) . ' -d opcache.enable=0 -S ' . escapeshellarg($address) . ' -t ' . escapeshellarg($directory),
        [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start test server');
    }
    fclose($pipes[0]);
    $deadline = microtime(true) + 5;
    do {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($connection !== false) {
            fclose($connection);
            break;
        }
        if (!proc_get_status($process)['running'] || microtime(true) >= $deadline) {
            throw new RuntimeException('Test server did not become ready');
        }
        usleep(10000);
    } while (true);

    $baseUrl = 'http://' . $address;
    $paths = ['/', '/index.php', '/?route=get&from=1970-01-01&to=2099-01-01&category=%20'];
    $cases = [
        'empty credentials' => ['enable' => true, 'username' => '', 'password' => ''],
        'disabled' => ['enable' => false, 'username' => 'test-user', 'password' => 'test-password'],
        'missing enable' => ['username' => 'test-user', 'password' => 'test-password'],
        'missing credentials' => ['enable' => true],
        'empty username' => ['enable' => true, 'username' => '', 'password' => 'test-password'],
        'empty password' => ['enable' => true, 'username' => 'test-user', 'password' => ''],
        'whitespace credentials' => ['enable' => true, 'username' => ' ', 'password' => "\t"],
        'invalid credential types' => ['enable' => true, 'username' => 123, 'password' => ['test-password']],
        'configured' => ['enable' => true, 'username' => 'test-user', 'password' => 'test-password'],
        'zero username' => ['enable' => true, 'username' => '0', 'password' => 'test-password'],
        'numeric-looking password' => ['enable' => true, 'username' => 'test-user', 'password' => '0e12345'],
    ];
    foreach ($cases as $name => $dashboard) {
        $fixture = ['database' => [], 'dashboard' => $dashboard + ['categories' => ['']]];
        if (file_put_contents($directory . '/config.inc.php', '<?php return ' . var_export($fixture, true) . ';') === false) {
            throw new RuntimeException('Cannot write test configuration');
        }
        $status = in_array($name, ['disabled', 'missing enable'], true) ? 403 :
            (in_array($name, ['configured', 'zero username', 'numeric-looking password'], true) ? 401 : 503);
        $body = $status === 403 ? 'Dashboard is disabled' :
            ($status === 503 ? 'Dashboard authentication is not configured' : 'Access denied');
        foreach ($paths as $path) {
            checkDashboardResponse(requestDashboard($baseUrl . $path), ['name' => $name . ' ' . $path, 'status' => $status, 'body' => $body]);
        }
        // 即使请求携带凭据，关闭或配置不完整的 Dashboard 也必须拒绝访问。
        $credentials = 'test-user:test-password';
        if ($name === 'numeric-looking password') {
            $credentials = 'test-user:0e54321';
        } elseif ($name === 'configured' || $name === 'zero username') {
            $credentials = 'test-user:wrong-password';
        }
        checkDashboardResponse(requestDashboard($baseUrl . $paths[2], $credentials), ['name' => $name . ' with rejected credentials', 'status' => $status, 'body' => $body]);
        if ($status === 401) {
            checkDashboardResponse(requestDashboard($baseUrl . '/', $dashboard['username'] . ':' . $dashboard['password']),
                ['name' => $name . ' with correct credentials', 'status' => 200, 'body' => '<title>MTR Database Dashboard</title>']);
        }
    }
} catch (Exception $exception) {
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    foreach (glob($directory . '/*') as $file) {
        unlink($file);
    }
    rmdir($directory);
}
exit($exitCode);
