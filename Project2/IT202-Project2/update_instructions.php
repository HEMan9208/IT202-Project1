
<?php
session_start();

// Require representative login
if (!isset($_SESSION["rep_id"])) {
    header("Location: index.html");
    exit;
}

require_once __DIR__ . "/db_connect.php";

// CSRF protection
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
$booking = null;

// Process submitted forms
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )) {
        http_response_code(403);
        exit("Invalid form request.");
    }

    $action = $_POST["action"] ?? "";
    $movingID = trim($_POST["moving_id"] ?? "");
    $clientID = trim($_POST["client_id"] ?? "");

    if (
        !ctype_digit($movingID) ||
        !ctype_digit($clientID)
    ) {
        $error = "Please enter valid numeric Moving and Client IDs.";
    } else {

        // Check that booking belongs to logged-in representative
        $stmt = $pdo->prepare(
            "SELECT
                m.moving_id,
                m.client_id,
                m.rep_id,
                c.first_name,
                c.last_name,
                s.instruction_id,
                s.instructions
             FROM moving_events m
             JOIN clients c
                ON m.client_id = c.client_id
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
            $error = "Moving event not found. Please check the Moving ID and Client ID.";
        } elseif ($booking["instruction_id"] === null) {
            $error = "No special instructions exist for this moving event. Please add them first.";
            $booking = null;
        } elseif ($action === "update") {

            $newInstructions = trim(
                $_POST["instructions"] ?? ""
            );

            // Validate new instructions
            if (
                $newInstructions === "" ||
                strlen($newInstructions) > 5000
            ) {
                $error = "Please enter valid instructions, maximum 5000 bytes.";
            } else {

                try {
                    // Update only instructions for this representative's booking
                    $update = $pdo->prepare(
                        "UPDATE special_instructions s
                         INNER JOIN moving_events m
                            ON s.moving_id = m.moving_id
                         SET s.instructions = ?
                         WHERE m.moving_id = ?
                           AND m.client_id = ?
                           AND m.rep_id = ?"
                    );

                    $update->execute([
                        $newInstructions,
                        $movingID,
                        $clientID,
                        $_SESSION["rep_id"]
                    ]);

                    $message =
                        "Special instructions successfully updated for Moving ID " .
                        $movingID . ".";

                    $booking = null;

                } catch (PDOException $ex) {
                    error_log($ex->getMessage());
                    $error = "Unable to update special instructions.";
                }
            }
        } elseif ($action !== "verify") {
            $error = "Invalid action.";
            $booking = null;
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

    <title>Moving On Up - Update Special Instructions</title>
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

        .update-container {
            max-width: 650px;
            margin: 35px auto;
            padding: 30px;
            background: rgba(255,255,255,0.96);
            border: 2px solid #245a81;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .update-container input,
        .update-container textarea {
            width: 100%;
            padding: 11px;
            margin-bottom: 10px;
            border: 1px solid #9eacb8;
            border-radius: 5px;
            font-size: 15px;
        }

        .update-container textarea {
            min-height: 160px;
            resize: vertical;
        }

        .update-container button {
            width: 100%;
            padding: 13px;
            margin-top: 15px;
            background: #1876b8;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }

        .update-container button:hover {
            background: #105887;
        }

        .booking-info {
            background: #eef5fa;
            padding: 15px;
            border: 1px solid #b5ccdd;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        h3 {
            color: #185a8d;
        }

        .back-link {
            text-align: center;
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

<div class="update-container">

    <h1>Moving On Up</h1>
    <h2>Update Special Instructions</h2>

    <?php if (!$booking): ?>

        <h3>Step 1: Find Moving Event</h3>

        <form method="POST"
              action="update_instructions.php">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <input type="hidden"
                   name="action"
                   value="verify">

            <label for="moving_id">Moving ID:</label>
            <input type="text"
                   id="moving_id"
                   name="moving_id"
                   required>

            <label for="client_id">Client ID:</label>
            <input type="text"
                   id="client_id"
                   name="client_id"
                   required>

            <button type="submit">
                Find Special Instructions
            </button>

        </form>

    <?php else: ?>

        <div class="booking-info">
            <h3>Moving Event Found!</h3>

            <p>
                <strong>Client:</strong>
                <?= e(
                    $booking["first_name"] . " " .
                    $booking["last_name"]
                ) ?>
            </p>

            <p>
                <strong>Client ID:</strong>
                <?= e($booking["client_id"]) ?>
            </p>

            <p>
                <strong>Moving ID:</strong>
                <?= e($booking["moving_id"]) ?>
            </p>

            <p>
                <strong>Representative ID:</strong>
                <?= e($booking["rep_id"]) ?>
            </p>
        </div>

        <h3>Step 2: Edit Special Instructions</h3>

        <form method="POST"
              action="update_instructions.php"
              onsubmit="return confirm('Are you sure you want to update these special instructions?');">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <input type="hidden"
                   name="action"
                   value="update">

            <input type="hidden"
                   name="moving_id"
                   value="<?= e($booking['moving_id']) ?>">

            <input type="hidden"
                   name="client_id"
                   value="<?= e($booking['client_id']) ?>">

            <label for="instructions">
                Current Special Instructions (Edit Below):
            </label>

            <textarea
                id="instructions"
                name="instructions"
                maxlength="5000"
                required><?= e($booking["instructions"]) ?></textarea>

            <button type="submit">
                Update Special Instructions
            </button>

        </form>

        <div class="back-link">
            <a href="update_instructions.php">
                Find Another Moving Event
            </a>
        </div>

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

if (error) {
    alert(error);
}

if (message) {
    alert(message);
    window.location.href = "update_instructions.php";
}
</script>

</body>
</html>
