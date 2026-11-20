
document.getElementById("movingForm").addEventListener("submit", function(event) {

    const firstName = document.getElementById("firstName").value.trim();
    const lastName = document.getElementById("lastName").value.trim();
    const password = document.getElementById("password").value;
    const repID = document.getElementById("repID").value.trim();
    const phone = document.getElementById("phone").value.trim();
    const email = document.getElementById("email").value.trim();
    const confirmation = document.getElementById("confirmation").checked;
    const transaction = document.getElementById("transaction").value;

    // Validate first and last names
    const namePattern = /^[A-Za-z][A-Za-z '-]*$/;

    if (!namePattern.test(firstName) || !namePattern.test(lastName)) {
        event.preventDefault();
        alert("Please enter valid first and last names.");
        return;
    }

    // Validate password
    if (password.length === 0) {
        event.preventDefault();
        alert("Please enter your password.");
        return;
    }

    // Validate representative ID (exactly 4 digits)
    if (!/^\d{4}$/.test(repID)) {
        event.preventDefault();
        alert("Representative ID must contain exactly 4 digits.");
        return;
    }

    // Validate phone number with optional extension
    const phonePattern = /^\d{3}[-.]?\d{3}[-.]?\d{4}(?:\s*(?:x|ext\.?)\s*\d+)?$/i;

    if (!phonePattern.test(phone)) {
        event.preventDefault();
        alert("Please enter a valid 10-digit phone number, with an optional extension.");
        return;
    }

    // Validate email if confirmation is checked
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (confirmation && !emailPattern.test(email)) {
        event.preventDefault();
        alert("Please enter a valid email address for confirmation.");
        return;
    }

    // Validate dropdown selection
    const validTransactions = [
        "search",
        "book",
        "cancel",
        "instructions",
        "update",
        "create"
    ];

    if (!validTransactions.includes(transaction)) {
        event.preventDefault();
        alert("Please select a valid transaction.");
        return;
    }

    // Valid form: browser submits data to login.php
});
