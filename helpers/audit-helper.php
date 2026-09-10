<?php
declare(strict_types=1);

if (!function_exists('getClientIP')) {
    function getClientIP(): string
    {
        $sources = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        foreach ($sources as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $comma = strpos($ip, ',');
                    if ($comma !== false) {
                        $ip = trim(substr($ip, 0, $comma));
                    }
                }
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}

if (!function_exists('getUserAgent')) {
    function getUserAgent(): string
    {
        return substr(trim($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }
}

if (!function_exists('logActivity')) {
    function logActivity(
        ?int    $userId,
        string  $userName,
        string  $userRole,
        string  $module,
        string  $action,
                $referenceId = null,
        ?string $description = null,
        ?PDO    $pdo = null
    ): bool {
        if ($pdo === null) {
            if (!function_exists('getDbConnection')) {
                return false;
            }
            $pdo = getDbConnection();
        }

        $ip       = getClientIP();
        $userAgent = getUserAgent();
        $refStr   = $referenceId !== null ? (string) $referenceId : null;

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, user_name, user_role, module, action, reference_id, description, ip_address, user_agent, created_at)
            VALUES (:user_id, :user_name, :user_role, :module, :action, :reference_id, :description, :ip_address, :user_agent, NOW())
        ");
        return $stmt->execute([
            ':user_id'      => $userId,
            ':user_name'    => $userName,
            ':user_role'    => $userRole,
            ':module'       => $module,
            ':action'       => $action,
            ':reference_id' => $refStr,
            ':description'  => $description,
            ':ip_address'   => $ip,
            ':user_agent'   => $userAgent,
        ]);
    }
}
