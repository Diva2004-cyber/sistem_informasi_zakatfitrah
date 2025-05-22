<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama KK</th>
                <th>Jumlah Tanggungan</th>
                <th>Jumlah Dibayar</th>
                <th>Jenis Bayar</th>
                <th>Jumlah</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
            <tr>
                <td><?php echo $payment['id_zakat']; ?></td>
                <td><?php echo htmlspecialchars($payment['nama_KK']); ?></td>
                <td><?php echo $payment['jumlah_tanggungan']; ?></td>
                <td><?php echo $payment['jumlah_tanggunganyangdibayar']; ?></td>
                <td><?php echo ucfirst($payment['jenis_bayar']); ?></td>
                <td>
                    <?php if ($payment['jenis_bayar'] === 'beras'): ?>
                        <?php echo number_format($payment['bayar_beras'], 2); ?> kg
                    <?php else: ?>
                        Rp <?php echo number_format($payment['bayar_uang'], 0, ',', '.'); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></td>
                <td>
                    <button class="btn btn-sm btn-info view-btn" data-id="<?php echo $payment['id_zakat']; ?>">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-warning edit-btn" data-id="<?php echo $payment['id_zakat']; ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $payment['id_zakat']; ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                    <button class="btn btn-sm btn-success print-btn" data-id="<?php echo $payment['id_zakat']; ?>">
                        <i class="bi bi-printer"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div> 