<?php
session_start();
include 'API/db_connect.php';
include 'API/auth_helpers.php';

require_admin_page($conn);   // redirects non-admins to index.php

// Query 1: total users
$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];

// Query 2: subscription breakdown
$breakdown = [];
$res = $conn->query("SELECT membership_type, COUNT(*) AS count FROM users GROUP BY membership_type");
while ($row = $res->fetch_assoc()) {
    $breakdown[$row['membership_type']] = $row['count'];
}

// Query 3: pending requests
$stmt = $conn->prepare("SELECT sr.id, sr.requested_plan, sr.requested_at, u.username 
                        FROM subscription_requests sr 
                        JOIN users u ON u.id = sr.user_id 
                        WHERE sr.status = 'pending' 
                        ORDER BY sr.requested_at ASC");
$stmt->execute();
$pendingRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="Assets/favicon.png">
</head>

<body>
    <a href="index.php" id="back-btn">← Back to Dashboard</a>

    <div class="admin-container">
        <h1 class="admin-title">Admin Dashboard</h1>
        <p class="admin-sub">Overview and pending approvals</p>

        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= $totalUsers ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Free</div>
                <div class="stat-value"><?= $breakdown['free'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Standard</div>
                <div class="stat-value"><?= $breakdown['standard'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Student / Worker</div>
                <div class="stat-value"><?= $breakdown['student_worker'] ?? 0 ?></div>
            </div>
        </div>

        <div class="admin-card">
            <h3>Revenue</h3>
            <p class="admin-coming-soon">Coming soon — payment integration pending.</p>
        </div>

        <div class="admin-card">
            <h3>Pending Approvals</h3>
            <?php if (empty($pendingRequests)): ?>
                <p class="admin-empty">No pending requests.</p>
            <?php else: ?>
                <ul class="pending-list" id="pendingList">
                    <?php foreach ($pendingRequests as $r): ?>
                        <li data-request-id="<?= $r['id'] ?>">
                            <span class="pending-user"><?= htmlspecialchars($r['username']) ?></span>
                            <span class="pending-plan"><?= htmlspecialchars($r['requested_plan']) ?></span>
                            <span class="pending-date"><?= date('M j, Y', strtotime($r['requested_at'])) ?></span>
                            <span class="pending-actions">
                                <button class="btn-approve" data-action="approve">Approve</button>
                                <button class="btn-reject" data-action="reject">Reject</button>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <script>
document.getElementById('pendingList')?.addEventListener('click', async function(event) {
    const btn = event.target.closest('button');
    if (!btn) return;

    const li = btn.closest('li');
    const requestId = li.dataset.requestId;
    const username = li.querySelector('.pending-user').textContent;
    const action = btn.dataset.action;

    const endpoint = action === 'approve' ? 'API/approve_request.php' : 'API/reject_request.php';
    const verb = action === 'approve' ? 'Approve' : 'Reject';

    const sure = confirm(verb + ' the student/worker request from ' + username + '?');
    if (!sure) return;

    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId })
        });
        const data = await res.json();

        if (data.success) {
            window.location.reload();
        } else {
            alert('Could not ' + action + ': ' + (data.error || 'unknown error'));
        }
    } catch (err) {
        alert('Network error. Please try again.');
    }
});
</script>
</body>

</html>