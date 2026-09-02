<?php
// index.php – Premium Insurance Staff Management (Fixed Modal)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Staff – Premium Dashboard</title>
    <style>
        /* ===== PREMIUM DARK THEME ===== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            background: #0b0d11;
            color: #e8edf5;
            padding: 30px 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        .container {
            max-width: 1400px;
            width: 100%;
            background: linear-gradient(145deg, #14181f, #1c222b);
            border-radius: 32px;
            padding: 32px 36px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,215,0,0.08);
            backdrop-filter: blur(2px);
            border: 1px solid rgba(255,215,0,0.06);
        }

        /* ===== HEADER ===== */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 28px;
            border-bottom: 1px solid rgba(255,215,0,0.12);
            padding-bottom: 20px;
        }
        .header-left h1 {
            font-size: 30px;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #f9e05d, #f5b041);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-left h1 span {
            font-size: 28px;
            background: none;
            -webkit-text-fill-color: initial;
            color: #f5b041;
        }
        .header-left .subtitle {
            color: #8a99b0;
            font-size: 15px;
            margin-top: 2px;
            letter-spacing: 0.3px;
        }
        .header-actions {
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* ===== BUTTONS ===== */
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.3px;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #f5b041, #e67e22);
            color: #0b0d11;
            box-shadow: 0 4px 18px rgba(245, 176, 65, 0.35);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(245, 176, 65, 0.5);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.06);
            color: #c8d0dc;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.12);
            color: #fff;
        }
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: #0b0d11;
            box-shadow: 0 4px 18px rgba(46, 204, 113, 0.3);
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(46, 204, 113, 0.4);
        }
        .btn-warning {
            background: #f39c12;
            color: #0b0d11;
        }
        .btn-warning:hover {
            background: #e67e22;
        }
        .btn-danger {
            background: #e74c3c;
            color: #fff;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .btn-sm {
            padding: 5px 14px;
            font-size: 12px;
            border-radius: 30px;
        }
        .btn-lg {
            padding: 14px 36px;
            font-size: 16px;
        }
        .btn-block {
            width: 100%;
            justify-content: center;
        }

        /* ===== FILTER BAR ===== */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            background: rgba(255,255,255,0.03);
            padding: 14px 20px;
            border-radius: 60px;
            border: 1px solid rgba(255,215,0,0.06);
        }
        .filter-bar label {
            color: #a0b0c8;
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .filter-bar select, .filter-bar input {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 40px;
            padding: 8px 18px;
            color: #e8edf5;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }
        .filter-bar select:focus, .filter-bar input:focus {
            border-color: #f5b041;
            box-shadow: 0 0 0 3px rgba(245,176,65,0.2);
        }
        .filter-bar select option {
            background: #1c222b;
        }
        .filter-bar input {
            min-width: 200px;
        }
        .filter-bar input::placeholder {
            color: #6a7a92;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        /* ===== TABLE ===== */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.05);
            background: rgba(0,0,0,0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 900px;
        }
        th {
            background: rgba(255,215,0,0.06);
            color: #d4dce8;
            font-weight: 600;
            padding: 16px 18px;
            text-align: left;
            letter-spacing: 0.5px;
            font-size: 13px;
            text-transform: uppercase;
            border-bottom: 2px solid rgba(255,215,0,0.1);
        }
        td {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            vertical-align: middle;
            color: #dce4ef;
        }
        tr:hover td {
            background: rgba(255,215,0,0.03);
        }
        .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }
        .status-active {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }
        .status-inactive {
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.15);
        }
        .position-badge {
            background: rgba(245, 176, 65, 0.12);
            color: #f5b041;
            padding: 3px 14px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            border: 1px solid rgba(245, 176, 65, 0.15);
        }
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .empty-msg {
            text-align: center;
            padding: 50px 0;
            color: #6a7a92;
            font-size: 16px;
        }

        /* ===== MODAL ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(8px);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #1c222b;
            max-width: 560px;
            width: 100%;
            border-radius: 28px;
            padding: 40px 36px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.8);
            border: 1px solid rgba(255,215,0,0.1);
            animation: slideUp 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
        }
        @keyframes slideUp {
            from { transform: translateY(30px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        .modal-content h2 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 28px;
            color: #f5b041;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,215,0,0.1);
            padding-bottom: 16px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
            color: #a0b0c8;
            letter-spacing: 0.3px;
        }
        .form-group label .required {
            color: #e74c3c;
            margin-left: 2px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            font-size: 14px;
            color: #e8edf5;
            transition: 0.2s;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #f5b041;
            box-shadow: 0 0 0 3px rgba(245,176,65,0.15);
            background: rgba(255,255,255,0.06);
        }
        .form-group input::placeholder {
            color: #5a6a82;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 14px;
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .btn-close-modal {
            background: rgba(255,255,255,0.06);
            color: #c8d0dc;
            padding: 12px 28px;
        }
        .btn-close-modal:hover {
            background: rgba(255,255,255,0.12);
        }
        .btn-submit {
            padding: 12px 36px;
            font-size: 15px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .container { padding: 20px 16px; }
            .header { flex-direction: column; align-items: stretch; }
            .filter-bar { flex-direction: column; align-items: stretch; border-radius: 20px; padding: 16px; }
            .filter-actions { margin-left: 0; justify-content: flex-end; }
            .filter-bar input { min-width: auto; }
            .modal-content { padding: 24px 18px; }
            .form-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            body { padding: 12px; }
            .header-left h1 { font-size: 24px; }
            .btn { font-size: 13px; padding: 8px 18px; }
            .modal-content { padding: 20px 16px; }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- ===== HEADER ===== -->
    <div class="header">
        <div class="header-left">
            <h1>
                <span>🏛️</span> Insurance Staff
            </h1>
            <div class="subtitle">Manage your team with premium control</div>
        </div>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openAddModal()">➕ Add New</button>
            <button class="btn btn-secondary" onclick="loadStaff()">🔄 Refresh</button>
        </div>
    </div>

    <!-- ===== FILTER BAR ===== -->
    <div class="filter-bar">
        <label for="positionFilter">Position</label>
        <select id="positionFilter" onchange="applyFilters()">
            <option value="">All Positions</option>
            <option value="Claims Adjuster">Claims Adjuster</option>
            <option value="Senior Underwriter">Senior Underwriter</option>
            <option value="Agent">Agent</option>
            <option value="Claims Manager">Claims Manager</option>
            <option value="Systems Analyst">Systems Analyst</option>
            <option value="HR Coordinator">HR Coordinator</option>
            <option value="Accountant">Accountant</option>
            <option value="Underwriter">Underwriter</option>
            <option value="Sales Manager">Sales Manager</option>
            <option value="Compliance Officer">Compliance Officer</option>
        </select>

        <label for="deptFilter">Department</label>
        <select id="deptFilter" onchange="applyFilters()">
            <option value="">All Departments</option>
            <option value="Claims">Claims</option>
            <option value="Underwriting">Underwriting</option>
            <option value="Sales">Sales</option>
            <option value="IT">IT</option>
            <option value="HR">HR</option>
            <option value="Finance">Finance</option>
            <option value="Legal">Legal</option>
        </select>

        <input type="text" id="searchInput" placeholder="🔍 Search by name, email..." oninput="applyFilters()">

        <div class="filter-actions">
            <button class="btn btn-secondary btn-sm" onclick="resetFilters()">✕ Clear</button>
        </div>
    </div>

    <!-- ===== TABLE ===== -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Hire Date</th>
                    <th>Salary</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="staffTableBody">
                <tr><td colspan="10" class="empty-msg">Loading staff...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== MODAL ===== -->
<div id="staffModal" class="modal">
    <div class="modal-content">
        <h2 id="modalTitle">➕ Add New Staff</h2>
        <form id="staffForm" onsubmit="event.preventDefault(); saveStaff();">
            <input type="hidden" id="editId" value="">
            
            <div class="form-row">
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" id="firstName" required placeholder="e.g. John">
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" id="lastName" required placeholder="e.g. Doe">
                </div>
            </div>

            <div class="form-group">
                <label>Email <span class="required">*</span></label>
                <input type="email" id="email" required placeholder="john@insurance.com">
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input type="text" id="phone" placeholder="555-0101">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" id="department" placeholder="e.g. Claims">
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" id="position" placeholder="e.g. Manager">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Hire Date</label>
                    <input type="date" id="hireDate">
                </div>
                <div class="form-group">
                    <label>Salary ($)</label>
                    <input type="number" step="0.01" id="salary" placeholder="0.00">
                </div>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select id="status">
                    <option value="active">✅ Active</option>
                    <option value="inactive">❌ Inactive</option>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-close-modal" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-success btn-submit" id="saveBtn">
                    💾 Save Staff
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // ========== GLOBALS ==========
    let allStaff = [];

    // ========== LOAD STAFF ==========
    async function loadStaff() {
        try {
            const res = await fetch('api.php');
            const data = await res.json();
            allStaff = data;
            applyFilters();
        } catch (e) {
            console.error('Error loading staff:', e);
            document.getElementById('staffTableBody').innerHTML = `<tr><td colspan="10" class="empty-msg">⚠️ Failed to load data. Check console.</td></tr>`;
        }
    }

    // ========== RENDER TABLE ==========
    function renderTable(staffArray) {
        const tbody = document.getElementById('staffTableBody');
        if (!staffArray || staffArray.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10" class="empty-msg">No staff found.</td></tr>`;
            return;
        }
        let html = '';
        staffArray.forEach(s => {
            const statusClass = s.status === 'active' ? 'status-active' : 'status-inactive';
            const salary = s.salary ? '$' + parseFloat(s.salary).toFixed(2) : '-';
            const positionDisplay = s.position ? `<span class="position-badge">${s.position}</span>` : '-';
            html += `
                <tr>
                    <td>${s.id}</td>
                    <td><strong>${s.first_name} ${s.last_name}</strong></td>
                    <td>${s.email}</td>
                    <td>${s.phone || '-'}</td>
                    <td>${s.department || '-'}</td>
                    <td>${positionDisplay}</td>
                    <td>${s.hire_date || '-'}</td>
                    <td>${salary}</td>
                    <td><span class="status-badge ${statusClass}">${s.status}</span></td>
                    <td class="actions">
                        <button class="btn btn-warning btn-sm" onclick="editStaff(${s.id})">✏️</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteStaff(${s.id})">🗑️</button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    // ========== FILTER LOGIC ==========
    function applyFilters() {
        const search = document.getElementById('searchInput').value.toLowerCase().trim();
        const positionFilter = document.getElementById('positionFilter').value;
        const deptFilter = document.getElementById('deptFilter').value;

        let filtered = allStaff;

        if (search) {
            filtered = filtered.filter(s =>
                (s.first_name + ' ' + s.last_name).toLowerCase().includes(search) ||
                s.email.toLowerCase().includes(search) ||
                (s.department && s.department.toLowerCase().includes(search)) ||
                (s.position && s.position.toLowerCase().includes(search))
            );
        }
        if (positionFilter) {
            filtered = filtered.filter(s => s.position === positionFilter);
        }
        if (deptFilter) {
            filtered = filtered.filter(s => s.department === deptFilter);
        }

        renderTable(filtered);
    }

    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('positionFilter').value = '';
        document.getElementById('deptFilter').value = '';
        applyFilters();
    }

    // ========== MODAL CONTROLS ==========
    function openAddModal() {
        document.getElementById('modalTitle').textContent = '➕ Add New Staff';
        document.getElementById('staffForm').reset();
        document.getElementById('editId').value = '';
        document.getElementById('saveBtn').textContent = '💾 Save Staff';
        document.getElementById('saveBtn').className = 'btn btn-success btn-submit';
        document.getElementById('staffModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('staffModal').classList.remove('active');
    }

    // ========== EDIT ==========
    async function editStaff(id) {
        try {
            const res = await fetch(`api.php?id=${id}`);
            const staff = await res.json();
            if (!staff) return alert('Staff not found');
            document.getElementById('modalTitle').textContent = '✏️ Edit Staff';
            document.getElementById('editId').value = staff.id;
            document.getElementById('firstName').value = staff.first_name;
            document.getElementById('lastName').value = staff.last_name;
            document.getElementById('email').value = staff.email;
            document.getElementById('phone').value = staff.phone || '';
            document.getElementById('department').value = staff.department || '';
            document.getElementById('position').value = staff.position || '';
            document.getElementById('hireDate').value = staff.hire_date || '';
            document.getElementById('salary').value = staff.salary || '';
            document.getElementById('status').value = staff.status || 'active';
            document.getElementById('saveBtn').textContent = '💾 Update Staff';
            document.getElementById('saveBtn').className = 'btn btn-warning btn-submit';
            document.getElementById('staffModal').classList.add('active');
        } catch (e) {
            alert('Error loading staff details');
        }
    }

    // ========== SAVE ==========
    async function saveStaff() {
        const id = document.getElementById('editId').value;
        const payload = {
            first_name: document.getElementById('firstName').value.trim(),
            last_name: document.getElementById('lastName').value.trim(),
            email: document.getElementById('email').value.trim(),
            phone: document.getElementById('phone').value.trim(),
            department: document.getElementById('department').value.trim(),
            position: document.getElementById('position').value.trim(),
            hire_date: document.getElementById('hireDate').value,
            salary: document.getElementById('salary').value,
            status: document.getElementById('status').value,
        };

        if (!payload.first_name || !payload.last_name || !payload.email) {
            alert('⚠️ First name, last name and email are required.');
            return;
        }

        if (id) {
            payload._method = 'PUT';
            payload.id = id;
        }

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const result = await res.json();
            
            if (result.success) {
                closeModal();
                loadStaff();
                alert(id ? '✅ Staff updated successfully!' : '✅ Staff added successfully!');
            } else {
                alert('❌ Operation failed. Please check console for errors.');
                console.error('Server response:', result);
            }
        } catch (e) {
            alert('❌ Network error. Please check:\n1. Is your server running?\n2. Is api.php accessible?\n3. Check console for details.');
            console.error('Network error:', e);
        }
    }

    // ========== DELETE ==========
    async function deleteStaff(id) {
        if (!confirm('Are you sure you want to delete this staff member?')) return;
        
        try {
            const payload = {
                _method: 'DELETE',
                id: id
            };
            
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const result = await res.json();
            
            if (result.success) {
                loadStaff();
                alert('✅ Staff deleted successfully!');
            } else {
                alert('❌ Delete failed. Check console.');
                console.error('Delete response:', result);
            }
        } catch (e) {
            alert('❌ Network error during delete.');
            console.error(e);
        }
    }

    // ========== INIT ==========
    loadStaff();

    // Close modal on backdrop click
    document.getElementById('staffModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
</script>
</body>
</html>