
<?php
session_start();

// Require representative login
if (!isset($_SESSION["rep_id"])) {
    header("Location: index.html");
    exit;
}

require_once __DIR__ . "/db_connect.php";

// Create CSRF token
if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Escape text for safe HTML output
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$error = "";
$message = "";
$client = null;

// Load client awaiting personal information
if (isset($_SESSION["new_client_id"])) {
    $stmt = $pdo->prepare(
        "SELECT client_id, first_name, last_name
         FROM clients
         WHERE client_id = ?"
    );

    $stmt->execute([$_SESSION["new_client_id"]]);
    $client = $stmt->fetch();

    if (!$client) {
        unset($_SESSION["new_client_id"]);
    }
}

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )) {
        http_response_code(403);
        exit("Invalid form request.");
    }

    $action = $_POST["action"] ?? "";

    // STEP 1: Create Client Account
    if ($action === "create") {

        $clientID = trim($_POST["client_id"] ?? "");
        $firstName = trim($_POST["first_name"] ?? "");
        $lastName = trim($_POST["last_name"] ?? "");

        // Validate client information
        if (
            !preg_match("/^[1-9][0-9]{0,9}$/", $clientID) ||
            (float)$clientID > 2147483647 ||
            !preg_match("/^[A-Za-z][A-Za-z '-]*$/", $firstName) ||
            !preg_match("/^[A-Za-z][A-Za-z '-]*$/", $lastName) ||
            strlen($firstName) > 50 ||
            strlen($lastName) > 50
        ) {
            $error = "Invalid client information. Please correct the form.";
        } else {

            // Check for existing Client ID
            $stmt = $pdo->prepare(
                "SELECT client_id
                 FROM clients
                 WHERE client_id = ?"
            );

            $stmt->execute([$clientID]);

            if ($stmt->fetch()) {
                $error =
                    "A client account with this ID already exists. " .
                    "Please enter a different Client ID.";
            } else {

                try {
                    $insert = $pdo->prepare(
                        "INSERT INTO clients
                         (client_id, first_name, last_name)
                         VALUES (?, ?, ?)"
                    );

                    $insert->execute([
                        $clientID,
                        $firstName,
                        $lastName
                    ]);

                    $_SESSION["new_client_id"] = $clientID;

                    $client = [
                        "client_id" => $clientID,
                        "first_name" => $firstName,
                        "last_name" => $lastName
                    ];

                    $message =
                        "Client account created successfully! " .
                        "Please enter the client's personal information.";

                } catch (PDOException $ex) {
                    error_log($ex->getMessage());
                    $error = "Unable to create client account. " .
                             "Please check the Client ID.";
                }
            }
        }

    // STEP 2: Save Client Personal Information
    } elseif ($action === "personal") {

        $clientID = $_SESSION["new_client_id"] ?? null;

        if (!$clientID || !$client) {
            $error = "Please create a client account first.";
        } else {

            $streetNumber = trim($_POST["street_number"] ?? "");
            $streetName = trim($_POST["street_name"] ?? "");
            $city = trim($_POST["city"] ?? "");
            $state = strtoupper(trim($_POST["state"] ?? ""));
            $zip = trim($_POST["zip_code"] ?? "");
            $phone = trim($_POST["phone_number"] ?? "");

            // Validate personal information
            if (
                !preg_match("/^[0-9]+[A-Za-z]?$/", $streetNumber) ||
                strlen($streetNumber) > 10 ||
                !preg_match("/^[A-Za-z0-9][A-Za-z0-9 .'-]*$/", $streetName) ||
                strlen($streetName) > 100 ||
                !preg_match("/^[A-Za-z][A-Za-z .'-]*$/", $city) ||
                strlen($city) > 50 ||
                !preg_match("/^[A-Z]{2}$/", $state) ||
                !preg_match("/^[0-9]{5}(-[0-9]{4})?$/", $zip) ||
                !preg_match(
                    "/^[0-9]{3}[-.]?[0-9]{3}[-.]?[0-9]{4}$/",
                    $phone
                )
            ) {
                $error =
                    "Invalid personal information. " .
                    "Please check all fields.";
            } else {

                try {
                    // Prevent duplicate personal records
                    $check = $pdo->prepare(
                        "SELECT client_id
                         FROM client_personal_information
                         WHERE client_id = ?"
                    );

                    $check->execute([$clientID]);

                    if ($check->fetch()) {
                        $error =
                            "Personal information already exists " .
                            "for this client.";
                    } else {

                        $insert = $pdo->prepare(
                            "INSERT INTO client_personal_information
                            (
                                client_id,
                                street_number,
                                street_name,
                                city,
                                state,
                                zip_code,
                                phone_number
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?)"
                        );

                        $insert->execute([
                            $clientID,
                            $streetNumber,
                            $streetName,
                            $city,
                            $state,
                            $zip,
                            $phone
                        ]);

                        $message =
                            "Client Personal Information Record " .
                            "created successfully for Client ID " .
                            $clientID . "!";

                        unset($_SESSION["new_client_id"]);
                        $client = null;
                    }

                } catch (PDOException $ex) {
                    error_log($ex->getMessage());
                    $error =
                        "Unable to save client personal information.";
                }
            }
        }

    } else {
        $error = "Invalid form action.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Moving On Up - Create Client</title>

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

        .client-container {
            max-width: 650px;
            margin: 35px auto;
            padding: 30px;
            background: rgba(255,255,255,0.96);
            border: 2px solid #245a81;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .client-container input {
            width: 100%;
            padding: 11px;
            margin-bottom: 10px;
            border: 1px solid #9eacb8;
            border-radius: 5px;
            font-size: 15px;
        }

        .client-container button {
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

        .client-container button:hover {
            background: #105887;
        }

        .client-info {
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

<div class="client-container">

    <h1>Moving On Up</h1>
    <h2>Create a New Client Account</h2>

    <?php if (!$client): ?>

        <!-- STEP 1: Client Account Form -->

        <h3>Step 1: Create Client Account</h3>

        <form method="POST"
              action="create_client.php"
              onsubmit="return confirm('Do you want to create this client account?');">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <input type="hidden"
                   name="action"
                   value="create">

            <label for="client_id">Client ID:</label>
            <input type="number"
                   id="client_id"
                   name="client_id"
                   min="1"
                   max="2147483647"
                   required>

            <label for="first_name">
                Client First Name:
            </label>
            <input type="text"
                   id="first_name"
                   name="first_name"
                   maxlength="50"
                   required>

            <label for="last_name">
                Client Last Name:
            </label>
            <input type="text"
                   id="last_name"
                   name="last_name"
                   maxlength="50"
                   required>

            <button type="submit">
                Create Client Account
            </button>

        </form>

    <?php else: ?>

        <!-- STEP 2: Client Personal Information -->

        <div class="client-info">

            <h3>Client Account Created!</h3>

            <p>
                <strong>Client Name:</strong>
                <?= e(
                    $client["first_name"] . " " .
                    $client["last_name"]
                ) ?>
            </p>

            <p>
                <strong>Client ID:</strong>
                <?= e($client["client_id"]) ?>
            </p>

        </div>

        <h3>Step 2: Enter Personal Information</h3>

        <form method="POST"
              action="create_client.php"
              onsubmit="return confirm('Save this client personal information?');">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <input type="hidden"
                   name="action"
                   value="personal">

            <label for="street_number">
                Street Number:
            </label>
            <input type="text"
                   id="street_number"
                   name="street_number"
                   maxlength="10"
                   required>

            <label for="street_name">
                Street Name:
            </label>
            <input type="text"
                   id="street_name"
                   name="street_name"
                   maxlength="100"
                   required>

            <label for="city">City:</label>
            <input type="text"
                   id="city"
                   name="city"
                   maxlength="50"
                   required>

            <label for="state">
                State (2 letters):
            </label>
            <input type="text"
                   id="state"
                   name="state"
                   maxlength="2"
                   required>

            <label for="zip_code">ZIP Code:</label>
            <input type="text"
                   id="zip_code"
                   name="zip_code"
                   placeholder="07102"
                   required>

            <label for="phone_number">
                Client Phone Number:
            </label>
            <input type="tel"
                   id="phone_number"
                   name="phone_number"
                   placeholder="973-555-0123"
                   required>

            <button type="submit">
                Save Client Personal Information
            </button>

        </form>

    <?php endif; ?>

    <div class="back-link">
        <a href="book.php">Go to Book a Moving Event</a>
    </div>

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
}
</script>

</body>
</html>
