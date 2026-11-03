<span class="account-avatar account-avatar-<?= h($accountAvatarSize) ?>">
    <?php if ($accountPhotoUrl): ?><img src="<?= h($accountPhotoUrl) ?>" alt="<?= h($accountPhotoAlt) ?>" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><?php endif; ?>
    <span aria-hidden="true" <?= $accountPhotoUrl ? 'hidden' : '' ?>><?= h($accountInitials) ?></span>
</span>
