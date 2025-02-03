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
                                <th>MAC Address</th>
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

                        // Output the asset information along with the MAC address
                        echo "<tr>
                                <td>$assetName</td>
                                <td>$serialNumber</td>
                                <td>$modelName</td>
                                <td>$lastLoginDate</td>
                                <td>$recentUserName</td>
                                <td>$macAddress</td>
                              </tr>";
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
    <title>Student Device Lookup</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body> 
    <h1>Lookup Devices by Student ID</h1>
    <form method="post">
        <label for="student_id">Student ID:</label>
        <input type="text" name="student_id" id="student_id" required>
        <button type="submit">Submit</button>
    </form>
</body>
</html>