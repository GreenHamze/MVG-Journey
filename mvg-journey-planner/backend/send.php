<?php
  $info = isset($_REQUEST['info']) ? $_REQUEST['info'] : '';
  $cb = isset($_REQUEST['cb']) ? $_REQUEST['cb'] : '';
  
  if (!$cb) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Error</title></head>';
    echo '<body style="background:#1a1a2e;color:#e74c3c;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;">';
    echo '<div><h2>&#x26A0;&#xFE0F; Missing Callback</h2><p style="color:#888;">This QR code must be scanned from the CPEE frame.</p></div>';
    echo '</body></html>';
    exit;
  }
  
  $opts = array(
    'http' => array(
      'method' => 'PUT',
      'header'  => "Content-type: text/plain\r\n",
      'content' => $info
    )
  );
  $context = stream_context_create($opts);
  $result = @file_get_contents($cb, false, $context);
  
  if ($result !== false) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Done</title></head>';
    echo '<body style="background:#1a1a2e;color:#2ecc71;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;">';
    echo '<div><h1 style="font-size:3rem;margin-bottom:10px;">&#x2705;</h1><h2>Got it!</h2><p style="color:#888;margin-top:10px;">You can close this tab and check the screen.</p></div>';
    echo '</body></html>';
  } else {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Error</title></head>';
    echo '<body style="background:#1a1a2e;color:#e74c3c;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;">';
    echo '<div><h2>&#x26A0;&#xFE0F; Something went wrong</h2><p style="color:#888;">The callback could not be reached. Please try scanning again.</p></div>';
    echo '</body></html>';
  }
  exit;
?>
