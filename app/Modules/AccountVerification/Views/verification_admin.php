<section class="page-heading">
    <p class="eyebrow">ADMINISTRATION</p>
    <h1>Account verification</h1>
    <p class="muted">Providers and credentials are configured only in the server environment. A provider acceptance is not proof of final delivery.</p>
</section>
<section class="verification-config-grid">
    <article class="card">
        <h2>Email provider</h2>
        <p><strong><?= h(ucfirst($emailProvider)) ?></strong> · <span class="badge <?= $emailConfigured ? 'approved' : 'pending' ?>"><?= $emailConfigured ? 'Configured' : 'Not configured' ?></span></p>
        <p class="muted">Email verification required: <?= $emailRequired ? 'Yes' : 'No' ?></p>
        <?php if ($testEmailAllowed): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="admin_test_email"><label>Authorized test email<input type="email" name="recipient" value="<?= h(getenv('VERIFICATION_TEST_EMAIL') ?: '') ?>" required></label><button class="button button-small" type="submit">Send test email</button></form>
        <?php else: ?><p class="muted">Set VERIFICATION_TEST_EMAIL on the server to enable a controlled test.</p><?php endif; ?>
    </article>
    <article class="card">
        <h2>SMS provider</h2>
        <p><strong><?= h(ucfirst($smsProvider)) ?></strong> · <span class="badge <?= $smsConfigured ? 'approved' : 'pending' ?>"><?= $smsConfigured ? 'Configured' : 'Not configured' ?></span></p>
        <p class="muted">Phone verification required: <?= $phoneRequired ? 'Yes' : 'No' ?></p>
        <?php if ($testPhoneAllowed): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="admin_test_sms"><label>Authorized test mobile<input type="tel" name="recipient" value="<?= h(getenv('VERIFICATION_TEST_PHONE') ?: '') ?>" required></label><button class="button button-small" type="submit">Send test SMS</button></form>
        <?php else: ?><p class="muted">Set VERIFICATION_TEST_PHONE on the server to enable a controlled test.</p><?php endif; ?>
    </article>
</section>
<section class="card verification-policy">
    <h2>Provider and policy selection</h2>
    <p class="muted">Only provider names and verification policy are stored here. API credentials remain in the server environment. Changes apply to new accounts.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_verification_settings">
        <div class="form-grid">
            <label>Email provider
                <select name="mail_provider">
                    <option value="brevo" <?= $emailProvider === 'brevo' ? 'selected' : '' ?>>Brevo</option>
                    <option value="mailjet" <?= $emailProvider === 'mailjet' ? 'selected' : '' ?>>Mailjet</option>
                    <option value="sendgrid" <?= $emailProvider === 'sendgrid' ? 'selected' : '' ?>>SendGrid</option>
                    <option value="smtp" <?= $emailProvider === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                </select>
            </label>
            <label>SMS provider
                <select name="sms_provider">
                    <option value="textbee" <?= $smsProvider === 'textbee' ? 'selected' : '' ?>>TextBee</option>
                    <option value="twilio" <?= $smsProvider === 'twilio' ? 'selected' : '' ?>>Twilio</option>
                </select>
            </label>
        </div>
        <label class="verification-toggle"><input type="checkbox" name="email_verification_required" value="1" <?= $emailRequired ? 'checked' : '' ?>> Require email verification for new accounts</label>
        <label class="verification-toggle"><input type="checkbox" name="phone_verification_required" value="1" <?= $phoneRequired ? 'checked' : '' ?>> Require mobile verification for new accounts</label>
        <div class="form-grid">
            <label>OTP lifetime (minutes)<input type="number" name="otp_expiry_minutes" min="1" max="5" value="<?= (int) $otpExpiryMinutes ?>" required></label>
            <label>Maximum incorrect attempts<input type="number" name="otp_max_attempts" min="1" max="5" value="<?= (int) $otpMaxAttempts ?>" required></label>
            <label>Resend cooldown (seconds)<input type="number" name="otp_resend_cooldown_seconds" min="30" max="3600" value="<?= (int) $resendCooldown ?>" required></label>
        </div>
        <button class="button" type="submit">Save verification settings</button>
    </form>
</section>
<section class="card verification-policy">
    <h2>Verification policy</h2>
    <p>Code lifetime: <?= (int) $otpExpiryMinutes ?> minutes · Incorrect attempts: <?= (int) $otpMaxAttempts ?> · Resend cooldown: <?= (int) $resendCooldown ?> seconds</p>
    <p class="muted">Account contact verification does not approve employers, companies, or OJT partners.</p>
</section>
<section class="card verification-log">
    <h2>Recent delivery requests</h2>
    <?php if (!$deliveryLogs): ?><p class="muted">No delivery requests have been recorded.</p><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Time</th><th>Channel</th><th>Provider</th><th>Recipient</th><th>Status</th><th>Diagnostic</th></tr></thead><tbody>
        <?php foreach ($deliveryLogs as $row): ?><tr><td><?= h($row['created_at']) ?></td><td><?= h(strtoupper($row['delivery_channel'])) ?></td><td><?= h($row['provider_name']) ?></td><td><?= h($row['recipient_masked']) ?></td><td><?= h($row['delivery_status']) ?></td><td><?= h($row['error_code'] ?? '—') ?><?= $row['error_message'] ? ': ' . h($row['error_message']) : '' ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<section class="card verification-log">
    <h2>Provider diagnostics</h2>
    <?php if (!$apiLogs): ?><p class="muted">No provider requests have been recorded.</p><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Time</th><th>Provider</th><th>Service</th><th>Status</th><th>HTTP</th><th>Error code</th></tr></thead><tbody>
        <?php foreach ($apiLogs as $row): ?><tr><td><?= h($row['created_at']) ?></td><td><?= h($row['provider_name']) ?></td><td><?= h($row['service_type']) ?></td><td><?= h($row['response_status']) ?></td><td><?= h($row['http_status_code'] ?? '—') ?></td><td><?= h($row['error_code'] ?? '—') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
