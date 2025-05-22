document.addEventListener('DOMContentLoaded', function() {
    console.log('Tasks.js loaded successfully');
    
    // Task status checkbox handling with AJAX
    const taskCheckboxes = document.querySelectorAll('.task-status-checkbox');
    
    if (taskCheckboxes.length > 0) {
        console.log('Found ' + taskCheckboxes.length + ' task checkboxes');
        taskCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const taskId = this.getAttribute('data-id');
                const isCompleted = this.checked;
                const taskItem = this.closest('.task-item');
                
                // Show loading state
                if (taskItem) {
                    taskItem.classList.add('loading');
                }
                
                fetch('../api/tasks_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'update_status',
                        task_id: taskId,
                        status: isCompleted ? 'completed' : 'pending'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (taskItem) {
                        taskItem.classList.remove('loading');
                    }
                    
                    if (data.success) {
                        if (taskItem) {
                            if (isCompleted) {
                                taskItem.classList.add('completed');
                                
                                // Update the status badge
                                const badge = taskItem.querySelector('.badge');
                                if (badge) {
                                    badge.className = 'badge bg-success';
                                    badge.textContent = 'Selesai';
                                }
                            } else {
                                taskItem.classList.remove('completed');
                                
                                // Update the status badge
                                const badge = taskItem.querySelector('.badge');
                                if (badge) {
                                    badge.className = 'badge bg-warning';
                                    badge.textContent = 'Belum Dikerjakan';
                                }
                            }
                        }
                        
                        // Optional: Show success toast or notification
                        showToast('success', 'Status tugas berhasil diperbarui');
                    } else {
                        // Revert checkbox state on error
                        this.checked = !isCompleted;
                        
                        // Show error message
                        showToast('error', data.error || 'Gagal memperbarui status tugas');
                    }
                })
                .catch(error => {
                    if (taskItem) {
                        taskItem.classList.remove('loading');
                    }
                    
                    // Revert checkbox state on error
                    this.checked = !isCompleted;
                    
                    console.error('Error updating task status:', error);
                    showToast('error', 'Terjadi kesalahan saat memperbarui status tugas');
                });
            });
        });
    }
    
    // Handle edit task modal
    const editBtns = document.querySelectorAll('.edit-task');
    
    if (editBtns.length > 0) {
        editBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const title = this.getAttribute('data-title');
                const description = this.getAttribute('data-description');
                const dueDate = this.getAttribute('data-due-date');
                const priority = this.getAttribute('data-priority');
                const status = this.getAttribute('data-status');
                const userId = this.getAttribute('data-user-id');
                
                document.getElementById('edit_task_id').value = id;
                document.getElementById('edit_task_title').value = title;
                document.getElementById('edit_task_description').value = description;
                document.getElementById('edit_task_due_date').value = dueDate;
                document.getElementById('edit_task_priority').value = priority;
                document.getElementById('edit_task_status').value = status;
                
                const assignToSelect = document.getElementById('edit_task_assign_to');
                if (assignToSelect) {
                    assignToSelect.value = userId;
                }
            });
        });
    }
    
    // Handle delete task modal
    const deleteBtns = document.querySelectorAll('.delete-task');
    
    if (deleteBtns.length > 0) {
        deleteBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const title = this.getAttribute('data-title');
                
                document.getElementById('delete_task_id').value = id;
                document.getElementById('delete_task_title').textContent = title;
            });
        });
    }
    
    // Helper function to show toast notifications
    function showToast(type, message) {
        // Check if toast container exists, create if not
        let toastContainer = document.querySelector('.toast-container');
        
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast element
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        
        // Toast content
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        // Add to container
        toastContainer.appendChild(toastEl);
        
        // Initialize and show toast
        const toast = new bootstrap.Toast(toastEl, {
            animation: true,
            autohide: true,
            delay: 3000
        });
        
        toast.show();
        
        // Remove from DOM after hidden
        toastEl.addEventListener('hidden.bs.toast', function() {
            toastEl.remove();
        });
    }
}); 