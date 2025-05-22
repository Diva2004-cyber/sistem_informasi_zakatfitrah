/**
 * Distribusi Zakat JavaScript File
 * 
 * This script handles the functionality of the Distribusi Zakat page:
 * - Initializes Bootstrap tabs
 * - Sets up edit and delete modal functionality
 * - Handles form submissions
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tabs
    const tabsContainer = document.getElementById('distributionTabs');
    if (tabsContainer) {
        const triggerTabList = [].slice.call(tabsContainer.querySelectorAll('button'));
        triggerTabList.forEach(function(triggerEl) {
            const tabTrigger = new bootstrap.Tab(triggerEl);
            
            triggerEl.addEventListener('click', function(event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });
    }
    
    // Handle edit modal for Mustahik Warga
    document.querySelectorAll('.edit-warga-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const kategori = this.getAttribute('data-kategori');
            
            document.getElementById('edit_warga_id').value = id;
            document.getElementById('edit_warga_nama').value = nama;
            document.getElementById('edit_warga_kategori').value = kategori;
            
            const editModal = new bootstrap.Modal(document.getElementById('editWargaModal'));
            editModal.show();
        });
    });
    
    // Handle edit modal for Mustahik Lainnya
    document.querySelectorAll('.edit-lainnya-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const kategori = this.getAttribute('data-kategori');
            
            document.getElementById('edit_lainnya_id').value = id;
            document.getElementById('edit_lainnya_nama').value = nama;
            document.getElementById('edit_lainnya_kategori').value = kategori;
            
            const editModal = new bootstrap.Modal(document.getElementById('editLainnyaModal'));
            editModal.show();
        });
    });
    
    // Handle delete modal for Mustahik Warga
    document.querySelectorAll('.delete-warga-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            
            document.getElementById('delete_warga_id').value = id;
            document.getElementById('delete_warga_nama').textContent = nama;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteWargaModal'));
            deleteModal.show();
        });
    });
    
    // Handle delete modal for Mustahik Lainnya
    document.querySelectorAll('.delete-lainnya-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            
            document.getElementById('delete_lainnya_id').value = id;
            document.getElementById('delete_lainnya_nama').textContent = nama;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteLainnyaModal'));
            deleteModal.show();
        });
    });

    // Fix tab content to be visible
    const fixTabContent = () => {
        const activeTab = document.querySelector('.tab-pane.active');
        if (activeTab && !activeTab.classList.contains('show')) {
            activeTab.classList.add('show');
        }
    };

    // Run fix immediately and after a short delay to handle any timing issues
    fixTabContent();
    setTimeout(fixTabContent, 100);
}); 