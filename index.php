<?php
/**
 * send_mail.php — Jareena Printing Press Quotation Mailer
 * ─────────────────────────────────────────────────────────
 * Upload this file to the SAME folder as jareena_quotation.html on cPanel.
 *
 * Receives JSON POST:
 *   fname, lname, email, phone, type, body (plain text), qno, pdf_b64 (base64 PDF)
 *
 * Sends a branded HTML email with the quotation PDF attached.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// ── Read JSON ──────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

function clean($v) {
    return htmlspecialchars(strip_tags(trim((string)$v)), ENT_QUOTES, 'UTF-8');
}

$fname   = clean($data['fname']  ?? '');
$lname   = clean($data['lname']  ?? '');
$email   = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone   = clean($data['phone']  ?? '');
$type    = clean($data['type']   ?? 'Quotation');
$qno     = clean($data['qno']    ?? 'N/A');
$bodyTxt = trim($data['body']    ?? '');
$pdfB64  = $data['pdf_b64']      ?? '';

if (!$email) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing email address']);
    exit;
}
if (empty($pdfB64)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'PDF data missing']);
    exit;
}

$pdfData = base64_decode($pdfB64, true);
if ($pdfData === false || strlen($pdfData) < 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'PDF base64 decode failed']);
    exit;
}

// ── YOUR DETAILS — EDIT THESE ──────────────────────────────────────
$from_name  = 'Jareena Printing Press LLC';
$from_email = 'info@jareenaprintingpress.com'; // must be a real cPanel email account
$reply_to   = 'info@jareenaprintingpress.com';
$bcc_email  = 'info@jareenaprintingpress.com'; // BCC copy to your inbox
// ──────────────────────────────────────────────────────────────────

$fullName    = trim("$fname $lname") ?: 'Sir/Madam';
$subject     = "Quotation #{$qno} - Jareena Printing Press LLC";
$pdfFilename = "Jareena_Quotation_{$qno}.pdf";

$plainEsc = nl2br(htmlspecialchars($bodyTxt, ENT_QUOTES, 'UTF-8'));

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
  <tr>
    <td style="background:#1a56db;padding:24px 32px;">
      <h1 style="margin:0;color:#fff;font-size:20px;font-weight:700;">Jareena Printing Press LLC</h1>
      <p style="margin:6px 0 0;color:#bfdbfe;font-size:12px;">P.O.Box 32260, Sharjah - UAE &nbsp;|&nbsp; Tel: 06 565 9485 &nbsp;|&nbsp; Mob: 050 569 9105</p>
    </td>
  </tr>
  <tr>
    <td style="padding:32px;">
      <p style="margin:0 0 14px;font-size:15px;color:#1e293b;">Dear <strong>{$fullName}</strong>,</p>
      <p style="margin:0 0 20px;font-size:14px;color:#475569;line-height:1.6;">
        Please find <strong>Quotation #{$qno}</strong> attached as a PDF to this email.<br>
        Below is a quick summary for your reference.
      </p>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;margin-bottom:24px;">
        <tr><td style="padding:20px 24px;font-family:monospace;font-size:13px;color:#1e3a8a;line-height:1.9;white-space:pre-wrap;">{$plainEsc}</td></tr>
      </table>
      <p style="margin:0 0 6px;font-size:13px;color:#64748b;">
        The full quotation is attached as <strong>{$pdfFilename}</strong>
      </p>
      <p style="margin:16px 0 0;font-size:13px;color:#64748b;line-height:1.7;">
        For any questions, please contact us:<br>
        <a href="tel:+97165659485" style="color:#1a56db;">06 565 9485</a> &nbsp;|&nbsp;
        <a href="tel:+971505699105" style="color:#1a56db;">050 569 9105</a> &nbsp;|&nbsp;
        <a href="mailto:info@jareenaprintingpress.com" style="color:#1a56db;">info@jareenaprintingpress.com</a>
      </p>
    </td>
  </tr>
  <tr>
    <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 32px;text-align:center;">
      <p style="margin:0;font-size:11px;color:#94a3b8;">
        &copy; Jareena Printing Press LLC &nbsp;|&nbsp;
        <a href="https://www.jareenaprintingpress.com" style="color:#1a56db;text-decoration:none;">www.jareenaprintingpress.com</a>
      </p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

// ── Build MIME multipart/mixed (HTML + PDF attachment) ─────────────
$boundary    = '----_MixedBoundary_'  . md5(uniqid('', true));
$boundaryAlt = '----_AltBoundary_'   . md5(uniqid('', true));
$pdfEncoded  = chunk_split(base64_encode($pdfData), 76, "\r\n");

$headers  = "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_email}>\r\n";
$headers .= "Reply-To: {$reply_to}\r\n";
$headers .= "Bcc: {$bcc_email}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
$headers .= "X-Mailer: Jareena-Quotation-Mailer/2.0";

$message  = "This is a multi-part message in MIME format.\r\n\r\n";

// -- Part 1: multipart/alternative wrapper (text + html)
$message .= "--{$boundary}\r\n";
$message .= "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n\r\n";

// Plain text
$message .= "--{$boundaryAlt}\r\n";
$message .= "Content-Type: text/plain; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
$message .= quoted_printable_encode($bodyTxt) . "\r\n\r\n";

// HTML
$message .= "--{$boundaryAlt}\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
$message .= quoted_printable_encode($htmlBody) . "\r\n\r\n";

$message .= "--{$boundaryAlt}--\r\n\r\n";

// -- Part 2: PDF attachment
$message .= "--{$boundary}\r\n";
$message .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\r\n";
$message .= "Content-Transfer-Encoding: base64\r\n";
$message .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n\r\n";
$message .= $pdfEncoded . "\r\n";

$message .= "--{$boundary}--";

// ── Fire ───────────────────────────────────────────────────────────
$sent = mail($email, $subject, $message, $headers);

if ($sent) {
    echo json_encode(['success' => true, 'message' => "Email + PDF sent to {$email}"]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'mail() failed. Make sure $from_email is a real cPanel email account, or use PHPMailer+SMTP (see file comments).'
    ]);
}

/*
=================================================================
  UPGRADE: PHPMailer + SMTP for better deliverability (no spam)
=================================================================

Step 1: Download PHPMailer → https://github.com/PHPMailer/PHPMailer
Step 2: Upload the /src/ folder next to this file on cPanel
Step 3: Replace everything from "Fire" to end of file with:

  require 'src/Exception.php';
  require 'src/PHPMailer.php';
  require 'src/SMTP.php';
  use PHPMailer\PHPMailer\PHPMailer;

  $mail = new PHPMailer(true);
  try {
      $mail->isSMTP();
      $mail->Host       = 'mail.jareenaprintingpress.com';
      $mail->SMTPAuth   = true;
      $mail->Username   = 'info@jareenaprintingpress.com';
      $mail->Password   = 'YOUR_CPANEL_EMAIL_PASSWORD';
      $mail->SMTPSecure = 'ssl';
      $mail->Port       = 465;

      $mail->setFrom($from_email, $from_name);
      $mail->addAddress($email, $fullName);
      $mail->addBCC($bcc_email);
      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body    = $htmlBody;
      $mail->AltBody = $bodyTxt;

      // Attach PDF from binary string (no temp file needed)
      $mail->addStringAttachment($pdfData, $pdfFilename, 'base64', 'application/pdf');

      $mail->send();
      echo json_encode(['success' => true]);
  } catch (Exception $e) {
      http_response_code(500);
      echo json_encode(['success' => false, 'message' => $mail->ErrorInfo]);
  }
*/
?>
