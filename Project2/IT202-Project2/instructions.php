
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
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$error = "";
$message = "";
$redirectToUpdate = false;
$booking = null;

$prefillID = $_GET["moving_id"] ?? "";

if (!is_string($prefillID) ||
    ($prefillID !== "" && !ctype_digit($prefillID))) {
    $prefillID = "";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )) {
        http_response_code(403);
        exit("Invalid request.");
    }

    $action = $_POST["action"] ?? "";
    $movingID = trim($_POST["moving_id"] ?? "");
    $clientID = trim($_POST["client_id"] ?? "");

    if (!ctype_digit($movingID) || !ctype_digit($clientID)) {
        $error = "Please enter valid numeric Moving and Client IDs.";
    } else {

        // Verify event belongs to logged-in representative
        $stmt = $pdo->prepare(
            "SELECT m.moving_id, m.client_id,
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

        $stmt->execute([
            $movingID,
            $clientID,
            $_SESSION["rep_id"]
        ]);

        $booking = $stmt->fetch();

        if (!$booking) {
            $error = "Moving event not found. Please check the information.";
        } elseif ($booking["instructions"] !== null) {
            $redirectToUpdate = true;
            $booking = null;
        } elseif ($action === "save") {

            $instructions = trim($_POST["instructions"] ?? "");

            if ($instructions === "" ||
                strlen($instructions) > 5000) {
                $error = "Please enter valid special instructions (maximum 5000 characters).";
            } else {

                try {
                    $insert = $pdo->prepare(
                        "INSERT INTO special_instructions
                         (moving_id, instructions)
                         VALUES (?, ?)"
                    );

                    $insert->execute([
                        $movingID,
                        $instructions
                    ]);

                    $message = "Special instructions were successfully added to Moving ID " . $movingID . ".";
                    $booking = null;

                } catch (PDOException $ex) {
                    error_log($ex->getMessage());
                    $error = "Unable to save special instructions. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Moving On Up - Special Instructions</title>
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

        nav a:hover {
            text-decoration: underline;
        }

        .instructions-container {
            max-width: 650px;
            margin: 35px auto;
            padding: 30px;
            background: rgba(255,255,255,0.96);
            border: 2px solid #245a81;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .instructions-container input,
        .instructions-container textarea {
            width: 100%;
            padding: 11px;
            margin-bottom: 10px;
            border: 1px solid #9eacb8;
            border-radius: 5px;
            font-size: 15px;
        }

        .instructions-container textarea {
            min-height: 130px;
            resize: vertical;
        }

        .instructions-container button {
            width: 100%;
            background: #1876b8;
            color: white;
            border: none;
            padding: 13px;
            margin-top: 15px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
        }

        .instructions-container button:hover {
            background: #105887;
        }

        .booking-info {
            background: #eef5fa;
            border: 1px solid #b5ccdd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        h3 {
            color: #185a8d;
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

<div class="instructions-container">

    <h1>Moving On Up</h1>
    <h2>Special Instructions for the Move</h2>

    <?php if (!$booking): ?>

        <h3>Step 1: Find Moving Event</h3>

        <form action="instructions.php" method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <input type="hidden" name="action" value="verify">

            <label for="moving_id">Moving ID:</label>
            <input type="text"
                   id="moving_id"
                   name="moving_id"
                   value="<?= e($prefillID) ?>"
                   required>

            <label for="client_id">Client ID:</label>
            <input type="text"
                   id="client_id"
                   name="client_id"
                   required>

            <button type="submit">Find Moving Event</button>

        </form>

    <?php else: ?>

        <div class="booking-info">
            <strong>Moving Event Verified!</strong>

            <p>Client:
                <?= e($booking["first_name"] . " " .
                      $booking["last_name"]) ?>
            </p>

            <p>Client ID:
                <?= e($booking["client_id"]) ?>
            </p>

            <p>Moving ID:
                <?= e($booking["moving_id"]) ?>
            </p>
        </div>

        <h3>Step 2: Enter Special Instructions</h3>

        <form action="instructions.php"
              method="POST"
              onsubmit="return confirm('Would you like to add these special instructions?');">

            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <input type="hidden" name="action" value="save">

            <input type="hidden" name="moving_id"
                   value="<?= e($booking['moving_id']) ?>">

            <input type="hidden" name="client_id"
                   value="<?= e($booking['client_id']) ?>">

            <label for="instructions">
                Packing Specifications and Special Requests:
            </label>

            <textarea id="instructions"
                      name="instructions"
                      maxlength="5000"
                      required
                      placeholder="Enter packing materials, fragile items, furniture placement, and other requests..."></textarea>

            <button type="submit">
                Save Special Instructions
            </button>
        </form>

    <?php endif; ?>

</div>

<script>
const error = <?= json_encode(
    $error,
    JSON_HEX_TAG | JSON_HEX_AMP |
    JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const message = <?= json_encode(
    $message,
    JSON_HEX_TAG | JSON_HEX_AMP |
    JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const redirectToUpdate =
    <?= $redirectToUpdate ? "true" : "false" ?>;

if (redirectToUpdate) {
    alert(
        "Special instructions already exist for this moving event. " +
        "You will be redirected to Update Special Instructions."
    );
    window.location.href = "update_instructions.php";

} else if (error) {
    alert(error);

} else if (message) {
    alert(message);
    window.location.href = "instructions.php";
}
</script>

</body>
</html>
