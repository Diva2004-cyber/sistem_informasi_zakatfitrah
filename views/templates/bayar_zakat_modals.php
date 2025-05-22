<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Pembayaran Zakat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm" method="POST" onsubmit="return validateForm()">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Muzakki</label>
                        <select class="form-select" name="nama_kk" id="muzakki_select" required>
                            <option value="">-- Pilih Muzakki --</option>
                            <optgroup label="Belum Bayar">
                            <?php 
                            foreach ($muzakki_list as $muzakki):
                                if ($muzakki['status_bayar'] === 'Belum Bayar'):
                            ?>
                                <option value="<?php echo htmlspecialchars($muzakki['nama_muzakki']); ?>" 
                                        data-tanggungan="<?php echo htmlspecialchars($muzakki['jumlah_tanggungan']); ?>">
                                    <?php echo htmlspecialchars($muzakki['nama_muzakki']); ?> 
                                    (<?php echo htmlspecialchars($muzakki['jumlah_tanggungan']); ?> jiwa)
                                </option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                            </optgroup>
                            <optgroup label="Sudah Bayar">
                            <?php 
                            foreach ($muzakki_list as $muzakki):
                                if ($muzakki['status_bayar'] === 'Sudah Bayar'):
                            ?>
                                <option value="<?php echo htmlspecialchars($muzakki['nama_muzakki']); ?>" 
                                        data-tanggungan="<?php echo htmlspecialchars($muzakki['jumlah_tanggungan']); ?>">
                                    <?php echo htmlspecialchars($muzakki['nama_muzakki']); ?> 
                                    (<?php echo htmlspecialchars($muzakki['jumlah_tanggungan']); ?> jiwa)
                                </option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                            </optgroup>
                        </select>
                        <small class="form-text text-danger" id="muzakki_error"></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jumlah Tanggungan</label>
                        <input type="number" class="form-control" id="jumlah_tanggungan" name="jumlah_tanggungan" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jumlah Tanggungan yang Dibayar</label>
                        <input type="number" class="form-control" name="jumlah_tanggunganyangdibayar" id="jumlah_tanggungan_bayar" 
                                min="1" required onkeypress="return (event.charCode !=8 && event.charCode ==0 || (event.charCode >= 48 && event.charCode <= 57))">
                        <small class="form-text text-danger" id="tanggungan_error"></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenis Pembayaran</label>
                        <select class="form-select" name="jenis_bayar" id="jenis_bayar" required>
                            <option value="beras">Beras</option>
                            <option value="uang">Uang</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" id="label_jumlah">Jumlah Bayar</label>
                        <input type="number" class="form-control" name="jumlah_bayar" id="jumlah_bayar" step="0.01" readonly>
                        <small class="form-text text-muted" id="satuan_bayar">Dalam kg untuk beras, atau dalam Rupiah untuk uang</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Payment Modal -->
<div class="modal fade" id="viewPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Pembayaran Zakat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Pembayaran Zakat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editPaymentForm" method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_bayarzakat" id="edit_id_zakat">

                    <div class="mb-3">
                        <label class="form-label">Jumlah Tanggungan yang Dibayar</label>
                        <input type="number" class="form-control" name="jumlah_tanggunganyangdibayar" id="edit_jumlah_tanggungan_bayar" 
                                min="1" required onkeypress="return (event.charCode !=8 && event.charCode ==0 || (event.charCode >= 48 && event.charCode <= 57))">
                        <small class="form-text text-danger" id="edit_tanggungan_error"></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenis Pembayaran</label>
                        <select class="form-select" name="jenis_bayar" id="edit_jenis_bayar" required>
                            <option value="beras">Beras</option>
                            <option value="uang">Uang</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" id="edit_label_jumlah">Jumlah Bayar</label>
                        <input type="number" class="form-control" name="jumlah_bayar" id="edit_jumlah_bayar" step="0.01" readonly>
                        <small class="form-text text-muted" id="edit_satuan_bayar">Dalam kg untuk beras, atau dalam Rupiah untuk uang</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deletePaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus pembayaran zakat ini?</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_bayarzakat" id="delete_id_zakat">
                    <button type="submit" class="btn btn-danger">Hapus</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Struk Pembayaran Zakat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <h4 class="mb-0">BUKTI PEMBAYARAN ZAKAT FITRAH</h4>
                    <h5><?php echo htmlspecialchars($lembaga['nama_lembaga'] ?? 'Lembaga Zakat'); ?></h5>
                    <small><?php echo htmlspecialchars($lembaga['alamat'] ?? ''); ?></small><br>
                    <small>Telp: <?php echo htmlspecialchars($lembaga['telepon'] ?? ''); ?> | Email: <?php echo htmlspecialchars($lembaga['email'] ?? ''); ?></small>
                    <hr>
                </div>
                <div class="row mb-2">
                    <div class="col-4">No. Kwitansi</div>
                    <div class="col-8">: <span id="receiptNo"></span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4">Tanggal</div>
                    <div class="col-8">: <span id="receiptDate"></span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4">Nama Muzakki</div>
                    <div class="col-8">: <span id="receiptName"></span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4">Alamat</div>
                    <div class="col-8">: <span id="receiptAddress"></span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4">Jenis Zakat</div>
                    <div class="col-8">: Zakat Fitrah</div>
                </div>
                <hr>
                <div class="row mb-2">
                    <div class="col-4">Beras</div>
                    <div class="col-8">: <span id="receiptBeras"></span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4">Uang</div>
                    <div class="col-8">: <span id="receiptUang"></span></div>
                </div>
                <hr>
                <div class="row mt-4">
                    <div class="col-6 text-center">
                        <p class="mb-5">Muzakki</p>
                        <p class="mt-5">(<span id="receiptName2"></span>)</p>
                    </div>
                    <div class="col-6 text-center">
                        <p class="mb-5"><?php echo htmlspecialchars($lembaga['ketua_jabatan'] ?? 'Petugas Amil Zakat'); ?></p>
                        <p class="mt-5">(<?php echo htmlspecialchars($lembaga['ketua_nama'] ?? ''); ?>)</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div> 