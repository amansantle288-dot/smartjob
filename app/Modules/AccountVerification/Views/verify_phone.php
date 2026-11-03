<section class="auth-wrap verification-layout">
    <div class="auth-aside">
        <p class="eyebrow">ACCOUNT SECURITY</p>
        <h1>Verify your<br>mobile number.</h1>
        <p>We sent a one-time code to <strong><?= h($verificationRecipient ?? 'your mobile number') ?></strong>.</p>
    </div>
    <div class="card auth-card verification-card">
        <div class="verification-logo"><span class="brand-mark">S</span><strong>SMART<span class="brand-accent">JOB</span></strong></div>
        <h2>Mobile verification</h2>
        <p class="muted">Enter the six-digit code from your SMS. It expires in five minutes.</p>
        <form method="post" class="verification-resend">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="start_phone_verification">
            <button class="button button-quiet full" type="submit">Send or resend code</button>
        </form>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_phone_otp">
            <label>Verification code
                <input class="otp-input" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" aria-label="Six-digit mobile verification code" required>
            </label>
            <button class="button full" type="submit">Verify mobile</button>
        </form>
        <?php if (!($phoneVerificationRequired ?? true)): ?><p class="form-foot">Mobile verification is optional. <a href="index.php?page=dashboard">Continue to SMARTJOB</a></p><?php endif; ?>
    </div>
</section>
