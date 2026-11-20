
<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.html");
    exit;
}

require_once __DIR__ . "/db_connect.php";

// Get login information
$firstName = trim($_POST["firstName"] ?? "");
$lastName = trim($_POST["lastName"] ?? "");
$password = $_POST["password"] ?? "";
$repID = trim($_POST["repID"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$email = trim($_POST["email"] ?? "");
$confirmation = isset($_POST["confirmation"]);
$transaction = $_POST["transaction"] ?? "";

// Validate submitted information
$allowedTransactions = [
    "search", "book", "cancel",
    "instructions", "update", "create"
];

if (
    !preg_match("/^[A-Za-z][A-Za-z '-]*$/", $firstName) ||
    !preg_match("/^[A-Za-z][A-Za-z '-]*$/", $lastName) ||
    $password === "" ||
    !preg_match("/^[0-9]{4}$/", $repID) ||
    !preg_match("/^[0-9]{3}[-.]?[0-9]{3}[-.]?[0-9]{4}(?:\s*(?:x|ext\.?)\s*[0-9]+)?$/i", $phone) ||
    ($confirmation && !filter_var($email, FILTER_VALIDATE_EMAIL)) ||
    !in_array($transaction, $allowedTransactions, true)
) {
    exit("Invalid form information. Please go back and correct it.");
}

// Find representative in MySQL
$stmt = $pdo->prepare(
    "SELECT * FROM moving_representatives
     WHERE rep_id = ?
     AND first_name = ?
     AND last_name = ?
     AND phone_number = ?"
);

$stmt->execute([
    $repID,
    $firstName,
    $lastName,
    $phone
]);

$representative = $stmt->fetch();

// Verify representative credentials
if (
    !$representative ||
    !password_verify($password, $representative["password_hash"]) ||
    ($confirmation && strcasecmp($email, $representative["email"]) !== 0)
) {
    exit("Login failed. Please check your representative information.");
}

// Create login session
session_regenerate_id(true);

$_SESSION["rep_id"] = $representative["rep_id"];
$_SESSION["rep_name"] =
    $representative["first_name"] . " " .
    $representative["last_name"];

// Redirect according to selected transaction
$destinations = [
    "search" => "search.php",
    "book" => "book.php",
    "cancel" => "cancel.php",
    "instructions" => "instructions.php",
    "update" => "update_instructions.php",
    "create" => "create_client.php"
];

header("Location: " . $destinations[$transaction]);
exit;
?>
