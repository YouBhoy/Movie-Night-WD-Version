// JS for edit/delete admin actions
            document.querySelectorAll('.btn-edit-admin').forEach(btn => {
                btn.onclick = function() {
                    document.getElementById('editAdminModal').style.display = 'flex';
                    document.getElementById('edit_admin_id').value = btn.dataset.id;
                    document.getElementById('edit_admin_username').value = btn.dataset.username;
                    document.getElementById('edit_admin_role').value = btn.dataset.role;
                    document.getElementById('edit_admin_active').checked = btn.dataset.active == '1';
                    document.getElementById('edit_admin_password').value = '';
                };
            });
            document.getElementById('cancelEditAdmin').onclick = function() {
                document.getElementById('editAdminModal').style.display = 'none';
            };
            document.querySelectorAll('.btn-delete-admin').forEach(btn => {
                btn.onclick = function() {
                    if (!confirm('Are you sure you want to delete admin: ' + btn.dataset.username + '? This cannot be undone.')) return;
                    // AJAX delete
                    const formData = new FormData();
                    formData.append('action', 'delete_admin');
                    formData.append('admin_csrf_token', document.querySelector('meta[name=admin-csrf-token]').content);
                    formData.append('delete_admin_id', btn.dataset.id);
                    fetch('admin.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Admin deleted successfully', 'success');
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            showToast(data.message || 'Error deleting admin', 'error');
                        }
                    })
                    .catch(() => showToast('Error deleting admin', 'error'));
                };
            });
