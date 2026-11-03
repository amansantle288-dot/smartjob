<section class="auth-wrap verification-layout">
    <div class="auth-aside">
        <p class="eyebrow">ACCOUNT SECURITY</p>
        <h1>Verify your<br>email address.</h1>
        <p>We sent a one-time code to <strong><?= h($verificationRecipient ?? 'your email address') ?></strong>.</p>
    </div>
    <div class="card auth-card verification-card">
        <div class="verification-logo"><span class="brand-mark">S</span><strong>SMART<span class="brand-accent">JOB</span></strong></div>
        <h2>Account verification</h2>
        <p class="muted">Enter the six-digit code from your email. It expires in five minutes.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_email_otp">
            <label>Verification code
                <input class="otp-input" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" aria-label="Six-digit email verification code" required>
            </label>
            <button class="button full" type="submit">Verify email</button>
        </form>
        <form method="post" class="verification-resend">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resend_email_otp">
            <button class="button button-quiet full" type="submit">Resend code</button>
        </form>
        <p class="form-foot">Already verified? <a href="index.php?page=login">Sign in</a></p>
    </div>
</section>
