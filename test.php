<?php
require 'vendor/autoload.php'; // Google API Client Library

use Google\Client;
use Google\Service\Directory;

// Increase memory limit
ini_set('memory_limit', '512M'); // Set memory limit to 512MB

// Load Google API credentials
$KEY_FILE_LOCATION = '/var/www/html/credentials.json';
$CUSTOMER_ID = 'C00v19hfe'; // Your Google Workspace Customer ID

// Function to fetch the MAC address of a Chromebook using its Google ID
function getMacAddressByGoogleId($googleId) {
    global $KEY_FILE_LOCATION, $CUSTOMER_ID;

    $client = new Client();
    $client->setAuthConfig($KEY_FILE_LOCATION);
    $client->addScope(Google\Service\Directory::ADMIN_DIRECTORY_DEVICE_CHROMEOS_READONLY);
    $client->setSubject('joshua.griffin@seaford.k12.de.us'); // Replace with admin email

    $service = new Directory($client);

    try {
        // Retrieve device by Google ID (resourceId)
        $device = $service->chromeosdevices->get($CUSTOMER_ID, $googleId);

        // Return the MAC address
        return $device->getMacAddress() ?: "MAC Address not found";
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST["googleId"])) {
    $googleId = trim($_POST["googleId"]);
    $macAddress = getMacAddressByGoogleId($googleId);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chromebook MAC Lookup</title>
</head>
<body>
    <h2>Enter Chromebook Google ID</h2>
    <form method="POST">
        <input type="text" name="googleId" required>
        <button type="submit">Lookup</button>
    </form>
    <?php if (!empty($macAddress)): ?>
        <h3>Result: <?php echo htmlspecialchars($macAddress); ?></h3>
    <?php endif; ?>
</body>
</html>