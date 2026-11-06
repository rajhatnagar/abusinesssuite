<?php
/**
 * Automated Business Suite - Lead Submission & Dual-Recipient Dispatch API
 *
 * Persists leads to database and dispatches HTML notification emails to:
 * - contact@csssofttech.com
 * - info@abusinesssuite.com
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Allow CORS for local testing if necessary
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Please use POST.']);
    exit;
}

// 1. Parse Input Data (Supports JSON, application/x-www-form-urlencoded, and multipart/form-data)
$rawInput = file_get_contents('php://input');
$data = [];

$contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';
if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode($rawInput, true) ?: [];
} else {
    $data = $_POST;
    // Handle nested Elementor form_fields array if sent via urlencoded/multipart
    if (isset($data['form_fields']) && is_array($data['form_fields'])) {
        foreach ($data['form_fields'] as $key => $val) {
            $data[$key] = $val;
        }
    }
}

// Normalize and sanitize fields
function sanitize_val($val) {
    if (is_array($val)) {
        return sanitize_val(implode(', ', $val));
    }
    return htmlspecialchars(strip_tags(trim((string)$val)), ENT_QUOTES, 'UTF-8');
}

$name        = sanitize_val($data['name'] ?? $data['form_fields[name]'] ?? '');
$email       = filter_var(trim($data['email'] ?? $data['form_fields[email]'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone       = sanitize_val($data['phone'] ?? $data['form_fields[phone]'] ?? '');
$company     = sanitize_val($data['company'] ?? $data['company_name'] ?? $data['form_fields[company_name]'] ?? '');
$subject     = sanitize_val($data['subject'] ?? $data['form_fields[subject]'] ?? 'New Website Inquiry');
$message     = sanitize_val($data['message'] ?? $data['form_fields[message]'] ?? '');
$source_page = sanitize_val($data['source_page'] ?? $data['referer_title'] ?? $_SERVER['HTTP_REFERER'] ?? 'Website Direct');
$ip_address  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$user_agent  = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
$created_at  = gmdate('Y-m-d H:i:s');

// 2. Validate Required Fields
if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide your name.']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid business email address.']);
    exit;
}

// 3. Database Persistence (SQLite with auto-migration + optional MySQL support)
$dbPersisted = false;
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
    // Protect data directory from direct HTTP access
    @file_put_contents($dataDir . '/.htaccess', "Order Deny,Allow\nDeny from all\nRequire all denied\n");
}

$dbPath = $dataDir . '/.leads.db';

try {
    $pdo = new PDO("sqlite:{$dbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT,
            company TEXT,
            subject TEXT,
            message TEXT,
            source_page TEXT,
            ip_address TEXT,
            user_agent TEXT,
            created_at DATETIME
        )
    ");

    $stmt = $pdo->prepare("
        INSERT INTO leads (name, email, phone, company, subject, message, source_page, ip_address, user_agent, created_at)
        VALUES (:name, :email, :phone, :company, :subject, :message, :source_page, :ip_address, :user_agent, :created_at)
    ");
    $stmt->execute([
        ':name'        => $name,
        ':email'       => $email,
        ':phone'       => $phone,
        ':company'     => $company,
        ':subject'     => $subject,
        ':message'     => $message,
        ':source_page' => $source_page,
        ':ip_address'  => $ip_address,
        ':user_agent'  => $user_agent,
        ':created_at'  => $created_at
    ]);
    $dbPersisted = true;
} catch (Exception $e) {
    error_log("SQLite persistence error: " . $e->getMessage());
}

// Optional secondary append to secure CSV log as extra reliability layer
$csvPath = $dataDir . '/.leads_backup.csv';
$csvRow = [$created_at, $name, $email, $phone, $company, $subject, str_replace(["\r", "\n"], ' ', $message), $source_page, $ip_address];
$fp = @fopen($csvPath, 'a');
if ($fp) {
    fputcsv($fp, $csvRow);
    fclose($fp);
}

// 4. Dual-Recipient Email Dispatch
$recipients = [
    'contact@csssofttech.com',
    'info@abusinesssuite.com'
];

$emailSubject = "New Lead: " . ($company ? "{$company} - " : "") . "{$name} ({$subject})";

$htmlBody = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f7f9fc; margin: 0; padding: 20px; color: #1f2937; }
    .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; }
    .header { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); color: #ffffff; padding: 24px 30px; text-align: left; }
    .header h1 { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.5px; }
    .header p { margin: 4px 0 0; font-size: 13px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; }
    .content { padding: 30px; }
    .field-group { margin-bottom: 20px; }
    .field-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #6b7280; font-weight: 600; margin-bottom: 4px; }
    .field-value { font-size: 15px; color: #111827; font-weight: 500; }
    .message-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-top: 8px; font-size: 14px; line-height: 1.6; color: #374151; white-space: pre-wrap; }
    .meta-footer { background: #f3f4f6; padding: 16px 30px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>New Lead Received</h1>
      <p>Automated Business Suite — Lead Notification</p>
    </div>
    <div class="content">
      <div class="field-group">
        <div class="field-label">Full Name</div>
        <div class="field-value">' . htmlspecialchars($name) . '</div>
      </div>
      <div class="field-group">
        <div class="field-label">Business Email</div>
        <div class="field-value"><a href="mailto:' . htmlspecialchars($email) . '" style="color: #6904F2; text-decoration: none;">' . htmlspecialchars($email) . '</a></div>
      </div>
      <div class="field-group">
        <div class="field-label">Phone Number</div>
        <div class="field-value">' . ($phone ? htmlspecialchars($phone) : '<span style="color: #9ca3af;">Not provided</span>') . '</div>
      </div>
      <div class="field-group">
        <div class="field-label">Company</div>
        <div class="field-value">' . ($company ? htmlspecialchars($company) : '<span style="color: #9ca3af;">Not provided</span>') . '</div>
      </div>
      <div class="field-group">
        <div class="field-label">Service / Subject</div>
        <div class="field-value">' . htmlspecialchars($subject) . '</div>
      </div>
      <div class="field-group">
        <div class="field-label">Message / Details</div>
        <div class="message-box">' . (!empty($message) ? htmlspecialchars($message) : 'No message text provided.') . '</div>
      </div>
    </div>
    <div class="meta-footer">
      <strong>Source Page:</strong> ' . htmlspecialchars($source_page) . '<br>
      <strong>Received:</strong> ' . htmlspecialchars($created_at) . ' UTC &bull; <strong>IP:</strong> ' . htmlspecialchars($ip_address) . '
    </div>
  </div>
</body>
</html>';

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: Automated Business Suite <no-reply@abusinesssuite.com>',
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . phpversion()
];

$emailSent = false;
$headersStr = implode("\r\n", $headers);
$toAddresses = implode(', ', $recipients);

if (@mail($toAddresses, $emailSubject, $htmlBody, $headersStr)) {
    $emailSent = true;
} else {
    error_log("Failed to send lead email to: {$toAddresses}");
}

// Return Success Response
http_response_code(200);
echo json_encode([
    'success'      => true,
    'message'      => 'Thank you! Your message has been sent successfully. We will contact you shortly.',
    'db_persisted' => $dbPersisted,
    'email_sent'   => $emailSent
]);

