<?php
session_start();
require_once 'config.php';
require_once 'dompdf/autoload.inc.php'; // Path to dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    die("Access Denied.");
}

$transaction_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// 1. Fetch Transaction Details
$stmt = $pdo->prepare("SELECT p.*, u.fullname, u.email FROM payments p 
                       JOIN users u ON p.user_id = u.id 
                       WHERE p.id = ? AND p.user_id = ?");
$stmt->execute([$transaction_id, $user_id]);
$t = $stmt->fetch();

if (!$t) { die("Transaction not found."); }

// 2. Setup Dompdf Options
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);

// 3. Create the HTML Template
$html = '
<style>
    body { font-family: sans-serif; color: #333; }
    .receipt-box { width: 100%; border: 1px solid #eee; padding: 30px; }
    .header { border-bottom: 2px solid #4a90e2; padding-bottom: 20px; margin-bottom: 20px; }
    .title { color: #4a90e2; font-size: 28px; font-weight: bold; }
    .details { margin-bottom: 40px; }
    .table { width: 100%; border-collapse: collapse; }
    .table th { background: #f8f9fa; text-align: left; padding: 10px; border-bottom: 1px solid #eee; }
    .table td { padding: 10px; border-bottom: 1px solid #eee; }
    .total { font-size: 20px; font-weight: bold; text-align: right; margin-top: 20px; }
    .footer { margin-top: 50px; font-size: 12px; color: #888; text-align: center; }
</style>

<div class="receipt-box">
    <div class="header">
        <span class="title">TeachFinder Receipt</span>
        <div style="float: right; text-align: right;">
            <p>Date: ' . date("d M Y", strtotime($t['created_at'])) . '<br>
            Receipt #: TF-' . $t['id'] . '</p>
        </div>
    </div>

    <div class="details">
        <strong>Billed To:</strong><br>
        ' . htmlspecialchars($t['fullname']) . '<br>
        ' . htmlspecialchars($t['email']) . '
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Transaction ID</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>' . htmlspecialchars($t['description']) . '</td>
                <td>' . ($t['order_id'] ?: "N/A") . '</td>
                <td>$' . number_format($t['amount'], 2) . '</td>
            </tr>
        </tbody>
    </table>

    <div class="total">
        Total Paid: $' . number_format($t['amount'], 2) . '
    </div>

    <div class="footer">
        Thank you for using TeachFinder! If you have any questions, please contact support@teachfinder.com
    </div>
</div>';

// 4. Load HTML and Render
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// 5. Output to Browser (Force Download)
$dompdf->stream("Receipt_TF_" . $t['id'] . ".pdf", array("Attachment" => 1));