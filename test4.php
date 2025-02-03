<?php
// Get the API key from the environment variable
$apiKey = getenv('MERAKI_API_KEY');
if (!$apiKey) {
    die("Error: MERAKI_API_KEY environment variable not set.");
}

// Hardcoded network ID (Seaford High School as an example)
$networkId = 'L_624311498344250992';

// Set your local time zone (change to your time zone, e.g., 'America/New_York' for Eastern Time)
date_default_timezone_set('America/New_York');

// Check if the MAC address is submitted via the form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mac_address'])) {
    $macAddress = trim($_POST['mac_address']);

    // Check if MAC address is provided
    if (empty($macAddress)) {
        die("Error: MAC address is required.");
    }

    // Ensure MAC address is in the correct format (xx:xx:xx:xx:xx:xx)
    if (strlen($macAddress) == 12) {
        $macAddress = implode(':', str_split($macAddress, 2));
    }

    // API URL for querying a client by MAC address
    $url = "https://api.meraki.com/api/v1/networks/{$networkId}/clients?mac={$macAddress}";

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json'
    ));

    // Execute the cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Check for errors
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    } elseif ($httpCode !== 200) {
        echo "Error: Unable to fetch client details. HTTP Code: {$httpCode}";
    } else {
        // Decode the JSON response
        $clients = json_decode($response, true);

        if (!empty($clients)) {
            // Output recent device name and last seen
            echo "<h3>Client Information for MAC Address {$macAddress}:</h3>";
            foreach ($clients as $client) {
                // Format the last seen time (UTC to local time)
                if (isset($client['lastSeen']) && !empty($client['lastSeen'])) {
                    $date = new DateTime($client['lastSeen'], new DateTimeZone('UTC')); // Original time is in UTC
                    $date->setTimezone(new DateTimeZone(date_default_timezone_get())); // Convert to local time zone
                    $lastSeenFormatted = $date->format('F j, Y g:i A');
                } else {
                    $lastSeenFormatted = 'N/A';
                }

                echo "Recent Device Name: " . ($client['recentDeviceName'] ?? 'N/A') . "<br>";
                echo "Last Seen: " . $lastSeenFormatted . "<br><br>";
            }
        } else {
            echo "No client found with MAC Address {$macAddress} in Network '{$networkId}'.<br>";
        }
    }

    // Close the cURL session
    curl_close($ch);
} else {
    // Display the form to enter the MAC address
    echo '<form method="POST" action="">
            <label for="mac_address">Enter MAC Address:</label>
            <input type="text" id="mac_address" name="mac_address" required>
            <button type="submit">Submit</button>
          </form>';
}
?>