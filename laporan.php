<?php
require_once 'header.php';
?>
<div class="page-header">
    <div>
        <h1>Laporan Kinerja</h1>
        <p>Visualisasi kecepatan respons staf dan riwayat tugas.</p>
    </div>
    <button class="btn-primary" onclick="window.print()">
        <i class="ri-printer-line"></i> Cetak Laporan
    </button>
</div>

<div class="table-container" style="text-align: center; padding: 5rem 2rem;">
    <i class="ri-bar-chart-2-fill" style="font-size: 5rem; color: var(--border); margin-bottom: 1rem; display: block;"></i>
    <h2 style="color: var(--text-muted)">Modul Laporan (Placeholder)</h2>
    <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto;">Di halaman ini Anda dapat menambahkan Chart.js atau ApexCharts untuk memvisualisasikan rata-rata waktu respons (SLA) dari setiap Staf berdasarkan kolom <strong>TIMEDIFF()</strong> dari tabel tasks.</p>
</div>
<?php require_once 'footer.php'; ?>
