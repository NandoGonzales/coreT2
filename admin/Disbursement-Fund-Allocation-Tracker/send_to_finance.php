<?php
/**
 * ============================================================
 * Send to Finance AJAX Handler
 * Path: /admin/Disbursement-Fund-Allocation-Tracker/send_to_finance.php
 *
 * Tinatawag ng "Send to Finance" button sa disbursement tracker.
 * Nagse-send ng disbursement records sa Financial Team API.
 * ============================================================
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

// ─── AUTH CHECK ───────────────────────────────────────────────
if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId   = $_SESSION['userdata']['user_id']   ?? 0;
$userName = $_SESSION['userdata']['full_name'] ?? 'Admin';

// ─── CONFIG ───────────────────────────────────────────────────
// I-update ito ng financial team's actual endpoint URL at API key
define('FINANCE_API_URL', 'https://core2.microfinancial-1.com/api/financial/disbursements.php');
define('FINANCE_API_KEY', '');  // ← Ilagay ang API key dito pagkatapos ma-generate

try {
    $raw    = file_get_contents('php://input');
    $body   = json_decode($raw, true);
    $action = trim($body['action'] ?? $_POST['action'] ?? '');

    // ─── ACTION: generate_key ──────────────────────────────
    // Para gumawa ng bagong API key para sa financial team
    if ($action === 'generate_key') {
        // Only Super Admin can generate keys
        $role = $_SESSION['userdata']['role'] ?? '';
        if ($role !== 'Super Admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only Super Admin can generate API keys']);
            exit;
        }

        $label = trim($body['label'] ?? 'Financial Team');

        // Ensure table exists
        $conn->query("
            CREATE TABLE IF NOT EXISTS financial_api_keys (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                api_key     VARCHAR(64) NOT NULL UNIQUE,
                label       VARCHAR(100) DEFAULT 'Financial Team',
                is_active   TINYINT(1) DEFAULT 1,
                created_by  INT DEFAULT NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at  DATETIME DEFAULT NULL,
                last_used   DATETIME DEFAULT NULL
            )
        ");

        // Generate secure key
        $newKey = bin2hex(random_bytes(32)); // 64-char hex key

        $stmt = $conn->prepare("
            INSERT INTO financial_api_keys (api_key, label, created_by)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('ssi', $newKey, $label, $userId);
        $stmt->execute();
        $stmt->close();

        while (@ob_end_clean());
        echo json_encode([
            'success'  => true,
            'message'  => 'API key generated successfully',
            'api_key'  => $newKey,
            'label'    => $label,
            'endpoint' => FINANCE_API_URL,
            'instructions' => [
                'GET'  => FINANCE_API_URL . '?api_key=' . $newKey,
                'POST' => 'POST to ' . FINANCE_API_URL . ' with header X-API-Key: ' . $newKey
            ]
        ]);
        exit;
    }

    // ─── ACTION: list_keys ────────────────────────────────
    if ($action === 'list_keys') {
        $result = $conn->query("
            SELECT ak.id, ak.label, ak.api_key, ak.is_active,
                   ak.created_at, ak.last_used, ak.expires_at,
                   u.full_name as created_by
            FROM financial_api_keys ak
            LEFT JOIN users u ON ak.created_by = u.user_id
            ORDER BY ak.created_at DESC
        ");
        $keys = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Mask the key for security (show first 8 + last 4 chars)
                $masked = substr($row['api_key'], 0, 8) . '...' . substr($row['api_key'], -4);
                $keys[] = [
                    'id'         => $row['id'],
                    'label'      => $row['label'],
                    'api_key'    => $masked,
                    'is_active'  => (bool)$row['is_active'],
                    'created_by' => $row['created_by'] ?? 'Admin',
                    'created_at' => $row['created_at'],
                    'last_used'  => $row['last_used'] ?? 'Never',
                    'expires_at' => $row['expires_at'] ?? 'Never'
                ];
            }
        }
        while (@ob_end_clean());
        echo json_encode(['success' => true, 'keys' => $keys]);
        exit;
    }

    // ─── ACTION: send ─────────────────────────────────────
    if ($action === 'send') {
        $disbursementIds = $body['disbursement_ids'] ?? [];

        if (empty($disbursementIds)) {
            throw new Exception('No disbursements selected');
        }

        // Fetch selected disbursement records
        $placeholders = implode(',', array_fill(0, count($disbursementIds), '?'));
        $types        = str_repeat('i', count($disbursementIds));

        $stmt = $conn->prepare("
            SELECT
                d.disbursement_id,
                d.loan_id,
                COALESCE(m.full_name, 'N/A')   AS member_name,
                DATE_FORMAT(d.disbursement_date, '%Y-%m-%d') AS disbursement_date,
                d.amount,
                d.fund_source,
                d.status,
                d.remarks
            FROM disbursements d
            LEFT JOIN members m ON d.member_id = m.member_id
            WHERE d.disbursement_id IN ({$placeholders})
        ");

        if (!$stmt) throw new Exception("Query failed: " . $conn->error);
        $stmt->bind_param($types, ...$disbursementIds);
        $stmt->execute();
        $result = $stmt->get_result();

        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        $stmt->close();

        if (empty($records)) throw new Exception('No records found for selected IDs');

        // Determine which API key to use
        // Try FINANCE_API_KEY constant first, else get latest active key from DB
        $apiKey = FINANCE_API_KEY;
        if (empty($apiKey)) {
            $keyResult = $conn->query("
                SELECT api_key FROM financial_api_keys
                WHERE is_active = 1
                ORDER BY created_at DESC
                LIMIT 1
            ");
            if ($keyResult && $row = $keyResult->fetch_assoc()) {
                $apiKey = $row['api_key'];
            }
        }

        if (empty($apiKey)) {
            throw new Exception('No API key configured. Please generate one first.');
        }

        // Send to Financial API via cURL
        $payload = json_encode([
            'disbursements' => $records,
            'sent_by'       => $userName,
            'sent_at'       => date('Y-m-d H:i:s')
        ]);

        $ch = curl_init(FINANCE_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-API-Key: ' . $apiKey,
                'Accept: application/json'
            ]
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Connection failed: {$curlError}");
        }

        $apiResponse = json_decode($response, true);

        if ($httpCode !== 200 || !($apiResponse['success'] ?? false)) {
            throw new Exception(
                "Financial API error (HTTP {$httpCode}): " .
                ($apiResponse['error'] ?? $apiResponse['message'] ?? 'Unknown error')
            );
        }

        // Log the send action
        $disbIds = implode(',', $disbursementIds);
        $conn->query("
            INSERT INTO financial_send_log
                (sent_by_user_id, disbursement_ids, records_sent, sent_at, api_response)
            VALUES
                ({$userId}, '{$disbIds}', " . count($records) . ", NOW(),
                 '" . $conn->real_escape_string($response) . "')
            -- Auto-create table if not exists handled below
        ");

        // Ensure log table exists
        $conn->query("
            CREATE TABLE IF NOT EXISTS financial_send_log (
                log_id           INT AUTO_INCREMENT PRIMARY KEY,
                sent_by_user_id  INT,
                disbursement_ids TEXT,
                records_sent     INT DEFAULT 0,
                sent_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                api_response     TEXT
            )
        ");

        // Re-insert after table creation
        $conn->query("
            INSERT INTO financial_send_log
                (sent_by_user_id, disbursement_ids, records_sent, api_response)
            VALUES
                ({$userId}, '{$disbIds}', " . count($records) . ",
                 '" . $conn->real_escape_string($response) . "')
        ");

        while (@ob_end_clean());
        echo json_encode([
            'success'      => true,
            'message'      => "Successfully sent " . count($records) . " record(s) to Financial team",
            'records_sent' => count($records),
            'api_response' => $apiResponse
        ]);
        exit;
    }

    throw new Exception("Invalid action: {$action}");

} catch (Exception $e) {
    error_log("send_to_finance.php Error: " . $e->getMessage());
    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}