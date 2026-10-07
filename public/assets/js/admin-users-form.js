// AJAX for Add Admin
            document.getElementById('addAdminForm').onsubmit = function(e) {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);
                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Admin added successfully', 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast(data.message || 'Error adding admin', 'error');
                    }
                })
                .catch(() => showToast('Error adding admin', 'error'));
            };

            // AJAX for Edit Admin
            document.getElementById('editAdminForm').onsubmit = function(e) {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);
                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Admin updated successfully', 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast(data.message || 'Error updating admin', 'error');
                    }
                })
                .catch(() => showToast('Error updating admin', 'error'));
            };
