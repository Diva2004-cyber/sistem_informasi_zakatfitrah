            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JS -->
    <script>
    $(document).ready(function() {
        // Initialize DataTables
        $('.datatable').DataTable({
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
            }
        });
        
        // Sidebar Toggle
        $('.sidebar-toggle').on('click', function() {
            $('.sidebar').toggleClass('active');
            $('.sidebar-backdrop').toggleClass('active');
            $('body').toggleClass('sidebar-open');
        });
        
        $('#close-sidebar, .sidebar-backdrop').on('click', function() {
            $('.sidebar').removeClass('active');
            $('.sidebar-backdrop').removeClass('active');
            $('body').removeClass('sidebar-open');
        });
        
        // Tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
        
        // Handle session flash messages fadeout
        setTimeout(function() {
            $('.alert-dismissible.fade').alert('close');
        }, 5000);
    });
    </script>
</body>
</html> 