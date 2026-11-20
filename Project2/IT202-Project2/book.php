
<?php
session_start();

// Reset client verification when requested
if (isset($_GET["reset"])) {
    unset($_SESSION["booking_client"]);
    header("Location: book.php");
    exit;
}

// Require representative login
if (!isset($_SESSION["rep_id"])) {
    header("Location: index.html");
    exit;
}

require_once __DIR__ . "/db_connect.php";

// Create CSRF protection token
if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Escape output to prevent HTML injection
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$client = null;
$error = "";
$missingClient = false;
$successID = null;

// Process submitted forms
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validate CSRF token
    if (!hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )) {
        http_response_code(403);
        exit("Invalid form request.");
    }

    $action = $_POST["action"] ?? "";

    // STEP 1: Verify an existing client
    if ($action === "verify") {

        unset($_SESSION["booking_client"]);

        $clientID = trim($_POST["client_id"] ?? "");
        $firstName = trim($_POST["first_name"] ?? "");
        $lastName = trim($_POST["last_name"] ?? "");

        if (
            !ctype_digit($clientID) ||
            !preg_match("/^[A-Za-z][A-Za-z '-]*$/", $firstName) ||
            !preg_match("/^[A-Za-z][A-Za-z '-]*$/", $lastName)
        ) {
            $error = "Please enter valid client information.";
        } else {

            $stmt = $pdo->prepare(
                "SELECT client_id, first_name, last_name
                 FROM clients
                 WHERE client_id = ?
                   AND first_name = ?
                   AND last_name = ?"
            );

            $stmt->execute([
                $clientID,
                $firstName,
                $lastName
            ]);

            $client = $stmt->fetch();

            if (!$client) {
                $missingClient = true;
                $error = "Client account not found.";
            } else {
                $_SESSION["booking_client"] = $client;
            }
        }
    }

    // STEP 2: Book a moving event
    if ($action === "book") {

        $client = $_SESSION["booking_client"] ?? null;

        if (!$client) {
            $error = "Please verify the client first.";
        } else {

            $streetNumber = trim($_POST["street_number"] ?? "");
            $streetName = trim($_POST["street_name"] ?? "");
            $city = trim($_POST["city"] ?? "");
            $state = strtoupper(trim($_POST["state"] ?? ""));
            $zip = trim($_POST["zip"] ?? "");
            $date = trim($_POST["moving_date"] ?? "");
            $weight = $_POST["weight"] ?? "";
            $miles = $_POST["miles"] ?? "";
            $cost = $_POST["cost"] ?? "";

            // Validate moving date
            $validDate = DateTimeImmutable::createFromFormat(
                "!Y-m-d",
                $date
            );

            // Validate all moving information
            if (
                !preg_match("/^[0-9]+[A-Za-z]?$/", $streetNumber) ||
                !preg_match("/^[A-Za-z0-9][A-Za-z0-9 .'-]*$/", $streetName) ||
                !preg_match("/^[A-Za-z][A-Za-z .'-]*$/", $city) ||
                !preg_match("/^[A-Z]{2}$/", $state) ||
                !preg_match("/^[0-9]{5}(-[0-9]{4})?$/", $zip) ||
                !$validDate ||
                $validDate->format("Y-m-d") !== $date ||
                $date < date("Y-m-d") ||
                !is_numeric($weight) ||
                (float)$weight <= 0 ||
                !is_numeric($miles) ||
                (float)$miles < 0 ||
                !is_numeric($cost) ||
                (float)$cost < 0
            ) {
                $error = "Invalid moving information. Please correct the form.";
            } else {

                try {
                    // Prepare database booking statement
                    $stmt = $pdo->prepare(
                        "INSERT INTO moving_events
                        (
                            moving_id,
                            client_id,
                            rep_id,
                            new_street_number,
                            new_street_name,
                            new_city,
                            new_state,
                            new_zip_code,
                            moving_date,
                            total_weight,
                            miles_for_move,
                            total_cost
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    // Generate a random unique Moving ID
                    for ($attempt = 0; $attempt < 10; $attempt++) {

                        $movingID = random_int(100000, 999999);

                        try {
                            $stmt->execute([
                                $movingID,
                                $client["client_id"],
                                $_SESSION["rep_id"],
                                $streetNumber,
                                $streetName,
                                $city,
                                $state,
                                $zip,
                                $date,
                                $weight,
                                $miles,
                                $cost
                            ]);

                            $successID = $movingID;
                            break;

                        } catch (PDOException $ex) {

                            // Retry only if Moving ID already exists
                            if (
                                $ex->getCode() !== "23000" ||
                                ($ex->errorInfo[1] ?? 0) != 1062
                            ) {
                                throw $ex;
                            }
                        }
                    }

                    if ($successID === null) {
                        $error = "Unable to generate a unique Moving ID. Please retry.";
                    } else {
                        // Clear previously verified client
                        unset($_SESSION["booking_client"]);
                        $client = null;
                    }

                } catch (PDOException $ex) {

                    error_log($ex->getMessage());

                    $error = "Booking could not be saved. Please try again.";
                }
            }
        }
    }
}

// Restore verified client for display
if ($client === null && $successID === null) {
    $client = $_SESSION["booking_client"] ?? null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Moving On Up - Book a Move</title>

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

        .booking-container {
            background: rgba(255, 255, 255, 0.96);
            max-width: 650px;
            margin: 35px auto;
            padding: 30px;
            border-radius: 12px;
            border: 2px solid #245a81;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .booking-container input,
        .booking-container select {
            width: 100%;
            padding: 11px;
            margin-bottom: 10px;
            border: 1px solid #9eacb8;
            border-radius: 5px;
            font-size: 15px;
        }

        .booking-container button {
            width: 100%;
            background: #1876b8;
            color: white;
            border: none;
            padding: 13px;
            margin-top: 20px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
        }

        .booking-container button:hover {
            background: #105887;
        }

        .client-info {
            background: #eef5fa;
            border: 1px solid #b5ccdd;
            padding: 14px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .client-info p {
            margin: 8px 0;
        }

        h3 {
            color: #185a8d;
            margin-top: 25px;
        }

        .reset-link {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <!-- Navigation Bar -->
    <nav>
        <a href="search.php">Search Account</a>
        <a href="book.php">Book Move</a>
        <a href="cancel.php">Cancel Move</a>
        <a href="instructions.php">Special Instructions</a>
        <a href="update_instructions.php">Update Instructions</a>
        <a href="create_client.php">Create Client</a>
    </nav>

    <div class="booking-container">

        <h1>Moving On Up</h1>
        <h2>Book a Client's Moving Event</h2>

        <?php if (!$client): ?>

            <!-- Client Verification Form -->

            <h3>Step 1: Verify Client</h3>

            <form method="POST" action="book.php">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($_SESSION['csrf_token']) ?>">

                <input type="hidden"
                       name="action"
                       value="verify">

                <label for="client_id">Client ID:</label>
                <input type="text"
                       id="client_id"
                       name="client_id"
                       required>

                <label for="first_name">Client First Name:</label>
                <input type="text"
                       id="first_name"
                       name="first_name"
                       required>

                <label for="last_name">Client Last Name:</label>
                <input type="text"
                       id="last_name"
                       name="last_name"
                       required>

                <button type="submit">
                    Verify Client
                </button>

            </form>

        <?php else: ?>

            <!-- Verified Client Information -->

            <div class="client-info">
                <strong>Client Verified!</strong>

                <p>
                    <?= e(
                        $client["first_name"] . " " .
                        $client["last_name"]
                    ) ?>
                </p>

                <p>
                    Client ID:
                    <?= e($client["client_id"]) ?>
                </p>
            </div>

            <!-- Moving Event Booking Form -->

            <h3>Step 2: Enter Moving Information</h3>

            <form method="POST"
                  action="book.php"
                  onsubmit="return confirm('Are you sure you want to book this moving event?');">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($_SESSION['csrf_token']) ?>">

                <input type="hidden"
                       name="action"
                       value="book">

                <label for="moving_date">Moving Date:</label>
                <input type="date"
                       id="moving_date"
                       name="moving_date"
                       min="<?= date('Y-m-d') ?>"
                       required>

                <label for="street_number">
                    Destination Street Number:
                </label>
                <input type="text"
                       id="street_number"
                       name="street_number"
                       required>

                <label for="street_name">
                    Destination Street Name:
                </label>
                <input type="text"
                       id="street_name"
                       name="street_name"
                       required>

                <label for="city">Destination City:</label>
                <input type="text"
                       id="city"
                       name="city"
                       required>

                <label for="state">
                    Destination State (2 letters):
                </label>
                <input type="text"
                       id="state"
                       name="state"
                       maxlength="2"
                       required>

                <label for="zip">Destination ZIP Code:</label>
                <input type="text"
                       id="zip"
                       name="zip"
                       required>

                <label for="weight">Total Weight (lbs):</label>
                <input type="number"
                       id="weight"
                       name="weight"
                       min="0.01"
                       step="0.01"
                       required>

                <label for="miles">Miles for Move:</label>
                <input type="number"
                       id="miles"
                       name="miles"
                       min="0"
                       step="0.01"
                       required>

                <label for="cost">Total Cost ($):</label>
                <input type="number"
                       id="cost"
                       name="cost"
                       min="0"
                       step="0.01"
                       required>

                <button type="submit">
                    Book Moving Event
                </button>

            </form>

            <div class="reset-link">
                <a href="book.php?reset=1">
                    Verify Another Client
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

        const missingClient = <?= $missingClient ? "true" : "false" ?>;
        const successID = <?= json_encode($successID) ?>;

        if (missingClient) {
            const retry = confirm(
                "Client cannot be found.\n\n" +
                "Click OK to re-enter the information.\n" +
                "Click Cancel to create a new client account."
            );

            if (!retry) {
                window.location.href = "create_client.php";
            }

        } else if (error) {
            alert(error);
        }

        if (successID !== null) {
            alert(
                "Moving Event Booked!\n\n" +
                "Your Moving ID is: " + successID
            );

            const addInstructions = confirm(
                "Would you like to add special instructions for this move?"
            );

            if (addInstructions) {
                window.location.href =
                    "instructions.php?moving_id=" +
                    encodeURIComponent(successID);
            } else {
                window.location.href = "book.php";
            }
        }
    </script>

</body>
</html>
