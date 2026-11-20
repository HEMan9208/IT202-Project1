
<?php
session_start();

if (!isset($_SESSION['rep_id'])) {
    header("Location: index.html");
    exit;
}

require_once __DIR__ . '/db_connect.php';

$repID = $_SESSION['rep_id'];

$stmt = $pdo->prepare("
    SELECT
        r.rep_id,
        r.first_name AS rep_first,
        r.last_name AS rep_last,
        r.phone_number AS rep_phone,
        r.email AS rep_email,
        c.client_id,
        c.first_name AS client_first,
        c.last_name AS client_last,
        p.street_number,
        p.street_name,
        p.city,
        p.state,
        p.zip_code,
        p.phone_number AS client_phone,
        m.moving_id,
        m.new_street_number,
        m.new_street_name,
        m.new_city,
        m.new_state,
        m.new_zip_code,
        m.moving_date,
        m.total_weight,
        m.miles_for_move,
        m.total_cost,
        s.instructions
    FROM moving_representatives r
    JOIN moving_events m ON r.rep_id = m.rep_id
    JOIN clients c ON m.client_id = c.client_id
    JOIN client_personal_information p
        ON c.client_id = p.client_id
    LEFT JOIN special_instructions s
        ON m.moving_id = s.moving_id
    WHERE r.rep_id = ?
    ORDER BY m.moving_date
");

$stmt->execute([$repID]);
$records = $stmt->fetchAll();

function e($value) {
    return htmlspecialchars((string)($value ?? ''),
        ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moving On Up - Representative Account</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: block;
            padding: 25px;
        }
        .account-container {
            background: rgba(255,255,255,0.96);
            border-radius: 12px;
            padding: 25px;
            max-width: 1400px;
            margin: 35px auto;
            overflow-x: auto;
        }
        nav {
            background: #185a8d;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
        }
        nav a {
            color: white;
            margin: 8px;
            text-decoration: none;
            display: inline-block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #aaa;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #185a8d;
            color: white;
        }
        tr:nth-child(even) {
            background: #eef5fa;
        }
    </style>
</head>
<body>

<nav>
    <a href="search.php">Search Account</a>
    <a href="book.php">Book Move</a>
    <a href="cancel.php">Cancel Move</a>
    <a href="instructions.php">Special Instructions</a>
    <a href="update_instructions.php">Update Instructions</a>
    <a href="create_client.php">Create Client</a>
</nav>

<div class="account-container">
    <h1>Moving On Up</h1>
    <h2>Moving Representative Account</h2>

    <p>Welcome,
        <strong><?= e($_SESSION['rep_name']) ?></strong>
    </p>

    <?php if (empty($records)): ?>
        <p>No moving records found for your account.</p>
    <?php else: ?>

    <table>
        <thead>
            <tr>
                <th>Rep ID</th>
                <th>Representative</th>
                <th>Rep Phone</th>
                <th>Rep Email</th>
                <th>Client ID</th>
                <th>Client</th>
                <th>Current Address</th>
                <th>Client Phone</th>
                <th>Moving ID</th>
                <th>New Address</th>
                <th>Moving Date</th>
                <th>Weight</th>
                <th>Miles</th>
                <th>Cost</th>
                <th>Special Instructions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($records as $row): ?>
            <tr>
                <td><?= e($row['rep_id']) ?></td>
                <td><?= e($row['rep_first'] . ' ' .
                           $row['rep_last']) ?></td>
                <td><?= e($row['rep_phone']) ?></td>
                <td><?= e($row['rep_email']) ?></td>
                <td><?= e($row['client_id']) ?></td>
                <td><?= e($row['client_first'] . ' ' .
                           $row['client_last']) ?></td>
                <td><?= e($row['street_number'] . ' ' .
                           $row['street_name'] . ', ' .
                           $row['city'] . ', ' .
                           $row['state'] . ' ' .
                           $row['zip_code']) ?></td>
                <td><?= e($row['client_phone']) ?></td>
                <td><?= e($row['moving_id']) ?></td>
                <td><?= e($row['new_street_number'] . ' ' .
                           $row['new_street_name'] . ', ' .
                           $row['new_city'] . ', ' .
                           $row['new_state'] . ' ' .
                           $row['new_zip_code']) ?></td>
                <td><?= e($row['moving_date']) ?></td>
                <td><?= e($row['total_weight']) ?> lbs</td>
                <td><?= e($row['miles_for_move']) ?></td>
                <td>$<?= number_format(
                    (float)$row['total_cost'], 2) ?></td>
                <td><?= e($row['instructions'] ?? 'None') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php endif; ?>
</div>

</body>
</html>
