<?php
// Configuration for IncidentIQ API
$apiKey = getenv('ACCESS_TOKEN'); 
$baseUrl = 'https://seaford.incidentiq.com/api/v1.0'; // Base URL for IncidentIQ API

// Google API credentials
require 'vendor/autoload.php'; // Google API Client Library
use Google\Client;
use Google\Service\Directory;

// Increase memory limit
ini_set('memory_limit', '512M'); // Set memory limit to 512MB
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

        // Return the MAC address if available
        return $device->getMacAddress() ?: "MAC Address not found";
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['student_id'])) {
    $schoolIdNumber = $_POST['student_id'];

    // Step 1: Search for the user by SchoolIdNumber
    $searchUrl = $baseUrl . "/search/v2";
    $searchPayload = json_encode([
        "Query" => $schoolIdNumber,
        "Facets" => 4,
        "IncludeMatchedItem" => false
    ]);

    // Set up the cURL request for the search
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $searchUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey",
        "Accept: application/json",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $searchPayload);

    // Execute the search request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $decodedResponse = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decodedResponse['Items'][0]['Id'])) {
            $userId = $decodedResponse['Items'][0]['Id'];
            $userName = $decodedResponse['Items'][0]['Name'] ?? 'Unknown User';

            // Step 2: Use the UserId to search for assets
            $assetsUrl = $baseUrl . "/assets/for/$userId";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $assetsUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $apiKey",
                "Accept: application/json"
            ]);
            $assetsResponse = curl_exec($ch);
            $assetsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($assetsHttpCode === 200 && $assetsResponse) {
                $assetsDecoded = json_decode($assetsResponse, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($assetsDecoded['Items']) && count($assetsDecoded['Items']) > 0) {
                    echo "<h3>$userName:</h3>";
                    echo "<h3>Assigned Asset Information:</h3>";
                    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>
                            <tr>
                                <th>Asset Name</th>
                                <th>Serial Number</th>
                                <th>Model</th>
                                <th>Last Login Date</th>
                                <th>Recent User</th>
                                <th>Last Seen (Meraki)</th>
                                <th>Recent Device Name (Meraki)</th>
                            </tr>";

                    foreach ($assetsDecoded['Items'] as $asset) {
                        $assetName = $asset['Name'] ?? 'N/A';
                        $serialNumber = $asset['SerialNumber'] ?? 'N/A';
                        $modelName = $asset['Model']['Name'] ?? 'N/A';
                        $externalId = $asset['ExternalId'] ?? 'N/A'; // ExternalId (Google ID)

                        $lastLoginDate = 'N/A';
                        $recentUserName = 'N/A';
                        if (isset($asset['CustomFieldValues']) && is_array($asset['CustomFieldValues'])) {
                            foreach ($asset['CustomFieldValues'] as $field) {
                                if (isset($field['Value']) && !empty($field['Value'])) {
                                    $customFieldsData = json_decode($field['Value'], true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        foreach ($customFieldsData as $customField) {
                                            if (isset($customField['LastLoginDate'])) {
                                                $lastLoginDate = $customField['LastLoginDate'];
                                            }
                                            if (isset($customField['RecentUserEmail'])) {
                                                $recentUserEmail = $customField['RecentUserEmail'];

                                                // Step: Search for the user by their email
                                                $emailSearchUrl = $baseUrl . "/search/v2";
                                                $emailSearchPayload = json_encode([
                                                    "Query" => $recentUserEmail,
                                                    "Facets" => 4,
                                                    "IncludeMatchedItem" => false
                                                ]);

                                                $ch = curl_init();
                                                curl_setopt($ch, CURLOPT_URL, $emailSearchUrl);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                                    "Authorization: Bearer $apiKey",
                                                    "Accept: application/json",
                                                    "Content-Type: application/json"
                                                ]);
                                                curl_setopt($ch, CURLOPT_POST, true);
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, $emailSearchPayload);

                                                $emailResponse = curl_exec($ch);
                                                curl_close($ch);

                                                if ($emailResponse) {
                                                    $emailDecodedResponse = json_decode($emailResponse, true);
                                                    if (json_last_error() === JSON_ERROR_NONE) {
                                                        $recentUserName = $emailDecodedResponse['Items'][0]['Name'] ?? 'Unknown User';
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Get MAC address from Google Admin API
                        $macAddress = getMacAddressByGoogleId($externalId);

                        // Ensure MAC address is in the correct format (xx:xx:xx:xx:xx:xx)
                        if (strlen($macAddress) == 12) {
                            $macAddress = implode(':', str_split($macAddress, 2));
                        }

                        // Query Meraki for client info based on MAC address
                        $networkId = 'L_624311498344250992'; // Your Meraki Network ID
                        $url = "https://api.meraki.com/api/v1/networks/{$networkId}/clients?mac={$macAddress}";

                        // Initialize cURL for Meraki API request
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Authorization: Bearer ' . getenv('MERAKI_API_KEY'),
                            'Accept: application/json'
                        ));

                        // Execute the cURL request
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        // Check for errors
                        if (curl_errno($ch)) {
                            echo 'Curl error: ' . curl_error($ch);
                        } elseif ($httpCode !== 200) {
                            echo "Error: Unable to fetch client details. HTTP Code: {$httpCode}";
                        } else {
                            // Decode the JSON response from Meraki
                            $clients = json_decode($response, true);

                            if (!empty($clients)) {
                                foreach ($clients as $client) {
                                    // Format the last seen time (UTC to EST)
                                    if (isset($client['lastSeen']) && !empty($client['lastSeen'])) {
                                        $date = new DateTime($client['lastSeen'], new DateTimeZone('UTC')); // Original time is in UTC
                                        $date->setTimezone(new DateTimeZone('America/New_York')); // Convert to EST
                                        $lastSeenFormatted = $date->format('F j, Y g:i A'); // EST time format (e.g., February 4, 2025 3:00 PM)
                                    } else {
                                        $lastSeenFormatted = 'N/A'; // If 'lastSeen' is not available, set it to 'N/A'
                                    }

                                    echo "<tr>
                                            <td>$assetName</td>
                                            <td>$serialNumber</td>
                                            <td>$modelName</td>
                                            <td>$lastLoginDate</td>
                                            <td>$recentUserName</td>
                                            <td>$lastSeenFormatted</td>
                                            <td>" . ($client['recentDeviceName'] ?? 'N/A') . "</td>
                                          </tr>";
                                }
                            } else {
                                echo "No client found with MAC Address {$macAddress} in Network '{$networkId}'.<br>";
                            }
                        }
                    }

                    echo "</table>";
                } else {
                    echo "<p>No assets found for this user.</p>";
                }
            } else {
                echo "<p>Error retrieving user assets.</p>";
            }
        } else {
            echo "<p>No UserId found for the provided Student ID.</p>";
        }
    } else {
        echo "<p>Error searching for user. Status Code: $httpCode</p>";
    }
} else {
    echo "<p>Please enter a valid Student ID.</p>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lookup Devices by Student ID</title>
</head>
<body>
    <h2>Lookup Devices by Student ID</h2>
    <form action="" method="POST">
        <label for="student_id">Enter Student ID: </label>
        <input type="text" id="student_id" name="student_id" required>
        <input type="submit" value="Search">
    </form>
</body>
</html>