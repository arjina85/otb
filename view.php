<?php
require_once 'config/config.php';
require_login();

$id=(int)($_GET['id']??0);
$s=db()->prepare("SELECT * FROM ads WHERE id=? AND status='approved'");
$s->execute([$id]);
$a=$s->fetch();
if(!$a) exit('Ad unavailable');

$token=bin2hex(random_bytes(32));
db()->prepare('INSERT INTO ad_views(ad_id,viewer_id,session_token,started_at) VALUES(?,?,?,NOW())')
  ->execute([$id,user()['id'],$token]);

$isFramed=$a['ad_type']==='framed';
require 'includes/header.php';
?>

<?php if($isFramed): ?>
<div class="ptc-view">
  <div class="ptc-topbar">
    <div class="ptc-brand">Advertisement</div>
    <div class="ptc-text"><?=e($a['ad_text'])?></div>
    <div class="ptc-status">
      <span id="timer">15</span>s
      <span id="challenge" hidden>
        <button type="button" class="btn" onclick="complete()">Continue / Verify</button>
      </span>
    </div>
  </div>
  <div class="ptc-frame-wrap">
    <iframe class="ptc-frame" src="<?=e($a['url'])?>" title="Advertisement" referrerpolicy="no-referrer"></iframe>
  </div>
</div>
<?php else: ?>
<div class="card">
  <h1><?=e($a['ad_text'])?></h1>
  <div class="frameless-layout">
    <div>
      <p id="status">Advertisement opened in a new tab.</p>
      <p class="muted">Keep the advertisement open until the 15-second viewing period finishes, then return here.</p>
    </div>
    <div class="side-timer">
      <strong>Timer</strong>
      <div class="timer" id="timer">15</div>
      <div id="returnStatus" class="muted">Waiting for return…</div>
      <div id="challenge" hidden>
        <button type="button" class="btn" onclick="complete()">Continue / Verify</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const token = <?=json_encode($token)?>;
const csrfToken = <?=json_encode(csrf())?>;
const targetUrl = <?=json_encode($a['url'])?>;
const isFramed = <?=json_encode($isFramed)?>;
let left = <?=VIEW_SECONDS?>;
let timerFinished = false;
let targetWindow = null;
let returned = isFramed;

const timer = document.getElementById('timer');
const challenge = document.getElementById('challenge');
const returnStatus = document.getElementById('returnStatus');
const statusEl = document.getElementById('status');

function finishTimer(){
  timerFinished = true;
  if (timer) timer.textContent = '0';
  if (isFramed) {
    challenge.hidden = false;
  } else if (returned) {
    challenge.hidden = false;
    if (returnStatus) returnStatus.textContent = 'Viewing period complete.';
  }
}

const timerLoop = setInterval(() => {
  if (left > 0) left--;
  if (timer) timer.textContent = left;
  if (left <= 0) {
    clearInterval(timerLoop);
    finishTimer();
  }
}, 1000);

function openFramelessAd(){
  // Opening immediately from the page-load context is intentional. Modern browsers
  // may block unsolicited popups; if that happens we cannot bypass the browser's policy.
  targetWindow = window.open(targetUrl, '_blank', 'noopener,noreferrer');
  if (targetWindow) {
    if (statusEl) statusEl.textContent = 'Advertisement opened in a new tab. Return here after the timer finishes.';
  } else {
    if (statusEl) statusEl.textContent = 'Your browser blocked the automatic ad tab. Please allow pop-ups for this site and reload the ad.';
    if (returnStatus) returnStatus.textContent = 'Ad tab was blocked.';
  }
}

if (!isFramed) {
  openFramelessAd();
  const watch = setInterval(() => {
    if (targetWindow && targetWindow.closed) {
      returned = true;
      clearInterval(watch);
      if (returnStatus) returnStatus.textContent = timerFinished ? 'Returned. Verification is ready.' : 'Ad closed. Finish the timer to continue.';
      if (timerFinished) challenge.hidden = false;
    }
  }, 500);

  window.addEventListener('focus', () => {
    // Returning focus is useful even when the browser does not expose window.closed reliably.
    if (timerFinished) {
      returned = true;
      if (returnStatus) returnStatus.textContent = 'Welcome back. Verification is ready.';
      challenge.hidden = false;
    }
  });
}

async function complete(){
  if (!timerFinished) return;
  if (!isFramed && !returned) {
    alert('Please return to this page after viewing the advertisement.');
    return;
  }

  const body = new URLSearchParams({csrf:csrfToken, token:token});
  try {
    const r = await fetch('complete_view.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','Accept':'application/json'},
      body:body.toString(),
      credentials:'same-origin'
    });
    const text = await r.text();
    let j;
    try { j = JSON.parse(text); } catch(e) { throw new Error(text || 'Invalid server response'); }
    if (!j.ok) throw new Error(j.message || 'Unable to complete the view.');
    alert(j.message);
    location.href='earn.php';
  } catch(e) {
    alert('Could not complete the ad: ' + e.message);
  }
}
</script>

<?php require 'includes/footer.php'; ?>
