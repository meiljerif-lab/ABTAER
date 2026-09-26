<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'API/db_connect.php';
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT membership_type FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$currentPlan = $row['membership_type'] ?? 'free';
$isFree  = $currentPlan === 'free';
$isStandard  = $currentPlan === 'standard';
$isStudentWorker  = $currentPlan === 'student_worker';

$pendingStmt = $conn->prepare("SELECT requested_plan FROM subscription_requests 
                               WHERE user_id = ? AND status = 'pending' 
                               LIMIT 1");
$pendingStmt->bind_param('i', $user_id);
$pendingStmt->execute();
$pendingRow = $pendingStmt->get_result()->fetch_assoc();
$pendingPlan = $pendingRow ? $pendingRow['requested_plan'] : null;

if ($isStudentWorker) {
    $swLabel = 'Current Plan';
    $swDisabled = true;
} elseif ($pendingPlan === 'student_worker') {
    $swLabel = 'Pending Approval';
    $swDisabled = true;
} else {
    $swLabel = 'Subscribe';
    $swDisabled = false;
}
$lastRejected = $conn->prepare("SELECT reviewed_at FROM subscription_requests 
    WHERE user_id = ? AND requested_plan = 'student_worker' 
      AND status = 'rejected' 
    ORDER BY reviewed_at DESC LIMIT 1
");
$lastRejected->bind_param('i', $user_id);
$lastRejected->execute();
$rejectedRow = $lastRejected->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="Assets/favicon.png">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <a href="index.php" id="back-btn">←Back to Dashboard</a>

    <div class="subscribe-container">
        <h1 class="subscribe-title">Choose Your Plan</h1>
        <p class="subscribe-sub">Unlock the financial advisor and support ABTAER's development.</p>

        <div class="sub-cards">
            <!-- Free card -->
            <div class="sub-card <?= $isFree ? 'current' : '' ?>">
                <div class="sub-card-head">
                    <h3>Free</h3>
                    <p class="sub-tagline">Get started with the basics</p>
                </div>
                <div class="sub-card-body">
                    <div class="sub-price">₱0<span>/month</span></div>
                    <ul class="sub-features">
                        <li>✓ Track expenses</li>
                        <li>✓ Set category budgets</li>
                        <li>✓ View expense history</li>
                    </ul>
                    <button class="btn-plan <?= $isFree ? 'is-current' : '' ?>"
                        data-plan="free"
                        <?= $isFree ? 'disabled' : '' ?>>
                        <?= $isFree ? 'Current Plan' : 'Switch to Free' ?>
                    </button>
                </div>
            </div>

            <!-- Standard card -->
            <div class="sub-card <?= $isStandard ? 'current' : '' ?> featured">
                <div class="sub-card-head">
                    <h3>Standard</h3>
                    <p class="sub-tagline">For everyday users</p>
                </div>
                <div class="sub-card-body">
                    <div class="sub-price">₱99<span>/month</span></div>
                    <ul class="sub-features">
                        <li>✓ Everything in Free</li>
                        <li>✓ Financial advisor</li>
                        <li>✓ Bug reports & feedback</li>
                    </ul>
                    <button class="btn-plan <?= $isStandard ? 'is-current' : '' ?>"
                        data-plan="standard"
                        <?= $isStandard ? 'disabled' : '' ?>>
                        <?= $isStandard ? 'Current Plan' : 'Subscribe' ?>
                    </button>
                </div>
            </div>

            <!-- Student / Worker card -->
            <div class="sub-card <?= $isStudentWorker ? 'current' : '' ?>">
                <div class="sub-card-head">
                    <h3>Student / Worker</h3>
                    <p class="sub-tagline">Discounted rate</p>
                </div>
                <div class="sub-card-body">
                    <div class="sub-price">₱49<span>/month</span></div>
                    <ul class="sub-features">
                        <li>✓ Everything in Standard</li>
                        <li>✓ 50% discount for students & workers</li>
                    </ul>
                    <button class="btn-plan <?= ($isStudentWorker || $pendingPlan === 'student_worker') ? 'is-current' : '' ?>"
                        data-plan="student_worker"
                        <?= $swDisabled ? 'disabled' : '' ?>>
                        <?= $swLabel ?>
                    </button>
                    <?php if ($rejectedRow && !$isStudentWorker && $pendingPlan !== 'student_worker'): ?>
                        <p class="rejected-note">
                            Your last Student/Worker request was not approved.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Fallback: original mailto: version. Requires OS mail handler.
     Switched to Gmail compose URL because Windows test machine had none. -->
        <!-- <p class="support-line">
            Need help? <a href="mailto:meiljerif@gmail.com">Contact support</a>
        </p>-->
        <p class="support-line">
            Need help?
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=meiljerif@gmail.com"
                target="_blank"
                rel="noopener">
                Contact support
            </a>
        </p>

    </div>
    <script>
        document.querySelectorAll('.btn-plan').forEach(function(btn) {
            btn.addEventListener('click', async function() {
                const plan = btn.dataset.plan;

                const planNames = {
                    free: 'Free',
                    standard: 'Standard',
                    student_worker: 'Student/Worker'
                };
                const sure = confirm('Switch to the ' + planNames[plan] + ' plan?');

                try {
                    const res = await fetch('API/subscribe.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            plan: plan
                        })
                    });
                    const data = await res.json();

                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Could not subscribe: ' + (data.error || 'unknown error'));
                    }
                } catch (err) {
                    alert('Network error. Please try again.');
                }
            });
        });
    </script>
</body>

</html>