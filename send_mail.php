<?php
/**
 * send_mail.php — Jareena Printing Press Quotation Mailer
 * ─────────────────────────────────────────────────────────
 * Upload this file to the SAME folder as jareena_quotation.html on cPanel.
 * It uses PHP's built-in mail() which works on all standard cPanel hosting.
 *
 * For better deliverability (avoid spam folder), consider PHPMailer + SMTP.
 * Instructions at the bottom of this file.
 */

// ── Security: only accept POST from same origin ────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');          // tighten to your domain in production
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method Not Allowed']); exit; }

// ── Read JSON body ─────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid JSON']);
    exit;
}

// ── Sanitize inputs ────────────────────────────────────────────────
function clean($v) { return htmlspecialchars(strip_tags(trim((string)$v)), ENT_QUOTES, 'UTF-8'); }

$fname = clean($data['fname']  ?? '');
$lname = clean($data['lname']  ?? '');
$email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$qno   = clean($data['qno']    ?? 'N/A');
$body  = clean($data['body']   ?? '');   // already formatted by JS

// ── Validate ───────────────────────────────────────────────────────
if (!$email) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Invalid email address']);
    exit;
}

// ── Your company sender details — EDIT THESE ──────────────────────
$from_name    = 'Jareena Printing Press LLC';
$from_email   = 'info@jareenaprintingpress.com';   // must match a valid email on your cPanel
$reply_to     = 'info@jareenaprintingpress.com';
$bcc          = 'info@jareenaprintingpress.com';    // keeps a copy in your inbox
// ─────────────────────────────────────────────────────────────────

$subject = "Quotation #{$qno} – Jareena Printing Press LLC";

// ── Plain-text body (replaces escaped chars back) ─────────────────
$plain_body = html_entity_decode($body, ENT_QUOTES, 'UTF-8');

// ── Build an HTML version too ─────────────────────────────────────
$html_lines = nl2br($plain_body);
$html_body  = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#1e293b;line-height:1.7;padding:24px;">
  <div style="max-width:600px;margin:0 auto;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
    <div style="background:#1a56db;padding:20px 28px;">
      <h1 style="color:#fff;font-size:18px;margin:0;">Jareena Printing Press LLC</h1>
      <p style="color:#bfdbfe;font-size:12px;margin:4px 0 0;">P.O.Box 32260, Sharjah – UAE &nbsp;|&nbsp; Tel: 06 565 9485</p>
    </div>
    <div style="padding:28px;">
      <p>Dear <strong>{$fname} {$lname}</strong>,</p>
      <div style="background:#f8fafc;border-left:4px solid #1a56db;padding:16px 20px;border-radius:4px;margin:20px 0;font-family:monospace;font-size:13px;white-space:pre-wrap;">{$plain_body}</div>
      <p style="color:#64748b;font-size:12px;">If you have any questions, please reply to this email or call us directly.</p>
    </div>
    <div style="background:#f1f5f9;padding:14px 28px;font-size:11px;color:#94a3b8;text-align:center;">
      &copy; Jareena Printing Press LLC – www.jareenaprintingpress.com
    </div>
  </div>
</body>
</html>
HTML;

// ── MIME multipart email ───────────────────────────────────────────
$boundary = md5(uniqid(rand(), true));

$headers  = "From: {$from_name} <{$from_email}>\r\n";
$headers .= "Reply-To: {$reply_to}\r\n";
$headers .= "Bcc: {$bcc}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$message  = "--{$boundary}\r\n";
$message .= "Content-Type: text/plain; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
$message .= quoted_printable_encode($plain_body) . "\r\n\r\n";
$message .= "--{$boundary}\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
$message .= quoted_printable_encode($html_body) . "\r\n\r\n";
$message .= "--{$boundary}--";

// ── Send ───────────────────────────────────────────────────────────
$sent = mail($email, $subject, $message, $headers);

if ($sent) {
    echo json_encode(['success'=>true, 'message'=>"Email sent to {$email}"]);
} else {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>'mail() failed. Check server mail config or use SMTP (see comments below).']);
}

/*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  BETTER DELIVERABILITY: PHPMailer + SMTP (Recommended)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  If emails land in spam, use SMTP instead of mail().

  Step 1 — Upload PHPMailer to your cPanel (via File Manager):
    Download: https://github.com/PHPMailer/PHPMailer/releases
    Upload the src/ folder next to this file.

  Step 2 — Replace the "Send" section above with:

    require 'src/Exception.php';
    require 'src/PHPMailer.php';
    require 'src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'mail.jareenaprintingpress.com';  // your cPanel mail server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info@jareenaprintingpress.com';  // cPanel email account
        $mail->Password   = 'YOUR_EMAIL_PASSWORD';
        $mail->SMTPSecure = 'ssl';
        $mail->Port       = 465;                              // or 587 with TLS
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($email, "$fname $lname");
        $mail->addBCC($bcc);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = $plain_body;
        $mail->send();
        echo json_encode(['success'=>true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>$mail->ErrorInfo]);
    }
*/
?>
