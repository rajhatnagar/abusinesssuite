<?php
/**
 * Automated Business Suite - Protected Leads Dashboard & CSV Exporter
 * Access via: /api/view-leads.php?key=abs2026
 */

$accessKey = 'abs2026';
$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $accessKey) {
    http_response_code(403);
    echo "<h1>403 Forbidden</h1><p>Access key required. Use ?key=...</p>";
    exit;
}

$dbPath = __DIR__ . '/data/.leads.db';
$leads = [];

if (file_exists($dbPath)) {
    try {
        $pdo = new PDO("sqlite:{$dbPath}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query("SELECT * FROM leads ORDER BY id DESC");
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// CSV Export Mode
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="leads_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Company', 'Subject', 'Message', 'Source Page', 'IP Address', 'Created At (UTC)']);
    foreach ($leads as $lead) {
        fputcsv($out, [
            $lead['id'] ?? '',
            $lead['name'] ?? '',
            $lead['email'] ?? '',
            $lead['phone'] ?? '',
            $lead['company'] ?? '',
            $lead['subject'] ?? '',
            $lead['message'] ?? '',
            $lead['source_page'] ?? '',
            $lead['ip_address'] ?? '',
            $lead['created_at'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Leads Dashboard — Automated Business Suite</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 24px; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #334155; }
    h1 { margin: 0; font-size: 22px; font-weight: 700; color: #fff; }
    .btn { background: #6904F2; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-block; }
    .btn:hover { background: #5502c9; }
    .card { background: #1e293b; border-radius: 8px; border: 1px solid #334155; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
    th { background: #0f172a; padding: 12px 16px; font-weight: 600; color: #94a3b8; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
    td { padding: 12px 16px; border-top: 1px solid #334155; color: #e2e8f0; vertical-align: top; }
    tr:hover { background: #243248; }
    .badge { background: #3b82f620; color: #60a5fa; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
    .empty { padding: 40px; text-align: center; color: #64748b; }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <h1>Captured Leads (<?= count($leads) ?>)</h1>
      <p style="margin: 4px 0 0; color: #94a3b8; font-size: 13px;">Notifications sent to: contact@csssofttech.com &bull; info@abusinesssuite.com</p>
    </div>
    <div>
      <a href="?key=<?= htmlspecialchars($providedKey) ?>&export=csv" class="btn">📥 Export to CSV</a>
    </div>
  </div>

  <div class="card">
    <?php if (empty($leads)): ?>
      <div class="empty">No leads recorded yet. Submissions from any site form will appear here.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Date (UTC)</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Company</th>
            <th>Subject</th>
            <th>Message</th>
            <th>Source Page</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($leads as $l): ?>
            <tr>
              <td>#<?= htmlspecialchars($l['id']) ?></td>
              <td style="white-space: nowrap;"><?= htmlspecialchars($l['created_at']) ?></td>
              <td><strong><?= htmlspecialchars($l['name']) ?></strong></td>
              <td><a href="mailto:<?= htmlspecialchars($l['email']) ?>" style="color: #a78bfa;"><?= htmlspecialchars($l['email']) ?></a></td>
              <td><?= htmlspecialchars($l['phone'] ?: '—') ?></td>
              <td><?= htmlspecialchars($l['company'] ?: '—') ?></td>
              <td><span class="badge"><?= htmlspecialchars($l['subject'] ?: 'Inquiry') ?></span></td>
              <td style="max-width: 300px;"><?= nl2br(htmlspecialchars($l['message'])) ?></td>
              <td style="font-size: 11px; color: #94a3b8;"><?= htmlspecialchars($l['source_page']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>

