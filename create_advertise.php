<?php
require_once 'config/config.php';
require_login();

$err = null;
$oldText = trim($_POST['ad_text'] ?? '');
$oldUrl = trim($_POST['url'] ?? '');
$oldType = $_POST['type'] ?? 'frameless';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $text = $oldText;
    $url = $oldUrl;
    $type = $oldType;

    if (mb_strlen($text) > 40 || $text === '') {
        $err = 'Ad text must be 1–40 characters.';
    } elseif (!valid_url($url)) {
        $err = 'Enter a valid http/https URL.';
    } elseif (!in_array($type, ['framed', 'frameless'], true)) {
        $err = 'Choose a display type.';
    } else {
        if ($type === 'framed') {
            // This is only a first-pass server check. The browser preview below is
            // the practical test because X-Frame-Options/CSP are browser-enforced.
            $headers = @get_headers($url, true);
            $status = (int)($headers[0] ?? 0);
            $xfo = $headers['X-Frame-Options'] ?? '';
            $csp = $headers['Content-Security-Policy'] ?? '';
            $xfo = is_array($xfo) ? implode(' ', $xfo) : (string)$xfo;
            $csp = is_array($csp) ? implode(' ', $csp) : (string)$csp;

            if ($status >= 400 || preg_match('/\bDENY\b|\bSAMEORIGIN\b/i', $xfo) || preg_match('/frame-ancestors\s+[^;]*(?:\'none\'|\*|https?:\/\/)/i', $csp)) {
                $err = 'This website does not allow framed display. Please choose Frameless or use a URL that permits iframe embedding.';
            }
        }

        if (!$err) {
            $s = db()->prepare('INSERT INTO ads(user_id,ad_text,url,ad_type) VALUES(?,?,?,?)');
            $s->execute([user()['id'], $text, $url, $type]);
            header('Location: advertise.php');
            exit;
        }
    }
}

require 'includes/header.php';
?>
<div class="card create-ad-card">
    <h1>Create Advertise</h1>
    <?php if ($err): ?><p class="danger"><?=e($err)?></p><?php endif; ?>

    <form method="post" id="adForm">
        <input type="hidden" name="csrf" value="<?=e(csrf())?>">

        <label>Ad text <span id="charCount">0/40</span></label>
        <input id="adText" name="ad_text" maxlength="40" value="<?=e($oldText)?>" required>

        <label>Link</label>
        <input id="adUrl" name="url" type="url" value="<?=e($oldUrl)?>" placeholder="https://example.com" required>

        <label>Display</label>
        <select id="adType" name="type">
            <option value="frameless" <?= $oldType === 'frameless' ? 'selected' : '' ?>>Frameless</option>
            <option value="framed" <?= $oldType === 'framed' ? 'selected' : '' ?>>Framed</option>
        </select>

        <div id="previewBox" class="preview-box" hidden>
            <div class="preview-head">
                <strong>Live preview</strong>
                <span id="previewStatus">Enter a URL to test it.</span>
            </div>
            <div class="preview-ad-text" id="previewText"></div>
            <iframe id="previewFrame" title="Framed advertisement preview" referrerpolicy="no-referrer" hidden></iframe>
            <div id="previewHelp" class="preview-help"></div>
        </div>

        <button type="submit" id="submitBtn">Submit for approval</button>
    </form>
</div>

<script>
const adText = document.getElementById('adText');
const adUrl = document.getElementById('adUrl');
const adType = document.getElementById('adType');
const count = document.getElementById('charCount');
const box = document.getElementById('previewBox');
const frame = document.getElementById('previewFrame');
const status = document.getElementById('previewStatus');
const help = document.getElementById('previewHelp');
const previewText = document.getElementById('previewText');
const submitBtn = document.getElementById('submitBtn');
let previewTimer = null;

function updateCount() {
    count.textContent = `${adText.value.length}/40`;
    previewText.textContent = adText.value || 'Your ad text will appear here';
}

function showFramelessPreview() {
    box.hidden = false;
    frame.hidden = true;
    status.textContent = 'Frameless: no iframe compatibility test is required.';
    status.className = 'preview-ok';
    help.textContent = 'This ad will open in a separate browser tab when a user clicks it.';
    submitBtn.disabled = false;
}

function testFrame() {
    box.hidden = false;
    frame.hidden = false;
    status.textContent = 'Testing whether this URL can be displayed in a frame…';
    status.className = '';
    help.textContent = 'If the site blocks iframe embedding, the preview may remain blank or show a browser security error.';
    submitBtn.disabled = false;

    clearTimeout(previewTimer);
    frame.src = 'about:blank';
    const url = adUrl.value.trim();
    if (!url) {
        status.textContent = 'Enter a URL to test it.';
        return;
    }

    try { new URL(url); } catch (_) {
        status.textContent = 'Enter a valid URL first.';
        return;
    }

    frame.src = url;
    previewTimer = setTimeout(() => {
        status.textContent = 'Preview loaded. Final compatibility is enforced again when you submit.';
        status.className = 'preview-ok';
    }, 3000);
}

function refreshPreview() {
    updateCount();
    if (adType.value === 'framed') testFrame();
    else showFramelessPreview();
}

adText.addEventListener('input', updateCount);
adUrl.addEventListener('input', () => {
    if (adType.value === 'framed') testFrame();
    else showFramelessPreview();
});
adType.addEventListener('change', refreshPreview);
frame.addEventListener('load', () => {
    if (adType.value !== 'framed') return;
    status.textContent = 'The browser loaded the frame. Final compatibility is checked again on submission.';
    status.className = 'preview-ok';
});

updateCount();
refreshPreview();
</script>
<?php require 'includes/footer.php'; ?>
