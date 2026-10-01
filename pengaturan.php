<?php
require_once 'header.php';
?>
<div class="page-header">
    <div>
        <h1>Pengaturan Sistem</h1>
        <p>Konfigurasi Telegram API dan profil.</p>
    </div>
</div>

<div class="table-container" style="max-width: 800px;">
    <h2>Konfigurasi Webhook Bot</h2>
    <p class="text-muted" style="margin-bottom: 2rem;">Pastikan token bot valid dan URL webhook sudah di-set ke domain HTTPS.</p>
    
    <div class="form-group">
        <label>Telegram Bot Token</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($bot_token) ?>" readonly style="background: #f1f5f9; cursor: not-allowed;">
        <small class="text-muted">Token didefinisikan secara hardcode di config.php</small>
    </div>
    
    <div class="form-group">
        <label>Set Webhook URL</label>
        <div style="display: flex; gap: 1rem;">
            <input type="url" class="form-control" value="https://domain-anda.com/webhook.php" readonly style="background: #f1f5f9;">
            <button class="btn-primary" onclick="alert('Silakan eksekusi URL secara manual menggunakan setWebhook API Telegram.')">Update URL</button>
        </div>
    </div>
</div>
<?php require_once 'footer.php'; ?>
