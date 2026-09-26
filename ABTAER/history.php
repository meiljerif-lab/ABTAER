<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location:login.php");
    exit;
}
include 'API/db_connect.php';
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT expenses.amount,categories.name AS category, expenses.created_at AS created_at, expenses.id AS id FROM expenses JOIN categories ON expenses.category_id = categories.id WHERE expenses.user_id=? ORDER BY expenses.created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function fmt_money($n)
{
    return number_format((float)$n, 0);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Full History</title>
    <link rel="icon" type="image/png" href="Assets/favicon.png">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <a href="index.php" id="back-btn">←Back to Dashboard</a>
    <div class="history-contain">
        <section class="panel2">
            <div class="panel-head">
                <h1>Full History Expenses</h1>
            </div>
            <div class="history-cards">
                <ul class="history-list2" id="historyList">
                    <?php foreach ($expenses as $e): ?>
                        <li>
                            <span class="cat"><?= htmlspecialchars($e['category']) ?></span>
                            <span class="amt">-₱<?= fmt_money($e['amount']) ?></span>
                            <span class="diff">
                                <button class="delete-btn" data-id="<?= $e['id'] ?>">Delete</button>
                                <button class="edit-btn" data-id="<?= $e['id'] ?>">Edit</button>

                            </span>
                        </li>

                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

    </div>
    <script>
        document.getElementById('historyList').addEventListener('click', function(event) {

            const btn = event.target.closest('button');
            if (!btn) return;

            const row = btn.closest('li');
            const id = btn.dataset.id;

            if (btn.classList.contains('delete-btn')) {
                const sure = confirm('Delete this expense? This can\'t be undone.');
                if (!sure) return;

                fetch('API/delete_expense.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: id
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            row.remove();
                        } else {
                            alert('Could not delete: ' + (data.error || 'unknown error'));
                        }
                    });
                return;
            }

            if (btn.classList.contains('edit-btn')) {
                const currentText = row.querySelector('.amt').textContent;
                const amount = currentText.replace(/[^0-9.]/g, '');

                row.dataset.originalAmt = row.querySelector('.amt').outerHTML;
                row.dataset.originalDiff = row.querySelector('.diff').innerHTML;

                row.querySelector('.amt').outerHTML =
                    '<input type="number" class="amt-input" value="' + amount + '">';

                row.querySelector('.diff').innerHTML =
                    '<button class="delete-btn" data-id="' + id + '" disabled>Delete</button>' +
                    '<button class="save-btn" data-id="' + id + '">Save</button>' +
                    '<button class="cancel-btn" data-id="' + id + '">Cancel</button>';
                return;
            }
            if (btn.classList.contains('cancel-btn')) {
                row.querySelector('.amt-input').outerHTML = row.dataset.originalAmt;
                row.querySelector('.diff').innerHTML = row.dataset.originalDiff;
                return;
            }
            if (btn.classList.contains('save-btn')) {
                const newAmount = row.querySelector('.amt-input').value;

                fetch('API/edit_expense.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: id,
                            amount: newAmount
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            row.querySelector('.amt-input').outerHTML = '<span class="amt">-₱' + newAmount + '</span>';
                            row.querySelector('.diff').innerHTML = row.dataset.originalDiff;
                        } else {
                            alert('Could not save: ' + (data.error || 'unknown error'));
                        }
                    });
                return;
            }
        });
    </script>


</body>

</html>