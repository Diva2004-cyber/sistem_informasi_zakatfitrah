<script>
    // Wait for DOM and Bootstrap to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Check if Bootstrap is available
        if (typeof bootstrap === 'undefined') {
            console.error('Bootstrap is not loaded');
            return;
        }

        // Add console log for debugging
        console.log('Script loaded, Bootstrap version:', bootstrap.Modal.VERSION);

        // Format date function
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Handle muzakki selection
        const muzakkiSelect = document.getElementById('muzakki_select');
        if (muzakkiSelect) {
            muzakkiSelect.addEventListener('change', function() {
                const selected = this.options[this.selectedIndex];
                const jumlahTanggungan = selected.dataset.tanggungan || '';
                document.getElementById('jumlah_tanggungan').value = jumlahTanggungan;
                
                // Reset jumlah tanggungan yang dibayar
                document.getElementById('jumlah_tanggungan_bayar').value = '';
                document.getElementById('jumlah_tanggungan_bayar').max = jumlahTanggungan;
                
                // Reset jumlah bayar
                calculatePayment();
            });
        }

        // Handle jumlah tanggungan yang dibayar
        const tanggunganBayar = document.getElementById('jumlah_tanggungan_bayar');
        if (tanggunganBayar) {
            tanggunganBayar.addEventListener('input', function() {
                const maxTanggungan = parseInt(document.getElementById('jumlah_tanggungan').value);
                const value = parseInt(this.value);
                const errorElement = document.getElementById('tanggungan_error');
                
                if (value <= 0) {
                    errorElement.textContent = 'Jumlah tanggungan minimal 1';
                    this.value = '';
                } else if (value > maxTanggungan) {
                    errorElement.textContent = 'Jumlah tidak boleh melebihi jumlah tanggungan (' + maxTanggungan + ')';
                    this.value = maxTanggungan;
                } else {
                    errorElement.textContent = '';
                }
                
                calculatePayment();
            });
        }

        // Handle payment type change
        const jenisBayar = document.getElementById('jenis_bayar');
        if (jenisBayar) {
            jenisBayar.addEventListener('change', function() {
                const label = document.getElementById('label_jumlah');
                const satuan = document.getElementById('satuan_bayar');
                if (this.value === 'beras') {
                    label.textContent = 'Jumlah Bayar (kg)';
                    satuan.textContent = 'Jumlah dalam kilogram (kg)';
                } else {
                    label.textContent = 'Jumlah Bayar (Rp)';
                    satuan.textContent = 'Jumlah dalam Rupiah';
                }
                calculatePayment();
            });
        }

        // Calculate payment amount
        function calculatePayment() {
            const tanggunganBayar = parseInt(document.getElementById('jumlah_tanggungan_bayar').value) || 0;
            const jenisBayar = document.getElementById('jenis_bayar').value;
            const jumlahBayarElement = document.getElementById('jumlah_bayar');
            
            if (tanggunganBayar > 0) {
                if (jenisBayar === 'beras') {
                    jumlahBayarElement.value = (tanggunganBayar * 2.5).toFixed(2);
                } else {
                    jumlahBayarElement.value = (tanggunganBayar * 2.5 * 15000).toFixed(0);
                }
            } else {
                jumlahBayarElement.value = '';
            }
        }

        // Handle view button
        document.querySelectorAll('.view-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const row = this.closest('tr');
                const modalBody = document.getElementById('viewModalBody');
                
                modalBody.innerHTML = `
                    <dl class="row">
                        <dt class="col-sm-4">ID Zakat</dt>
                        <dd class="col-sm-8">${row.cells[0].textContent}</dd>
                        
                        <dt class="col-sm-4">Nama KK</dt>
                        <dd class="col-sm-8">${row.cells[1].textContent}</dd>
                        
                        <dt class="col-sm-4">Jumlah Tanggungan</dt>
                        <dd class="col-sm-8">${row.cells[2].textContent}</dd>
                        
                        <dt class="col-sm-4">Jumlah Dibayar</dt>
                        <dd class="col-sm-8">${row.cells[3].textContent}</dd>
                        
                        <dt class="col-sm-4">Jenis Bayar</dt>
                        <dd class="col-sm-8">${row.cells[4].textContent}</dd>
                        
                        <dt class="col-sm-4">Jumlah</dt>
                        <dd class="col-sm-8">${row.cells[5].textContent}</dd>
                        
                        <dt class="col-sm-4">Tanggal</dt>
                        <dd class="col-sm-8">${row.cells[6].textContent}</dd>
                    </dl>
                `;
                
                const viewModal = new bootstrap.Modal(document.getElementById('viewPaymentModal'));
                viewModal.show();
            });
        });

        // Handle edit button
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const row = this.closest('tr');
                
                document.getElementById('edit_id_zakat').value = id;
                document.getElementById('edit_jumlah_tanggungan_bayar').value = row.cells[3].textContent;
                
                // Set jenis bayar
                const jenisBayar = row.cells[4].textContent.toLowerCase();
                document.getElementById('edit_jenis_bayar').value = jenisBayar;
                
                // Set jumlah bayar
                const jumlahBayar = row.cells[5].textContent.trim();
                if (jenisBayar === 'beras') {
                    document.getElementById('edit_jumlah_bayar').value = parseFloat(jumlahBayar.replace(' kg', ''));
                } else {
                    document.getElementById('edit_jumlah_bayar').value = parseFloat(jumlahBayar.replace('Rp ', '').replace(/\./g, ''));
                }
                
                const editModal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
                editModal.show();
            });
        });

        // Handle delete button
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                document.getElementById('delete_id_zakat').value = id;
                const deleteModal = new bootstrap.Modal(document.getElementById('deletePaymentModal'));
                deleteModal.show();
            });
        });

        // Handle print button
        document.querySelectorAll('.print-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                printReceipt(id);
            });
        });

        function printReceipt(id) {
            // Add console log for debugging
            console.log('Printing receipt for ID:', id);
            
            fetch(`/zakatfitrah/views/get_payment.php?id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(payment => {
                    console.log('Payment data received:', payment);
                    
                    // Fill receipt modal content
                    document.getElementById('receiptNo').textContent = `ZF-${String(payment.id_zakat).padStart(4, '0')}`;
                    document.getElementById('receiptDate').textContent = formatDate(payment.created_at);
                    document.getElementById('receiptName').textContent = payment.nama_KK;
                    document.getElementById('receiptName2').textContent = payment.nama_KK;
                    document.getElementById('receiptAddress').textContent = payment.alamat || '-';
                    
                    if (payment.jenis_bayar === 'beras') {
                        document.getElementById('receiptBeras').textContent = `${payment.bayar_beras} kg`;
                        document.getElementById('receiptUang').textContent = '-';
                    } else {
                        document.getElementById('receiptBeras').textContent = '-';
                        document.getElementById('receiptUang').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(payment.bayar_uang)}`;
                    }
                    
                    // Show receipt modal
                    const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
                    receiptModal.show();
                })
                .catch(error => {
                    console.error('Error fetching payment data:', error);
                    alert('Gagal mengambil data pembayaran: ' + error.message);
                });
        }

        // Add console log for debugging button clicks
        document.querySelectorAll('.view-btn, .edit-btn, .delete-btn, .print-btn').forEach(button => {
            button.addEventListener('click', function() {
                console.log('Button clicked:', this.className, 'with ID:', this.dataset.id);
            });
        });

        // Add print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body * {
                    visibility: hidden;
                }
                .modal-body, .modal-body * {
                    visibility: visible;
                }
                .modal-body {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                }
                .modal-footer {
                    display: none !important;
                }
            }
        `;
        document.head.appendChild(style);

        // Form validation
        window.validateForm = function() {
            let isValid = true;
            const muzakkiSelect = document.getElementById('muzakki_select');
            const jumlahTanggunganBayar = document.getElementById('jumlah_tanggungan_bayar');
            const jumlahTanggungan = document.getElementById('jumlah_tanggungan');
            
            // Reset error messages
            document.getElementById('muzakki_error').textContent = '';
            document.getElementById('tanggungan_error').textContent = '';
            
            // Validate muzakki selection
            if (!muzakkiSelect.value) {
                document.getElementById('muzakki_error').textContent = 'Harap pilih muzakki';
                isValid = false;
            }
            
            // Validate jumlah tanggungan yang dibayar
            if (!jumlahTanggunganBayar.value) {
                document.getElementById('tanggungan_error').textContent = 'Harap masukkan jumlah tanggungan yang dibayar';
                isValid = false;
            } else if (parseInt(jumlahTanggunganBayar.value) > parseInt(jumlahTanggungan.value)) {
                document.getElementById('tanggungan_error').textContent = 'Jumlah tidak boleh melebihi total tanggungan';
                isValid = false;
            }
            
            return isValid;
        };
    });
</script> 