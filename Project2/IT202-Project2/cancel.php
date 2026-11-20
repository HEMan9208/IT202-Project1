
<?php
session_start();

if (!isset($_SESSION["rep_id"])) {
    header("Location: index.html");
    exit;
}

require_once __DIR__ . "/db_connect.php";

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$message = "";
$error = "";
$booking = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )) {
        http_response_code(403);
        exit("Invalid request.");
    }

    $movingID = trim($_POST["moving_id"] ?? "");
    $clientID = trim($_POST["client_id"] ?? "");
    $repID = $_SESSION["rep_id"];
    $action = $_POST["action"] ?? "";

    if (!ctype_digit($movingID) || !ctype_digit($clientID)) {
        $error = "Please enter valid numeric Moving and Client IDs.";
    } elseif ($action === "verify") {

        $stmt = $pdo->prepare(
            "SELECT m.moving_id, m.client_id, m.rep_id,
                    m.moving_date, m.total_cost,
                    c.first_name, c.last_name,
                    s.instructions
             FROM moving_events m
             JOIN clients c ON m.client_id = c.client_id
             LEFT JOIN special_instructions s
                 ON m.moving_id = s.moving_id
             WHERE m.moving_id = ?
               AND m.client_id = ?
               AND m.rep_id = ?"
        );

        $stmt->execute([$movingID, $clientID, $repID]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $error = "Moving event not found. Please check the IDs.";
        }

    } elseif ($action === "cancel") {

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT moving_id
                 FROM moving_events
                 WHERE moving_id = ?
                   AND client_id = ?
                   AND rep_id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$movingID, $clientID, $repID]);

            if (!$stmt->fetch()) {
                $pdo->rollBack();
                $error = "Moving event does not exist or is not assigned to your account.";
            } else {

                // Special instructions are deleted automatically
                // through ON DELETE CASCADE.
                $delete = $pdo->prepare(
                    "DELETE FROM moving_events
                     WHERE moving_id = ?
                       AND client_id = ?
                       AND rep_id = ?"
                );

                $delete->execute([$movingID, $clientID, $repID]);

                $pdo->commit();

                $message = "Moving event cancelled successfully. " .
                           "Representative ID: " . $repID .
                           ", Client ID: " . $clientID .
                           ", Moving ID: " . $movingID .
                           ". Associated special instructions were also removed.";
            }

        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($ex->getMessage());
            $error = "Unable to cancel moving event. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Moving On Up - Cancel Moving Event</title>
<link rel="stylesheet" href="style.css">
<style>
body {
    display: block;
    padding: 25px;
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
.cancel-container {
    max-width: 650px;
    margin: 35px auto;
    background: rgba(255, 255, 255, 0.96);
    padding: 30px;
    border: 2px solid #245a81;
    border-radius: 12px;
}
.cancel-container input {
    width: 100%;
    padding: 11px;
    margin-bottom: 10px;
    border: 1px solid #9eacb8;
    border-radius: 5px;
}
.cancel-container button {
    width: 100%;
    padding: 13px;
    background: #1876b8;
    color: white;
    border: none;
    border-radius: 5px;
    margin-top: 15px;
    cursor: pointer;
}
.cancel-container button:hover {
    background: #105887;
}
.booking-details {
    background: #eef5fa;
    padding: 15px;
    border-radius: 6px;
    margin-top: 20px;
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

<div class="cancel-container">
    <h1>Moving On Up</h1>
    <h2>Cancel a Client's Moving Event</h2>

    <form action="cancel.php" method="POST">
        <input type="hidden" name="csrf_token"
               value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="verify">

        <label for="moving_id">Moving ID:</label>
        <input type="text" id="moving_id"
               name="moving_id" required>

        <label for="client_id">Client ID:</label>
        <input type="text" id="client_id"
               name="client_id" required>

        <button type="submit">Find Moving Event</button>
    </form>

    <?php if ($booking): ?>
        <div class="booking-details">
            <h3>Moving Event Found</h3>
            <p><strong>Client:</strong>
                <?= e($booking["first_name"] . " " .
                      $booking["last_name"]) ?></p>
            <p><strong>Moving ID:</strong>
                <?= e($booking["moving_id"]) ?></p>
            <p><strong>Moving Date:</strong>
                <?= e($booking["moving_date"]) ?></p>
            <p><strong>Total Cost:</strong>
                $<?= number_format((float)$booking["total_cost"], 2) ?></p>
            <p><strong>Special Instructions:</strong>
                <?= e($booking["instructions"] ?? "None") ?></p>

            <form action="cancel.php" method="POST"
                  onsubmit="return confirm('Are you sure you want to cancel this moving event? Associated special instructions will also be deleted.');">

                <input type="hidden" name="csrf_token"
                       value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="moving_id"
                       value="<?= e($booking['moving_id']) ?>">
                <input type="hidden" name="client_id"
                       value="<?= e($booking['client_id']) ?>">

                <button type="submit">Confirm Cancellation</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
const error = <?= json_encode($error, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const message = <?= json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

if (error) alert(error);
if (message) alert(message);
</script>

</body>
</html>
